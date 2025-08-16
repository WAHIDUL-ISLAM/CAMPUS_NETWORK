<?php

declare(strict_types=1);

// Base URL for your app (leading + trailing slash recommended)
define('BASE_URL', '/CAMPUS_NETWORK/');

// Database config (adjust as needed)
define('DB_HOST', 'localhost');
define('DB_NAME', 'campus_network');
define('DB_USER', 'root');
define('DB_PASS', '');

// Use a consistent session name across all PHP endpoints (csrf-token.php, auth.php, etc.)
// session_name('CNSESSID');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Asia/Dhaka');
