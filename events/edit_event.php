<?php
// /events/edit_event.php
declare(strict_types=1);

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';

/* Logged-in user */
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

/* Helpers */
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* Fetch existing event (owner-only) */
$sql = "SELECT e.event_id, e.title, e.description, e.event_date, e.location, e.poster, e.host_user_id
        FROM `event` e
        WHERE e.event_id = ? AND e.host_user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $eventId, $userId);
$stmt->execute();
$res = $stmt->get_result();
$ev  = $res->fetch_assoc();
$stmt->close();

if (!$ev) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/events/event_sidebar.php?error=' . urlencode('Forbidden or not found'));
    exit();
}

/* Pre-fill */
$title       = $ev['title'] ?? '';
$description = $ev['description'] ?? '';
$location    = $ev['location'] ?? '';

$eventDateRaw = (string)($ev['event_date'] ?? '');
$dt = strtotime($eventDateRaw);
$prefillDate = $dt ? date('Y-m-d', $dt) : '';
$prefillTime = $dt ? date('H:i', $dt)   : '';

$errors = [];

/* Handle POST (update) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date        = trim($_POST['event_date'] ?? '');
    $time        = trim($_POST['event_time'] ?? '');
    $location    = trim($_POST['location'] ?? '');

    // Validation
    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if ($date === '') {
        $errors[] = 'Event date is required.';
    }
    if ($location !== '' && mb_strlen($location) > 160) {
        $errors[] = 'Location must be ≤ 160 characters.';
    }

    // Build event datetime
    $eventDate = null;
    if ($date !== '') {
        $combined = $time !== '' ? ($date . ' ' . $time) : ($date . ' 00:00');
        $dt2 = DateTime::createFromFormat('Y-m-d H:i', $combined);
        if ($dt2 === false) {
            $errors[] = 'Invalid date/time.';
        } else {
            $eventDate = $dt2->format('Y-m-d H:i:s');
        }
    }

    if (!$errors && $eventDate !== null) {
        $sql = "UPDATE `event`
                   SET title = ?, description = ?, event_date = ?, location = ?, poster = NULL
                 WHERE event_id = ? AND host_user_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $desc = ($description !== '') ? $description : null;
            $loc  = ($location   !== '') ? $location   : null;
            $stmt->bind_param('ssssii', $title, $desc, $eventDate, $loc, $eventId, $userId);
            $stmt->execute();
            $stmt->close();

            // Redirect to the list with a success flag
            header('Location: ' . rtrim(BASE_URL, '/') . '/events/event_sidebar.php?status=updated');
            exit();
        } else {
            $errors[] = 'Failed to prepare update statement.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Event</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Styles -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="create_event.css">
</head>

<body class="container py-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h1 class="h5 d-flex align-items-center gap-2 mb-3">
                <span class="badge text-bg-primary">Edit</span>
                Update Event
            </h1>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold" for="title">Title</label>
                    <input type="text" class="form-control" id="title" name="title" maxlength="180" required
                        value="<?= h($title) ?>">
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold" for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="5"><?= h($description) ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="event_date">Date</label>
                    <input type="date" class="form-control" id="event_date" name="event_date" required
                        value="<?= h($prefillDate) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="event_time">Time</label>
                    <input type="time" class="form-control" id="event_time" name="event_time"
                        value="<?= h($prefillTime) ?>">
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold" for="location">Location</label>
                    <input type="text" class="form-control" id="location" name="location" maxlength="160"
                        value="<?= h($location) ?>">
                </div>

                <div class="d-flex gap-2 mt-2">
                    <button type="submit" class="btn cn-btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                    </button>
                    <a class="btn cn-btn-outline" href="<?= rtrim(BASE_URL, '/') ?>/events/event_sidebar.php">Cancel</a>
                </div>
            </form>

            <div class="small text-muted mt-3">
                Only the creator can edit this event.
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>