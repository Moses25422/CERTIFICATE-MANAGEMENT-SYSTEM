<?php
require_once __DIR__ . '/includes/db.php';

$pdo = getDatabaseConnection();
$today = new DateTime('today');
$sentCount = 0;

$stmt = $pdo->query('SELECT * FROM students WHERE collected = 0 ORDER BY completion_date ASC');
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    $headers = "From: no-reply@example.com\r\nReply-To: no-reply@example.com\r\nContent-Type: text/plain; charset=UTF-8\r\n";

    $success = mail($student['email'], $subject, $message, $headers);

    if ($success) {
        $stmt = $pdo->prepare('UPDATE students SET last_reminder_sent_at = :last_reminder_sent_at WHERE id = :id');
        $stmt->execute([
            ':last_reminder_sent_at' => date('Y-m-d H:i:s'),
            ':id' => $student['id'],
        ]);
        logEmailEvent($pdo, $student['student_id'], $student['email'], $subject, 'sent');
        logActivity("Reminder sent to {$student['full_name']} <{$student['email']}> for {$student['course_name']}");
        $sentCount++;
    } else {
        logEmailEvent($pdo, $student['student_id'], $student['email'], $subject, 'failed', 'Email delivery failed');
        logActivity("Reminder failed for {$student['full_name']} <{$student['email']}>: email delivery failed");
    }
}

echo "Reminder job completed. Sent {$sentCount} reminder(s).\n";
