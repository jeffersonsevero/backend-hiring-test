<?php

declare(strict_types = 1);

namespace RioSlum\HiringTest;

use JsonException;
use RioSlum\HiringTest\Core\{HandleDuplicates, ValidateContacts};
use RuntimeException;

class ContactImporter
{
    private ValidateContacts $validator;

    private HandleDuplicates $deduplicator;

    public function __construct(
        private MockCrmClient $client,
        private int $batchSize = 3,
        private int $maxRetries = 2,
        ?ValidateContacts $validator = null,
        ?HandleDuplicates $deduplicator = null,
    ) {
        $this->validator    = $validator ?? new ValidateContacts();
        $this->deduplicator = $deduplicator ?? new HandleDuplicates();
    }

    public function run(string $inputPath, string $outputPath): array
    {
        $contacts = $this->readContacts($inputPath);
        $result   = $this->emptyResult();

        $result['summary']['total_records'] = count($contacts);

        $validation                           = $this->validator->handle($contacts);
        $result['summary']['valid_records']   = $validation['valid'];
        $result['summary']['invalid_records'] = $validation['invalid'];
        $result['skipped']                    = $validation['skipped'];

        $deduplication                          = $this->deduplicator->handle($validation['contacts']);
        $result['summary']['duplicates_merged'] = $deduplication['duplicates'];

        $this->importContacts($deduplication['contacts'], $result);
        $this->writeReport($outputPath, $result);

        return $result;
    }

    private function importContacts(array $contacts, array &$result): void
    {
        foreach (array_chunk($contacts, $this->batchSize) as $chunk) {
            foreach ($chunk as $contact) {
                $result['summary']['attempted_imports']++;

                $import = $this->importContact($contact);

                if ($import['success']) {
                    $result['summary']['successful_imports']++;
                    $result['imported'][] = $contact;

                    continue;
                }

                $result['summary']['failed_imports']++;
                $result['failed'][] = [
                    'contact'  => $contact,
                    'reason'   => $import['reason'],
                    'attempts' => $import['attempts'],
                    'response' => $import['response'],
                ];
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
                    'success'  => true,
                    'attempts' => $attempt,
                    'contact'  => $contact,
                    'response' => $response,
                ];
            }

            $status = (int) ($response['status'] ?? 0);

            if ($status === 400) {
                return [
                    'success'  => false,
                    'attempts' => $attempt,
                    'contact'  => $contact,
                    'response' => $response,
                    'reason'   => 'permanent failure',
                ];
            }

            if ($status === 429) {
                if ($attempt > $this->maxRetries) {
                    break;
                }

                $retryAfter = max(0, (int) ($response['retry_after'] ?? 1));
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
                'success'  => false,
                'attempts' => $attempt,
                'contact'  => $contact,
                'response' => $response,
                'reason'   => 'unexpected_failure',
            ];
        }

        return [
            'success'  => false,
            'attempts' => $attempt,
            'contact'  => $contact,
            'response' => $response ?? null,
            'reason'   => 'max_retries_exceeded',
        ];
    }

    private function readContacts(string $inputPath): array
    {
        if (!is_file($inputPath) || !is_readable($inputPath)) {
            throw new RuntimeException("Input file is not readable: {$inputPath}");
        }

        $contents = file_get_contents($inputPath);

        if ($contents === false) {
            throw new RuntimeException("Unable to read input file: {$inputPath}");
        }

        try {
            $contacts = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                "Input file contains invalid JSON: {$inputPath}",
                previous: $exception,
            );
        }

        if (!is_array($contacts)) {
            throw new RuntimeException('Input JSON must contain an array of contacts.');
        }

        return $contacts;
    }

    private function writeReport(string $outputPath, array $result): void
    {
        $directory = dirname($outputPath);

        if (!is_dir($directory)) {
            throw new RuntimeException("Output directory does not exist: {$directory}");
        }

        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        if (file_put_contents($outputPath, $json) === false) {
            throw new RuntimeException("Unable to write output file: {$outputPath}");
        }
    }

    private function emptyResult(): array
    {
        return [
            'summary' => [
                'total_records'      => 0,
                'valid_records'      => 0,
                'invalid_records'    => 0,
                'duplicates_merged'  => 0,
                'attempted_imports'  => 0,
                'successful_imports' => 0,
                'failed_imports'     => 0,
            ],
            'imported' => [],
            'failed'   => [],
            'skipped'  => [],
        ];
    }
}
