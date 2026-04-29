<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/totmann-lib.php';

$passed = 0;
$failed = 0;
$tmpRoots = [];

function t_ok(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[OK] {$message}\n";
        return;
    }
    $failed++;
    echo "[FAIL] {$message}\n";
}

function t_failed_count(): int
{
    global $failed;
    return $failed;
}

function t_tmp_roots(): array
{
    global $tmpRoots;
    return $tmpRoots;
}

function t_expect_exception(callable $fn, string $contains, string $message): void
{
    try {
        $fn();
        t_ok(false, $message);
    } catch (Throwable $e) {
        t_ok(str_contains($e->getMessage(), $contains), $message);
    }
}

function t_tmpdir(string $prefix): string
{
    global $tmpRoots;
    $base = rtrim(sys_get_temp_dir(), '/') . '/' . $prefix . '-' . bin2hex(random_bytes(6));
    if (!mkdir($base, 0770, true) && !is_dir($base)) {
        throw new RuntimeException("Cannot create temp dir: {$base}");
    }
    $tmpRoots[] = $base;
    return $base;
}

function t_rm_rf(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        unlink($path);
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        t_rm_rf($path . '/' . $item);
    }
    rmdir($path);
}

function t_copy_dir(string $source, string $target): void
{
    if (!mkdir($target, 0770, true) && !is_dir($target)) {
        throw new RuntimeException("Cannot create directory: {$target}");
    }
    $items = scandir($source);
    if ($items === false) {
        throw new RuntimeException("Cannot scan directory: {$source}");
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $from = $source . '/' . $item;
        $to = $target . '/' . $item;
        if (is_dir($from)) {
            t_copy_dir($from, $to);
        } else {
            copy($from, $to);
        }
    }
}

