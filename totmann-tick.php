<?php

declare(strict_types=1);

/**
 * totmannschalter – systemd tick entrypoint
 *
 * Project: https://github.com/MacSteini/totmannschalter
 * Licence: MIT (see LICENCE)
 *
 * State dir resolution:
 * - ENV TOTMANN_STATE_DIR, otherwise __DIR__ (so it works when placed in /var/lib/totmann).
 */

$stateDir = rtrim((string)(getenv('TOTMANN_STATE_DIR') ?: __DIR__), '/');

function dm_bootstrap_load_config_raw(string $configPath): array {
if (!is_file($configPath) || !is_readable($configPath)) throw new RuntimeException("missing/unreadable totmann.inc.php: {$configPath}");
$cfg = require $configPath;
if (!is_array($cfg)) throw new RuntimeException('totmann.inc.php must return an array');
return $cfg;
}

function dm_bootstrap_file_name(array $cfg, string $key): string {
$v = trim((string)($cfg[$key] ?? ''));
if ($v==='') throw new RuntimeException("Missing config key: {$key}");
if (str_contains($v, '/') || str_contains($v, '\\')) throw new RuntimeException("Invalid {$key}: filename must not contain slashes");
if ($v==='.' || $v==='..' || str_contains($v, '..') || preg_match('/[[:cntrl:]]/', $v)) throw new RuntimeException("Invalid {$key}: traversal/control chars not allowed");
return $v;
}

function dm_cfg_int_required(array $cfg, string $key, int $min, ?int $max = null): int {
if (!array_key_exists($key, $cfg)) throw new RuntimeException("Missing config key: {$key}");
$raw = $cfg[$key];
if (is_int($raw)) $v = $raw;
elseif (is_string($raw) && preg_match('/^-?\d+$/', trim($raw))) $v = (int)trim($raw);
else throw new RuntimeException("Invalid {$key}: expected integer, got ".gettype($raw));
if ($v<$min) throw new RuntimeException("Invalid {$key}: must be >= {$min}, got {$v}");
if ($max!==null && $v>$max) throw new RuntimeException("Invalid {$key}: must be <= {$max}, got {$v}");
return $v;
}

function dm_validate_runtime_config(array $cfg): array {
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
if ($ackRemindEvery<60) $warnings[] = 'escalate_ack_remind_every_seconds is below 60; runtime clamps it to 60 seconds.';
}

