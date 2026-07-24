<?php
require_once __DIR__ . '/includes/auth.php';

startSession();
requireAdmin();

$configFile = __DIR__ . '/config/smtp.json';
$message = '';

// Load existing config if present
$config = [
    'host' => '',
    'port' => '587',
    'username' => '',
    'password' => '',
    'encryption' => 'tls',
    'from_address' => 'no-reply@example.com',
    'from_name' => 'Certificate Office',
];

if (file_exists($configFile)) {
    $json = file_get_contents($configFile);
    $cfg = json_decode($json, true);
    if (is_array($cfg)) {
        $config = array_merge($config, $cfg);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config['host'] = trim($_POST['host'] ?? '');
    $config['port'] = trim($_POST['port'] ?? '587');
    $config['username'] = trim($_POST['username'] ?? '');
    $config['password'] = trim($_POST['password'] ?? '');
    $config['encryption'] = trim($_POST['encryption'] ?? 'tls');
    $config['from_address'] = trim($_POST['from_address'] ?? 'no-reply@example.com');
    $config['from_name'] = trim($_POST['from_name'] ?? 'Certificate Office');

    if (!is_dir(__DIR__ . '/config')) {
        mkdir(__DIR__ . '/config', 0777, true);
    }

    // Persist to JSON file. Warning: stores password in plain text for local use.
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $_SESSION['flash_message'] = 'SMTP settings saved.';
    $_SESSION['flash_message_type'] = 'success';
    header('Location: settings.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Certificate System</title>
    <link rel="stylesheet" href="assets/styles.css">
    <style>
        .note { font-size: 13px; color: #6b7280; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-main">
                <div>
                    <h1>Settings</h1>
                    <p>Configure SMTP settings for sending reminder emails.</p>
                </div>
                <div class="header-actions">
                    <span>Signed in as <?php echo htmlspecialchars(getLoggedInAdmin(), ENT_QUOTES, 'UTF-8'); ?></span>
                    <a href="index.php" class="secondary-link">Dashboard</a>
                    <a href="logout.php" class="secondary-link">Sign out</a>
                </div>
            </div>
        </header>

        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_SESSION['flash_message_type'] ?? 'info', ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($_SESSION['flash_message'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_message_type']); ?>
        <?php endif; ?>

        <section class="card">
            <form method="post" class="settings-form">
                <label>
                    SMTP Host
                    <input type="text" name="host" value="<?php echo htmlspecialchars($config['host'], ENT_QUOTES, 'UTF-8'); ?>">
                </label>
                <label>
                    SMTP Port
                    <input type="text" name="port" value="<?php echo htmlspecialchars($config['port'], ENT_QUOTES, 'UTF-8'); ?>">
                </label>
                <label>
                    Username
                    <input type="text" name="username" value="<?php echo htmlspecialchars($config['username'], ENT_QUOTES, 'UTF-8'); ?>">
                </label>
                <label>
                    Password
                    <input type="password" name="password" value="<?php echo htmlspecialchars($config['password'], ENT_QUOTES, 'UTF-8'); ?>">
                </label>
                <label>
                    Encryption
                    <select name="encryption">
                        <option value="tls" <?php echo ($config['encryption'] === 'tls') ? 'selected' : ''; ?>>TLS</option>
                        <option value="ssl" <?php echo ($config['encryption'] === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                        <option value="" <?php echo ($config['encryption'] === '') ? 'selected' : ''; ?>>None</option>
                    </select>
                </label>
                <label>
                    From address
                    <input type="email" name="from_address" value="<?php echo htmlspecialchars($config['from_address'], ENT_QUOTES, 'UTF-8'); ?>">
                </label>
                <label>
                    From name
                    <input type="text" name="from_name" value="<?php echo htmlspecialchars($config['from_name'], ENT_QUOTES, 'UTF-8'); ?>">
                </label>

                <div class="note">Note: For local setups the SMTP password is stored in <code>config/smtp.json</code>. Keep this project directory secure.</div>

                <div class="actions">
                    <button type="submit">Save settings</button>
                    <a href="index.php" class="secondary-link">Cancel</a>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
