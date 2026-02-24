<?php

declare(strict_types=1);

/**
 * totmannschalter – runtime library
 *
 * Project: https://github.com/MacSteini/totmannschalter
 * Licence: MIT (see LICENCE)
 *
 * Shared helpers for time formatting, state handling, locking, mail sending,
 * token generation/validation, link building, and logging.
 */

/**
 * Convert a UNIX timestamp into a human-readable string for emails.
 *
 * Behaviour:
 * - Uses `mail_timezone` (falls back to UTC if invalid).
 * - If `mail_datetime_format` is set (non-empty), it takes precedence.
 * - Otherwise uses `mail_date_format`+`mail_time_format`.
 *
 * Notes:
 * - Uses a tiny in-function cache for DateTimeZone objects (micro-optimisation).
 * - Any invalid timezone string falls back to UTC.
 */
function dm_mail_dt(array $cfg, int $ts): string
{
    static $tzCache = [];

    $tzName = (string)($cfg['mail_timezone'] ?? 'UTC');
    if ($tzName === '') {
        $tzName = 'UTC';
    }

    if (!isset($tzCache[$tzName])) {
        try {
            $tzCache[$tzName] = new DateTimeZone($tzName);
        } catch (Throwable $e) {
            $tzCache[$tzName] = new DateTimeZone('UTC');
        }
    }
    $tz = $tzCache[$tzName];
    $dt = (new DateTimeImmutable('@' . $ts))->setTimezone($tz);

    $fmt = $cfg['mail_datetime_format'] ?? null;
    if (is_string($fmt) && $fmt !== '') {
        return $dt->format($fmt);
    }

    $df = (string)($cfg['mail_date_format'] ?? 'Y-m-d');
    $tf = (string)($cfg['mail_time_format'] ?? 'H:i:s');
    return $dt->format($df . ' ' . $tf);
}

/**
 * Render "last confirmation" for human-facing output.
 *
 * Why this exists:
 * - We use last_confirm_at=0 to mean "never confirmed" (important for escalation logic).
 * - Printing 0 as a timestamp produces 1970-01-01, which looks like a bug to humans.
 *
 * Output:
 * - If $ts <= 0: return a clear placeholder
 * - Else: normal dm_mail_dt()
 */
function dm_mail_dt_or_never(array $cfg, int $ts, string $never = 'Never'): string
{
    return ($ts > 0) ? dm_mail_dt($cfg, $ts) : $never;
}

/**
 * Wrapper for "now" to keep calling code uniform and easy to stub later.
 */
function dm_now(): int
{
    return time();
}

/**
 * ISO timestamp (UTC) for logs and machine-readable fields.
 * Always UTC, intentionally independent from `mail_timezone`.
 */
function dm_iso(int $ts): string
{
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}

/**
 * Resolve the state directory.
 * - Normalises trailing slashes.
 * - Falls back to the library directory as last resort (should rarely happen).
 */
function dm_state_dir(array $cfg): string
{
    $dir = rtrim((string)($cfg['state_dir'] ?? __DIR__), '/');
    return $dir !== '' ? $dir : __DIR__;
}

/**
 * Build an absolute path inside the state directory.
 * `name` may be given with or without a leading slash.
 */
function dm_path(array $cfg, string $name): string
{
    return dm_state_dir($cfg) . '/' . ltrim($name, '/');
}

/**
 * Validate runtime file names from config.
 * - Basename only (no slashes, no traversal, no control chars)
 * - Throws on missing/invalid values
 */
function dm_runtime_file_name(array $cfg, string $key): string
{
    $raw = trim((string)($cfg[$key] ?? ''));
    if ($raw === '') {
        throw new RuntimeException("Missing config key: {$key}");
    }
    if (str_contains($raw, '/') || str_contains($raw, '\\')) {
        throw new RuntimeException("Invalid {$key}: filename must not contain slashes");
    }
    if ($raw === '.' || $raw === '..' || str_contains($raw, '..')) {
        throw new RuntimeException("Invalid {$key}: traversal is not allowed");
    }
    if (preg_match('/[[:cntrl:]]/', $raw)) {
        throw new RuntimeException("Invalid {$key}: control chars are not allowed");
    }
    return $raw;
}

/**
 * Canonical runtime state file path.
 * Uses configurable `state_file`.
 */
function dm_state_file(array $cfg): string
{
    return dm_path($cfg, dm_runtime_file_name($cfg, 'state_file'));
}

/**
 * Canonical runtime lock file path.
 * Uses configurable `lock_file`.
 */
function dm_lock_file(array $cfg): string
{
    return dm_path($cfg, dm_runtime_file_name($cfg, 'lock_file'));
}

/**
 * Determine the logfile path.
 * - If `log_file` is explicitly set (non-empty string), use it as absolute/relative path override.
 * - Otherwise use `{state_dir}/{log_file_name}`.
 */
function dm_log_file(array $cfg): string
{
    $lf = $cfg['log_file'] ?? null;
    if (is_string($lf) && trim($lf) !== '') {
        return trim($lf);
    }
    return dm_path($cfg, dm_runtime_file_name($cfg, 'log_file_name'));
}

/**
 * Logging target mode.
 * Allowed values:
 * - none
 * - syslog
 * - file
 * - both
 *
 * Invalid/missing values safely fall back to "both".
 */
function dm_log_mode(array $cfg): string
{
    $mode = strtolower(trim((string)($cfg['log_mode'] ?? 'both')));
    if (in_array($mode, ['none', 'syslog', 'file', 'both'], true)) {
        return $mode;
    }
    return 'both';
}

/**
 * Minimal logging (best effort):
 * - Targets are controlled by `log_mode`.
 * - File and syslog logging are both best effort and never fatal.
 *
 * No `@`:
 * - We avoid many warnings via pre-checks.
 * - Remaining warnings go to logs (good for CLI/systemd).
 * - For web stealth: ensure `display_errors=Off` so nothing leaks to responses.
 */
function dm_log(array $cfg, string $msg): void
{
    try {
        $mode = dm_log_mode($cfg);
        $toFile = ($mode === 'file' || $mode === 'both');
        $toSyslog = ($mode === 'syslog' || $mode === 'both');

    // File logging (best effort, never fatal)
        if ($toFile) {
            $line = '[' . dm_iso(dm_now()) . '] ' . $msg . PHP_EOL;
            $lf = dm_log_file($cfg);
            if ($lf) {
                $dir = dirname($lf);

                // Create dir if needed (best effort)
                if (!is_dir($dir)) {
                // If mkdir fails, we simply skip file logging.
                    if (!mkdir($dir, 0770, true) && !is_dir($dir)) {
                        $dir = '';
                    }
                }

                // Write only if we can reasonably expect success
                if ($dir !== '' && is_dir($dir) && is_writable($dir)) {
                // NOTE: If your environment converts warnings to exceptions,
                // this call might throw – that's why we are inside try/catch.
                    file_put_contents($lf, $line, FILE_APPEND);
                }
            }
        }

    // Syslog logging (best effort, never fatal)
        if ($toSyslog && function_exists('syslog')) {
            openlog('totmann', LOG_PID, LOG_USER);
            syslog(LOG_INFO, $msg);
            closelog();
        }
    } catch (Throwable $e) {
    // Absolutely never let logging break the tick/web endpoint.
    }
}

/**
 * Open and lock a lock file (exclusive lock).
 * - The returned file handle must remain open while the lock is needed.
 * - Closing the handle releases the lock.
 *
 * Here we are intentionally strict: lock failure is a real correctness issue.
 */
function dm_lock_open(string $lockFile)
{
    $dir = dirname($lockFile);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create lock dir: $dir");
        }
    }

    $fh = fopen($lockFile, 'c+');
    if ($fh === false) {
        throw new RuntimeException("Cannot open lock file: $lockFile");
    }
    if (!flock($fh, LOCK_EX)) {
        throw new RuntimeException("Cannot acquire lock");
    }
    return $fh;
}

