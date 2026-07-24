<?php
require_once __DIR__ . '/includes/auth.php';

startSession();

if (isAdminAuthenticated()) {
    header('Location: index.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (loginAdmin($username, $password)) {
        header('Location: index.php');
        exit;
    }

    $message = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Certificate System</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container">
        <section class="card login-card">
            <h1>Admin Sign In</h1>
            <p>Only authorized administrators may manage student records and send reminders.</p>

            <?php if ($message !== ''): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" class="login-form">
                <label>
                    Username
                    <input type="text" name="username" required autofocus>
                </label>
                <label>
                    Password
                    <input type="password" name="password" required>
                </label>
                <div class="actions">
                    <button type="submit">Sign In</button>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
