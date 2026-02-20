<?php

declare(strict_types=1);

/**
 * totmannschalter – web endpoint
 *
 * Project: https://github.com/MacSteini/totmannschalter
 * Licence: MIT (see LICENCE)
 *
 * State dir resolution order:
 * 1) ENV: TOTMANN_STATE_DIR
 * 2) define('TOTMANN_STATE_DIR', '/var/lib/totmann'); // fallback if you cannot set ENV
 *
 * If neither exists, endpoint stays neutral (no implicit fallback to webroot/config state_dir).
 */

// Fallback define for installations without webserver ENV injection.
// Adjust path for your host, or comment out when ENV TOTMANN_STATE_DIR is provided.
define('TOTMANN_STATE_DIR', '/var/lib/totmann');

function dm_resolve_state_dir(): ?string {
$v = rtrim((string)getenv('TOTMANN_STATE_DIR'), '/');
if ($v!=='') return $v;

if (defined('TOTMANN_STATE_DIR')) {
$v = rtrim((string)constant('TOTMANN_STATE_DIR'), '/');
return $v!=='' ? $v : null;
}

return null;
}

function dm_web_bootstrap_load_config(string $configPath): array {
if (!is_file($configPath) || !is_readable($configPath)) throw new RuntimeException("missing/unreadable totmann.inc.php: {$configPath}");
$cfg = require $configPath;
if (!is_array($cfg)) throw new RuntimeException('totmann.inc.php must return an array');
return $cfg;
}

function dm_web_bootstrap_file_name(array $cfg, string $key): string {
$v = trim((string)($cfg[$key] ?? ''));
if ($v==='') throw new RuntimeException("Missing config key: {$key}");
if (str_contains($v, '/') || str_contains($v, '\\')) throw new RuntimeException("Invalid {$key}: filename must not contain slashes");
if ($v==='.' || $v==='..' || str_contains($v, '..') || preg_match('/[[:cntrl:]]/', $v)) throw new RuntimeException("Invalid {$key}: traversal/control chars not allowed");
return $v;
}

function dm_web_bootstrap_optional_file_name(array $cfg, string $key): ?string {
if (!array_key_exists($key, $cfg)) return null;
$v = trim((string)$cfg[$key]);
if ($v==='') return null;
if (str_contains($v, '/') || str_contains($v, '\\')) throw new RuntimeException("Invalid {$key}: filename must not contain slashes");
if ($v==='.' || $v==='..' || str_contains($v, '..') || preg_match('/[[:cntrl:]]/', $v)) throw new RuntimeException("Invalid {$key}: traversal/control chars not allowed");
return $v;
}

function dm_headers_common(): void {
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
}

function dm_h(string $v): string { return htmlspecialchars($v, ENT_QUOTES); }

function dm_web_css_file_set(?string $file): void { $GLOBALS['dm_web_css_file'] = $file; }
function dm_web_css_file_get(): ?string {
$f = $GLOBALS['dm_web_css_file'] ?? null;
return is_string($f) && $f!=='' ? $f : null;
}

function dm_confirm_details_dt(array $cfg, int $ts): string {
static $tzCache = [];
$tzName = (string)($cfg['mail_timezone'] ?? 'UTC');
if ($tzName==='') $tzName = 'UTC';
if (!isset($tzCache[$tzName])) {
try { $tzCache[$tzName] = new DateTimeZone($tzName); }
catch (Throwable $e) { $tzCache[$tzName] = new DateTimeZone('UTC'); }
}
$tz = $tzCache[$tzName];
$dt = (new DateTimeImmutable('@'.$ts))->setTimezone($tz);
return $dt->format('j F Y \a\t H:i:s');
}

function dm_human_duration(int $seconds): string {
static $units = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60], ['second', 1]];
$seconds = max(0, $seconds);
$parts = [];
foreach ($units as [$name, $size]) {
if ($seconds<$size) continue;
$qty = intdiv($seconds, $size);
$seconds -= $qty*$size;
$parts[] = $qty.' '.$name.($qty===1 ? '' : 's');
}
if ($parts===[]) return '0 seconds';
$count = count($parts);
if ($count===1) return $parts[0];
if ($count===2) return $parts[0].' and '.$parts[1];
$last = array_pop($parts);
return implode(', ', $parts).' and '.$last;
}

function dm_cfg_nonneg_int(array $cfg, string $key): int { return max(0, (int)($cfg[$key] ?? 0)); }

function dm_cycles_required_text(int $count): string {
if ($count===1) return '1 missed cycle is required before escalation can trigger.';
return $count.' missed cycles are required before escalation can trigger.';
}

function dm_cycles_current_text(int $count): string {
if ($count===0) return 'No missed cycles are currently recorded in state.';
if ($count===1) return '1 missed cycle is currently recorded in state.';
return $count.' missed cycles are currently recorded in state.';
}

function dm_render_page(string $title, string $bodyHtml): void {
dm_headers_common();
$cssFile = dm_web_css_file_get();
$cssLink = $cssFile!==null ? '<link rel="stylesheet" href="'.dm_h($cssFile).'">' : '';
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'.dm_h($title).'</title>'.$cssLink.'</head><body><main class="dm_shell"><section class="dm_card">'.$bodyHtml.'</section></main></body></html>';
exit;
}

