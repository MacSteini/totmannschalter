# totmannschalter – Mail delivery notes
## Mail delivery (prerequisite)
The script sends mail via the configured sendmail binary (`sendmail_path` in `totmann.inc.php`) in sendmail-compatible mode.

Manual pipe test:
```sh
# Adjust `/usr/sbin/sendmail` to match totmann.inc.php sendmail_path
printf "From: test <test@example.com>\nTo: you@example.com\nSubject: pipe-test\n\nhello\n" | /usr/sbin/sendmail -i -- you@example.com
echo $?
```
## SMTPUTF8 pitfalls (some recipients reject)
Some receiving servers reject messages unless your sending MTA offers SMTPUTF8 when headers contain raw non-ASCII. This project RFC2047-encodes headers to avoid SMTPUTF8 in typical cases, but ASCII-only `mail_from` remains the safest option.

Fix options (pick one):
- use an ASCII-only `mail_from` (recommended for best compatibility)
- if your mail setup still emits SMTPUTF8, ensure your outbound MTA supports and advertises SMTPUTF8 (advanced; depends on your mail setup)
## Deliverability (practical)
Even with SPF/DKIM/DMARC, identical short mails sent frequently can look spammy, especially to large providers like Google or Microsoft.

Suggestions:
- during real use, keep reminder frequency human-scale (hours, not minutes)
- avoid repeating identical subject/body too often, especially during tests
- use an ASCII-only display name in `mail_from` (some filters dislike unusual symbols even if technically valid)
- consider adding a stable plain-text footer (what this is and why the recipient gets it) so content looks less automated
- for tests, use the provided test preset in [Timing](Timing.md "Timing model and presets")
## Reminder mails (`to_self`)
Reminder mails are configured only in `totmann.inc.php`.

Rules:
- `to_self` is a list
- each list entry must be exactly one mailbox string
- reminder mails are always sent individually (one mail per `to_self` entry)
- do not put comma-separated recipient lists into one string
- display names that contain commas are emitted safely in the `To:` header as one mailbox, not as a fake multi-recipient list

Correct:
```php
'to_self' => [
    'Alice <alice@example.com>',
    'Bob <bob@example.com>',
],
```

Do not do this:
```php
'to_self' => [
    'Alice <alice@example.com>, Bob <bob@example.com>',
],
```
## The recipient file at a glance
Everything recipient-related now lives in `totmann-recipients.php`.

The file has exactly 3 top-level areas:
- `$files` => define each downloadable file once
- `$messages` => define reusable escalation subjects and bodies
- `$recipients` => assign one mailbox, one message key, and optional files per row

Practical editing order:
1. define file aliases in `$files`
2. write reusable mail texts in `$messages`
3. assign one message key and optional file aliases in `$recipients`

Each recipient row follows this fixed order:
1. personal name for `{RECIPIENT_NAME}`
2. mailbox used for the real `To:` header
3. message key
4. optional list of normal file aliases
5. optional list of single-use file aliases

Important rules:
- the first 3 values are always mandatory
- field 2 may be:
	- `recipient@example.com`
	- `<recipient@example.com>`
	- `Recipient Name <recipient@example.com>`
- field 3 must always reference an existing message key in `$messages`
- field 4 is the normal/safe default for downloads
- field 5 is only for one-time download links
- you never write `single_use=true` yourself in this file
- if field 5 is omitted, everything stays on the normal-download default
## Enduser example for `totmann-recipients.php`
```php
$files = [
    'letter' => 'shared/letter.pdf',
    'contacts' => 'shared/contacts.txt',
    'photos' => 'shared/family-photos.zip',
];

$messages = [
    'default' => [
        'subject' => '[totmannschalter] Escalation triggered',
        'body' => <<<TXT
Hello {RECIPIENT_NAME},

{ACK_BLOCK}

{DOWNLOAD_NOTICE}

{DOWNLOAD_LINKS}
TXT,
    ],
    'documents' => [
        'subject' => '[totmannschalter] Important documents',
        'body' => <<<TXT
Dear {RECIPIENT_NAME},

Please read this message carefully.

{ACK_BLOCK}

{DOWNLOAD_NOTICE}

Downloads:
{DOWNLOAD_LINKS}
TXT,
    ],
];

$recipients = [
    // Message only, no files.
    ['Recipient 1', 'recipient1@example.com', 'default'],

    // Normal downloads only: use field 4.
    ['Jane Doe', 'Jane Doe <recipient2@example.com>', 'documents', ['letter', 'contacts']],

    // Single-use download only: use field 5.
    ['John Doe', '<recipient3@example.com>', 'documents', [], ['photos']],

    // Mixed case: field 4 stays normal, field 5 becomes single-use.
    ['Alex Example', 'alex@example.com', 'documents', ['letter'], ['photos']],

    // Same file for another recipient: repeat the same alias.
    ['Sam Example', 'sam@example.com', 'documents', ['letter']],
];

return [
    'files' => $files,
    'messages' => $messages,
    'recipients' => $recipients,
];
```

How to read those rows:
- `['Recipient 1', 'recipient1@example.com', 'default']`
	- sends the `default` message
	- includes no download links
- `['Jane Doe', 'Jane Doe <recipient2@example.com>', 'documents', ['letter', 'contacts']]`
	- sends the `documents` message
	- includes 2 normal download links from field 4
- `['John Doe', '<recipient3@example.com>', 'documents', [], ['photos']]`
	- sends the `documents` message
	- includes 1 single-use download link from field 5
- `['Alex Example', 'alex@example.com', 'documents', ['letter'], ['photos']]`
	- sends the `documents` message
	- includes 1 normal link and 1 single-use link
