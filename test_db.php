<?php
require_once __DIR__ . '/core/db.php';

try {
    $pdo = db();
    echo "✅ Database connection successful!";
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage();
}
