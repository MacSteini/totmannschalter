<?php

/**
 * totmannschalter – recipients, reusable messages, and optional download files
 *
 * Project: https://github.com/MacSteini/totmannschalter
 * Licence: MIT (see LICENCE)
 *
 * This file is intentionally flat:
 * - `$files` defines each downloadable file once
 * - `$messages` defines reusable escalation subjects/bodies
 * - `$recipients` assigns one mailbox per row
 *
 * Recipient row format (fixed order):
 * 1. personal name for mail text (`{RECIPIENT_NAME}`)
 * 2. mailbox used for the actual `To:` header
 * 3. message key
 * 4. optional list of normal file aliases
 * 5. optional list of single-use file aliases
 *
 * Field 2 accepts exactly these mailbox forms:
 * - `recipient@example.com`
 * - `<recipient@example.com>`
 * - `Recipient Name <recipient@example.com>`
 *
 * Important rules:
 * - The first 3 values in every recipient row are mandatory.
 * - A row with only 2 values is invalid and will not be sent.
 * - Field 3 is mandatory and must point to an existing message in `$messages`.
 * - `single_use=false` is always the implicit default.
 * - Use field 5 only for the special case where a file must be single-use.
 * - File aliases and message keys must match `^[a-z0-9_-]+$`.
 * - All file paths are relative to `download_base_dir` from `totmann.inc.php`.
 * - Download validity is global via `download_valid_days` in `totmann.inc.php`.
 *
 * Supported mail placeholders:
 * - `{LAST_CONFIRM_ISO}`
 * - `{CYCLE_START_ISO}`
 * - `{DEADLINE_ISO}`
 * - `{RECIPIENT_NAME}`
 * - `{ACK_BLOCK}`
 * - `{ACK_URL}`
 * - `{DOWNLOAD_NOTICE}`
 * - `{DOWNLOAD_LINKS}`
 *
 * IMPORTANT:
 * - The subjects and bodies below are examples only.
 * - Replace them with your own real texts before production use.
 */

declare(strict_types=1);

$files = [
    'letter' => 'shared/letter.pdf',
    'contacts' => 'shared/contacts.txt',
    'photos' => 'shared/family-photos.zip',
];

$messages = [
    'default' => [
        'subject' => '[totmannschalter] EXAMPLE ONLY – replace before production use',
        'body' => <<<TXT
[EXAMPLE TEXT – REPLACE BEFORE PRODUCTION USE]

Hello {RECIPIENT_NAME},

Please review the original email carefully.

{ACK_BLOCK}

{DOWNLOAD_NOTICE}

{DOWNLOAD_LINKS}
TXT,
    ],
    'jane' => [
        'subject' => '[totmannschalter] EXAMPLE ONLY – personal message template',
        'body' => <<<TXT
[EXAMPLE TEXT – REPLACE BEFORE PRODUCTION USE]

Dear {RECIPIENT_NAME},

Please review the original email carefully.

{ACK_BLOCK}

{DOWNLOAD_NOTICE}

{DOWNLOAD_LINKS}
TXT,
    ],
    'john' => [
        'subject' => '[totmannschalter] EXAMPLE ONLY – document delivery template',
        'body' => <<<TXT
[EXAMPLE TEXT – REPLACE BEFORE PRODUCTION USE]

Hello {RECIPIENT_NAME},

Please read the note below.

{DOWNLOAD_NOTICE}

{DOWNLOAD_LINKS}
TXT,
    ],
];

$recipients = [
    ['Recipient 1', 'recipient1@example.com', 'default'],
    ['Jane Doe', 'Jane Doe <recipient2@example.com>', 'jane', ['letter', 'contacts']],
    ['John Doe', '<recipient3@example.com>', 'john', ['letter', 'photos']],
];

return [
    'files' => $files,
    'messages' => $messages,
    'recipients' => $recipients,
];
