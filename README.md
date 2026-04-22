![totmann](img/totmannschalter-xs.png)

[![GitHub Release](https://img.shields.io/github/v/release/macsteini/totmannschalter?label=Release&color=red)](https://github.com/MacSteini/totmannschalter/releases/latest)
[![Static Badge](https://img.shields.io/badge/PHP->=v8.0.0-red)](docs/Installation.md)
[![Licence: MIT](https://img.shields.io/github/license/macsteini/totmannschalter?label=License&color=red)](LICENCE)

A fully self-hosted “dead man’s switch” for email: it sends periodic confirmation links from your own server, and if you do not confirm within a defined window (plus grace), it escalates to predefined recipients. No third-party services, no vendor lock-in – just `systemd`, PHP, and sendmail on infrastructure you control.

**[Download the latest version from here.](https://github.com/MacSteini/totmannschalter/releases/latest)**

## What this does
- You regularly receive an email containing a **confirmation link**.
- Confirmation is **two-step** (`GET` shows a button, `POST` confirms) to defeat mail link scanners.
- The link is **HMAC-signed** (tamper-resistant), so the web endpoint (`totmann.php`) can verify requests without keeping a history of issued confirmation links.
- You must open the link **within a configured time window**.
- A successful confirmation **resets the cycle**.
- The script sends reminder emails as one email per `to_self` recipient (no shared `To:` list).
- If you do not confirm in time (plus grace), the script triggers **escalation** using conservative logic.
- Escalation uses exactly one recipient file (`totmann-recipients.php` by default) with 3 flat top-level sections: `$files`, `$messages`, and `$recipients`.
- Escalation emails are always sent individually (one mail per recipient).
- Escalation emails can optionally include recipient-specific download links for files stored outside the webroot.
- Escalation emails can include an optional **receipt acknowledgement (ACK)** link. Once **any** recipient acknowledges, no further escalation mails are sent for that escalation event.
- Web requests without a valid current token always show a **neutral page** (stealth).
- Public web pages follow the browser language via `Accept-Language` (`de-DE`, `en-GB`, `en-US`, `fr-FR`, `it-IT`, `es-ES`; fallback: `en-US`).
- Web timestamps stay in your configured `mail_timezone`, even when the browser language changes.
- Rate limiting runs in **fail-open** mode to reduce abuse without breaking functionality.
## Requirements
- Linux host with `systemd` (timer + oneshot service).
- PHP 8.0+.
- A sendmail-compatible MTA (e. g., Postfix/Exim) available via `sendmail_path`.
## Quick start
> One rule that matters:
> The tick runs as `root`, but the web request does not. This means the state directory must be writable by both `root` and your real web user/group – and it must not be inside your webroot.
## Installation (step-by-step, happy path)
1. Identify your real PHP runtime identity (`<WEB_USER>:<WEB_GROUP>`): [Installation guide](docs/Installation.md "Installation guide"), section “Before you start”.
2. Create state dir + place files:
	```sh
	sudo mkdir -p /var/lib/totmann
	sudo mkdir -p /var/lib/totmann/downloads
	sudo cp totmann.inc.php totmann-tick.php totmann-lib.php totmann-recipients.php /var/lib/totmann/
	sudo cp -R l18n /var/lib/totmann/
	# Place the web endpoint in your webroot (example):
	# sudo cp totmann.php /var/www/html/totmann/totmann.php
	# Optional but recommended for styled web pages:
	# sudo cp totmann.css /var/www/html/totmann/totmann.css
	```
	If you changed `lib_file`, `l18n_dir_name`, `recipients_file`, `web_file`, or `web_css_file` in `totmann.inc.php`, copy/rename files accordingly.
3. Set required config in `/var/lib/totmann/totmann.inc.php`:
	- `base_url` (real HTTPS base URL without endpoint filename; the runtime appends `web_file` automatically)
	- `hmac_secret_hex` (example: `openssl rand -hex 32`)
	- `to_self`
	- `l18n_dir_name` (default: `l18n`)
	- `recipients_file` (default: `totmann-recipients.php`)
	- `download_base_dir` (private directory for downloadable files; keep it outside webroot)
	- `download_valid_days` (global download-link validity for all files; default: `180`)
	- `operator_alert_interval_hours` (mandatory operator-warning throttle in whole hours; allowed: `1..24`; missing/invalid values fall back automatically to `2`)
	- Runtime names: `lib_file`, `l18n_dir_name`, `lock_file`, `log_file_name`, `recipients_file`, `state_file`, `web_file` (filenames/directories only; no paths)
	- Optional web stylesheet filename: `web_css_file` (same webroot folder as `web_file`; empty disables link)
	- Logging target via `log_mode`: `none`, `syslog`, `file`, `both` (recommended: `both`)
	- Operator warnings are separate mails to `to_self`; they are built in on purpose and cannot be disabled
	- Use the test preset from [Timing](docs/Timing.md "Timing model and presets")
	- `{DOWNLOAD_LINKS}` renders the complete download block for that mail
	- `totmann-recipients.php` defines files once in `$files`, reusable mail texts in `$messages`, and then assigns them in `$recipients`
	- every recipient row must reference a valid message key in field 3; there is no escalation fallback in `totmann.inc.php`
	- normal downloads go into recipient field 4; single-use downloads go into recipient field 5
	- you never write `single_use=true` yourself; field 5 is the single-use list
	- if a message should contain a receipt-confirmation link, keep `{ACK_BLOCK}` in that message body
	- if a message is used with field-5 files, add `single_use_notice` to that message in `totmann-recipients.php`
	- if a mail contains more than one download, the runtime adds `X Downloads:` and leaves a blank line between the download blocks automatically
	- If two recipients should receive the same file, repeat the same file alias in both recipient rows
	- Public web pages use the browser language from `Accept-Language`; if only a base language such as `de` is sent, the runtime picks the closest supported locale such as `de-DE`
	- If no supported browser language matches, the web endpoint falls back to `en-US`
4. Set permissions:
	```sh
	sudo chown -R root:<WEB_GROUP> /var/lib/totmann
	sudo find /var/lib/totmann -type d -exec chmod 2770 {} \;
	sudo find /var/lib/totmann -type f -exec chmod 0660 {} \;
	```
5. Ensure the web runtime resolves the same state dir:
	- Prefer ENV `totmann_STATE_DIR=/var/lib/totmann` in your PHP runtime.
	- `totmann.php` in this repo ships with `define('TOTMANN_STATE_DIR', '/var/lib/totmann')` enabled by default. Adjust this value if your state dir differs.
6. Run preflight in the deployed state dir:
	```sh
	cd /var/lib/totmann
	php totmann-tick.php check
	php totmann-tick.php check --web-user=<WEB_USER>
	```
7. Clean initialise once:
	```sh
	sudo rm -f /var/lib/totmann/totmann.json /var/lib/totmann/totmann.lock /var/lib/totmann/totmann.log
	sudo sh -c 'umask 0007; /usr/bin/php /var/lib/totmann/totmann-tick.php tick'
	```
	The `rm` line uses the filenames shown in the template config; if you changed them in `totmann.inc.php`, adapt this command.
8. Install + enable `systemd` unit/timer: follow [systemd](docs/Systemd.md "systemd").
9. Run the smoke/E2E test with short timings: follow [Installation](docs/Installation.md "Installation guide") and [Timing](docs/Timing.md "Timing model and presets").
10. During live testing, watch current activity in real time:
	```sh
	tail -f /var/lib/totmann/totmann.log
	```
	If you changed `log_file_name` or `log_file`, use that effective path instead. If `log_mode` is `syslog` or `none`, use `journalctl` instead of `tail`. For help reading file-log lines, journal bootstrap failures, and operator warning mails together, use [Log guide](docs/Logs.md "Log guide").
## Terms
- ENV: environment variable (e. g., `totmann_STATE_DIR`).
- GET/POST: HTTP request methods (`GET` shows the confirm page; `POST` performs the confirmation).
- ACK: recipient receipt acknowledgement link (stops further escalation mails for that escalation event once any recipient clicks).
- HMAC: keyed hash used to sign tokens (tamper-resistant).
- MTA: Mail Transfer Agent (server-side mailer, e. g., Postfix/Exim).
- PHP-FPM: PHP FastCGI Process Manager (common PHP runtime behind nginx).
- setgid: directory bit so new files inherit the directory group.
- umask: process permission mask controlling default file modes.
- fail-open: on limiter failure, allow the request (used for rate limiting to avoid accidental lockouts).
## Docs
1. Read the [installation guide](docs/Installation.md "Installation guide") – layout, permissions, clean initialise, smoke test
2. [Configure `systemd`](docs/Systemd.md "systemd") – service/timer units + operational checks
3. [Configure the web endpoint](docs/Web.md "Web endpoint configuration") – state dir resolution, stealth responses, downloads, proxy trust, rate limiting
4. [Understand the timing model and presets](docs/Timing.md "Timing model and presets") – timing model, presets, walkthrough
5. [Mail delivery notes](docs/Mail.md "Mail delivery notes") – sendmail notes, recipient file, placeholders, ACK, normal downloads, single-use downloads
6. [Example messages](docs/Examples.md "Example messages") – representative reminder, operator-warning, and escalation mails with practical explanation
7. [Log guide](docs/Logs.md "Log guide") – how to read `totmann.log` and which lines require action
8. [Troubleshooting](docs/Troubleshooting.md "Troubleshooting") – neutral page, missing mails, permissions, common failure modes
9. [Changelog](docs/Changelog.md "Changelog") – release notes and version history
10. [Roadmap](docs/Roadmap.md "Roadmap") – planned next features
11. [Contribution guide](CONTRIBUTING.md "Contribution guide") – contribution workflow, quality checks, PR checklist
## Contributing
[Contributions are welcome!](CONTRIBUTING.md "Contributions are welcome!")
## Licence
This project uses the [MIT Licence](LICENCE "MIT Licence"). You may use, change, and distribute it in compliance with the licence terms.
