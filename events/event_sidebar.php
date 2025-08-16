<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';

$conn = db();

// Auth
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/sign-in/sign_in.php');
    exit;
}

// Current user info
$roleSql = "SELECT role, first_name, last_name FROM users WHERE id = ?";
$stmtRole = $conn->prepare($roleSql);
$stmtRole->bind_param("i", $userId);
$stmtRole->execute();
$userRow = $stmtRole->get_result()->fetch_assoc();
$userRole = $userRow['role'] ?? 'student';
$userName = trim(($userRow['first_name'] ?? '') . ' ' . ($userRow['last_name'] ?? ''));
$stmtRole->close();

// Filters
$filter      = $_GET['filter'] ?? 'upcoming';
$q           = trim($_GET['q'] ?? '');
$hostId      = isset($_GET['host']) && $_GET['host'] !== '' ? (int)$_GET['host'] : null;
$onlyApplied = isset($_GET['mine']) && $_GET['mine'] === '1';
$sort        = $_GET['sort'] ?? 'soonest';
$createdBy   = $_GET['created_by'] ?? '';

$where  = [];
$params = [];
$types  = '';

// Search
if ($q !== '') {
    $where[] = "(e.title LIKE CONCAT('%', ?, '%') OR e.description LIKE CONCAT('%', ?, '%') OR e.location LIKE CONCAT('%', ?, '%'))";
    $params = array_merge($params, [$q, $q, $q]);
    $types  .= 'sss';
}

// Status filter
$nowStr = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
if ($filter === 'upcoming') {
    $where[] = "e.event_date >= ?";
    $params[] = $nowStr;
    $types .= 's';
} elseif ($filter === 'past') {
    $where[] = "e.event_date < ?";
    $params[] = $nowStr;
    $types .= 's';
}

// Host filter
if ($hostId) {
    $where[] = "e.host_user_id = ?";
    $params[] = $hostId;
    $types .= 'i';
}

// Created by filter (only non-students)
if (strtolower($userRole) !== 'student') {
    if ($createdBy === 'me') {
        $where[] = "e.host_user_id = ?";
        $params[] = $userId;
        $types .= 'i';
    }
    // "everyone" means no restriction
}

// Order
switch ($sort) {
    case 'latest':
        $orderSql = "ORDER BY e.event_date DESC";
        break;
    case 'popular':
        $orderSql = "ORDER BY going DESC, e.event_date ASC";
        break;
    case 'created':
        $orderSql = "ORDER BY e.created_at DESC";
        break;
    default:
        $orderSql = ($filter === 'past') ? "ORDER BY e.event_date DESC" : "ORDER BY e.event_date ASC";
        break;
}

// SQL to get events
$baseSql = "
SELECT 
  e.event_id, e.title, e.description, e.event_date, e.location, e.host_user_id, e.created_at,
  u.first_name AS host_first, u.last_name AS host_last,
  COUNT(DISTINCT ep.user_id) AS going,
  MAX(CASE WHEN ep_self.user_id IS NOT NULL THEN 1 ELSE 0 END) AS i_applied
FROM event AS e
LEFT JOIN users AS u ON u.id = e.host_user_id
LEFT JOIN event_participant AS ep       ON ep.event_id = e.event_id
LEFT JOIN event_participant AS ep_self  ON ep_self.event_id = e.event_id AND ep_self.user_id = ?
";

$paramsWithSelf = array_merge([$userId], $params);
$typesWithSelf  = 'i' . $types;
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$mineClause = $onlyApplied ? (($whereSql ? " AND " : " WHERE ") . "ep_self.user_id IS NOT NULL") : "";

$sql = $baseSql . " " . $whereSql . $mineClause . "
GROUP BY e.event_id, e.title, e.description, e.event_date, e.location, e.host_user_id, e.created_at, u.first_name, u.last_name
" . $orderSql;

