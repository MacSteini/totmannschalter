<?php

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

declare(strict_types=1);

// Fallback define for installations without webserver ENV injection.
define('TOTMANN_STATE_DIR', '/var/lib/totmann');

function dm_resolve_state_dir(): ?string
{
    $v = rtrim((string)getenv('TOTMANN_STATE_DIR'), '/');
    if ($v !== '') {
        return $v;
    }

    if (defined('TOTMANN_STATE_DIR')) {
        $v = rtrim((string)constant('TOTMANN_STATE_DIR'), '/');
        return $v;
    }

    return null;
}

function dm_web_bootstrap_load_config(string $configPath): array
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

function dm_web_bootstrap_file_name(array $cfg, string $key): string
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

function dm_web_bootstrap_optional_file_name(array $cfg, string $key): ?string
{
    if (!array_key_exists($key, $cfg)) {
        return null;
    }
    $v = trim((string)$cfg[$key]);
    if ($v === '') {
        return null;
    }
    if (str_contains($v, '/') || str_contains($v, '\\')) {
        throw new RuntimeException("Invalid {$key}: filename must not contain slashes");
    }
    if ($v === '.' || $v === '..' || str_contains($v, '..') || preg_match('/[[:cntrl:]]/', $v)) {
        throw new RuntimeException("Invalid {$key}: traversal/control chars not allowed");
    }
    return $v;
}

function dm_headers_common(): void
{
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
}

function dm_h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dm_web_css_file_set(?string $file): void
{
    $GLOBALS['dm_web_css_file'] = $file;
}

function dm_web_css_file_get(): ?string
{
    $f = $GLOBALS['dm_web_css_file'] ?? null;
    return is_string($f) && $f !== '' ? $f : null;
}

function dm_web_fallback_catalog(): array
{
    return [
        'locale' => 'en-US',
        'html_lang' => 'en-US',
        'date_template' => '{month} {day}, {year} at {time}',
        'months' => [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ],
        'duration_zero' => '0 seconds',
        'duration_pair_glue' => ' and ',
        'duration_middle_glue' => ', ',
        'duration_last_glue' => ' and ',
        'duration_units' => [
            'year' => ['one' => 'year', 'other' => 'years'],
            'month' => ['one' => 'month', 'other' => 'months'],
            'week' => ['one' => 'week', 'other' => 'weeks'],
            'day' => ['one' => 'day', 'other' => 'days'],
            'hour' => ['one' => 'hour', 'other' => 'hours'],
            'minute' => ['one' => 'minute', 'other' => 'minutes'],
            'second' => ['one' => 'second', 'other' => 'seconds'],
        ],
        'texts' => [
            'page_neutral_title' => 'This page is not available.',
            'page_neutral_heading' => 'This page is not available.',
            'page_neutral_body_1' => 'No further information is shown through this URL.',
            'page_neutral_body_2' => 'You may close this page now.',
            'page_confirm_title' => 'Still alive?',
            'page_confirm_heading' => 'Please check in…',
            'page_confirm_intro' => 'If all is well, please click the button below.',
            'page_confirm_button' => 'Everything is okay!',
            'page_confirm_ok_title' => 'Confirmation saved.',
            'page_confirm_ok_heading' => 'Thank you.',
            'page_confirm_ok_intro' => 'The cycle has been reset…',
            'page_confirm_ok_details_heading' => 'What happens next:',
            'page_ack_ok_title' => 'Acknowledgment saved.',
            'page_ack_ok_heading' => 'Thank you.',
            'page_ack_ok_body_1' => 'The message has been marked as received.',
            'page_ack_ok_body_2' => 'You will not receive any further emails. The dead man’s switch has been shut down.',
            'page_ack_ok_body_3' => 'Please now follow the wishes and instructions in the email you received.',
            'page_ack_ok_body_4' => 'Important! If the email included a download link, make sure that you have downloaded that document.',
            'page_error_title' => 'That did not work just now.',
            'page_error_heading' => 'That did not work just now.',
            'page_error_body_1' => 'Please try again.',
            'page_error_body_2' => 'If the problem persists, keep the original message so the sender or operator can investigate.',
            'page_error_code_label' => 'Reference code',
            'page_download_unavailable_title' => 'This URL is not valid.',
            'page_download_unavailable_heading' => 'This URL is not valid.',
            'page_download_unavailable_body_1' => 'The link to this download has expired, been used already, or is no longer valid.',
            'page_download_unavailable_body_2' => 'Please keep the original message for reference.',
            'page_download_unavailable_body_3' => '',
            'detail_cycle_started' => 'The current cycle started on {datetime}.',
            'detail_last_confirm' => 'The last successful confirmation was recorded on {datetime}.',
            'detail_window_opens' => 'The confirmation window opens on {datetime}.',
            'detail_deadline' => 'The confirmation deadline for this cycle is {datetime}.',
            'detail_next_reminder' => 'The next reminder is scheduled for {datetime}.',
            'detail_window_opens_after' => '{duration} before the confirmation window opens.',
            'detail_window_length' => '{duration} for the confirmation window.',
            'detail_reminders_repeat' => 'Reminders repeat every {duration} while the window is open.',
            'detail_grace' => 'After the deadline, there is an additional grace period of {duration} before escalation is considered.',
            'detail_missed_required_one' => '1 missed cycle is required before escalation can trigger.',
            'detail_missed_required_other' => '{count} missed cycles are required before escalation can trigger.',
            'detail_missed_current_zero' => 'No missed cycles are currently recorded in state.',
            'detail_missed_current_one' => '1 missed cycle is currently recorded in state.',
            'detail_missed_current_other' => '{count} missed cycles are currently recorded in state.',
        ],
    ];
}

