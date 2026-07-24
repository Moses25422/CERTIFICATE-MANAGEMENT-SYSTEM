<?php
/**
 * Scheduler Diagnostic Tool
 * 
 * Run this script to verify scheduler setup and configuration
 * Usage: php scheduler-diagnostics.php
 */

define('DIAGNOSTIC_MODE', true);

// Colors for CLI output
const GREEN = "\033[92m";
const RED = "\033[91m";
const YELLOW = "\033[93m";
const BLUE = "\033[94m";
const RESET = "\033[0m";

class SchedulerDiagnostics
{
    private $warnings = [];
    private $errors = [];
    private $info = [];

    public function run(): void
    {
        $this->printHeader();
        $this->checkEnvironment();
        $this->checkFiles();
        $this->checkDatabase();
        $this->checkScheduler();
        $this->checkEmailConfig();
        $this->checkLogs();
        $this->printSummary();
    }

    private function printHeader(): void
    {
        echo BLUE . "\n";
        echo "╔════════════════════════════════════════════════════════╗\n";
        echo "║   Certificate Management System - Scheduler Diagnostic   ║\n";
        echo "╚════════════════════════════════════════════════════════╝\n";
        echo RESET . "\n";
    }

    private function checkEnvironment(): void
    {
        echo BLUE . "1. Environment Check\n" . RESET;

        // PHP Version
        $phpVersion = phpversion();
        echo "   PHP Version: $phpVersion\n";
        if (version_compare($phpVersion, '7.0.0') < 0) {
            $this->addError("PHP 7.0.0 or higher required");
        } else {
            echo "   " . GREEN . "✓ PHP version OK\n" . RESET;
        }

        // PHP Executable
        $phpExe = defined('PHP_EXECUTABLE') ? PHP_EXECUTABLE : 'php';
        echo "   PHP Executable: $phpExe\n";
        if (!file_exists($phpExe)) {
            $this->addWarning("PHP executable not found at: $phpExe");
        } else {
            echo "   " . GREEN . "✓ PHP executable found\n" . RESET;
        }

        // SAPI
        $sapi = php_sapi_name();
        echo "   SAPI: $sapi\n";

        // Required extensions
        $extensions = ['pdo', 'json'];
        foreach ($extensions as $ext) {
            if (!extension_loaded($ext)) {
                $this->addError("Required extension missing: $ext");
            }
        }
        if (count($this->errors) === 0) {
            echo "   " . GREEN . "✓ Required extensions loaded\n" . RESET;
        }

        echo "\n";
    }

