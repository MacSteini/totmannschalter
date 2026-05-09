# totman – `systemd` unit & timer
![totman](../img/totman-icon.png)

You can create the unit and timer in one of two ways:
- **Option A (recommended):** via terminal (copy/paste, deterministic)
- **Option B:** manually with an editor (`nano`, `vi`, etc.)
## Option A (recommended): create files via terminal
This avoids editor mistakes and creates the files exactly as shown.
### Create the unit manually
Replace `<WEB_GROUP>`:
```sh
sudo tee /etc/systemd/system/totman.service >/dev/null <<'EOF'
[Unit]
Description=totman

[Service]
ExecStart=/usr/bin/php /var/lib/totman/totman-tick.php tick
User=root
Group=<WEB_GROUP>
Type=oneshot
WorkingDirectory=/var/lib/totman
Environment=TOTMAN_STATE_DIR=/var/lib/totman
UMask=0007

ProtectSystem=strict
ReadWritePaths=/var/lib/totman
EOF
```
> **Critical**: `UMask=0007` ensures runtime files created by root become group-writable (`0660`).
### Create the timer manually
```sh
sudo tee /etc/systemd/system/totman.timer >/dev/null <<'EOF'
[Unit]
Description=Run totman every minute

[Timer]
OnBootSec=30s
OnUnitActiveSec=60s
AccuracySec=5s
Persistent=true

[Install]
WantedBy=timers.target
EOF
```
## Option B: create files with an editor
If you prefer doing it manually, create the files below and paste the contents exactly as shown.
### Create the unit
Replace `<WEB_GROUP>`:
```sh
sudo nano /etc/systemd/system/totman.service
```
Paste:
```text
[Unit]
Description=totman

[Service]
ExecStart=/usr/bin/php /var/lib/totman/totman-tick.php tick
User=root
Group=<WEB_GROUP>
Type=oneshot
WorkingDirectory=/var/lib/totman
Environment=TOTMAN_STATE_DIR=/var/lib/totman
UMask=0007

ProtectSystem=strict
ReadWritePaths=/var/lib/totman
```
> **Critical**: `UMask=0007` ensures runtime files created by root become group-writable (`0660`).
### Create the timer
```sh
sudo nano /etc/systemd/system/totman.timer
```
Paste:
```text
[Unit]
Description=Run totman every minute

[Timer]
OnBootSec=30s
OnUnitActiveSec=60s
AccuracySec=5s
Persistent=true

[Install]
WantedBy=timers.target
```
## Enable and run once
```sh
sudo systemctl daemon-reload
sudo systemctl enable --now totman.timer

# Run once now
sudo systemctl start totman.service
```
## Operational checks
Timer status:
```sh
systemctl list-timers | grep totman
```
Inspect the effective unit config:
```sh
sudo systemctl cat totman.service
```
Confirm that the `totman.service` environment points to the same state directory you configured (and that `UMask` is set to `0007`, so runtime files are created group-writable):
```sh
sudo systemctl show totman.service -p Environment -p WorkingDirectory -p ExecStart -p UMask
```
## Logs
```sh
journalctl -u totman.service -n 200 --no-pager
tail -n 50 /var/lib/totman/totman.log
```
Use according to `log_mode`:
- `syslog` => `journalctl` only
- `file` => `tail` only
- `both` => both commands
- `none` => only systemd unit status/events are visible (no script log lines)
If you changed `log_file_name` or `log_file`, use that effective file log path for `tail`.
If you are unsure how to read the script log lines, use [Log guide](Logs.md "Log guide").
If `journalctl` shows a bootstrap problem such as `CONFIG ERROR: ...`, treat that as a journal-only failure first and then compare it with the same guides.

The same `/var/lib/totman` state directory should also contain your configured `l18n/` directory, because the web endpoint loads its public page texts from there.

Live stream (useful during test runs):
```sh
tail -f /var/lib/totman/totman.log
```
