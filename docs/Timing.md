# totmannschalter – Timing model & presets
The system works in repeating cycles. Each cycle is anchored at `cycle_start_at` (set on initialisation and on every successful confirmation)
## Timeline definitions (per cycle)
- `next_check_at = cycle_start_at + check_interval_seconds` → the moment the confirmation window opens
- `deadline_at = next_check_at + confirm_window_seconds` → end of the confirmation window
- Escalation may only fire after: `deadline_at + escalate_grace_seconds` → extra safety buffer after the deadline
## Behaviour (what actually happens)
1. **Before `next_check_at`**: nothing happens (no emails)
2. **From `next_check_at` (inclusive) until `deadline_at` (exclusive)**:
	- Reminder emails are sent to `to_self`
	- Frequency is controlled by `remind_every_seconds`
3. **At/after `deadline_at + escalate_grace_seconds`**:
	- If you did not confirm within the window, the cycle counts as missed
	- If the threshold is not yet met, the tick starts a new cycle immediately (cadence shifts to “from now”)
	- Once `missed_cycles_before_fire` is reached, escalation triggers and a mail is sent to `to_recipients`
		- If ACK is enabled, the escalation mail contains an ACK link
		- Until any recipient clicks ACK, optional ACK reminders are re-sent (see `escalate_ack_*`)
		- `escalate_ack_max_reminds = 0` disables ACK reminder re-sends (initial escalation mail is still sent)
		- After the configured ACK reminder limit is reached, one final log marker is written and recurring escalation logs pause until ACK or reset

> Note: the `totmann.timer` can tick every minute (or faster)
> This does not change the timing model – it only determines how quickly the script notices a boundary has been reached

## Validation rules (applied by preflight and runtime)
- `check_interval_seconds >= 1`
- `confirm_window_seconds >= 1`
- `remind_every_seconds >= 1`
- `escalate_grace_seconds >= 0`
- `missed_cycles_before_fire >= 1`
- if ACK is enabled: `escalate_ack_remind_every_seconds >= 1`, `escalate_ack_max_reminds >= 0`
- warning (allowed): `confirm_window_seconds > check_interval_seconds`
- warning (allowed): `remind_every_seconds > confirm_window_seconds`
- warning (allowed): ACK remind interval below 60 seconds (runtime clamps to 60 seconds)

## Changing timings during operation
You can change timing values in `totmann.inc.php` while `totmann.timer` is running.
A `systemctl stop/start` is not required for these config-only changes.

How it applies:
- New values are loaded on the next timer tick.
- Existing state timestamps (`next_check_at`, `deadline_at`, `next_reminder_at`) are not rewritten immediately.
- Updated values therefore apply to subsequent cycle calculations and reminder scheduling decisions.

For deterministic tests after larger timing changes, delete the runtime files (`state_file`, `lock_file`, `log_file_name`) and initialise once.
## Recommended presets
### Production preset (sane defaults, low noise)
This is conservative and human-friendly:
- Cycle: **14 days**
- Confirm window: **3 days**
- Reminder: **every 12 hours** during the window
- Grace: **6 hours** after deadline
- Escalation threshold: **3 missed cycles** before notifying others
- ACK reminders: **every 12 hours**, up to **25** times (safety cap)
```php
// Cycle timing (production)
'check_interval_seconds' => 60 * 60 * 24 * 14, // 14 days
'confirm_window_seconds' => 60 * 60 * 24 * 3, // 3 days
'remind_every_seconds' => 60 * 60 * 12, // 12 hours
'escalate_grace_seconds' => 60 * 60 * 6, // 6 hours
'missed_cycles_before_fire' => 3, // 3 missed cycles before escalation

// ACK (production)
'escalate_ack_enabled' => true,
'escalate_ack_remind_every_seconds' => 60 * 60 * 12, // 12 hours
'escalate_ack_max_reminds' => 25,
```
**What this means in practice**
- You only start receiving reminders every two weeks, then you have three days to confirm
- If you miss once, escalation still does not trigger – it takes three misses (by design). After each miss, the script starts a new cycle immediately (so the cadence shifts)
### Test preset (fast, but still “realistic”)
This is designed to test the whole flow quickly without mail-bombing:
- Cycle: **5 minutes**
- Confirm window: **4 minutes**
- Reminder: **every 1 minute** during the window
- Grace: **1 minute**
- Escalation threshold: **1 missed cycle** (so you can see escalation quickly)
- ACK reminders: **every 2 minutes**, max **5** reminder re-sends (initial escalation mail not counted)

```php
// Cycle timing (test)
'check_interval_seconds' => 60 * 5, // 5 minutes until window opens
'confirm_window_seconds' => 60 * 4, // 4 minutes to confirm
'remind_every_seconds' => 60, // remind every minute (during window)
'escalate_grace_seconds' => 60, // 1 minute grace after deadline
'missed_cycles_before_fire' => 1, // escalate after 1 missed cycle (test only)

// ACK (test)
'escalate_ack_enabled' => true,
'escalate_ack_remind_every_seconds' => 60 * 2, // every 2 minutes
'escalate_ack_max_reminds' => 5,
```
## Walkthrough: expected emails with the test preset
Assume the script is initialised at T0 (cycle start):
- **T0**: cycle starts (`cycle_start_at = now`)
- **T0 + 5 min**: window opens (`next_check_at`)
- **T0 + 9 min**: deadline (`deadline_at`)
- **T0 + 10 min**: escalation is allowed (deadline + grace)
### If you do not confirm
During the window (T0+5 to T0+9):
- Reminders to `to_self` at approx:
	- T0 + 5 min
	- T0 + 6 min
	- T0 + 7 min
	- T0 + 8 min

After deadline + grace:
- At/after **T0 + 10 min**:
	- Cycle is marked as missed
	- Escalation triggers immediately (because `missed_cycles_before_fire = 1`)
	- Escalation email is sent to `to_recipients`
	- If ACK is enabled, the escalation email contains the ACK link
- If no recipient acknowledges:
	- ACK reminder re-sends at approx:
		- T0 + 12 min
		- T0 + 14 min
		- T0 + 16 min
		- T0 + 18 min
		- T0 + 20 min (last one, because max is 5)
### If you do confirm
- You receive reminders during the window as above
- You open the confirmation link (GET shows button, POST confirms)
- Confirmation resets the cycle immediately:
	- `cycle_start_at = now`
	- new token is issued
	- `missed_cycles` is reset
	- escalation state and ACK state are reset
- After confirming, reminders stop for that cycle
## Practical testing checklist (fast and repeatable)
1. Apply the **test preset** and set `to_self` + `to_recipients` to your own addresses
2. Factory reset the runtime state (recommended for deterministic tests):
	- remove configured runtime files (`state_file`, `lock_file`, `log_file_name`)
	- initialise once with the correct umask (see [Installation](Installation.md "Installation"), section “Clean initialise”)
3. Watch logs while testing:
	- `journalctl -u totmann.service -f`
	- `tail -f /var/lib/totmann/totmann.log`
4. Test scenarios:
	- **Scenario A (confirm):** confirm within the window → cycle resets, escalation never triggers
	- **Scenario B (no confirm):** wait past deadline+grace → escalation mail arrives + ACK reminders follow
	- **Scenario C (ACK):** click ACK link → ACK reminders stop immediately

> Tip:
> When testing deliverability, do not use tiny intervals (seconds)
> Google, Microsoft, and others treat rapid identical emails as suspicious
> The test preset above stays short but avoids “mail flooding”.
