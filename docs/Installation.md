# totmannschalter – Installation
## Prerequisites
- PHP 8.0+.
- `systemd` (service + timer).
- A sendmail-compatible MTA (e. g., Postfix/Exim) reachable via `sendmail_path`.
## Install order (recommended)
1. Identify `<WEB_USER>:<WEB_GROUP>` first.
2. Create the state directory and place the files.
3. Set required `totmann.inc.php` values.
4. Update `totmann-recipients.php`.
5. Fix ownership/permissions.
6. Run preflight checks.
7. Clean initialise once.
8. Install and enable the `systemd` timer.
9. Run the smoke test with short timings.
## Layout (recommended)
Recommended base directory (not under `/home`): `/var/lib/totmann`

In `/var/lib/totmann`:
- `totmann.inc.php`
- your configured `lib_file` (template default: `totmann-lib.php`)
- your configured `l18n_dir_name` directory (template default: `l18n/`)
- your configured `recipients_file` (template default: `totmann-recipients.php`)
- `totmann-tick.php`

Runtime files created automatically (as needed) in `/var/lib/totmann`:
- `totmann.json`
- `totmann.lock`
- `ratelimit/`
- `totmann.log`

Private download directory (outside webroot):
- your configured `download_base_dir` (template default: `/var/lib/totmann/downloads`)

In your webroot:
- your configured `web_file` (template default: `totmann.php`)
- optional stylesheet for web pages: your configured `web_css_file` (template default: `totmann.css`)
## Before you start: identify the real web identity
**This is the most important step**: you must find the actual user and group that execute your configured web endpoint file (`web_file`). On Debian/Ubuntu with PHP-FPM, the pool configuration is the source of truth.

List configured pool users/groups:
```sh
sudo grep -R --line-number "^\s*user\s*=" /etc/php/*/fpm/pool.d
sudo grep -R --line-number "^\s*group\s*=" /etc/php/*/fpm/pool.d
```
If you have more than one pool, determine which pool serves your site (vhost). Use one of these methods if you are unsure:

`systemd` services:
```sh
systemctl list-units --type=service | egrep -i 'php.*fpm|apache2|nginx'
systemctl status php*-fpm --no-pager
systemctl status nginx apache2 --no-pager
```
Look at listening sockets:
```sh
sudo ss -lptn | egrep -i ':(80|443)\b|php-fpm|nginx|apache'
sudo ss -lx | egrep -i 'php.*fpm|fpm\.sock'
```
nginx and PHP-FPM sockets: find the pool socket used by your vhost:
```sh
sudo grep -R --line-number "fastcgi_pass" /etc/nginx | head -n 50
```
Pick ONE “web identity” that actually executes your configured web endpoint file:
- Example 1: `www-data:www-data`
- Example 2: `usera:usera`
- Example 3: `nginx:nginx`

From here on:
- `<WEB_USER>` = your PHP runtime user
- `<WEB_GROUP>` = your PHP runtime group
## Create the state directory
```sh
sudo mkdir -p /var/lib/totmann
sudo mkdir -p /var/lib/totmann/downloads
sudo mkdir -p /var/lib/totmann/l18n
```
## Place the files
- Copy `totmann.inc.php`, your configured `lib_file`, your configured `recipients_file`, and `totmann-tick.php` to `/var/lib/totmann`:
```sh
sudo cp totmann.inc.php totmann-tick.php totmann-lib.php totmann-recipients.php /var/lib/totmann/
sudo cp -R l18n /var/lib/totmann/
```
- Place your configured `web_file` into your webroot (e. g., `/var/www/html/totmann/totmann.php`):
```sh
sudo cp totmann.php /var/www/html/totmann/totmann.php
```
- Optional but recommended: copy the stylesheet into the same webroot folder:
```sh
sudo cp totmann.css /var/www/html/totmann/totmann.css
```
If you changed `lib_file`, `l18n_dir_name`, `recipients_file`, `web_file`, or `web_css_file` from the template names, adjust these copy/rename commands accordingly.
## Update `totmann.inc.php` (required values)
- `state_dir` should match the directory where you placed `totmann.inc.php` (recommended: `/var/lib/totmann`)
- Runtime names (filenames/directories only): `lib_file`, `l18n_dir_name`, `lock_file`, `log_file_name`, `recipients_file`, `state_file`, `web_file`
- `download_base_dir` should point to a private directory outside your webroot
- `download_valid_days` sets one global validity period for all download links in the same escalation event
- `operator_alert_interval_hours` throttles mandatory operator warning mails to `to_self`
- Optional web stylesheet filename in webroot: `web_css_file` (empty disables link)
- `base_url` must point to your real public HTTPS base URL (without endpoint filename); the runtime appends `web_file` automatically
- set `log_mode` explicitly: `none`, `syslog`, `file`, `both` (recommended: `both`)
- `log_file_name` keeps the default file-log name unless you intentionally want a different filename
- `hmac_secret_hex` (e. g., `openssl rand -hex 32`)
- `to_self`
- Public web pages follow the browser language from `Accept-Language`; fallback language is `en-US`
- Public web timestamps stay in `mail_timezone`
- If a locale directory/file is missing or unreadable, preflight reports it and the endpoint falls back to `en-US`
- `operator_alert_interval_hours` accepts only whole hours `1..24`; if you remove it or set an invalid value, Totmannschalter automatically falls back to `2`
- If you plan to read file logs directly, also read [Log guide](Logs.md "Log guide") so you know how to interpret file-log lines, journal bootstrap failures, and operator warning mails together
- Important: operator warning mails are built in on purpose, go to `to_self`, and cannot be disabled
## Update `totmann-recipients.php`
`totmann-recipients.php` contains exactly 3 flat top-level areas:
- `$files`: file alias => relative path
- `$messages`: message key => `subject` + `body`
- `$recipients`: one flat recipient row per mailbox