/**
 * Load JSON state from disk.
 * - Missing/empty/invalid => return empty array.
 * - Caller decides whether "empty" means "initialise" or "error".
 *
 * No `@`:
 * - Pre-check `is_readable()` to avoid warnings.
 */
function dm_state_load(string $stateFile): array
{
    if (!is_file($stateFile) || !is_readable($stateFile)) {
        return [];
    }

    $raw = file_get_contents($stateFile);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Atomic-ish save of JSON state:
 * - Write to `<state>.tmp`, then rename over `<state>`.
 * - Ensures either old or new state exists (no partial JSON).
 *
 * Strict behaviour:
 * - If writing fails, throw (caller must treat as error; avoids false positives).
 */
function dm_state_save(string $stateFile, array $state): void
{
    $dir = dirname($stateFile);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create state dir: $dir");
        }
    }

    $tmp = $stateFile . '.tmp';
    $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Failed to encode state JSON');
    }

    if (file_put_contents($tmp, $json) === false) {
        throw new RuntimeException('Failed to write tmp state');
    }
    if (!rename($tmp, $stateFile)) {
        throw new RuntimeException('Failed to rename tmp state');
    }
}

/**
 * Bootstrap helper for loading totmann.inc.php in a guarded way.
 * Throws on missing/unreadable config or invalid return type.
 */
function dm_bootstrap_load_config(string $configPath): array
{
    if (!is_file($configPath) || !is_readable($configPath)) {
        throw new RuntimeException("missing/unreadable totmann.inc.php: {$configPath}");
    }
    $cfg = require $configPath;
    if (!is_array($cfg)) {
        throw new RuntimeException('totmann.inc.php must return an array');
    }
    return $cfg;
}

/**
 * Compute cycle timing fields from a cycle start timestamp.
 */
function dm_cycle_window(int $cycleStart, int $checkInterval, int $confirmWindow): array
{
    $check = max(1, $checkInterval);
    $window = max(1, $confirmWindow);
    $nextCheck = $cycleStart + $check;
    $deadline = $nextCheck + $window;
    return ['cycle_start_at' => $cycleStart, 'next_check_at' => $nextCheck, 'deadline_at' => $deadline, 'next_reminder_at' => $nextCheck];
}

/**
 * Apply cycle timing + token fields into state.
 * Returns the computed timing fields.
 */
function dm_state_apply_cycle(array &$state, int $cycleStart, int $checkInterval, int $confirmWindow, array $token): array
{
    $timing = dm_cycle_window($cycleStart, $checkInterval, $confirmWindow);
    $state['cycle_start_at'] = $timing['cycle_start_at'];
    $state['token'] = $token;
    $state['next_check_at'] = $timing['next_check_at'];
    $state['deadline_at'] = $timing['deadline_at'];
    $state['next_reminder_at'] = $timing['next_reminder_at'];
    return $timing;
}

/**
 * Clear escalation ACK tracking fields.
 */
function dm_state_reset_ack(array &$state): void
{
    $state['escalate_ack_token'] = null;
    $state['escalate_ack_at'] = null;
    $state['escalate_ack_sent_count'] = 0;
    $state['escalate_ack_next_at'] = null;
}

/**
 * Clear escalation + ACK tracking fields.
 */
function dm_state_clear_escalation(array &$state): void
{
    $state['escalated_sent_at'] = null;
    dm_state_reset_ack($state);
}

/**
 * Decode HMAC secret from hex to binary.
 * - Requires hex input.
 * - Requires at least 16 bytes of entropy (32+ recommended).
 */
function dm_secret_bin(array $cfg): string
{
    $hex = trim((string)($cfg['hmac_secret_hex'] ?? ''));
    if ($hex === '' || !preg_match('/^[a-f0-9]+$/i', $hex) || (strlen($hex) % 2) !== 0) {
        throw new RuntimeException('Invalid hmac_secret_hex (hex, min 16 bytes)');
    }
    $bin = hex2bin($hex);
    if ($bin === false || strlen($bin) < 16) {
        throw new RuntimeException('Invalid hmac_secret_hex (hex, min 16 bytes)');
    }
    return $bin;
}

/**
 * Create a token:
 * - id: random 16 bytes => 32 hex chars
 * - sig: HMAC-SHA256 over id => 64 hex chars
 */
function dm_make_token(array $cfg): array
{
    $id = bin2hex(random_bytes(16));
    return ['id' => $id, 'sig' => hash_hmac('sha256', $id, dm_secret_bin($cfg))];
}

/**
 * Verify token format and signature.
 * - Strict hex validation avoids weird encodings.
 * - Uses hash_equals to avoid timing leaks.
 */
function dm_token_valid(array $cfg, string $id, string $sig): bool
{
    if (!preg_match('/^[a-f0-9]{32}$/', $id) || !preg_match('/^[a-f0-9]{64}$/', $sig)) {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $id, dm_secret_bin($cfg)), $sig);
}

/**
 * Build the public endpoint URL from base_url + web_file.
 */
function dm_endpoint_url(array $cfg): string
{
    $baseUrl = rtrim((string)($cfg['base_url'] ?? ''), '/');
    $webFile = dm_runtime_file_name($cfg, 'web_file');
    if ($baseUrl === '') {
        throw new RuntimeException('Missing config key: base_url');
    }
    return $baseUrl . '/' . $webFile;
}

/**
 * Create the confirmation URL for a token.
 * Adds `a=confirm&id=...&sig=...` to endpoint URL.
 */
function dm_confirm_url(array $cfg, array $token): string
{
    $base = rtrim(dm_endpoint_url($cfg), '?');
    $q = http_build_query(['a' => 'confirm', 'id' => $token['id'], 'sig' => $token['sig']]);
    return $base . (str_contains($base, '?') ? '&' : '?') . $q;
}

/**
 * Create the ACK URL for a token.
 * Adds `a=ack&id=...&sig=...` to endpoint URL.
 */
function dm_ack_url(array $cfg, array $token): string
{
    $base = rtrim(dm_endpoint_url($cfg), '?');
    $q = http_build_query(['a' => 'ack', 'id' => $token['id'], 'sig' => $token['sig']]);
    return $base . (str_contains($base, '?') ? '&' : '?') . $q;
}

/**
 * Validate individual mail IDs.
 * Allowed: lowercase letters, digits, underscore, hyphen (1..100 chars).
 */
function dm_mail_id_valid(string $id): bool
{
    $id = trim($id);
    $len = strlen($id);
    if ($len < 1 || $len > 100) {
        return false;
    }
    return (bool)preg_match('/^[a-z0-9_-]+$/', $id);
}

/**
 * Parse escalation recipients for runtime delivery.
 *
 * Expected entry format:
 * - [0] => mailbox (required)
 * - [1] => individual mail ID (optional)
 *
 * Runtime is fail-safe:
 * - malformed IDs are ignored (fallback to `subject_escalate` + `body_escalate`)
 * - malformed recipient entries are skipped
 */
function dm_escalation_recipients_runtime(array $cfg): array
{
    $raw = $cfg['to_recipients'] ?? null;
    if (!is_array($raw) || $raw === []) {
        throw new RuntimeException('Invalid to_recipients: expected non-empty list of [address] or [address, id] entries');
    }

    $out = [];

    foreach ($raw as $entry) {
        if (!is_array($entry) || !array_key_exists(0, $entry)) {
            continue;
        }

        $address = trim(str_replace(["\r", "\n"], '', (string)$entry[0]));
        if ($address === '') {
            continue;
        }

        $mailbox = $address;
        if (preg_match('/<([^>]+)>/', $address, $m)) {
            $mailbox = trim($m[1]);
        }

        $isValid = (str_contains($mailbox, '@') && filter_var($mailbox, FILTER_VALIDATE_EMAIL) !== false);
        if (!$isValid && str_contains($mailbox, '@')) {
            $isValid = (bool)preg_match('/^[^\s@<>",;:]+@[^\s@<>",;:]+\.[^\s@<>",;:]+$/', $mailbox);
        }
        if (!$isValid) {
            continue;
        }

        $mailId = '';
        if (array_key_exists(1, $entry) && (is_string($entry[1]) || is_numeric($entry[1]))) {
            $candidate = trim(str_replace(["\r", "\n"], '', (string)$entry[1]));
            if (dm_mail_id_valid($candidate)) {
                $mailId = $candidate;
            }
        }

        $out[] = ['address' => $address, 'mail_id' => $mailId];
    }

    if ($out === []) {
        throw new RuntimeException('sendmail: empty/invalid recipient list');
    }
    return $out;
}

