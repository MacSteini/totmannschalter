<?php

/**
 * totman – recipients, reusable messages, and optional download files
 *
 * Project: https://github.com/macsteini/totmannschalter
 * Licence: MIT (see LICENCE)
 *
 * You may either copy this file to totman-recipients.php or intentionally keep
 * this exact .dist filename as your configured recipients_file.
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
 * - All file paths are relative to `download_base_dir` from the effective config.
 * - With the shipped default, `shared/letter.pdf` means
 *   `/var/lib/totman/files/shared/letter.pdf`.
 * - Download validity is global via `download_valid_days` in the effective config.
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
 * Example file alias:
 *
 * $files = [
 *     'letter' => 'shared/letter.pdf',
 * ];
 *
 * Example message:
 *
 * $messages = [
 *     'default' => [
 *         'subject' => '[totman] Escalation message',
 *         'body' => "Hello {RECIPIENT_NAME},\n\n{ACK_BLOCK}\n\n{DOWNLOAD_LINKS}",
 *     ],
 * ];
 *
 * Example recipient:
 *
 * $recipients = [
 *     ['Jane Doe', 'Jane Doe <jane@example.com>', 'default', ['letter']],
 * ];
 */

declare(strict_types=1);

$files = [];

$messages = [];

$recipients = [];

return ['files' => $files, 'messages' => $messages, 'recipients' => $recipients];
