# totman – Roadmap
![totman](../img/totman-icon.png)

This document lists planned next steps.
## Scope and intent
- No ETA commitments.
- Order is intentionally flexible and may change based on implementation complexity and real-world feedback.
- Focus remains on operational reliability and predictable behaviour.
## Planned items
### Optional recipient-assisted safety check before final escalation
- Add an optional intermediate “light escalation” step before final recipient messages are sent.
- After the normal confirmation window plus grace has expired, the system first sends a short check-in mail to the configured recipients instead of immediately sending the final messages.
- The light mail contains no final instructions or secrets; it asks recipients to verify whether the sender is safe, reachable, and able to respond.
- If the sender is unavailable but still alive or incapacitated, e. g., due to coma, hospitalisation, or similar circumstances, any one recipient may take over confirmation for the switch temporarily.
- That temporary takeover shifts the operational responsibility from the sender to the recipient side until the situation is clarified or reversed.
- Once one recipient confirms on behalf of the sender, the current escalation cycle is reset, paused, or handed over according to configuration, and final messages are not sent at that stage.
- If no recipient confirms within the configured recipient-check window, the system proceeds with the final escalation emails.
- The design must also cover how control returns to the original sender once they are able to manage the switch again.
- Possible return paths may include a dedicated reclaim link in a parallel notification mail, an explicit config switch, or a different later reclaim mechanism.
- Scope includes recipient confirmation tokens, wording of the light mail, timing configuration, temporary responsibility transfer, reclaim handling, audit/log output, replay protection, and clear separation from ACK links used for final escalation receipt acknowledgement.
### Exploratory shared-hosting compatibility mode
- Explore whether a reduced shared-hosting-compatible mode is feasible and safe at all; this is not a delivery commitment.
- The current design targets controlled self-hosted environments with clear separation between private state, webroot content, timers, and file permissions.
- Shared hosting would impose hard constraints around webroot separation, private file handling, cron reliability, file permissions, and isolation between web-facing and state data.
- Scope includes feasibility assessment, security implications, acceptable feature reductions, and a clear go/no-go conclusion rather than a promise of implementation.
### Mail priority headers
- Add configurable priority headers for mail types (for example CONFIRM, ACK, ESCALATION).
- Goal: improve inbox handling and client-side prioritisation where supported.
### TXT or HTML output mode
- Add a config option to send plain text or HTML mail format.
- Default remains plain text.
### Encrypted final recipient messages
- Add optional encryption for final recipient emails to reduce unauthorised access risk in transit/storage and allow sensitive content usage.
- Scope includes key handling strategy and operational recovery expectations.
### Optional "2-of-n" secret reconstruction mode
- Add an option where recipient emails contain separate secret fragments and at least 2 of n recipients must combine them.
- Example use cases: split password-vault access data or split seed phrase components.
- Dependency: requires per-recipient custom email support first.
### Alternative implementation languages
- Provide alternative implementations beside PHP to improve accessibility and adoption.
- Goal: preserve feature parity and operational semantics across implementations.
### Active-passive dual-server mail failover
- Add an optional two-server mode where both nodes run the script and exchange a heartbeat, but only the active node sends emails.
- If the standby node detects heartbeat loss, it takes over mail delivery automatically until the primary path is healthy again.
- Scope includes shared configuration/state strategy, split-brain prevention, takeover timing, and safe failback behaviour.
### Ratelimit directory cleanup (file-based mode)
- Add periodic cleanup for stale entries in `ratelimit/` to keep directory size bounded over time.
- Dependency: build this before SQLite migration, or skip it if you start SQLite migration immediately.
### Ratelimit storage migration to SQLite
- Replace the file-based `ratelimit/` directory approach with a SQLite-backed rate-limit store.
- Goal: improve concurrency handling, reduce file-IO overhead, and simplify operational cleanup/inspection.
