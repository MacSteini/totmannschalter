# totmannschalter – `systemd` unit & timer
You can create the unit and timer either:
- **Option A (recommended):** via terminal (copy/paste, deterministic)
- **Option B:** manually with an editor (`nano`, `vi`, etc.)
## Option A (recommended): create files via terminal
This avoids editor mistakes and creates the files exactly as shown.
### Create the unit manually
Replace `<WEB_GROUP>`:
```sh
sudo tee /etc/systemd/system/totmann.service >/dev/null <<'EOF'
[Unit]
Description=totmannschalter

[Service]
ExecStart=/usr/bin/php /var/lib/totmann/totmann-tick.php tick
User=root
Group=<WEB_GROUP>
Type=oneshot
WorkingDirectory=/var/lib/totmann
Environment=TOTMANN_STATE_DIR=/var/lib/totmann

# Critical:
# UMask=0007 ensures runtime files created by root become group-writable (0660).
UMask=0007

ReadWritePaths=/var/lib/totmann
EOF
```
### Create the timer manually
```sh
sudo tee /etc/systemd/system/totmann.timer >/dev/null <<'EOF'
[Unit]
Description=Run totmannschalter every minute

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
If you prefer doing it manually, create the files below and paste the contents.
### Create the unit
Replace `<WEB_GROUP>`:
```sh
sudo nano /etc/systemd/system/totmann.service
```
Paste:
```text
[Unit]
Description=totmannschalter

[Service]
ExecStart=/usr/bin/php /var/lib/totmann/totmann-tick.php tick
User=root
Group=<WEB_GROUP>
Type=oneshot
WorkingDirectory=/var/lib/totmann
Environment=TOTMANN_STATE_DIR=/var/lib/totmann

# Critical:
# UMask=0007 ensures runtime files created by root become group-writable (0660).
UMask=0007

ReadWritePaths=/var/lib/totmann
```
### Create the timer
```sh
sudo nano /etc/systemd/system/totmann.timer
```
Paste:
```text
[Unit]
Description=Run totmannschalter every minute

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
sudo systemctl enable --now totmann.timer

# Run once now
sudo systemctl start totmann.service
```
## Operational checks
Timer status:
```sh
systemctl list-timers | grep totmann
```
Inspect the effective unit config:
```sh
sudo systemctl cat totmann.service
```
Confirm that the `totmann.service` environment points to the same state directory you configured (and that `UMask` is set to `0007`, so runtime files are created group-writable):
```sh
sudo systemctl show totmann.service -p Environment -p WorkingDirectory -p ExecStart -p UMask
```
## Logs
```sh
journalctl -u totmann.service -n 200 --no-pager
tail -n 50 /var/lib/totmann/totmann.log
```
Use according to `log_mode`:
- `syslog` => `journalctl` only
- `file` => `tail` only
- `both` => both commands
- `none` => only systemd unit status/events are visible (no script log lines)
If you changed `log_file_name` or `log_file`, use that effective file log path for `tail`.

Live stream (useful during test runs):
```sh
tail -f /var/lib/totmann/totmann.log
```
