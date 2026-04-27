<?php

declare(strict_types=1);

namespace RioSlum\HiringTest;


use RioSlum\HiringTest\Core\HandleDuplicates;
use RioSlum\HiringTest\Core\ValidateContacts;


class ContactImporter
{


    private array $result = [
        'summary' => [
            'total_records' => 0,
            'valid_records' => 0,
            'invalid_records' => 0,
            'duplicates_merged' => 0,
            'attempted_imports' => 0,
            'successful_imports' => 0,
            'failed_imports' => 0,
        ],
        'imported' => [],
        'failed' => [],
        'skipped' => [],
    ];

    public function __construct(
        private MockCrmClient         $client,
        private int                   $batchSize = 3,
        private int                   $maxRetries = 2

    )
    {
    }

    public function run(string $inputPath, string $outputPath): array
    {
        // TODO: Implement the import workflow.
        //
        // Suggested steps:
        // 1. Read contacts from $inputPath.
        $contacts = json_decode(file_get_contents($inputPath), true);
        $this->result['summary']['total_records'] = count($contacts);
        $resultFromValidContacts = (new ValidateContacts())->handle($contacts);
        $this->result['summary']['valid_records'] = $resultFromValidContacts['valid'];
        $this->result['summary']['invalid_records'] = $resultFromValidContacts['invalid'];
        $contacts = (new HandleDuplicates())->handle($resultFromValidContacts['contacts']);
        $this->result['summary']['duplicates_merged'] = $contacts['duplicates'];
        $this->batchProcess($contacts['contacts']);


//        $contacts = (new ValidateContacts())->handle($contacts)['contacts'];

//        $newContacts = $this->deduplicator->handle($contacts);
//        $this->validRecords = count($newContacts);
//        $this->invalidRecords = count($contacts) - $this->validRecords;
//
//        $this->importContact($contacts);
        // 2. Validate and normalize emails.
        // 3. Skip invalid contacts.
        // 4. Merge duplicates by email.
        // 5. Process contacts in batches.
        // 6. Send each contact to MockCrmClient.
        // 7. Retry temporary failures.
        // 8. Handle rate limit responses.
        // 9. Write a JSON report to $outputPath.


        file_put_contents($outputPath, json_encode($this->result, JSON_PRETTY_PRINT));

        return $this->result;
    }


    private function batchProcess(array $contacts)
    {
        foreach (array_chunk($contacts, $this->batchSize) as $chunk) {
            foreach ($chunk as $contact) {
                $this->result['summary']['attempted_imports']++;
                $response = $this->importContact($contact);
                if($response['success']) {
                    $this->result['summary']['successful_imports']++;
                    $this->result['imported'][] = $contact;
                }

                else{
                    $this->result['summary']['failed_imports']++;
                    $this->result['failed'][] = $contact;
                }
            }

        }

    }

    private function importContact(array $contact): array
    {
        $attempt = 0;

        while ($attempt <= $this->maxRetries) {
            $attempt++;
            $response = $this->client->sendContact($contact);

            if ($response['success'] === true) {
                return [
                    'success' => true,
                    'attempts' => $attempt,
                    'contact' => $contact,
                    'response' => $response,
                ];
            }
            $status = $response['status'];

            if ($status === 400) {
                return [
                    'success' => false,
                    'attempts' => $attempt,
                    'contact' => $contact,
                    'response' => $response,
                    'reason' => 'permanent failure',
                ];
            }

            if ($status === 429) {
                $retryAfter = (int)$response['retry_after'] ?? 1;
                sleep($retryAfter);
                continue;
            }

            if ($status >= 500) {
                if ($attempt > $this->maxRetries) {
                    break;
                }
                sleep(1);
                continue;
            }
            return [
                'success' => false,
                'attempts' => $attempt,
                'contact' => $contact,
                'response' => $response,
                'reason' => 'unexpected_failure',
            ];


        }
        return [
            'success' => false,
            'attempts' => $attempt,
            'contact' => $contact,
            'response' => $response ?? null,
            'reason' => 'max_retries_exceeded',
        ];


    }
}
