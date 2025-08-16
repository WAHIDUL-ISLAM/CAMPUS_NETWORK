<?php
// /event/join_event.php


require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';

/* ✅ Use the canonical key everywhere */
$userId = (int)($_SESSION['user_id'] ?? 0);

/* Auth guard */
if ($userId <= 0) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/signin.php?error=' . urlencode('Please sign in'));
    exit();
}

if (!isset($conn)) {
    $DB_HOST = defined('DB_HOST') ? DB_HOST : 'localhost';
    $DB_USER = defined('DB_USER') ? DB_USER : 'root';
    $DB_PASS = defined('DB_PASS') ? DB_PASS : '';
    $DB_NAME = defined('DB_NAME') ? DB_NAME : 'campus_network';
    $conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) {
        header("Location: events.php?error=" . urlencode("DB connection failed"));
        exit;
    }
}

function back_with($params = [])
{
    // Preserve original filters (range, month, q, from) from query string
    $keep = [];
    foreach (['range', 'month', 'q', 'from'] as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') $keep[$k] = $_GET[$k];
    }
    $qs = $params + $keep;
    $url = 'events.php' . ($qs ? ('?' . http_build_query($qs)) : '');
    header("Location: " . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back_with(['error' => 'Invalid request']);
}

$event_id   = isset($_POST['event_id'])   ? (int)$_POST['event_id'] : 0;
$student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
$phone      = isset($_POST['phone'])      ? trim($_POST['phone']) : '';
$department = isset($_POST['department']) ? trim($_POST['department']) : '';
$major      = isset($_POST['major'])      ? trim($_POST['major']) : '';



if ($event_id <= 0 || $student_id === '' || $phone === '' || $department === '' || $major === '') {
    back_with(['error' => 'Please fill in all required fields.']);
}

// Ensure event exists (foreign key safety)
$chk = $conn->prepare("SELECT 1 FROM event WHERE event_id = ?");
$chk->bind_param("i", $event_id);
$chk->execute();
$exists = $chk->get_result()->fetch_row();
$chk->close();

if (!$exists) {
    back_with(['error' => 'Event not found.']);
}

// Insert join/application
// Adjust table/columns to your schema. Example schema assumption:
// event_participant(event_id, user_id, student_id, phone, department, major, applied_at)
$sql = "INSERT INTO event_participant
        (event_id, user_id, student_id, phone, department, major, applied_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iissss", $event_id, $userId, $student_id, $phone, $department, $major);

try {
    $stmt->execute();
    $stmt->close();
    header("Location: event.php?status=success&event_id=" . $event_id);
    exit;
} catch (mysqli_sql_exception $e) {
    $stmt->close();
    if ((int)$e->getCode() === 1062) { // duplicate key
        header("Location: event.php?status=already&event_id=" . $event_id);
        exit;
    }
    header("Location: event.php?status=error&event_id=" . $event_id);
    exit;
}