    private function checkFiles(): void
    {
        echo BLUE . "2. File Structure Check\n" . RESET;

        $files = [
            'send_reminders.php',
            'scheduler.php',
            'reminders-scheduler.php',
            'includes/db.php',
            'includes/auth.php',
        ];

        $allExist = true;
        foreach ($files as $file) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path)) {
                echo "   " . GREEN . "✓ $file\n" . RESET;
            } else {
                echo "   " . RED . "✗ $file (NOT FOUND)\n" . RESET;
                $this->addError("Missing file: $file");
                $allExist = false;
            }
        }

        // Check directory structure
        $dirs = ['logs', 'database', 'config'];
        foreach ($dirs as $dir) {
            $path = __DIR__ . '/' . $dir;
            if (!is_dir($path)) {
                if (!@mkdir($path, 0777, true)) {
                    $this->addWarning("Could not create directory: $dir");
                } else {
                    echo "   " . GREEN . "✓ Created directory: $dir\n" . RESET;
                }
            } else {
                echo "   " . GREEN . "✓ Directory exists: $dir\n" . RESET;
            }
        }

        echo "\n";
    }

    private function checkDatabase(): void
    {
        echo BLUE . "3. Database Check\n" . RESET;

        try {
            require_once __DIR__ . '/includes/db.php';
            $pdo = getDatabaseConnection();
            echo "   " . GREEN . "✓ Database connection OK\n" . RESET;

            // Check tables
            $driver = getenv('DB_DRIVER') ?: 'sqlite';

            $stmt = $pdo->query('
                SELECT COUNT(*) as count FROM students
            ');
            $students = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            echo "   Students in database: $students\n";

            $stmt = $pdo->query('
                SELECT COUNT(*) as count FROM email_logs
            ');
            $logs = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            echo "   Email logs: $logs\n";

            // Check last reminder sent
            $stmt = $pdo->query('
                SELECT id, full_name, email, last_reminder_sent_at 
                FROM students 
                WHERE last_reminder_sent_at IS NOT NULL 
                ORDER BY last_reminder_sent_at DESC 
                LIMIT 1
            ');
            $lastReminder = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($lastReminder) {
                echo "   Last reminder sent: " . $lastReminder['last_reminder_sent_at'] . "\n";
                echo "   To: " . $lastReminder['full_name'] . " <" . $lastReminder['email'] . ">\n";
            } else {
                echo "   No reminders sent yet\n";
            }

            echo "   " . GREEN . "✓ Database tables OK\n" . RESET;
        } catch (Exception $e) {
            $this->addError("Database error: " . $e->getMessage());
        }

        echo "\n";
    }

    private function checkScheduler(): void
    {
        echo BLUE . "4. Scheduler Configuration Check\n" . RESET;

        $lastRunFile = __DIR__ . '/database/.scheduler_last_run';

        if (file_exists($lastRunFile)) {
            $lastRun = file_get_contents($lastRunFile);
            echo "   Last run date: $lastRun\n";
            echo "   " . GREEN . "✓ Scheduler has run before\n" . RESET;
        } else {
            $this->addInfo("Scheduler has not run yet");
            echo "   " . YELLOW . "⚠ No scheduler history found\n" . RESET;
        }

        // Check Windows Task Scheduler
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            echo "\n   Windows Task Scheduler Status:\n";
            $output = [];
            @exec('schtasks /query /tn "Certificate Management - Daily Reminders" /v', $output, $returnCode);

            if ($returnCode === 0 && !empty($output)) {
                echo "   " . GREEN . "✓ Task found in Task Scheduler\n" . RESET;
                foreach ($output as $line) {
                    if (strpos($line, 'Status') !== false || strpos($line, 'Next Run') !== false) {
                        echo "   " . trim($line) . "\n";
                    }
                }
            } else {
                $this->addWarning("Task not found in Windows Task Scheduler. Run setup-scheduler-windows.bat");
            }
        }

        echo "\n";
    }

    private function checkEmailConfig(): void
    {
        echo BLUE . "5. Email Configuration Check\n" . RESET;

        // Check SMTP config file
        $configFile = __DIR__ . '/config/smtp.json';
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            echo "   SMTP Config File: EXISTS\n";
            echo "   Host: " . ($config['host'] ?? 'not set') . "\n";
            echo "   Port: " . ($config['port'] ?? 'not set') . "\n";
            echo "   From: " . ($config['from_address'] ?? 'not set') . "\n";
            if (!empty($config['host'])) {
                echo "   " . GREEN . "✓ SMTP configured\n" . RESET;
            } else {
                $this->addWarning("SMTP configuration incomplete");
            }
        } else {
            echo "   SMTP Config File: NOT FOUND\n";
            $this->addInfo("Using PHP mail() function by default");
        }

        // Check environment variables
        $env_vars = ['SMTP_HOST', 'SMTP_USER', 'SMTP_PASS'];
        $hasEnvConfig = false;
        foreach ($env_vars as $var) {
            if (getenv($var)) {
                $hasEnvConfig = true;
                echo "   Environment Variable: " . GREEN . "$var = set\n" . RESET;
            }
        }

        if (!file_exists($configFile) && !$hasEnvConfig) {
            $this->addInfo("No SMTP configuration found. Using PHP mail() function.");
        }

        echo "\n";
    }

    private function checkLogs(): void
    {
        echo BLUE . "6. Log Files Check\n" . RESET;

        $logFiles = [
            'logs/scheduler.log' => 'Scheduler Log',
            'logs/reminders.log' => 'Reminders Log',
        ];

        foreach ($logFiles as $path => $name) {
            $fullPath = __DIR__ . '/' . $path;
            if (file_exists($fullPath)) {
                $size = filesize($fullPath);
                $lines = count(file($fullPath));
                echo "   $name: EXISTS\n";
                echo "   Size: " . $this->formatBytes($size) . " ($lines lines)\n";

                // Show last entry
                $lastLines = array_slice(file($fullPath), -1);
                if (!empty($lastLines)) {
                    echo "   Last entry: " . trim($lastLines[0]) . "\n";
                }
                echo "   " . GREEN . "✓ Log file OK\n" . RESET;
            } else {
                echo "   $name: NOT FOUND (will be created on first run)\n";
            }
        }

        echo "\n";
    }

    private function printSummary(): void
    {
        echo BLUE . "═══════════════════════════════════════════════════════\n" . RESET;
        echo BLUE . "DIAGNOSTIC SUMMARY\n" . RESET;
        echo BLUE . "═══════════════════════════════════════════════════════\n" . RESET . "\n";

        if (!empty($this->errors)) {
            echo RED . "ERRORS (" . count($this->errors) . "):\n" . RESET;
            foreach ($this->errors as $error) {
                echo RED . "  ✗ " . $error . "\n" . RESET;
            }
            echo "\n";
        }

        if (!empty($this->warnings)) {
            echo YELLOW . "WARNINGS (" . count($this->warnings) . "):\n" . RESET;
            foreach ($this->warnings as $warning) {
                echo YELLOW . "  ⚠ " . $warning . "\n" . RESET;
            }
            echo "\n";
        }

        if (!empty($this->info)) {
            echo BLUE . "INFO (" . count($this->info) . "):\n" . RESET;
            foreach ($this->info as $info) {
                echo BLUE . "  ℹ " . $info . "\n" . RESET;
            }
            echo "\n";
        }

        if (empty($this->errors)) {
            echo GREEN . "STATUS: ✓ All checks passed!\n" . RESET;
            echo "\nNext steps:\n";
            echo "  1. Run: setup-scheduler-windows.bat (Windows)\n";
            echo "  2. Run: bash setup-scheduler-linux.sh (Linux/Mac)\n";
            echo "  3. Visit: reminders-scheduler.php (Web Dashboard)\n";
        } else {
            echo RED . "STATUS: ✗ Some errors found. Please review above.\n" . RESET;
        }

        echo "\n";
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    private function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    private function addInfo(string $message): void
    {
        $this->info[] = $message;
    }
}

// Run diagnostics
if (php_sapi_name() === 'cli') {
    $diagnostics = new SchedulerDiagnostics();
    $diagnostics->run();
} else {
    echo "<pre>";
    $diagnostics = new SchedulerDiagnostics();
    $diagnostics->run();
    echo "</pre>";
}
