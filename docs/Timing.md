# totmannschalter – Timing model & presets
The system works in repeating cycles. Each cycle is anchored at `cycle_start_at`, set on initialisation and on every successful confirmation.
## Timeline definitions (per cycle)
- `next_check_at = cycle_start_at + check_interval_seconds` -> the moment the confirmation window opens
- `deadline_at = next_check_at + confirm_window_seconds` -> end of the confirmation window
- escalation may only fire after `deadline_at + escalate_grace_seconds` -> extra safety buffer after the deadline
## Behaviour (what actually happens)
1. Before `next_check_at`: nothing happens, no mails are sent.
2. From `next_check_at` inclusive until `deadline_at` exclusive:
	- reminder mails are sent to `to_self`
	- frequency is controlled by `remind_every_seconds`
3. At or after `deadline_at + escalate_grace_seconds`:
	- if you did not confirm within the window, the cycle counts as missed
	- if the threshold is not yet met, the tick starts a new cycle immediately, so the cadence shifts to “from now”
	- once `missed_cycles_before_fire` is reached, escalation triggers and a mail is sent to the recipients defined in `recipients_file`
		- if ACK is enabled, the escalation mail contains the ACK link
		- until any recipient clicks ACK, optional ACK reminders are re-sent, see `escalate_ack_*`
		- `escalate_ack_max_reminds = 0` disables ACK reminder re-sends, the initial escalation mail is still sent
		- after the configured ACK reminder limit is reached, one final log marker is written and recurring escalation logs pause until ACK or reset

> Note: `totmann.timer` can tick every minute, or faster.
> This does not change the timing model. It only determines how quickly the script notices that a boundary has been reached.
## Validation rules (applied by preflight and runtime)
- `check_interval_seconds >= 1`
- `confirm_window_seconds >= 1`
- `remind_every_seconds >= 1`
- `escalate_grace_seconds >= 0`
- `missed_cycles_before_fire >= 1`
- if ACK is enabled: `escalate_ack_remind_every_seconds >= 1`, `escalate_ack_max_reminds >= 0`
- warning, but allowed: `confirm_window_seconds > check_interval_seconds`
- warning, but allowed: `remind_every_seconds > confirm_window_seconds`
- warning, but allowed: ACK remind interval below 60 seconds, runtime clamps to 60 seconds
## Changing timings during operation
You can change timing values in `totmann.inc.php` while `totmann.timer` is running.
A `systemctl stop` or `systemctl start` is not required for these config-only changes.

How it applies:
- new values are loaded on the next timer tick
- existing state timestamps such as `next_check_at`, `deadline_at`, and `next_reminder_at` are not rewritten immediately
- updated values therefore apply to subsequent cycle calculations and reminder scheduling decisions

For deterministic tests after larger timing changes, delete the configured runtime files `state_file`, `lock_file`, and `log_file_name`, then initialise once.
## Recommended presets
### Production preset (sane defaults, low noise)
This is conservative and human-friendly while keeping escalation below one week:
- cycle: 1 day
- confirm window: 2 days
- reminder: every 12 hours during the window
- grace: 4 hours after deadline
- escalation threshold: 2 missed cycles before notifying others
- ACK reminders: every 12 hours, up to 25 times
```php
// Cycle timing (production)
'check_interval_seconds' => 60 * 60 * 24 * 1, // 1 day
'confirm_window_seconds' => 60 * 60 * 24 * 2, // 2 days
'remind_every_seconds' => 60 * 60 * 12, // 12 hours
'escalate_grace_seconds' => 60 * 60 * 4, // 4 hours
'missed_cycles_before_fire' => 2, // 2 missed cycles before escalation

// ACK (production)
'escalate_ack_enabled' => true,
'escalate_ack_remind_every_seconds' => 60 * 60 * 12, // 12 hours
'escalate_ack_max_reminds' => 25,
```
### What this means in practice
- You start receiving reminders one day after cycle start and then have two days to confirm.
- If you miss once, escalation still does not trigger. It takes two misses by design. After each miss, the script starts a new cycle immediately, so the cadence shifts.
- Worst-case time from last confirm to first escalation: 6 days 8 hours.
### Worked example with production preset
Assume script initialisation at 1 January 2026, 09:00, and no confirmation clicks.