function dm_web_catalog_set(array $catalog): void
{
    $GLOBALS['dm_web_catalog'] = $catalog;
}

function dm_web_catalog(): array
{
    $catalog = $GLOBALS['dm_web_catalog'] ?? null;
    if (is_array($catalog)) {
        return $catalog;
    }
    return dm_web_fallback_catalog();
}

function dm_web_text(string $key, array $vars = []): string
{
    $catalog = dm_web_catalog();
    $fallback = dm_web_fallback_catalog();
    $text = $catalog['texts'][$key] ?? $fallback['texts'][$key] ?? $key;
    foreach ($vars as $name => $value) {
        $text = str_replace('{' . $name . '}', (string)$value, $text);
    }
    return $text;
}

function dm_web_accept_language_candidates(?string $header): array
{
    $header = trim((string)$header);
    if ($header === '') {
        return [];
    }

    $items = [];
    foreach (explode(',', $header) as $index => $chunk) {
        $part = trim($chunk);
        if ($part === '') {
            continue;
        }

        $q = 1.0;
        if (preg_match('/^([^;]+);\s*q=([0-9.]+)$/i', $part, $m)) {
            $part = trim($m[1]);
            $q = (float)$m[2];
        }

        $tag = str_replace('_', '-', strtolower($part));
        if ($tag === '' || $tag === '*') {
            continue;
        }

        $items[] = ['tag' => $tag, 'q' => $q, 'index' => $index];
    }

    usort($items, static function (array $a, array $b): int {
        if ($a['q'] === $b['q']) {
            return $a['index'] <=> $b['index'];
        }
        return ($a['q'] > $b['q']) ? -1 : 1;
    });

    return array_map(static fn(array $item): string => (string)$item['tag'], $items);
}

function dm_web_pick_locale(?string $header): string
{
    $supported = [
        'de-de' => 'de-DE',
        'en-gb' => 'en-GB',
        'en-us' => 'en-US',
        'fr-fr' => 'fr-FR',
        'it-it' => 'it-IT',
        'es-es' => 'es-ES',
    ];
    $baseMap = [
        'de' => 'de-DE',
        'en' => 'en-US',
        'fr' => 'fr-FR',
        'it' => 'it-IT',
        'es' => 'es-ES',
    ];

    foreach (dm_web_accept_language_candidates($header) as $candidate) {
        if (isset($supported[$candidate])) {
            return $supported[$candidate];
        }

        $base = explode('-', $candidate, 2)[0];
        if (isset($baseMap[$base])) {
            return $baseMap[$base];
        }
    }

    return 'en-US';
}

