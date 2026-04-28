# Contact Importer

## How to Run

Install the project dependencies:

```bash
composer install
```

Run the contact import:

```bash
php import.php
```

The command reads contacts from:

```bash
data/contacts.json
```

And writes the import report to:

```bash
output/import-results.json
```

To run the automated tests:

```bash
./vendor/bin/pest
```

## What Was Implemented

This project implements a small PHP contact import workflow.

The importer:

- reads contacts from a JSON file;
- normalizes email addresses;
- skips contacts without a valid email;
- merges duplicate contacts by email;
- keeps the most complete data when duplicates are found;
- processes contacts in batches;
- sends each cleaned contact to the mock CRM client;
- retries temporary CRM failures;
- handles rate limit responses;
- writes a JSON report with summary, imported, failed, and skipped records.

The main workflow lives in `src/ContactImporter.php`.

Supporting classes:

- `src/Core/ValidateContacts.php` validates and normalizes contacts;
- `src/Core/HandleDuplicates.php` merges duplicate contacts;
- `src/MockCrmClient.php` simulates the CRM integration.

## Assumptions

- Email is the unique identifier used to detect duplicate contacts.
- Email normalization is limited to trimming spaces and converting to lowercase.
- Contacts without a valid email should not be sent to the CRM.
- For duplicate contacts, the most complete field value should be kept.
- A two-letter state value is preferred over a longer state name because all the other contacts have two-letter state values.


## What I Would Improve With More Time

- Add a configurable logger for import progress and CRM failures.
- Move retry behavior into a dedicated retry policy class.

