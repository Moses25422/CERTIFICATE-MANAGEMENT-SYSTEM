<?php
/**
 * Manual Trigger for Reminder Scheduler
 * 
 * Allows administrators to manually trigger the reminder sending process
 * from the web interface.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSession();
requireAdmin();

$pdo = getDatabaseConnection();
$result = null;
$output = [];

// Manual trigger via POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'trigger') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $result = [
            'success' => false,
            'message' => 'Invalid CSRF token'
        ];
    } else {
        // Execute reminders
        $remindersScript = __DIR__ . '/send_reminders.php';
        
        if (!file_exists($remindersScript)) {
            $result = [
                'success' => false,
                'message' => 'send_reminders.php not found'
            ];
        } else {
            $phpPath = PHP_EXECUTABLE ?? 'php';
            $command = escapeshellcmd($phpPath) . ' ' . escapeshellarg($remindersScript);
            
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0) {
                $result = [
                    'success' => true,
                    'message' => 'Reminders triggered successfully!',
                    'output' => implode("\n", $output)
                ];
                logActivity("Manual reminder trigger executed by " . getLoggedInAdmin());
            } else {
                $result = [
                    'success' => false,
                    'message' => 'Failed to execute reminders',
                    'output' => implode("\n", $output) ?: 'No output',
                    'code' => $returnCode
                ];
            }
        }
    }
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get scheduler status
$schedulerLogFile = __DIR__ . '/logs/scheduler.log';
$schedulerLastRun = null;
$schedulerStatus = 'Not configured';

if (file_exists($schedulerLogFile)) {
    $lines = array_reverse(file($schedulerLogFile));
    foreach ($lines as $line) {
        if (strpos($line, 'completed') !== false || strpos($line, 'failed') !== false) {
            $schedulerLastRun = trim($line);
            $schedulerStatus = strpos($line, 'failed') !== false ? 'Error' : 'Running';
            break;
        }
    }
}

// Get email logs
$stmt = $pdo->query('
    SELECT * FROM email_logs 
    ORDER BY sent_at DESC 
    LIMIT 20
');
$emailLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count pending reminders
$stmt = $pdo->query('
    SELECT COUNT(*) as count FROM students 
    WHERE collected = 0 
    AND completion_date IS NOT NULL
');
$pendingReminders = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

function logActivity(string $message): void {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . '/reminders.log';
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reminder Scheduler - Certificate System</title>
    <link rel="stylesheet" href="assets/styles.css">
    <style>
        .scheduler-section {
            margin-bottom: 2rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .status-badge.active {
            background-color: #10b981;
            color: white;
        }

        .status-badge.inactive {
            background-color: #ef4444;
            color: white;
        }

        .status-badge.warning {
            background-color: #f59e0b;
            color: white;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .info-box {
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 1rem;
            background-color: #f9fafb;
        }

        .info-box h4 {
            margin: 0 0 0.5rem 0;
            font-size: 0.875rem;
            color: #6b7280;
            text-transform: uppercase;
        }

        .info-box p {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #111827;
        }

        .trigger-button {
            background-color: #3b82f6;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .trigger-button:hover:not(:disabled) {
            background-color: #2563eb;
        }

        .trigger-button:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
        }

        .output-box {
            background-color: #1f2937;
            color: #10b981;
            padding: 1rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            white-space: pre-wrap;
            word-wrap: break-word;
            margin: 1rem 0;
            max-height: 400px;
            overflow-y: auto;
        }

        .log-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .log-table thead {
            background-color: #f3f4f6;
        }

        .log-table th, .log-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.875rem;
        }

        .log-table tr:hover {
            background-color: #f9fafb;
        }

        .status-sent {
            color: #10b981;
            font-weight: 600;
        }

        .status-failed {
            color: #ef4444;
            font-weight: 600;
        }

        .setup-instructions {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 1rem;
        }

        .setup-instructions h5 {
            margin: 0 0 0.5rem 0;
            color: #1e40af;
        }

        .setup-instructions code {
            background-color: #dbeafe;
            padding: 0.2rem 0.4rem;
            border-radius: 2px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-main">
                <div>
                    <h1>Reminder Scheduler</h1>
                    <p>Manage automatic daily certificate collection reminders</p>
                </div>
                <div class="header-actions">
                    <span>Signed in as <?php echo htmlspecialchars(getLoggedInAdmin(), ENT_QUOTES, 'UTF-8'); ?></span>
                    <a href="index.php" class="secondary-link">Dashboard</a>
                    <a href="logout.php" class="secondary-link">Sign out</a>
                </div>
            </div>
        </header>

        <!-- Status Section -->
        <section class="card scheduler-section">
            <h2>Scheduler Status</h2>

            <div class="info-grid">
                <div class="info-box">
                    <h4>Configuration Status</h4>
                    <p>
                        <span class="status-badge <?php echo file_exists(__DIR__ . '/logs/scheduler.log') ? 'active' : 'inactive'; ?>">
                            <?php echo file_exists(__DIR__ . '/logs/scheduler.log') ? 'Configured' : 'Not Configured'; ?>
                        </span>
                    </p>
                </div>

                <div class="info-box">
                    <h4>Pending Reminders</h4>
                    <p><?php echo htmlspecialchars((string)$pendingReminders, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>

                <div class="info-box">
                    <h4>Schedule</h4>
                    <p>Daily at 6:00 AM</p>
                </div>
            </div>

            <?php if ($schedulerLastRun): ?>
                <p><strong>Last Run:</strong> <?php echo htmlspecialchars($schedulerLastRun, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </section>

        <!-- Manual Trigger Section -->
        <section class="card scheduler-section">
            <h2>Manual Trigger</h2>
            <p>Manually trigger the reminder sending process immediately:</p>

            <form method="post" id="triggerForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="trigger">
                <button type="submit" class="trigger-button">Send Reminders Now</button>
            </form>

            <?php if ($result): ?>
                <?php if ($result['success']): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if (isset($result['code'])): ?>
                            (Error Code: <?php echo htmlspecialchars((string)$result['code'], ENT_QUOTES, 'UTF-8'); ?>)
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($result['output']) && !empty($result['output'])): ?>
                    <div class="output-box"><?php echo htmlspecialchars($result['output'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- Setup Section -->
        <section class="card scheduler-section">
            <h2>Setup Automatic Scheduling</h2>
            <p>To set up automatic daily reminders, follow these steps:</p>

            <div class="setup-instructions">
                <h5>For Windows Users:</h5>
                <p>
                    Open Command Prompt as Administrator and run:
                    <br><code>setup-scheduler-windows.bat</code>
                    <br>Or using PowerShell:
                    <br><code>.\setup-scheduler-windows.ps1</code>
                </p>
            </div>

            <div class="setup-instructions">
                <h5>For Linux/Mac Users:</h5>
                <p>
                    Open Terminal and run:
                    <br><code>bash setup-scheduler-linux.sh</code>
                </p>
            </div>

            <p><strong>See <a href="SCHEDULER_SETUP.md" target="_blank">SCHEDULER_SETUP.md</a> for detailed instructions.</strong></p>
        </section>

        <!-- Recent Email Logs -->
        <section class="card scheduler-section">
            <h2>Recent Email Activity</h2>
            <?php if (!empty($emailLogs)): ?>
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Sent At</th>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Subject</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emailLogs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['sent_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($log['student_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($log['recipient_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <span class="status-<?php echo htmlspecialchars($log['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo ucfirst(htmlspecialchars($log['status'], ENT_QUOTES, 'UTF-8')); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['subject'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No email logs found yet.</p>
            <?php endif; ?>
        </section>

        <section class="card">
            <div style="text-align: center;">
                <a href="index.php" class="secondary-link">Return to dashboard</a>
            </div>
        </section>
    </div>
</body>
</html>