$stmt = $conn->prepare($sql);
if ($typesWithSelf !== '') {
    $stmt->bind_param($typesWithSelf, ...$paramsWithSelf);
}
$stmt->execute();
$res = $stmt->get_result();
$events = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Host dropdown list
$hostRows = [];
$hostSql = "
SELECT DISTINCT e.host_user_id, u.first_name, u.last_name
FROM event e
LEFT JOIN users u ON u.id = e.host_user_id
WHERE e.host_user_id IS NOT NULL
ORDER BY u.first_name, u.last_name
";
$hst = $conn->prepare($hostSql);
$hst->execute();
$hostRows = $hst->get_result()->fetch_all(MYSQLI_ASSOC);
$hst->close();

// Topbar stats
$upcomingCount = 0;
$stmtCount = $conn->prepare("SELECT COUNT(*) AS total FROM event WHERE event_date >= NOW()");
$stmtCount->execute();
$upcomingCount = (int)$stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();

$myApplicationsCount = 0;
$stmtCount = $conn->prepare("SELECT COUNT(*) AS total FROM event_participant WHERE user_id = ?");
$stmtCount->bind_param("i", $userId);
$stmtCount->execute();
$myApplicationsCount = (int)$stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();

$totalFilteredEvents = count($events);

// Helpers
function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
function isPast(string $dt): bool
{
    return (new DateTimeImmutable($dt)) < new DateTimeImmutable('now');
}
function isSoon(string $dt): bool
{
    $now = new DateTimeImmutable('now');
    $when = new DateTimeImmutable($dt);
    $diff = $when->getTimestamp() - $now->getTimestamp();
    return ($diff > 0 && $diff <= 7 * 24 * 3600);
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Events</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="event_sidebar.css?v=<?= time() ?>">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="../side_bar.css">
</head>

<body>

    <div class="events-shell">
        <?php include __DIR__ . '/../side_bar.php'; ?>

        <div class="events-page">
            <div class="main">
                <header class="topbar">
                    <h1 class="page-title"><i class="ri-calendar-2-line"></i> Events</h1>
                    <div class="stats">
                        <div class="stat"><i class="ri-time-line"></i><span><?= $upcomingCount ?> Upcoming</span></div>
                        <div class="stat"><i class="ri-user-add-line"></i><span><?= $myApplicationsCount ?> My Applications</span></div>
                        <div class="stat"><i class="ri-filter-3-line"></i><span><?= $totalFilteredEvents ?> Matching</span></div>
                    </div>
                </header>

                <!-- flash/status messages -->
                <?php if (isset($_GET['status'])): ?>
                    <?php $st = $_GET['status']; ?>
                    <div class="alert <?= in_array($st, ['success', 'updated', 'deleted']) ? 'alert-success' : (in_array($st, ['already', 'forbidden']) ? 'alert-warning' : 'alert-danger') ?> mt-3">
                        <?php
                        switch ($st) {
                            case 'success':
                                echo 'Application submitted ✅';
                                break;
                            case 'already':
                                echo 'You already applied to this event.';
                                break;
                            case 'updated':
                                echo 'Event updated.';
                                break;
                            case 'deleted':
                                echo 'Event deleted.';
                                break;
                            case 'forbidden':
                                echo 'You are not allowed to perform that action.';
                                break;
                            default:
                                echo 'Something went wrong. Try again.';
                                break;
                        }
                        ?>
                    </div>
                <?php endif; ?>

                <form class="toolbar" method="get" action="">
                    <div class="field-group">
                        <i class="ri-search-line icon"></i>
                        <input class="field field--search" type="search" name="q" placeholder="Search events…" value="<?= h($q) ?>">
                    </div>
                    <select class="field" name="filter">
                        <option value="upcoming" <?= $filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="all" <?= $filter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="past" <?= $filter === 'past' ? 'selected' : ''; ?>>Past</option>
                    </select>
                    <?php if (in_array(strtolower(trim($userRole)), ['admin', 'alumni'])): ?>
                        <select class="field" name="created_by">
                            <option value="me" <?= $createdBy === 'me' ? 'selected' : ''; ?>>Me</option>
                            <option value="everyone" <?= $createdBy === 'everyone' ? 'selected' : ''; ?>>Everyone</option>
                        </select>
                    <?php endif; ?>

                    <select class="field" name="sort">
                        <option value="soonest" <?= $sort === 'soonest' ? 'selected' : ''; ?>>Soonest</option>
                        <option value="latest" <?= $sort === 'latest' ? 'selected' : ''; ?>>Latest</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                        <option value="created" <?= $sort === 'created' ? 'selected' : ''; ?>>Recently Created</option>
                    </select>
                    <label class="switch">
                        <input type="checkbox" name="mine" value="1" <?= $onlyApplied ? 'checked' : ''; ?>>
                        <span class="switch__track"></span>
                        <span class="switch__label"><i class="ri-check-double-line"></i> My applications</span>
                    </label>
                    <button class="btn" type="submit"><i class="ri-equalizer-line"></i>Apply</button>
                    <a class="btn btn--ghost" href="?"><i class="ri-refresh-line"></i>Reset</a>
                </form>

                <main class="content">
                    <?php if (!$events): ?>
                        <div class="empty"><i class="ri-calendar-line"></i>
                            <p>No events match your filters.</p>
                        </div>
                    <?php else: ?>
                        <section class="deck">
                            <?php foreach ($events as $ev):
                                $eid     = (int)$ev['event_id'];
                                $title   = $ev['title'] ?? 'Untitled Event';
                                $desc    = $ev['description'] ?? '';
                                $at      = new DateTimeImmutable($ev['event_date']);
                                $past    = isPast($ev['event_date']);
                                $soon    = !$past && isSoon($ev['event_date']);
                                $loc     = $ev['location'] ?? '';
                                $count   = (int)$ev['going'];
                                $hostN   = trim(($ev['host_first'] ?? '') . ' ' . ($ev['host_last'] ?? ''));
                                $applied = (int)$ev['i_applied'] === 1;
                                $isOwner = ((int)($ev['host_user_id'] ?? 0) === $userId);
                                $isAdmin = (strtolower($userRole) === 'admin');
                            ?>
                                <article class="card" aria-labelledby="e-<?= $eid; ?>">
                                    <div class="card__body">
                                        <div class="card__head">
                                            <h3 id="e-<?= $eid; ?>" class="title"><?= h($title); ?></h3>
                                            <span class="chip <?= $applied ? 'chip--applied' : 'chip--muted'; ?>">
                                                <?= $applied ? 'Applied' : 'Not applied'; ?>
                                            </span>
                                        </div>
                                        <ul class="meta">
                                            <li class="meta__item">
                                                <i class="ri-calendar-event-line" style="color:#22c55e;"></i>
                                                <time><?= $at->format('D, M j · H:i'); ?></time>
                                            </li>
                                            <?php if ($loc): ?>
                                                <li class="meta__item">
                                                    <i class="ri-map-pin-line" style="color:#22c55e;"></i>
                                                    <span><?= h($loc); ?></span>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($hostN): ?>
                                                <li class="meta__item">
                                                    <i class="ri-user-3-line" style="color:#22c55e;"></i>
                                                    <span>Created by: <?= h($hostN); ?></span>
                                                </li>
                                            <?php endif; ?>
                                            <li class="meta__item">
                                                <i class="ri-group-line" style="color:#22c55e;"></i>
                                                <span><?= $count; ?> going</span>
                                            </li>
                                        </ul>

                                        <p class="desc">
                                            <a href="#"
                                                data-bs-toggle="modal"
                                                data-bs-target="#descModal"
                                                data-title="<?= h($title) ?>"
                                                data-desc="<?= h($desc ?? '') ?>"
                                                class="desc-link " style="text-decoration: none;">
                                                Click for Description <i class="ri-arrow-right-s-line"></i>
                                            </a>
                                        </p>

                                        <div class="card__actions" style="display:flex; gap:.5rem; flex-wrap:wrap;">
                                            <?php if ($past): ?>
                                                <button class="btn btn--ghost"
                                                    style="background-color:#ff5b00; color:white; padding:8px 16px; border-radius:6px; text-decoration:none; margin-left:5px; display:inline-block;"
                                                    disabled>
                                                    Event Ended
                                                </button>

                                            <?php elseif ($applied): ?>
                                                <button class="btn btn--ghost btn--student-applied" disabled>Applied</button>
                                            <?php elseif ($userRole === 'student'): ?>
                                                <a href="#"
                                                    class="btn btn--primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#applyModal"
                                                    data-event-id="<?= (int)$eid ?>"
                                                    data-event-title="<?= h($title) ?>">
                                                    <i class="ri-add-line"></i>Apply
                                                </a>
                                            <?php endif; ?>


                                            <!-- Owner/Admin controls (UI only; server still enforces) -->
                                            <?php if ($isOwner || $isAdmin): ?>
                                                <a href="edit_event.php?id=<?= (int)$eid ?>"
                                                    style="background-color:#3b82f6; color:white; padding:8px 16px; border-radius:6px; text-decoration:none; margin-left:5px; display:inline-block;">
                                                    <i class="ri-edit-line"></i> Edit
                                                </a>
                                                <form method="get" action="delete_event.php"
                                                    onsubmit="return confirm('Delete this event?');"
                                                    style="display:inline;">
                                                    <input type="hidden" name="id" value="<?= (int)$eid ?>">
                                                    <button type="submit"
                                                        style="background-color:#E4004B; color:white; padding:8px 16px; border:none; border-radius:6px; margin-left:5px; cursor:pointer;">
                                                        <i class="ri-delete-bin-line"></i> Delete
                                                    </button>
                                                </form>

                                            <?php endif; ?>
                                        </div>

                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </section>
                    <?php endif; ?>
                </main>

                <footer class="footer">
                    <span class="muted">Showing <?= count($events); ?> event(s)</span>
                </footer>
            </div>
        </div>
    </div>

    <!-- Apply Modal -->
    <div class="modal fade" id="applyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ri-user-add-line me-2"></i>
                        Apply to <span id="applyEventTitle" style="color:#22c55e; font-weight:bold; font-size:1.1em;">Event</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="post" action="/CAMPUS_NETWORK/event/join_event.php">
                    <div class="modal-body">
                        <input type="hidden" name="event_id" id="applyEventId">

                        <div class="mb-3">
                            <label class="form-label">Student ID <span class="text-danger">*</span></label>
                            <input name="student_id" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input name="phone" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department <span class="text-danger">*</span></label>
                            <input name="department" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Major <span class="text-danger">*</span></label>
                            <input name="major" class="form-control" required>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button"
                            data-bs-dismiss="modal"
                            style="background-color:#ef4444; border:none; padding:8px 20px; font-weight:bold; color:white; border-radius:6px; cursor:pointer;">
                            Cancel
                        </button>
                        <button type="submit"
                            class="btn btn--primary"
                            style="background-color:#22c55e; border:none; padding:8px 20px; font-weight:bold; color:white; border-radius:6px; cursor:pointer;">
                            Submit
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="descModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content custom-popup">
                <div class="modal-header">
                    <h5 class="modal-title" id="descModalTitle">Event Title</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="descModalBody"></div>
            </div>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('applyModal');
            if (!modal) return;

            modal.addEventListener('show.bs.modal', (evt) => {
                const btn = evt.relatedTarget;
                if (!btn) return;

                const id = btn.getAttribute('data-event-id') || '';
                const title = btn.getAttribute('data-event-title') || 'Event';

                const idInput = document.getElementById('applyEventId');
                const titleSpan = document.getElementById('applyEventTitle');

                if (idInput) idInput.value = id;
                if (titleSpan) titleSpan.textContent = title;
            });
        });
    </script>
    <script>
        // Fill modal content on open
        const descModalEl = document.getElementById('descModal');
        descModalEl.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            const title = trigger?.getAttribute('data-title') || 'Description';
            const raw = trigger?.getAttribute('data-desc') || '';

            const modalTitle = descModalEl.querySelector('#descModalTitle');
            const modalBody = descModalEl.querySelector('#descModalBody');

            modalTitle.textContent = `Description — ${title}`;

            // Show full text or fallback; preserve newlines
            const hasText = raw.trim().length > 0;
            modalBody.innerHTML = hasText ? raw.replace(/\n/g, '<br>') : 'Not available';
        });
    </script>


</body>

</html>