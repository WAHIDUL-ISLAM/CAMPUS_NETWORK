<?php
// redirect to sign-in or dashboard
require_once __DIR__ . '/core/auth.php';
if (current_user()) {
    header('Location: /dashboard/index.php');
    exit;
}
header('Location: /sign-in/index.html');
exit;
