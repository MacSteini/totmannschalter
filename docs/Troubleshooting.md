# totmannschalter – Troubleshooting
## Neutral page (“This page is not available.”)
This is expected behaviour for:
- invalid or missing token
- stale or non-current token (depending on your stealth config)
- most runtime failures on the website side (by design: stealth)
	- If you used the current valid token, you may instead see a generic error page with a code

Most common cause:
Your configured web endpoint (`web_file`) cannot read/write the state directory or cannot acquire the configured lock file.

Typical causes:
- you used `root:root` instead of `root:<WEB_GROUP>`
- runtime files were created with the wrong permissions (e. g., `0644`, so group cannot write)
- your web user/group is not what you assumed (custom PHP-FPM pools are common)
- the state dir is missing setgid, so files created by `root` do not stay in the web group
## Fast checks (90 seconds)
### Check ownership and permissions (state dir and runtime files)
```sh
ls -la /var/lib/totmann
```

Expected (conceptually):
- directory: `root:<WEB_GROUP>` and mode like `drwxrws---` (`2770`)
- files: `-rw-rw----` (`0660`) for configured runtime files such as `state_file`, `lock_file`, and `log_file_name`
### Confirm `systemd` points to the correct state dir and umask
```sh
sudo systemctl show totmann.service -p Environment -p WorkingDirectory -p ExecStart -p UMask
```

Make sure you see:
- `Environment=TOTMANN_STATE_DIR=/var/lib/totmann`
- `WorkingDirectory=/var/lib/totmann`
- `UMask=0007`
### Confirm the real web identity can write
Replace `<WEB_USER>` with the PHP runtime user (e. g., `www-data`).
```sh
sudo -u <WEB_USER> php -r '$f="/var/lib/totmann/.permtest"; echo (file_put_contents($f,"x")!==false)?"write:OK\n":"write:NO\n"; @unlink($f);'
```

If you do not know `<WEB_USER>` (PHP-FPM):
```sh
sudo grep -R --line-number "^\s*user\s*=" /etc/php/*/fpm/pool.d
sudo grep -R --line-number "^\s*group\s*=" /etc/php/*/fpm/pool.d
```
## Tick runs but no follow-up mails
First: reminders are only sent during the confirmation window.
- no mails before `next_check_at`
- reminders only from `next_check_at` (inclusive) until `deadline_at` (exclusive)
- escalation only at or after `deadline_at + escalate_grace_seconds`, and only once `missed_cycles_before_fire` is reached

If Totmannschalter detects an operator-facing setup/runtime problem while the tick is running, it can also send a separate warning mail to `to_self`.
Those warning mails are mandatory on purpose, are throttled by `operator_alert_interval_hours`, and do not replace the log.
### Check timer and logs
```sh
systemctl list-timers | grep totmann
journalctl -u totmann.service -n 200 --no-pager
tail -n 200 /var/lib/totmann/totmann.log
```

Choose log commands according to `log_mode`:
- `syslog` => rely on `journalctl`
- `file` => rely on `tail`
- `both` => use both
- `none` => no Totmannschalter file-log lines are expected
If you are unsure how to read the file-log lines or how they relate to `journalctl`, use [Log guide](Logs.md "Log guide").
### Check state actually progresses
Look at these fields in `totmann.json` under the `runtime` subtree:
- `next_check_at`, `deadline_at` (window boundaries)
- `next_reminder_at` (should advance by `remind_every_seconds` after each reminder send)
- `escalated_sent_at` (set when escalation mail was sent)
- `escalate_ack_next_at` / `escalate_ack_sent_count` (only after escalation, if ACK enabled)

The shared `totmann.json` contains two top-level areas:
- `runtime`
- `downloads`

If the timing fields in `runtime` are missing or inconsistent (`cycle_start_at`, `next_check_at`, `deadline_at`), the tick performs conservative state recovery (new cycle, no immediate escalation). Check logs for `State sanity recovery triggered…`.
```sh
cat /var/lib/totmann/totmann.json
```

