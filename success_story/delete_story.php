<?php
// success_story/delete_story.php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';


if (session_status() === PHP_SESSION_NONE) session_start();

function bad($msg = 'Bad request', $code = 400)
{
    http_response_code($code);
    echo $msg;
    exit;
}
function is_owner(mysqli $conn, int $storyId, int $userId): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM success_stories WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $storyId, $userId);
    $stmt->execute();
    $stmt->store_result();
    $ok = $stmt->num_rows > 0;
    $stmt->close();
    return $ok;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad('Use POST');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) bad('Invalid id');

$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = current_role();
$conn   = db();
if (!($conn instanceof mysqli)) bad('DB not ready', 500);

// only admin or owner can delete
if (!($role === 'admin' || ($userId > 0 && is_owner($conn, $id, $userId)))) {
    bad('Forbidden', 403);
}

$stmt = $conn->prepare("DELETE FROM success_stories WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header("Location: success_story_feed.php");
exit;