function dm_web_catalog_valid(array $catalog): bool
{
    return isset($catalog['locale'], $catalog['html_lang'], $catalog['date_template'], $catalog['months'], $catalog['duration_units'], $catalog['texts'])
        && is_array($catalog['months'])
        && is_array($catalog['duration_units'])
        && is_array($catalog['texts']);
}

function dm_web_load_catalog(array $cfg): array
{
    $fallback = dm_web_fallback_catalog();
    $locale = dm_web_pick_locale($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    $dir = dm_l18n_dir($cfg);
    $paths = [];

    if ($locale !== 'en-US') {
        $paths[] = $dir . '/' . $locale . '.php';
    }
    $paths[] = $dir . '/en-US.php';

    foreach ($paths as $path) {
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }
        $loaded = require $path;
        if (!is_array($loaded) || !dm_web_catalog_valid($loaded)) {
            continue;
        }
        return array_replace_recursive($fallback, $loaded);
    }

    return $fallback;
}

function dm_confirm_details_dt(array $cfg, int $ts): string
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

    $dt = (new DateTimeImmutable('@' . $ts))->setTimezone($tzCache[$tzName]);
    $catalog = dm_web_catalog();
    $months = is_array($catalog['months'] ?? null) ? $catalog['months'] : [];
    $month = (string)($months[(int)$dt->format('n')] ?? $dt->format('F'));

    return str_replace(
        ['{day}', '{month}', '{year}', '{time}'],
        [$dt->format('j'), $month, $dt->format('Y'), $dt->format('H:i:s')],
        (string)($catalog['date_template'] ?? '{month} {day}, {year} at {time}')
    );
}

function dm_human_duration(int $seconds): string
{
    $catalog = dm_web_catalog();
    $units = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
        ['second', 1],
    ];

    $seconds = max(0, $seconds);
    $parts = [];
    foreach ($units as [$name, $size]) {
        if ($seconds < $size) {
            continue;
        }
        $qty = intdiv($seconds, $size);
        $seconds -= $qty * $size;
        $forms = $catalog['duration_units'][$name] ?? ['one' => $name, 'other' => $name . 's'];
        $label = ($qty === 1) ? (string)($forms['one'] ?? $name) : (string)($forms['other'] ?? ($forms['one'] ?? $name));
        $parts[] = $qty . ' ' . $label;
    }

    if ($parts === []) {
        return (string)($catalog['duration_zero'] ?? '0 seconds');
    }

    $count = count($parts);
    if ($count === 1) {
        return $parts[0];
    }
    if ($count === 2) {
        return $parts[0] . (string)($catalog['duration_pair_glue'] ?? ' and ') . $parts[1];
    }

    $last = array_pop($parts);
    return implode((string)($catalog['duration_middle_glue'] ?? ', '), $parts) . (string)($catalog['duration_last_glue'] ?? ' and ') . $last;
}

function dm_cfg_nonneg_int(array $cfg, string $key): int
{
    return max(0, (int)($cfg[$key] ?? 0));
}

function dm_cycles_required_text(int $count): string
{
    if ($count === 1) {
        return dm_web_text('detail_missed_required_one');
    }
    return dm_web_text('detail_missed_required_other', ['count' => (string)$count]);
}

function dm_cycles_current_text(int $count): string
{
    if ($count <= 0) {
        return dm_web_text('detail_missed_current_zero');
    }
    if ($count === 1) {
        return dm_web_text('detail_missed_current_one');
    }
    return dm_web_text('detail_missed_current_other', ['count' => (string)$count]);
}