Recipient row format (fixed order):
1. personal name used by `{RECIPIENT_NAME}`
2. mailbox used for the actual `To:` header
3. message key
4. optional list of normal file aliases
5. optional list of single-use file aliases

Practical meaning:
- the first 3 values are always mandatory
- field 2 may be:
	- `recipient@example.com`
	- `<recipient@example.com>`
	- `Recipient Name <recipient@example.com>`
- field 3 is mandatory and must always reference a valid message in `$messages`
- field 4 is the normal/safe default for downloads
- field 5 is the special case for single-use downloads
- you never write `single_use=true` yourself in this file
- if field 5 is omitted, everything stays on the safer normal-download default
- if a message is used with field 5, that message must define `single_use_notice`
- `download_valid_days` in `totmann.inc.php` controls one global validity period for all downloads

Happy-path editing order:
1. Define each reusable file once in `$files`.
2. Write each reusable escalation mail once in `$messages`.
3. Assign one message key and optional file aliases in `$recipients`.

Copy/paste example:
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

{DOWNLOAD_LINKS}
TXT,
    ],
    'jane' => [
        'subject' => '[totmannschalter] Escalation triggered for Jane',
        'body' => <<<TXT
Dear {RECIPIENT_NAME},

{ACK_BLOCK}

{DOWNLOAD_LINKS}
TXT,
    ],
    'john' => [
        'subject' => '[totmannschalter] Escalation triggered',
        'single_use_notice' => '[replace with your own single-use warning]',
        'body' => <<<TXT
Hello {RECIPIENT_NAME},

{ACK_BLOCK}

{DOWNLOAD_LINKS}
TXT,
    ],
];

$recipients = [
    // Simplest case: message only, no files.
    ['Recipient 1', 'recipient1@example.com', 'default'],

    // Normal download: use field 4.
    ['Jane Doe', 'Jane Doe <recipient2@example.com>', 'jane', ['letter', 'contacts']],

    // Single-use download: use field 5.
    ['John Doe', '<recipient3@example.com>', 'john', [], ['photos']],

    // Mixed case: field 4 stays normal, field 5 becomes single-use.
    ['Alex Example', 'alex@example.com', 'default', ['letter'], ['photos']],
];

