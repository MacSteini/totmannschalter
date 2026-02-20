# totmannschalter – Web endpoint
## State dir resolution
The web endpoint file (`web_file`, template default `totmann.php`) resolves the state directory in this order:
1. ENV `TOTMANN_STATE_DIR`
2. `define('TOTMANN_STATE_DIR', '/var/lib/totmann');` (fallback)

`base_url` must point to your public base path (without endpoint filename). The runtime appends `web_file` automatically to build confirm/ACK links.

In this repository version, the `define(...)` fallback is enabled in `totmann.php` by default. Adjust it to your actual state dir if needed.

If neither exists, the endpoint returns a neutral page (the generic “Request received” response). There is intentionally no silent fallback to a local webroot state/config path.
## Optional stylesheet (`web_css_file`)
`totmann.php` links the configured `web_css_file` from the same webroot folder as `web_file` (template default: `totmann.css`).

- If the configured stylesheet exists, pages are rendered with the centered responsive layout.
- If the file is missing (or `web_css_file` is empty), pages remain fully functional but unstyled.

For strict CSP setups, this is usually easier than inline styles because `style-src 'self'` is sufficient.
## Stealth behaviour
The endpoint is intentionally stealthy:
- Invalid/missing tokens are answered with the neutral page (depending on config).
- Stale/non-current tokens are answered with the neutral page (depending on config).
- If the endpoint cannot read/write the state directory (or cannot acquire the configured lock file), it returns the neutral page.
	Exception: if the request uses the current valid token, you may see a generic error page with an error code (for troubleshooting).

Ensure:
- `display_errors=off` for the web endpoint (errors go to server logs, not HTTP responses).
## Confirmation flow (GET then POST)
Confirmation links are deliberately two-step to defeat mail link scanners:
- `GET` shows a page with a Confirm button.
- Only the `POST` actually resets the cycle.
## Rate limiting (fail-open)
Rate limiting reduces abuse without breaking functionality:
- If the ratelimit dir is missing/unwritable/locking fails => allow request (fail-open).
- This preserves stealth and avoids accidental lockouts.
## Quick permission sanity checks
Expected paths exist:
```sh
ls -la /var/lib/totmann
```
Web identity can read config and write runtime files (replace `<WEB_USER>`):
```sh
sudo -u <WEB_USER> php -r 'echo is_readable("/var/lib/totmann/totmann.inc.php") ? "config:OK\n" : "config:NO\n";'
sudo -u <WEB_USER> php -r '$f="/var/lib/totmann/.permtest"; echo (file_put_contents($f,"x")!==false)?"write:OK\n":"write:NO\n"; @unlink($f);'
```
