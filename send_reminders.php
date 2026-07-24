<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSession();
requireAdmin();

$pdo = getDatabaseConnection();
$today = new DateTime('today');
$sentCount = 0;
$errors = [];

function getSmtpConfig(): array
{
    $configFile = __DIR__ . '/config/smtp.json';
    $cfg = [];
    if (file_exists($configFile)) {
        $json = file_get_contents($configFile);
        $cfg = json_decode($json, true) ?: [];
    }

    return [
        'host' => $cfg['host'] ?? getenv('SMTP_HOST') ?: '',
        'port' => $cfg['port'] ?? getenv('SMTP_PORT') ?: '587',
        'username' => $cfg['username'] ?? getenv('SMTP_USER') ?: '',
        'password' => $cfg['password'] ?? getenv('SMTP_PASS') ?: '',
        'encryption' => strtolower($cfg['encryption'] ?? getenv('SMTP_ENCRYPTION') ?: 'tls'),
        'from_address' => $cfg['from_address'] ?? getenv('SMTP_FROM_ADDRESS') ?: 'no-reply@example.com',
        'from_name' => $cfg['from_name'] ?? getenv('SMTP_FROM_NAME') ?: 'Certificate Office',
    ];
}

function smtpSendMail(string $to, string $subject, string $body, array $smtpConfig): bool
{
    $host = $smtpConfig['host'];
    $port = (int) $smtpConfig['port'];
    $encryption = $smtpConfig['encryption'];
    $username = $smtpConfig['username'];
    $password = $smtpConfig['password'];
    $from = $smtpConfig['from_address'];
    $fromName = $smtpConfig['from_name'];

    if ($host === '') {
        return false;
    }

    $remoteSocket = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $contextOptions = [];

    $socket = stream_socket_client($remoteSocket, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, stream_context_create($contextOptions));
    if ($socket === false) {
        return false;
    }

    stream_set_timeout($socket, 15);

    $response = fgets($socket, 515);
    if (strpos($response, '220') !== 0) {
        fclose($socket);
        return false;
    }

    $serverName = parse_url('http://localhost', PHP_URL_HOST);
    fwrite($socket, "EHLO {$serverName}\r\n");
    $response = smtpReadResponse($socket);
    if (strpos($response, '250') !== 0) {
        fwrite($socket, "HELO {$serverName}\r\n");
        $response = smtpReadResponse($socket);
        if (strpos($response, '250') !== 0) {
            fclose($socket);
            return false;
        }
    }

    if ($encryption === 'tls') {
        fwrite($socket, "STARTTLS\r\n");
        $response = smtpReadResponse($socket);
        if (strpos($response, '220') !== 0) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        fwrite($socket, "EHLO {$serverName}\r\n");
        $response = smtpReadResponse($socket);
        if (strpos($response, '250') !== 0) {
            fclose($socket);
            return false;
        }
    }

    if ($username !== '' && $password !== '') {
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = smtpReadResponse($socket);
        if (strpos($response, '334') !== 0) {
            fclose($socket);
            return false;
        }
        fwrite($socket, base64_encode($username) . "\r\n");
        $response = smtpReadResponse($socket);
        if (strpos($response, '334') !== 0) {
            fclose($socket);
            return false;
        }
        fwrite($socket, base64_encode($password) . "\r\n");
        $response = smtpReadResponse($socket);
        if (strpos($response, '235') !== 0) {
            fclose($socket);
            return false;
        }
    }

    fwrite($socket, "MAIL FROM:<{$from}>\r\n");
    $response = smtpReadResponse($socket);
    if (strpos($response, '250') !== 0) {
        fclose($socket);
        return false;
    }

    fwrite($socket, "RCPT TO:<{$to}>\r\n");
    $response = smtpReadResponse($socket);
    if (strpos($response, '250') !== 0 && strpos($response, '251') !== 0) {
        fclose($socket);
        return false;
    }

    fwrite($socket, "DATA\r\n");
    $response = smtpReadResponse($socket);
    if (strpos($response, '354') !== 0) {
        fclose($socket);
        return false;
    }

    $mimeBody = "From: {$fromName} <{$from}>\r\n" .
        "To: {$to}\r\n" .
        "Subject: {$subject}\r\n" .
        "Date: " . date('r') . "\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n" .
        "Content-Transfer-Encoding: 8bit\r\n" .
        "Reply-To: {$from}\r\n\r\n" .
        wordwrap($body, 998, "\r\n") . "\r\n.\r\n";

    fwrite($socket, $mimeBody);
    $response = smtpReadResponse($socket);
    if (strpos($response, '250') !== 0) {
        fclose($socket);
        return false;
    }

    fwrite($socket, "QUIT\r\n");
    fclose($socket);
    return true;
}

function smtpReadResponse($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function sendEmail(string $to, string $subject, string $body, string $from, array $smtpConfig): bool
{
    if ($smtpConfig['host'] !== '') {
        return smtpSendMail($to, $subject, $body, $smtpConfig);
    }

    $headers = "From: {$from}\r\nReply-To: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    return mail($to, $subject, $body, $headers);
}

$stmt = $pdo->query('SELECT * FROM students WHERE collected = 0 ORDER BY completion_date ASC');
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
$smtpConfig = getSmtpConfig();
$fromAddress = $smtpConfig['from_address'];

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