return [
    'files' => $files,
    'messages' => $messages,
    'recipients' => $recipients,
];
```

How to read those recipient rows:
- `['Recipient 1', 'recipient1@example.com', 'default']`
  - sends the `default` message
  - no download links
- `['Jane Doe', 'Jane Doe <recipient2@example.com>', 'jane', ['letter', 'contacts']]`
  - sends the `jane` message
  - adds 2 normal download links from field 4
- `['John Doe', '<recipient3@example.com>', 'john', [], ['photos']]`
  - sends the `john` message
  - adds 1 single-use download link from field 5
- `['Alex Example', 'alex@example.com', 'default', ['letter'], ['photos']]`
  - sends the `default` message
  - adds 1 normal link and 1 single-use link

Same file for two recipients:
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
- both recipients receive the same underlying file
- both recipients still receive separate escalation mails
- both recipients still receive separate signed download URLs
## Download placeholders
Use these placeholders in each message `body` inside `$messages`:
- `{RECIPIENT_NAME}`
- `{ACK_BLOCK}`
- `{ACK_URL}` (advanced manual use only)
- `{DOWNLOAD_LINKS}`

Behaviour:
- `{RECIPIENT_NAME}` expands to field 1 from the matching recipient row
- `{ACK_BLOCK}` expands to the full acknowledgement hint plus URL when ACK is enabled, otherwise it stays empty
- keep `{ACK_BLOCK}` in any message that should let recipients confirm receipt
- if you remove `{ACK_BLOCK}` from a message, that message will not show an ACK link even when ACK is enabled globally
- `{ACK_URL}` expands only to the raw ACK URL and is intended for advanced custom bodies
- `{DOWNLOAD_LINKS}` expands to the full download block for that mail
- field 4 is the default and behaves like `single_use=false`
- field 5 is the single-use list
- if a message is used with field 5, define `single_use_notice` in that message
- if a mail contains 2 or more downloads, the runtime adds `X Downloads:` and leaves a blank line between the download blocks automatically

Minimal example:
```text
{DOWNLOAD_LINKS}
```

Practical rule:
- If you only ever use field 4, you do not need `single_use_notice`.
- If a message is used with field 5 anywhere, that message must define `single_use_notice`.
- Full detail for mail bodies, ACK, and downloads: see [Mail delivery notes](Mail.md "Mail delivery notes").
- If you are unsure what the runtime writes to `totmann.log` during mail delivery, see [Log guide](Logs.md "Log guide").
## Preflight check (recommended before enabling timer)
Run the built-in preflight in your deployed state dir:
```sh
cd /var/lib/totmann
php totmann-tick.php check
php totmann-tick.php check --web-user=<WEB_USER>
echo $?
```
Exit codes:
- `0` => ready
- `1` => warnings (review before go-live)
- `2` => hard failures (do not go live yet)

`--web-user` is optional but recommended. It validates (read-only) whether the actual PHP runtime user can likely read config and create/update lock/state files based on POSIX mode bits.

What `check` now validates:
- filenames in `totmann.inc.php`
- `l18n_dir_name` plus the shipped locale files (`de-DE`, `en-GB`, `en-US`, `fr-FR`, `it-IT`, `es-ES`)
- timing values
- `to_self`
- `recipients_file`
- `download_base_dir`
- `base_url`
- message-specific `single_use_notice` requirements inside `recipients_file`
- `mail_from`
- `sendmail_path`
- `log_mode`
- `ip_mode` / trusted-proxy settings
## Changing config without restarting `systemd`
For `totmann.inc.php` changes (for example timing values), you do not need to restart `totmann.timer` or `totmann.service`.
The runtime reads config on each tick, so it picks up updates automatically.

Only changes to unit files in `/etc/systemd/system/*.service` or `*.timer` require:
```sh
sudo systemctl daemon-reload
sudo systemctl restart totmann.timer
```
## Permissions (critical)
Do NOT use `root:root`. Use `root:<WEB_GROUP>` so the web identity can access secrets and runtime files.

Owner: `root`, Group: `<WEB_GROUP>`:
```sh
sudo chown -R root:<WEB_GROUP> /var/lib/totmann
```
> Ensure `<WEB_USER>` is in `<WEB_GROUP>` (or that both are the same identity).

Directories: setgid so new files stay in group `<WEB_GROUP>`; group-write enabled:
```sh
sudo find /var/lib/totmann -type d -exec chmod 2770 {} \;
```
Files: readable+writable by group `<WEB_GROUP>`; not world-readable:
```sh
sudo find /var/lib/totmann -type f -exec chmod 0660 {} \;
```
> Why setgid matters: files created later by `root` will still land in group `<WEB_GROUP>`.
> Why `0660` matters: the web identity must be able to write your configured state file.
## Clean initialise (ensures correct runtime perms)
Delete any old runtime files, then initialise once.
```sh
sudo rm -f /var/lib/totmann/totmann.json /var/lib/totmann/totmann.lock /var/lib/totmann/totmann.log

# IMPORTANT:
# Initialise with umask 0007 so files become 0660 (group-writable).
sudo sh -c 'umask 0007; /usr/bin/php /var/lib/totmann/totmann-tick.php tick'
```
The `rm`/`ls` examples use the filenames from the template config; if you changed them in `totmann.inc.php`, adapt these commands.

Verify:
```sh
ls -la /var/lib/totmann/totmann.json /var/lib/totmann/totmann.lock /var/lib/totmann/totmann.log
```
## Smoke test (use only your own addresses)
1. In `totmann.inc.php`, temporarily set short timings. See [Timing](Timing.md "Timing model and presets").
2. Point `to_self` and all recipient addresses in `totmann-recipients.php` to your own mailboxes.
3. Ensure `totmann.timer` is active and wait for the reminder email.
4. Open the confirmation link (`GET`): you should see a confirm button.
5. Click Confirm (`POST`): the page should show the localised confirmation-success page, e. g., “Thank you.” plus “The cycle has been reset…”.
6. Test the escalation path by not confirming.
7. If you use download links, test:
	- a normal download
	- a single-use download from recipient field 5
	- the same file for two different recipients
	- an ACK reminder mail for the same escalation event (the single-use file must still remain single-use)
8. With random/invalid/stale tokens: the endpoint should show a neutral page.

For live debugging during the smoke test, keep one of these running in a second shell:
```sh
journalctl -u totmann.service -f
```
```sh
tail -f /var/lib/totmann/totmann.log
```
If you are not yet familiar with the log lines, keep [Log guide](Logs.md "Log guide") open in parallel.
