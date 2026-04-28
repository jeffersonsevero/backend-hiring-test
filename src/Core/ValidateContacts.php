<?php

declare(strict_types = 1);

namespace RioSlum\HiringTest\Core;

class ValidateContacts
{
    public function handle(array $contacts): array
    {
        $validContacts   = [];
        $skippedContacts = [];

        foreach ($contacts as $contact) {
            if (!is_array($contact)) {
                $skippedContacts[] = [
                    'contact' => $contact,
                    'reason'  => 'invalid_contact_format',
                ];

                continue;
            }

            $contact = $this->normalize($contact);

            if (!$this->hasValidEmail($contact)) {
                $skippedContacts[] = [
                    'contact' => $contact,
                    'reason'  => 'invalid_email',
                ];

                continue;
            }

            $validContacts[] = $contact;
        }

        return [
            'valid'    => count($validContacts),
            'invalid'  => count($skippedContacts),
            'contacts' => $validContacts,
            'skipped'  => $skippedContacts,
        ];
    }

    private function normalize(array $contact): array
    {
        return [
            ...$contact,
            'email' => trim(mb_strtolower((string) ($contact['email'] ?? ''))),
        ];
    }

    private function hasValidEmail(array $contact): bool
    {
        return $contact['email'] !== ''
            && filter_var($contact['email'], FILTER_VALIDATE_EMAIL) !== false;
    }
}