/**
 * Load individual message texts from external file.
 *
 * The external file must return an array:
 * - id => ['subject' => string, 'body' => string]
 */
function dm_individual_messages_load(array $cfg): array
{
    $fileNameRaw = trim((string)($cfg['mail_file'] ?? ''));
    if ($fileNameRaw === '') {
        return [];
    }

    $fileName = dm_runtime_file_name(['mail_file' => $fileNameRaw], 'mail_file');
    $path = dm_path($cfg, $fileName);

    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException("mail_file missing/unreadable: {$path}");
    }
    $data = require $path;
    if (!is_array($data)) {
        throw new RuntimeException("mail_file must return an array: {$path}");
    }

    $out = [];
    foreach ($data as $id => $entry) {
        if (!is_string($id) || !dm_mail_id_valid($id)) {
            continue;
        }
        if (!is_array($entry)) {
            continue;
        }
        $subject = $entry['subject'] ?? null;
        $body = $entry['body'] ?? null;
        if (!is_string($subject) || !is_string($body)) {
            continue;
        }
        if (trim($subject) === '' || trim($body) === '') {
            continue;
        }
        $out[$id] = ['subject' => $subject, 'body' => $body];
    }
    return $out;
}

/**
 * Expand recipient config entries into individual mailbox entries.
 *
 * Input format:
 * - list of strings, each string may contain one or more comma-separated mailboxes
 *
 * Output:
 * - list of unique, valid mailbox entries (display names preserved)
 */
function dm_recipient_entries_runtime(array $list): array
{
    $out = [];
    $seen = [];

    foreach ($list as $rawEntry) {
        $rawEntry = str_replace(["\r", "\n"], '', trim((string)$rawEntry));
        if ($rawEntry === '') {
            continue;
        }

        $parts = array_map('trim', explode(',', $rawEntry));
        foreach ($parts as $part) {
            $part = str_replace(["\r", "\n"], '', $part);
            if ($part === '') {
                continue;
            }

            $addr = $part;
            if (preg_match('/<([^>]+)>/', $part, $m)) {
                $addr = trim($m[1]);
            }
            $addr = trim($addr);

            if (function_exists('idn_to_ascii') && str_contains($addr, '@')) {
                [$local, $domain] = explode('@', $addr, 2);
                $domain = trim($domain);
                if ($domain !== '') {
                    $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
                    $ascii = idn_to_ascii($domain, 0, $variant);
                    if (is_string($ascii) && $ascii !== '') {
                        $addr = $local . '@' . $ascii;
                    }
                }
            }

            $isValid = (str_contains($addr, '@') && filter_var($addr, FILTER_VALIDATE_EMAIL) !== false);
            if (!$isValid && str_contains($addr, '@')) {
                $isValid = (bool)preg_match('/^[^\s@<>",;:]+@[^\s@<>",;:]+\.[^\s@<>",;:]+$/', $addr);
            }
            if (!$isValid) {
                continue;
            }

            $key = strtolower($addr);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $part;
        }
    }

    return $out;
}

/**
 * Resolve escalation template text for one recipient.
 *
 * If recipient ID has no mapping, fallback to standard escalation config.
 */
function dm_escalate_message_for_recipient(array $cfg, string $mailId, array $templates): array
{
    $fallback = [
    'subject' => (string)($cfg['subject_escalate'] ?? ''),
    'body' => (string)($cfg['body_escalate'] ?? ''),
    ];
    if ($mailId !== '' && array_key_exists($mailId, $templates)) {
        return (array)$templates[$mailId];
    }
    return $fallback;
}

/**
 * Render escalation text from placeholders.
 *
 * Supported placeholders:
 * - {LAST_CONFIRM_ISO}
 * - {CYCLE_START_ISO}
 * - {DEADLINE_ISO}
 * - {ACK_URL}
 */
function dm_render_escalate_template(array $cfg, string $tpl, int $lastConfirm, int $cycleStart, int $deadline, string $ackUrl, bool $ackEnabled): string
{

    if (!$ackEnabled) {
        $tpl = str_replace(["Ack receipt by clicking:\n{ACK_URL}\n\n", "Ack receipt by clicking:\r\n{ACK_URL}\r\n\r\n"], ["", ""], $tpl);
        $tpl = str_replace('{ACK_URL}', '', $tpl);
    }

    return str_replace(['{LAST_CONFIRM_ISO}', '{CYCLE_START_ISO}', '{DEADLINE_ISO}', '{ACK_URL}'], [dm_mail_dt_or_never($cfg, $lastConfirm), dm_mail_dt($cfg, $cycleStart), dm_mail_dt($cfg, $deadline), $ackUrl], $tpl);
}

/**
 * Write the full payload to a stream (handles short writes).
 */
function dm_stream_write_all($stream, string $data): bool
{
    $len = strlen($data);
    $off = 0;
    while ($off < $len) {
        $written = fwrite($stream, substr($data, $off));
        if ($written === false || $written === 0) {
            return false;
        }
        $off += $written;
    }
    return true;
}

/**
 * Persist JSON content into an already locked file handle.
 */
function dm_locked_write_json($fh, array $data): bool
{
    $json = json_encode($data);
    if (!is_string($json)) {
        return false;
    }
    if (!ftruncate($fh, 0)) {
        return false;
    }
    if (!rewind($fh)) {
        return false;
    }
    if (!dm_stream_write_all($fh, $json)) {
        return false;
    }
    if (!fflush($fh)) {
        return false;
    }
    return true;
}

/**
 * RFC 2047 header encoding for non-ASCII strings.
 * - If ASCII: returned as-is
 * - If UTF-8: encoded as =?UTF-8?B?...?=
 *
 * This avoids triggering SMTPUTF8 on picky remote MTAs.
 */