If `next_reminder_at` does not move forward, the tick likely failed before saving state. Check logs for an exception.

After escalation ACK reminders hit the configured maximum, one final “limit reached” marker is expected, then recurring escalation status lines stop by design. This keeps logs focused on actionable events.

If `escalate_ack_at` is already set, no further escalation mails are sent for that escalation event.
## You received an operator warning mail
Treat that mail as a direct setup/runtime problem report from the script itself.

What to do first:
1. read the original problem text in the warning mail
2. follow the built-in “What to check next” hint from that mail
3. run `php totmann-tick.php check` in your state directory
4. inspect `totmann.log` for the same fingerprint or matching error text
5. compare the affected values in `totmann.inc.php` and `totmann-recipients.php`
6. if the warning refers to a bootstrap problem such as `CONFIG ERROR: ...`, also inspect `journalctl -u totmann.service`

Throttle behaviour:
- `operator_alert_interval_hours` accepts only `1..24`
- if you remove it or set an invalid value, Totmannschalter falls back automatically to `2`
- the warning mail itself cannot be disabled
## Downloads do not work
Check these points in order:
1. the relevant file alias exists in `$files` inside `totmann-recipients.php`
2. the recipient row really references that alias in field 4 or field 5
3. you used the intended field:
	- field 4 for normal downloads
	- field 5 for single-use downloads
4. the real file path in `$files` is relative to `download_base_dir`, not absolute
5. the real file exists under `download_base_dir`
6. `download_valid_days` in `totmann.inc.php` has not already expired the link
7. `{DOWNLOAD_LINKS}` is present in the relevant escalation mail body
8. if field 5 is used, remember that only the first successful download of that escalation event is allowed
9. if field 5 is used, confirm that the referenced message defines `single_use_notice`

If a recipient’s download aliases cannot be resolved, that recipient’s escalation mail is still sent without those links. Check logs for the underlying reason.

Already issued valid download links still resolve even if an unrelated message or recipient row in `totmann-recipients.php` is later broken.

If you are unsure how field 4, field 5, `{DOWNLOAD_LINKS}`, and `single_use_notice` work together, go back to [Mail delivery notes](Mail.md "Mail delivery notes").
## Website language is wrong or always English
Check these points in order:
1. `l18n/` was copied into your configured `state_dir`
2. `l18n_dir_name` in `totmann.inc.php` matches the real directory name
3. the expected locale file exists (e. g., `l18n/de-DE.php`)
4. the browser really sends the language you expect in `Accept-Language`

Current behaviour:
- exact locale match first (`de-DE`)
- base-language fallback second (`de` => `de-DE`)
- default fallback last (`en-US`)

Note:
- only the language follows the browser
- timestamps still follow `mail_timezone`
- if the locale files are missing or broken at runtime, the endpoint falls back to `en-US`
## Proxy/IP settings
Default and safest setting:
```php
'ip_mode' => 'remote_addr',
```

Only use:
```php
'ip_mode' => 'trusted_proxy',
```
if you are really behind a proxy you control and have correctly configured both:
- `trusted_proxies`
- `trusted_proxy_header`

If this is set incorrectly, request attribution in logs can be spoofed.
## Factory reset / re-arm
If you want to restart completely:
```sh
sudo systemctl stop totmann.timer
sudo rm -f /var/lib/totmann/totmann.json /var/lib/totmann/totmann.lock /var/lib/totmann/totmann.log
sudo sh -c 'umask 0007; /usr/bin/php /var/lib/totmann/totmann-tick.php tick'
sudo systemctl start totmann.timer
```

The `rm` command uses the filenames from the template config. Adapt it if you changed them in `totmann.inc.php`.

> **Why `umask 0007` matters**
> `umask 0007` makes newly created files group-writable but not world-accessible. In practice, files created by the tick (running as `root`) become `0660` and directories become `0770`. Combined with the setgid dir bit (`2770`), new files stay in `<WEB_GROUP>`, so your configured web endpoint (`web_file`) can write the configured state/lock files safely.
