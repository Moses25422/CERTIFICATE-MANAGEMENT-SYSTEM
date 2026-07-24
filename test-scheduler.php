<?php
require 'includes/db.php';

$pdo = getDatabaseConnection();

// Check uncollected certificates
$stmt = $pdo->query('SELECT * FROM students WHERE collected = 0 ORDER BY id');
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== SCHEDULER TEST RESULTS ===\n\n";
echo "Uncollected Certificates: " . count($students) . "\n\n";

foreach ($students as $s) {
    echo "ID: " . $s['student_id'] . "\n";
    echo "  Name: " . $s['full_name'] . "\n";
    echo "  Email: " . $s['email'] . "\n";
    echo "  Course: " . $s['course_name'] . "\n";
    echo "  Completion Date: " . $s['completion_date'] . "\n";
    echo "  Last Reminder Sent: " . ($s['last_reminder_sent_at'] ?? 'Never') . "\n\n";
}

// Check scheduler status
echo "=== SCHEDULER STATUS ===\n\n";
$lastRunFile = __DIR__ . '/database/.scheduler_last_run';
if (file_exists($lastRunFile)) {
    echo "Last Run Date: " . file_get_contents($lastRunFile) . "\n";
} else {
    echo "Last Run Date: Not recorded yet\n";
}

// Check email logs
$stmt = $pdo->query('SELECT COUNT(*) as count FROM email_logs');
$emailLogCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "Email Logs: " . $emailLogCount . "\n\n";

// Check log file
$schedulerLogFile = __DIR__ . '/logs/scheduler.log';
if (file_exists($schedulerLogFile)) {
    echo "=== LATEST SCHEDULER LOG ===\n\n";
    $lines = file($schedulerLogFile);
    $lastLines = array_slice($lines, -5);
    foreach ($lastLines as $line) {
        echo trim($line) . "\n";
    }
}

echo "\n✓ Scheduler test complete!\n";
