# totmannschalter
![GitHub Release](https://img.shields.io/github/v/release/macsteini/totmannschalter?label=Release&color=red)
![Static Badge](https://img.shields.io/badge/PHP_>=-v8.0.0-blue)
[![Licence: MIT](https://img.shields.io/github/license/macsteini/totmannschalter)](https://gnu.org/licenses/mit)

A fully self-hosted “dead man’s switch” for email: it sends periodic confirmation links from your own server, and if you don’t confirm within a defined window (plus grace), it escalates to predefined recipients. No third-party services, no vendor lock-in – just systemd, PHP, and sendmail on infrastructure you control.
## What this does
- You regularly receive an email containing a **confirmation link**.
- Confirmation is **two-step** (GET shows a button, POST confirms) to defeat mail link scanners.
- The link is **HMAC-signed** (tamper-resistant), so the web endpoint (`totmann.php`) can verify requests **without** storing a history of issued links.
- You must open the link **within a configured time window**.
- A successful confirmation **resets the cycle**.
- The script sends reminder emails as one email per `to_self` recipient (no shared To list).
- If you do not confirm in time (plus grace), the script triggers **escalation** using conservative logic.
- The script sends escalation as one email per recipient (no shared To list), with optional recipient-specific plain-text subject/body pairs via external template IDs.
- Escalation emails can include an optional **receipt acknowledgement (ACK)** link. Once **any** recipient acknowledges, further ACK reminders stop.
- If you configure an ACK reminder limit, the script logs one final marker when it reaches the limit and then stays quiet for this escalation state (until ACK or reset) to avoid irrelevant log noise.
- Web requests without a valid current token always show a **neutral page** (stealth).
- Rate limiting runs in **fail-open** mode to reduce abuse without breaking functionality.
## Requirements
- Linux host with `systemd` (timer + oneshot service).
- PHP 8.0+ (uses `str_contains()`).
- A sendmail-compatible MTA (e. g., Postfix/Exim) available via `sendmail_path`.
## Quick start
> One rule that matters:
> The tick runs as `root`, but the web request does not. This means the state directory must be writable by both `root` and your real web user/group – and it must not be inside your webroot.
## Installation (step-by-step, happy path)
1. Identify your real PHP runtime identity (`<WEB_USER>:<WEB_GROUP>`):
	[Installation guide](docs/Installation.md "Installation guide"), section “Before you start”.
2. Create state dir + place files:
	```sh
	sudo mkdir -p /var/lib/totmann
	sudo cp totmann.inc.php totmann-tick.php totmann-lib.php totmann-messages.php /var/lib/totmann/
	# Place the web endpoint in your webroot (example):
	# sudo cp totmann.php /var/www/html/totmann/totmann.php
	# Optional but recommended for styled web pages:
	# sudo cp totmann.css /var/www/html/totmann/totmann.css
	```
	If you changed `lib_file`, `web_file`, `web_css_file`, or `mail_file` in `totmann.inc.php`, copy/rename files accordingly.
3. Set required config in `/var/lib/totmann/totmann.inc.php`:
	- `base_url` (real HTTPS base URL without endpoint filename; the script appends `web_file` automatically)
	- `hmac_secret_hex` (example: `openssl rand -hex 32`)
	- `to_self`
	- `to_recipients` as entries in format `[address]` or `[address, id]`
	- `body_escalate` (mandatory fallback escalation body)
	- `mail_file` (optional ID-to-subject/body map in external PHP file)
	- Runtime filenames: `lib_file`, `lock_file`, `log_file_name`, `mail_file`, `state_file`, `web_file` (filenames only)
	- Optional web stylesheet filename: `web_css_file` (same webroot folder as `web_file`; empty disables link)
	- Logging target via `log_mode`: `none`, `syslog`, `file`, `both` (recommended: `both`)
	- Use the test preset from [Timing](docs/Timing.md "Timing model and presets")
	- Important fail-safe: if recipient ID mapping is invalid/missing, or template loading fails at runtime, escalation still sends with `subject_escalate` + `body_escalate`
4. Set permissions:
	```sh
	sudo chown -R root:<WEB_GROUP> /var/lib/totmann
	sudo find /var/lib/totmann -type d -exec chmod 2770 {} \;
	sudo find /var/lib/totmann -type f -exec chmod 0660 {} \;
	```
5. Ensure web runtime can resolve the same state dir:
	- Prefer ENV `TOTMANN_STATE_DIR=/var/lib/totmann` in your PHP runtime.
	- `totmann.php` in this repo ships with `define('TOTMANN_STATE_DIR', '/var/lib/totmann')` enabled by default. Adjust this value if your state dir differs.
6. Run preflight in deployed state dir:
	```sh
	cd /var/lib/totmann
	php totmann-tick.php check
	php totmann-tick.php check --web-user=<WEB_USER>
	```
	Preflight now validates timing/interval values strictly (integer type + minimum bounds) and reports warning-level hints for suspicious but allowed combinations (e. g., `confirm_window_seconds > check_interval_seconds`).
7. Clean initialise once:
	```sh
	sudo rm -f /var/lib/totmann/totmann.json /var/lib/totmann/totmann.lock /var/lib/totmann/totmann.log
	sudo sh -c 'umask 0007; /usr/bin/php /var/lib/totmann/totmann-tick.php tick'
	```
	The `rm` line uses the filenames shown in the template config; if you changed them in `totmann.inc.php`, adapt this command.
8. Install + enable `systemd` unit/timer:
	Follow the instructions in [systemd](docs/Systemd.md "systemd").
9. Run smoke/E2E test with short timings:
	Follow the “Smoke test” in the [installation guide](docs/Installation.md "Installation guide") and the checklist in [Timing](docs/Timing.md "Timing model and presets").
10. During live testing, watch script decisions in real time:
	```sh
	tail -f /var/lib/totmann/totmann.log
	```
	If you changed `log_file_name` or `log_file`, use that effective path instead. If `log_mode` is `syslog` or `none`, use `journalctl` instead of `tail`.
## Terms
- ENV: environment variable (e. g., `TOTMANN_STATE_DIR`).
- GET/POST: HTTP request methods (GET shows the confirm page; POST performs the confirmation).
- ACK: recipient receipt acknowledgement link (stops further ACK reminders once any recipient clicks).
- HMAC: keyed hash used to sign tokens (tamper-resistant).
- MTA: Mail Transfer Agent (server-side mailer, e. g., Postfix/Exim).
- PHP-FPM: PHP FastCGI Process Manager (common PHP runtime behind nginx).
- setgid: directory bit so new files inherit the directory group.
- umask: process permission mask controlling default file modes.
- fail-open: on internal failure, allow the request (used for rate limiting to avoid accidental lockouts).
## Docs
1. Read the [installation guide](docs/Installation.md "Installation guide") – layout, permissions, clean initialise, smoke test
2. [Configure `systemd`](docs/Systemd.md "systemd") – service/timer units + operational checks
3. [Configure the web endpoint](docs/Web.md "Web endpoint configuration") – state dir resolution, stealth responses, rate limiting
4. [Understand the timing model and presets](docs/Timing.md "Timing model and presets") – timing model, presets, walkthrough
5. [Mail delivery notes](docs/Mail.md "Mail delivery notes") – sendmail notes, SMTPUTF8, deliverability tips
6. [Troubleshooting](docs/Troubleshooting.md "Troubleshooting") – neutral page, missing mails, permissions, common failure modes
7. [Changelog](docs/Changelog.md "Changelog") – release notes and version history
8. [Roadmap](docs/Roadmap.md "Roadmap") – planned next features
9. [Contribution guide](CONTRIBUTING.md "Contribution guide") – contribution workflow, quality checks, PR checklist
## Contributing
Contributions are welcome! Please follow these steps:
1. Fork this repository
2. Create a feature branch: `git checkout -b feature-branch`
3. Commit your changes: `git commit -m "Add feature"`
4. Push the branch: `git push origin feature-branch`
5. Submit a pull request

Please ensure all changes are well-documented and tested.
## Licence
This project uses the [MIT Licence](LICENCE "MIT Licence"). You may use, change, and distribute it in compliance with the licence terms.
