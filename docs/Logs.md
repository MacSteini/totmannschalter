# totmannschalter – Log guide
## What `totmann.log` is for
`totmann.log` records Totmannschalter activity in plain English.

Use it when you want to answer practical questions such as:
- Did the cycle reset?
- Did escalation already trigger?
- Was a recipient skipped?
- Did an ACK stop the current escalation event?
- Why did a download or web request not behave as expected?

Important:
- critical operator-facing setup/runtime problems can also trigger a separate operator warning mail to `to_self`
- those warning mails do not replace the log; they point you back to the same underlying problem
- repeated operator warning mails are throttled by `operator_alert_interval_hours`

If you use `log_mode = file` or `log_mode = both`, the default file is `/var/lib/totmann/totmann.log`.

If you changed `log_file_name` or `log_file` in `totmann.inc.php`, use that effective path instead.
## What belongs where
You may see Totmannschalter errors in 3 different places:

1. `totmann.log`
	- normal runtime activity and runtime failures after bootstrap
2. `journalctl -u totmann.service`
	- service start/stop information
	- STDERR output from the tick, including early bootstrap failures such as `CONFIG ERROR: ...`
3. operator warning mails to `to_self`
	- only for operator-facing setup/runtime problems
	- throttled by `operator_alert_interval_hours`

Practical rule:
- if the tick never got far enough to write a normal runtime log line, look in `journalctl`
- if you received an operator warning mail, use the fingerprint or error text to find the matching context in `totmann.log` or `journalctl`
## Before you read the log
Choose your log command according to `log_mode`:
- `file` => read `totmann.log`
- `syslog` => use `journalctl -u totmann.service`
- `both` => you can use both
- `none` => Totmannschalter does not write a file log

Useful commands:
```sh
tail -f /var/lib/totmann/totmann.log
journalctl -u totmann.service -f
```
## How one log line is structured
Each file-log line starts with an ISO timestamp and then the message text.

Example:
```text
[2026-04-10T12:34:56+00:00] confirm: OK ip=203.0.113.10 next_check_at=2026-04-11T12:34:56+00:00
```

Practical reading order:
1. read the timestamp
2. read the leading topic such as `confirm:`, `ack:`, or `Escalation mail ...`
3. read the rest as the outcome of that event
## Early bootstrap and preflight failures
These messages are important, but they are not guaranteed to appear in `totmann.log`.

Typical STDERR / journal examples:
```text
CONFIG ERROR: Missing config key: download_valid_days
BOOTSTRAP ERROR: missing/unreadable totmann.inc.php: /var/lib/totmann/totmann.inc.php
```

How to interpret them:
- `CONFIG ERROR: ...` => configuration loaded far enough to check, but a required value or file structure is wrong
- `BOOTSTRAP ERROR: ...` => the script failed before normal runtime setup completed

What to do:
- read the exact missing/invalid key or file path
- run `php totmann-tick.php check` in your state directory
- inspect `totmann.inc.php` and `totmann-recipients.php`
- if the problem happens under `systemd`, confirm it again with `journalctl -u totmann.service`

Important:
- these messages may still trigger a separate operator warning mail if Totmannschalter can load enough mail configuration to send one
- do not rely on them appearing in `totmann.log`
## Normal confirmation activity
Typical lines:
```text
confirm: OK ip=203.0.113.10 next_check_at=2026-04-11T12:34:56+00:00
confirm: stale-or-noncurrent token used ip=203.0.113.10
confirm: blocked after escalation ip=203.0.113.10
```

How to interpret them:
- `confirm: OK ...` => the button click was accepted and the cycle was reset
- `stale-or-noncurrent token used` => an older link was used; the current cycle had already moved on
- `blocked after escalation` => escalation had already started, so this confirmation could no longer reset the cycle

What to do:
- `confirm: OK ...` => nothing else; this is the expected success line
- stale/non-current token => check whether the user clicked an older reminder
- blocked after escalation => check whether the deadline was already missed and whether escalation mail was sent
## ACK activity
Typical lines:
```text
ack: OK ip=203.0.113.10
ack: escalation acknowledged; no further escalation mails will be sent for this event.
ack: stale-or-noncurrent token used ip=203.0.113.10
ack: recipient token missing for recipient@example.com during reminder phase; skipping recipient.
```

How to interpret them:
- `ack: OK ...` => one recipient confirmed receipt successfully
- `no further escalation mails ...` => the current escalation event is finished from the system’s point of view
- `stale-or-noncurrent token used` => an older ACK link was used
- `recipient token missing ... skipping recipient` => the reminder phase could not build an ACK link for that one recipient

What to do:
- `ack: OK ...` => expected success; no more escalation mails should follow for that event
- stale/non-current token => check whether the recipient used an older reminder
- `recipient token missing ...` => inspect the relevant recipient row and mail body; other valid recipients continue
## Escalation mail delivery
Typical lines:
```text
Escalation mail sent to recipient@example.com.
Escalation mail failed for recipient@example.com: ...
Escalation delivery progress (sent_now=1, failed_now=1, recipients=2).
Recipient skipped: ...
Operator alert sent for recipient_skipped (fingerprint=..., recipients=1).
```

