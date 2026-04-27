<?php

namespace RioSlum\HiringTest\Core;

class HandleDuplicates
{

    private int $duplicates = 0;

    /**
     * @throws \Exception
     */
    public function handle(array $contacts)
    {

        $uniqueContacts = [];

        foreach ($contacts as $contact) {
            $email = $contact['email'];

            if (!isset($uniqueContacts[$email])) {
                $uniqueContacts[$email] = $contact;
                continue;
            }

            $uniqueContacts[$email] = $this->bestContact($uniqueContacts[$email], $contact);
            $this->duplicates++;
        }

        return [
            'duplicates' => $this->duplicates,
            'contacts' => array_values($uniqueContacts),
        ];
    }

    private function bestContact(array $incomingContact, array $newContact)
    {
        if ($incomingContact['email'] !== $newContact['email']) {
            throw new \Exception(message: 'The emails need to be equal');
        }

        $bestContact = $incomingContact;

        foreach ($newContact as $field => $newValue) {
            $bestContact[$field] = $this->pickBestFieldValue(
                $field,
                $bestContact[$field] ?? null,
                $newValue,
            );
        }

        return $bestContact;
    }

    private function pickBestFieldValue(string $field, mixed $currentValue, mixed $newValue): mixed
    {
        if ($this->isEmpty($currentValue)) {
            return $newValue;
        }

        if ($this->isEmpty($newValue)) {
            return $currentValue;
        }

        if ($field === 'state') {
            return $this->pickBestState($currentValue, $newValue);
        }

        return $this->pickMostCompleteValue($currentValue, $newValue);
    }

    private function pickBestState(mixed $currentValue, mixed $newValue): mixed
    {
        $currentIsStateCode = $this->isTwoLetterStateCode($currentValue);
        $newIsStateCode = $this->isTwoLetterStateCode($newValue);

        if ($currentIsStateCode && !$newIsStateCode) {
            return $currentValue;
        }

        if (!$currentIsStateCode && $newIsStateCode) {
            return $newValue;
        }

        return $this->pickMostCompleteValue($currentValue, $newValue);
    }

    private function pickMostCompleteValue(mixed $currentValue, mixed $newValue): mixed
    {
        if (!is_string($currentValue) || !is_string($newValue)) {
            return $currentValue;
        }

        return mb_strlen($newValue) > mb_strlen($currentValue)
            ? $newValue
            : $currentValue;
    }

    private function isTwoLetterStateCode(mixed $value): bool
    {
        return is_string($value) && mb_strlen($value) === 2;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '';
    }


}
