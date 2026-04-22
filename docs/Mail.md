# totmann – Mail delivery notes
![totmann](../img/totmannschalter-icon.png)

## Mail delivery (prerequisite)
The script sends mail via the configured sendmail binary (`sendmail_path` in `totmann.inc.php`) in sendmail-compatible mode.

If you want to see what reminder mails, operator warnings, and escalation mails actually look like for end users, go to [Example messages](Examples.md "Example messages").

Practical rule:
- `mail_from` must contain exactly one mailbox string
- if `reply_to` is set, it must also contain exactly one mailbox string

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
- use `Name <recipient@example.com>` only when you really want a display name in the visible mail header
- if you only need a personal greeting inside the mail body, prefer `{RECIPIENT_NAME}` instead

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
Everything recipient-related lives in `totmann-recipients.php`.

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
- field 2 must still describe exactly one real mailbox
- if you want the simplest visible `To:` header, use only the address in field 2 and keep the human greeting in field 1 via `{RECIPIENT_NAME}`
- field 3 must always reference an existing message key in `$messages`
- field 4 is the normal/safe default for downloads
- field 5 is only for one-time download links
- you never write `single_use=true` yourself in this file
- if field 5 is omitted, everything stays on the normal-download default
- if a message is used with field 5 anywhere, that message must define `single_use_notice`
## Enduser example for `totmann-recipients.php`
```php
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
'documents' => [
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
- `{DOWNLOAD_LINKS}`

Practical meaning:
- `{RECIPIENT_NAME}` expands to field 1 from the matching recipient row
- `{ACK_BLOCK}` expands to the complete ACK text plus URL, or stays empty
- `{ACK_URL}` expands only to the raw ACK URL
- `{DOWNLOAD_LINKS}` expands to the complete download block for that mail
- with one download, the block contains only that one download
- with two or more downloads, the runtime adds `X Downloads:` and inserts one blank line between the download blocks
- when a download is single-use, the message-specific `single_use_notice` appears directly above that one URL
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
## `single_use_notice`
`single_use_notice` is the message-specific warning text for single-use downloads.

Practical meaning:
- write it directly next to `subject` and `body` inside the affected message entry in `$messages`
- only messages that are actually used with field 5 need it
- the runtime inserts that text automatically above each affected single-use URL
- you do not need a second placeholder in the message body

Minimal example:
```php
'documents' => [
'subject' => '[totmann] EXAMPLE TEMPLATE – message with documents',
'single_use_notice' => 'Please save this file straight away. This download link works only once.',
'body' => "Hello {RECIPIENT_NAME},\n\n{DOWNLOAD_LINKS}",
],
```
Practical rule:
- if a message is only used with field 4, `single_use_notice` can be omitted
- if a message is used with field 5 anywhere, `single_use_notice` must be present
## Same file for multiple recipients
If two or more recipients should receive the same file, define it once in `$files` and repeat only the alias in each recipient row.

Example:
```php
$files = [
'letter' => 'shared/letter.pdf',
];

$messages = [
'default' => [
'subject' => '[totmann] EXAMPLE TEMPLATE – escalation message',
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
6. every message that is used with field-5 files defines `single_use_notice`
7. `{DOWNLOAD_LINKS}` is still present in every message that should contain downloads
