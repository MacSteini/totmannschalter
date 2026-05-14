# totman – Web endpoint configuration
![totman](../img/totman-icon.png)

## One endpoint for everything
`totman.php` is the only public endpoint.

It handles three actions:
- `a=confirm`
- `a=ack`
- `a=download`

This means:
- confirm links, ACK links, and download links all point to the same endpoint
- `base_url` must point to the public base path without the endpoint filename
- the runtime appends `web_file` automatically
## State dir resolution
The web endpoint resolves the state directory in this order:
1. ENV `TOTMAN_STATE_DIR`
2. `define('TOTMAN_STATE_DIR', '/var/lib/totman');`

The shipped `totman.php` template enables the `define(...)` fallback by default. Adjust it to your actual state dir if needed.

If neither exists, the endpoint returns a neutral page. The endpoint intentionally has no implicit fallback to a local webroot or to `state_dir` from the main config.

The endpoint recognises exactly `totman.inc.php` and `totman.inc.dist.php` as main-config filenames. It prefers values from `totman.inc.php` when both files exist, and it may use `totman.inc.dist.php` as the complete effective config when you intentionally maintain that file. If the effective config or configured recipient file is missing, incomplete, or still contains template recipients, the endpoint stays neutral.
## Website language (`l18n/` + `Accept-Language`)
`totman.php` loads website text from your configured `l18n_dir_name` directory (template default: `l18n/` inside `state_dir`).

Language selection order:
1. exact browser-language match from `Accept-Language` (e. g., `de-DE`)
2. closest supported locale for the base language (e. g., `de` => `de-DE`)
3. fallback to `en-US`

Supported starter locales:
- `de-DE`
- `en-GB`
- `en-US`
- `fr-FR`
- `it-IT`
- `es-ES`

Important:
- the website language follows the browser
- timestamps still use your configured `mail_timezone`
- if `l18n/` is missing or incomplete, preflight reports it
- if runtime still cannot load a locale file, the endpoint falls back to built-in `en-US`
- public page text is intentionally calm and human, but still generic enough not to reveal unnecessary link context
## Optional stylesheet (`web_css_file`)
`totman.php` can link the configured `web_css_file` from the same webroot folder as `web_file`.

- If the stylesheet exists, the endpoint renders styled pages.
- If it is missing, or `web_css_file` is empty, pages remain functional but unstyled.

## Optional Web UI add-on (`totman-ui.php`)
`totman-ui.php` provides a separate optional administration interface. The normal runtime never uses it for confirmation, ACK, download delivery, reminders, escalation, or `systemd` ticks.

The default config keeps browser administration off after setup. Existing manual operation remains fully supported. First-run setup can still import templates or live config and write runtime files with the server-side setup code; after that, administration requires the effective main config to contain:
```php
'web_ui_enabled' => true,
```

Use it only when you intentionally want browser-based administration:
- deploy `totman-ui.php` into an HTTPS webroot
- ensure it resolves the same private state directory as `totman.php`, preferably with `TOTMAN_STATE_DIR`
- set the setup code near the top of `totman-ui.php` before first setup; Docker and managed hosting can instead set `TOTMAN_UI_SETUP_CODE` server-side
- keep the generated `.totman-ui.php`, `.totman-ui-backups/`, `state_dir`, logs, state files, and downloads outside public web access
- keep HTTPS enabled; if PHP runs behind a TLS-terminating proxy, configure the PHP runtime so session cookies are still treated as secure

The Web UI writes the same runtime files that manual operation uses: `totman.inc.php` and the configured recipient file. Drafts stay in UI/session state until you explicitly write runtime files. A save from the UI may change formatting and remove template comments, because it writes a generated, stable PHP-array layout. The content remains runtime-compatible and grouped in the same broad order as the `.dist.php` templates.

The private `.totman-ui.php` file is UI-only state in the resolved state directory. It stores admin metadata such as password hashes and timestamps; it is not part of confirmation, ACK, download, mail, or tick processing.

The administration area exposes read-only runtime summary, bounded log tail, and file-alias inventory. Maintenance actions such as HMAC rotation, runtime-state reset, safe log clear, and file-alias deletion require admin login, CSRF protection, recent reauthentication, rate-limit allowance, and explicit confirmation.

To stop using it, set `web_ui_enabled` back to `false` or remove `totman-ui.php` from the webroot. The normal runtime continues to work from the same config files.

## Product logo
Runtime web pages render the product logo from this GitHub-hosted image URL:
```text
https://raw.githubusercontent.com/MacSteini/totmannschalter/refs/heads/main/img/totman-s.png
```

