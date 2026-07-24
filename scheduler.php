<?php
/**
 * Reminder Scheduler - Executes send_reminders.php daily
 * 
 * This script can be run via:
 * 1. Windows Task Scheduler (recommended on Windows)
 * 2. Linux Cron (on Linux/Mac)
 * 3. Direct CLI call: php scheduler.php
 */

require_once __DIR__ . '/includes/db.php';

const SCHEDULER_LOG_FILE = __DIR__ . '/logs/scheduler.log';
const LAST_RUN_FILE = __DIR__ . '/database/.scheduler_last_run';

/**
 * Write log entry
 */
function logScheduler(string $message): void
{
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents(SCHEDULER_LOG_FILE, $entry, FILE_APPEND);
    
    // Also output to console for debugging
    echo $entry;
}

/**
 * Check if task should run (runs once per day max)
 */
function shouldRunTask(): bool
{
    $databaseDir = __DIR__ . '/database';
    if (!is_dir($databaseDir)) {
        mkdir($databaseDir, 0777, true);
    }

    $now = new DateTime('now');
    $today = $now->format('Y-m-d');

    // Check if we have a last run file
    if (!file_exists(LAST_RUN_FILE)) {
        return true;
    }

    $lastRun = file_get_contents(LAST_RUN_FILE);
    if ($lastRun !== $today) {
        return true;
    }

    return false;
}

/**
 * Mark task as run for today
 */
function markTaskAsRun(): void
{
    $today = (new DateTime('now'))->format('Y-m-d');
    file_put_contents(LAST_RUN_FILE, $today);
}

/**
 * Execute the reminder sending script
 */
function executeReminders(): bool
{
    $remindersScript = __DIR__ . '/send_reminders.php';
    
    if (!file_exists($remindersScript)) {
        logScheduler('ERROR: send_reminders.php not found');
        return false;
    }

    // Execute the reminders script via CLI
    $output = [];
    $returnCode = 0;
    
    // Determine the PHP executable path
    $phpPath = defined('PHP_EXECUTABLE') ? PHP_EXECUTABLE : 'php';
    
    $command = escapeshellcmd($phpPath) . ' ' . escapeshellarg($remindersScript);
    exec($command, $output, $returnCode);

    $outputText = implode("\n", $output);
    
    if ($returnCode === 0) {
        logScheduler('Successfully executed send_reminders.php: ' . $outputText);
        return true;
    } else {
        logScheduler('ERROR executing send_reminders.php (code: ' . $returnCode . '): ' . $outputText);
        return false;
    }
}

// Main execution
try {
    logScheduler('Scheduler started');

    if (!shouldRunTask()) {
        logScheduler('Task already ran today, skipping execution');
        exit(0);
    }

    logScheduler('Executing reminder task...');
    
    if (executeReminders()) {
        markTaskAsRun();
        logScheduler('Scheduler completed successfully');
        exit(0);
    } else {
        logScheduler('Scheduler failed to execute reminders');
        exit(1);
    }
} catch (Exception $e) {
    logScheduler('EXCEPTION: ' . $e->getMessage());
    exit(1);
}
