# totmannschalter – Installation
## Prerequisites
- PHP 8.0+ (required).
- `systemd` (service + timer).
- `sendmail`-compatible MTA (e. g., Postfix/Exim) reachable via `sendmail_path`.
## Install order (recommended)
1. Identify `<WEB_USER>:<WEB_GROUP>` first.
2. Create state directory and place files.
3. Set minimum `totmann.inc.php` values.
4. Fix ownership/permissions.
5. Run preflight checks.
6. Clean initialise once.
7. Install and enable `systemd` timer.
8. Run smoke test with short timings.
## Layout (recommended)
Recommended base directory (not under `/home`): `/var/lib/totmann`

In `/var/lib/totmann`:
- `totmann.inc.php`
- your configured `lib_file` (template default: `totmann-lib.php`)
- `totmann-tick.php`

Runtime files created automatically (as needed) in `/var/lib/totmann`:
- `totmann.json`
- `totmann.lock`
- `ratelimit/`
- `totmann.log`
These names are configurable in `totmann.inc.php` via `state_file`, `lock_file`, `log_file_name`.

In your webroot:
- your configured `web_file` (template default: `totmann.php`)
- optional stylesheet for web pages: your configured `web_css_file` (template default: `totmann.css`)
## Before you start: identify the real web identity
**This is the most important step**: you must find the actual user and group that execute your configured web endpoint file (`web_file`). On Debian/Ubuntu with PHP-FPM, the pool configuration is usually the source of truth.

List configured pool users/groups:
```sh
sudo grep -R --line-number "^\s*user\s*=" /etc/php/*/fpm/pool.d
sudo grep -R --line-number "^\s*group\s*=" /etc/php/*/fpm/pool.d
```
If you have multiple pools, determine which pool serves your site (vhost). Use one of these methods if you are unsure:

`systemd` services:
```sh
systemctl list-units --type=service | egrep -i 'php.*fpm|apache2|nginx'
systemctl status php*-fpm --no-pager
systemctl status nginx apache2 --no-pager
```
Look at listening sockets:
```sh
sudo ss -lptn | egrep -i ':(80|443)\b|php-fpm|nginx|apache'
sudo ss -lx | egrep -i 'php.*fpm|fpm\.sock'
```
nginx and php-fpm sockets: find the pool socket used by your vhost:
```sh
sudo grep -R --line-number "fastcgi_pass" /etc/nginx | head -n 50
```
Pick ONE “web identity” that actually executes your configured web endpoint file:
- Example 1: `www-data:www-data`
- Example 2: `usera:usera`
- Example 3: `nginx:nginx`

From here on:
- `<WEB_USER>` = your PHP runtime user
- `<WEB_GROUP>` = your PHP runtime group
## Create the state directory
```sh
sudo mkdir -p /var/lib/totmann
```
## Place the files
- Copy `totmann.inc.php`, your configured `lib_file`, and `totmann-tick.php` to `/var/lib/totmann`:
```sh
sudo cp totmann.inc.php totmann-tick.php totmann-lib.php /var/lib/totmann/
```
- Place your configured `web_file` into your webroot (e. g., `/var/www/html/totmann/totmann.php`):
```sh
sudo cp totmann.php /var/www/html/totmann/totmann.php
```
- Optional but recommended: copy the stylesheet into the same webroot folder:
```sh
sudo cp totmann.css /var/www/html/totmann/totmann.css
```
If you changed `lib_file`, `web_file`, or `web_css_file` from the template names, adjust these copy/rename commands accordingly.
- Ensure your PHP runtime sets ENV `TOTMANN_STATE_DIR=/var/lib/totmann` (see [web endpoint](Web.md "Web endpoint")).
## Update `totmann.inc.php` (minimum required)
- `state_dir` should match the directory where you placed `totmann.inc.php` (recommended: `/var/lib/totmann`)
- Runtime filenames (filenames only): `lib_file`, `web_file`, `state_file`, `lock_file`, `log_file_name`
- Optional web stylesheet filename in webroot: `web_css_file` (empty disables link)
- `base_url` must point to your real public HTTPS base URL (without endpoint filename); `web_file` is appended automatically
- `log_mode` should be set explicitly: `none`, `syslog`, `file`, `both` (recommended: `both`)
- `hmac_secret_hex` (e. g., `openssl rand -hex 32`)
- `to_self` and `to_recipients` (for testing: only your own addresses)
## Preflight check (recommended before enabling timer)
Run the built-in preflight in your deployed state dir:
```sh
cd /var/lib/totmann
php totmann-tick.php check
php totmann-tick.php check --web-user=<WEB_USER>
echo $?
```
Exit codes:
- `0` => ready
- `1` => warnings (review before go-live)
- `2` => hard failures (do not go live yet)

