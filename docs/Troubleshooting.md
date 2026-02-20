# totmannschalter – Troubleshooting
## “Request received” (neutral page)
This is expected behaviour for:
- invalid/missing token
- stale/non-current token (depending on your stealth config)
- most internal web-side failures (by design: stealth). If you used the current valid token, you may instead see a generic error page with a code.

Most common cause:
Your configured web endpoint (`web_file`) cannot read/write the state directory or cannot acquire the configured lock file.

Typical causes:
- You used `root:root` instead of `root:<WEB_GROUP>`.
- Runtime files were created with the wrong permissions (e. g., `0644`, so group cannot write).
- Your web user/group is not what you assumed (custom PHP-FPM pools are common).
- The state dir is missing setgid, so files created by `root` don’t stay in the web group.
## Fast checks (90 seconds)
### Check ownership + perms (state dir + runtime files)
```sh
ls -la /var/lib/totmann
```
Expected (conceptually):
- directory: `root:<WEB_GROUP>` and mode like `drwxrws---` (2770)
- files: `-rw-rw----` (0660) for your configured runtime files (`state_file`, `lock_file`, `log_file_name`)
### Confirm `systemd` points to the correct state dir + umask
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
If you don’t know `<WEB_USER>` (PHP-FPM):
```sh
sudo grep -R --line-number "^\s*user\s*=" /etc/php/*/fpm/pool.d
sudo grep -R --line-number "^\s*group\s*=" /etc/php/*/fpm/pool.d
```
## Tick runs but no follow-up mails
First: reminders are only sent **during the confirmation window**:
- no mails before `next_check_at`
- reminders only from `next_check_at` (inclusive) until `deadline_at` (exclusive)
- escalation only at/after `deadline_at + escalate_grace_seconds`, and only once `missed_cycles_before_fire` is reached
### Check timer + logs
```sh
systemctl list-timers | grep totmann
journalctl -u totmann.service -n 200 --no-pager
tail -n 200 /var/lib/totmann/totmann.log
```
Choose log commands according to `log_mode`:
- `syslog` => rely on `journalctl`
- `file` => rely on `tail`
- `both` => use both
- `none` => no script decision logs are expected
### Check state actually progresses
Look at these fields in `totmann.json`:
- `next_check_at`, `deadline_at` (window boundaries)
- `next_reminder_at` (should advance by `remind_every_seconds` after each reminder send)
- `escalated_sent_at` (set when escalation mail was sent)
- `escalate_ack_next_at` / `escalate_ack_sent_count` (only after escalation, if ACK enabled)

If these timing fields are missing or inconsistent (`cycle_start_at`, `next_check_at`, `deadline_at`), the tick now performs a conservative state recovery (new cycle, no immediate escalation). Check logs for `State sanity recovery triggered…`.
```sh
cat /var/lib/totmann/totmann.json
```
If `next_reminder_at` doesn’t move forward, the tick likely failed before saving state → check logs for an exception.

After escalation ACK reminders hit the configured maximum, one final "limit reached" marker is expected, then recurring escalation status lines stop by design. This keeps logs focused on actionable events.
## Factory reset/Re-arm
If you want to restart completely:
```sh
sudo systemctl stop totmann.timer
sudo rm -f /var/lib/totmann/totmann.json /var/lib/totmann/totmann.lock /var/lib/totmann/totmann.log
sudo sh -c 'umask 0007; /usr/bin/php /var/lib/totmann/totmann-tick.php tick'
sudo systemctl start totmann.timer
```
The `rm` command uses the filenames from the template config; adapt it if you changed them in `totmann.inc.php`.
> **Why `umask 0007` matters**
> `umask 0007` makes newly created files **group-writable** but **not world-accessible**. In practice, files created by the tick (running as `root`) become `0660` and directories become `0770`. Combined with the setgid dir bit (2770), new files stay in `<WEB_GROUP>`, so your configured web endpoint (`web_file`) can write the configured state/lock files safely.
