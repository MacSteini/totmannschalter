# totmannschalter – Changelog
This file documents all notable changes to this project.

This project uses semantic versioning:
- MAJOR: breaking changes
- MINOR: new features (backwards compatible)
- PATCH: bugfixes and small improvements (backwards compatible)
## v4.0.0
- Added a dedicated [Log guide](Logs.md "Log guide") for reading `totmann.log` during setup, testing, and troubleshooting.
- Tightened mailbox-header serialisation for single-recipient `To:` headers by always quoting ASCII display names and keeping non-ASCII names RFC2047-encoded.
- Clarified enduser wording across the public guides and templates.
- Removed the extra reference sentence from the download-unavailable website page in all shipped locales and in the built-in fallback text.
- BREAKING: `{DOWNLOAD_NOTICE}` and `download_notice_single_use` were removed; single-use warning text now lives as `single_use_notice` inside the affected message in `totmann-recipients.php`.
- `{DOWNLOAD_LINKS}` now renders complete download blocks instead of raw URL lines and adds `X Downloads:` automatically when a mail contains more than one download.
- Added mandatory operator warning mails to `to_self` for operator-facing config/runtime problems that would otherwise only show up in the log.
- Added `operator_alert_interval_hours` as the public throttle key for those warning mails; only whole hours `1..24` are accepted and invalid/missing values now fall back automatically to `2`.
## v3.0.0
- Added browser-language website localisation via `Accept-Language` with starter locales `de-DE`, `en-GB`, `en-US`, `fr-FR`, `it-IT`, and `es-ES`.
- Reworked public website text to a more empathetic tone for neutral pages, confirmation pages, ACK pages, generic errors, and download-unavailable responses.
- ACK success pages now show the extra download reminder only for recipients whose escalation mail actually contained download links.
- ACK now stops all further escalation mails for the current escalation event, including pending per-recipient retry deliveries.
- Escalation delivery and ACK reminders now persist per recipient, so partial send failures no longer force duplicate re-sends to already successful recipients.
- Already issued valid download links now stay usable even if an unrelated message or recipient row later breaks in `totmann-recipients.php`.
- Added `{ACK_BLOCK}` as the preferred escalation-mail placeholder for the full acknowledgement hint plus URL; `{ACK_URL}` remains available for advanced custom mail bodies.
- Added `l18n_dir_name` as the configurable runtime directory name for website locale files.
- Reworked the operator-facing documentation and templates for enduser readability, including clearer field-4/field-5 download examples and ACK guidance.
- BREAKING: `totmann-recipients.php` uses 3 flat top-level sections (`$files`, `$messages`, `$recipients`) instead of the earlier PHP-DSL helper model.
- BREAKING: escalation mail text no longer falls back to `subject_escalate` or `body_escalate` in `totmann.inc.php`; every recipient row must reference a valid message key in field 3.
- Tightened mailbox-header serialisation for additional ASCII display-name special characters by always quoting ASCII display names.
- Verification: `php -l`, `phpstan`, and the project validation gate must be rerun after deployment because both runtime behaviour and documentation changed.
## v2.0.0
- BREAKING: recipient-specific escalation configuration now lives in exactly one file: `totmann-recipients.php`.
- BREAKING: removed the split model of `to_recipients` in `totmann.inc.php` plus separate `totmann-messages.php` and `totmann-downloads.php`.
- BREAKING: `recipients_file` is now the canonical runtime key in `totmann.inc.php`.
- BREAKING: `totmann.php` is now the only public web endpoint; the separate download endpoint file was removed.
- BREAKING: download runtime state now lives inside `totmann.json` instead of a separate download state file.
- BREAKING: one operator-facing rate-limit root remains (`rate_limit_dir`), with separate namespaces for normal web requests and downloads.
- Reminder mails to `to_self` are always sent individually (one mail per recipient entry).
- Escalation mails remain individual (one mail per recipient entry).
- The `To:` header no longer derives extra mailbox fragments from comma-splitting a single recipient string.
- Download links are configured directly under each recipient entry and can be zero, one, or many per recipient.
- The same file can be assigned to multiple recipients by repeating the same relative `file` path in each recipient entry.
- `{DOWNLOAD_NOTICE}` was introduced as the dedicated placeholder for the single-use warning text in that release.
- `single_use=true` now applies to the whole escalation event for that recipient/link pair, not to each newly generated reminder URL.
- `check` validates the unified recipient file and the simplified runtime file model.
- Documentation was reworked to match the simplified one-file recipient model and one-endpoint web model.
## v1.0.1
- Updated timing defaults to more realistic escalation behaviour:
	- `check_interval_seconds`: 1 day
	- `confirm_window_seconds`: 2 days
	- `remind_every_seconds`: 12 hours
	- `escalate_grace_seconds`: 4 hours
	- `missed_cycles_before_fire`: 2
- Added a worked timing example and clarified default-interval behaviour in `docs/Timing.md`.
- Refined wording and cross-reference text in `README.md` and `docs/Mail.md`.
## v1.0.0 – stable
- Initial stable release.
- Complete cycle engine with reminders, confirmation windows, grace handling, and escalation after repeated misses.
- Hardened web confirmation flow with scanner-resistant two-step confirmation and post-escalation confirmation blocking.
- Stealth-first endpoint behaviour with neutral responses for invalid/stale requests and unresolved runtime state.
- Optional recipient acknowledgement workflow with controlled reminder re-sends and clear limit behaviour.
- File-based runtime state with locking to coordinate timer ticks and web requests safely.
- Unified public link generation from one base URL and configurable endpoint/runtime filenames.
- Sendmail-based delivery pipeline (no dependency on PHP `mail()`).
- Built-in abuse protection for web requests (rate limit, fail-open strategy).
- Read-only preflight checks for deployment readiness, including optional web-user permission validation.
- Configurable logging output modes and runtime safety guards for clock/state edge cases.
- Documentation delivered as dedicated guides for installation, operations, web, timing, mail, troubleshooting, and release planning.
