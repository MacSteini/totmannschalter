<?php

declare(strict_types=1);

/**
 * totmannschalter – configuration template
 *
 * Project: https://github.com/MacSteini/totmannschalter
 * Licence: MIT (see LICENCE)
 *
 * Template defaults for runtime filenames, timing, mail, logging, and web behaviour.
 */

return [
// Public base URL (must be HTTPS, WITHOUT endpoint filename).
// Runtime links are built as: <base_url>/<web_file>?a=...&id=...&sig=...
'base_url' => 'https://example.com/totmann',

// State directory on disk (holds totmann.inc.php, totmann-tick.php, your configured lib_file, and runtime files).
// NOTE: Entry points do NOT use this value to locate the directory. They resolve it via:
// - totmann-tick.php: ENV TOTMANN_STATE_DIR (or __DIR__)
// - totmann.php (web endpoint): ENV TOTMANN_STATE_DIR (or define('TOTMANN_STATE_DIR', ...))
// Keep this value aligned with TOTMANN_STATE_DIR purely for clarity.
'state_dir' => '/var/lib/totmann',

// Runtime file names inside state_dir (filenames only, no paths).
// These keys are required by the runtime; invalid/missing values fail bootstrap.
'lib_file' => 'totmann-lib.php',
'web_file' => 'totmann.php',
'state_file' => 'totmann.json',
'lock_file' => 'totmann.lock',
'log_file_name' => 'totmann.log',

// Optional stylesheet filename in the webroot (same folder as web_file).
// Empty string disables stylesheet linking from the web endpoint.
'web_css_file' => 'totmann.css',

// Shared secret for HMAC signing/verification.
// - Must be hex-encoded bytes.
// - Minimum: 16 bytes (32 hex chars). Recommended: 32 bytes (64 hex chars).
// Generate (32 bytes => 64 hex chars):
// Linux/macOS (OpenSSL): openssl rand -hex 32
// Linux/macOS (no OpenSSL): head -c 32 /dev/urandom | xxd -p -c 256
// Windows (PowerShell): $b=New-Object byte[] 32;[Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b);-join($b|%{$_.ToString('x2')})
'hmac_secret_hex' => 'REPLACE_WITH_64_HEX_CHARS',

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
//		- reminder emails are sent every remind_every_seconds (to_self).
// 3) At/after (deadline_at + grace):
//		- if you did NOT confirm during this cycle, the cycle counts as "missed".
//		- only after missed_cycles_before_fire missed cycles: escalation triggers.
//		- escalation email goes to to_recipients.
//
// NOTE: The systemd timer can tick every minute without changing these time windows.
//       It just checks whether a boundary has been reached.

// Length of a cycle (when the next confirmation window starts).
'check_interval_seconds' => 60 * 60 * 24 * 14, // fortnightly

// Length of the confirmation window once it opens (time you have to click confirm).
'confirm_window_seconds' => 60 * 60 * 24 * 3, // 3 days

// Reminder frequency while the confirmation window is open.
'remind_every_seconds' => 60 * 60 * 12, // every 12 hours

// Additional grace period after the deadline before escalation is even considered.
'escalate_grace_seconds' => 60 * 60 * 6, // 6 hours

// Extra safety: require N missed cycles before escalation actually triggers.
// Note: if a cycle is missed and the threshold is not yet met, the tick starts a new cycle immediately (after deadline+grace) and carries missed_cycles forward.
'missed_cycles_before_fire' => 3,

// --- Recipient receipt acknowledgement (ACK) ---
//
// If enabled, escalation emails include an ACK link.
// Once ANY recipient clicks the ACK link, the web endpoint records escalate_ack_at and stops further ACK reminders.
//
// ACK reminders:
// - after escalation was sent, if not yet acknowledged, the same escalation email is re-sent
//   up to escalate_ack_max_reminds times, spaced by escalate_ack_remind_every_seconds.
// - escalate_ack_max_reminds counts only reminder re-sends (not the initial escalation mail).
//
// Note: ACK links are built from base_url + web_file (same endpoint as confirm links).
'escalate_ack_enabled' => true,
'escalate_ack_remind_every_seconds' => 60 * 60 * 12, // e. g., 12h between ACK reminder mails
'escalate_ack_max_reminds' => 25, // safety cap for ACK reminder sends

// --- Stealth behaviour (web endpoint responses) ---
//
// If enabled, the web endpoint returns a neutral "request received" page for:
// - invalid/missing token
// - stale/non-current token (i. e., token that was validly signed but is not the current one in totmann.json)
//
// This avoids leaking whether a token exists / whether the system is active.
'stealth_neutral_for_invalid' => true, // invalid/missing token => neutral
'stealth_level_2_neutral_on_stale' => true, // stale/non-current token => neutral

// If true, confirm success page shows "Next check at ..." (helpful for self-testing).
'show_success_details' => true,

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
// remote_addr = safest default (uses REMOTE_ADDR).
// trusted_proxy = only if your reverse proxy is configured and you list it in trusted_proxies.
// trusted_proxy_header is typically X-Forwarded-For.
'ip_mode' => 'remote_addr', // 'remote_addr' | 'trusted_proxy'
'trusted_proxies' => ['127.0.0.1', '::1'],
'trusted_proxy_header' => 'X-Forwarded-For',

// Path to sendmail binary (varies by distro/setup)
'sendmail_path' => '/usr/sbin/sendmail',

// Reminder address(es) (you).
'to_self' => [
'My Name <myname@example.com>',
'Fallback Mail <fallback@example.com>',
],

// Escalation recipients (others).
'to_recipients' => [
'Recipient 1 <recipient1@example.com>',
'Recipient 2 <recipient2@example.com>',
'Recipient 3 <recipient3@example.com>',
],

// Mail From + optional Reply-To.
'mail_from' => 'totmannschalter <totmannschalter@example.com>',
'reply_to' => 'My Name <myname@example.com>',

// Subjects.
'subject_reminder' => '[totmannschalter] Confirmation required',
'subject_escalate' => '[totmannschalter] Escalation triggered',

// Timezone for human-readable timestamps in emails.
'mail_timezone' => 'Europe/London',
// Date/time format pieces (DateTime::format()) – see https://php.net/manual/datetime.format.php
'mail_date_format' => 'j F Y', // "14. Feb 2026"
'mail_time_format' => 'H:i:s', // "21:03:12"
// Optional: override date+time with one format string (DateTime::format()). Empty string disables.
'mail_datetime_format' => 'l, j F Y, H:i:s e',

// Mail body templates.
// Placeholders (rendered as human-readable timestamps in `mail_timezone`; names kept for backwards compatibility):
// - reminder: {CONFIRM_URL}, {DEADLINE_ISO}, {CYCLE_START_ISO}
// - escalate: {LAST_CONFIRM_ISO}, {CYCLE_START_ISO}, {DEADLINE_ISO}, {ACK_URL}
'body_reminder' => <<<TXT
Hi,

Please confirm you are still alive by clicking this link:
{CONFIRM_URL}

Please confirm by: {DEADLINE_ISO}
Cycle started at: {CYCLE_START_ISO}

Note: This email link may remain valid until the next cycle starts. If you confirm after the deadline, escalation logic may already have progressed.
TXT,

'body_escalate' => <<<TXT
Hi,

The totmannschalter did not receive confirmation in time.

Last confirmation: {LAST_CONFIRM_ISO}
Cycle started at: {CYCLE_START_ISO}
Deadline was: {DEADLINE_ISO}

Ack receipt by clicking:
{ACK_URL}

[YOUR PREDEFINED MESSAGE GOES HERE – DO NOT FORGET TO CHANGE THIS DEFAULT MESSAGE]
TXT,

// Logging target mode:
// - 'none'   => no script logging (not recommended)
// - 'syslog' => syslog only
// - 'file'   => file only (log_file / log_file_name)
// - 'both'   => syslog + file
'log_mode' => 'both',

// Optional logging path override (absolute or relative path).
// null => fallback: {state_dir}/{log_file_name}
'log_file' => null,
];
