<?php

/**
 * totmannschalter – systemd tick entrypoint
 *
 * Project: https://github.com/MacSteini/totmannschalter
 * Licence: MIT (see LICENCE)
 *
 * State dir resolution:
 * - ENV TOTMANN_STATE_DIR, otherwise __DIR__ (so it works when placed in /var/lib/totmann).
 */

declare(strict_types=1);

$stateDir = rtrim((string)(getenv('TOTMANN_STATE_DIR') ?: __DIR__), '/');

$argv = $_SERVER['argv'] ?? [];
if (!is_array($argv)) {
    $argv = [];
}

$cmd = (string)($argv[1] ?? '');
if (!in_array($cmd, ['tick', 'check'], true)) {
    fwrite(STDERR, "Usage: php totmann-tick.php tick\n");
    fwrite(STDERR, "Usage: php totmann-tick.php check [--web-user=<WEB_USER>]\n");
    exit(2);
}
if ($cmd === 'tick' && count($argv) > 2) {
    fwrite(STDERR, "Usage: php totmann-tick.php tick\n");
    exit(2);
}

$configPath = $stateDir . '/totmann.inc.php';
try {
    if (!is_file($configPath) || !is_readable($configPath)) {
        throw new RuntimeException("missing/unreadable totmann.inc.php: {$configPath}");
    }
    $cfg = require $configPath;
    if (!is_array($cfg)) {
        throw new RuntimeException('totmann.inc.php must return an array');
    }

    $libFile = trim((string)($cfg['lib_file'] ?? ''));
    if ($libFile === '') {
        throw new RuntimeException('Missing config key: lib_file');
    }
    if (str_contains($libFile, '/') || str_contains($libFile, '\\')) {
        throw new RuntimeException('Invalid lib_file: filename must not contain slashes');
    }
    if ($libFile === '.' || $libFile === '..' || str_contains($libFile, '..') || preg_match('/[[:cntrl:]]/', $libFile)) {
        throw new RuntimeException('Invalid lib_file: traversal/control chars not allowed');
    }

    $libPath = $stateDir . '/' . $libFile;
    if (!is_file($libPath) || !is_readable($libPath)) {
        throw new RuntimeException("missing/unreadable {$libFile} in {$stateDir}");
    }
    require $libPath;
} catch (Throwable $e) {
    fwrite(STDERR, 'BOOTSTRAP ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($cmd === 'check') {
    $webUser = null;
    $args = array_slice($argv, 2);

    for ($i = 0; $i < count($args); $i++) {
        $arg = (string)$args[$i];
        if (str_starts_with($arg, '--web-user=')) {
            $webUser = trim(substr($arg, strlen('--web-user=')));
            if ($webUser === '') {
                fwrite(STDERR, "ERROR: --web-user requires a non-empty value\n");
                exit(2);
            }
            continue;
        }

        if ($arg === '--web-user') {
            $next = $args[$i + 1] ?? null;
            if (!is_scalar($next) || trim((string)$next) === '' || str_starts_with((string)$next, '--')) {
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

$cfg['state_dir'] = $stateDir;
try {
    $runtimeCfg = dm_validate_runtime_config($cfg);
    $recipientErrors = [];
    $escalationRecipients = dm_escalation_recipients_runtime($cfg, $recipientErrors);
} catch (Throwable $e) {
    fwrite(STDERR, 'CONFIG ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
foreach ($recipientErrors as $recipientError) {
    dm_log($cfg, 'Recipient skipped: ' . $recipientError);
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
    fwrite(STDERR, 'BOOTSTRAP ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}

$lockHandle = null;

try {
    $lockHandle = dm_lock_open($lockFile);

    $now = dm_now();
    $stateRoot = dm_state_load($stateFile);
    $state = dm_state_runtime($stateRoot);

    if (empty($state)) {
        $state = dm_state_make_initial($cfg, $now, $checkInterval, $confirmWindow);
        $stateRoot = dm_state_with_runtime($stateRoot, $state);
        $stateRoot = dm_state_with_downloads($stateRoot, dm_state_downloads($stateRoot));
        dm_state_save($stateFile, $stateRoot);
        dm_log($cfg, 'Initialised state. Next check at ' . dm_iso((int)$state['next_check_at']));
        exit(0);
    }

    $createdAt = (int)($state['created_at'] ?? 0);
    $cycleStart0 = (int)($state['cycle_start_at'] ?? 0);
    $lastConfirm0 = (int)($state['last_confirm_at'] ?? 0);

    if ($createdAt > 0 && $cycleStart0 === $createdAt && $lastConfirm0 === $createdAt && empty($state['escalated_sent_at']) && (int)($state['missed_cycles'] ?? 0) === 0 && ($now - $createdAt) > 5) {
        $state['last_confirm_at'] = 0;
        dm_log($cfg, 'Migrated initial state: last_confirm_at reset to 0 (was equal to created_at).');
    }

    if (isset($state['last_tick_at']) && $now + 5 < (int)$state['last_tick_at']) {
        dm_log($cfg, "Clock moved backwards. now={$now}, last_tick_at={$state['last_tick_at']}. Skipping actions.");
        $state['last_tick_at'] = $now;
        $stateRoot = dm_state_with_runtime($stateRoot, $state);
        dm_state_save($stateFile, $stateRoot);
        exit(0);
    }

    $state['last_tick_at'] = $now;

    $nextCheck = (int)($state['next_check_at'] ?? 0);
    $deadline = (int)($state['deadline_at'] ?? 0);

    if (empty($state['token']['id']) || empty($state['token']['sig'])) {
        $state['token'] = dm_make_token($cfg);
        dm_log($cfg, 'Token was missing; regenerated.');
        $stateRoot = dm_state_with_runtime($stateRoot, $state);
        dm_state_save($stateFile, $stateRoot);
        exit(0);
    }

    $tokenId = (string)($state['token']['id'] ?? '');
    $tokenSig = (string)($state['token']['sig'] ?? '');
    $tokenValid = false;
    try {
        $tokenValid = dm_token_valid($cfg, $tokenId, $tokenSig);
    } catch (Throwable $e) {
        $tokenValid = false;
    }
    if (!$tokenValid) {
        $state['token'] = dm_make_token($cfg);
        dm_log($cfg, 'Token was invalid; regenerated.');
        $stateRoot = dm_state_with_runtime($stateRoot, $state);
        dm_state_save($stateFile, $stateRoot);
        exit(0);
    }

    $cycleStartCurrent = (int)($state['cycle_start_at'] ?? 0);
    $stateBroken = ($cycleStartCurrent <= 0 || $nextCheck <= 0 || $deadline <= 0 || $deadline <= $nextCheck);
    if ($stateBroken) {
        dm_log($cfg, "State sanity recovery triggered (cycle_start_at={$cycleStartCurrent}, next_check_at={$nextCheck}, deadline_at={$deadline}).");
        dm_state_start_cycle($cfg, $state, $now, $checkInterval, $confirmWindow);
        $state['missed_cycles'] = 0;
        $state['missed_cycle_deadline'] = null;
        dm_state_clear_escalation($state);
        $state['last_tick_at'] = $now;
        $stateRoot = dm_state_with_runtime($stateRoot, $state);
        dm_state_save($stateFile, $stateRoot);
        exit(0);
    }

    if ($now >= $nextCheck && $now < $deadline) {
        $nextReminder = (int)($state['next_reminder_at'] ?? $nextCheck);
        if ($nextReminder < $nextCheck) {
            $nextReminder = $nextCheck;
        }

        if ($now >= $nextReminder) {
            $confirmUrl = dm_confirm_url($cfg, (array)$state['token']);
            $body = str_replace(
                ['{CONFIRM_URL}', '{DEADLINE_ISO}', '{CYCLE_START_ISO}'],
                [$confirmUrl, dm_mail_dt($cfg, $deadline), dm_mail_dt($cfg, (int)$state['cycle_start_at'])],
                (string)$cfg['body_reminder']
            );

            $selfRecipients = dm_recipient_entries_runtime((array)($cfg['to_self'] ?? []));
            if ($selfRecipients === []) {
                throw new RuntimeException('sendmail: empty/invalid recipient list');
            }
            $subjectReminder = (string)$cfg['subject_reminder'];
            foreach ($selfRecipients as $selfRecipient) {
                dm_send_mail($cfg, [$selfRecipient], $subjectReminder, $body);
            }
            dm_log($cfg, "Sent reminder to self. next_reminder_at was {$nextReminder}");
            $state['next_reminder_at'] = $now + $remindEvery;
        }
    }

    $fireAt = $deadline + $escalateGrace;
    $lastConfirm = (int)($state['last_confirm_at'] ?? 0);
    $cycleStart = (int)($state['cycle_start_at'] ?? 0);
    $confirmedThisCycle = ($lastConfirm >= $nextCheck);
    $ackEnabled = ($ackEnabledCfg && !empty($cfg['base_url']));

    if ($now >= $fireAt && !$confirmedThisCycle) {
        $eventAt = (int)($state['escalation_event_at'] ?? 0);
        if ($eventAt <= 0) {
            $alreadyCounted = ((int)($state['missed_cycle_deadline'] ?? 0) === $deadline);
            if ($alreadyCounted) {
                dm_log($cfg, 'Missed cycle already recorded for this deadline; skipping counter bump.');
            } else {
                $state['missed_cycles'] = (int)($state['missed_cycles'] ?? 0) + 1;
                $state['missed_cycle_deadline'] = $deadline;
            }

            $threshold = $missedCyclesBeforeFire;
            dm_log($cfg, "Missed cycle status (missed_cycles={$state['missed_cycles']}/{$threshold}).");

            if ((int)$state['missed_cycles'] >= $threshold) {
                dm_state_reset_ack($state);
                $state['escalation_event_at'] = $now;
                $state['escalated_sent_at'] = $now;
                if (!isset($state['escalation_delivery']) || !is_array($state['escalation_delivery'])) {
                    $state['escalation_delivery'] = [];
                }
                dm_state_refresh_ack_summary($state);
                dm_log($cfg, 'Escalation event opened at ' . dm_iso($now) . '.');
            } else {
                $timing = dm_state_start_cycle($cfg, $state, $now, $checkInterval, $confirmWindow);
                $state['missed_cycle_deadline'] = null;
                dm_log($cfg, 'Started new conservative cycle after miss. Next check at ' . dm_iso((int)$timing['next_check_at']));
            }
        }

        $eventAt = (int)($state['escalation_event_at'] ?? 0);
        if ($eventAt > 0) {
            $deliveryMap = $state['escalation_delivery'] ?? [];
            if (!is_array($deliveryMap)) {
                $deliveryMap = [];
            }
            $ackRecipients = $state['escalate_ack_recipients'] ?? [];
            if (!is_array($ackRecipients)) {
                $ackRecipients = [];
            }

            $initialSentNow = 0;
            $initialFailedNow = 0;

            foreach ($escalationRecipients as $recipient) {
                $recipientKey = (string)($recipient['recipient_key'] ?? '');
                $recipientName = (string)($recipient['name'] ?? '');
                $recipientAddress = (string)($recipient['address'] ?? '');
                if ($recipientKey === '' || $recipientAddress === '') {
                    continue;
                }

                $delivery = $deliveryMap[$recipientKey] ?? dm_state_escalation_delivery_default();
                if (!is_array($delivery)) {
                    $delivery = dm_state_escalation_delivery_default();
                }

                if ($ackEnabled) {
                    $ackRecipient = $ackRecipients[$recipientKey] ?? null;
                    if (!is_array($ackRecipient) || empty($ackRecipient['id']) || empty($ackRecipient['sig'])) {
                        $recipientAckToken = dm_make_token($cfg);
                        $ackRecipients[$recipientKey] = [
                            'id' => (string)$recipientAckToken['id'],
                            'sig' => (string)$recipientAckToken['sig'],
                            'has_downloads' => false,
                        ];
                    }
                }

                if (!empty($delivery['initial_sent_at'])) {
                    $deliveryMap[$recipientKey] = $delivery;
                    continue;
                }

                try {
                    $messageTemplate = dm_escalate_message_for_recipient($recipient);
                    $linksForRecipient = dm_download_links_for_recipient($cfg, $recipient, $eventAt, $now);
                    $downloadNotice = dm_render_download_notice($cfg, $linksForRecipient);
                    $downloadBlock = dm_render_download_links_block($linksForRecipient);
                    $ackUrl = '';
                    if ($ackEnabled) {
                        $ackRecipient = $ackRecipients[$recipientKey];
                        $ackRecipients[$recipientKey]['has_downloads'] = ($linksForRecipient !== []);
                        $ackUrl = dm_ack_url($cfg, ['id' => (string)$ackRecipient['id'], 'sig' => (string)$ackRecipient['sig']]);
                    }
                    $ackBlock = dm_render_ack_block($ackUrl, $ackEnabled);
                    $body = dm_render_escalate_template($cfg, (string)$messageTemplate['body'], $lastConfirm, $cycleStart, $deadline, $recipientName, $ackUrl, $ackEnabled, $downloadNotice, $downloadBlock, $ackBlock);
                    dm_send_mail($cfg, [$recipientAddress], (string)$messageTemplate['subject'], $body);

                    $delivery['initial_sent_at'] = $now;
                    $delivery['last_error'] = null;
                    if ($ackEnabled && $ackMaxReminds > 0) {
                        $delivery['ack_next_at'] = $now + $ackRemindEvery;
                    }
                    $initialSentNow++;
                    dm_log($cfg, "Escalation mail sent to {$recipientAddress}.");
                } catch (Throwable $e) {
                    $delivery['last_error'] = $e->getMessage();
                    $initialFailedNow++;
                    dm_log($cfg, "Escalation mail failed for {$recipientAddress}: " . $e->getMessage());
                }

                $deliveryMap[$recipientKey] = $delivery;
            }

            $state['escalation_delivery'] = $deliveryMap;
            $state['escalate_ack_recipients'] = $ackRecipients;
            dm_state_refresh_ack_summary($state);

            if ($initialSentNow > 0 || $initialFailedNow > 0) {
                dm_log($cfg, "Escalation delivery progress (sent_now={$initialSentNow}, failed_now={$initialFailedNow}, recipients=" . count($escalationRecipients) . ').');
            }
        }
    }

    $eventAt = (int)($state['escalation_event_at'] ?? 0);
    if ($ackEnabled && $eventAt > 0 && empty($state['escalate_ack_at'])) {
        $deliveryMap = $state['escalation_delivery'] ?? [];
        if (!is_array($deliveryMap)) {
            $deliveryMap = [];
        }
        $ackRecipients = $state['escalate_ack_recipients'] ?? [];
        if (!is_array($ackRecipients)) {
            $ackRecipients = [];
        }

        if ($ackMaxReminds <= 0) {
            foreach ($deliveryMap as $recipientKey => $delivery) {
                if (!is_array($delivery) || empty($delivery['ack_next_at'])) {
                    continue;
                }
                $delivery['ack_next_at'] = null;
                $deliveryMap[$recipientKey] = $delivery;
            }
            $state['escalation_delivery'] = $deliveryMap;
            dm_state_refresh_ack_summary($state);
        } else {
            $remindersSentNow = 0;
            $reminderFailures = 0;

            foreach ($escalationRecipients as $recipient) {
                $recipientKey = (string)($recipient['recipient_key'] ?? '');
                $recipientName = (string)($recipient['name'] ?? '');
                $recipientAddress = (string)($recipient['address'] ?? '');
                if ($recipientKey === '' || $recipientAddress === '') {
                    continue;
                }

                $delivery = $deliveryMap[$recipientKey] ?? dm_state_escalation_delivery_default();
                if (!is_array($delivery) || empty($delivery['initial_sent_at'])) {
                    $deliveryMap[$recipientKey] = is_array($delivery) ? $delivery : dm_state_escalation_delivery_default();
                    continue;
                }

                $sentCount = max(0, (int)($delivery['ack_remind_sent_count'] ?? 0));
                $nextAt = (int)($delivery['ack_next_at'] ?? 0);

                if ($sentCount >= $ackMaxReminds) {
                    if ($nextAt > 0) {
                        $delivery['ack_next_at'] = null;
                        $deliveryMap[$recipientKey] = $delivery;
                    }
                    continue;
                }
                if ($nextAt <= 0 || $now < $nextAt) {
                    $deliveryMap[$recipientKey] = $delivery;
                    continue;
                }

                $ackRecipient = $ackRecipients[$recipientKey] ?? null;
                if (!is_array($ackRecipient) || empty($ackRecipient['id']) || empty($ackRecipient['sig'])) {
                    dm_log($cfg, "ack: recipient token missing for {$recipientAddress} during reminder phase; skipping recipient.");
                    $deliveryMap[$recipientKey] = $delivery;
                    continue;
                }

                try {
                    $messageTemplate = dm_escalate_message_for_recipient($recipient);
                    $linksForRecipient = dm_download_links_for_recipient($cfg, $recipient, $eventAt, $now);
                    $downloadNotice = dm_render_download_notice($cfg, $linksForRecipient);
                    $downloadBlock = dm_render_download_links_block($linksForRecipient);
                    $ackUrl = dm_ack_url($cfg, ['id' => (string)$ackRecipient['id'], 'sig' => (string)$ackRecipient['sig']]);
                    $ackBlock = dm_render_ack_block($ackUrl, true);
                    $body = dm_render_escalate_template($cfg, (string)$messageTemplate['body'], (int)($state['last_confirm_at'] ?? 0), (int)($state['cycle_start_at'] ?? 0), (int)($state['deadline_at'] ?? 0), $recipientName, $ackUrl, true, $downloadNotice, $downloadBlock, $ackBlock);
                    dm_send_mail($cfg, [$recipientAddress], (string)$messageTemplate['subject'], $body);

                    $delivery['ack_remind_sent_count'] = $sentCount + 1;
                    $delivery['ack_next_at'] = ($delivery['ack_remind_sent_count'] >= $ackMaxReminds) ? null : ($now + $ackRemindEvery);
                    $delivery['last_error'] = null;
                    $remindersSentNow++;
                    dm_log($cfg, "Escalation ACK reminder sent to {$recipientAddress} (count={$delivery['ack_remind_sent_count']}/{$ackMaxReminds}).");
                } catch (Throwable $e) {
                    $delivery['last_error'] = $e->getMessage();
                    $reminderFailures++;
                    dm_log($cfg, "Escalation ACK reminder failed for {$recipientAddress}: " . $e->getMessage());
                }

                $deliveryMap[$recipientKey] = $delivery;
            }

            $state['escalation_delivery'] = $deliveryMap;
            dm_state_refresh_ack_summary($state);
            if ($remindersSentNow > 0 || $reminderFailures > 0) {
                dm_log($cfg, "Escalation ACK reminder progress (sent_now={$remindersSentNow}, failed_now={$reminderFailures}).");
            }
        }
    }

    $stateRoot = dm_state_with_runtime($stateRoot, $state);
    dm_state_save($stateFile, $stateRoot);
    exit(0);
} catch (Throwable $e) {
    dm_log($cfg, 'ERROR: ' . $e->getMessage());
    exit(1);
} finally {
    if (is_resource($lockHandle)) {
        fclose($lockHandle);
    }
}
