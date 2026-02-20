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
function dm_mail_dt(array $cfg, int $ts): string {
static $tzCache = [];

$tzName = (string)($cfg['mail_timezone'] ?? 'UTC');
if ($tzName==='') $tzName = 'UTC';

if (!isset($tzCache[$tzName])) {
try { $tzCache[$tzName] = new DateTimeZone($tzName); }
catch (Throwable $e) { $tzCache[$tzName] = new DateTimeZone('UTC'); }
}
$tz = $tzCache[$tzName];
$dt = (new DateTimeImmutable('@'.$ts))->setTimezone($tz);

$fmt = $cfg['mail_datetime_format'] ?? null;
if (is_string($fmt) && $fmt!=='') return $dt->format($fmt);

$df = (string)($cfg['mail_date_format'] ?? 'Y-m-d');
$tf = (string)($cfg['mail_time_format'] ?? 'H:i:s');
return $dt->format($df.' '.$tf);
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
function dm_mail_dt_or_never(array $cfg, int $ts, string $never = 'Never'): string { return ($ts>0) ? dm_mail_dt($cfg, $ts) : $never; }

/**
 * Wrapper for "now" to keep calling code uniform and easy to stub later.
 */
function dm_now(): int { return time(); }

/**
 * ISO timestamp (UTC) for logs and machine-readable fields.
 * Always UTC, intentionally independent from `mail_timezone`.
 */
function dm_iso(int $ts): string { return gmdate('Y-m-d\TH:i:s\Z', $ts); }

/**
 * Resolve the state directory.
 * - Normalises trailing slashes.
 * - Falls back to the library directory as last resort (should rarely happen).
 */
function dm_state_dir(array $cfg): string {
$dir = rtrim((string)($cfg['state_dir'] ?? __DIR__), '/');
return $dir!=='' ? $dir : __DIR__;
}

/**
 * Build an absolute path inside the state directory.
 * `name` may be given with or without a leading slash.
 */
function dm_path(array $cfg, string $name): string { return dm_state_dir($cfg).'/'.ltrim($name, '/'); }

/**
 * Validate runtime file names from config.
 * - Basename only (no slashes, no traversal, no control chars)
 * - Throws on missing/invalid values
 */
function dm_runtime_file_name(array $cfg, string $key): string {
$raw = trim((string)($cfg[$key] ?? ''));
if ($raw==='') throw new RuntimeException("Missing config key: {$key}");
if (str_contains($raw, '/') || str_contains($raw, '\\')) throw new RuntimeException("Invalid {$key}: filename must not contain slashes");
if ($raw==='.' || $raw==='..' || str_contains($raw, '..')) throw new RuntimeException("Invalid {$key}: traversal is not allowed");
if (preg_match('/[[:cntrl:]]/', $raw)) throw new RuntimeException("Invalid {$key}: control chars are not allowed");
return $raw;
}

/**
 * Canonical runtime state file path.
 * Uses configurable `state_file`.
 */
function dm_state_file(array $cfg): string { return dm_path($cfg, dm_runtime_file_name($cfg, 'state_file')); }

/**
 * Canonical runtime lock file path.
 * Uses configurable `lock_file`.
 */
function dm_lock_file(array $cfg): string { return dm_path($cfg, dm_runtime_file_name($cfg, 'lock_file')); }

/**
 * Determine the logfile path.
 * - If `log_file` is explicitly set (non-empty string), use it as absolute/relative path override.
 * - Otherwise use `{state_dir}/{log_file_name}`.
 */
function dm_log_file(array $cfg): ?string {
$lf = $cfg['log_file'] ?? null;
if (is_string($lf) && trim($lf)!=='') return trim($lf);
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
function dm_log_mode(array $cfg): string {
$mode = strtolower(trim((string)($cfg['log_mode'] ?? 'both')));
if (in_array($mode, ['none', 'syslog', 'file', 'both'], true)) return $mode;
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
function dm_log(array $cfg, string $msg): void {
try {
$mode = dm_log_mode($cfg);
$toFile = ($mode==='file' || $mode==='both');
$toSyslog = ($mode==='syslog' || $mode==='both');

// File logging (best effort, never fatal)
if ($toFile) {
$line = '['.dm_iso(dm_now()).'] '.$msg.PHP_EOL;
$lf = dm_log_file($cfg);
if ($lf) {
$dir = dirname($lf);

// Create dir if needed (best effort)
if (!is_dir($dir)) {
// If mkdir fails, we simply skip file logging.
if (!mkdir($dir, 0770, true) && !is_dir($dir)) $dir = '';
}

// Write only if we can reasonably expect success
if ($dir!=='' && is_dir($dir) && is_writable($dir)) {
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
function dm_lock_open(string $lockFile) {
$dir = dirname($lockFile);
if (!is_dir($dir)) {
if (!mkdir($dir, 0770, true) && !is_dir($dir)) throw new RuntimeException("Cannot create lock dir: $dir");
}

$fh = fopen($lockFile, 'c+');
if ($fh===false) throw new RuntimeException("Cannot open lock file: $lockFile");
if (!flock($fh, LOCK_EX)) throw new RuntimeException("Cannot acquire lock");
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
function dm_state_load(string $stateFile): array {
if (!is_file($stateFile) || !is_readable($stateFile)) return [];

$raw = file_get_contents($stateFile);
if ($raw===false || trim($raw)==='') return [];

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
function dm_state_save(string $stateFile, array $state): void {
$dir = dirname($stateFile);
if (!is_dir($dir)) {
if (!mkdir($dir, 0770, true) && !is_dir($dir)) throw new RuntimeException("Cannot create state dir: $dir");
}

$tmp = $stateFile.'.tmp';
$json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json===false) throw new RuntimeException('Failed to encode state JSON');

if (file_put_contents($tmp, $json)===false) throw new RuntimeException('Failed to write tmp state');
if (!rename($tmp, $stateFile)) throw new RuntimeException('Failed to rename tmp state');
}

/**
 * Bootstrap helper for loading totmann.inc.php in a guarded way.
 * Throws on missing/unreadable config or invalid return type.
 */
function dm_bootstrap_load_config(string $configPath): array {
if (!is_file($configPath) || !is_readable($configPath)) throw new RuntimeException("missing/unreadable totmann.inc.php: {$configPath}");
$cfg = require $configPath;
if (!is_array($cfg)) throw new RuntimeException('totmann.inc.php must return an array');
return $cfg;
}

/**
 * Compute cycle timing fields from a cycle start timestamp.
 */
function dm_cycle_window(int $cycleStart, int $checkInterval, int $confirmWindow): array {
$check = max(1, $checkInterval);
$window = max(1, $confirmWindow);
$nextCheck = $cycleStart+$check;
$deadline = $nextCheck+$window;
return ['cycle_start_at' => $cycleStart, 'next_check_at' => $nextCheck, 'deadline_at' => $deadline, 'next_reminder_at' => $nextCheck];
}

/**
 * Apply cycle timing + token fields into state.
 * Returns the computed timing fields.
 */
function dm_state_apply_cycle(array &$state, int $cycleStart, int $checkInterval, int $confirmWindow, array $token): array {
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
function dm_state_reset_ack(array &$state): void {
$state['escalate_ack_token'] = null;
$state['escalate_ack_at'] = null;
$state['escalate_ack_sent_count'] = 0;
$state['escalate_ack_next_at'] = null;
}

/**
 * Clear escalation + ACK tracking fields.
 */
function dm_state_clear_escalation(array &$state): void {
$state['escalated_sent_at'] = null;
dm_state_reset_ack($state);
}

/**
 * Decode HMAC secret from hex to binary.
 * - Requires hex input.
 * - Requires at least 16 bytes of entropy (32+ recommended).
 */
function dm_secret_bin(array $cfg): string {
$bin = hex2bin((string)($cfg['hmac_secret_hex'] ?? ''));
if ($bin===false || strlen($bin)<16) throw new RuntimeException('Invalid hmac_secret_hex (hex, min 16 bytes)');
return $bin;
}

/**
 * Create a token:
 * - id: random 16 bytes => 32 hex chars
 * - sig: HMAC-SHA256 over id => 64 hex chars
 */
function dm_make_token(array $cfg): array {
$id = bin2hex(random_bytes(16));
return ['id' => $id, 'sig' => hash_hmac('sha256', $id, dm_secret_bin($cfg))];
}

/**
 * Verify token format and signature.
 * - Strict hex validation avoids weird encodings.
 * - Uses hash_equals to avoid timing leaks.
 */
function dm_token_valid(array $cfg, string $id, string $sig): bool {
if (!preg_match('/^[a-f0-9]{32}$/', $id) || !preg_match('/^[a-f0-9]{64}$/', $sig)) return false;
return hash_equals(hash_hmac('sha256', $id, dm_secret_bin($cfg)), $sig);
}

/**
 * Build the public endpoint URL from base_url + web_file.
 */
function dm_endpoint_url(array $cfg): string {
$baseUrl = rtrim((string)($cfg['base_url'] ?? ''), '/');
$webFile = dm_runtime_file_name($cfg, 'web_file');
if ($baseUrl==='') throw new RuntimeException('Missing config key: base_url');
return $baseUrl.'/'.$webFile;
}

/**
 * Create the confirmation URL for a token.
 * Adds `a=confirm&id=...&sig=...` to endpoint URL.
 */
function dm_confirm_url(array $cfg, array $token): string {
$base = rtrim(dm_endpoint_url($cfg), '?');
$q = http_build_query(['a' => 'confirm', 'id' => $token['id'], 'sig' => $token['sig']]);
return $base.(str_contains($base, '?') ? '&' : '?').$q;
}

/**
 * Create the ACK URL for a token.
 * Adds `a=ack&id=...&sig=...` to endpoint URL.
 */
function dm_ack_url(array $cfg, array $token): string {
$base = rtrim(dm_endpoint_url($cfg), '?');
$q = http_build_query(['a' => 'ack', 'id' => $token['id'], 'sig' => $token['sig']]);
return $base.(str_contains($base, '?') ? '&' : '?').$q;
}

/**
 * Render escalation body from template placeholders.
 */
function dm_render_escalate_body(array $cfg, int $lastConfirm, int $cycleStart, int $deadline, string $ackUrl, bool $ackEnabled): string {
$tpl = (string)($cfg['body_escalate'] ?? '');

if (!$ackEnabled) {
$tpl = str_replace(["Ack receipt by clicking:\n{ACK_URL}\n\n", "Ack receipt by clicking:\r\n{ACK_URL}\r\n\r\n"], ["", ""], $tpl);
$tpl = str_replace('{ACK_URL}', '', $tpl);
}

return str_replace(['{LAST_CONFIRM_ISO}', '{CYCLE_START_ISO}', '{DEADLINE_ISO}', '{ACK_URL}'], [dm_mail_dt_or_never($cfg, $lastConfirm), dm_mail_dt($cfg, $cycleStart), dm_mail_dt($cfg, $deadline), $ackUrl], $tpl);
}

/**
 * Write the full payload to a stream (handles short writes).
 */
function dm_stream_write_all($stream, string $data): bool {
$len = strlen($data);
$off = 0;
while ($off<$len) {
$written = fwrite($stream, substr($data, $off));
if ($written===false || $written===0) return false;
$off += $written;
}
return true;
}

/**
 * Persist JSON content into an already locked file handle.
 */
function dm_locked_write_json($fh, array $data): bool {
$json = json_encode($data);
if (!is_string($json)) return false;
if (!ftruncate($fh, 0)) return false;
if (!rewind($fh)) return false;
if (!dm_stream_write_all($fh, $json)) return false;
if (!fflush($fh)) return false;
return true;
}

/**
 * RFC 2047 header encoding for non-ASCII strings.
 * - If ASCII: returned as-is
 * - If UTF-8: encoded as =?UTF-8?B?...?=
 *
 * This avoids triggering SMTPUTF8 on picky remote MTAs.
 */
function dm_hdr_encode(string $s): string {
// Prevent header injection (strip CR/LF)
$s = str_replace(["\r", "\n"], '', $s);
// fast path: pure ASCII
if ($s==='' || preg_match('/^[\x00-\x7F]*$/', $s)) return $s;
// base64-encode UTF-8 header (single chunk; good enough for typical short subjects/names)
return '=?UTF-8?B?'.base64_encode($s).'?=';
}

/**
 * Encode a mailbox header value like:
 * "Name <addr@example.com>"OR"addr@example.com"
 *
 * Only the display name is RFC2047-encoded; address stays ASCII.
 */
function dm_hdr_mailbox(string $raw): string {
$raw = trim($raw);
// Prevent header injection (strip CR/LF)
$raw = str_replace(["\r", "\n"], '', $raw);
if ($raw==='') return '';
// "Name <addr>"
if (preg_match('/^\s*(.*?)\s*<\s*([^>]+)\s*>\s*$/', $raw, $m)) {
$name = trim($m[1]);
$addr = trim($m[2]);
// Prevent header injection (strip CR/LF)
$name = str_replace(["\r", "\n"], '', $name);
$addr = str_replace(["\r", "\n"], '', $addr);
if ($name!=='') $name = dm_hdr_encode($name);
return ($name!=='' ? $name.' ' : '').'<'.$addr.'>';
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
function dm_send_mail(array $cfg, array $to, string $subject, string $body): void {
$fromRaw = trim((string)($cfg['mail_from'] ?? ''));
$replyRaw = trim((string)($cfg['reply_to'] ?? ''));

$toHeader = [];
$toArgv = [];
$seen = [];

$addArgv = function(string $addr) use (&$toArgv, &$seen): void {
$k = strtolower($addr);
if (isset($seen[$k])) return;
$seen[$k] = true;
$toArgv[] = $addr;
};

foreach ($to as $raw) {
$raw = str_replace(["\r", "\n"], '', trim((string)$raw));
if ($raw==='') continue;

// Allow comma-separated lists in one entry (store each mailbox separately for header encoding)
$parts = array_map('trim', explode(',', $raw));
foreach ($parts as $part) {
$part = str_replace(["\r", "\n"], '', $part);
if ($part==='') continue;
$toHeader[] = $part;

// Extract addr from "Name <addr>"
$addr = $part;
if (preg_match('/<([^>]+)>/', $part, $m)) $addr = trim($m[1]);
$addr = trim($addr);

// Optional: IDN domain normalisation if intl is installed
if (function_exists('idn_to_ascii') && str_contains($addr, '@')) {
[$local, $domain] = explode('@', $addr, 2);
$domain = trim($domain);
if ($domain!=='') {
$variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
$ascii = idn_to_ascii($domain, 0, $variant);
if (is_string($ascii) && $ascii!=='') $addr = $local.'@'.$ascii;
}
}

// Validate mailbox for argv (must be ASCII mailbox)
$isValid = (str_contains($addr, '@') && filter_var($addr, FILTER_VALIDATE_EMAIL)!==false);
if (!$isValid && str_contains($addr, '@')) $isValid = (bool)preg_match('/^[^\s@<>",;:]+@[^\s@<>",;:]+\.[^\s@<>",;:]+$/', $addr);
if ($isValid) $addArgv($addr);
}
}

$toHeader = array_values(array_unique($toHeader));
if (!$toArgv) throw new RuntimeException('sendmail: empty/invalid recipient list');

// RFC2047 encode non-ASCII parts
$fromHeader = ($fromRaw!=='')? dm_hdr_mailbox($fromRaw) : '';
$replyHeader = ($replyRaw!=='') ? dm_hdr_mailbox($replyRaw) : '';
$toHeaderEnc = array_map('dm_hdr_mailbox', $toHeader);
$subjectEnc = dm_hdr_encode($subject);

$h = [];
if ($fromHeader!=='')$h[] = 'From: '.$fromHeader;
if ($replyHeader!=='') $h[] = 'Reply-To: '.$replyHeader;
$h[] = 'MIME-Version: 1.0';
$h[] = 'Content-Type: text/plain; charset=UTF-8';
$h[] = 'Content-Transfer-Encoding: 8bit';
$h[] = 'Subject: '.$subjectEnc;
$h[] = 'To: '.implode(', ', $toHeaderEnc);

$msg = implode("\r\n", $h)."\r\n\r\n".$body."\r\n";

$sendmail = (string)($cfg['sendmail_path'] ?? '/usr/sbin/sendmail');
if (!is_executable($sendmail)) throw new RuntimeException("sendmail not found/executable at {$sendmail}");

$desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$cmd = array_merge([$sendmail, '-i', '--'], array_values($toArgv));

$proc = proc_open($cmd, $desc, $pipes);
if (!is_resource($proc)) throw new RuntimeException('proc_open failed for sendmail');

$stdout = $stderr = '';
try {
if (!isset($pipes[0]) || !is_resource($pipes[0])) throw new RuntimeException('sendmail: stdin pipe missing');

$wrote = dm_stream_write_all($pipes[0], $msg);
fclose($pipes[0]);

$stdout = (isset($pipes[1]) && is_resource($pipes[1])) ? stream_get_contents($pipes[1]) : '';
if (isset($pipes[1]) && is_resource($pipes[1])) fclose($pipes[1]);

$stderr = (isset($pipes[2]) && is_resource($pipes[2])) ? stream_get_contents($pipes[2]) : '';
if (isset($pipes[2]) && is_resource($pipes[2])) fclose($pipes[2]);

$code = proc_close($proc);

if (!$wrote) throw new RuntimeException("sendmail: failed to write message to stdin: {$stderr} {$stdout}");
if ($code!==0) throw new RuntimeException("sendmail failed (code {$code}): {$stderr} {$stdout}");
} finally {
foreach ($pipes ?? [] as $p) {
if (is_resource($p)) fclose($p);
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
function dm_client_ip(array $cfg): string {
$remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
if ($remote==='') $remote = '0.0.0.0';

if ((string)($cfg['ip_mode'] ?? 'remote_addr')!=='trusted_proxy') return $remote;

$trusted = (array)($cfg['trusted_proxies'] ?? []);
if (!in_array($remote, $trusted, true)) return $remote;

$hdr = (string)($cfg['trusted_proxy_header'] ?? 'X-Forwarded-For');
$key = 'HTTP_'.strtoupper(str_replace('-', '_', $hdr));
$val = (string)($_SERVER[$key] ?? '');
if ($val==='') return $remote;

$first = trim(explode(',', $val, 2)[0] ?? '');
return ($first!=='' && filter_var($first, FILTER_VALIDATE_IP)) ? $first : $remote;
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
function dm_rate_limit_check(array $cfg, string $ip, int $now): bool {
if (empty($cfg['rate_limit_enabled'])) return true;

$dir = $cfg['rate_limit_dir'] ?? '';
$dir = is_string($dir) ? $dir : '';
if ($dir==='') $dir = dm_path($cfg, 'ratelimit');

// If misconfigured, do not break the web endpoint.
if ($dir==='/ratelimit' || $dir==='ratelimit') return true;
if (!is_dir($dir)) {
if (!mkdir($dir, 0770, true) && !is_dir($dir)) return true;
}
if (!is_writable($dir)) return true;

$max = max(1, (int)($cfg['rate_limit_max_requests'] ?? 30));
$win = max(1, (int)($cfg['rate_limit_window_seconds'] ?? 60));

$key = hash('sha256', $ip);
$path = rtrim($dir, '/').'/'.substr($key, 0, 2).'/'.$key.'.json';

$subdir = dirname($path);
if (!is_dir($subdir)) {
if (!mkdir($subdir, 0770, true) && !is_dir($subdir)) return true;
}

$fh = fopen($path, 'c+');
if ($fh===false) return true;

try {
if (!flock($fh, LOCK_EX)) return true;

rewind($fh);
$raw = stream_get_contents($fh);
$data = (is_string($raw) && trim($raw)!=='') ? json_decode($raw, true) : [];
if (!is_array($data)) $data = [];

$hits = $data['hits'] ?? [];
if (!is_array($hits)) $hits = [];

$cutoff = $now-$win;
$hits = array_values(array_filter($hits, fn($t) => (int)$t >= $cutoff));

if (count($hits)>=$max) {
$data['hits'] = $hits;
$data['last'] = $now;
if (!dm_locked_write_json($fh, $data)) return true;
return false;
}

$hits[] = $now;
$data['hits'] = $hits;
$data['last'] = $now;

if (!dm_locked_write_json($fh, $data)) return true;

return true;
} finally {
flock($fh, LOCK_UN);
fclose($fh);
}
}
