<?php
/**
 * Email utility for sending student notifications
 */

require_once __DIR__ . '/db.php';

class EmailService
{
    private $smtpConfig;

    public function __construct()
    {
        $this->smtpConfig = $this->getSmtpConfig();
    }

    /**
     * Get SMTP configuration
     */
    private function getSmtpConfig(): array
    {
        $configFile = __DIR__ . '/../config/smtp.json';
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

    /**
     * Check if email is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->smtpConfig['host']) && !empty($this->smtpConfig['username']);
    }

    /**
     * Get SMTP configuration status
     */
    public function getStatus(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'host' => $this->smtpConfig['host'],
            'from' => $this->smtpConfig['from_address'],
        ];
    }

    /**
     * Send certificate ready notification
     */
    public function sendCertificateReadyNotification(string $studentName, string $studentEmail, string $courseName): bool
    {
        $subject = 'Your Certificate is Ready for Collection';
        $body = $this->getCertificateReadyTemplate($studentName, $courseName);
        
        return $this->sendEmail($studentEmail, $subject, $body);
    }

    /**
     * Send certificate collection reminder
     */
    public function sendCertificateReminder(string $studentName, string $studentEmail, string $courseName): bool
    {
        $subject = 'Reminder: Please Collect Your Certificate';
        $body = $this->getCertificateReminderTemplate($studentName, $courseName);
        
        return $this->sendEmail($studentEmail, $subject, $body);
    }

    /**
     * Send bulk reminders to pending certificates
     */
    public function sendPendingReminders(): array
    {
        $pdo = getDatabaseConnection();
        $today = new DateTime('today');
        $sentCount = 0;
        $failedCount = 0;
        $errors = [];

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

            if ($this->sendCertificateReminder($student['full_name'], $student['email'], $student['course_name'])) {
                $this->updateLastReminder($pdo, $student['id']);
                $this->logEmailEvent($pdo, $student['student_id'], $student['email'], 'Certificate Reminder', 'sent');
                $sentCount++;
            } else {
                $this->logEmailEvent($pdo, $student['student_id'], $student['email'], 'Certificate Reminder', 'failed', 'Email delivery failed');
                $failedCount++;
                $errors[] = "Failed to send reminder to {$student['email']}";
            }
        }