How to interpret them:
- `Escalation mail sent ...` => that one recipient mail was handed to sendmail successfully
- `Escalation mail failed ...` => that one recipient mail failed; the reason follows after the colon
- `Escalation delivery progress ...` => this tick finished one escalation delivery pass and shows how many recipients succeeded or failed in that pass
- `Recipient skipped: ...` => that one recipient row was unusable, so the script continued without sending to that recipient
- `Operator alert sent ...` => Totmannschalter also sent a separate operator warning mail to `to_self`

What to do:
- sent => nothing else; this is expected
- failed => inspect the error text first; common causes are invalid mailbox formatting, sendmail problems, or file/message config errors
- delivery progress with failures => inspect the matching `Escalation mail failed ...` lines directly above or below it
- skipped => inspect the referenced recipient row in `totmann-recipients.php`
- operator alert sent => open the warning mail, follow the suggested fix, then verify the same problem stops reappearing

Important:
- one failing recipient does not automatically stop other valid recipients
- one skipped recipient does not mean the whole escalation event failed
## Operator warning lines
Typical lines:
```text
Operator alert sent for recipient_skipped (fingerprint=..., recipients=1).
Operator alert delivery failed for recipient_skipped (fingerprint=...): ...
Operator alert handling failed: ...
Operator alert state save failed: ...
```

How to interpret them:
- `Operator alert sent ...` => a separate operator warning mail was handed to sendmail successfully
- `Operator alert delivery failed ...` => Totmannschalter detected the problem but could not deliver the warning mail to at least one `to_self` address
- `Operator alert handling failed ...` => even the warning-mail helper hit a runtime problem while trying to process the alert
- `Operator alert state save failed ...` => the script handled the runtime problem but could not persist the updated alert-throttle state afterwards

What to do:
- `Operator alert sent ...` => read the mail, then fix the underlying problem named by the fingerprinted error
- `Operator alert delivery failed ...` => check `to_self`, `mail_from`, and `sendmail_path`
- `Operator alert handling failed ...` => inspect the surrounding `ERROR: ...` or `Recipient skipped: ...` lines and rerun `php totmann-tick.php check`
- `Operator alert state save failed ...` => inspect state-dir permissions, lock handling, and `totmann.json`

Practical note:
- the fingerprint stays stable for the same alert type plus the same normalised error text
- repeated alerts with the same fingerprint are throttled by `operator_alert_interval_hours`
- if you set an invalid value or remove that key, Totmannschalter falls back to `2` hours
## ACK reminder problems
Typical lines:
```text
ack: recipient token missing for recipient@example.com during reminder phase; skipping recipient.
Escalation ACK reminder failed for recipient@example.com: ...
Escalation ACK reminder progress (sent_now=0, failed_now=1).
```

How to interpret them:
- `recipient token missing ...` => that one recipient could not get an ACK reminder link in this pass
- `Escalation ACK reminder failed ...` => sendmail handoff failed for that ACK reminder mail
- `Escalation ACK reminder progress ...` => this tick finished one ACK reminder pass and reports the totals for that pass

What to do:
- inspect the matching recipient row and the escalation state for that recipient
- check sendmail configuration if the line is a delivery failure
- expect other valid recipients to continue; this does not automatically stop the whole escalation event
## General runtime failure lines
Typical lines:
```text
ERROR: ...
```

How to interpret them:
- this is the main runtime catch-all for unexpected failures after bootstrap completed

What to do:
- read the exact exception text
- inspect the surrounding log lines for context
- if an operator warning mail was sent as well, use its fingerprint and fix hint as the operator-facing summary
- rerun `php totmann-tick.php check` before trusting the system again
## Download-related behaviour
Download problems do not always create a large dedicated error page. The useful evidence is often the surrounding escalation log plus your current recipient/file configuration.

Practical checks:
1. confirm that the intended alias exists in `$files`
2. confirm that the recipient row uses that alias in field 4 or field 5
3. confirm that the real file exists under `download_base_dir`
4. confirm that the link is still inside `download_valid_days`

If you need the full operator model for fields 4 and 5, go back to [Mail delivery notes](Mail.md "Mail delivery notes").
## When a line is harmless
These usually do not mean the system is broken:
- `confirm: stale-or-noncurrent token used ...`
- `ack: stale-or-noncurrent token used ...`
- one `Recipient skipped: ...` line while other recipient mails still send

These lines usually reflect:
- an old link was clicked
- one recipient row is wrong while others are still valid
- a user action happened after the cycle had already moved on
## When you should act immediately
Pay attention when you see:
- repeated `Escalation mail failed for ...`
- repeated `Recipient skipped: ...`
- repeated operator warning mails about the same fingerprint after the throttle interval
- `Operator alert delivery failed ...`
- `Operator alert handling failed ...`
- `Operator alert state save failed ...`
- repeated `ERROR: ...`
- no `confirm: OK ...` line even though you completed the confirmation button flow
- no `ack: OK ...` line even though a recipient clicked the ACK link

Recommended response:
1. compare the affected mailbox and message key with `totmann-recipients.php`
2. confirm that `totmann.inc.php` still points to the intended `recipients_file`, `log_mode`, and `download_base_dir`
3. rerun `php totmann-tick.php check`
4. use `journalctl -u totmann.service` as well if the failure may have happened during bootstrap
5. use [Troubleshooting](Troubleshooting.md "Troubleshooting") if the log alone is not enough
