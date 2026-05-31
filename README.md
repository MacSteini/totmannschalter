<!-- markdownlint-disable MD033 MD041 -->

<div align="center">

![totman](https://github.com/MacSteini/totmannschalter/blob/main/img/totman-xs.png?raw=true)

[![GitHub Release](https://img.shields.io/github/v/release/MacSteini/totmannschalter?label=Release&color=black)](https://github.com/MacSteini/totmannschalter/releases/latest)
[![Static Badge](https://img.shields.io/badge/PHP->=v8.0.0-black)](https://github.com/MacSteini/totmannschalter/blob/main/docs/Installation.md)
[![Licence: MIT](https://img.shields.io/github/license/MacSteini/totmannschalter?label=Licence&color=black)](LICENCE)

</div>

# totman
A fully self-hosted “dead man’s switch” for email: it sends periodic confirmation links from your own server, and if you do not confirm within a defined window (plus grace), it escalates to predefined recipients. No third-party services, no vendor lock-in – just `systemd`, PHP, and sendmail on infrastructure you control.

**[Download the latest version from here.](https://github.com/MacSteini/totmannschalter/releases/latest)**

The release archive is intentionally slim. It contains `README.md`, `LICENCE`, the runtime PHP/CSS files, the optional `totman-ui.php` add-on, the `.dist.php` templates, and the shipped `l18n/` locale files. It does not contain the full `docs/`, `site/`, or `img/` directories. The quick start below is the offline starting point; the linked GitHub documentation and project website provide the full operator guide when you have network access.

## What this does
- You regularly receive an email containing a **confirmation link**.
- Confirmation is **two-step** (`GET` shows a button, `POST` confirms) to defeat mail link scanners.
- The link is **HMAC-signed** (tamper-resistant), so the web endpoint (`totman.php`) can verify requests without keeping a history of issued confirmation links.
- You must open the link **within a configured time window**.
- A successful confirmation **resets the cycle**.
- The script sends reminder emails as one email per `to_self` recipient (no shared `To:` list).
- If you do not confirm in time (plus grace), the script triggers **escalation** using conservative logic.
- Escalation uses exactly one configured recipient file (`totman-recipients.php` by default, or `totman-recipients.dist.php` if you intentionally keep that filename) with 3 flat top-level sections: `$files`, `$messages`, and `$recipients`.
- Escalation emails are always sent individually (one mail per recipient).
- Escalation emails can optionally include recipient-specific download links for files stored outside the webroot.
- Escalation emails can include an optional **receipt acknowledgement (ACK)** link. The link opens an acknowledgement page first; only the submitted acknowledgement stops further escalation mails for that escalation event.
- Web requests without a valid current token always show a **neutral page** (stealth).
- Public web pages follow the browser language via `Accept-Language` (`de-DE`, `en-GB`, `en-US`, `fr-FR`, `it-IT`, `es-ES`; fallback: `en-US`).
- Web timestamps stay in your configured `mail_timezone`, even when the browser language changes.
- Runtime web pages load the product logo from the project’s GitHub-hosted image URL by default; the pages remain functional if that decorative image is blocked.
- Rate limiting runs in **fail-open** mode to reduce abuse without breaking functionality.
- `totman-ui.php` provides an optional browser-based administration add-on. You may rename the deployed file; the UI uses its current request filename for its CSS and JavaScript assets. The default config keeps post-setup administration off; enable it explicitly with `web_ui_enabled`.

## Requirements
- Linux host with `systemd` (timer + oneshot service).
- PHP 8.0+.
- A sendmail-compatible MTA (e. g., Postfix/Exim). The shipped `sendmail_path` default is `/usr/sbin/sendmail`; change it only if your server uses another path.
## Quick start
> One rule that matters:
> The tick runs as `root`, but the web request does not. This means the state directory must be writable by both `root` and your real web user/group – and it must not be inside your webroot.
## Installation (step-by-step, happy path)
1. Identify your real PHP runtime identity (`<WEB_USER>:<WEB_GROUP>`): [Installation guide](https://github.com/MacSteini/totmannschalter/blob/main/docs/Installation.md "Installation guide"), section “Before you start”.
2. Create state dir + place files:
	```sh
	sudo mkdir -p /var/lib/totman
	sudo mkdir -p /var/lib/totman/files
	sudo cp totman.inc.dist.php totman-tick.php totman-lib.php totman-recipients.dist.php /var/lib/totman/
	sudo cp -R l18n /var/lib/totman/
	cd /var/lib/totman
	sudo cp totman.inc.dist.php totman.inc.php
	sudo cp totman-recipients.dist.php totman-recipients.php
	sudo mkdir -p /var/www/html/totman
	sudo cp totman.php /var/www/html/totman/totman.php
	sudo cp totman.css /var/www/html/totman/totman.css
	```
	The bootstrap config files must keep their exact supported names: `totman.inc.php` and/or `totman.inc.dist.php`. Names such as `totman.inc.prod.php` are not supported. The shipped `.dist.php` templates keep operator-specific values empty and show examples only in comments. The recommended operational pattern is to edit `totman.inc.php` and `totman-recipients.php`; if you intentionally keep real values in `.dist.php` files instead, merge updates manually before replacing those files.
	If you changed configurable runtime names such as `lib_file`, `l18n_dir_name`, `recipients_file`, `web_file`, or `web_css_file` in the effective config, copy/rename only those referenced files accordingly.
3. Set required config in `/var/lib/totman/totman.inc.php` or, if you intentionally use the `.dist.php` file as your runtime config, in `/var/lib/totman/totman.inc.dist.php`:
	- `base_url` (real HTTPS base URL without endpoint filename; the runtime appends `web_file` automatically)
	- `hmac_secret_hex` (example: `openssl rand -hex 32`)
	- `to_self`
	- `l18n_dir_name` (default: `l18n`)
	- `recipients_file` (default: `totman-recipients.php`)
	- `download_base_dir` (private directory for downloadable files; default: `/var/lib/totman/files`; keep it outside webroot)
	- `download_valid_days` (global download-link validity for all files; default: `180`)
	- `operator_alert_interval_hours` (mandatory operator-warning throttle in whole hours; allowed: `1..24`; missing/invalid values fall back automatically to `2`)
	- Runtime names: `lib_file`, `l18n_dir_name`, `lock_file`, `log_file_name`, `recipients_file`, `state_file`, `web_file` (filenames/directories only; no paths)
	- Optional web stylesheet filename: `web_css_file` (same webroot folder as `web_file`; empty disables link)
	- Optional Web UI add-on switch: `web_ui_enabled` (default: `false`)
	- Logging target via `log_mode`: `none`, `syslog`, `file`, `both` (recommended: `both`)
	- Operator warnings are separate mails to `to_self`; they are built in on purpose and cannot be disabled
	- Use the test preset from [Timing](https://github.com/MacSteini/totmannschalter/blob/main/docs/Timing.md "Timing model and presets")
	- `{DOWNLOAD_LINKS}` renders the complete download block for that mail
	- the configured recipient file defines files once in `$files`, reusable mail texts in `$messages`, and then assigns them in `$recipients`
	- every recipient row must reference a valid message key in field 3; there is no escalation fallback in the main config
	- normal downloads go into recipient field 4; single-use downloads go into recipient field 5
	- you never write `single_use=true` yourself; field 5 is the single-use list
	- if a message should contain a receipt-confirmation link, keep `{ACK_BLOCK}` in that message body
	- if a message is used with field-5 files, add `single_use_notice` to that message in `totman-recipients.php`
	- every download block starts with `1 Download:` or `X Downloads:`; when several downloads are present, the runtime leaves a blank line between the download blocks automatically
	- download links are signed for the recipient, alias, escalation event, and current relative file path; if that alias is later changed to another file, the old link fails closed
	- If two recipients should receive the same file, repeat the same file alias in both recipient rows
	- Public web pages use the browser language from `Accept-Language`; if only a base language such as `de` is sent, the runtime picks the closest supported locale such as `de-DE`
	- If no supported browser language matches, the web endpoint falls back to `en-US`
	- To use the optional Web UI, deploy `totman-ui.php` or a renamed copy into the webroot, set the setup code near the top of that deployed UI file or set `TOTMAN_UI_SETUP_CODE` server-side for Docker/managed hosting, and use `web_ui_enabled` to control browser administration after setup
	- The Web UI imports existing live/template config, guides first-run values, creates a private `.totman-ui.php` admin file in the state directory, and writes runtime files only from explicit save or maintenance actions
	- When the Web UI saves configuration, it writes stable runtime-compatible PHP arrays in the same broad order as the `.dist.php` templates; detailed template comments are not preserved
4. Set permissions:
	```sh
	sudo chown -R root:<WEB_GROUP> /var/lib/totman
	sudo find /var/lib/totman -type d -exec chmod 2770 {} \;
	sudo find /var/lib/totman -type f -exec chmod 0660 {} \;
	```
5. Ensure the web runtime resolves the same state dir:
	- Prefer ENV `TOTMAN_STATE_DIR=/var/lib/totman` in your PHP runtime.
	- `totman.php` in this repo ships with `define('TOTMAN_STATE_DIR', '/var/lib/totman')` enabled by default. Adjust this value if your state dir differs.
6. Run preflight in the deployed state dir:
	```sh
	cd /var/lib/totman
	php totman-tick.php check
	php totman-tick.php check --web-user=<WEB_USER>
	```
7. Clean initialise once:
	```sh
	sudo rm -f /var/lib/totman/totman.json /var/lib/totman/totman.lock /var/lib/totman/totman.log
	sudo sh -c 'umask 0007; /usr/bin/php /var/lib/totman/totman-tick.php tick'
	```
	The `rm` line uses the filenames shown in the effective config; if you changed them there, adapt this command.
8. Install + enable `systemd` unit/timer: follow [systemd](https://github.com/MacSteini/totmannschalter/blob/main/docs/Systemd.md "systemd").
9. Run the smoke/E2E test with short timings: follow [Installation](https://github.com/MacSteini/totmannschalter/blob/main/docs/Installation.md "Installation guide") and [Timing](https://github.com/MacSteini/totmannschalter/blob/main/docs/Timing.md "Timing model and presets").
10. During live testing, watch current activity in real time:
	```sh
	tail -f /var/lib/totman/totman.log
	```
	If you changed `log_file_name` or `log_file`, use that effective path instead. If `log_mode` is `syslog` or `none`, use `journalctl` instead of `tail`. For help reading file-log lines, journal bootstrap failures, and operator warning mails together, use [Log guide](https://github.com/MacSteini/totmannschalter/blob/main/docs/Logs.md "Log guide").
## Terms
- ENV: environment variable (e. g., `TOTMAN_STATE_DIR`).
- GET/POST: HTTP request methods (`GET` shows the confirm or ACK page; `POST` performs the confirmation or acknowledgement).
- ACK: recipient receipt acknowledgement link (`GET` opens the ACK page; only the submitted `POST` stops further escalation mails for that escalation event).
- HMAC: keyed hash used to sign tokens (tamper-resistant).
- MTA: Mail Transfer Agent (server-side mailer, e. g., Postfix/Exim).
- PHP-FPM: PHP FastCGI Process Manager (common PHP runtime behind nginx).
- setgid: directory bit so new files inherit the directory group.
- umask: process permission mask controlling default file modes.
- fail-open: on limiter failure, allow the request (used for rate limiting to avoid accidental lockouts).
## Docs
1. Read the [installation guide](https://github.com/MacSteini/totmannschalter/blob/main/docs/Installation.md "Installation guide") – layout, permissions, clean initialise, smoke test
2. [Configure `systemd`](https://github.com/MacSteini/totmannschalter/blob/main/docs/Systemd.md "systemd") – service/timer units + operational checks
3. [Configure the web endpoint](https://github.com/MacSteini/totmannschalter/blob/main/docs/Web.md "Web endpoint configuration") – state dir resolution, stealth responses, downloads, proxy trust, rate limiting
4. [Understand the timing model and presets](https://github.com/MacSteini/totmannschalter/blob/main/docs/Timing.md "Timing model and presets") – timing model, presets, walkthrough
5. [Mail delivery notes](https://github.com/MacSteini/totmannschalter/blob/main/docs/Mail.md "Mail delivery notes") – sendmail notes, recipient file, placeholders, ACK, normal downloads, single-use downloads
6. [Example messages](https://github.com/MacSteini/totmannschalter/blob/main/docs/Examples.md "Example messages") – representative reminder, operator-warning, and escalation mails with practical explanation
7. [Log guide](https://github.com/MacSteini/totmannschalter/blob/main/docs/Logs.md "Log guide") – how to read `totman.log` and which lines require action
8. [Troubleshooting](https://github.com/MacSteini/totmannschalter/blob/main/docs/Troubleshooting.md "Troubleshooting") – neutral page, missing mails, permissions, common failure modes
9. [Changelog](https://github.com/MacSteini/totmannschalter/blob/main/docs/Changelog.md "Changelog") – release notes and version history
10. [Roadmap](https://github.com/MacSteini/totmannschalter/blob/main/docs/Roadmap.md "Roadmap") – planned next features
11. [Contribution guide](https://github.com/MacSteini/totmannschalter/blob/main/CONTRIBUTING.md "Contribution guide") – contribution workflow, quality checks, PR checklist
## Contributing
[Contributions are welcome!](https://github.com/MacSteini/totmannschalter/blob/main/CONTRIBUTING.md "Contributions are welcome!")
## Licence
This project uses the [MIT Licence](LICENCE "MIT Licence"). You may use, change, and distribute it in compliance with the licence terms.