function dm_render_page(string $title, string $bodyHtml): void
{
    dm_headers_common();
    $cssFile = dm_web_css_file_get();
    $cssLink = $cssFile !== null ? '<link rel="stylesheet" href="' . dm_h($cssFile) . '">' : '';
    $htmlLang = dm_h((string)(dm_web_catalog()['html_lang'] ?? 'en-US'));
    echo '<!doctype html><html lang="' . $htmlLang . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . dm_h($title) . '</title>' . $cssLink . '</head><body><main class="dm_shell"><section class="dm_card">' . $bodyHtml . '</section></main></body></html>';
    exit;
}

function dm_render_neutral(): void
{
    http_response_code(200);
    $body = '<h1>' . dm_h(dm_web_text('page_neutral_heading')) . '</h1>';
    $body .= '<p>' . dm_h(dm_web_text('page_neutral_body_1')) . '</p>';
    $body .= '<p>' . dm_h(dm_web_text('page_neutral_body_2')) . '</p>';
    dm_render_page(dm_web_text('page_neutral_title'), $body);
}

function dm_render_confirm_prompt(string $id, string $sig): void
{
    http_response_code(200);
    $body = '<h1>' . dm_h(dm_web_text('page_confirm_heading')) . '</h1>';
    $body .= '<p>' . dm_h(dm_web_text('page_confirm_intro')) . '</p>';
    $body .= '<form method="post"><input type="hidden" name="a" value="confirm"><input type="hidden" name="id" value="' . dm_h($id) . '"><input type="hidden" name="sig" value="' . dm_h($sig) . '"><button type="submit">' . dm_h(dm_web_text('page_confirm_button')) . '</button></form>';
    dm_render_page(dm_web_text('page_confirm_title'), $body);
}

function dm_render_confirm_ok(array $cfg, array $state): void
{
    http_response_code(200);
    $body = '<h1>' . dm_h(dm_web_text('page_confirm_ok_heading')) . '</h1>';
    $body .= '<p>' . dm_h(dm_web_text('page_confirm_ok_intro')) . '</p>';

    if (!empty($cfg['show_success_details'])) {
        $items = [];
        if (isset($state['cycle_start_at'])) {
            $items[] = dm_web_text('detail_cycle_started', ['datetime' => dm_confirm_details_dt($cfg, (int)$state['cycle_start_at'])]);
        }
        if (isset($state['last_confirm_at'])) {
            $items[] = dm_web_text('detail_last_confirm', ['datetime' => dm_confirm_details_dt($cfg, (int)$state['last_confirm_at'])]);
        }
        if (isset($state['next_check_at'])) {
            $items[] = dm_web_text('detail_window_opens', ['datetime' => dm_confirm_details_dt($cfg, (int)$state['next_check_at'])]);
        }
        if (isset($state['deadline_at'])) {
            $items[] = dm_web_text('detail_deadline', ['datetime' => dm_confirm_details_dt($cfg, (int)$state['deadline_at'])]);
        }
        if (isset($state['next_reminder_at'])) {
            $items[] = dm_web_text('detail_next_reminder', ['datetime' => dm_confirm_details_dt($cfg, (int)$state['next_reminder_at'])]);
        }

        $checkInterval = dm_cfg_nonneg_int($cfg, 'check_interval_seconds');
        $confirmWindow = dm_cfg_nonneg_int($cfg, 'confirm_window_seconds');
        $remindEvery = dm_cfg_nonneg_int($cfg, 'remind_every_seconds');
        $grace = dm_cfg_nonneg_int($cfg, 'escalate_grace_seconds');
        $missedThreshold = dm_cfg_nonneg_int($cfg, 'missed_cycles_before_fire');
        $missedCurrent = max(0, (int)($state['missed_cycles'] ?? 0));

        $items[] = dm_web_text('detail_window_opens_after', ['duration' => dm_human_duration($checkInterval)]);
        $items[] = dm_web_text('detail_window_length', ['duration' => dm_human_duration($confirmWindow)]);
        $items[] = dm_web_text('detail_reminders_repeat', ['duration' => dm_human_duration($remindEvery)]);
        $items[] = dm_web_text('detail_grace', ['duration' => dm_human_duration($grace)]);
        $items[] = dm_cycles_required_text($missedThreshold);
        $items[] = dm_cycles_current_text($missedCurrent);

        $body .= '<h2>' . dm_h(dm_web_text('page_confirm_ok_details_heading')) . '</h2><ul><li>' . implode('</li><li>', array_map('dm_h', $items)) . '</li></ul>';
    }

    dm_render_page(dm_web_text('page_confirm_ok_title'), $body);
}

