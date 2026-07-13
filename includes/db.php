<?php

function getDatabaseConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $driver = getenv('DB_DRIVER') ?: 'sqlite';
    $databaseDir = __DIR__ . '/../database';

    if (!is_dir($databaseDir)) {
        mkdir($databaseDir, 0777, true);
    }

    if ($driver === 'mysql') {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $name = getenv('DB_NAME') ?: 'certificate_management';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    } else {
        $databasePath = getenv('DB_PATH') ?: $databaseDir . '/certificate_management.sqlite';
        $dsn = 'sqlite:' . $databasePath;
    }

    $pdo = new PDO($dsn, $driver === 'mysql' ? $user : null, $driver === 'mysql' ? $pass : null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    initializeSchema($pdo, $driver);

    return $pdo;
}

function initializeSchema(PDO $pdo, string $driver): void
{
    if ($driver === 'sqlite') {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS students (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                student_id TEXT NOT NULL UNIQUE,
                full_name TEXT NOT NULL,
                email TEXT NOT NULL,
                course_name TEXT NOT NULL,
                completion_date TEXT NOT NULL,
                signed_status INTEGER NOT NULL DEFAULT 0,
                collected INTEGER NOT NULL DEFAULT 0,
                collected_at TEXT,
                last_reminder_sent_at TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS email_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                student_id TEXT NOT NULL,
                recipient_email TEXT NOT NULL,
                subject TEXT NOT NULL,
                status TEXT NOT NULL,
                error_message TEXT,
                sent_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        SQL);
        return;
    }

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(50) NOT NULL UNIQUE,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            course_name VARCHAR(255) NOT NULL,
            completion_date DATE NOT NULL,
            signed_status TINYINT(1) NOT NULL DEFAULT 0,
            collected TINYINT(1) NOT NULL DEFAULT 0,
            collected_at DATETIME NULL,
            last_reminder_sent_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(50) NOT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            error_message TEXT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    SQL);
}

function generateStudentId(PDO $pdo): string
{
    $prefix = 'CERT-' . date('Ymd') . '-';
    $suffix = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    $candidate = $prefix . $suffix;

    $stmt = $pdo->prepare('SELECT id FROM students WHERE student_id = :student_id');
    $stmt->execute([':student_id' => $candidate]);
    if ($stmt->fetchColumn() === false) {
        return $candidate;
    }

    return generateStudentId($pdo);
}

function logEmailEvent(PDO $pdo, string $studentId, string $recipientEmail, string $subject, string $status, ?string $errorMessage = null): void
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

function logActivity(string $message): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . '/reminders.log';
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}
