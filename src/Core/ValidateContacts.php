<?php

namespace RioSlum\HiringTest\Core;

use RioSlum\HiringTest\Facades\Str;

class ValidateContacts
{

    private int $valid = 0;
    private int $invalid = 0;

    public function handle(array $contacts): array
    {
        $newContacts = [];
         $contacts = array_map(function (array $contact) {
            $email = trim(mb_strtolower($contact['email']));
            return [
                ...$contact,
                'email' => $email,

            ];
        }, $contacts);

         foreach ($contacts as $contact) {
             if(!$contact['email'] || !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
                 $this->invalid++;
             }
             else{
                 $this->valid++;
                 $newContacts[] = $contact;
             }
         }

        return [
            'valid' => $this->valid,
            'invalid' => $this->invalid,
            'contacts' => $newContacts
        ];
    }



}