function dm_render_ack_ok(bool $hasDownloads): void
{
    http_response_code(200);
    $body = '<h1>' . dm_h(dm_web_text('page_ack_ok_heading')) . '</h1>';
    $body .= '<p>' . dm_h(dm_web_text('page_ack_ok_body_1')) . '</p>';
    $body .= '<p>' . dm_h(dm_web_text('page_ack_ok_body_2')) . '</p>';
    $body .= '<p>' . dm_h(dm_web_text('page_ack_ok_body_3')) . '</p>';
    if ($hasDownloads) {
        $body .= '<p>' . dm_h(dm_web_text('page_ack_ok_body_4')) . '</p>';
    }
    dm_render_page(dm_web_text('page_ack_ok_title'), $body);
}

function dm_render_error(string $code): void
{
    http_response_code(200);
    $body = '<h1>' . dm_h(dm_web_text('page_error_heading')) . '</h1>';
    $body .= '<p>' . dm_h(dm_web_text('page_error_body_1')) . '</p>';
    $body .= '<p>' . dm_h(dm_web_text('page_error_body_2')) . '</p>';
    $body .= '<p class="dm_note">' . dm_h(dm_web_text('page_error_code_label')) . ': <code>' . dm_h($code) . '</code></p>';
    dm_render_page(dm_web_text('page_error_title'), $body);
}

function dm_render_unavailable(): void
{
    http_response_code(200);
    $body = '<h1>' . dm_h(dm_web_text('page_download_unavailable_heading')) . '</h1>';
    $body .= '<p>' . dm_h(dm_web_text('page_download_unavailable_body_1')) . '</p>';
    $body .= '<p>' . dm_h(dm_web_text('page_download_unavailable_body_2')) . '</p>';
    $body3 = trim(dm_web_text('page_download_unavailable_body_3'));
    if ($body3 !== '') {
        $body .= '<p>' . dm_h($body3) . '</p>';
    }
    dm_render_page(dm_web_text('page_download_unavailable_title'), $body);
}

$resolvedDir = dm_resolve_state_dir();
if ($resolvedDir === null) {
    dm_render_neutral();
}

$configPath = rtrim($resolvedDir, '/') . '/totmann.inc.php';
try {
    $cfg = dm_web_bootstrap_load_config($configPath);
    $libFile = dm_web_bootstrap_file_name($cfg, 'lib_file');
    $cssFile = dm_web_bootstrap_optional_file_name($cfg, 'web_css_file');
    dm_web_css_file_set($cssFile);
    $libPath = rtrim($resolvedDir, '/') . '/' . $libFile;
    if (!is_file($libPath) || !is_readable($libPath)) {
        throw new RuntimeException("missing/unreadable {$libFile}");
    }
    require $libPath;
} catch (Throwable $e) {
    dm_render_neutral();
}

$cfgStateDir = rtrim((string)($cfg['state_dir'] ?? ''), '/');
if ($resolvedDir !== null) {
    $stateDir = rtrim($resolvedDir, '/');
} elseif ($cfgStateDir !== '') {
    $stateDir = $cfgStateDir;
} else {
    $stateDir = rtrim(dirname($configPath), '/');
}
if ($stateDir === '') {
    dm_render_neutral();
}
$cfg['state_dir'] = $stateDir;

try {
    dm_web_catalog_set(dm_web_load_catalog($cfg));
} catch (Throwable $e) {
    dm_web_catalog_set(dm_web_fallback_catalog());
}

$stateFile = '';
$lockFile = '';
try {
    $stateFile = dm_state_file($cfg);
    $lockFile = dm_lock_file($cfg);
} catch (Throwable $e) {
    dm_render_neutral();
}

