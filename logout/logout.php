<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method Not Allowed';
    exit;
}

// If you want to use the helper in core/auth.php:
if (function_exists('logout_user')) {
    logout_user();
} else {
    // Fallback manual session cleanup
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// Redirect to sign in page
$base = rtrim(defined('BASE_URL') ? BASE_URL : '/', '/');
header('Location: ' . $base . '/sign-in/sign_in.php');
exit;
