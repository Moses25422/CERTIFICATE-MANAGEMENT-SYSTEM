<?php
/**
 * Email Configuration Diagnostic Tool
 * Validates and tests email configuration
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/email.php';

const GREEN = "\033[92m";
const RED = "\033[91m";
const YELLOW = "\033[93m";
const BLUE = "\033[94m";
const RESET = "\033[0m";

echo BLUE . "\n╔════════════════════════════════════════════╗\n";
echo "║   Email Configuration Diagnostics            ║\n";
echo "╚════════════════════════════════════════════╝\n" . RESET . "\n";

// Check config file
echo BLUE . "1. Configuration File Check\n" . RESET;
$configFile = __DIR__ . '/config/smtp.json';

if (file_exists($configFile)) {
    echo GREEN . "✓ config/smtp.json exists\n" . RESET;
    $config = json_decode(file_get_contents($configFile), true);
    
    if ($config === null) {
        echo RED . "✗ Invalid JSON in config/smtp.json\n" . RESET;
        exit(1);
    }
    
    echo "\nConfiguration values:\n";
    echo "  Host: " . ($config['host'] ?? 'NOT SET') . "\n";
    echo "  Port: " . ($config['port'] ?? 'NOT SET') . "\n";
    echo "  Username: " . ($config['username'] ?? 'NOT SET') . "\n";
    echo "  Password: " . (isset($config['password']) ? "✓ Set" : "✗ Not set") . "\n";
    echo "  Encryption: " . ($config['encryption'] ?? 'NOT SET') . "\n";
    echo "  From Address: " . ($config['from_address'] ?? 'NOT SET') . "\n";
    echo "  From Name: " . ($config['from_name'] ?? 'NOT SET') . "\n";
} else {
    echo YELLOW . "⚠ config/smtp.json not found\n" . RESET;
    echo "  Copy from example: cp config/smtp.json.example config/smtp.json\n";
}

echo "\n";

// Validate configuration
echo BLUE . "2. Configuration Validation\n" . RESET;

$issues = [];

if (empty($config['host'])) {
    $issues[] = "Host is empty - should be 'smtp.gmail.com' or your SMTP server";
}

if (strpos($config['host'] ?? '', '@') !== false) {
    $issues[] = RED . "✗ Host contains '@' - you probably entered an email address instead of SMTP server\n" . RESET;
    $issues[] = "  Change from: " . $config['host'];
    $issues[] = "  Change to: smtp.gmail.com (for Gmail)";
}

if (empty($config['username'])) {
    $issues[] = "Username is empty";
}

if (strpos($config['username'] ?? '', 'kcaucertificates') !== false && $config['username'] === $config['host']) {
    $issues[] = RED . "✗ Username and Host are the same - Host should be SMTP server address\n" . RESET;
}

if (empty($config['password'])) {
    $issues[] = "Password is empty";
}

if (empty($config['from_address'])) {
    $issues[] = "From Address is empty";
}

if (!empty($issues)) {
    echo RED . "Issues found:\n" . RESET;
    foreach ($issues as $issue) {
        echo "  " . $issue . "\n";
    }
    echo "\n";
}

// Email service check
echo BLUE . "3. Email Service Status\n" . RESET;

$emailService = new EmailService();
$status = $emailService->getStatus();

if ($status['configured']) {
    echo GREEN . "✓ Email service is configured\n" . RESET;
    echo "  Host: " . $status['host'] . "\n";
    echo "  From: " . $status['from'] . "\n";
} else {
    echo RED . "✗ Email service is NOT configured\n" . RESET;
    echo "  Please ensure config/smtp.json has valid SMTP settings\n";
}

echo "\n";

// Test connectivity
echo BLUE . "4. Connection Test\n" . RESET;

if ($status['configured']) {
    $host = $config['host'];
    $port = $config['port'] ?? 587;
    
    echo "Testing connection to $host:$port...\n";
    
    $socket = @stream_socket_client($host . ':' . $port, $errno, $errstr, 5);
    
    if ($socket) {
        echo GREEN . "✓ Connection successful\n" . RESET;
        fclose($socket);
    } else {
        echo RED . "✗ Connection failed\n" . RESET;
        echo "  Error: $errstr (Code: $errno)\n";
        echo "  Common causes:\n";
        echo "    - Firewall blocking port $port\n";
        echo "    - Incorrect host address\n";
        echo "    - Network connectivity issue\n";
    }
} else {
    echo YELLOW . "⚠ Skipping connection test - not configured\n" . RESET;
}

echo "\n";

// Configuration examples
echo BLUE . "5. Example Configurations\n" . RESET;

echo "\nGmail (Recommended):\n";
echo <<<'EOT'
{
  "host": "smtp.gmail.com",
  "port": 587,
  "username": "your-email@gmail.com",
  "password": "your-16-char-app-password",
  "encryption": "tls",
  "from_address": "your-email@gmail.com",
  "from_name": "Certificate Office"
}
EOT;

echo "\n\nOutlook:\n";
echo <<<'EOT'
{
  "host": "smtp.office365.com",
  "port": 587,
  "username": "your-email@outlook.com",
  "password": "your-password",
  "encryption": "tls",
  "from_address": "your-email@outlook.com",
  "from_name": "Certificate Office"
}
EOT;

echo "\n\n";

// Summary
echo BLUE . "═════════════════════════════════════════════\n";
echo "SUMMARY\n";
echo "═════════════════════════════════════════════\n" . RESET;

if (empty($issues)) {
    echo GREEN . "✓ Configuration appears valid\n" . RESET;
    echo "\nNext steps:\n";
    echo "  1. Check your SMTP credentials are correct\n";
    echo "  2. For Gmail: Verify you have 2FA enabled\n";
    echo "  3. For Gmail: Generate App Password (not regular password)\n";
    echo "  4. Try adding a test student with email notification\n";
    echo "  5. Check the recipient's inbox (including spam folder)\n";
} else {
    echo RED . "✗ Configuration issues found\n" . RESET;
    echo "\nPlease fix the issues above and try again.\n";
    echo "\nFor Gmail setup:\n";
    echo "  1. Go to myaccount.google.com/apppasswords\n";
    echo "  2. Select 'Mail' and 'Windows Computer'\n";
    echo "  3. Copy the 16-character password\n";
    echo "  4. Paste into config/smtp.json under 'password'\n";
}

echo "\n";