$a = (string)($_POST['a'] ?? $_GET['a'] ?? 'confirm');
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$now = dm_now();
$ip = dm_client_ip($cfg);

if ($a === 'download') {
    if (!dm_download_rate_limit_check($cfg, $ip, $now)) {
        dm_log($cfg, "download: ratelimited ip={$ip}");
        dm_render_neutral();
    }

    $downloadRecipients = [];
    $recipientId = trim((string)($_GET['rid'] ?? ''));
    $linkId = trim((string)($_GET['lid'] ?? ''));
    $eventAt = (int)($_GET['evt'] ?? 0);
    $expiresAt = (int)($_GET['exp'] ?? 0);
    $nonce = trim((string)($_GET['n'] ?? ''));
    $sig = trim((string)($_GET['sig'] ?? ''));
    $contentLength = 0;
    $contentType = 'application/octet-stream';
    $fileName = 'download';
    $downloadHandle = null;

    try {
        if (!dm_download_token_valid($cfg, $recipientId, $linkId, $eventAt, $expiresAt, $nonce, $sig)) {
            dm_render_neutral();
        }
    } catch (Throwable $e) {
        dm_log($cfg, 'download: token validation failed rid=' . $recipientId . ' lid=' . $linkId . ' ip=' . $ip . ' exception=' . $e->getMessage());
        dm_render_neutral();
    }

    if ($now > $expiresAt) {
        dm_log($cfg, "download: expired rid={$recipientId} lid={$linkId} ip={$ip}");
        dm_render_unavailable();
    }

    try {
        $downloadRecipients = dm_download_recipients_runtime($cfg);
    } catch (Throwable $e) {
        dm_log($cfg, 'download: recipients unavailable rid=' . $recipientId . ' lid=' . $linkId . ' ip=' . $ip . ' exception=' . $e->getMessage());
        dm_render_unavailable();
    }

    $definition = dm_download_definition_get($downloadRecipients, $recipientId, $linkId);
    if (!is_array($definition)) {
        dm_log($cfg, "download: mapping missing rid={$recipientId} lid={$linkId} ip={$ip}");
        dm_render_unavailable();
    }

    $singleUse = !empty($definition['single_use']);
    $stateKey = dm_download_state_key($recipientId, $linkId, $eventAt);
    $leaseSeconds = max(1, (int)($cfg['download_lease_seconds'] ?? 300));

    $lockHandle = null;
    try {
        $lockHandle = dm_lock_open($lockFile);
        $stateRoot = dm_state_load($stateFile);
        $downloadState = dm_state_downloads($stateRoot);
        $leaseStatus = dm_download_state_acquire_lease($downloadState, $stateKey, $now, $leaseSeconds, $singleUse, $expiresAt);
        $stateRoot = dm_state_with_downloads($stateRoot, $downloadState);
        dm_state_save($stateFile, $stateRoot);
        if ($leaseStatus === 'consumed') {
            dm_log($cfg, "download: already used rid={$recipientId} lid={$linkId} ip={$ip}");
            dm_render_unavailable();
        }
        if ($leaseStatus === 'leased') {
            dm_log($cfg, "download: lease active rid={$recipientId} lid={$linkId} ip={$ip}");
            dm_render_unavailable();
        }
    } catch (Throwable $e) {
        dm_log($cfg, 'download: lease acquire failed rid=' . $recipientId . ' lid=' . $linkId . ' ip=' . $ip . ' exception=' . $e->getMessage());
        dm_render_unavailable();
    } finally {
        if (is_resource($lockHandle)) {
            fclose($lockHandle);
        }
    }

    try {
        $path = dm_download_resolve_file($cfg, (string)($definition['file'] ?? ''));
        $fileName = basename($path);
        $contentLength = filesize($path);
        if (!is_int($contentLength)) {
            $contentLength = 0;
        }
        $contentType = dm_download_content_type($path);
        $downloadHandle = fopen($path, 'rb');
        if ($downloadHandle === false) {
            throw new RuntimeException("Cannot open download file: {$path}");
        }
    } catch (Throwable $e) {
        try {
            $lockHandle = dm_lock_open($lockFile);
            $stateRoot = dm_state_load($stateFile);
            $downloadState = dm_state_downloads($stateRoot);
            dm_download_state_release_lease($downloadState, $stateKey);
            $stateRoot = dm_state_with_downloads($stateRoot, $downloadState);
            dm_state_save($stateFile, $stateRoot);
        } catch (Throwable $inner) {
        } finally {
            if (is_resource($lockHandle)) {
                fclose($lockHandle);
            }
        }
        dm_log($cfg, 'download: file unavailable rid=' . $recipientId . ' lid=' . $linkId . ' ip=' . $ip . ' exception=' . $e->getMessage());
        dm_render_unavailable();
    }

    ignore_user_abort(true);
    if (function_exists('set_time_limit')) {
        set_time_limit(0);
    }

    header_remove('Content-Type');
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . (($contentLength !== false) ? (string)$contentLength : '0'));
    header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $fileName) . '"');
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    fpassthru($downloadHandle);
    fclose($downloadHandle);
    $transferOk = (connection_status() === CONNECTION_NORMAL);

    try {
        $lockHandle = dm_lock_open($lockFile);
        $stateRoot = dm_state_load($stateFile);
        $downloadState = dm_state_downloads($stateRoot);
        if ($transferOk) {
            dm_download_state_mark_consumed($downloadState, $stateKey, dm_now(), $singleUse, $expiresAt);
            dm_log($cfg, "download: served rid={$recipientId} lid={$linkId} ip={$ip}");
        } else {
            dm_download_state_release_lease($downloadState, $stateKey);
            dm_log($cfg, "download: transfer incomplete rid={$recipientId} lid={$linkId} ip={$ip}");
        }
        $stateRoot = dm_state_with_downloads($stateRoot, $downloadState);
        dm_state_save($stateFile, $stateRoot);
    } catch (Throwable $e) {
        dm_log($cfg, 'download: state finalise failed rid=' . $recipientId . ' lid=' . $linkId . ' ip=' . $ip . ' exception=' . $e->getMessage());
    } finally {
        if (is_resource($lockHandle)) {
            fclose($lockHandle);
        }
    }

    exit;
}