Cycle 1:
- `cycle_start_at`: 01.01.2026 09:00
- `next_check_at`: 02.01.2026 09:00
- reminders to `to_self`: 02.01. 09:00, 02.01. 21:00, 03.01. 09:00, 03.01. 21:00
- `deadline_at`: 04.01.2026 09:00
- `fireAt`: 04.01.2026 13:00, `deadline + 4h`
- result: `missed_cycles = 1`, no escalation, next cycle starts at 04.01.2026 13:00

Cycle 2:
- `next_check_at`: 05.01.2026 13:00
- reminders to `to_self`: 05.01. 13:00, 06.01. 01:00, 06.01. 13:00, 07.01. 01:00
- `deadline_at`: 07.01.2026 13:00
- `fireAt`: 07.01.2026 17:00
- result: `missed_cycles = 2` -> first escalation mail to the recipients from `recipients_file` at or after 07.01.2026 17:00
### Test preset (fast, but still realistic)
This is designed to test the whole flow quickly without mail flooding:
- cycle: 5 minutes
- confirm window: 4 minutes
- reminder: every 1 minute during the window
- grace: 1 minute
- escalation threshold: 1 missed cycle, so you can see escalation quickly
- ACK reminders: every 2 minutes, max 5 reminder re-sends, initial escalation mail not counted

```php
// Cycle timing (test)
'check_interval_seconds' => 60 * 5, // 5 minutes until window opens
'confirm_window_seconds' => 60 * 4, // 4 minutes to confirm
'remind_every_seconds' => 60, // remind every minute during window
'escalate_grace_seconds' => 60, // 1 minute grace after deadline
'missed_cycles_before_fire' => 1, // escalate after 1 missed cycle

// ACK (test)
'escalate_ack_enabled' => true,
'escalate_ack_remind_every_seconds' => 60 * 2, // every 2 minutes
'escalate_ack_max_reminds' => 5,
```
## Walkthrough: expected mails with the test preset
Assume the script is initialised at T0, cycle start.
- T0: cycle starts, `cycle_start_at = now`
- T0 + 5 min: window opens, `next_check_at`
- T0 + 9 min: deadline, `deadline_at`
- T0 + 10 min: escalation is allowed, deadline + grace
### If you do not confirm
During the window, T0+5 to T0+9:
- reminders to `to_self` at approximately:
	- T0 + 5 min
	- T0 + 6 min
	- T0 + 7 min
	- T0 + 8 min

After deadline + grace:
- at or after T0 + 10 min:
	- cycle is marked as missed
	- escalation triggers immediately because `missed_cycles_before_fire = 1`
	- escalation mail is sent to the recipients from `recipients_file`
	- if ACK is enabled, the escalation mail contains the ACK link
- if no recipient acknowledges:
	- ACK reminder re-sends at approximately:
		- T0 + 12 min
		- T0 + 14 min
		- T0 + 16 min
		- T0 + 18 min
		- T0 + 20 min, last one because max is 5
### If you do confirm
- You receive reminders during the window as above.
- You open the confirmation link, GET shows the button and POST confirms.
- Confirmation resets the cycle immediately:
	- `cycle_start_at = now`
	- a new token is issued
	- `missed_cycles` is reset
	- escalation state and ACK state are reset
- After confirming, reminders stop for that cycle.
## Practical testing checklist (fast and repeatable)
1. Apply the test preset and set `to_self` plus all recipient addresses in `totmann-recipients.php` to your own addresses.
2. Factory-reset the runtime state for deterministic tests:
	- remove the configured runtime files `state_file`, `lock_file`, and `log_file_name`
	- initialise once with the correct umask, see [Installation](Installation.md "Installation guide"), section “Clean initialise”
3. Watch logs while testing:
	- `journalctl -u totmann.service -f`
	- `tail -f /var/lib/totmann/totmann.log`
4. Test scenarios:
	- Scenario A, confirm: confirm within the window -> cycle resets, escalation never triggers
	- Scenario B, no confirm: wait past deadline plus grace -> escalation mail arrives and ACK reminders follow
	- Scenario C, ACK: click ACK link -> ACK reminders stop immediately
	- Scenario D, downloads: test at least one normal download, one `single_use=true` download, and the same file for two different recipients

> Tip: when testing deliverability, do not use tiny intervals in seconds. Google, Microsoft, and others treat rapid identical emails as suspicious.