function dm_hdr_encode(string $s): string
{
// Prevent header injection (strip CR/LF)
    $s = str_replace(["\r", "\n"], '', $s);
// fast path: pure ASCII
    if ($s === '' || preg_match('/^[\x00-\x7F]*$/', $s)) {
        return $s;
    }
// base64-encode UTF-8 header (single chunk; good enough for typical short subjects/names)
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

/**
 * Encode a mailbox header value like:
 * "Name <addr@example.com>"OR"addr@example.com"
 *
 * Only the display name is RFC2047-encoded; address stays ASCII.
 */
function dm_hdr_mailbox(string $raw): string
{
    $raw = trim($raw);
// Prevent header injection (strip CR/LF)
    $raw = str_replace(["\r", "\n"], '', $raw);
    if ($raw === '') {
        return '';
    }
// "Name <addr>"
    if (preg_match('/^\s*(.*?)\s*<\s*([^>]+)\s*>\s*$/', $raw, $m)) {
        $name = trim($m[1]);
        $addr = trim($m[2]);
    // Prevent header injection (strip CR/LF)
        $name = str_replace(["\r", "\n"], '', $name);
        $addr = str_replace(["\r", "\n"], '', $addr);
        if ($name !== '') {
            $name = dm_hdr_encode($name);
        }
        return ($name !== '' ? $name . ' ' : '') . '<' . $addr . '>';
    }
// bare addr
    return $raw;
}

/**
 * Send a plain text email via sendmail binary.
 *
 * Key point:
 * - We RFC2047-encode non-ASCII in headers (Subject/From/Reply-To/To)
 * so Postfix does NOT need SMTPUTF8 for remote MTAs that lack it.
 */
function dm_send_mail(array $cfg, array $to, string $subject, string $body): void
{
    $fromRaw = trim((string)($cfg['mail_from'] ?? ''));
    $replyRaw = trim((string)($cfg['reply_to'] ?? ''));

    $toHeader = [];
    $toArgv = [];
    $seen = [];

    $addArgv = function (string $addr) use (&$toArgv, &$seen): void {
        $k = strtolower($addr);
        if (isset($seen[$k])) {
            return;
        }
        $seen[$k] = true;
        $toArgv[] = $addr;
    };

    foreach ($to as $raw) {
        $raw = str_replace(["\r", "\n"], '', trim((string)$raw));
        if ($raw === '') {
            continue;
        }

    // Allow comma-separated lists in one entry (store each mailbox separately for header encoding)
        $parts = array_map('trim', explode(',', $raw));
        foreach ($parts as $part) {
            $part = str_replace(["\r", "\n"], '', $part);
            if ($part === '') {
                continue;
            }
            $toHeader[] = $part;

        // Extract addr from "Name <addr>"
            $addr = $part;
            if (preg_match('/<([^>]+)>/', $part, $m)) {
                $addr = trim($m[1]);
            }
            $addr = trim($addr);

        // Optional: IDN domain normalisation if intl is installed
            if (function_exists('idn_to_ascii') && str_contains($addr, '@')) {
                [$local, $domain] = explode('@', $addr, 2);
                $domain = trim($domain);
                if ($domain !== '') {
                    $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
                    $ascii = idn_to_ascii($domain, 0, $variant);
                    if (is_string($ascii) && $ascii !== '') {
                        $addr = $local . '@' . $ascii;
                    }
                }
            }

        // Validate mailbox for argv (must be ASCII mailbox)
            $isValid = (str_contains($addr, '@') && filter_var($addr, FILTER_VALIDATE_EMAIL) !== false);
            if (!$isValid && str_contains($addr, '@')) {
                $isValid = (bool)preg_match('/^[^\s@<>",;:]+@[^\s@<>",;:]+\.[^\s@<>",;:]+$/', $addr);
            }
            if ($isValid) {
                $addArgv($addr);
            }
        }
    }

    $toHeader = array_values(array_unique($toHeader));
    if (!$toArgv) {
        throw new RuntimeException('sendmail: empty/invalid recipient list');
    }

// RFC2047 encode non-ASCII parts
    $fromHeader = ($fromRaw !== '') ? dm_hdr_mailbox($fromRaw) : '';
    $replyHeader = ($replyRaw !== '') ? dm_hdr_mailbox($replyRaw) : '';
    $toHeaderEnc = array_map('dm_hdr_mailbox', $toHeader);
    $subjectEnc = dm_hdr_encode($subject);

    $h = [];
    if ($fromHeader !== '') {
        $h[] = 'From: ' . $fromHeader;
    }
    if ($replyHeader !== '') {
        $h[] = 'Reply-To: ' . $replyHeader;
    }
    $h[] = 'MIME-Version: 1.0';
    $h[] = 'Content-Type: text/plain; charset=UTF-8';
    $h[] = 'Content-Transfer-Encoding: 8bit';
    $h[] = 'Subject: ' . $subjectEnc;
    $h[] = 'To: ' . implode(', ', $toHeaderEnc);

    $msg = implode("\r\n", $h) . "\r\n\r\n" . $body . "\r\n";

    $sendmail = (string)($cfg['sendmail_path'] ?? '/usr/sbin/sendmail');
    if (!is_executable($sendmail)) {
        throw new RuntimeException("sendmail not found/executable at {$sendmail}");
    }

    $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $cmd = array_merge([$sendmail, '-i', '--'], $toArgv);

    $proc = proc_open($cmd, $desc, $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('proc_open failed for sendmail');
    }

    $stdout = $stderr = '';
    try {
        if (!isset($pipes[0]) || !is_resource($pipes[0])) {
            throw new RuntimeException('sendmail: stdin pipe missing');
        }

        $wrote = dm_stream_write_all($pipes[0], $msg);
        fclose($pipes[0]);

        $stdout = (isset($pipes[1]) && is_resource($pipes[1])) ? stream_get_contents($pipes[1]) : '';
        if (isset($pipes[1]) && is_resource($pipes[1])) {
            fclose($pipes[1]);
        }

        $stderr = (isset($pipes[2]) && is_resource($pipes[2])) ? stream_get_contents($pipes[2]) : '';
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            fclose($pipes[2]);
        }

        $code = proc_close($proc);

        if (!$wrote) {
            throw new RuntimeException("sendmail: failed to write message to stdin: {$stderr} {$stdout}");
        }
        if ($code !== 0) {
            throw new RuntimeException("sendmail failed (code {$code}): {$stderr} {$stdout}");
        }
    } finally {
        foreach ($pipes as $p) {
            if (is_resource($p)) {
                fclose($p);
            }
        }
    }
}

/**
 * Determine client IP for rate limiting.
 *
 * Modes:
 * - remote_addr: always use REMOTE_ADDR (safest default).
 * - trusted_proxy: if REMOTE_ADDR is a trusted proxy, read first IP from configured header.
 *
 * Notes:
 * - Only the first XFF value is used ("client, proxy1, proxy2 ...").
 * - If parsing fails, falls back to REMOTE_ADDR.
 */
function dm_client_ip(array $cfg): string
{
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($remote === '') {
        $remote = '0.0.0.0';
    }

    if ((string)($cfg['ip_mode'] ?? 'remote_addr') !== 'trusted_proxy') {
        return $remote;
    }

    $trusted = (array)($cfg['trusted_proxies'] ?? []);
    if (!in_array($remote, $trusted, true)) {
        return $remote;
    }

    $hdr = (string)($cfg['trusted_proxy_header'] ?? 'X-Forwarded-For');
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $hdr));
    $val = (string)($_SERVER[$key] ?? '');
    if ($val === '') {
        return $remote;
    }

    $first = trim(explode(',', $val, 2)[0]);
    return ($first !== '' && filter_var($first, FILTER_VALIDATE_IP)) ? $first : $remote;
}

/**
 * Simple per-IP rate limiter (fail-open).
 *
 * - Stores a JSON file per IP hash under `{rate_limit_dir}/ab/<hash>.json`.
 * - Keeps an array of timestamps ("hits") within a rolling window.
 * - If the count >= max: deny (return false).
 *
 * Fail-open philosophy:
 * - If directory is missing / not writable / locking fails -> return true.
 * - Reason: rate limiting is abuse reduction, not correctness.
 *
 * No `@`:
 * - We avoid most warnings via pre-checks.
 * - Any remaining failures simply cause a "true" (fail-open).
 */