function dm_render_neutral(): void {
http_response_code(200);
dm_render_page('Request received', '<h1>Request received</h1><p>Your request has been received.</p><p>For security reasons, no further details are shown on this page.</p>');
}

function dm_render_confirm_prompt(string $id, string $sig): void {
http_response_code(200);
dm_render_page('Confirm', '<h1>Confirm</h1><p>Please click the button to confirm.</p><form method="post"><input type="hidden" name="a" value="confirm"><input type="hidden" name="id" value="'.dm_h($id).'"><input type="hidden" name="sig" value="'.dm_h($sig).'"><button type="submit">Confirm</button></form>');
}

function dm_render_confirm_ok(array $cfg, array $state): void {
http_response_code(200);
$body = '<h1>Confirmed!</h1>';
if (!empty($cfg['show_success_details'])) {
$items = [];
if (isset($state['cycle_start_at'])) $items[] = 'The current cycle started on '.dm_h(dm_confirm_details_dt($cfg, (int)$state['cycle_start_at'])).'.';
if (isset($state['last_confirm_at'])) $items[] = 'The last successful confirmation was recorded on '.dm_h(dm_confirm_details_dt($cfg, (int)$state['last_confirm_at'])).'.';
if (isset($state['next_check_at'])) $items[] = 'The confirmation window opens on '.dm_h(dm_confirm_details_dt($cfg, (int)$state['next_check_at'])).'.';
if (isset($state['deadline_at'])) $items[] = 'The confirmation deadline for this cycle is '.dm_h(dm_confirm_details_dt($cfg, (int)$state['deadline_at'])).'.';
if (isset($state['next_reminder_at'])) $items[] = 'The next reminder is scheduled for '.dm_h(dm_confirm_details_dt($cfg, (int)$state['next_reminder_at'])).'.';
$checkInterval = dm_cfg_nonneg_int($cfg, 'check_interval_seconds');
$confirmWindow = dm_cfg_nonneg_int($cfg, 'confirm_window_seconds');
$remindEvery = dm_cfg_nonneg_int($cfg, 'remind_every_seconds');
$grace = dm_cfg_nonneg_int($cfg, 'escalate_grace_seconds');
$missedThreshold = dm_cfg_nonneg_int($cfg, 'missed_cycles_before_fire');
$missedCurrent = max(0, (int)($state['missed_cycles'] ?? 0));
$items[] = dm_h(dm_human_duration($checkInterval)).' before the confirmation window opens.';
$items[] = dm_h(dm_human_duration($confirmWindow)).' for the confirmation window.';
$items[] = 'Reminders repeat every '.dm_h(dm_human_duration($remindEvery)).' while the window is open.';
$items[] = 'An additional '.dm_h(dm_human_duration($grace)).' grace period after the deadline before escalation is considered.';
$items[] = dm_h(dm_cycles_required_text($missedThreshold));
$items[] = dm_h(dm_cycles_current_text($missedCurrent));
$body .= '<h2>Cycle reset.</h2><ul><li>'.implode('</li><li>', $items).'</li></ul>';
}
dm_render_page('Confirmed', $body);
}

function dm_render_ack_ok(): void {
http_response_code(200);
dm_render_page('Request received', '<h1>Request received</h1><p>Your acknowledgement has been recorded successfully.</p><p>You will not receive further escalation emails for this incident.</p><p>Please now act in accordance with the sender’s stated wishes and follow the instructions provided in the received message.</p><p>Thank you!</p>');
}

function dm_render_error(string $code): void {
http_response_code(200);
dm_render_page('Request received', '<h1>Something went wrong</h1><p>Please try again later.</p><p class="dm_note">Error code: <code>'.dm_h($code).'</code></p>');
}

// --- bootstrap (stealth by default) ---

$resolvedDir = dm_resolve_state_dir();
if ($resolvedDir===null) dm_render_neutral();

$configPath = rtrim($resolvedDir, '/').'/totmann.inc.php';
try {
$cfg = dm_web_bootstrap_load_config($configPath);
$libFile = dm_web_bootstrap_file_name($cfg, 'lib_file');
$cssFile = dm_web_bootstrap_optional_file_name($cfg, 'web_css_file');
dm_web_css_file_set($cssFile);
$libPath = rtrim($resolvedDir, '/').'/'.$libFile;
if (!is_file($libPath) || !is_readable($libPath)) throw new RuntimeException("missing/unreadable {$libFile}");
require $libPath;
} catch (Throwable $e) { dm_render_neutral(); }

// Runtime state dir priority:
// 1) explicit runtime override from ENV/define
// 2) config state_dir
// 3) directory of loaded totmann.inc.php
$cfgStateDir = rtrim((string)($cfg['state_dir'] ?? ''), '/');
if ($resolvedDir!==null) $stateDir = rtrim($resolvedDir, '/');
elseif ($cfgStateDir!=='') $stateDir = $cfgStateDir;
else $stateDir = rtrim(dirname($configPath), '/');
if ($stateDir==='') dm_render_neutral();
$cfg['state_dir'] = $stateDir;

