<?php
// /event/events.php


// ---------- DB CONNECTION ----------
// If you already have central config/includes, prefer those:
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';

/* ✅ Use the canonical key everywhere */
$userId = (int)($_SESSION['user_id'] ?? 0);

/* Auth guard */
if ($userId <= 0) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/sign-in/sign_in.php');
    exit();
}
if (!isset($conn)) {
    $DB_HOST = defined('DB_HOST') ? DB_HOST : 'localhost';
    $DB_USER = defined('DB_USER') ? DB_USER : 'root';
    $DB_PASS = defined('DB_PASS') ? DB_PASS : '';
    $DB_NAME = defined('DB_NAME') ? DB_NAME : 'campus_network';

    $conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        echo "Database connection failed: " . htmlspecialchars($conn->connect_error);
        exit;
    }
    // Optional: set a consistent timezone for MySQL session
    // $conn->query("SET time_zone = '+00:00'");
}

// ---------- INPUTS ----------
$range = isset($_GET['range']) ? trim($_GET['range']) : '';
$month = isset($_GET['month']) ? trim($_GET['month']) : '';
$q     = isset($_GET['q'])     ? trim($_GET['q'])     : '';
$from  = isset($_GET['from'])  ? trim($_GET['from'])  : ''; // if "dashboard", back button goes there
$joined = isset($_GET['joined']) ? trim($_GET['joined']) : '';
$error  = isset($_GET['error'])  ? trim($_GET['error'])  : '';

// Build a "preserve filters" query string for links
function qs_preserve($extra = [])
{
    $base = [
        'range' => $_GET['range'] ?? '',
        'month' => $_GET['month'] ?? '',
        'q'     => $_GET['q'] ?? '',
        'from'  => $_GET['from'] ?? '',
    ];
    $merged = array_filter(array_merge($base, $extra), fn($v) => $v !== '');
    return $merged ? ('?' . http_build_query($merged)) : '';
}

// ---------- QUERY ----------
$sql = "SELECT e.event_id, 
               e.title, 
               e.description, 
               e.event_date, 
               e.location, 
               e.poster, 
               CONCAT(u.first_name, ' ', u.last_name) AS host_name
        FROM event e
        LEFT JOIN users u ON e.host_user_id = u.id
        WHERE DATE(e.event_date) >= CURDATE()";



$params = [];
$types  = "";

// next 6 months
if ($range === '6months') {
    $sql .= " AND event_date < DATE_ADD(CURDATE(), INTERVAL 6 MONTH)";
}

// exact month
if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
    $sql .= " AND DATE_FORMAT(event_date, '%Y-%m') = ?";
    $params[] = $month;
    $types   .= "s";
}

// search
if ($q !== '') {
    $like = "%" . $q . "%";
    $sql .= " AND (title LIKE ? OR description LIKE ? OR location LIKE ?)";
    array_push($params, $like, $like, $like);
    $types .= "sss";
}

$sql .= " ORDER BY event_date ASC";

$stmt = $conn->prepare($sql);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$events = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Add creator ids without touching your original SELECT ---

if (!empty($events)) {
    // Collect event IDs that came from the DB (safe to inline)
    $ids = array_map('intval', array_column($events, 'event_id'));
    $ids = array_values(array_unique(array_filter($ids))); // clean up

    if (!empty($ids)) {
        $idList = implode(',', $ids);
        $sqlOwners = "SELECT event_id, host_user_id FROM `event` WHERE event_id IN ($idList)";
        $res2 = $conn->query($sqlOwners);

        $ownerMap = [];
        if ($res2) {
            while ($row = $res2->fetch_assoc()) {
                $ownerMap[(int)$row['event_id']] = (int)$row['host_user_id'];
            }
            $res2->free();
        }

        // Attach as `user_id` so your existing check works
        foreach ($events as &$ev) {
            $eid = (int)$ev['event_id'];
            $ev['user_id'] = $ownerMap[$eid] ?? null;
        }
        unset($ev); // break reference
    }
}