if ($confirmWindow>$checkInterval) $warnings[] = 'confirm_window_seconds is greater than check_interval_seconds.';
if ($remindEvery>$confirmWindow) $warnings[] = 'remind_every_seconds is greater than confirm_window_seconds; only limited reminders may occur per cycle.';

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
function dm_preflight_check(string $stateDir, ?string $webUser = null): int {
$okCount = 0;
$warnCount = 0;
$failCount = 0;

$emit = static function(string $level, string $msg) use (&$okCount, &$warnCount, &$failCount): void {
if ($level==='OK') $okCount++;
if ($level==='WARN') $warnCount++;
if ($level==='FAIL') $failCount++;
echo "[{$level}] {$msg}\n";
};

$ok = static function(string $msg) use ($emit): void { $emit('OK', $msg); };
$warn = static function(string $msg) use ($emit): void { $emit('WARN', $msg); };
$fail = static function(string $msg) use ($emit): void { $emit('FAIL', $msg); };

$looksPlaceholder = static function(string $value): bool {
$v = strtolower($value);
return str_contains($v, 'example.com') || str_contains($v, 'replace_with') || str_contains($v, 'localhost');
};

$stateDir = rtrim($stateDir, '/');
if ($stateDir==='') $stateDir = '.';
$ok("Resolved state directory: {$stateDir}");

if (!is_dir($stateDir)) $fail("State directory does not exist: {$stateDir}");
else {
if (!is_readable($stateDir)) $fail("State directory is not readable for current user: {$stateDir}");
if (!is_writable($stateDir)) $warn("State directory is not writable for current user: {$stateDir}");
}

$configPath = $stateDir.'/totmann.inc.php';
$tickPath = $stateDir.'/totmann-tick.php';

foreach (['totmann.inc.php' => $configPath, 'totmann-tick.php' => $tickPath] as $name => $path) {
if (is_file($path) && is_readable($path)) $ok("Found {$name}: {$path}");
else $fail("Missing/unreadable {$name}: {$path}");
}

$cfg = [];
$libFileName = 'totmann-lib.php';
$webFileName = 'totmann.php';
$stateFileName = 'totmann.json';
$lockFileName = 'totmann.lock';
$logFileName = 'totmann.log';
$webCssFileName = 'totmann.css';
if (is_file($configPath) && is_readable($configPath)) {
try {
$cfg = dm_bootstrap_load_config_raw($configPath);
$ok('Loaded totmann.inc.php');
} catch (Throwable $e) { $fail('Loading totmann.inc.php failed: '.$e->getMessage()); }
}
$libPath = $stateDir.'/'.$libFileName;
if ($cfg) {
$runtimeFileName = static function(array $cfg, string $key, callable $fail): string {
$v = trim((string)($cfg[$key] ?? ''));
if ($v==='') {
$fail("{$key} is missing/empty.");
return '__invalid__';
}
if (str_contains($v, '/') || str_contains($v, '\\')) {
$fail("{$key} must be a filename only (no slashes): {$v}");
return '__invalid__';
}
if ($v==='.' || $v==='..' || str_contains($v, '..') || preg_match('/[[:cntrl:]]/', $v)) {
$fail("{$key} contains invalid characters: {$v}");
return '__invalid__';
}
return $v;
};

$optionalRuntimeFileName = static function(array $cfg, string $key, callable $fail): ?string {
if (!array_key_exists($key, $cfg)) return null;
$v = trim((string)$cfg[$key]);
if ($v==='') return null;
if (str_contains($v, '/') || str_contains($v, '\\')) {
$fail("{$key} must be a filename only (no slashes): {$v}");
return '__invalid__';
}
if ($v==='.' || $v==='..' || str_contains($v, '..') || preg_match('/[[:cntrl:]]/', $v)) {
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
$webCssFileName = $optionalRuntimeFileName($cfg, 'web_css_file', $fail);
if ($failCount===0) {
$cssMsg = ($webCssFileName===null) ? 'css=disabled' : "css={$webCssFileName}";
$ok("Runtime filenames: lib={$libFileName}, web={$webFileName}, state={$stateFileName}, lock={$lockFileName}, log={$logFileName}, {$cssMsg}");
}
if ($webCssFileName===null) $ok('web_css_file empty: stylesheet link from web endpoint is disabled.');
$libPath = $stateDir.'/'.$libFileName;

if (is_file($libPath) && is_readable($libPath)) $ok("Found {$libFileName}: {$libPath}");
else $fail("Missing/unreadable {$libFileName}: {$libPath}");

$configuredStateDir = rtrim((string)($cfg['state_dir'] ?? ''), '/');
if ($configuredStateDir==='') $warn('totmann.inc.php state_dir is empty.');
elseif ($configuredStateDir!==$stateDir) $warn("totmann.inc.php state_dir ({$configuredStateDir}) differs from resolved state dir ({$stateDir}).");
else $ok('totmann.inc.php state_dir matches resolved state dir.');

$secret = trim((string)($cfg['hmac_secret_hex'] ?? ''));
if ($secret==='') $fail('hmac_secret_hex is empty.');
elseif (str_contains($secret, 'REPLACE_WITH')) $fail('hmac_secret_hex still contains placeholder text.');
elseif (!preg_match('/^[a-f0-9]+$/i', $secret)) $fail('hmac_secret_hex must be hex-encoded.');
elseif ((strlen($secret)%2)!==0) $fail('hmac_secret_hex length must be even.');
elseif (strlen($secret)<32) $fail('hmac_secret_hex must be at least 32 hex chars (16 bytes).');
elseif (strlen($secret)<64) $warn('hmac_secret_hex is valid but shorter than recommended 64 hex chars (32 bytes).');
else $ok('hmac_secret_hex format/length looks good.');

$checkBaseUrl = static function(array $cfg, string $key, bool $required, bool $httpsOnly, bool $forbidPlaceholder, string $webFileName, callable $ok, callable $warn, callable $fail, callable $looksPlaceholder): void {
$url = trim((string)($cfg[$key] ?? ''));
if ($url==='') {
if ($required) $fail("{$key} is empty.");
else $warn("{$key} is empty.");
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
if ($httpsOnly && strtolower((string)$parts['scheme'])!=='https') {
$fail("{$key} must use HTTPS for GoLive: {$url}");
return;
}
$path = (string)($parts['path'] ?? '');
$base = $path!=='' ? basename($path) : '';
if ($webFileName!=='__invalid__' && $base===$webFileName) $warn("{$key} currently includes web_file ({$webFileName}). Use only the base URL path; web_file is appended automatically.");
if (!empty($parts['query'])) $warn("{$key} should not include a query string; it is added automatically.");
$ok("{$key} looks valid.");
};

$checkBaseUrl($cfg, 'base_url', true, true, true, $webFileName, $ok, $warn, $fail, $looksPlaceholder);

try {
$validatedRuntimeCfg = dm_validate_runtime_config($cfg);
$ok('Runtime timing config validation passed.');
foreach ((array)($validatedRuntimeCfg['warnings'] ?? []) as $w) $warn('Runtime timing warning: '.$w);
} catch (Throwable $e) { $fail('Runtime timing config validation failed: '.$e->getMessage()); }

$checkRecipients = static function(array $cfg, string $key, callable $ok, callable $warn, callable $fail, callable $looksPlaceholder): void {
$list = $cfg[$key] ?? null;
if (!is_array($list) || $list===[]) {
$fail("{$key} must contain at least one mailbox.");
return;
}

$valid = 0;
$invalid = 0;
$placeholder = 0;

foreach ($list as $entry) {
$entry = trim((string)$entry);
if ($entry==='') continue;
$parts = array_filter(array_map('trim', explode(',', $entry)), static fn(string $p): bool => $p!=='');
foreach ($parts as $part) {
$addr = $part;
if (preg_match('/<([^>]+)>/', $part, $m)) $addr = trim($m[1]);
if ($addr==='' || !str_contains($addr, '@')) {
$invalid++;
continue;
}
if (filter_var($addr, FILTER_VALIDATE_EMAIL)===false) {
$invalid++;
continue;
}
$valid++;
if ($looksPlaceholder($part) || $looksPlaceholder($addr)) $placeholder++;
}
}

if ($valid<1) {
$fail("{$key} does not contain any valid mailbox.");
return;
}
if ($invalid>0) $warn("{$key} contains {$invalid} malformed mailbox entr".($invalid===1 ? 'y.' : 'ies.'));
if ($placeholder>0) $fail("{$key} contains placeholder addresses (example.com/localhost).");
else $ok("{$key} has {$valid} valid mailbox entr".($valid===1 ? 'y.' : 'ies.'));
};

$checkRecipients($cfg, 'to_self', $ok, $warn, $fail, $looksPlaceholder);
$checkRecipients($cfg, 'to_recipients', $ok, $warn, $fail, $looksPlaceholder);

$mailFrom = trim((string)($cfg['mail_from'] ?? ''));
if ($mailFrom==='') $fail('mail_from is empty.');
elseif ($looksPlaceholder($mailFrom)) $fail('mail_from contains placeholder/local host value.');
else $ok('mail_from is set.');

$sendmailPath = trim((string)($cfg['sendmail_path'] ?? '/usr/sbin/sendmail'));
if ($sendmailPath==='') $fail('sendmail_path is empty.');
elseif (!is_file($sendmailPath)) $fail("sendmail_path not found: {$sendmailPath}");
elseif (!is_executable($sendmailPath)) $fail("sendmail_path is not executable: {$sendmailPath}");
else $ok("sendmail_path executable: {$sendmailPath}");

$logMode = strtolower(trim((string)($cfg['log_mode'] ?? 'both')));
$allowedLogModes = ['none', 'syslog', 'file', 'both'];
if (!in_array($logMode, $allowedLogModes, true)) $fail("log_mode must be one of: none, syslog, file, both (got: {$logMode})");
else {
if ($logMode==='none') $warn('log_mode=none: logging disabled (not recommended for production).');
else $ok("log_mode set to {$logMode}.");

if ($logMode==='file' || $logMode==='both') {
$logFile = trim((string)($cfg['log_file'] ?? ''));
if ($logFile==='') $logFile = $stateDir.'/'.$logFileName;
$logDir = dirname($logFile);
if (is_dir($logDir) && is_writable($logDir)) $ok("log target directory writable: {$logDir}");
elseif (!is_dir($logDir)) $warn("log target directory does not exist yet (will be created on demand): {$logDir}");
else $warn("log target directory exists but is not writable for current user: {$logDir}");
}
}

$rateLimitDir = $cfg['rate_limit_dir'] ?? null;
if (!is_string($rateLimitDir) || trim($rateLimitDir)==='') $rateLimitDir = $stateDir.'/ratelimit';
$rateLimitDir = rtrim((string)$rateLimitDir, '/');
if ($rateLimitDir==='') $rateLimitDir = $stateDir.'/ratelimit';

if (!empty($cfg['rate_limit_enabled'])) {
if (is_dir($rateLimitDir) && is_writable($rateLimitDir)) $ok("rate_limit_dir writable: {$rateLimitDir}");
elseif (!is_dir($rateLimitDir)) $warn("rate_limit_dir does not exist yet (will be created on demand): {$rateLimitDir}");
else $warn("rate_limit_dir exists but is not writable for current user: {$rateLimitDir}");
}
}

if (!$cfg) {
if (is_file($libPath) && is_readable($libPath)) $ok("Found {$libFileName}: {$libPath}");
else $fail("Missing/unreadable {$libFileName}: {$libPath}");
}

if (is_string($webUser) && $webUser!=='') {
$ok("Web user permission check requested for: {$webUser}");

if (!function_exists('posix_getpwnam')) $warn('POSIX functions unavailable: cannot validate --web-user permissions in this PHP build.');
else {
$pw = posix_getpwnam($webUser);
if (!is_array($pw)) $fail("Web user not found: {$webUser}");
else {
$uid = (int)($pw['uid'] ?? -1);
$primaryGid = (int)($pw['gid'] ?? -1);
$gids = [];
if ($primaryGid>=0) $gids[$primaryGid] = true;

if (function_exists('posix_getgrall')) {
$allGroups = posix_getgrall();
if (is_array($allGroups)) {
foreach ($allGroups as $group) {
$gid = (int)($group['gid'] ?? -1);
$members = $group['members'] ?? [];
if ($gid>=0 && is_array($members) && in_array($webUser, $members, true)) $gids[$gid] = true;
}
}
} else $warn('posix_getgrall unavailable: supplementary groups not evaluated for --web-user.');

$gidList = array_keys($gids);
$ok("Resolved web user {$webUser} (uid={$uid}, gids=".implode(',', $gidList).').');

$hasPerm = static function(string $path, int $uid, array $gids, int $needBits): ?bool {
if (!file_exists($path)) return null;
$perms = fileperms($path);
$owner = fileowner($path);
$group = filegroup($path);
if ($perms===false || $owner===false || $group===false) return false;

$mode = $perms & 0777;
$owner = (int)$owner;
$group = (int)$group;
$gids = array_values(array_map('intval', $gids));

if ($uid===$owner) $granted = ($mode>>6) & 0x7;
elseif (in_array($group, $gids, true)) $granted = ($mode>>3) & 0x7;
else $granted = $mode & 0x7;

return (($granted & $needBits)===$needBits);
};

$requirePathPerm = static function(string $path, int $needBits, string $label, bool $failOnMissing) use ($uid, $gidList, $hasPerm, $ok, $warn, $fail): bool {
$res = $hasPerm($path, $uid, $gidList, $needBits);
if ($res===null) {
if ($failOnMissing) {
$fail("Missing path for web-user check ({$label}): {$path}");
return false;
}
$warn("Path not present yet ({$label}): {$path}");
return false;
}
if ($res===true) {
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

$stateJsonPath = $stateDir.'/'.$stateFileName;
$lockPath = $stateDir.'/'.$lockFileName;

$stateJsonPresent = file_exists($stateJsonPath);
if ($stateJsonPresent) {
$requirePathPerm($stateJsonPath, 0x4, "{$stateFileName} (read)", true);
if ($stateDirOkWx) $ok("{$stateFileName} update path likely works (write via tmp+rename in state dir).");
else $fail("{$stateFileName} update path likely blocked because state dir lacks w+x for web user.");
} else $warn("{$stateFileName} does not exist yet (expected before first initialise).");

$lockPresent = file_exists($lockPath);
if ($lockPresent) $requirePathPerm($lockPath, 0x6, "{$lockFileName} (read+write for c+)", true);
else {
if ($stateDirOkWx) $ok("{$lockFileName} missing is acceptable; web user can likely create it (state dir has w+x).");
else $fail("{$lockFileName} missing and state dir lacks w+x, so web user likely cannot create lock file.");
}

if (!$stateDirOkRx) $fail('Without state dir r+x, web endpoint traversal/read will fail for web user.');
}
}
}

echo "Summary: {$okCount} OK, {$warnCount} WARN, {$failCount} FAIL\n";

if ($failCount>0) {
echo "Result: NOT READY FOR GOLIVE\n";
return 2;
}
if ($warnCount>0) {
echo "Result: READY WITH WARNINGS\n";
return 1;
}
echo "Result: READY\n";
return 0;
}

function dm_state_make_initial(array $cfg, int $now, int $checkInterval, int $confirmWindow): array {
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

function dm_state_start_cycle(array $cfg, array &$state, int $now, int $checkInterval, int $confirmWindow): array {
$token = dm_make_token($cfg);
return dm_state_apply_cycle($state, $now, $checkInterval, $confirmWindow, $token);
}

$cmd = $argv[1] ?? '';
if (!in_array($cmd, ['tick', 'check'], true)) {
fwrite(STDERR, "Usage: php totmann-tick.php tick\n");
fwrite(STDERR, "       php totmann-tick.php check [--web-user=<WEB_USER>]\n");
exit(2);
}
if ($cmd==='tick' && count($argv)>2) {
fwrite(STDERR, "Usage: php totmann-tick.php tick\n");
exit(2);
}

if ($cmd==='check') {
$webUser = null;
$args = array_slice($argv, 2);

for ($i=0; $i<count($args); $i++) {
$arg = (string)$args[$i];
if (str_starts_with($arg, '--web-user=')) {
$webUser = trim(substr($arg, strlen('--web-user=')));
if ($webUser==='') {
fwrite(STDERR, "ERROR: --web-user requires a non-empty value\n");
exit(2);
}
continue;
}

if ($arg==='--web-user') {
$next = $args[$i+1] ?? '';
if (!is_string($next) || trim($next)==='' || str_starts_with((string)$next, '--')) {
fwrite(STDERR, "ERROR: --web-user requires a value\n");
exit(2);
}
$webUser = trim((string)$next);
$i++;
continue;
}

fwrite(STDERR, "Unknown option for check: {$arg}\n");
fwrite(STDERR, "Usage: php totmann-tick.php check [--web-user=<WEB_USER>]\n");
exit(2);
}

exit(dm_preflight_check($stateDir, $webUser));
}

$configPath = $stateDir.'/totmann.inc.php';
try {
$cfg = dm_bootstrap_load_config_raw($configPath);
$libFile = dm_bootstrap_file_name($cfg, 'lib_file');
$libPath = $stateDir.'/'.$libFile;
if (!is_file($libPath) || !is_readable($libPath)) throw new RuntimeException("missing/unreadable {$libFile} in {$stateDir}");
require $libPath;
} catch (Throwable $e) {
fwrite(STDERR, "BOOTSTRAP ERROR: ".$e->getMessage()."\n");
exit(1);
}
$cfg['state_dir'] = $stateDir;
try {
$runtimeCfg = dm_validate_runtime_config($cfg);
} catch (Throwable $e) {
fwrite(STDERR, "CONFIG ERROR: ".$e->getMessage()."\n");
exit(1);
}
$checkInterval = (int)$runtimeCfg['check_interval_seconds'];
$confirmWindow = (int)$runtimeCfg['confirm_window_seconds'];
$escalateGrace = (int)$runtimeCfg['escalate_grace_seconds'];
$remindEvery = (int)$runtimeCfg['remind_every_seconds'];
$missedCyclesBeforeFire = (int)$runtimeCfg['missed_cycles_before_fire'];
$ackEnabledCfg = (bool)$runtimeCfg['ack_enabled'];
$ackRemindEvery = max(60, (int)$runtimeCfg['escalate_ack_remind_every_seconds']);
$ackMaxReminds = (int)$runtimeCfg['escalate_ack_max_reminds'];

try {
$stateFile = dm_state_file($cfg);
$lockFile = dm_lock_file($cfg);
} catch (Throwable $e) {
fwrite(STDERR, "BOOTSTRAP ERROR: ".$e->getMessage()."\n");
exit(1);
}

$lockHandle = null;

try {
$lockHandle = dm_lock_open($lockFile);

$now = dm_now();
$state = dm_state_load($stateFile);

// Initialise state (first run)
if (empty($state)) {
$state = dm_state_make_initial($cfg, $now, $checkInterval, $confirmWindow);
dm_state_save($stateFile, $state);
dm_log($cfg, "Initialised state. Next check at ".dm_iso((int)$state['next_check_at']));
exit(0);
}

$createdAt = (int)($state['created_at'] ?? 0);
$cycleStart0 = (int)($state['cycle_start_at'] ?? 0);
$lastConfirm0 = (int)($state['last_confirm_at'] ?? 0);

if ($createdAt>0 && $cycleStart0===$createdAt && $lastConfirm0===$createdAt && empty($state['escalated_sent_at']) && (int)($state['missed_cycles'] ?? 0)===0 && ($now-$createdAt)>5) {
$state['last_confirm_at'] = 0;
dm_log($cfg, "Migrated initial state: last_confirm_at reset to 0 (was equal to created_at).");
}

// Sanity: clock went backwards -> do nothing risky in this tick
if (isset($state['last_tick_at']) && $now+5<(int)$state['last_tick_at']) {
dm_log($cfg, "Clock moved backwards. now={$now}, last_tick_at={$state['last_tick_at']}. Skipping actions.");
$state['last_tick_at'] = $now;
dm_state_save($stateFile, $state);
exit(0);
}

$state['last_tick_at'] = $now;

$nextCheck = (int)($state['next_check_at'] ?? 0);
$deadline = (int)($state['deadline_at'] ?? 0);

// If confirm token is missing: regenerate, but do NOT trigger escalation in this tick
if (empty($state['token']['id']) || empty($state['token']['sig'])) {
$state['token'] = dm_make_token($cfg);
dm_log($cfg, "Token was missing; regenerated.");
dm_state_save($stateFile, $state);
exit(0);
}

// Also guard against malformed/non-verifiable token values in state.
$tokenId = (string)($state['token']['id'] ?? '');
$tokenSig = (string)($state['token']['sig'] ?? '');
$tokenValid = false;
try {
$tokenValid = dm_token_valid($cfg, $tokenId, $tokenSig);
} catch (Throwable $e) { $tokenValid = false; }
if (!$tokenValid) {
$state['token'] = dm_make_token($cfg);
dm_log($cfg, "Token was invalid; regenerated.");
dm_state_save($stateFile, $state);
exit(0);
}

$cycleStartCurrent = (int)($state['cycle_start_at'] ?? 0);
$stateBroken = ($cycleStartCurrent<=0 || $nextCheck<=0 || $deadline<=0 || $deadline<=$nextCheck);
if ($stateBroken) {
dm_log($cfg, "State sanity recovery triggered (cycle_start_at={$cycleStartCurrent}, next_check_at={$nextCheck}, deadline_at={$deadline}).");
dm_state_start_cycle($cfg, $state, $now, $checkInterval, $confirmWindow);
$state['missed_cycles'] = 0;
$state['missed_cycle_deadline'] = null;
dm_state_clear_escalation($state);
$state['last_tick_at'] = $now;
dm_state_save($stateFile, $state);
exit(0);
}

// 1) Reminder phase: from next_check_at until deadline_at
if ($now>=$nextCheck && $now<$deadline) {
$nextReminder = (int)($state['next_reminder_at'] ?? $nextCheck);

// Defensive: if next_reminder_at is behind the window start, bump it forward.
if ($nextReminder<$nextCheck) $nextReminder = $nextCheck;

// Defensive: if next_reminder_at is in the past, send now and then schedule from "now".
if ($now>=$nextReminder) {
$confirmUrl = dm_confirm_url($cfg, (array)$state['token']);

$body = str_replace(['{CONFIRM_URL}', '{DEADLINE_ISO}', '{CYCLE_START_ISO}'], [$confirmUrl, dm_mail_dt($cfg, $deadline), dm_mail_dt($cfg, (int)$state['cycle_start_at'])], (string)$cfg['body_reminder']);

dm_send_mail($cfg, (array)$cfg['to_self'], (string)$cfg['subject_reminder'], $body);
dm_log($cfg, "Sent reminder to self. next_reminder_at was {$nextReminder}");

$state['next_reminder_at'] = $now+$remindEvery;
}
}

// 2) Escalation: only after deadline+grace, only if NOT confirmed in this cycle
$grace = $escalateGrace;
$fireAt = $deadline+$grace;

$lastConfirm = (int)($state['last_confirm_at'] ?? 0);
$cycleStart = (int)($state['cycle_start_at'] ?? 0);

// "Confirmed this cycle" means: a confirm happened after the window opened.
// If confirmation happened before next_check_at, this cycle still counts as unconfirmed.
$confirmedThisCycle = ($lastConfirm>=$nextCheck && $lastConfirm>0);

if ($now>=$fireAt && !$confirmedThisCycle) {
if (!empty($state['escalated_sent_at'])) {
$ackEnabledState = ($ackEnabledCfg && !empty($cfg['base_url']));
$ackRecordedState = !empty($state['escalate_ack_at']);
$maxRemindsState = $ackMaxReminds;
$sentCountState = (int)($state['escalate_ack_sent_count'] ?? 0);
$shouldLogAlreadySent = true;
if ($ackEnabledState && $ackRecordedState) $shouldLogAlreadySent = false;
elseif ($ackEnabledState && $maxRemindsState>0 && $sentCountState>=$maxRemindsState) $shouldLogAlreadySent = false;
if ($shouldLogAlreadySent) dm_log($cfg, "Escalation already sent at ".dm_iso((int)$state['escalated_sent_at']).". Skipping.");
}
else {
// Count missed cycle only once per deadline (timer runs every minute)
$alreadyCounted = ((int)($state['missed_cycle_deadline'] ?? 0)===$deadline);
if ($alreadyCounted) dm_log($cfg, "Missed cycle already recorded for this deadline; skipping counter bump.");
else {
$state['missed_cycles'] = (int)($state['missed_cycles'] ?? 0)+1;
$state['missed_cycle_deadline'] = $deadline;
}

$threshold = $missedCyclesBeforeFire;
dm_log($cfg, "Missed cycle status (missed_cycles={$state['missed_cycles']}/{$threshold}).");

if ((int)$state['missed_cycles'] >= $threshold) {
// ACK is enabled only if explicitly enabled AND base_url exists
$ackEnabled = ($ackEnabledCfg && !empty($cfg['base_url']));
$ackUrl = '';
dm_state_reset_ack($state);

if ($ackEnabled) {
$ackToken = dm_make_token($cfg);
$state['escalate_ack_token'] = $ackToken;

$maxAckRemindsCfg = $ackMaxReminds;
if ($maxAckRemindsCfg>0) $state['escalate_ack_next_at'] = $now+$ackRemindEvery;
else $state['escalate_ack_next_at'] = null;

$ackUrl = dm_ack_url($cfg, $ackToken);
}

$body = dm_render_escalate_body($cfg, $lastConfirm, $cycleStart, $deadline, $ackUrl, $ackEnabled);

dm_send_mail($cfg, (array)$cfg['to_recipients'], (string)$cfg['subject_escalate'], $body);
$state['escalated_sent_at'] = $now;

dm_log($cfg, "Escalation mail sent to recipients.");
} else {
// Conservative: start a new cycle instead of escalating
$timing = dm_state_start_cycle($cfg, $state, $now, $checkInterval, $confirmWindow);

// reset so the next cycle counts cleanly
$state['missed_cycle_deadline'] = null;

dm_log($cfg, "Started new conservative cycle after miss. Next check at ".dm_iso((int)$timing['next_check_at']));
}
}
}

// 3) Escalation ACK reminders (re-send until one recipient acknowledges)
$ackEnabled = ($ackEnabledCfg && !empty($cfg['base_url']));
if ($ackEnabled && !empty($state['escalated_sent_at']) && empty($state['escalate_ack_at'])) {
$maxReminds = $ackMaxReminds;
if ($maxReminds<=0) {
if (!empty($state['escalate_ack_next_at'])) {
$state['escalate_ack_next_at'] = null;
dm_log($cfg, "ACK reminders disabled (escalate_ack_max_reminds<=0).");
}
} else {
$sentCount = (int)($state['escalate_ack_sent_count'] ?? 0);
$nextAt = (int)($state['escalate_ack_next_at'] ?? 0);

if ($sentCount>=$maxReminds) {
if ($nextAt>0) {
$state['escalate_ack_next_at'] = null;
dm_log($cfg, "Escalation ACK reminder limit reached ({$sentCount}/{$maxReminds}). Reminder logging paused until ACK or reset.");
}
} elseif ($nextAt>0 && $now>=$nextAt) {
// hard fail: reminders make no sense without an issued token from the initial escalation
if (empty($state['escalate_ack_token']) || empty($state['escalate_ack_token']['id']) || empty($state['escalate_ack_token']['sig'])) {
dm_log($cfg, "ack: token missing during reminder phase; not sending reminder.");
$state['escalate_ack_next_at'] = null;
dm_state_save($stateFile, $state);
exit(0);
}

$ackToken = (array)$state['escalate_ack_token'];
$ackUrl = dm_ack_url($cfg, $ackToken);

$body = dm_render_escalate_body($cfg, (int)($state['last_confirm_at'] ?? 0), (int)($state['cycle_start_at'] ?? 0), (int)($state['deadline_at'] ?? 0), $ackUrl, true);

dm_send_mail($cfg, (array)$cfg['to_recipients'], (string)$cfg['subject_escalate'], $body);

$state['escalate_ack_sent_count'] = $sentCount+1;
$state['escalate_ack_next_at'] = $now+$ackRemindEvery;

dm_log($cfg, "Escalation ACK reminder sent (count={$state['escalate_ack_sent_count']}/{$maxReminds}).");
}
}
}

dm_state_save($stateFile, $state);
exit(0); } catch (Throwable $e) {
dm_log($cfg, "ERROR: ".$e->getMessage());
// Errors must NOT trigger escalation. Fail-closed against false positives.
exit(1); } finally { if (is_resource($lockHandle)) fclose($lockHandle); }