`--web-user` is optional but recommended. It validates (read-only) whether the actual PHP runtime user can likely read config and create/update lock/state files based on POSIX mode bits.

Timing/interval config is also validated in preflight and at runtime:
- required integer values must be numeric and within valid minimum bounds
- suspicious but allowed relations are emitted as warnings (e. g., `confirm_window_seconds > check_interval_seconds`)

> Note:
> The repository `totmann.inc.php` is a template by design.
> The preflight should be run against the deployed config in your real state dir.

## Changing config without restarting systemd
For `totmann.inc.php` changes (for example timing/interval values), you usually do not need to restart `totmann.timer` or `totmann.service`.
The runtime reads config on each tick, so updates are picked up automatically.

Only changes to unit files in `/etc/systemd/system/*.service` or `*.timer` require:
```sh
sudo systemctl daemon-reload
sudo systemctl restart totmann.timer
```

## Permissions (critical)
Do NOT use `root:root`. Use `root:<WEB_GROUP>` so the web identity can access secrets and runtime files.

Owner: `root`, Group: `<WEB_GROUP>`:
```sh
sudo chown -R root:<WEB_GROUP> /var/lib/totmann
```
> Ensure `<WEB_USER>` is in `<WEB_GROUP>` (or that both are the same identity).

Directories: setgid so new files stay in group `<WEB_GROUP>`; group-write enabled:
```sh
sudo find /var/lib/totmann -type d -exec chmod 2770 {} \;
```
Files: readable+writable by group `<WEB_GROUP>`; not world-readable:
```sh
sudo find /var/lib/totmann -type f -exec chmod 0660 {} \;
```
> Why setgid matters: files created later by `root` will still land in group `<WEB_GROUP>`.
> Why `0660` matters: the web identity must be able to write your configured state file.

## Clean initialise (ensures correct runtime perms)
Delete any old runtime files, then initialise once.
```sh
sudo rm -f /var/lib/totmann/totmann.json /var/lib/totmann/totmann.lock /var/lib/totmann/totmann.log

# IMPORTANT:
# Initialise with umask 0007 so files become 0660 (group-writable).
sudo sh -c 'umask 0007; /usr/bin/php /var/lib/totmann/totmann-tick.php tick'
```
The `rm`/`ls` examples use the filenames from the template config; if you changed them in `totmann.inc.php`, adapt these commands.

Verify:
```sh
ls -la /var/lib/totmann/totmann.json /var/lib/totmann/totmann.lock /var/lib/totmann/totmann.log
```
## Smoke test (use only your own addresses)
1. In `totmann.inc.php`, temporarily set short timings (minutes). [See timing](Timing.md "Timing").
2. Ensure `totmann.timer` is active and wait for the reminder email.
3. Open the link (`GET`): you should see a confirm button.
4. Click Confirm (`POST`): page shows `Confirmed!`
5. With random/invalid/stale: neutral page is shown

For live debugging during the smoke test, keep this running in a second shell:
```sh
tail -f /var/lib/totmann/totmann.log
```
If you changed `log_file_name` or `log_file`, tail that effective path instead. If `log_mode` is `syslog` or `none`, use:
```sh
journalctl -u totmann.service -f
```
## Next step
After smoke test passes:
1. Switch from test preset to production preset in [Timing](Timing.md "Timing model and presets").
2. Re-run `php totmann-tick.php check` and keep `totmann.timer` enabled.
