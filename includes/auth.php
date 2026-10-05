<?php

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function getAdminUsername(): string
{
    return getenv('ADMIN_USER') ?: 'PTTI2026';
}

function getAdminPasswordHash(): string
{
    return getenv('ADMIN_PASSWORD_HASH') ?: '$2y$10$Qf/T85ujyP80g7d0chXDyui35FA5kfYBgK07vA51EL5gds9ujDome';
}

function isAdminAuthenticated(): bool
{
    startSession();
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

function requireAdmin(): void
{
    if (php_sapi_name() === 'cli') {
        return;
    }

    if (!isAdminAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

function loginAdmin(string $username, string $password): bool
{
    $validUser = getAdminUsername();
    $validHash = getAdminPasswordHash();

    if ($username !== $validUser) {
        return false;
    }

    if (!password_verify($password, $validHash)) {
        return false;
    }

    startSession();
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_username'] = $validUser;
    return true;
}

function logoutAdmin(): void
{
    startSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }

    session_destroy();
}

function getLoggedInAdmin(): string
{
    startSession();
    return $_SESSION['admin_username'] ?? getAdminUsername();
}