- `['Sam Example', 'sam@example.com', 'documents', ['letter']]`
	- receives the same underlying file as Jane Doe
	- still gets a separate signed link
## ACK: what it means and when to use it
ACK means receipt acknowledgement.

Practical meaning:
- the recipient clicks one link to confirm that the escalation mail was received
- once any recipient acknowledges, no further escalation mails are sent for that escalation event
- ACK is useful when you want at least one recipient to confirm that the message arrived
- ACK does not mean that every recipient has read every attachment; it only confirms receipt by one person

How ACK reaches the mail body:
- `{ACK_BLOCK}` is the normal placeholder
- `{ACK_BLOCK}` expands to the full acknowledgement hint plus URL when ACK is enabled
- `{ACK_BLOCK}` stays empty when ACK is disabled
- `{ACK_URL}` expands only to the raw URL and is meant for advanced custom mail bodies

Practical rule:
- keep `{ACK_BLOCK}` in every message that should allow receipt confirmation
- if you remove `{ACK_BLOCK}` from one message, recipients using that message will not see an ACK link even if ACK is enabled globally
## Mail placeholders
Reminder mails from `totmann.inc.php` support:
- `{CONFIRM_URL}`
- `{DEADLINE_ISO}`
- `{CYCLE_START_ISO}`

Escalation mails from `$messages` in `totmann-recipients.php` support:
- `{LAST_CONFIRM_ISO}`
- `{CYCLE_START_ISO}`
- `{DEADLINE_ISO}`
- `{RECIPIENT_NAME}`
- `{ACK_BLOCK}`
- `{ACK_URL}`
- `{DOWNLOAD_NOTICE}`
- `{DOWNLOAD_LINKS}`

Practical meaning:
- `{RECIPIENT_NAME}` expands to field 1 from the matching recipient row
- `{ACK_BLOCK}` expands to the complete ACK text plus URL, or stays empty
- `{ACK_URL}` expands only to the raw ACK URL
- `{DOWNLOAD_LINKS}` expands to raw URLs only, one URL per line
- the runtime does not add headings, bullets, labels, or expiry text around those URLs
- `{DOWNLOAD_NOTICE}` expands only if the current mail includes at least one field-5 file
## Normal downloads vs single-use downloads
Escalation mails can include optional download links through field 4 and field 5 in each recipient row.

Practical rule:
- field 4 => normal download links
- field 5 => single-use download links
- you never type `single_use=true`; field 5 is the single-use list

Operational behaviour:
- the runtime generates links per recipient, not as one shared group link
- the runtime still sends one escalation mail per recipient
- if two or more recipients should receive the same file, each recipient still gets a separate signed link
- if one recipient downloads a single-use file, that does not consume another recipient’s separate link
- already issued valid download links still resolve even if an unrelated message or recipient row later breaks in `totmann-recipients.php`
- the link lifetime is measured from the first escalation mail of that escalation event, not from each ACK reminder mail
- `download_valid_days` in `totmann.inc.php` controls that lifetime globally for all files
- single-use applies to the whole escalation event for that recipient/file pair, not to each newly generated reminder URL

If you need the same file for two recipients, define it once in `$files` and repeat only the alias in each recipient row.
## `{DOWNLOAD_NOTICE}` and `download_notice_single_use`
`{DOWNLOAD_NOTICE}` is the dedicated warning placeholder for single-use downloads.

Practical meaning:
- if a message includes only field-4 files, `{DOWNLOAD_NOTICE}` stays empty
- if a message includes at least one field-5 file, `{DOWNLOAD_NOTICE}` expands to the text from `download_notice_single_use` in `totmann.inc.php`
- the repository default for `download_notice_single_use` is deliberately only a placeholder and must be replaced with your own real warning text before production use

Practical rule:
- if any recipient using that message may receive a field-5 file, keep `{DOWNLOAD_NOTICE}` in that message body
- if you remove `{DOWNLOAD_NOTICE}`, the single-use link still works technically, but the recipient loses the warning text

Minimal example:
```text
{DOWNLOAD_NOTICE}

{DOWNLOAD_LINKS}
```

Practical example:
```text
Please save all downloaded files locally before closing this message.

{DOWNLOAD_NOTICE}

Downloads:
{DOWNLOAD_LINKS}
```
## Same file for multiple recipients
If two or more recipients should receive the same file, define it once in `$files` and repeat only the alias in each recipient row.

Example:
```php
$files = [
    'letter' => 'shared/letter.pdf',
];

$messages = [
    'default' => [
        'subject' => '[totmannschalter] Escalation triggered',
        'body' => "Hello {RECIPIENT_NAME},\n\n{DOWNLOAD_LINKS}",
    ],
];

$recipients = [
    ['Jane Doe', 'recipient2@example.com', 'default', ['letter']],
    ['John Doe', 'recipient3@example.com', 'default', ['letter']],
];
```

Result:
- both recipients receive separate mails
- both recipients receive separate signed URLs
- both recipients still download the same underlying file from `download_base_dir`
## Final operator checklist
Before you go live, confirm all of these points:
1. every message subject and body in `$messages` was replaced with your own real text
2. every recipient row uses a valid message key in field 3
3. every normal download alias lives in field 4
4. every single-use alias lives in field 5
5. every message that should allow receipt confirmation still contains `{ACK_BLOCK}`
6. every message that may carry field-5 files still contains `{DOWNLOAD_NOTICE}`
7. `download_notice_single_use` in `totmann.inc.php` was replaced with your own finished warning text
