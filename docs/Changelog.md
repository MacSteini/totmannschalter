# totmannschalter – Changelog
All notable changes to this project will be documented in this file.

This project uses semantic versioning:
- MAJOR: breaking changes
- MINOR: new features (backwards compatible)
- PATCH: bugfixes/small improvements (backwards compatible)
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
- Documentation delivered as dedicated guides in `docs/` for installation, operations, web, timing, mail, troubleshooting, and release planning.