        return [
            'success' => $failedCount === 0,
            'sent' => $sentCount,
            'failed' => $failedCount,
            'errors' => $errors,
        ];
    }

    /**
     * Send email via SMTP or PHP mail
     */
    private function sendEmail(string $to, string $subject, string $body): bool
    {
        if ($this->smtpConfig['host'] !== '') {
            return $this->smtpSendMail($to, $subject, $body);
        }

        $headers = "From: {$this->smtpConfig['from_name']} <{$this->smtpConfig['from_address']}>\r\n";
        $headers .= "Reply-To: {$this->smtpConfig['from_address']}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        return mail($to, $subject, $body, $headers);
    }

    /**
     * Send email via SMTP
     */
    private function smtpSendMail(string $to, string $subject, string $body): bool
    {
        $host = $this->smtpConfig['host'];
        $port = (int) $this->smtpConfig['port'];
        $encryption = $this->smtpConfig['encryption'];
        $username = $this->smtpConfig['username'];
        $password = $this->smtpConfig['password'];
        $from = $this->smtpConfig['from_address'];
        $fromName = $this->smtpConfig['from_name'];

        $remoteSocket = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = stream_socket_client($remoteSocket, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, stream_context_create([]));
        
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
        $response = $this->smtpReadResponse($socket);
        
        if (strpos($response, '250') !== 0) {
            fwrite($socket, "HELO {$serverName}\r\n");
            $response = $this->smtpReadResponse($socket);
            if (strpos($response, '250') !== 0) {
                fclose($socket);
                return false;
            }
        }

        if ($encryption === 'tls') {
            fwrite($socket, "STARTTLS\r\n");
            $response = $this->smtpReadResponse($socket);
            if (strpos($response, '220') !== 0) {
                fclose($socket);
                return false;
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return false;
            }
            fwrite($socket, "EHLO {$serverName}\r\n");
            $response = $this->smtpReadResponse($socket);
            if (strpos($response, '250') !== 0) {
                fclose($socket);
                return false;
            }
        }

        if ($username !== '' && $password !== '') {
            fwrite($socket, "AUTH LOGIN\r\n");
            $response = $this->smtpReadResponse($socket);
            if (strpos($response, '334') !== 0) {
                fclose($socket);
                return false;
            }
            fwrite($socket, base64_encode($username) . "\r\n");
            $response = $this->smtpReadResponse($socket);
            if (strpos($response, '334') !== 0) {
                fclose($socket);
                return false;
            }
            fwrite($socket, base64_encode($password) . "\r\n");
            $response = $this->smtpReadResponse($socket);
            if (strpos($response, '235') !== 0) {
                fclose($socket);
                return false;
            }
        }

        fwrite($socket, "MAIL FROM:<{$from}>\r\n");
        $response = $this->smtpReadResponse($socket);
        if (strpos($response, '250') !== 0) {
            fclose($socket);
            return false;
        }

        fwrite($socket, "RCPT TO:<{$to}>\r\n");
        $response = $this->smtpReadResponse($socket);
        if (strpos($response, '250') !== 0 && strpos($response, '251') !== 0) {
            fclose($socket);
            return false;
        }

        fwrite($socket, "DATA\r\n");
        $response = $this->smtpReadResponse($socket);
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
        $response = $this->smtpReadResponse($socket);
        if (strpos($response, '250') !== 0) {
            fclose($socket);
            return false;
        }

        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return true;
    }

    /**
     * Read SMTP response
     */
    private function smtpReadResponse($socket): string
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

    /**
     * Update last reminder sent time
     */
    private function updateLastReminder(PDO $pdo, int $studentId): void
    {
        $stmt = $pdo->prepare('UPDATE students SET last_reminder_sent_at = :last_reminder_sent_at WHERE id = :id');
        $stmt->execute([
            ':last_reminder_sent_at' => date('Y-m-d H:i:s'),
            ':id' => $studentId,
        ]);
    }

    /**
     * Log email event to database
     */
    private function logEmailEvent(PDO $pdo, string $studentId, string $recipientEmail, string $subject, string $status, ?string $errorMessage = null): void
    {
        $stmt = $pdo->prepare('INSERT INTO email_logs (student_id, recipient_email, subject, status, error_message) VALUES (:student_id, :recipient_email, :subject, :status, :error_message)');
        $stmt->execute([
            ':student_id' => $studentId,
            ':recipient_email' => $recipientEmail,
            ':subject' => $subject,
            ':status' => $status,
            ':error_message' => $errorMessage,
        ]);
    }

    /**
     * Get certificate ready email template
     */
    private function getCertificateReadyTemplate(string $studentName, string $courseName): string
    {
        return <<<EOT
Hello {$studentName},

Great news! Your certificate for {$courseName} is now ready for collection.

Please visit the Certificate Office at your earliest convenience to collect your certificate.

Office Hours:
Monday - Friday: 9:00 AM - 4:00 PM
Saturday: 9:00 AM - 12:00 PM

Please bring a valid form of identification with you.

If you have any questions or need to arrange a different collection time, please contact us at your earliest convenience.

Thank you,
Certificate Management Team
EOT;
    }

    /**
     * Get certificate reminder email template
     */
    private function getCertificateReminderTemplate(string $studentName, string $courseName): string
    {
        return <<<EOT
Hello {$studentName},

This is a friendly reminder that your certificate for {$courseName} is ready and waiting for collection.

Please visit the Certificate Office to collect your certificate as soon as possible.

Office Hours:
Monday - Friday: 9:00 AM - 4:00 PM
Saturday: 9:00 AM - 12:00 PM

If you have already collected your certificate, please disregard this message.

Thank you,
Certificate Management Team
EOT;
    }
}
