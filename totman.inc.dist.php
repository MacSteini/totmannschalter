<?php

/**
 * totman – configuration defaults/template
 *
 * Project: https://github.com/macsteini/totmannschalter
 * Licence: MIT (see LICENCE)
 *
 * You may either copy this file to totman.inc.php or intentionally keep this
 * exact .dist filename as your effective main config.
 * Template defaults cover runtime filenames, timing, mail, logging,
 * web behaviour, and optional download links.
 */

declare(strict_types=1);

return [
// Public base URL (must be HTTPS, WITHOUT endpoint filename).
// Runtime links are built as: <base_url>/<web_file>?a=confirm|ack|download&...
// Example: https://example.com/totman
'base_url' => '',

// State directory on disk (holds totman.inc.php and/or totman.inc.dist.php, totman-tick.php,
// your configured lib_file, your configured recipients_file, and runtime files).
// NOTE: Entry points do NOT use this value to locate the directory. They resolve it via:
// - totman-tick.php: ENV TOTMAN_STATE_DIR (or __DIR__)
// - totman.php: ENV TOTMAN_STATE_DIR (or define('TOTMAN_STATE_DIR', ...))
// Keep this value aligned with TOTMAN_STATE_DIR purely for clarity.
// Example: /var/lib/totman
'state_dir' => '',

// Runtime file names (filenames only, no paths).
// Files loaded from state_dir: lib_file, recipients_file, state_file, lock_file, log_file_name
// Directory loaded from state_dir: l18n_dir_name
// Files deployed in webroot: web_file, optional web_css_file
// These keys are required by the runtime unless explicitly documented as optional;
// invalid/missing values fail bootstrap.
'lib_file' => 'totman-lib.php',
// Directory inside state_dir that contains the web-language files.
// The web endpoint picks the best supported locale from the browser's Accept-Language
// header, falls back to en-US, and still renders all timestamps in mail_timezone.
'l18n_dir_name' => 'l18n',
'lock_file' => 'totman.lock',
'log_file_name' => 'totman.log',
'recipients_file' => 'totman-recipients.php',
'state_file' => 'totman.json',
'web_file' => 'totman.php',

// Optional stylesheet filename in the webroot (same folder as web_file).
// Empty string disables stylesheet linking from the web endpoint.
'web_css_file' => 'totman.css',

// Private directory for downloadable files.
// Files served through the `download` action must live inside this directory.
// Keep this OUTSIDE your webroot.
// File aliases in totman-recipients.php are relative to this directory.
// Example: alias `letter` with path `shared/letter.pdf` resolves to:
// /var/lib/totman/files/shared/letter.pdf
'download_base_dir' => '/var/lib/totman/files',

// Validity period for every download link (days).
// This timer starts at the first escalation mail of that escalation event and then applies
// to all later reminder URLs for the same recipient/file pair as well.
// The setting is global on purpose, so the configured recipient file stays simple.
'download_valid_days' => 180,

// Download action rate limiting.
// Confirm/ACK and download requests share one top-level ratelimit directory, but use
// separate namespaces automatically.
'download_rate_limit_enabled' => true,
'download_rate_limit_max_requests' => 20,
'download_rate_limit_window_seconds' => 60,

// One-time download lease (seconds).
// A valid download token is temporarily reserved while one request is in progress.
// If the transfer does not complete, the lease expires and the token can be retried.
'download_lease_seconds' => 300,

// Shared secret for HMAC signing/verification.
// - Must be hex-encoded bytes.
// - Minimum: 16 bytes (32 hex chars). Recommended: 32 bytes (64 hex chars).
// Generate (32 bytes => 64 hex chars):
// Linux/macOS (OpenSSL): openssl rand -hex 32
// Linux/macOS (no OpenSSL): head -c 32 /dev/urandom | xxd -p -c 256
// Windows (PowerShell): $b=New-Object byte[] 32;[Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b);-join($b|%{$_.ToString('x2')})
'hmac_secret_hex' => '',

// --- Cycle timing model (how the clock works) ---
//
// Each cycle is anchored at cycle_start_at (set on init and on successful confirm).
//
// Definitions per cycle:
// - next_check_at = cycle_start_at + check_interval_seconds
// - deadline_at = next_check_at + confirm_window_seconds
// - escalation may only fire after: deadline_at + escalate_grace_seconds
//
// Behaviour:
// 1) Before next_check_at: nothing happens.
// 2) From next_check_at (inclusive) until deadline_at (exclusive):
// - reminder emails are sent every remind_every_seconds (to_self).
// 3) At/after (deadline_at + grace):
// - if you did NOT confirm during this cycle, the cycle counts as "missed".
// - only after missed_cycles_before_fire missed cycles: escalation triggers.
// - escalation email goes to recipients from the configured recipients_file.
//
// NOTE: The systemd timer can tick every minute without changing these time windows.
// It just checks whether a boundary has been reached.

// Length of a cycle (when the next confirmation window starts).
'check_interval_seconds' => 60 * 60 * 24 * 1, // daily

// Length of the confirmation window once it opens (time you have to click confirm).
'confirm_window_seconds' => 60 * 60 * 24 * 2, // 2 days

// Reminder frequency while the confirmation window is open.
'remind_every_seconds' => 60 * 60 * 12, // every 12 hours

// Additional grace period after the deadline before escalation is even considered.
'escalate_grace_seconds' => 60 * 60 * 4, // 4 hours

// Extra safety: require N missed cycles before escalation actually triggers.
// Note: if a cycle is missed and the threshold is not yet met, the tick starts a new cycle immediately (after deadline+grace) and carries missed_cycles forward.
'missed_cycles_before_fire' => 2,

// --- Recipient receipt acknowledgement (ACK) ---
//
// If enabled, escalation emails can include an ACK link via {ACK_BLOCK} or {ACK_URL}
// inside the message bodies in the configured recipient file.
// Once ANY recipient opens the ACK page and submits the acknowledgement, the web endpoint
// records escalate_ack_at and stops all further escalation mails for that escalation event.
//
// ACK reminders:
// - after escalation was sent, if not yet acknowledged, the same escalation email is re-sent
// up to escalate_ack_max_reminds times, spaced by escalate_ack_remind_every_seconds.
// - escalate_ack_max_reminds counts only reminder re-sends (not the initial escalation mail).
//
// Note: ACK links are built from base_url + web_file (same endpoint as confirm and download links).
'escalate_ack_enabled' => true,
'escalate_ack_remind_every_seconds' => 60 * 60 * 12, // e. g., 12h between ACK reminder mails
'escalate_ack_max_reminds' => 25, // safety cap for ACK reminder sends

// --- Stealth behaviour (web endpoint responses) ---
//
// If enabled, the web endpoint returns a neutral "request received" page for:
// - invalid/missing token
// - stale/non-current token (i. e., token that was validly signed but is not the current one in state)
//
// This avoids leaking whether a token exists / whether the system is active.
'stealth_neutral_for_invalid' => true, // invalid/missing token => neutral
'stealth_level_2_neutral_on_stale' => true, // stale/non-current token => neutral

// If true, confirm success page shows "Next check at ..." (helpful for self-testing).
'show_success_details' => true,

// Optional Web UI administration add-on.
// Keep disabled unless you intentionally deploy totman-ui.php, protect it with HTTPS,
// configure a setup code in totman-ui.php or server-side, and want browser-based administration.
// The normal runtime does not need this key to be true.
'web_ui_enabled' => false,

// --- Rate limiting (web endpoint) ---
//
// Per-IP basic rate limit. Fail-open by design:
// If the ratelimit dir is missing/unwritable, requests are not blocked (stealth is preserved).
'rate_limit_enabled' => true,
// null => fallback: {state_dir}/ratelimit
'rate_limit_dir' => null,
'rate_limit_max_requests' => 30,
'rate_limit_window_seconds' => 60,

// --- IP handling for rate limiting and logging ---
//
// `remote_addr` = safest default. Use this unless you really run totman behind your own reverse proxy.
// `trusted_proxy` = trust `trusted_proxy_header` only when REMOTE_ADDR belongs to one of `trusted_proxies`.
// If you trust a header from an untrusted source, clients could spoof their IP for logging/rate limiting.
'ip_mode' => 'remote_addr', // 'remote_addr' | 'trusted_proxy'
'trusted_proxies' => ['127.0.0.1', '::1'],
'trusted_proxy_header' => 'X-Forwarded-For',

// Path to sendmail binary.
// /usr/sbin/sendmail is the common default on many Linux systems. Change it only
// when your server or hosting provider uses a different sendmail-compatible path.
'sendmail_path' => '/usr/sbin/sendmail',

// Reminder address(es) (you).
// Each list entry must be exactly one mailbox string.
// Do not put comma-separated mailbox lists into one entry.
// Example:
// 'to_self' => [
// 'My Name <myname@example.com>',
// 'Fallback Mail <fallback@example.com>',
// ],
'to_self' => [],

// Mandatory operator warning mails also go to `to_self`.
// They are sent only for operator-facing setup/runtime problems that would otherwise
// be easy to miss in the log or journal.
//
// Allowed values: whole hours 1..24
// If you remove this key or set an invalid value, totman falls back to 2.
// The warning mail itself is built in on purpose and cannot be disabled.
'operator_alert_interval_hours' => 2,

// Mail From + optional Reply-To.
// Example: totman <totman@example.com>
'mail_from' => '',
'reply_to' => '',

// Reminder subject.
// Example: [totman] Please confirm you are safe
'subject_reminder' => '',

// Timezone for human-readable timestamps in emails.
'mail_timezone' => 'Europe/London',
// Date/time format pieces (DateTime::format()) – see https://php.net/manual/datetime.format.php
'mail_date_format' => 'j F Y',
'mail_time_format' => 'H:i:s',
// Optional: override date+time with one format string (DateTime::format()). Empty string disables.
'mail_datetime_format' => 'l, j F Y, H:i:s e',

// Mail body templates.
// Placeholders (rendered as human-readable timestamps in `mail_timezone`):
// - reminder: {CONFIRM_URL}, {DEADLINE_ISO}, {CYCLE_START_ISO}
// Escalation mail bodies live only in the configured recipient file.
'body_reminder' => '',

// Logging target mode:
// - 'none' => no script logging (not recommended)
// - 'syslog' => syslog only
// - 'file' => file only (log_file / log_file_name)
// - 'both' => syslog + file
'log_mode' => 'both',

// Optional logging path override (absolute or relative path).
// null => fallback: {state_dir}/{log_file_name}
'log_file' => null,
];
