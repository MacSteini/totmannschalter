# totmannschalter – Web endpoint configuration
## One endpoint for everything
`totmann.php` is now the only public endpoint.

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
1. ENV `TOTMANN_STATE_DIR`
2. `define('TOTMANN_STATE_DIR', '/var/lib/totmann');`

In this repository version, `totmann.php` enables the `define(...)` fallback by default. Adjust it to your actual state dir if needed.

If neither exists, the endpoint returns a neutral page. The endpoint intentionally has no implicit fallback to a local webroot or to `state_dir` from `totmann.inc.php`.
## Website language (`l18n/` + `Accept-Language`)
`totmann.php` loads website text from your configured `l18n_dir_name` directory (template default: `l18n/` inside `state_dir`).

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
`totmann.php` can link the configured `web_css_file` from the same webroot folder as `web_file`.

- If the stylesheet exists, the endpoint renders styled pages.
- If it is missing, or `web_css_file` is empty, pages remain functional but unstyled.
## Stealth behaviour
The endpoint is intentionally stealthy:
- invalid or missing tokens get the neutral page
- stale or non-current tokens get the neutral page, depending on your stealth config
- most internal web-side failures return the neutral page as well

Exception:
- if the request uses the current valid token, you may see a generic error page with an error code for troubleshooting

Ensure:
- `display_errors=off` for the web endpoint
## Confirmation flow (GET then POST)
Confirmation links are deliberately two-step to defeat mail link scanners:
- `GET` shows a page with a Confirm button
- only the `POST` resets the cycle

ACK success pages follow the same locale selection as confirm pages.

- The ACK success page always confirms that the message was marked as received.
- It only shows the extra download reminder if that specific recipient’s escalation mail actually included at least one download link.
## Shared runtime state
The runtime now uses only one state file: `state_file` (template default: `totmann.json`).

Internally it contains separate state areas for:
- normal cycle/runtime behaviour
- download tracking

Operationally, you only need to care about one state file.
## Rate limiting (one root, two namespaces)
Rate limiting reduces abuse without breaking functionality.

The runtime now uses only one top-level rate-limit root:
- `rate_limit_dir`
- if `null`, the runtime uses `{state_dir}/ratelimit`

Internally the runtime separates:
- normal web requests
- download requests

You keep one directory on disk, but confirm/ACK traffic and download traffic do not overwrite each other’s counters.

Fail-open behaviour remains intentional:
- if the rate-limit directory is missing, unwritable, or locking fails, the request is still allowed
## Download action
Downloads are served through the `download` action of `totmann.php`.

Rules:
- keep `download_base_dir` outside your webroot
- keep the paths in `$files` inside `totmann-recipients.php` relative to `download_base_dir`
- the runtime signs each link for one recipient and one configured download entry
- if a file is defined for two recipients, the runtime still generates separate signed URLs per recipient
- `{DOWNLOAD_LINKS}` expands to raw URLs only
- `{DOWNLOAD_NOTICE}` is the dedicated mail placeholder for the single-use warning text
- `single_use=true` applies to the whole escalation event for that recipient and link
- ACK reminder mails do not create a fresh single-use allowance
- download expiry is measured from the first escalation mail of that escalation event via the global `download_valid_days` setting
- already issued valid download links still resolve even if an unrelated message or recipient row later breaks in `totmann-recipients.php`
## Proxy trust and client IP handling
This is not just a comfort feature. It is security-relevant.

Default:
- `ip_mode = remote_addr`

Why this is safest:
- the runtime uses the direct TCP peer IP from `REMOTE_ADDR`
- clients cannot spoof this through request headers

Optional proxy mode:
- `ip_mode = trusted_proxy`
- only use this if totmann runs behind a reverse proxy that you control
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
ls -la /var/lib/totmann
```

Web identity can read config and write runtime files, replace `<WEB_USER>`:
```sh
sudo -u <WEB_USER> php -r 'echo is_readable("/var/lib/totmann/totmann.inc.php") ? "config:OK\n" : "config:NO\n";'
sudo -u <WEB_USER> php -r '$f="/var/lib/totmann/.permtest"; echo (file_put_contents($f,"x")!==false)?"write:OK\n":"write:NO\n"; @unlink($f);'
```
