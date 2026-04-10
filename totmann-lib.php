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
 * Validate a runtime directory name from config.
 * Basename only, no traversal, no control chars.
 */
function dm_runtime_dir_name(array $cfg, string $key): string
{
    return dm_runtime_file_name($cfg, $key);
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
 * Canonical runtime localisation directory path.
 * Uses configurable `l18n_dir_name`.
 */
function dm_l18n_dir(array $cfg): string
{
    return dm_path($cfg, dm_runtime_dir_name($cfg, 'l18n_dir_name'));
}

/**
 * Extract the runtime subtree from the shared state root.
 */
function dm_state_runtime(array $root): array
{
    $runtime = $root['runtime'] ?? [];
    return is_array($runtime) ? $runtime : [];
}

/**
 * Replace the runtime subtree inside the shared state root.
 */
function dm_state_with_runtime(array $root, array $runtime): array
{
    $root['runtime'] = $runtime;
    return $root;
}

/**
 * Extract the download subtree from the shared state root.
 */
function dm_state_downloads(array $root): array
{
    $downloads = $root['downloads'] ?? [];
    return is_array($downloads) ? $downloads : [];
}

/**
 * Replace the download subtree inside the shared state root.
 */
function dm_state_with_downloads(array $root, array $downloads): array
{
    $root['downloads'] = $downloads;
    return $root;
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
    $state['escalate_ack_recipients'] = [];
    $state['escalate_ack_at'] = null;
    $state['escalate_ack_sent_count'] = 0;
    $state['escalate_ack_next_at'] = null;
}

/**
 * Clear escalation + ACK tracking fields.
 */
function dm_state_clear_escalation(array &$state): void
{
    $state['escalation_event_at'] = null;
    $state['escalation_delivery'] = [];
    $state['escalated_sent_at'] = null;
    dm_state_reset_ack($state);
}

/**
 * Create the default per-recipient escalation delivery state.
 *
 * @return array{initial_sent_at: int|null, last_error: string|null, ack_remind_sent_count: int, ack_next_at: int|null}
 */
function dm_state_escalation_delivery_default(): array
{
    return [
        'initial_sent_at' => null,
        'last_error' => null,
        'ack_remind_sent_count' => 0,
        'ack_next_at' => null,
    ];
}

/**
 * Refresh the legacy summary ACK fields from per-recipient delivery state.
 */
function dm_state_refresh_ack_summary(array &$state): void
{
    $deliveryMap = $state['escalation_delivery'] ?? [];
    if (!is_array($deliveryMap) || $deliveryMap === []) {
        $state['escalate_ack_sent_count'] = 0;
        $state['escalate_ack_next_at'] = null;
        return;
    }

    $totalSent = 0;
    $nextAt = null;
    foreach ($deliveryMap as $delivery) {
        if (!is_array($delivery)) {
            continue;
        }
        $totalSent += max(0, (int)($delivery['ack_remind_sent_count'] ?? 0));
        $candidate = (int)($delivery['ack_next_at'] ?? 0);
        if ($candidate > 0 && ($nextAt === null || $candidate < $nextAt)) {
            $nextAt = $candidate;
        }
    }

    $state['escalate_ack_sent_count'] = $totalSent;
    $state['escalate_ack_next_at'] = $nextAt;
}

/**
 * Resolve the mandatory operator-alert interval from config.
 *
 * Public operator input is intentionally restricted to whole hours in the
 * range 1..24. Missing or invalid values fall back to 2 hours.
 *
 * @return array{hours: int, used_fallback: bool}
 */
function dm_operator_alert_interval_meta(array $cfg): array
{
    $fallbackHours = 2;
    $raw = $cfg['operator_alert_interval_hours'] ?? null;

    if (is_int($raw)) {
        $hours = $raw;
    } elseif (is_string($raw) && preg_match('/^\d+$/', trim($raw))) {
        $hours = (int)trim($raw);
    } else {
        return ['hours' => $fallbackHours, 'used_fallback' => true];
    }

    if ($hours < 1 || $hours > 24) {
        return ['hours' => $fallbackHours, 'used_fallback' => true];
    }

    return ['hours' => $hours, 'used_fallback' => false];
}

/**
 * Human-readable label for one internal operator-alert type.
 */
function dm_operator_alert_label(string $type): string
{
    return match ($type) {
        'recipient_skipped' => 'Recipient skipped',
        'config_error' => 'Configuration error',
        'runtime_error' => 'Runtime error',
        'delivery_error' => 'Mail delivery error',
        default => 'Operator warning',
    };
}

/**
 * Suggest the next operator action for a detected problem.
 */
function dm_operator_alert_hint(string $type, string $message): string
{
    if (str_contains($message, 'single_use_notice')) {
        return 'Open totmann-recipients.php, find the referenced message key in $messages, and add a non-empty single_use_notice because that message is used with field 5.';
    }
    if (str_contains($message, 'unknown message key')) {
        return 'Open totmann-recipients.php and check field 3 in the affected recipient row. It must point to an existing key in $messages.';
    }
    if (str_contains($message, 'unknown file alias')) {
        return 'Open totmann-recipients.php and compare the affected alias with $files plus the field-4/field-5 lists in the affected recipient row.';
    }
    if (str_contains($message, 'invalid mailbox')) {
        return 'Open totmann-recipients.php and correct field 2. Supported forms are recipient@example.com, <recipient@example.com>, or Recipient Name <recipient@example.com>.';
    }
    if (str_contains($message, 'duplicate recipient mailbox')) {
        return 'Open totmann-recipients.php and keep each real mailbox only once. One recipient row must represent exactly one mailbox.';
    }
    if (str_contains($message, 'invalid normal file alias list') || str_contains($message, 'invalid single-use file alias list')) {
        return 'Open totmann-recipients.php and make sure field 4 and field 5 are flat alias lists such as [\'letter\'] or [\'photos\'].';
    }
    if (str_contains($message, 'sendmail')) {
        return 'Check sendmail_path in totmann.inc.php, verify the binary exists and is executable, and run php totmann-tick.php check in your state directory.';
    }
    if (str_contains($message, 'to_self')) {
        return 'Check to_self in totmann.inc.php. Each entry must contain exactly one valid mailbox string.';
    }
    if (str_contains($message, 'recipients_file')) {
        return 'Open totmann-recipients.php, fix the referenced row or top-level structure, and rerun php totmann-tick.php check.';
    }
    if ($type === 'delivery_error') {
        return 'Check the affected recipient mailbox plus your local sendmail setup, then rerun php totmann-tick.php check and inspect totmann.log.';
    }
    return 'Run php totmann-tick.php check in your state directory, inspect totmann.log, and compare the affected values in totmann.inc.php and totmann-recipients.php.';
}

/**
 * Stable fingerprint for one operator-facing problem.
 */
function dm_operator_alert_fingerprint(string $type, string $message): string
{
    $normalised = strtolower(trim((string)preg_replace('/\s+/', ' ', $message)));
    return substr(hash('sha256', $type . "\n" . $normalised), 0, 24);
}

/**
 * Update operator-alert state and decide whether another warning mail is due.
 *
 * @return array<string, int|string>|null
 */
function dm_operator_alert_consider(array $cfg, array &$state, int $now, string $type, string $message): ?array
{
    $meta = dm_operator_alert_interval_meta($cfg);
    $intervalSeconds = (int)$meta['hours'] * 3600;
    $label = dm_operator_alert_label($type);
    $hint = dm_operator_alert_hint($type, $message);
    $fingerprint = dm_operator_alert_fingerprint($type, $message);

    $alerts = $state['operator_alerts'] ?? [];
    if (!is_array($alerts)) {
        $alerts = [];
    }

    $existing = $alerts[$fingerprint] ?? [];
    if (!is_array($existing)) {
        $existing = [];
    }

    $firstSeenAt = max(0, (int)($existing['first_seen_at'] ?? 0));
    if ($firstSeenAt <= 0) {
        $firstSeenAt = $now;
    }
    $lastSentAt = max(0, (int)($existing['last_sent_at'] ?? 0));
    $count = max(0, (int)($existing['count'] ?? 0)) + 1;

    $alerts[$fingerprint] = [
        'type' => $type,
        'label' => $label,
        'message' => $message,
        'hint' => $hint,
        'first_seen_at' => $firstSeenAt,
        'last_seen_at' => $now,
        'last_sent_at' => $lastSentAt,
        'count' => $count,
    ];
    $state['operator_alerts'] = $alerts;

    if ($lastSentAt > 0 && ($now - $lastSentAt) < $intervalSeconds) {
        return null;
    }

    return [
        'type' => $type,
        'label' => $label,
        'message' => $message,
        'hint' => $hint,
        'fingerprint' => $fingerprint,
        'first_seen_at' => $firstSeenAt,
        'last_seen_at' => $now,
        'count' => $count,
    ];
}

/**
 * Mark one operator alert as successfully mailed.
 */
function dm_operator_alert_mark_sent(array &$state, string $fingerprint, int $now): void
{
    $alerts = $state['operator_alerts'] ?? [];
    if (!is_array($alerts)) {
        $alerts = [];
    }
    $entry = $alerts[$fingerprint] ?? null;
    if (!is_array($entry)) {
        return;
    }
    $entry['last_sent_at'] = $now;
    $alerts[$fingerprint] = $entry;
    $state['operator_alerts'] = $alerts;
}

/**
 * Render the fixed operator-warning mail.
 *
 * @param array{type: string, label: string, message: string, hint: string, fingerprint: string, first_seen_at: int, last_seen_at: int, count: int} $alert
 * @return array{subject: string, body: string}
 */
function dm_operator_alert_render_mail(array $cfg, array $alert): array
{
    $label = (string)$alert['label'];
    $subject = '[totmannschalter] Operator warning: ' . $label;
    $stateDir = dm_state_dir($cfg);

    $body = implode("\n", [
        'Totmannschalter detected an operator-facing problem and continued in best-effort mode where possible.',
        '',
        'Alert type: ' . $label,
        'Fingerprint: ' . (string)$alert['fingerprint'],
        'First seen: ' . dm_mail_dt($cfg, (int)$alert['first_seen_at']),
        'Last seen: ' . dm_mail_dt($cfg, (int)$alert['last_seen_at']),
        'Occurrences: ' . (string)$alert['count'],
        '',
        'Original problem:',
        (string)$alert['message'],
        '',
        'What to check next:',
        (string)$alert['hint'],
        '',
        'Recommended next steps:',
        '1. Change into your state directory: ' . $stateDir,
        '2. Run: php totmann-tick.php check',
        '3. Inspect totmann.log for matching lines.',
        '4. Compare the affected values in totmann.inc.php and totmann-recipients.php.',
        '5. If you still have the project docs at hand, read docs/Logs.md and docs/Troubleshooting.md.',
    ]) . "\n";

    return ['subject' => $subject, 'body' => $body];
}

/**
 * Send one operator-warning mail per to_self recipient.
 *
 * @param array{type: string, label: string, message: string, hint: string, fingerprint: string, first_seen_at: int, last_seen_at: int, count: int} $alert
 * @return array{sent: int, failed: int, errors: array<int, string>}
 */
function dm_operator_alert_send(array $cfg, array $alert): array
{
    $selfRecipients = dm_recipient_entries_runtime((array)($cfg['to_self'] ?? []));
    if ($selfRecipients === []) {
        return ['sent' => 0, 'failed' => 1, 'errors' => ['to_self does not contain any valid mailbox entry for operator warnings']];
    }

    $mail = dm_operator_alert_render_mail($cfg, $alert);
    $sent = 0;
    $failed = 0;
    $errors = [];

    foreach ($selfRecipients as $selfRecipient) {
        try {
            dm_send_mail($cfg, [$selfRecipient], $mail['subject'], $mail['body']);
            $sent++;
        } catch (Throwable $e) {
            $failed++;
            $errors[] = $selfRecipient . ': ' . $e->getMessage();
        }
    }

    return ['sent' => $sent, 'failed' => $failed, 'errors' => $errors];
}

/**
 * Handle one operator warning using an already loaded runtime state.
 */
function dm_operator_alert_handle(array $cfg, array &$state, int $now, string $type, string $message): void
{
    try {
        $alert = dm_operator_alert_consider($cfg, $state, $now, $type, $message);
        if ($alert === null) {
            return;
        }

        $result = dm_operator_alert_send($cfg, $alert);
        if ((int)$result['sent'] > 0) {
            dm_operator_alert_mark_sent($state, (string)$alert['fingerprint'], $now);
            dm_log($cfg, 'Operator alert sent for ' . (string)$alert['type'] . ' (fingerprint=' . (string)$alert['fingerprint'] . ', recipients=' . (int)$result['sent'] . ').');
        }
        foreach ($result['errors'] as $error) {
            dm_log($cfg, 'Operator alert delivery failed for ' . (string)$alert['type'] . ' (fingerprint=' . (string)$alert['fingerprint'] . '): ' . $error);
        }
    } catch (Throwable $e) {
        dm_log($cfg, 'Operator alert handling failed: ' . $e->getMessage());
    }
}

/**
 * Best-effort operator warning helper for paths where no runtime state is loaded yet.
 */
function dm_operator_alert_handle_from_statefile(array $cfg, string $type, string $message): void
{
    $lockHandle = null;

    try {
        $stateFile = dm_state_file($cfg);
        $lockFile = dm_lock_file($cfg);
        $lockHandle = dm_lock_open($lockFile);

        $now = dm_now();
        $stateRoot = dm_state_load($stateFile);
        $state = dm_state_runtime($stateRoot);
        dm_operator_alert_handle($cfg, $state, $now, $type, $message);

        $stateRoot = dm_state_with_runtime($stateRoot, $state);
        $stateRoot = dm_state_with_downloads($stateRoot, dm_state_downloads($stateRoot));
        dm_state_save($stateFile, $stateRoot);
    } catch (Throwable $e) {
        dm_log($cfg, 'Operator alert handling failed: ' . $e->getMessage());
    } finally {
        if (is_resource($lockHandle)) {
            fclose($lockHandle);
        }
    }
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
 * Create a signed download token bound to recipient ID, link ID, escalation event, expiry, and nonce.
 */
function dm_download_token_make(array $cfg, string $recipientId, string $linkId, int $eventAt, int $expiresAt): array
{
    if (!dm_mail_id_valid($recipientId)) {
        throw new RuntimeException("Invalid recipient download ID: {$recipientId}");
    }
    if (!dm_mail_id_valid($linkId)) {
        throw new RuntimeException("Invalid download link ID: {$linkId}");
    }
    if ($eventAt < 1) {
        throw new RuntimeException('Invalid download event timestamp');
    }
    if ($expiresAt < 1) {
        throw new RuntimeException('Invalid download expiry timestamp');
    }

    $nonce = bin2hex(random_bytes(16));
    $payload = $recipientId . "\n" . $linkId . "\n" . $eventAt . "\n" . $expiresAt . "\n" . $nonce;
    $sig = hash_hmac('sha256', $payload, dm_secret_bin($cfg));

    return [
    'rid' => $recipientId,
    'lid' => $linkId,
    'evt' => $eventAt,
    'exp' => $expiresAt,
    'n' => $nonce,
    'sig' => $sig,
    ];
}

/**
 * Verify download token format and signature.
 */
function dm_download_token_valid(array $cfg, string $recipientId, string $linkId, int $eventAt, int $expiresAt, string $nonce, string $sig): bool
{
    if (!dm_mail_id_valid($recipientId) || !dm_mail_id_valid($linkId)) {
        return false;
    }
    if ($eventAt < 1 || $expiresAt < 1) {
        return false;
    }
    if (!preg_match('/^[a-f0-9]{32}$/', $nonce) || !preg_match('/^[a-f0-9]{64}$/', $sig)) {
        return false;
    }

    $payload = $recipientId . "\n" . $linkId . "\n" . $eventAt . "\n" . $expiresAt . "\n" . $nonce;
    $expected = hash_hmac('sha256', $payload, dm_secret_bin($cfg));
    return hash_equals($expected, $sig);
}

/**
 * Create the public download URL for a token.
 */
function dm_download_url(array $cfg, array $token): string
{
    $base = rtrim(dm_endpoint_url($cfg), '?');
    $q = http_build_query(['a' => 'download'] + $token);
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
 * Validate relative download paths inside download_base_dir.
 */
function dm_download_rel_path_valid(string $path): bool
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '') {
        return false;
    }
    if (str_starts_with($path, '/')) {
        return false;
    }
    if (str_contains($path, '..')) {
        return false;
    }
    if ((bool)preg_match('/[[:cntrl:]]/', $path)) {
        return false;
    }
    return true;
}

/**
 * Extract the actual mailbox address from a supported mailbox field.
 *
 * Supported forms:
 * - recipient@example.com
 * - <recipient@example.com>
 * - Recipient Name <recipient@example.com>
 */
function dm_mailbox_extract_address(string $mailbox): string
{
    $mailbox = trim(str_replace(["\r", "\n"], '', $mailbox));
    if ($mailbox === '') {
        return '';
    }
    if (preg_match('/^<([^>]+)>$/', $mailbox, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/^(.*)<([^>]+)>$/', $mailbox, $m)) {
        return trim($m[2]);
    }
    return $mailbox;
}

/**
 * Normalise one mailbox address for stable internal keys.
 */
function dm_mailbox_normalize_address(string $mailbox): string
{
    $addr = dm_mailbox_extract_address($mailbox);
    if ($addr === '') {
        return '';
    }

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

    return strtolower(trim($addr));
}

/**
 * Validate one mailbox field for one escalation recipient.
 */
function dm_mailbox_field_valid(string $mailbox): bool
{
    $addr = dm_mailbox_normalize_address($mailbox);
    if ($addr === '') {
        return false;
    }

    $isValid = (str_contains($addr, '@') && filter_var($addr, FILTER_VALIDATE_EMAIL) !== false);
    if (!$isValid && str_contains($addr, '@')) {
        $isValid = (bool)preg_match('/^[^\s@<>",;:]+@[^\s@<>",;:]+\.[^\s@<>",;:]+$/', $addr);
    }
    return $isValid;
}

/**
 * Derive a stable internal recipient key from the normalised mailbox address.
 */
function dm_recipient_runtime_key(string $mailbox): string
{
    $addr = dm_mailbox_normalize_address($mailbox);
    if ($addr === '') {
        throw new RuntimeException('Cannot derive recipient key from empty mailbox');
    }
    return 'r_' . substr(hash('sha256', $addr), 0, 32);
}

/**
 * Derive a stable internal download key from one recipient key and one file alias.
 */
function dm_download_runtime_key(string $recipientKey, string $alias): string
{
    if (!dm_mail_id_valid($recipientKey) || !dm_mail_id_valid($alias)) {
        throw new RuntimeException("Cannot derive download key from recipient '{$recipientKey}' and alias '{$alias}'");
    }
    return 'd_' . substr(hash('sha256', $recipientKey . "\n" . $alias), 0, 32);
}

/**
 * Read the global download validity period in days.
 */
function dm_download_valid_days(array $cfg): int
{
    $raw = $cfg['download_valid_days'] ?? 180;
    if (is_int($raw)) {
        $days = $raw;
    } elseif (is_string($raw) && preg_match('/^\d+$/', trim($raw))) {
        $days = (int)trim($raw);
    } else {
        throw new RuntimeException('Invalid download_valid_days: expected positive integer');
    }
    if ($days < 1) {
        throw new RuntimeException('Invalid download_valid_days: must be >= 1');
    }
    return $days;
}

/**
 * Parse the unified recipient file and optionally skip invalid recipient rows.
 *
 * Top-level `files`/`messages`/`recipients` structure errors remain fatal because
 * the file is not usable at all in that state. Recipient-row errors can be
 * collected so runtime delivery can skip only the broken recipients.
 *
 * @return array{recipients: array<int, array<string, mixed>>, errors: array<int, string>}
 */
function dm_recipients_parse(array $cfg, bool $skipInvalidRecipients): array
{
    $fileName = dm_runtime_file_name($cfg, 'recipients_file');
    $path = dm_path($cfg, $fileName);

    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException("recipients_file missing/unreadable: {$path}");
    }

    $data = require $path;
    if (!is_array($data)) {
        throw new RuntimeException("recipients_file must return an array: {$path}");
    }

    $files = $data['files'] ?? null;
    $messages = $data['messages'] ?? null;
    $rows = $data['recipients'] ?? null;
    if (!is_array($files) || !is_array($messages) || !is_array($rows)) {
        throw new RuntimeException('recipients_file must return files/messages/recipients arrays');
    }

    $errors = [];
    $cleanFiles = [];
    foreach ($files as $alias => $file) {
        if (!is_string($alias) || !dm_mail_id_valid($alias)) {
            $message = "recipients_file contains invalid file alias: {$alias}";
            if (!$skipInvalidRecipients) {
                throw new RuntimeException($message);
            }
            $errors[] = $message;
            continue;
        }
        $file = trim((string)$file);
        if (!dm_download_rel_path_valid($file)) {
            $message = "recipients_file contains invalid file path for alias {$alias}: {$file}";
            if (!$skipInvalidRecipients) {
                throw new RuntimeException($message);
            }
            $errors[] = $message;
            continue;
        }
        $cleanFiles[$alias] = str_replace('\\', '/', $file);
    }

    $cleanMessages = [];
    foreach ($messages as $messageKey => $entry) {
        if (!is_string($messageKey) || !dm_mail_id_valid($messageKey)) {
            $message = "recipients_file contains invalid message key: {$messageKey}";
            if (!$skipInvalidRecipients) {
                throw new RuntimeException($message);
            }
            $errors[] = $message;
            continue;
        }
        if (!is_array($entry)) {
            $message = "recipients_file contains invalid message entry for key {$messageKey}";
            if (!$skipInvalidRecipients) {
                throw new RuntimeException($message);
            }
            $errors[] = $message;
            continue;
        }
        $subject = $entry['subject'] ?? null;
        $body = $entry['body'] ?? null;
        $singleUseNotice = $entry['single_use_notice'] ?? '';
        if (!is_string($subject) || trim($subject) === '' || !is_string($body) || trim($body) === '') {
            $message = "recipients_file message {$messageKey} must contain non-empty subject and body";
            if (!$skipInvalidRecipients) {
                throw new RuntimeException($message);
            }
            $errors[] = $message;
            continue;
        }
        if (!is_string($singleUseNotice)) {
            $message = "recipients_file message {$messageKey} must use a string for single_use_notice when present";
            if (!$skipInvalidRecipients) {
                throw new RuntimeException($message);
            }
            $errors[] = $message;
            continue;
        }
        if (str_contains($body, '{DOWNLOAD_NOTICE}')) {
            $message = "recipients_file message {$messageKey} still contains removed placeholder {DOWNLOAD_NOTICE}";
            if (!$skipInvalidRecipients) {
                throw new RuntimeException($message);
            }
            $errors[] = $message;
            continue;
        }
        $cleanMessages[$messageKey] = [
            'subject' => $subject,
            'body' => $body,
            'single_use_notice' => trim($singleUseNotice),
        ];
    }

    $out = [];
    $seenRecipientKeys = [];
    foreach ($rows as $index => $row) {
        try {
            if (!is_array($row) || array_values($row) !== $row) {
                throw new RuntimeException("recipients_file recipient entry #{$index} must be a flat numeric array");
            }
            if (count($row) < 3) {
                throw new RuntimeException("recipients_file recipient entry #{$index} must contain at least 3 values");
            }

            $name = trim((string)($row[0] ?? ''));
            $address = trim(str_replace(["\r", "\n"], '', (string)($row[1] ?? '')));
            $messageKey = trim((string)($row[2] ?? ''));
            if ($name === '') {
                throw new RuntimeException("recipients_file recipient entry #{$index} contains an empty personal name");
            }
            if ($address === '' || !dm_mailbox_field_valid($address)) {
                throw new RuntimeException("recipients_file contains invalid mailbox in recipient entry #{$index}: {$address}");
            }
            if ($messageKey === '') {
                throw new RuntimeException("recipients_file recipient entry #{$index} must reference a message key in field 3");
            }
            if (!isset($cleanMessages[$messageKey])) {
                throw new RuntimeException("recipients_file references unknown message key '{$messageKey}' in recipient entry #{$index}");
            }

            $recipientKey = dm_recipient_runtime_key($address);
            if (isset($seenRecipientKeys[$recipientKey])) {
                throw new RuntimeException("recipients_file contains duplicate recipient mailbox in entry #{$index}: {$address}");
            }

            $normalAliases = $row[3] ?? [];
            $singleUseAliases = $row[4] ?? [];
            if (!is_array($normalAliases) || array_values($normalAliases) !== $normalAliases) {
                throw new RuntimeException("recipients_file contains invalid normal file alias list for {$address}");
            }
            if (!is_array($singleUseAliases) || array_values($singleUseAliases) !== $singleUseAliases) {
                throw new RuntimeException("recipients_file contains invalid single-use file alias list for {$address}");
            }

            $downloads = [];
            $seenAliases = [];
            foreach ($normalAliases as $alias) {
                $alias = trim((string)$alias);
                if (!isset($cleanFiles[$alias])) {
                    $message = "recipients_file references unknown file alias '{$alias}' for {$address}";
                    if (!$skipInvalidRecipients) {
                        throw new RuntimeException($message);
                    }
                    $errors[] = $message;
                    continue;
                }
                if (isset($seenAliases[$alias])) {
                    $message = "recipients_file contains duplicate file alias '{$alias}' for {$address}";
                    if (!$skipInvalidRecipients) {
                        throw new RuntimeException($message);
                    }
                    $errors[] = $message;
                    continue;
                }
                $downloads[] = [
                    'alias' => $alias,
                    'download_key' => dm_download_runtime_key($recipientKey, $alias),
                    'file' => $cleanFiles[$alias],
                    'single_use' => false,
                ];
                $seenAliases[$alias] = true;
            }
            foreach ($singleUseAliases as $alias) {
                $alias = trim((string)$alias);
                if (!isset($cleanFiles[$alias])) {
                    $message = "recipients_file references unknown single-use file alias '{$alias}' for {$address}";
                    if (!$skipInvalidRecipients) {
                        throw new RuntimeException($message);
                    }
                    $errors[] = $message;
                    continue;
                }
                if (isset($seenAliases[$alias])) {
                    $message = "recipients_file contains file alias '{$alias}' in both normal and single-use lists for {$address}";
                    if (!$skipInvalidRecipients) {
                        throw new RuntimeException($message);
                    }
                    $errors[] = $message;
                    continue;
                }
                $downloads[] = [
                    'alias' => $alias,
                    'download_key' => dm_download_runtime_key($recipientKey, $alias),
                    'file' => $cleanFiles[$alias],
                    'single_use' => true,
                ];
                $seenAliases[$alias] = true;
            }

            if ($singleUseAliases !== [] && trim($cleanMessages[$messageKey]['single_use_notice']) === '') {
                throw new RuntimeException("recipients_file message {$messageKey} must define single_use_notice for recipient {$address} because field 5 is used");
            }

            $out[] = [
                'name' => $name,
                'address' => $address,
                'recipient_key' => $recipientKey,
                'message_key' => $messageKey,
                'message' => $cleanMessages[$messageKey],
                'downloads' => $downloads,
            ];
            $seenRecipientKeys[$recipientKey] = true;
        } catch (Throwable $e) {
            if (!$skipInvalidRecipients) {
                throw $e;
            }
            $errors[] = $e->getMessage();
        }
    }

    if ($out === []) {
        throw new RuntimeException('recipients_file contains no valid recipient entries');
    }

    return ['recipients' => $out, 'errors' => $errors];
}

/**
 * Load the unified recipient file with strict validation.
 */
function dm_recipients_load(array $cfg): array
{
    $parsed = dm_recipients_parse($cfg, false);
    return $parsed['recipients'];
}

/**
 * Parse escalation recipients for runtime delivery from the unified recipient file.
 */
function dm_escalation_recipients_runtime(array $cfg, array &$errors = []): array
{
    $parsed = dm_recipients_parse($cfg, true);
    $errors = $parsed['errors'];
    return $parsed['recipients'];
}

/**
 * Validate a recipient list where each entry is exactly one mailbox string.
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

        $part = $rawEntry;
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

    return $out;
}

/**
 * Resolve escalation subject/body for one recipient.
 */
function dm_escalate_message_for_recipient(array $recipient): array
{
    $message = $recipient['message'] ?? null;
    if (!is_array($message)) {
        throw new RuntimeException('Recipient message is missing');
    }

    $subject = $message['subject'] ?? null;
    $body = $message['body'] ?? null;
    if (!is_string($subject) || trim($subject) === '' || !is_string($body) || trim($body) === '') {
        throw new RuntimeException('Recipient message is incomplete');
    }

    $singleUseNotice = $message['single_use_notice'] ?? '';
    if (!is_string($singleUseNotice)) {
        throw new RuntimeException('Recipient message single_use_notice is invalid');
    }

    return ['subject' => $subject, 'body' => $body, 'single_use_notice' => trim($singleUseNotice)];
}

/**
 * Build concrete download link data for one recipient.
 *
 * `eventAt` identifies the current escalation event.
 * It stays stable across ACK reminder mails, so `single_use=true` applies to the
 * whole escalation event rather than to one individual URL.
 */
function dm_download_links_for_recipient(array $cfg, array $recipient, int $eventAt, int $sentAt): array
{
    $mailId = (string)($recipient['recipient_key'] ?? '');
    $downloads = $recipient['downloads'] ?? [];
    if ($mailId === '' || !is_array($downloads) || $downloads === []) {
        return [];
    }

    $out = [];
    $expiresAfter = dm_download_valid_days($cfg) * 86400;
    foreach ($downloads as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $linkId = (string)($entry['download_key'] ?? '');
        $singleUse = !empty($entry['single_use']);
        if (!dm_mail_id_valid($mailId) || !dm_mail_id_valid($linkId) || $eventAt < 1 || $expiresAfter < 1) {
            continue;
        }
        $expiresAt = $eventAt + $expiresAfter;
        if ($sentAt > $expiresAt) {
            continue;
        }

        $token = dm_download_token_make($cfg, $mailId, $linkId, $eventAt, $expiresAt);
        $out[] = [
            'id' => $linkId,
            'alias' => (string)($entry['alias'] ?? ''),
            'url' => dm_download_url($cfg, $token),
            'expires_at' => $token['exp'],
            'event_at' => $token['evt'],
            'single_use' => $singleUse,
        ];
    }

    return $out;
}

/**
 * Render the optional ACK block used in escalation mail templates.
 */
function dm_render_ack_block(string $ackUrl, bool $ackEnabled): string
{
    if (!$ackEnabled || trim($ackUrl) === '') {
        return '';
    }

    return "Please click this link to acknowledge receipt:\n" . trim($ackUrl);
}

/**
 * Render download links as one optional plain-text block.
 *
 * Output is intentionally simple:
 * - one download => one block without heading
 * - multiple downloads => "X Downloads:" plus one blank line between blocks
 * - single-use warning text appears directly above the affected URL
 */
function dm_render_download_links_block(array $links, string $singleUseNotice = ''): string
{
    if ($links === []) {
        return '';
    }

    $blocks = [];
    $singleUseNotice = trim($singleUseNotice);
    foreach ($links as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $url = trim((string)($entry['url'] ?? ''));
        if ($url === '') {
            continue;
        }
        if (!empty($entry['single_use']) && $singleUseNotice !== '') {
            $blocks[] = $singleUseNotice . "\n" . $url;
            continue;
        }
        $blocks[] = $url;
    }

    if ($blocks === []) {
        return '';
    }
    if (count($blocks) === 1) {
        return $blocks[0];
    }

    return count($blocks) . " Downloads:\n\n" . implode("\n\n", $blocks);
}

/**
 * Render escalation text from placeholders.
 *
 * Supported placeholders:
 * - {LAST_CONFIRM_ISO}
 * - {CYCLE_START_ISO}
 * - {DEADLINE_ISO}
 * - {RECIPIENT_NAME}
 * - {ACK_BLOCK}
 * - {ACK_URL}
 * - {DOWNLOAD_LINKS}
 */
function dm_render_escalate_template(array $cfg, string $tpl, int $lastConfirm, int $cycleStart, int $deadline, string $recipientName, string $ackUrl, bool $ackEnabled, string $downloadLinks = '', string $ackBlock = ''): string
{
    if (!$ackEnabled) {
        $tpl = str_replace('{ACK_BLOCK}', '', $tpl);
        $tpl = str_replace('{ACK_URL}', '', $tpl);
    }

    return str_replace(
        ['{LAST_CONFIRM_ISO}', '{CYCLE_START_ISO}', '{DEADLINE_ISO}', '{RECIPIENT_NAME}', '{ACK_BLOCK}', '{ACK_URL}', '{DOWNLOAD_LINKS}'],
        [dm_mail_dt_or_never($cfg, $lastConfirm), dm_mail_dt($cfg, $cycleStart), dm_mail_dt($cfg, $deadline), $recipientName, $ackBlock, $ackUrl, $downloadLinks],
        $tpl
    );
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
 * Only the display name is RFC2047-encoded or quoted; address stays ASCII.
 * ASCII display names are always emitted as quoted strings so one mailbox
 * cannot look like a comma-separated recipient list in the final header.
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
            if (preg_match('/^[\x00-\x7F]*$/', $name)) {
                $name = '"' . addcslashes($name, "\\\"") . '"';
            } else {
                $name = dm_hdr_encode($name);
            }
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

        $toHeader[] = $raw;

        $addr = $raw;
        if (preg_match('/<([^>]+)>/', $raw, $m)) {
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
        if ($isValid) {
            $addArgv($addr);
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

    $namespace = trim((string)($cfg['rate_limit_namespace'] ?? 'web'));
    if ($namespace === '') {
        $namespace = 'web';
    }
    $dir = rtrim($dir, '/') . '/' . $namespace;

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
 * Download endpoint rate limit wrapper with dedicated defaults.
 *
 * Both web and download requests share one rate-limit root, but use separate
 * internal namespaces.
 */
function dm_download_rate_limit_check(array $cfg, string $ip, int $now): bool
{
    $derived = $cfg;
    $derived['rate_limit_enabled'] = !empty($cfg['download_rate_limit_enabled']);

    $baseDir = $cfg['rate_limit_dir'] ?? null;
    if (!is_string($baseDir) || trim($baseDir) === '') {
        $baseDir = dm_path($cfg, 'ratelimit');
    }

    $derived['rate_limit_dir'] = rtrim(trim((string)$baseDir), '/');
    $derived['rate_limit_namespace'] = 'download';
    $derived['rate_limit_max_requests'] = (int)($cfg['download_rate_limit_max_requests'] ?? 20);
    $derived['rate_limit_window_seconds'] = (int)($cfg['download_rate_limit_window_seconds'] ?? 60);
    return dm_rate_limit_check($derived, $ip, $now);
}

/**
 * Resolve and validate download base directory.
 */
function dm_download_base_dir(array $cfg): string
{
    $dir = rtrim((string)($cfg['download_base_dir'] ?? ''), '/');
    if ($dir === '') {
        throw new RuntimeException('Missing config key: download_base_dir');
    }
    if (!str_starts_with($dir, '/')) {
        throw new RuntimeException('download_base_dir must be an absolute path');
    }
    $real = realpath($dir);
    if ($real === false || !is_dir($real) || !is_readable($real)) {
        throw new RuntimeException("download_base_dir missing/unreadable: {$dir}");
    }
    return rtrim($real, '/');
}

/**
 * Resolve a configured relative download path against download_base_dir.
 */
function dm_download_resolve_file(array $cfg, string $relativePath): string
{
    if (!dm_download_rel_path_valid($relativePath)) {
        throw new RuntimeException("Invalid download file path: {$relativePath}");
    }

    $baseDir = dm_download_base_dir($cfg);
    $candidate = $baseDir . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    $real = realpath($candidate);
    if ($real === false || !is_file($real) || !is_readable($real)) {
        throw new RuntimeException("Download file missing/unreadable: {$relativePath}");
    }

    $basePrefix = $baseDir . '/';
    if (!str_starts_with($real, $basePrefix)) {
        throw new RuntimeException("Download file escapes download_base_dir: {$relativePath}");
    }

    return $real;
}

/**
 * Deterministic state key for one logical download in one escalation event.
 */
function dm_download_state_key(string $recipientId, string $linkId, int $eventAt): string
{
    return hash('sha256', $recipientId . "\n" . $linkId . "\n" . $eventAt);
}

/**
 * Remove expired leases from download state.
 */
function dm_download_state_cleanup(array &$state, int $now): void
{
    $entries = (array)($state['entries'] ?? []);
    $clean = [];
    foreach ($entries as $key => $entry) {
        if (!is_string($key) || !is_array($entry)) {
            continue;
        }
        $consumedAt = (int)($entry['consumed_at'] ?? 0);
        $leaseUntil = (int)($entry['lease_until'] ?? 0);
        $expiresAt = (int)($entry['expires_at'] ?? 0);
        if ($expiresAt > $now && ($consumedAt > 0 || $leaseUntil > $now)) {
            $clean[$key] = [
            'lease_until' => $leaseUntil,
            'consumed_at' => $consumedAt,
            'expires_at' => $expiresAt,
            ];
        }
    }
    $state['entries'] = $clean;
}

/**
 * Acquire a temporary lease for a one-time download token.
 */
function dm_download_state_acquire_lease(array &$state, string $key, int $now, int $leaseSeconds, bool $singleUse, int $expiresAt): string
{
    dm_download_state_cleanup($state, $now);
    if (!$singleUse) {
        return 'ready';
    }

    $entries = (array)($state['entries'] ?? []);
    $entry = (array)($entries[$key] ?? []);
    if ((int)($entry['consumed_at'] ?? 0) > 0) {
        return 'consumed';
    }
    if ((int)($entry['lease_until'] ?? 0) > $now) {
        return 'leased';
    }

    $entries[$key] = [
    'lease_until' => $now + max(1, $leaseSeconds),
    'consumed_at' => 0,
    'expires_at' => $expiresAt,
    ];
    $state['entries'] = $entries;
    return 'ready';
}

/**
 * Release a temporary lease after a failed transfer attempt.
 */
function dm_download_state_release_lease(array &$state, string $key): void
{
    $entries = (array)($state['entries'] ?? []);
    if (!isset($entries[$key]) || !is_array($entries[$key])) {
        return;
    }
    if ((int)($entries[$key]['consumed_at'] ?? 0) > 0) {
        return;
    }
    unset($entries[$key]);
    $state['entries'] = $entries;
}

/**
 * Mark a one-time download token as consumed after a completed transfer.
 */
function dm_download_state_mark_consumed(array &$state, string $key, int $now, bool $singleUse, int $expiresAt): void
{
    if (!$singleUse) {
        return;
    }

    $entries = (array)($state['entries'] ?? []);
    $entries[$key] = [
    'lease_until' => 0,
    'consumed_at' => $now,
    'expires_at' => $expiresAt,
    ];
    $state['entries'] = $entries;
}

/**
 * Find one configured recipient by internal recipient key.
 */
function dm_recipient_by_key(array $recipients, string $recipientKey): ?array
{
    foreach ($recipients as $recipient) {
        if (is_array($recipient) && (string)($recipient['recipient_key'] ?? '') === $recipientKey) {
            return $recipient;
        }
    }

    return null;
}

/**
 * Return the configured download definition for one recipient/download pair.
 */
function dm_download_definition_get(array $recipients, string $recipientKey, string $downloadKey): ?array
{
    $recipient = dm_recipient_by_key($recipients, $recipientKey);
    if (!is_array($recipient)) {
        return null;
    }

    $downloads = $recipient['downloads'] ?? [];
    if (!is_array($downloads)) {
        return null;
    }

    foreach ($downloads as $entry) {
        if (is_array($entry) && (string)($entry['download_key'] ?? '') === $downloadKey) {
            return $entry;
        }
    }
    return null;
}

/**
 * Load only the parts of recipients_file needed for download resolution.
 *
 * This path is intentionally tolerant: unrelated broken message or recipient rows must not
 * invalidate already issued download links for a valid recipient/download pair.
 *
 * @return array<int, array<string, mixed>>
 */
function dm_download_recipients_runtime(array $cfg): array
{
    $fileName = dm_runtime_file_name($cfg, 'recipients_file');
    $path = dm_path($cfg, $fileName);

    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException("recipients_file missing/unreadable: {$path}");
    }

    $data = require $path;
    if (!is_array($data)) {
        throw new RuntimeException("recipients_file must return an array: {$path}");
    }

    $files = $data['files'] ?? null;
    $rows = $data['recipients'] ?? null;
    if (!is_array($files) || !is_array($rows)) {
        throw new RuntimeException('recipients_file must return files/messages/recipients arrays');
    }

    $cleanFiles = [];
    foreach ($files as $alias => $file) {
        if (!is_string($alias) || !dm_mail_id_valid($alias)) {
            continue;
        }
        $file = trim((string)$file);
        if (!dm_download_rel_path_valid($file)) {
            continue;
        }
        $cleanFiles[$alias] = str_replace('\\', '/', $file);
    }

    $out = [];
    $seenRecipientKeys = [];
    foreach ($rows as $row) {
        if (!is_array($row) || array_values($row) !== $row || count($row) < 2) {
            continue;
        }

        $name = trim((string)($row[0] ?? ''));
        $address = trim(str_replace(["\r", "\n"], '', (string)($row[1] ?? '')));
        if ($name === '' || $address === '' || !dm_mailbox_field_valid($address)) {
            continue;
        }

        $recipientKey = dm_recipient_runtime_key($address);
        if (isset($seenRecipientKeys[$recipientKey])) {
            continue;
        }

        $normalAliases = $row[3] ?? [];
        $singleUseAliases = $row[4] ?? [];
        if (!is_array($normalAliases) || array_values($normalAliases) !== $normalAliases) {
            $normalAliases = [];
        }
        if (!is_array($singleUseAliases) || array_values($singleUseAliases) !== $singleUseAliases) {
            $singleUseAliases = [];
        }

        $downloads = [];
        $seenAliases = [];
        foreach ($normalAliases as $alias) {
            $alias = trim((string)$alias);
            if (!isset($cleanFiles[$alias]) || isset($seenAliases[$alias])) {
                continue;
            }
            $downloads[] = [
                'alias' => $alias,
                'download_key' => dm_download_runtime_key($recipientKey, $alias),
                'file' => $cleanFiles[$alias],
                'single_use' => false,
            ];
            $seenAliases[$alias] = true;
        }
        foreach ($singleUseAliases as $alias) {
            $alias = trim((string)$alias);
            if (!isset($cleanFiles[$alias]) || isset($seenAliases[$alias])) {
                continue;
            }
            $downloads[] = [
                'alias' => $alias,
                'download_key' => dm_download_runtime_key($recipientKey, $alias),
                'file' => $cleanFiles[$alias],
                'single_use' => true,
            ];
            $seenAliases[$alias] = true;
        }

        $out[] = [
            'name' => $name,
            'address' => $address,
            'recipient_key' => $recipientKey,
            'downloads' => $downloads,
        ];
        $seenRecipientKeys[$recipientKey] = true;
    }

    return $out;
}

/**
 * Best-effort MIME type detection for downloads.
 */
function dm_download_content_type(string $path): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $type = finfo_file($finfo, $path);
            if (is_string($type) && trim($type) !== '') {
                return $type;
            }
        }
    }
    return 'application/octet-stream';
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
    $downloadValidDays = dm_cfg_int_required($cfg, 'download_valid_days', 1);
    $downloadLeaseSeconds = dm_cfg_int_required($cfg, 'download_lease_seconds', 1);
    $downloadRateLimitMax = dm_cfg_int_required($cfg, 'download_rate_limit_max_requests', 1);
    $downloadRateLimitWindow = dm_cfg_int_required($cfg, 'download_rate_limit_window_seconds', 1);

    $ackEnabled = !empty($cfg['escalate_ack_enabled']);
    $downloadRateLimitEnabled = !empty($cfg['download_rate_limit_enabled']);
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
    if ($downloadLeaseSeconds < 60) {
        $warnings[] = 'download_lease_seconds is below 60; interrupted downloads may become hard to retry.';
    }
    if ($downloadValidDays < 7) {
        $warnings[] = 'download_valid_days is below 7; recipients may lose access to files sooner than expected.';
    }

    $operatorAlertMeta = dm_operator_alert_interval_meta($cfg);
    if ((bool)$operatorAlertMeta['used_fallback']) {
        $warnings[] = 'operator_alert_interval_hours is missing or invalid; safety fallback of 2 hours will be used.';
    }

    return [
    'check_interval_seconds' => $checkInterval,
    'confirm_window_seconds' => $confirmWindow,
    'remind_every_seconds' => $remindEvery,
    'escalate_grace_seconds' => $escalateGrace,
    'missed_cycles_before_fire' => $missedCyclesBeforeFire,
    'download_valid_days' => $downloadValidDays,
    'ack_enabled' => $ackEnabled,
    'escalate_ack_remind_every_seconds' => $ackRemindEvery,
    'escalate_ack_max_reminds' => $ackMaxReminds,
    'download_rate_limit_enabled' => $downloadRateLimitEnabled,
    'download_rate_limit_max_requests' => $downloadRateLimitMax,
    'download_rate_limit_window_seconds' => $downloadRateLimitWindow,
    'download_lease_seconds' => $downloadLeaseSeconds,
    'operator_alert_interval_hours' => (int)$operatorAlertMeta['hours'],
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
        } elseif ($level === 'WARN') {
            $warnCount++;
        } elseif ($level === 'FAIL') {
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
        } else {
            $ok("State directory readable: {$stateDir}");
        }
        if (!is_writable($stateDir)) {
            $warn("State directory is not writable for current user: {$stateDir}");
        } else {
            $ok("State directory writable: {$stateDir}");
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
    if (is_file($configPath) && is_readable($configPath)) {
        try {
            $cfg = dm_bootstrap_load_config_raw($configPath);
            $ok('Loaded totmann.inc.php');
        } catch (Throwable $e) {
            $fail('Loading totmann.inc.php failed: ' . $e->getMessage());
        }
    }

    if ($cfg === []) {
        echo "Summary: {$okCount} OK, {$warnCount} WARN, {$failCount} FAIL\n";
        echo "Result: NOT READY FOR GOLIVE\n";
        return 2;
    }

    $runtimeFileName = static function (array $cfg, string $key, callable $fail): string {
        $v = trim((string)($cfg[$key] ?? ''));
        if ($v === '') {
            $fail("{$key} is missing/empty.");
            return '__invalid__';
        }
        if (str_contains($v, '/') || str_contains($v, '\\') || $v === '.' || $v === '..' || str_contains($v, '..') || preg_match('/[[:cntrl:]]/', $v)) {
            $fail("{$key} must be a filename only: {$v}");
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
        if (str_contains($v, '/') || str_contains($v, '\\') || $v === '.' || $v === '..' || str_contains($v, '..') || preg_match('/[[:cntrl:]]/', $v)) {
            $fail("{$key} must be a filename only: {$v}");
            return '__invalid__';
        }
        return $v;
    };

    $libFileName = $runtimeFileName($cfg, 'lib_file', $fail);
    $l18nDirName = $runtimeFileName($cfg, 'l18n_dir_name', $fail);
    $lockFileName = $runtimeFileName($cfg, 'lock_file', $fail);
    $logFileName = $runtimeFileName($cfg, 'log_file_name', $fail);
    $recipientsFileName = $runtimeFileName($cfg, 'recipients_file', $fail);
    $stateFileName = $runtimeFileName($cfg, 'state_file', $fail);
    $webFileName = $runtimeFileName($cfg, 'web_file', $fail);
    $webCssFileName = $optionalRuntimeFileName($cfg, 'web_css_file', $fail);
    if ($failCount === 0) {
        $cssMsg = ($webCssFileName === null) ? 'css=disabled' : "css={$webCssFileName}";
        $ok("Runtime filenames: lib={$libFileName}, l18n={$l18nDirName}, lock={$lockFileName}, log={$logFileName}, recipients={$recipientsFileName}, state={$stateFileName}, web={$webFileName}, {$cssMsg}");
    }

    $libPath = $stateDir . '/' . $libFileName;
    $l18nDirPath = $stateDir . '/' . $l18nDirName;
    $recipientsPath = $stateDir . '/' . $recipientsFileName;
    foreach ([$libFileName => $libPath, $recipientsFileName => $recipientsPath] as $name => $path) {
        if (is_file($path) && is_readable($path)) {
            $ok("Found {$name}: {$path}");
        } else {
            $fail("Missing/unreadable {$name}: {$path}");
        }
    }

    if (!is_dir($l18nDirPath)) {
        $fail("Missing l18n directory: {$l18nDirPath}");
    } elseif (!is_readable($l18nDirPath)) {
        $fail("Unreadable l18n directory: {$l18nDirPath}");
    } else {
        $ok("Found l18n directory: {$l18nDirPath}");
        foreach (['de-DE', 'en-GB', 'en-US', 'fr-FR', 'it-IT', 'es-ES'] as $locale) {
            $localePath = $l18nDirPath . '/' . $locale . '.php';
            if (is_file($localePath) && is_readable($localePath)) {
                $ok("Found l18n locale file: {$locale}.php");
            } else {
                $fail("Missing/unreadable l18n locale file: {$localePath}");
            }
        }
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
        $warn('hmac_secret_hex is valid but shorter than the recommended 64 hex chars (32 bytes).');
    } else {
        $ok('hmac_secret_hex format/length looks good.');
    }

    $baseUrl = trim((string)($cfg['base_url'] ?? ''));
    if ($baseUrl === '') {
        $fail('base_url is empty.');
    } elseif ($looksPlaceholder($baseUrl)) {
        $fail("base_url contains placeholder/local host value: {$baseUrl}");
    } else {
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            $fail("base_url must be an absolute URL: {$baseUrl}");
        } elseif (strtolower((string)$parts['scheme']) !== 'https') {
            $fail("base_url must use HTTPS for GoLive: {$baseUrl}");
        } else {
            $path = (string)($parts['path'] ?? '');
            $base = $path !== '' ? basename($path) : '';
            if ($base === $webFileName) {
                $warn("base_url currently includes web_file ({$webFileName}). Use only the base URL path; web_file is appended automatically.");
            } else {
                $ok('base_url looks valid.');
            }
        }
    }

    $downloadBaseDir = trim((string)($cfg['download_base_dir'] ?? ''));
    if ($downloadBaseDir === '') {
        $fail('download_base_dir is empty.');
    } elseif (!str_starts_with($downloadBaseDir, '/')) {
        $fail("download_base_dir must be an absolute path: {$downloadBaseDir}");
    } elseif (!is_dir($downloadBaseDir)) {
        $warn("download_base_dir does not exist yet: {$downloadBaseDir}");
    } elseif (!is_readable($downloadBaseDir)) {
        $fail("download_base_dir is not readable: {$downloadBaseDir}");
    } else {
        $ok("download_base_dir readable: {$downloadBaseDir}");
    }

    try {
        $validatedRuntimeCfg = dm_validate_runtime_config($cfg);
        $ok('Runtime timing config validation passed.');
        foreach ((array)($validatedRuntimeCfg['warnings'] ?? []) as $w) {
            $warn('Runtime timing warning: ' . $w);
        }
    } catch (Throwable $e) {
        $fail('Runtime timing config validation failed: ' . $e->getMessage());
    }

    try {
        $selfRecipients = dm_recipient_entries_runtime((array)($cfg['to_self'] ?? []));
        if ($selfRecipients === []) {
            $fail('to_self does not contain any valid mailbox.');
        } else {
            $ok('to_self contains ' . count($selfRecipients) . ' valid mailbox entr' . (count($selfRecipients) === 1 ? 'y.' : 'ies.'));
        }
    } catch (Throwable $e) {
        $fail('to_self validation failed: ' . $e->getMessage());
    }

    try {
        $recipients = dm_recipients_load($cfg);
        $ok('recipients_file load passed: ' . $recipientsFileName);
        $messageKeys = [];
        $downloadCount = 0;
        foreach ($recipients as $recipient) {
            if (!is_array($recipient)) {
                continue;
            }
            $name = (string)($recipient['name'] ?? '');
            $address = (string)($recipient['address'] ?? '');
            if ($looksPlaceholder($name)) {
                $fail("recipients_file contains placeholder personal name: {$name}");
            }
            if ($looksPlaceholder($address)) {
                $fail("recipients_file contains placeholder address: {$address}");
            }
            $messageKey = (string)($recipient['message_key'] ?? '');
            if ($messageKey !== '') {
                $messageKeys[$messageKey] = true;
            }
            $message = $recipient['message'] ?? null;
            if ($message !== null && !is_array($message)) {
                $fail("recipients_file contains invalid message data for {$address}");
            }
            $downloads = $recipient['downloads'] ?? [];
            if (is_array($downloads)) {
                $downloadCount += count($downloads);
            }
        }
        $ok('recipients_file has ' . count($recipients) . ' valid recipient entr' . (count($recipients) === 1 ? 'y.' : 'ies.'));
        if ($messageKeys !== []) {
            $ok('recipients_file references ' . count($messageKeys) . ' individual message entr' . (count($messageKeys) === 1 ? 'y.' : 'ies.'));
        }
        if ($downloadCount > 0) {
            $ok('recipients_file defines ' . $downloadCount . ' download assignment' . ($downloadCount === 1 ? '.' : 's.'));
        }
    } catch (Throwable $e) {
        $fail('recipients_file load failed: ' . $e->getMessage());
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
    } elseif ($logMode === 'none') {
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

    $rateLimitDir = $cfg['rate_limit_dir'] ?? null;
    if (!is_string($rateLimitDir) || trim($rateLimitDir) === '') {
        $rateLimitDir = $stateDir . '/ratelimit';
    }
    $rateLimitDir = rtrim((string)$rateLimitDir, '/');
    if (!empty($cfg['rate_limit_enabled'])) {
        if (is_dir($rateLimitDir) && is_writable($rateLimitDir)) {
            $ok("rate_limit_dir writable: {$rateLimitDir}");
        } elseif (!is_dir($rateLimitDir)) {
            $warn("rate_limit_dir does not exist yet (will be created on demand): {$rateLimitDir}");
        } else {
            $warn("rate_limit_dir exists but is not writable for current user: {$rateLimitDir}");
        }
    }

    $ipMode = (string)($cfg['ip_mode'] ?? 'remote_addr');
    if ($ipMode === 'remote_addr') {
        $ok('ip_mode=remote_addr (safest default).');
    } elseif ($ipMode === 'trusted_proxy') {
        $trustedProxies = $cfg['trusted_proxies'] ?? [];
        $proxyHeader = trim((string)($cfg['trusted_proxy_header'] ?? ''));
        if (!is_array($trustedProxies) || $trustedProxies === []) {
            $fail('trusted_proxy mode requires at least one trusted proxy IP.');
        } else {
            $ok('trusted_proxy mode configured with ' . count($trustedProxies) . ' trusted prox' . (count($trustedProxies) === 1 ? 'y.' : 'ies.'));
        }
        if ($proxyHeader === '') {
            $fail('trusted_proxy_header is empty.');
        } else {
            $ok("trusted_proxy_header set to {$proxyHeader}.");
        }
    } else {
        $fail("ip_mode must be 'remote_addr' or 'trusted_proxy' (got: {$ipMode})");
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
                    if ($uid === $owner) {
                        $granted = ($mode >> 6) & 0x7;
                    } elseif (in_array($group, array_values(array_map('intval', $gids)), true)) {
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

                $stateDirOkWx = $requirePathPerm($stateDir, 0x3, 'state dir (w+x)', true);
                $requirePathPerm($stateDir, 0x5, 'state dir (r+x)', true);
                $requirePathPerm($configPath, 0x4, 'totmann.inc.php (read)', true);
                $requirePathPerm($libPath, 0x4, "{$libFileName} (read)", true);
                $requirePathPerm($recipientsPath, 0x4, "{$recipientsFileName} (read)", true);

                $stateJsonPath = $stateDir . '/' . $stateFileName;
                if (file_exists($stateJsonPath)) {
                    $requirePathPerm($stateJsonPath, 0x4, "{$stateFileName} (read)", true);
                    if ($stateDirOkWx) {
                        $ok("{$stateFileName} update path likely works (write via tmp+rename in state dir).");
                    } else {
                        $fail("{$stateFileName} update path likely blocked because state dir lacks w+x for web user.");
                    }
                } else {
                    $warn("{$stateFileName} does not exist yet (expected before first initialise).");
                }

                $lockPath = $stateDir . '/' . $lockFileName;
                if (file_exists($lockPath)) {
                    $requirePathPerm($lockPath, 0x6, "{$lockFileName} (read+write for c+)", true);
                } elseif ($stateDirOkWx) {
                    $ok("{$lockFileName} missing is acceptable; web user can likely create it.");
                } else {
                    $fail("{$lockFileName} missing and state dir lacks w+x, so web user likely cannot create the lock file.");
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
    'escalation_event_at' => null,
    'operator_alerts' => [],
    'escalation_delivery' => [],
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
