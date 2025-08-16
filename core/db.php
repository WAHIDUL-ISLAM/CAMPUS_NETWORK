<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';


/**
 * Get a shared MySQLi instance.
 */
function db(): \mysqli
{
    static $mysqli = null;
    if ($mysqli instanceof \mysqli) {
        return $mysqli;
    }

    // Create connection (default port 3306 if not specified in DB_HOST)
    $mysqli = @new \mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($mysqli->connect_errno) {
        // Log to server logs; don't echo (keeps JSON responses clean)
        error_log('DB connection failed: ' . $mysqli->connect_error);
        throw new \RuntimeException('Database connection failed');
    }

    // Optional: set charset
    if (!$mysqli->set_charset('utf8mb4')) {
        error_log('Error setting charset: ' . $mysqli->error);
    }

    return $mysqli;
}

// IMPORTANT: no closing PHP tag to avoid stray output