function dm_rate_limit_check(array $cfg, string $ip, int $now): bool
{
    if (empty($cfg['rate_limit_enabled'])) {
        return true;
    }

    $dir = $cfg['rate_limit_dir'] ?? '';
    $dir = is_string($dir) ? $dir : '';
    if ($dir === '') {
        $dir = dm_path($cfg, 'ratelimit');
    }

// If misconfigured, do not break the web endpoint.
    if ($dir === '/ratelimit' || $dir === 'ratelimit') {
        return true;
    }
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0770, true) && !is_dir($dir)) {
            return true;
        }
    }
    if (!is_writable($dir)) {
        return true;
    }

    $max = max(1, (int)($cfg['rate_limit_max_requests'] ?? 30));
    $win = max(1, (int)($cfg['rate_limit_window_seconds'] ?? 60));

    $key = hash('sha256', $ip);
    $path = rtrim($dir, '/') . '/' . substr($key, 0, 2) . '/' . $key . '.json';

    $subdir = dirname($path);
    if (!is_dir($subdir)) {
        if (!mkdir($subdir, 0770, true) && !is_dir($subdir)) {
            return true;
        }
    }

    $fh = fopen($path, 'c+');
    if ($fh === false) {
        return true;
    }

    try {
        if (!flock($fh, LOCK_EX)) {
            return true;
        }

        rewind($fh);
        $raw = stream_get_contents($fh);
        $data = (is_string($raw) && trim($raw) !== '') ? json_decode($raw, true) : [];
        if (!is_array($data)) {
            $data = [];
        }

        $hits = $data['hits'] ?? [];
        if (!is_array($hits)) {
            $hits = [];
        }

        $cutoff = $now - $win;
        $hits = array_values(array_filter($hits, fn($t) => (int)$t >= $cutoff));

        if (count($hits) >= $max) {
            $data['hits'] = $hits;
            $data['last'] = $now;
            if (!dm_locked_write_json($fh, $data)) {
                return true;
            }
            return false;
        }

        $hits[] = $now;
        $data['hits'] = $hits;
        $data['last'] = $now;

        if (!dm_locked_write_json($fh, $data)) {
            return true;
        }

        return true;
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}


/**
 * Tick/bootstrap helper functions used by totmann-tick.php.
 */

function dm_bootstrap_load_config_raw(string $configPath): array
{
    if (!is_file($configPath) || !is_readable($configPath)) {
        throw new RuntimeException("missing/unreadable totmann.inc.php: {$configPath}");
    }
    $cfg = require $configPath;
    if (!is_array($cfg)) {
        throw new RuntimeException('totmann.inc.php must return an array');
    }
    return $cfg;
}

function dm_bootstrap_file_name(array $cfg, string $key): string
{
    $v = trim((string)($cfg[$key] ?? ''));
    if ($v === '') {
        throw new RuntimeException("Missing config key: {$key}");
    }
    if (str_contains($v, '/') || str_contains($v, '\\')) {
        throw new RuntimeException("Invalid {$key}: filename must not contain slashes");
    }
    if ($v === '.' || $v === '..' || str_contains($v, '..') || preg_match('/[[:cntrl:]]/', $v)) {
        throw new RuntimeException("Invalid {$key}: traversal/control chars not allowed");
    }
    return $v;
}

function dm_cfg_int_required(array $cfg, string $key, int $min, ?int $max = null): int
{
    if (!array_key_exists($key, $cfg)) {
        throw new RuntimeException("Missing config key: {$key}");
    }
    $raw = $cfg[$key];
    if (is_int($raw)) {
        $v = $raw;
    } elseif (is_string($raw) && preg_match('/^-?\d+$/', trim($raw))) {
        $v = (int)trim($raw);
    } else {
        throw new RuntimeException("Invalid {$key}: expected integer, got " . gettype($raw));
    }
    if ($v < $min) {
        throw new RuntimeException("Invalid {$key}: must be >= {$min}, got {$v}");
    }
    if ($max !== null && $v > $max) {
        throw new RuntimeException("Invalid {$key}: must be <= {$max}, got {$v}");
    }
    return $v;
}

function dm_validate_runtime_config(array $cfg): array
{
    $warnings = [];

    $checkInterval = dm_cfg_int_required($cfg, 'check_interval_seconds', 1);
    $confirmWindow = dm_cfg_int_required($cfg, 'confirm_window_seconds', 1);
    $remindEvery = dm_cfg_int_required($cfg, 'remind_every_seconds', 1);
    $escalateGrace = dm_cfg_int_required($cfg, 'escalate_grace_seconds', 0);
    $missedCyclesBeforeFire = dm_cfg_int_required($cfg, 'missed_cycles_before_fire', 1);

    $ackEnabled = !empty($cfg['escalate_ack_enabled']);
    $ackRemindEvery = 60;
    $ackMaxReminds = 0;
    if ($ackEnabled) {
        $ackRemindEvery = dm_cfg_int_required($cfg, 'escalate_ack_remind_every_seconds', 1);
        $ackMaxReminds = dm_cfg_int_required($cfg, 'escalate_ack_max_reminds', 0);
        if ($ackRemindEvery < 60) {
            $warnings[] = 'escalate_ack_remind_every_seconds is below 60; runtime clamps it to 60 seconds.';
        }
    }

    if ($confirmWindow > $checkInterval) {
        $warnings[] = 'confirm_window_seconds is greater than check_interval_seconds.';
    }
    if ($remindEvery > $confirmWindow) {
        $warnings[] = 'remind_every_seconds is greater than confirm_window_seconds; only limited reminders may occur per cycle.';
    }

    return [
    'check_interval_seconds' => $checkInterval,
    'confirm_window_seconds' => $confirmWindow,
    'remind_every_seconds' => $remindEvery,
    'escalate_grace_seconds' => $escalateGrace,
    'missed_cycles_before_fire' => $missedCyclesBeforeFire,
    'ack_enabled' => $ackEnabled,
    'escalate_ack_remind_every_seconds' => $ackRemindEvery,
    'escalate_ack_max_reminds' => $ackMaxReminds,
    'warnings' => $warnings,
    ];
}

/**
 * Read-only preflight checks for GoLive readiness.
 *
 * Exit codes:
 * - 0: all checks passed
 * - 1: warnings only
 * - 2: at least one hard failure
 */