try {
$stateFile = dm_state_file($cfg);
$lockFile = dm_lock_file($cfg);
} catch (Throwable $e) { dm_render_neutral(); }

$now = dm_now();
$ip = dm_client_ip($cfg);

// Rate limit first (stealth)
if (!dm_rate_limit_check($cfg, $ip, $now)) {
dm_log($cfg, "web: ratelimited ip={$ip}");
dm_render_neutral();
}

$a = (string)(($_POST['a'] ?? $_GET['a'] ?? 'confirm'));
$id = (string)(($_POST['id'] ?? $_GET['id'] ?? ''));
$sig = (string)(($_POST['sig'] ?? $_GET['sig'] ?? ''));
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (!in_array($a, ['confirm', 'ack'], true)) dm_render_neutral();

// Token must be validly signed (stealth on invalid)
$tokenOk = false;
try {
$tokenOk = dm_token_valid($cfg, $id, $sig);
} catch (Throwable $e) {
dm_log($cfg, "web: token validation failed a={$a} ip={$ip} exception=".$e->getMessage());
$tokenOk = false;
}
if (!$tokenOk) {
if (!empty($cfg['stealth_neutral_for_invalid'])) dm_render_neutral();
dm_render_error('E_TOKEN_INVALID_'.bin2hex(random_bytes(4)));
}

$lockHandle = null;
$isCurrentRequest = false;

try {
$lockHandle = dm_lock_open($lockFile);

$state = dm_state_load($stateFile);
if (empty($state)) {
dm_log($cfg, "web: state missing (valid-signed request) a={$a} ip={$ip}");
dm_render_neutral();
}

if ($a==='confirm') {
$cur = $state['token'] ?? [];
$isCurrent = (($cur['id'] ?? '')===$id) && (($cur['sig'] ?? '')===$sig);
$isCurrentRequest = $isCurrent;

// Stale/non-current token => stealth (optional)
if (!$isCurrent) {
dm_log($cfg, "confirm: stale-or-noncurrent token used ip={$ip}");
if (!empty($cfg['stealth_level_2_neutral_on_stale'])) dm_render_neutral();
dm_render_error('E_TOKEN_STALE_'.bin2hex(random_bytes(4)));
}

// After escalation fired, confirmation must no longer restart the cycle.
if (!empty($state['escalated_sent_at'])) {
dm_log($cfg, "confirm: blocked after escalation ip={$ip}");
if (!empty($cfg['stealth_level_2_neutral_on_stale'])) dm_render_neutral();
dm_render_error('E_CONFIRM_BLOCKED_'.bin2hex(random_bytes(4)));
}

// 2-step confirm to defeat mail link scanners: GET shows button, POST confirms
if ($method!=='POST') dm_render_confirm_prompt($id, $sig);

// Reset cycle
$state['last_confirm_at'] = $now;
$state['missed_cycles'] = 0;
$state['missed_cycle_deadline'] = null;
dm_state_clear_escalation($state);

$token = dm_make_token($cfg);
$timing = dm_state_apply_cycle($state, $now, (int)($cfg['check_interval_seconds'] ?? 0), (int)($cfg['confirm_window_seconds'] ?? 0), $token);

dm_state_save($stateFile, $state);
dm_log($cfg, "confirm: OK ip={$ip} next_check_at=".dm_iso((int)$timing['next_check_at']));

dm_render_confirm_ok($cfg, $state);
}

// ACK path
$ackEnabled = (!empty($cfg['escalate_ack_enabled']) && !empty($cfg['base_url']));
if (!$ackEnabled) dm_render_neutral();

$cur = $state['escalate_ack_token'] ?? [];
$isCurrent = (($cur['id'] ?? '')===$id) && (($cur['sig'] ?? '')===$sig);
$isCurrentRequest = $isCurrent;

if (!$isCurrent) {
dm_log($cfg, "ack: stale-or-noncurrent token used ip={$ip}");
if (!empty($cfg['stealth_level_2_neutral_on_stale'])) dm_render_neutral();
dm_render_error('E_TOKEN_STALE_'.bin2hex(random_bytes(4)));
}

$state['escalate_ack_at'] = $now;
$state['escalate_ack_next_at'] = null;
dm_state_save($stateFile, $state);
dm_log($cfg, "ack: OK ip={$ip}");
dm_log($cfg, "ack: escalation acknowledged; escalation-state logging paused until reset.");

dm_render_ack_ok();

} catch (Throwable $e) {
$err = ($a==='ack') ? 'E_ACK_FAIL_' : 'E_CONFIRM_FAIL_';
$err .= bin2hex(random_bytes(4));

dm_log($cfg, "web: {$err} a={$a} ip={$ip} exception=".$e->getMessage());
if ($isCurrentRequest) dm_render_error($err);
dm_render_neutral();
} finally {
if (is_resource($lockHandle)) fclose($lockHandle);
}
