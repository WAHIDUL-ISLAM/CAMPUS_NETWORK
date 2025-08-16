<?php
// /events/delete_event.php
declare(strict_types=1);

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';

$userId  = (int)($_SESSION['user_id'] ?? 0);
$eventId = (int)($_GET['id'] ?? 0);

if ($userId <= 0) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/sign-in/sign_in.php');
    exit();
}
if ($eventId <= 0) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/events/event_sidebar.php?error=' . urlencode('Invalid event id'));
    exit();
}

/* DB connect */
$conn = db();
if (!($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection failed.');
}

/* Delete only if this user owns it */
$sql = "DELETE FROM `event` WHERE event_id = ? AND host_user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $eventId, $userId);
$stmt->execute();
$stmt->close();

/* Redirect back with a message */
header('Location: ' . rtrim(BASE_URL, '/') . '/events/event_sidebar.php?status=deleted');
exit();