function t_write_php_array(string $path, array $value): void
{
    file_put_contents($path, "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($value, true) . ";\n");
}

function t_run(string $command, string $cwd, int $timeoutSeconds = 10): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, $cwd);
    if (!is_resource($process)) {
        throw new RuntimeException("Cannot start command: {$command}");
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + $timeoutSeconds;
    $timedOut = false;
    while (true) {
        $stdout .= (string)stream_get_contents($pipes[1]);
        $stderr .= (string)stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        if (microtime(true) >= $deadline) {
            $timedOut = true;
            proc_terminate($process);
            break;
        }
        usleep(50000);
    }
    $stdout .= (string)stream_get_contents($pipes[1]);
    $stderr .= (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($timedOut) {
        return [124, $stdout . $stderr . "\nCommand timed out: {$command}\n"];
    }
    return [$exit, (string)$stdout . (string)$stderr];
}

function t_valid_config(string $stateDir): array
{
    $cfg = require dirname(__DIR__) . '/totmann.inc.dist.php';
    $cfg['state_dir'] = $stateDir;
    $cfg['base_url'] = 'https://totmann.invalid/totmann';
    $cfg['hmac_secret_hex'] = str_repeat('a', 64);
    $cfg['lib_file'] = 'totmann-lib.php';
    $cfg['l18n_dir_name'] = 'l18n';
    $cfg['lock_file'] = 'totmann.lock';
    $cfg['log_file_name'] = 'totmann.log';
    $cfg['recipients_file'] = 'totmann-recipients.php';
    $cfg['state_file'] = 'totmann.json';
    $cfg['web_file'] = 'totmann.php';
    $cfg['web_css_file'] = '';
    $cfg['download_base_dir'] = $stateDir . '/downloads';
    $cfg['to_self'] = ['Operator <operator@totmann.invalid>'];
    $cfg['mail_from'] = 'totmann <totmann@totmann.invalid>';
    return $cfg;
}

function t_write_runtime_state(string $stateDir, array $cfg): void
{
    $now = time();
    $state = dm_state_make_initial($cfg, $now, 3600, 3600);
    dm_state_save($stateDir . '/totmann.json', dm_state_with_runtime([], $state));
}

function t_prepare_state_dir(string $stateDir): array
{
    $root = dirname(__DIR__);
    mkdir($stateDir . '/downloads', 0770, true);
    copy($root . '/totmann-lib.php', $stateDir . '/totmann-lib.php');
    copy($root . '/totmann-tick.php', $stateDir . '/totmann-tick.php');
    t_copy_dir($root . '/l18n', $stateDir . '/l18n');
    $cfg = t_valid_config($stateDir);
    t_write_php_array($stateDir . '/totmann.inc.php', $cfg);
    t_write_php_array($stateDir . '/totmann-recipients.php', [
        'files' => ['letter' => 'letter.txt'],
        'messages' => [
            'default' => [
                'subject' => '[totmann] Test',
                'body' => "Test body\n{ACK_BLOCK}\n{DOWNLOAD_LINKS}",
                'single_use_notice' => 'Save this file straight away.',
            ],
        ],
        'recipients' => [
            ['Recipient', 'recipient@totmann.invalid', 'default', [], []],
        ],
    ]);
    return $cfg;
}

function t_http_get(string $url): array
{
    $rawHeaders = @get_headers($url);
    $body = @file_get_contents($url);
    return [is_string($body) ? $body : '', is_array($rawHeaders) ? $rawHeaders : []];
}

try {
    $stateDir = t_tmpdir('totmann-state-test');
    t_ok(dm_state_load($stateDir . '/missing.json') === [], 'missing state returns an empty root');
    file_put_contents($stateDir . '/empty.json', '');
    t_expect_exception(fn() => dm_state_load($stateDir . '/empty.json'), 'empty', 'empty state file is rejected');
    file_put_contents($stateDir . '/broken.json', '{broken');
    t_expect_exception(fn() => dm_state_load($stateDir . '/broken.json'), 'invalid JSON', 'invalid JSON state is rejected');
    file_put_contents($stateDir . '/scalar.json', '"value"');
    t_expect_exception(fn() => dm_state_load($stateDir . '/scalar.json'), 'JSON object', 'non-object JSON state is rejected');
    dm_state_save($stateDir . '/valid.json', ['runtime' => ['cycle_start_at' => 1, 'next_check_at' => 2, 'deadline_at' => 3]]);
    t_ok(dm_state_load($stateDir . '/valid.json') !== [], 'valid state is loaded');
    t_ok(dm_state_runtime_sanity_errors(['cycle_start_at' => 1, 'next_check_at' => 2, 'deadline_at' => 3]) === [], 'consistent runtime state passes sanity check');
    t_ok(dm_state_runtime_sanity_errors(['cycle_start_at' => 1, 'next_check_at' => 3, 'deadline_at' => 2]) !== [], 'inconsistent runtime state fails sanity check');

    $configDir = t_tmpdir('totmann-config-test');
    $cfg = t_prepare_state_dir($configDir);
    $loaded = dm_bootstrap_load_effective_config($configDir);
    t_ok(($loaded['_config_source']['effective_config_source'] ?? '') === 'live', 'live-only config is an effective config source');
    t_ok(dm_config_readiness_errors($loaded) === [], 'live-only config is runtime-ready');
    unlink($configDir . '/totmann.inc.php');
    t_write_php_array($configDir . '/totmann.inc.dist.php', $cfg);
    $loaded = dm_bootstrap_load_effective_config($configDir);
    t_ok(($loaded['_config_source']['effective_config_source'] ?? '') === 'dist', 'dist-only config is an effective config source');
    t_ok(dm_config_readiness_errors($loaded) === [], 'dist-only config is runtime-ready');

    t_write_php_array($configDir . '/bad-recipients.php', [
        'files' => ['letter' => 'letter.txt'],
        'messages' => [
            'default' => ['subject' => '[totmann] Test', 'body' => '{DOWNLOAD_LINKS}'],
        ],
        'recipients' => [
            ['Recipient', 'recipient@totmann.invalid', 'default', [], ['letter']],
        ],
    ]);
    $badCfg = $cfg;
    $badCfg['recipients_file'] = 'bad-recipients.php';
    t_expect_exception(fn() => dm_recipients_load($badCfg), 'single_use_notice', 'field-5 recipients require single_use_notice');

    $tickDir = t_tmpdir('totmann-tick-test');
    t_prepare_state_dir($tickDir);
    [$tickExit, $tickOutput] = t_run(PHP_BINARY . ' ' . escapeshellarg($tickDir . '/totmann-tick.php') . ' tick', $tickDir);
    t_ok($tickExit === 0 && is_file($tickDir . '/totmann.json'), 'first tick initialises a missing state file');
    file_put_contents($tickDir . '/totmann.json', '{broken');
    [$checkExit, $checkOutput] = t_run(PHP_BINARY . ' ' . escapeshellarg($tickDir . '/totmann-tick.php') . ' check', $tickDir);
    t_ok($checkExit === 2 && str_contains($checkOutput, 'State file validation failed'), 'check fails visibly for corrupt state');
    [$badTickExit, $badTickOutput] = t_run(PHP_BINARY . ' ' . escapeshellarg($tickDir . '/totmann-tick.php') . ' tick', $tickDir);
    t_ok($badTickExit === 1, 'tick stops for corrupt state');

    $template = require $root . '/totmann.inc.dist.php';
    $bodyReminder = (string)($template['body_reminder'] ?? '');
    t_ok(str_contains($bodyReminder, 'Please click this link to confirm:'), 'reminder template uses click wording');
    t_ok(!str_contains($bodyReminder, 'Please use this link to confirm:'), 'reminder template no longer uses old wording');
    t_ok(str_contains($bodyReminder, 'You must click the link no later than the confirmation deadline.'), 'reminder template includes deadline action line');

    $webSource = file_get_contents($root . '/totmann.php');
    t_ok(is_string($webSource) && str_contains($webSource, "frame-ancestors 'none'"), 'web source defines frame-ancestor protection');
    t_ok(is_string($webSource) && str_contains($webSource, 'X-Frame-Options: DENY'), 'web source defines X-Frame-Options fallback');
    t_ok(is_string($webSource) && str_contains($webSource, 'https://raw.githubusercontent.com/MacSteini/totmannschalter/refs/heads/main/img/totmannschalter-s.png'), 'runtime logo remains the approved external image');

    $webStateDir = t_tmpdir('totmann-web-state-test');
    $webCfg = t_prepare_state_dir($webStateDir);
    $token = dm_make_token($webCfg);
    file_put_contents($webStateDir . '/totmann.json', '{broken');
    $webRoot = t_tmpdir('totmann-webroot-test');
    copy($root . '/totmann.php', $webRoot . '/totmann.php');
    $port = random_int(19000, 25000);
    $cmd = PHP_BINARY . ' -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($webRoot);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($cmd, $descriptors, $pipes, $root, ['totmann_STATE_DIR' => $webStateDir]);
    if (is_resource($process)) {
        try {
            $ready = false;
            for ($i = 0; $i < 50; $i++) {
                $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
                if (is_resource($socket)) {
                    fclose($socket);
                    $ready = true;
                    break;
                }
                usleep(100000);
            }
            t_ok($ready, 'PHP built-in server started for web smoke');
            if ($ready) {
                [$body, $headers] = t_http_get('http://127.0.0.1:' . $port . '/totmann.php?a=confirm&id=' . rawurlencode((string)$token['id']) . '&sig=' . rawurlencode((string)$token['sig']));
                $headerText = implode("\n", $headers);
                t_ok(str_contains($body, '<title>[totmann] This page is not available.</title>'), 'web stays neutral when state file is corrupt');
                t_ok(str_contains($body, '<img class="dm_logo"'), 'web page still renders the logo image');
                t_ok(str_contains($headerText, "Content-Security-Policy: frame-ancestors 'none'"), 'web response sends frame-ancestor protection');
                t_ok(str_contains($headerText, 'X-Frame-Options: DENY'), 'web response sends X-Frame-Options fallback');
            }
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    } else {
        t_ok(false, 'PHP built-in server started for web smoke');
    }
} finally {
    foreach (t_tmp_roots() as $tmpRoot) {
        t_rm_rf($tmpRoot);
    }
}

echo "Summary: {$passed} passed, {$failed} failed\n";
exit(t_failed_count() === 0 ? 0 : 1);
