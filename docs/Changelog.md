# totmannschalter – Changelog
This file documents all notable changes to this project.

This project uses semantic versioning:
- MAJOR: breaking changes
- MINOR: new features (backwards compatible)
- PATCH: bugfixes/small improvements (backwards compatible)
## v2.0.0
- BREAKING: `to_recipients` now requires structured entries in the format `[address]` or `[address, id]`.
- BREAKING: recipient-specific entries in `mail_file` are now structured subject/body pairs (`id => ['subject' => ..., 'body' => ...]`), and body placeholders include `{ACK_URL}`.
- BREAKING: removed `ack_mail_default`; fallback now uses `subject_escalate` + `body_escalate`.
- Reminder mails to `to_self` are now always sent individually (one mail per recipient).
- Escalation mails remain per recipient (individual delivery), not as one shared recipient list.
- `check` reports `FAIL` for invalid recipient IDs and missing message mappings; runtime delivery remains fail-safe and falls back to `subject_escalate` + `body_escalate`.
- Internal split: `totmann-tick.php` is now a runner-only entrypoint; helper declarations moved into `lib_file` (template default: `totmann-lib.php`) to meet strict file side-effect linting.
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
