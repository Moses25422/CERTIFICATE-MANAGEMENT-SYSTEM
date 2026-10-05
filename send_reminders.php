<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/email.php';

startSession();
requireAdmin();

$emailService = new EmailService();
$result = $emailService->sendPendingReminders();
$sentCount = $result['sent'];
$errors = $result['errors'];

foreach ($students as $student) {
    if (empty($student['completion_date'])) {
        continue;
    }

    $completionDate = new DateTime($student['completion_date']);
    $lastReminder = !empty($student['last_reminder_sent_at']) ? new DateTime($student['last_reminder_sent_at']) : null;
    $nextDueDate = $lastReminder instanceof DateTime
        ? (clone $lastReminder)->modify('+30 days')
        : (clone $completionDate)->modify('+30 days');

    if ($today < $nextDueDate) {
        continue;
    }

    $subject = 'Reminder: Please collect your certificate';
    $message = "Hello {$student['full_name']},\n\nThis is a friendly reminder that your certificate for {$student['course_name']} is ready for collection. Please visit the office to collect it at your earliest convenience.\n\nThank you.\n";

    $success = sendEmail($student['email'], $subject, $message, $fromAddress, $smtpConfig);

    if ($success) {
        $stmtUpdate = $pdo->prepare('UPDATE students SET last_reminder_sent_at = :last_reminder_sent_at WHERE id = :id');
        $stmtUpdate->execute([
            ':last_reminder_sent_at' => date('Y-m-d H:i:s'),
            ':id' => $student['id'],
        ]);
        logEmailEvent($pdo, $student['student_id'], $student['email'], $subject, 'sent');
        logActivity("Reminder sent to {$student['full_name']} <{$student['email']}> for {$student['course_name']}");
        $sentCount++;
    } else {
        logEmailEvent($pdo, $student['student_id'], $student['email'], $subject, 'failed', 'Email delivery failed');
        logActivity("Reminder failed for {$student['full_name']} <{$student['email']}>: email delivery failed");
        $errors[] = "Failed to send reminder to {$student['email']}";
    }
}

if (php_sapi_name() === 'cli') {
    echo "Reminder job completed. Sent {$sentCount} reminder(s).\n";
    if (!empty($errors)) {
        echo implode("\n", $errors) . "\n";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Reminders</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container">
        <section class="card">
            <h1>Reminder job completed</h1>
            <p>Sent <?php echo htmlspecialchars((string) $sentCount, ENT_QUOTES, 'UTF-8'); ?> reminder(s).</p>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-info">
                    <p>Some reminders failed:</p>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <a href="index.php" class="secondary-link">Return to dashboard</a>
        </section>
    </div>
</body>
</html>