if (!in_array($a, ['confirm', 'ack'], true)) {
    dm_render_neutral();
}

if (!dm_rate_limit_check($cfg, $ip, $now)) {
    dm_log($cfg, "web: ratelimited ip={$ip}");
    dm_render_neutral();
}

$id = (string)($_POST['id'] ?? $_GET['id'] ?? '');
$sig = (string)($_POST['sig'] ?? $_GET['sig'] ?? '');

$tokenOk = false;
try {
    $tokenOk = dm_token_valid($cfg, $id, $sig);
} catch (Throwable $e) {
    dm_log($cfg, 'web: token validation failed a=' . $a . ' ip=' . $ip . ' exception=' . $e->getMessage());
    $tokenOk = false;
}
if (!$tokenOk) {
    if (!empty($cfg['stealth_neutral_for_invalid'])) {
        dm_render_neutral();
    }
    dm_render_error('E_TOKEN_INVALID_' . bin2hex(random_bytes(4)));
}

$lockHandle = null;
$isCurrentRequest = false;

try {
    $lockHandle = dm_lock_open($lockFile);
    $stateRoot = dm_state_load($stateFile);
    $state = dm_state_runtime($stateRoot);
    if (empty($state)) {
        dm_log($cfg, "web: state missing (valid-signed request) a={$a} ip={$ip}");
        dm_render_neutral();
    }

    if ($a === 'confirm') {
        $cur = $state['token'] ?? [];
        $isCurrent = (($cur['id'] ?? '') === $id) && (($cur['sig'] ?? '') === $sig);
        $isCurrentRequest = $isCurrent;
        if (!$isCurrent) {
            dm_log($cfg, "confirm: stale-or-noncurrent token used ip={$ip}");
            if (!empty($cfg['stealth_level_2_neutral_on_stale'])) {
                dm_render_neutral();
            }
            dm_render_error('E_TOKEN_STALE_' . bin2hex(random_bytes(4)));
        }

        if (!empty($state['escalated_sent_at'])) {
            dm_log($cfg, "confirm: blocked after escalation ip={$ip}");
            if (!empty($cfg['stealth_level_2_neutral_on_stale'])) {
                dm_render_neutral();
            }
            dm_render_error('E_CONFIRM_BLOCKED_' . bin2hex(random_bytes(4)));
        }

        if ($method !== 'POST') {
            dm_render_confirm_prompt($id, $sig);
        }

        $state['last_confirm_at'] = $now;
        $state['missed_cycles'] = 0;
        $state['missed_cycle_deadline'] = null;
        dm_state_clear_escalation($state);
        $token = dm_make_token($cfg);
        $timing = dm_state_apply_cycle($state, $now, (int)($cfg['check_interval_seconds'] ?? 0), (int)($cfg['confirm_window_seconds'] ?? 0), $token);
        $stateRoot = dm_state_with_runtime($stateRoot, $state);
        dm_state_save($stateFile, $stateRoot);
        dm_log($cfg, 'confirm: OK ip=' . $ip . ' next_check_at=' . dm_iso((int)$timing['next_check_at']));
        dm_render_confirm_ok($cfg, $state);
    }

    $ackEnabled = (!empty($cfg['escalate_ack_enabled']) && !empty($cfg['base_url']));
    if (!$ackEnabled) {
        dm_render_neutral();
    }

    $ackRecipients = $state['escalate_ack_recipients'] ?? [];
    $isCurrent = false;
    $ackHasDownloads = false;
    if (is_array($ackRecipients)) {
        foreach ($ackRecipients as $ackRecipient) {
            if (!is_array($ackRecipient)) {
                continue;
            }
            if ((string)($ackRecipient['id'] ?? '') === $id && (string)($ackRecipient['sig'] ?? '') === $sig) {
                $isCurrent = true;
                $ackHasDownloads = !empty($ackRecipient['has_downloads']);
                break;
            }
        }
    }
    $isCurrentRequest = $isCurrent;
    if (!$isCurrent) {
        dm_log($cfg, "ack: stale-or-noncurrent token used ip={$ip}");
        if (!empty($cfg['stealth_level_2_neutral_on_stale'])) {
            dm_render_neutral();
        }
        dm_render_error('E_TOKEN_STALE_' . bin2hex(random_bytes(4)));
    }

    $state['escalate_ack_at'] = $now;
    $state['escalate_ack_next_at'] = null;
    $deliveryMap = $state['escalation_delivery'] ?? [];
    if (is_array($deliveryMap)) {
        foreach ($deliveryMap as $recipientKey => $delivery) {
            if (!is_array($delivery)) {
                continue;
            }
            $delivery['ack_next_at'] = null;
            $deliveryMap[$recipientKey] = $delivery;
        }
        $state['escalation_delivery'] = $deliveryMap;
    }
    $stateRoot = dm_state_with_runtime($stateRoot, $state);
    dm_state_save($stateFile, $stateRoot);
    dm_log($cfg, "ack: OK ip={$ip}");
    dm_log($cfg, 'ack: escalation acknowledged; no further escalation mails will be sent for this event.');
    dm_render_ack_ok($ackHasDownloads);
} catch (Throwable $e) {
    $err = ($a === 'ack') ? 'E_ACK_FAIL_' : 'E_CONFIRM_FAIL_';
    $err .= bin2hex(random_bytes(4));
    dm_log($cfg, 'web: ' . $err . ' a=' . $a . ' ip=' . $ip . ' exception=' . $e->getMessage());
    if ($isCurrentRequest) {
        dm_render_error($err);
    }
    dm_render_neutral();
} finally {
    if (is_resource($lockHandle)) {
        fclose($lockHandle);
    }
}
