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
Even with SPF/DKIM/DMARC, identical short mails sent frequently can look spammy (especially to large providers like Google or Microsoft).

Suggestions:
- during real use, keep reminder frequency human-scale (hours, not minutes)
- avoid repeating identical subject/body too often (especially during tests)
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
## Escalation recipient model
Escalation recipients are no longer split across multiple files.

Everything recipient-related now lives in `totmann-recipients.php`.

The file now has exactly 3 top-level areas:
- `$files`
- `$messages`
- `$recipients`

Each recipient row is one mailbox and follows this fixed order:
1. personal name for `{RECIPIENT_NAME}`
2. mailbox for the actual `To:` header
3. message key
4. optional normal file aliases
5. optional single-use file aliases

Rule:
- field 3 is mandatory and must reference a valid message key in `$messages`

Minimal file shape:
```php
$files = [
    'letter' => 'shared/letter.pdf',
];

$messages = [
    'default' => [
        'subject' => '[totmannschalter] Escalation triggered',
        'body' => <<<TXT
Hello {RECIPIENT_NAME},

{DOWNLOAD_NOTICE}

{DOWNLOAD_LINKS}
TXT,
    ],
    'jane' => [
        'subject' => '[totmannschalter] Escalation triggered for Jane',
        'body' => <<<TXT
Dear {RECIPIENT_NAME},

{DOWNLOAD_NOTICE}

{DOWNLOAD_LINKS}
TXT,
    ],
];

$recipients = [
    ['Recipient 1', 'fallback@example.com', 'default'],
    ['Jane Doe', 'Jane Doe <jane@example.com>', 'jane', ['letter']],
];

return [
    'files' => $files,
    'messages' => $messages,
    'recipients' => $recipients,
];
```
## Mail placeholders
All escalation mail text now lives in `$messages` inside `totmann-recipients.php`.

Available placeholders:
- reminder mail: `{CONFIRM_URL}`
- escalation mail: `{LAST_CONFIRM_ISO}`, `{CYCLE_START_ISO}`, `{DEADLINE_ISO}`, `{RECIPIENT_NAME}`, `{ACK_BLOCK}`, `{ACK_URL}`, `{DOWNLOAD_NOTICE}`, `{DOWNLOAD_LINKS}`

Download-specific behaviour:
- `{RECIPIENT_NAME}` expands to field 1 from the matching recipient row
- `{ACK_BLOCK}` expands to the full acknowledgement hint plus URL when ACK is enabled, otherwise it stays empty
- `{ACK_URL}` expands only to the raw ACK URL and is intended for advanced manual mail bodies
- `{DOWNLOAD_LINKS}` expands to raw URLs only, one URL per line
- the runtime does not add headings, bullets, labels, or expiry text around those URLs
- `{DOWNLOAD_NOTICE}` stays empty unless at least one included link uses `single_use=true`
- `{DOWNLOAD_NOTICE}` uses the text from `download_notice_single_use` in `totmann.inc.php`
- the repository default for `download_notice_single_use` is intentionally only a placeholder and must be replaced with your own warning text in your own language

Minimal example:
```text
{DOWNLOAD_NOTICE}

{DOWNLOAD_LINKS}
```

Practical example:
```text
Please read this carefully and save all downloaded files immediately.

{DOWNLOAD_NOTICE}

Downloads:
{DOWNLOAD_LINKS}
```
## Optional download links
Escalation mails can include optional download links via field 4 and field 5 in each recipient row in `totmann-recipients.php`.

Behaviour:
- the runtime generates links per recipient, not as one shared group link
- the runtime still sends one escalation mail per recipient
- field 4 contains normal download aliases (`single_use=false`)
- field 5 contains one-time download aliases (`single_use=true`)
- if two or more recipients should receive the same file, each recipient still gets a separate signed link
- if two or more recipients should receive the same file, repeat the same alias under each recipient row
- `single_use=false` is the safer default
- if you use field 5, keep `{DOWNLOAD_NOTICE}` in the mail body so recipients always see the warning
- do not leave the repository placeholder for `download_notice_single_use` unchanged in production

Operational notes:
- keep large or sensitive content out of the mail body and use download links instead
- file encryption stays outside the scope of this project; if you need encrypted PDFs or archives, prepare them before placing them into `download_base_dir`
- if a recipient’s download aliases cannot be resolved at runtime, that recipient’s escalation mail is still sent without those download links
- already issued valid download links still resolve even if an unrelated message or recipient row in `totmann-recipients.php` is later broken
- the link lifetime is measured from the first escalation mail of that escalation event, not from each ACK reminder mail
- `download_valid_days` in `totmann.inc.php` controls that lifetime globally for all files
- `single_use=true` applies to the escalation event for that recipient/link pair, not to each newly generated reminder URL
- the same mailbox string can use a display name with commas; the runtime now serialises that safely as one recipient header
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