function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Back button logic:
// If came from dashboard (your dashboard link should add &from=dashboard), go there.
// Otherwise default to ../dashboard.php
// Back button logic
if ($from === 'dashboard') {
    // Always point to dashboard folder
    $backUrl = rtrim(BASE_URL, '/') . '/dashboard/dashboard.php';
} else {
    // Fallback
    $backUrl = rtrim(BASE_URL, '/') . '/dashboard/dashboard.php';
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Events</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap & Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />

    <!-- External CSS -->
    <link rel="stylesheet" href="event.css">
</head>

<body>
    <?php if (isset($_GET['status'])): ?>
        <div class="container mt-3">
            <?php if ($_GET['status'] === 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    ✅ You have successfully applied to join this event.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($_GET['status'] === 'already'): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    ⚠️ You have already applied for this event.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['status']) && $_GET['status'] === 'updated'): ?>
                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                    <i class="fa-solid fa-circle-check me-1"></i> Event updated successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['status']) && $_GET['status'] === 'deleted'): ?>
                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                    <i class="fa-solid fa-trash me-1"></i> Event deleted successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>


        </div>
    <?php endif; ?>

    <div class="page-wrap container-fluid">
        <header class="page-header d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="page-title"><i class="fa-solid fa-calendar-days me-2"></i>Events</h1>
                <div class="small text-muted badges-row">
                    <?php if ($range === '6months'): ?>
                        <span class="badge badge-range rounded-pill px-3 py-2">Upcoming (next 6 months)</span>
                    <?php else: ?>
                        <span class="badge bg-light text-dark rounded-pill px-3 py-2">Upcoming (from today)</span>
                    <?php endif; ?>
                    <?php if ($month !== ''): ?>
                        <span class="badge bg-dark-subtle text-dark rounded-pill px-3 py-2 ms-2">Month: <?= h($month) ?></span>
                    <?php endif; ?>
                    <?php if ($q !== ''): ?>
                        <span class="badge bg-warning-subtle text-dark rounded-pill px-3 py-2 ms-2">Search: "<?= h($q) ?>"</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= h($backUrl) ?>" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </header>

        <?php if ($joined === '1'): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i>
                You have requested to join the event. We’ll be in touch!
            </div>
        <?php elseif ($error !== ''): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <form method="get" class="filters card card-body shadow-sm mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Search</label>
                    <div class="input-group">
                        <input type="text" name="q" value="<?= h($q) ?>" class="form-control" placeholder="Search title, description, location..." />
                        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label mb-1">Month (YYYY-MM)</label>
                    <input type="text" name="month" value="<?= h($month) ?>" class="form-control" placeholder="2025-08" pattern="\d{4}-\d{2}" />
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label mb-1">Range</label>
                    <select name="range" class="form-select">
                        <option value="" <?= ($range === '' ? 'selected' : '') ?>>Upcoming (all)</option>
                        <option value="6months" <?= ($range === '6months' ? 'selected' : '') ?>>Next 6 months</option>
                    </select>
                </div>
                <?php if ($from === 'dashboard'): ?>
                    <input type="hidden" name="from" value="dashboard">
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($events)): ?>
            <div class="empty text-center">
                <i class="fa-regular fa-calendar-xmark icon mb-3"></i>
                <div>No events found with the current filters.</div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($events as $ev):
                    $id = (int)$ev['event_id'];
                    $modalId = 'joinModal_' . $id;

                    $poster = trim((string)($ev['poster'] ?? ''));
                    $posterSrc = '';
                    if ($poster !== '') {
                        if (preg_match('~^https?://~i', $poster)) $posterSrc = $poster;
                        else $posterSrc = "../uploads/" . ltrim($poster, '/');
                    }
                ?>
                    <div class="col-12 col-md-6">
                        <article class="event-card card h-100 shadow-sm">
                            <?php if ($posterSrc !== ''): ?>
                                <img src="<?= h($posterSrc) ?>" alt="Poster" class="event-poster card-img-top" onerror="this.style.display='none'">
                            <?php endif; ?>

                            <div class="card-body">
                                <h2 class="h5 mb-2"><?= h($ev['title']) ?></h2>
                                <div class="event-meta mb-3">
                                    <span><i class="fa-regular fa-clock me-1"></i><?= h(date('D, M j, Y', strtotime($ev['event_date']))) ?></span>
                                    <span class="mx-2">•</span>
                                    <span><i class="fa-solid fa-location-dot me-1"></i><?= h($ev['location']) ?></span>
                                </div>

                                <?php if (!empty($ev['description'])): ?>
                                    <p class="text-muted mb-3"><?= nl2br(h(mb_strimwidth($ev['description'], 0, 220, '…'))) ?></p>
                                <?php endif; ?>

                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="small text-muted">
                                        <span class="host-label">Host:</span>
                                        <span class="host-name"><?= h($ev['host_name']) ?></span>
                                    </div>

                                    <?php if ((int)($ev['user_id'] ?? 0) === $userId): ?>
                                        <!-- Show only for event creator -->
                                        <div>
                                            <a href="edit_event.php?id=<?= $id ?>" class="btn btn-warning btn-sm">
                                                <i class="fa-solid fa-pen"></i> Update
                                            </a>
                                            <a href="delete_event.php?id=<?= $id ?>" class="btn btn-danger btn-sm"
                                                onclick="return confirm('Are you sure you want to delete this event?');">
                                                <i class="fa-solid fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <!-- Show Join button for others -->
                                        <button class="btn btn-primary btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#<?= $modalId ?>">
                                            <i class="fa-solid fa-user-plus me-1"></i> Join
                                        </button>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </article>
                    </div>

                    <!-- JOIN MODAL -->
                    <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content rounded-4">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="fa-solid fa-user-plus me-2"></i>Join Event: <?= h($ev['title']) ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="post" action="join_event.php<?= qs_preserve() ?>">
                                    <div class="modal-body">
                                        <input type="hidden" name="event_id" value="<?= $id ?>">
                                        <!-- If your app uses session user: -->
                                        <!-- <input type="hidden" name="user_id" value="<?= $_SESSION['user']['id'] ?? '' ?>"> -->

                                        <div class="mb-3">
                                            <label class="form-label">Student ID <span class="text-danger">*</span></label>
                                            <input type="text" name="student_id" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                            <input type="tel" name="phone" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Department <span class="text-danger">*</span></label>
                                            <input type="text" name="department" class="form-control" required>
                                        </div>
                                        <div class="mb-0">
                                            <label class="form-label">Major <span class="text-danger">*</span></label>
                                            <input type="text" name="major" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Submit</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <footer class="mt-5 text-center text-muted small">
            Showing <?= count($events) ?> event(s)
        </footer>
    </div>

    <!-- Bootstrap JS (for modal) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>