This is a deliberate visual dependency. It does not affect confirmation, ACK, download, mail, state, or escalation logic.

If a browser or network policy blocks that image, the page content and buttons remain usable.
## Stealth behaviour
The endpoint is intentionally stealthy:
- invalid or missing tokens get the neutral page
- stale or non-current tokens get the neutral page, depending on your stealth config
- most website-side runtime failures return the neutral page as well

Exception:
- if the request uses the current valid token, you may see a generic error page with an error code for troubleshooting

Ensure:
- `display_errors=off` for the web endpoint
## Confirmation and ACK flows (GET then POST)
Confirmation links are deliberately two-step to defeat mail link scanners:
- `GET` shows a page with a Confirm button
- only the `POST` resets the cycle

ACK links use the same pattern:
- `GET` shows a page with an acknowledgement button
- only the `POST` marks the escalation mail as received and stops ACK reminders for that escalation event

ACK pages follow the same locale selection as confirm pages.

- The ACK success page always confirms that the message was marked as received.
- Once any recipient acknowledges, no further escalation mails are sent for that escalation event.
- It only shows the extra download reminder if that specific recipient’s escalation mail actually included at least one download link.
## Shared runtime state
The runtime uses one state file: `state_file` (template default: `totman.json`).

Internally it contains separate state areas for:
- normal cycle/runtime behaviour
- download tracking

Operationally, you only need to care about one state file.
## Rate limiting (one root, two namespaces)
Rate limiting reduces abuse without breaking functionality.

The runtime uses one top-level rate-limit root:
- `rate_limit_dir`
- if `null`, the runtime uses `{state_dir}/ratelimit`

Internally the runtime separates:
- normal web requests
- download requests

You keep one directory on disk, but confirm/ACK traffic and download traffic do not overwrite each other’s counters.

Fail-open behaviour remains intentional:
- if the rate-limit directory is missing, unwritable, or locking fails, the request is still allowed
## Download action
Downloads are served through the `download` action of `totman.php`.

Rules:
- keep `download_base_dir` outside your webroot
- keep the paths in `$files` inside the configured `recipients_file` relative to `download_base_dir`
- the runtime signs each link for one recipient, one configured download entry, one escalation event, and the current relative file path
- if a file is defined for two recipients, the runtime still generates separate signed URLs per recipient
- if the same alias is later changed to another relative file path, already issued links for the old path fail closed instead of serving the new file
- `{DOWNLOAD_LINKS}` expands to the complete download block for that mail
- every download block starts with `1 Download:` or `X Downloads:`
- if a mail contains several downloads, the runtime leaves a blank line between download blocks automatically
- if a download is single-use, the matching message entry supplies the warning text through `single_use_notice`
- field 4 in the configured `recipients_file` creates normal links
- field 5 in the configured `recipients_file` creates single-use links
- single-use applies to the whole escalation event for that recipient and link
- ACK reminder mails do not create a fresh single-use allowance
- download expiry is measured from the first escalation mail of that escalation event via the global `download_valid_days` setting
- already issued valid download links still resolve if an unrelated message or recipient row later breaks in the configured `recipients_file`
## Proxy trust and client IP handling
This is not just a comfort feature. It is security-relevant.

Default:
- `ip_mode = remote_addr`

Why this is safest:
- the runtime uses the direct TCP peer IP from `REMOTE_ADDR`
- clients cannot spoof this through request headers

Optional proxy mode:
- `ip_mode = trusted_proxy`
- only use this if totman runs behind a reverse proxy that you control
- only proxy IPs listed in `trusted_proxies` are allowed to supply the client IP header
- the runtime then reads the header from `trusted_proxy_header` (default: `X-Forwarded-For`)

Risk if misconfigured:
- if you trust `X-Forwarded-For` from untrusted sources, clients can spoof their IP
- that affects logging and rate limiting

Practical rule:
- if you are unsure, keep `ip_mode = remote_addr`

This trust model affects:
- request logging
- rate limiting
- auditability of suspicious requests
## Quick permission sanity checks
Expected paths exist:
```sh
ls -la /var/lib/totman
```
Web identity can read the effective config and write runtime files, replace `<WEB_USER>`:
```sh
sudo -u <WEB_USER> php -r 'echo is_readable("/var/lib/totman/totman.inc.php") ? "config:OK\n" : "config:NO\n";'
sudo -u <WEB_USER> php -r '$f="/var/lib/totman/.permtest"; echo (file_put_contents($f,"x")!==false)?"write:OK\n":"write:NO\n"; @unlink($f);'
```
