# totmannschalter – Roadmap
This document lists planned next steps.
## Scope and intent
- No ETA commitments.
- Order is intentionally flexible and may change based on implementation complexity and real-world feedback.
- Focus remains on operational reliability and predictable behaviour.
## Planned items
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
