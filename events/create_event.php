<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';


$conn = db();
if (!($conn instanceof mysqli)) {
    die('Database connection not initialized. Check core/db.php → db()');
}

/* Auth guard */
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/sign-in/sign_in.php');
    exit();
}

/* Helper */
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* CSRF */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$errors  = [];
$created = isset($_GET['created']) && $_GET['created'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    }

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $date        = trim($_POST['event_date'] ?? '');
    $time        = trim($_POST['event_time'] ?? '');

    if ($title === '' || mb_strlen($title) > 180) {
        $errors[] = 'Title is required and must be ≤ 180 characters.';
    }
    if ($location !== '' && mb_strlen($location) > 160) {
        $errors[] = 'Location must be ≤ 160 characters.';
    }
    if ($date === '' || $time === '') {
        $errors[] = 'Event date and time are required.';
    }

    $eventDate = null;
    if ($date && $time) {
        $dt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
        if ($dt === false) {
            $errors[] = 'Invalid date/time.';
        } else {
            $eventDate = $dt->format('Y-m-d H:i:s');
        }
    }

    if (!$errors && $eventDate !== null) {
        $sql = "INSERT INTO `event`
                (`title`,`description`,`event_date`,`location`,`poster`,`host_user_id`)
                VALUES (?,?,?,?,NULL,?)";
        if ($stmt = $conn->prepare($sql)) {
            $desc = ($description !== '') ? $description : null;
            $loc  = ($location   !== '') ? $location   : null;
            $stmt->bind_param('ssssi', $title, $desc, $eventDate, $loc, $userId);
            if ($stmt->execute()) {
                // PRG: clear POST & show success
                $self = strtok($_SERVER['REQUEST_URI'], '?');
                header('Location: ' . $self . '?created=1');
                exit();
            } else {
                $errors[] = 'Insert failed.';
            }
            $stmt->close();
        } else {
            $errors[] = 'Prepare failed.';
        }
    }
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Create Event (Alumni)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="create_event.css" rel="stylesheet">
</head>

<body class="bg-body-tertiary">

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h1 class="h5 mb-3">Create Event <span class="badge text-bg-primary align-middle">Alumni</span></h1>
                        <p class="text-muted mb-4">Fill the details below.</p>

                        <?php if ($errors): ?>
                            <div class="alert alert-danger rounded-3">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $e): ?>
                                        <li><?php echo h($e); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($created): ?>
                            <!-- Action buttons after success -->
                            <div class="d-flex gap-2 mb-3">
                                <a class="btn cn-btn-primary" href="<?= rtrim(BASE_URL, '/'); ?>/dashboard/dashboard.php">Go to Dashboard</a>
                                <a class="btn cn-btn-outline" href="<?= rtrim(BASE_URL, '/'); ?>/events/event_sidebar.php">View Events</a>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="row g-3">
                            <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">

                            <div class="col-12">
                                <label class="form-label fw-semibold" for="title">Title</label>
                                <input type="text" class="form-control" id="title" name="title" maxlength="180" required
                                    value="<?php echo !$errors && $created ? '' : h($_POST['title'] ?? ''); ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold" for="description">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5">
                                    <?php echo !$errors && $created ? '' : h($_POST['description'] ?? ''); ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="event_date">Date</label>
                                <input type="date" class="form-control" id="event_date" name="event_date" required
                                    value="<?php echo !$errors && $created ? '' : h($_POST['event_date'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="event_time">Time</label>
                                <input type="time" class="form-control" id="event_time" name="event_time" required
                                    value="<?php echo !$errors && $created ? '' : h($_POST['event_time'] ?? ''); ?>">
                            </div>

                            <div class="col-md-8">
                                <label class="form-label fw-semibold" for="location">Location</label>
                                <input type="text" class="form-control" id="location" name="location" maxlength="160"
                                    placeholder="e.g., Auditorium A or Zoom"
                                    value="<?php echo !$errors && $created ? '' : h($_POST['location'] ?? ''); ?>">
                            </div>

                            <div class="col-12 d-flex gap-2">
                                <a class="btn cn-btn-outline" href="<?= rtrim(BASE_URL, '/'); ?>/dashboard/dashboard.php">Cancel</a>
                                <button class="btn cn-btn-primary" type="submit">Create Event</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast container (TOP-RIGHT) -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1100">
        <div id="successToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ✅ Event has been created successfully!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($created): ?>
        <script>
            // Show success toast at top-right
            const toastEl = document.getElementById('successToast');
            const toast = new bootstrap.Toast(toastEl, {
                delay: 4500
            });
            toast.show();
        </script>
    <?php endif; ?>
</body>

</html>