function dm_preflight_check(string $stateDir, ?string $webUser = null): int
{
    $okCount = 0;
    $warnCount = 0;
    $failCount = 0;

    $emit = static function (string $level, string $msg) use (&$okCount, &$warnCount, &$failCount): void {
        if ($level === 'OK') {
            $okCount++;
        }
        if ($level === 'WARN') {
            $warnCount++;
        }
        if ($level === 'FAIL') {
            $failCount++;
        }
        echo "[{$level}] {$msg}\n";
    };

    $ok = static function (string $msg) use ($emit): void {
        $emit('OK', $msg);
    };
    $warn = static function (string $msg) use ($emit): void {
        $emit('WARN', $msg);
    };
    $fail = static function (string $msg) use ($emit): void {
        $emit('FAIL', $msg);
    };

    $looksPlaceholder = static function (string $value): bool {
        $v = strtolower($value);
        return str_contains($v, 'example.com') || str_contains($v, 'replace_with') || str_contains($v, 'localhost');
    };

    $stateDir = rtrim($stateDir, '/');
    if ($stateDir === '') {
        $stateDir = '.';
    }
    $ok("Resolved state directory: {$stateDir}");

    if (!is_dir($stateDir)) {
        $fail("State directory does not exist: {$stateDir}");
    } else {
        if (!is_readable($stateDir)) {
            $fail("State directory is not readable for current user: {$stateDir}");
        }
        if (!is_writable($stateDir)) {
            $warn("State directory is not writable for current user: {$stateDir}");
        }
    }

    $configPath = $stateDir . '/totmann.inc.php';
    $tickPath = $stateDir . '/totmann-tick.php';

    foreach (['totmann.inc.php' => $configPath, 'totmann-tick.php' => $tickPath] as $name => $path) {
        if (is_file($path) && is_readable($path)) {
            $ok("Found {$name}: {$path}");
        } else {
            $fail("Missing/unreadable {$name}: {$path}");
        }
    }

    $cfg = [];
    $libFileName = 'totmann-lib.php';
    $webFileName = 'totmann.php';
    $stateFileName = 'totmann.json';
    $lockFileName = 'totmann.lock';
    $logFileName = 'totmann.log';
    $mailFileName = 'totmann-messages.php';
    $webCssFileName = 'totmann.css';
    if (is_file($configPath) && is_readable($configPath)) {
        try {
            $cfg = dm_bootstrap_load_config_raw($configPath);
            $ok('Loaded totmann.inc.php');
        } catch (Throwable $e) {
            $fail('Loading totmann.inc.php failed: ' . $e->getMessage());
        }
    }
    $libPath = $stateDir . '/' . $libFileName;
    if ($cfg) {
        $runtimeFileName = static function (array $cfg, string $key, callable $fail): string {
            $v = trim((string)($cfg[$key] ?? ''));
            if ($v === '') {
                $fail("{$key} is missing/empty.");
                return '__invalid__';
            }
            if (str_contains($v, '/') || str_contains($v, '\\')) {
                $fail("{$key} must be a filename only (no slashes): {$v}");
                return '__invalid__';
            }
            if ($v === '.' || $v === '..' || str_contains($v, '..') || preg_match('/[[:cntrl:]]/', $v)) {
                $fail("{$key} contains invalid characters: {$v}");
                return '__invalid__';
            }
            return $v;
        };

        $optionalRuntimeFileName = static function (array $cfg, string $key, callable $fail): ?string {
            if (!array_key_exists($key, $cfg)) {
                return null;
            }
            $v = trim((string)$cfg[$key]);
            if ($v === '') {
                return null;
            }
            if (str_contains($v, '/') || str_contains($v, '\\')) {
                $fail("{$key} must be a filename only (no slashes): {$v}");
                return '__invalid__';
            }
            if ($v === '.' || $v === '..' || str_contains($v, '..') || preg_match('/[[:cntrl:]]/', $v)) {
                $fail("{$key} contains invalid characters: {$v}");
                return '__invalid__';
            }
            return $v;
        };

        $libFileName = $runtimeFileName($cfg, 'lib_file', $fail);
        $webFileName = $runtimeFileName($cfg, 'web_file', $fail);
        $stateFileName = $runtimeFileName($cfg, 'state_file', $fail);
        $lockFileName = $runtimeFileName($cfg, 'lock_file', $fail);
        $logFileName = $runtimeFileName($cfg, 'log_file_name', $fail);
        $mailFileName = $runtimeFileName($cfg, 'mail_file', $fail);
        $webCssFileName = $optionalRuntimeFileName($cfg, 'web_css_file', $fail);
        if ($failCount === 0) {
            $cssMsg = ($webCssFileName === null) ? 'css=disabled' : "css={$webCssFileName}";
            $ok("Runtime filenames: lib={$libFileName}, lock={$lockFileName}, log={$logFileName}, mail={$mailFileName}, state={$stateFileName}, web={$webFileName}, {$cssMsg}");
        }
        if ($webCssFileName === null) {
            $ok('web_css_file empty: stylesheet link from web endpoint is disabled.');
        }
        $libPath = $stateDir . '/' . $libFileName;
        $mailPath = $stateDir . '/' . $mailFileName;

        if (is_file($libPath) && is_readable($libPath)) {
            $ok("Found {$libFileName}: {$libPath}");
        } else {
            $fail("Missing/unreadable {$libFileName}: {$libPath}");
        }
        if (is_file($mailPath) && is_readable($mailPath)) {
            $ok("Found {$mailFileName}: {$mailPath}");
        } else {
            $fail("Missing/unreadable {$mailFileName}: {$mailPath}");
        }

        $configuredStateDir = rtrim((string)($cfg['state_dir'] ?? ''), '/');
        if ($configuredStateDir === '') {
            $warn('totmann.inc.php state_dir is empty.');
        } elseif ($configuredStateDir !== $stateDir) {
            $warn("totmann.inc.php state_dir ({$configuredStateDir}) differs from resolved state dir ({$stateDir}).");
        } else {
            $ok('totmann.inc.php state_dir matches resolved state dir.');
        }

        $secret = trim((string)($cfg['hmac_secret_hex'] ?? ''));
        if ($secret === '') {
            $fail('hmac_secret_hex is empty.');
        } elseif (str_contains($secret, 'REPLACE_WITH')) {
            $fail('hmac_secret_hex still contains placeholder text.');
        } elseif (!preg_match('/^[a-f0-9]+$/i', $secret)) {
            $fail('hmac_secret_hex must be hex-encoded.');
        } elseif ((strlen($secret) % 2) !== 0) {
            $fail('hmac_secret_hex length must be even.');
        } elseif (strlen($secret) < 32) {
            $fail('hmac_secret_hex must be at least 32 hex chars (16 bytes).');
        } elseif (strlen($secret) < 64) {
            $warn('hmac_secret_hex is valid but shorter than recommended 64 hex chars (32 bytes).');
        } else {
            $ok('hmac_secret_hex format/length looks good.');
        }

        $checkBaseUrl = static function (array $cfg, string $key, bool $required, bool $httpsOnly, bool $forbidPlaceholder, string $webFileName, callable $ok, callable $warn, callable $fail, callable $looksPlaceholder): void {
            $url = trim((string)($cfg[$key] ?? ''));
            if ($url === '') {
                if ($required) {
                    $fail("{$key} is empty.");
                } else {
                    $warn("{$key} is empty.");
                }
                return;
            }
            if ($forbidPlaceholder && $looksPlaceholder($url)) {
                $fail("{$key} contains placeholder/local host value: {$url}");
                return;
            }
            $parts = parse_url($url);
            if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
                $fail("{$key} must be an absolute URL: {$url}");
                return;
            }
            if ($httpsOnly && strtolower((string)$parts['scheme']) !== 'https') {
                $fail("{$key} must use HTTPS for GoLive: {$url}");
                return;
            }
            $path = (string)($parts['path'] ?? '');
            $base = $path !== '' ? basename($path) : '';
            if ($webFileName !== '__invalid__' && $base === $webFileName) {
                $warn("{$key} currently includes web_file ({$webFileName}). Use only the base URL path; web_file is appended automatically.");
            }
            if (!empty($parts['query'])) {
                $warn("{$key} should not include a query string; it is added automatically.");
            }
            $ok("{$key} looks valid.");
        };

        $checkBaseUrl($cfg, 'base_url', true, true, true, $webFileName, $ok, $warn, $fail, $looksPlaceholder);

        try {
            $validatedRuntimeCfg = dm_validate_runtime_config($cfg);
            $ok('Runtime timing config validation passed.');
            foreach ((array)($validatedRuntimeCfg['warnings'] ?? []) as $w) {
                $warn('Runtime timing warning: ' . $w);
            }
        } catch (Throwable $e) {
            $fail('Runtime timing config validation failed: ' . $e->getMessage());
        }

        $checkRecipients = static function (array $cfg, string $key, callable $ok, callable $warn, callable $fail, callable $looksPlaceholder): void {
            $list = $cfg[$key] ?? null;
            if (!is_array($list) || $list === []) {
                $fail("{$key} must contain at least one mailbox.");
                return;
            }

            $valid = 0;
            $invalid = 0;
            $placeholder = 0;

            foreach ($list as $entry) {
                $entry = trim((string)$entry);
                if ($entry === '') {
                    continue;
                }
                $parts = array_filter(array_map('trim', explode(',', $entry)), static fn(string $p): bool => $p !== '');
                foreach ($parts as $part) {
                    $addr = $part;
                    if (preg_match('/<([^>]+)>/', $part, $m)) {
                        $addr = trim($m[1]);
                    }
                    if ($addr === '' || !str_contains($addr, '@')) {
                        $invalid++;
                        continue;
                    }
                    if (filter_var($addr, FILTER_VALIDATE_EMAIL) === false) {
                        $invalid++;
                        continue;
                    }
                    $valid++;
                    if ($looksPlaceholder($part) || $looksPlaceholder($addr)) {
                        $placeholder++;
                    }
                }
            }

            if ($valid < 1) {
                $fail("{$key} does not contain any valid mailbox.");
                return;
            }
            if ($invalid > 0) {
                $warn("{$key} contains {$invalid} malformed mailbox entr" . ($invalid === 1 ? 'y.' : 'ies.'));
            }
            if ($placeholder > 0) {
                $fail("{$key} contains placeholder addresses (example.com/localhost).");
            } else {
                $ok("{$key} has {$valid} valid mailbox entr" . ($valid === 1 ? 'y.' : 'ies.'));
            }
        };

        $checkEscalationRecipients = static function (array $cfg, string $key, callable $ok, callable $warn, callable $fail, callable $looksPlaceholder): array {
            $list = $cfg[$key] ?? null;
            if (!is_array($list) || $list === []) {
                $fail("{$key} must contain at least one recipient entry in the format [address] or [address, id].");
                return ['ids' => []];
            }

            $valid = 0;
            $invalid = 0;
            $placeholder = 0;
            $invalidIds = 0;
            $ids = [];

            foreach ($list as $entry) {
                if (!is_array($entry) || !array_key_exists(0, $entry)) {
                    $invalid++;
                    continue;
                }

                foreach (array_keys($entry) as $k) {
                    if ($k !== 0 && $k !== 1) {
                        $invalid++;
                        continue 2;
                    }
                }

                $rawAddress = trim(str_replace(["\r", "\n"], '', (string)$entry[0]));
                if ($rawAddress === '') {
                    $invalid++;
                    continue;
                }

                $addr = $rawAddress;
                if (preg_match('/<([^>]+)>/', $rawAddress, $m)) {
                    $addr = trim($m[1]);
                }
                if ($addr === '' || !str_contains($addr, '@')) {
                    $invalid++;
                    continue;
                }

                $isValid = (filter_var($addr, FILTER_VALIDATE_EMAIL) !== false);
                if (!$isValid) {
                    $isValid = (bool)preg_match('/^[^\s@<>",;:]+@[^\s@<>",;:]+\.[^\s@<>",;:]+$/', $addr);
                }
                if (!$isValid) {
                    $invalid++;
                    continue;
                }

                if (array_key_exists(1, $entry)) {
                    if (!(is_string($entry[1]) || is_numeric($entry[1]))) {
                        $invalidIds++;
                        continue;
                    }
                    $rawId = trim(str_replace(["\r", "\n"], '', (string)$entry[1]));
                    if ($rawId !== '') {
                        $idLen = strlen($rawId);
                        $idOk = ($idLen <= 100 && (bool)preg_match('/^[a-z0-9_-]+$/', $rawId));
                        if ($idOk) {
                            $ids[$rawId] = true;
                        } else {
                            $invalidIds++;
                        }
                    }
                }

                $valid++;
                if ($looksPlaceholder($rawAddress) || $looksPlaceholder($addr)) {
                    $placeholder++;
                }
            }

            if ($valid < 1) {
                $fail("{$key} does not contain any valid recipient entries.");
                return ['ids' => []];
            }

            if ($invalid > 0) {
                $warn("{$key} contains {$invalid} malformed recipient " . ($invalid === 1 ? 'entry.' : 'entries.'));
            }
            if ($placeholder > 0) {
                $fail("{$key} contains placeholder addresses (example.com/localhost).");
            } else {
                $ok("{$key} has {$valid} valid recipient " . ($valid === 1 ? 'entry.' : 'entries.'));
            }

            if ($invalidIds > 0) {
                $fail("{$key} contains {$invalidIds} invalid recipient id entr" . ($invalidIds === 1 ? 'y' : 'ies') . ". Allowed: ^[a-z0-9_-]+$ (1..100 chars).");
            } elseif ($ids !== []) {
                $ok("{$key} id format check passed (" . count($ids) . " id entr" . (count($ids) === 1 ? 'y' : 'ies') . ').');
            }

            return ['ids' => array_keys($ids)];
        };

        $checkRecipients($cfg, 'to_self', $ok, $warn, $fail, $looksPlaceholder);
        $escalationRecipientCheck = $checkEscalationRecipients($cfg, 'to_recipients', $ok, $warn, $fail, $looksPlaceholder);
        $recipientIds = (array)$escalationRecipientCheck['ids'];

        $subjectEscalate = (string)($cfg['subject_escalate'] ?? '');
        if (trim($subjectEscalate) === '') {
            $fail('subject_escalate is empty.');
        } else {
            $ok('subject_escalate is set.');
        }

        $bodyEscalate = (string)($cfg['body_escalate'] ?? '');
        if (trim($bodyEscalate) === '') {
            $fail('body_escalate is empty.');
        } else {
            $ok('body_escalate is set.');
        }

        if ($recipientIds !== []) {
            $messagesFile = trim((string)($cfg['mail_file'] ?? ''));
            if ($messagesFile === '') {
                $fail('mail_file is empty while recipient IDs are configured.');
            } else {
                $fileNameOk = !(str_contains($messagesFile, '/') || str_contains($messagesFile, '\\') || $messagesFile === '.' || $messagesFile === '..' || str_contains($messagesFile, '..') || (bool)preg_match('/[[:cntrl:]]/', $messagesFile));
                if (!$fileNameOk) {
                    $fail("mail_file is invalid (filename only, no slashes/traversal): {$messagesFile}");
                } else {
                    $templatesPath = $stateDir . '/' . $messagesFile;
                    if (!is_file($templatesPath) || !is_readable($templatesPath)) {
                        $fail("mail_file missing/unreadable: {$templatesPath}");
                    } else {
                        try {
                            $templatesRaw = require $templatesPath;
                            if (!is_array($templatesRaw)) {
                                $fail("mail_file must return an array: {$templatesPath}");
                            } else {
                                $templates = [];
                                foreach ($templatesRaw as $id => $entry) {
                                    if (!is_string($id)) {
                                        continue;
                                    }
                                    $idLen = strlen($id);
                                    if ($idLen < 1 || $idLen > 100) {
                                        continue;
                                    }
                                    if (!(bool)preg_match('/^[a-z0-9_-]+$/', $id)) {
                                                continue;
                                    }
                                    $subject = is_array($entry) ? ($entry['subject'] ?? null) : null;
                                    $body = is_array($entry) ? ($entry['body'] ?? null) : null;
                                    if (!is_string($subject) || trim($subject) === '') {
                                        continue;
                                    }
                                    if (!is_string($body) || trim($body) === '') {
                                        continue;
                                    }
                                    $templates[$id] = true;
                                }

                                $missingIds = [];
                                foreach ($recipientIds as $id) {
                                    if (!isset($templates[$id])) {
                                        $missingIds[] = $id;
                                    }
                                }

                                if ($missingIds !== []) {
                                    $fail("recipient IDs missing in {$messagesFile}: " . implode(', ', $missingIds));
                                } else {
                                    $ok("recipient IDs resolved in {$messagesFile}.");
                                }
                            }
                        } catch (Throwable $e) {
                            $fail("mail_file load failed: " . $e->getMessage());
                        }
                    }
                }
            }
        }

        $mailFrom = trim((string)($cfg['mail_from'] ?? ''));
        if ($mailFrom === '') {
            $fail('mail_from is empty.');
        } elseif ($looksPlaceholder($mailFrom)) {
            $fail('mail_from contains placeholder/local host value.');
        } else {
            $ok('mail_from is set.');
        }

        $sendmailPath = trim((string)($cfg['sendmail_path'] ?? '/usr/sbin/sendmail'));
        if ($sendmailPath === '') {
            $fail('sendmail_path is empty.');
        } elseif (!is_file($sendmailPath)) {
            $fail("sendmail_path not found: {$sendmailPath}");
        } elseif (!is_executable($sendmailPath)) {
            $fail("sendmail_path is not executable: {$sendmailPath}");
        } else {
            $ok("sendmail_path executable: {$sendmailPath}");
        }

        $logMode = strtolower(trim((string)($cfg['log_mode'] ?? 'both')));
        $allowedLogModes = ['none', 'syslog', 'file', 'both'];
        if (!in_array($logMode, $allowedLogModes, true)) {
            $fail("log_mode must be one of: none, syslog, file, both (got: {$logMode})");
        } else {
            if ($logMode === 'none') {
                $warn('log_mode=none: logging disabled (not recommended for production).');
            } else {
                $ok("log_mode set to {$logMode}.");
            }

            if ($logMode === 'file' || $logMode === 'both') {
                $logFile = trim((string)($cfg['log_file'] ?? ''));
                if ($logFile === '') {
                    $logFile = $stateDir . '/' . $logFileName;
                }
                $logDir = dirname($logFile);
                if (is_dir($logDir) && is_writable($logDir)) {
                    $ok("log target directory writable: {$logDir}");
                } elseif (!is_dir($logDir)) {
                    $warn("log target directory does not exist yet (will be created on demand): {$logDir}");
                } else {
                    $warn("log target directory exists but is not writable for current user: {$logDir}");
                }
            }
        }

        $rateLimitDir = $cfg['rate_limit_dir'] ?? null;
        if (!is_string($rateLimitDir) || trim($rateLimitDir) === '') {
            $rateLimitDir = $stateDir . '/ratelimit';
        }
        $rateLimitDir = rtrim((string)$rateLimitDir, '/');
        if ($rateLimitDir === '') {
            $rateLimitDir = $stateDir . '/ratelimit';
        }

        if (!empty($cfg['rate_limit_enabled'])) {
            if (is_dir($rateLimitDir) && is_writable($rateLimitDir)) {
                $ok("rate_limit_dir writable: {$rateLimitDir}");
            } elseif (!is_dir($rateLimitDir)) {
                $warn("rate_limit_dir does not exist yet (will be created on demand): {$rateLimitDir}");
            } else {
                $warn("rate_limit_dir exists but is not writable for current user: {$rateLimitDir}");
            }
        }
    }

    if (!$cfg) {
        if (is_file($libPath) && is_readable($libPath)) {
            $ok("Found {$libFileName}: {$libPath}");
        } else {
            $fail("Missing/unreadable {$libFileName}: {$libPath}");
        }
    }

    if (is_string($webUser) && $webUser !== '') {
        $ok("Web user permission check requested for: {$webUser}");

        if (!function_exists('posix_getpwnam')) {
            $warn('POSIX functions unavailable: cannot validate --web-user permissions in this PHP build.');
        } else {
            $pw = posix_getpwnam($webUser);
            if (!is_array($pw)) {
                $fail("Web user not found: {$webUser}");
            } else {
                $uid = (int)$pw['uid'];
                $primaryGid = (int)$pw['gid'];
                $gids = [];
                if ($primaryGid >= 0) {
                    $gids[$primaryGid] = true;
                }

                if (function_exists('posix_getgrall')) {
                    $allGroups = posix_getgrall();
                    if (is_array($allGroups)) {
                        foreach ($allGroups as $group) {
                            $gid = (int)($group['gid'] ?? -1);
                            $members = $group['members'] ?? [];
                            if ($gid >= 0 && is_array($members) && in_array($webUser, $members, true)) {
                                $gids[$gid] = true;
                            }
                        }
                    }
                } else {
                    $warn('posix_getgrall unavailable: supplementary groups not evaluated for --web-user.');
                }

                $gidList = array_keys($gids);
                $ok("Resolved web user {$webUser} (uid={$uid}, gids=" . implode(',', $gidList) . ').');

                $hasPerm = static function (string $path, int $uid, array $gids, int $needBits): ?bool {
                    if (!file_exists($path)) {
                        return null;
                    }
                    $perms = fileperms($path);
                    $owner = fileowner($path);
                    $group = filegroup($path);
                    if ($perms === false || $owner === false || $group === false) {
                        return false;
                    }

                    $mode = $perms & 0777;
                    $owner = (int)$owner;
                    $group = (int)$group;
                    $gids = array_values(array_map('intval', $gids));

                    if ($uid === $owner) {
                        $granted = ($mode >> 6) & 0x7;
                    } elseif (in_array($group, $gids, true)) {
                        $granted = ($mode >> 3) & 0x7;
                    } else {
                        $granted = $mode & 0x7;
                    }

                    return (($granted & $needBits) === $needBits);
                };

                $requirePathPerm = static function (string $path, int $needBits, string $label, bool $failOnMissing) use ($uid, $gidList, $hasPerm, $ok, $warn, $fail): bool {
                    $res = $hasPerm($path, $uid, $gidList, $needBits);
                    if ($res === null) {
                        if ($failOnMissing) {
                            $fail("Missing path for web-user check ({$label}): {$path}");
                            return false;
                        }
                        $warn("Path not present yet ({$label}): {$path}");
                        return false;
                    }
                    if ($res === true) {
                        $ok("{$label} permission looks sufficient for {$path}");
                        return true;
                    }
                    $fail("{$label} permission appears insufficient for {$path}");
                    return false;
                };

                $stateDirOkRx = $requirePathPerm($stateDir, 0x5, 'state dir (r+x)', true);
                $stateDirOkWx = $requirePathPerm($stateDir, 0x3, 'state dir (w+x)', true);

                $requirePathPerm($configPath, 0x4, 'totmann.inc.php (read)', true);
                $requirePathPerm($libPath, 0x4, "{$libFileName} (read)", true);

                $stateJsonPath = $stateDir . '/' . $stateFileName;
                $lockPath = $stateDir . '/' . $lockFileName;

                $stateJsonPresent = file_exists($stateJsonPath);
                if ($stateJsonPresent) {
                    $requirePathPerm($stateJsonPath, 0x4, "{$stateFileName} (read)", true);
                    if ($stateDirOkWx) {
                        $ok("{$stateFileName} update path likely works (write via tmp+rename in state dir).");
                    } else {
                        $fail("{$stateFileName} update path likely blocked because state dir lacks w+x for web user.");
                    }
                } else {
                    $warn("{$stateFileName} does not exist yet (expected before first initialise).");
                }

                $lockPresent = file_exists($lockPath);
                if ($lockPresent) {
                    $requirePathPerm($lockPath, 0x6, "{$lockFileName} (read+write for c+)", true);
                } else {
                    if ($stateDirOkWx) {
                        $ok("{$lockFileName} missing is acceptable; web user can likely create it (state dir has w+x).");
                    } else {
                        $fail("{$lockFileName} missing and state dir lacks w+x, so web user likely cannot create lock file.");
                    }
                }

                if (!$stateDirOkRx) {
                    $fail('Without state dir r+x, web endpoint traversal/read will fail for web user.');
                }
            }
        }
    }

    echo "Summary: {$okCount} OK, {$warnCount} WARN, {$failCount} FAIL\n";

    if ($failCount > 0) {
        echo "Result: NOT READY FOR GOLIVE\n";
        return 2;
    }
    if ($warnCount > 0) {
        echo "Result: READY WITH WARNINGS\n";
        return 1;
    }
    echo "Result: READY\n";
    return 0;
}

function dm_state_make_initial(array $cfg, int $now, int $checkInterval, int $confirmWindow): array
{
    $token = dm_make_token($cfg);
    $timing = dm_cycle_window($now, $checkInterval, $confirmWindow);
    $state = [
    'version' => 1,
    'created_at' => $now,
    'last_tick_at' => $now,
    'cycle_start_at' => $timing['cycle_start_at'],
    'last_confirm_at' => 0,
    'missed_cycles' => 0,
    'missed_cycle_deadline' => null,
    'token' => $token,
    'next_check_at' => $timing['next_check_at'],
    'deadline_at' => $timing['deadline_at'],
    'next_reminder_at' => $timing['next_reminder_at'],
    'escalated_sent_at' => null,
    ];
    dm_state_reset_ack($state);
    return $state;
}

function dm_state_start_cycle(array $cfg, array &$state, int $now, int $checkInterval, int $confirmWindow): array
{
    $token = dm_make_token($cfg);
    return dm_state_apply_cycle($state, $now, $checkInterval, $confirmWindow, $token);
}
