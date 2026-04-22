<?php

/**
 * totmannschalter – recipients, reusable messages, and optional download files
 *
 * Project: https://github.com/macsteini/totmannschalter
 * Licence: MIT (see LICENCE)
 *
 * This file is the operator-facing recipient template:
 * - `$files` defines each downloadable file once
 * - `$messages` defines reusable escalation subjects/bodies and optional single-use warnings
 * - `$recipients` assigns one mailbox per row
 *
 * Practical order for editing this file:
 * 1. define each reusable file once in `$files`
 * 2. write each reusable mail in `$messages`
 * 3. assign message keys and file aliases in `$recipients`
 *
 * Recipient row format (fixed order):
 * 1. personal name for mail text (`{RECIPIENT_NAME}`)
 * 2. mailbox used for the actual `To:` header
 * 3. message key
 * 4. optional list of normal file aliases
 * 5. optional list of single-use file aliases
 *
 * Practical meaning of fields 4 and 5:
 * - field 4 = normal download links
 * - field 5 = single-use download links
 * - you never write `single_use=true` yourself in this file
 * - if field 5 is omitted, everything stays on the safer normal-download default
 *
 * Field 2 accepts exactly these mailbox forms:
 * - `recipient@example.com`
 * - `<recipient@example.com>`
 * - `Recipient Name <recipient@example.com>`
 * - If you want the simplest visible `To:` header, keep the actual greeting
 * in field 1 and use only the address in field 2.
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
 * - `{DOWNLOAD_LINKS}`
 *
 * Practical placeholder rules:
 * - keep `{ACK_BLOCK}` in a message body if that recipient should be able to confirm receipt
 * - omit `{ACK_BLOCK}` only if you intentionally do not want an ACK link in that message
 * - keep `{DOWNLOAD_LINKS}` in the message body if that recipient should receive download links
 * - add `single_use_notice` to the message only if that message is used with field 5
 * - the runtime prints `single_use_notice` directly above each affected single-use URL
 *
 * Important:
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
'subject' => '[totmann] EXAMPLE TEMPLATE – escalation message',
'body' => <<<TXT
Hello {RECIPIENT_NAME},

This is an example escalation message for totmann.
Please replace it with your own wording before production use.

You are receiving this message because the sender did not complete the required confirmation in time.

{ACK_BLOCK}

{DOWNLOAD_LINKS}
TXT,
],
'jane' => [
'subject' => '[totmann] EXAMPLE TEMPLATE – personal message',
'body' => <<<TXT
Dear {RECIPIENT_NAME},

This is an example of a more personal escalation message.
Please replace it with your own wording before production use.

If you are reading this, the sender did not complete the required confirmation in time.

{ACK_BLOCK}

{DOWNLOAD_LINKS}
TXT,
],
'john' => [
'subject' => '[totmann] EXAMPLE TEMPLATE – message with documents',
'single_use_notice' => 'Please save this file straight away. This download link works only once.',
'body' => <<<TXT
Hello {RECIPIENT_NAME},

This is an example escalation message for document delivery.
Please replace it with your own wording before production use.

The files below are included as part of this message.

{ACK_BLOCK}

{DOWNLOAD_LINKS}
TXT,
],
];

$recipients = [
// Simplest case: one recipient, one message, no download links.
['Recipient 1', 'recipient1@example.com', 'default'],

// Normal downloads only: put file aliases into field 4.
['Jane Doe', 'Jane Doe <recipient2@example.com>', 'jane', ['letter', 'contacts']],

// Mixed case: field 4 stays normal, field 5 becomes single-use.
// Here `letter` can be downloaded normally, while `photos` is limited to one successful download.
// Because field 5 is used here, message `john` also defines `single_use_notice`.
['John Doe', '<recipient3@example.com>', 'john', ['letter'], ['photos']],

// The same file can be assigned to another recipient by repeating the same alias.
['Alex Example', 'alex@example.com', 'default', ['letter']],
];

return ['files' => $files, 'messages' => $messages, 'recipients' => $recipients];
