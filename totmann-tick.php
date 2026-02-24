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
    fwrite(STDERR, "       php totmann-tick.php check [--web-user=<WEB_USER>]\n");
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
        throw new RuntimeException("Invalid lib_file: filename must not contain slashes");
    }
    if ($libFile === '.' || $libFile === '..' || str_contains($libFile, '..') || preg_match('/[[:cntrl:]]/', $libFile)) {
        throw new RuntimeException("Invalid lib_file: traversal/control chars not allowed");
    }

    $libPath = $stateDir . '/' . $libFile;
    if (!is_file($libPath) || !is_readable($libPath)) {
        throw new RuntimeException("missing/unreadable {$libFile} in {$stateDir}");
    }
    require $libPath;
} catch (Throwable $e) {
    fwrite(STDERR, "BOOTSTRAP ERROR: " . $e->getMessage() . "\n");
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
    $escalationRecipients = dm_escalation_recipients_runtime($cfg);
} catch (Throwable $e) {
    fwrite(STDERR, "CONFIG ERROR: " . $e->getMessage() . "\n");
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

$ackMailTemplateIdsUsed = [];
foreach ($escalationRecipients as $recipient) {
    $rid = (string)($recipient['mail_id'] ?? '');
    if ($rid !== '') {
        $ackMailTemplateIdsUsed[$rid] = true;
    }
}

$ackMailTemplates = [];
$ackMailTemplateLoadError = null;
if ($ackMailTemplateIdsUsed !== []) {
    try {
        $ackMailTemplates = dm_individual_messages_load($cfg);
    } catch (Throwable $e) {
        $ackMailTemplateLoadError = $e->getMessage();
    }
}
$ackMailTemplateFallbackLogged = [];

try {
    $stateFile = dm_state_file($cfg);
    $lockFile = dm_lock_file($cfg);
} catch (Throwable $e) {
    fwrite(STDERR, "BOOTSTRAP ERROR: " . $e->getMessage() . "\n");
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
        dm_log($cfg, "Initialised state. Next check at " . dm_iso((int)$state['next_check_at']));
        exit(0);
    }

    $createdAt = (int)($state['created_at'] ?? 0);
    $cycleStart0 = (int)($state['cycle_start_at'] ?? 0);
    $lastConfirm0 = (int)($state['last_confirm_at'] ?? 0);

    if ($createdAt > 0 && $cycleStart0 === $createdAt && $lastConfirm0 === $createdAt && empty($state['escalated_sent_at']) && (int)($state['missed_cycles'] ?? 0) === 0 && ($now - $createdAt) > 5) {
        $state['last_confirm_at'] = 0;
        dm_log($cfg, "Migrated initial state: last_confirm_at reset to 0 (was equal to created_at).");
    }

// Sanity: clock went backwards -> do nothing risky in this tick
    if (isset($state['last_tick_at']) && $now + 5 < (int)$state['last_tick_at']) {
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
    } catch (Throwable $e) {
        $tokenValid = false;
    }
    if (!$tokenValid) {
        $state['token'] = dm_make_token($cfg);
        dm_log($cfg, "Token was invalid; regenerated.");
        dm_state_save($stateFile, $state);
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
        dm_state_save($stateFile, $state);
        exit(0);
    }

// 1) Reminder phase: from next_check_at until deadline_at
    if ($now >= $nextCheck && $now < $deadline) {
        $nextReminder = (int)($state['next_reminder_at'] ?? $nextCheck);

    // Defensive: if next_reminder_at is behind the window start, bump it forward.
        if ($nextReminder < $nextCheck) {
            $nextReminder = $nextCheck;
        }

    // Defensive: if next_reminder_at is in the past, send now and then schedule from "now".
        if ($now >= $nextReminder) {
            $confirmUrl = dm_confirm_url($cfg, (array)$state['token']);

            $body = str_replace(['{CONFIRM_URL}', '{DEADLINE_ISO}', '{CYCLE_START_ISO}'], [$confirmUrl, dm_mail_dt($cfg, $deadline), dm_mail_dt($cfg, (int)$state['cycle_start_at'])], (string)$cfg['body_reminder']);

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

// 2) Escalation: only after deadline+grace, only if NOT confirmed in this cycle
    $grace = $escalateGrace;
    $fireAt = $deadline + $grace;

    $lastConfirm = (int)($state['last_confirm_at'] ?? 0);
    $cycleStart = (int)($state['cycle_start_at'] ?? 0);

// "Confirmed this cycle" means: a confirm happened after the window opened.
// If confirmation happened before next_check_at, this cycle still counts as unconfirmed.
    $confirmedThisCycle = ($lastConfirm >= $nextCheck);

    if ($now >= $fireAt && !$confirmedThisCycle) {
        if (!empty($state['escalated_sent_at'])) {
            $ackEnabledState = ($ackEnabledCfg && !empty($cfg['base_url']));
            $ackRecordedState = !empty($state['escalate_ack_at']);
            $maxRemindsState = $ackMaxReminds;
            $sentCountState = (int)($state['escalate_ack_sent_count'] ?? 0);
            $shouldLogAlreadySent = true;
            if ($ackEnabledState && $ackRecordedState) {
                $shouldLogAlreadySent = false;
            } elseif ($ackEnabledState && $maxRemindsState > 0 && $sentCountState >= $maxRemindsState) {
                $shouldLogAlreadySent = false;
            }
            if ($shouldLogAlreadySent) {
                dm_log($cfg, "Escalation already sent at " . dm_iso((int)$state['escalated_sent_at']) . ". Skipping.");
            }
        } else {
        // Count missed cycle only once per deadline (timer runs every minute)
            $alreadyCounted = ((int)($state['missed_cycle_deadline'] ?? 0) === $deadline);
            if ($alreadyCounted) {
                dm_log($cfg, "Missed cycle already recorded for this deadline; skipping counter bump.");
            } else {
                $state['missed_cycles'] = (int)($state['missed_cycles'] ?? 0) + 1;
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
                    if ($maxAckRemindsCfg > 0) {
                        $state['escalate_ack_next_at'] = $now + $ackRemindEvery;
                    } else {
                        $state['escalate_ack_next_at'] = null;
                    }

                    $ackUrl = dm_ack_url($cfg, $ackToken);
                }

                $subjectEscalate = (string)$cfg['subject_escalate'];
                if ($ackMailTemplateLoadError !== null) {
                    dm_log($cfg, "Individual messages unavailable ({$ackMailTemplateLoadError}). Falling back to subject_escalate/body_escalate.");
                }
                foreach ($escalationRecipients as $recipient) {
                    $recipientAckId = (string)($recipient['mail_id'] ?? '');
                    if ($ackMailTemplateLoadError === null && $recipientAckId !== '' && !array_key_exists($recipientAckId, $ackMailTemplates) && !isset($ackMailTemplateFallbackLogged[$recipientAckId])) {
                        dm_log($cfg, "Recipient " . (string)$recipient['address'] . " (ID: '{$recipientAckId}') not found in mail_file. Falling back to subject_escalate/body_escalate.");
                        $ackMailTemplateFallbackLogged[$recipientAckId] = true;
                    }
                    $messageTemplate = dm_escalate_message_for_recipient($cfg, $recipientAckId, $ackMailTemplates);
                    $subjectEscalate = (string)$messageTemplate['subject'];
                    $bodyTemplate = (string)$messageTemplate['body'];
                    $body = dm_render_escalate_template($cfg, $bodyTemplate, $lastConfirm, $cycleStart, $deadline, $ackUrl, $ackEnabled);
                    dm_send_mail($cfg, [(string)$recipient['address']], $subjectEscalate, $body);
                }
                $state['escalated_sent_at'] = $now;

                dm_log($cfg, "Escalation mail sent to recipients (count=" . count($escalationRecipients) . ').');
            } else {
            // Conservative: start a new cycle instead of escalating
                $timing = dm_state_start_cycle($cfg, $state, $now, $checkInterval, $confirmWindow);

            // reset so the next cycle counts cleanly
                $state['missed_cycle_deadline'] = null;

                dm_log($cfg, "Started new conservative cycle after miss. Next check at " . dm_iso((int)$timing['next_check_at']));
            }
        }
    }

// 3) Escalation ACK reminders (re-send until one recipient acknowledges)
    $ackEnabled = ($ackEnabledCfg && !empty($cfg['base_url']));
    if ($ackEnabled && !empty($state['escalated_sent_at']) && empty($state['escalate_ack_at'])) {
        $maxReminds = $ackMaxReminds;
        if ($maxReminds <= 0) {
            if (!empty($state['escalate_ack_next_at'])) {
                $state['escalate_ack_next_at'] = null;
                dm_log($cfg, "ACK reminders disabled (escalate_ack_max_reminds<=0).");
            }
        } else {
            $sentCount = (int)($state['escalate_ack_sent_count'] ?? 0);
            $nextAt = (int)($state['escalate_ack_next_at'] ?? 0);

            if ($sentCount >= $maxReminds) {
                if ($nextAt > 0) {
                    $state['escalate_ack_next_at'] = null;
                    dm_log($cfg, "Escalation ACK reminder limit reached ({$sentCount}/{$maxReminds}). Reminder logging paused until ACK or reset.");
                }
            } elseif ($nextAt > 0 && $now >= $nextAt) {
            // hard fail: reminders make no sense without an issued token from the initial escalation
                if (empty($state['escalate_ack_token']) || empty($state['escalate_ack_token']['id']) || empty($state['escalate_ack_token']['sig'])) {
                    dm_log($cfg, "ack: token missing during reminder phase; not sending reminder.");
                    $state['escalate_ack_next_at'] = null;
                    dm_state_save($stateFile, $state);
                    exit(0);
                }

                $ackToken = (array)$state['escalate_ack_token'];
                $ackUrl = dm_ack_url($cfg, $ackToken);

                $subjectEscalate = (string)$cfg['subject_escalate'];
                if ($ackMailTemplateLoadError !== null) {
                    dm_log($cfg, "Individual messages unavailable ({$ackMailTemplateLoadError}). Falling back to subject_escalate/body_escalate.");
                }
                foreach ($escalationRecipients as $recipient) {
                    $recipientAckId = (string)($recipient['mail_id'] ?? '');
                    if ($ackMailTemplateLoadError === null && $recipientAckId !== '' && !array_key_exists($recipientAckId, $ackMailTemplates) && !isset($ackMailTemplateFallbackLogged[$recipientAckId])) {
                        dm_log($cfg, "Recipient " . (string)$recipient['address'] . " (ID: '{$recipientAckId}') not found in mail_file. Falling back to subject_escalate/body_escalate.");
                        $ackMailTemplateFallbackLogged[$recipientAckId] = true;
                    }
                    $messageTemplate = dm_escalate_message_for_recipient($cfg, $recipientAckId, $ackMailTemplates);
                    $subjectEscalate = (string)$messageTemplate['subject'];
                    $bodyTemplate = (string)$messageTemplate['body'];
                    $body = dm_render_escalate_template($cfg, $bodyTemplate, (int)($state['last_confirm_at'] ?? 0), (int)($state['cycle_start_at'] ?? 0), (int)($state['deadline_at'] ?? 0), $ackUrl, true);
                    dm_send_mail($cfg, [(string)$recipient['address']], $subjectEscalate, $body);
                }

                $state['escalate_ack_sent_count'] = $sentCount + 1;
                $state['escalate_ack_next_at'] = $now + $ackRemindEvery;

                dm_log($cfg, "Escalation ACK reminder sent (count={$state['escalate_ack_sent_count']}/{$maxReminds}).");
            }
        }
    }

    dm_state_save($stateFile, $state);
    exit(0);
} catch (Throwable $e) {
    dm_log($cfg, "ERROR: " . $e->getMessage());
    // Errors must NOT trigger escalation. Fail-closed against false positives.
    exit(1);
} finally {
    if (is_resource($lockHandle)) {
        fclose($lockHandle);
    }
}
