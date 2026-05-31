# totman – Roadmap
![totman](../img/totman-icon.png)

This document lists planned next steps.
## Scope and intent
- No ETA commitments.
- Order is intentionally flexible and may change based on implementation complexity and real-world feedback.
- Focus remains on operational reliability and predictable behaviour.
- Items describe broad directions, not committed feature specifications.
## Planned items
### Portable handover and long-term continuity
- Explore ways to make long-term handover, recipient preparedness, and recovery expectations more robust without weakening the self-hosted model.
### Recipient-assisted safety checks
- Explore whether recipients can safely help clarify ambiguous missed-confirmation situations before final escalation.
### Shared-hosting compatibility
- Assess whether a reduced shared-hosting-compatible mode can be safe enough to support without compromising private state, timers, permissions, or file handling.
### Mail presentation and delivery signals
- Improve how totman messages are presented, prioritised, and rendered while keeping conservative plain-text operation available.
### Protected final messages and shared recovery
- Explore stronger protection and recovery patterns for sensitive final content, including recipient-specific and collaborative recovery approaches.
### Alternative runtimes
- Evaluate whether additional implementation languages could make totman easier to adopt while preserving the same operational semantics.
### Resilient operations and failover
- Explore ways to make long-running deployments more robust across host, timer, mail, and state-management failures.
### Rate-limit storage and cleanup
- Improve long-term rate-limit maintenance, storage behaviour, and operational inspection.
