<?php

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';


$conn = db();
if (!($conn instanceof mysqli)) {
    die('Database connection not initialized. Check core/db.php → db()');
}


$userId = (int)($_SESSION['user_id'] ?? 0);

/* Auth guard */
if ($userId <= 0) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/sign-in/sign_in.php');
    exit();
}
$role = current_role();
$canQuickAction = in_array($role, ['admin', 'alumni'], true);

// Debug (view-source): remove when done
echo "<!-- role='{$role}', canQuickAction=" . ($canQuickAction ? '1' : '0') . " -->";



// Check if a table exists in the current DB
function table_exists(mysqli $c, string $table): bool
{
    $stmt = $c->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $table);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_row();
}


// 3) Auth guard (adjust sign-in path if different)
if (empty($_SESSION['user_id'])) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/signin.php');
    exit();
}


// 4) Small safe query helpers
function qrow(mysqli $c, string $sql, string $types = "", array $params = [], array $fallback = [])
{
    $stmt = $c->prepare($sql);
    if (!$stmt) return $fallback;
    if ($types && $params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? ($res->fetch_assoc() ?: $fallback) : $fallback;
}
function qval(mysqli $c, string $sql, string $types = "", array $params = [], $fallback = 0)
{
    $stmt = $c->prepare($sql);
    if (!$stmt) return $fallback;
    if ($types && $params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res) return $fallback;
    $row = $res->fetch_row();
    return $row ? (int)$row[0] : $fallback;
}
function qrows(mysqli $c, string $sql, string $types = "", array $params = [], array $fallback = [])
{
    $stmt = $c->prepare($sql);
    if (!$stmt) return $fallback;
    if ($types && $params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : $fallback;
}

// 5) Current user (adjust to your schema)
$current = qrow(
    $conn,
    "SELECT id, CONCAT(first_name, ' ', last_name) AS name, email 
     FROM users 
     WHERE id = ?",
    "i",
    [$userId],
    ['id' => $userId, 'name' => 'Member', 'email' => '']
);

// 6) KPIs (adjust table/field names to your DB)
// KPIs
$kpiEvents = table_exists($conn, 'event')
    ? qval(
        $conn,
        "SELECT COUNT(*) FROM event 
         WHERE event_date >= CURDATE() 
         AND event_date < DATE_ADD(CURDATE(), INTERVAL 6 MONTH)",
        "",
        [],
        0
    )
    : 0;

$kpiOpportunities = table_exists($conn, 'opportunities')
    ? qval($conn, "SELECT COUNT(*) FROM opportunities WHERE posted_date >= (CURDATE() - INTERVAL 7 DAY)", "", [], 5)
    : 5;

$kpiPosts = table_exists($conn, 'success_stories')
    ? qval($conn, "SELECT COUNT(*) FROM success_stories WHERE DATE(created_at) >= (CURDATE() - INTERVAL 30 DAY)", "", [], 28)
    : 28;


$kpiConnections = table_exists($conn, 'event_participant')
    ? qval($conn, "SELECT COUNT(DISTINCT user_id) FROM event_participant WHERE user_id = ?", "i", [$userId], 156)
    : 156;


// Lists
$events = table_exists($conn, 'event')
    ? qrows($conn, "
        SELECT event_id AS id, title, location, event_date
        FROM event
        WHERE DATE(event_date) >= CURDATE()
        ORDER BY event_date ASC
        LIMIT 3
    ")
    : [
        ['id' => 1, 'title' => 'AI & Data Science Meetup', 'location' => 'Auditorium A', 'event_date' => date('Y') . '-08-21'],
        ['id' => 2, 'title' => 'Alumni Networking Night', 'location' => 'Center Hall', 'event_date' => date('Y') . '-08-29'],
        ['id' => 3, 'title' => 'Resume Workshop', 'location' => 'Career Lab', 'event_date' => date('Y') . '-09-02'],
    ];

$jobs = table_exists($conn, 'opportunities')
    ? qrows($conn, "
        SELECT op_post_id AS id,
               title AS role,
               organization AS company,
               '' AS location,
               posted_date AS posted_at,
               '' AS tags
        FROM opportunities
        ORDER BY posted_date DESC
        LIMIT 2
    ")
    : [
        ['id' => 11, 'role' => 'Frontend Engineer', 'company' => 'Nova Labs', 'location' => 'Remote', 'posted_at' => date('Y-m-d'), 'tags' => 'React,TypeScript'],
        ['id' => 12, 'role' => 'Backend Developer', 'company' => 'Astra Corp', 'location' => 'Berlin', 'posted_at' => date('Y-m-d'), 'tags' => 'Python,Django'],
    ];

$channels = table_exists($conn, 'channels')
    ? qrows($conn, "
        SELECT id, name, member_count
        FROM channels
        ORDER BY member_count DESC
        LIMIT 3
    ")
    : [
        ['id' => 101, 'name' => '#mentorship', 'member_count' => 124],
        ['id' => 102, 'name' => '#internships', 'member_count' => 98],
        ['id' => 103, 'name' => '#alumni-talks', 'member_count' => 76],
    ];




?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Campus Networking · Dashboard</title>
    <meta name="description" content="Modern, responsive campus networking dashboard with events, jobs, and profile insights." />
    <meta name="color-scheme" content="light dark" />

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css"> <!-- must be AFTER Bootstrap -->
    <link rel="stylesheet" href="../side_bar.css"> <!-- must be AFTER Bootstrap -->
</head>

<body class="min-vh-100">
    <a class="visually-hidden-focusable skip-link" href="#main">Skip to main content</a>
    <div class="app d-flex">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../side_bar.php'; ?>

        <!-- Offcanvas (Mobile Nav) -->
        <div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="mobileNavLabel">Menu</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="list-unstyled d-grid gap-2">
                    <li><a class="btn btn-sm btn-outline-light w-100 text-start" href="<?php echo rtrim(BASE_URL, '/'); ?>/dashboard/dashboard.php"><i class="fa-solid fa-gauge me-2"></i> Dashboard</a></li>
                    <li><a class="btn btn-sm btn-outline-light w-100 text-start" href="<?php echo rtrim(BASE_URL, '/'); ?>/events/event.php"><i class="fa-solid fa-calendar-days me-2"></i> Events</a></li>
                    <li><a class="btn btn-sm btn-outline-light w-100 text-start" href="<?php echo rtrim(BASE_URL, '/'); ?>/jobs.php"><i class="fa-solid fa-briefcase me-2"></i> Jobs</a></li>
                    <li><a class="nav-link" href="<?php echo rtrim(BASE_URL, '/'); ?>/success_story/success_story_feed.php">
                            <i class="fa-solid fa-pen-to-square"></i> <span class="label">Posts</span>
                        </a>
                    </li>
                    <li><a class="btn btn-sm btn-outline-light w-100 text-start" href="<?php echo rtrim(BASE_URL, '/'); ?>/community.php"><i class="fa-solid fa-users me-2"></i> Community</a></li>
                    <li><a class="btn btn-sm btn-outline-light w-100 text-start" href="<?php echo rtrim(BASE_URL, '/'); ?>/profile.php"><i class="fa-solid fa-id-badge me-2"></i> Profile</a></li>
                    <li><a class="btn btn-sm btn-danger w-100 text-start" href="<?php echo rtrim(BASE_URL, '/'); ?>/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>

        <!-- Main Column -->
        <div class="flex-grow-1 d-flex flex-column">
            <!-- Top Header -->
            <header class="px-3 px-lg-4 py-3 border-bottom border-opacity-25 cn-topbar">
                <div class="d-flex align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <button class="btn btn-sm btn-light d-md-none" type="button" data-bs-toggle="offcanvas"
                            data-bs-target="#mobileNav" aria-controls="mobileNav" aria-label="Open menu">
                            <i class="fa-solid fa-bars"></i>
                        </button>
                        <form class="input-group" role="search" method="get" action="<?php echo rtrim(BASE_URL, '/'); ?>/search.php">
                            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input name="q" type="search" class="form-control" placeholder="Search events, jobs, people..." />
                        </form>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <!-- ✅ Quick Action trigger button (hidden for students) -->
                        <button class="btn btn-sm cn-btn-primary"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#quickActionModal"
                            <?php if (!$canQuickAction) echo 'style="display:none;"'; ?>>
                            <i class="fa-solid fa-plus me-1"></i> Quick Action
                        </button>

                        <div class="dropdown" style="display:inline-block; position:relative;">
                            <button
                                class="btn btn-sm cn-btn-outline dropdown-toggle"
                                type="button"
                                data-bs-toggle="dropdown">
                                <i class="fa-solid fa-user"></i>
                                <?php echo htmlspecialchars($current['name'] ?? 'Member'); ?>
                            </button>
                            <ul
                                class="dropdown-menu dropdown-menu-end"
                                style="border-radius: 8px; padding: 4px 0; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 160px; ">
                                <li>
                                    <a class="dropdown-item"
                                        href="<?php echo rtrim(BASE_URL, '/'); ?>/profile/profile.php"
                                        style="padding: 8px 16px; font-size: 14px ; transition: background-color 0.2s ease; font-weight: 800;"
                                        onmouseover="this.style.backgroundColor='#f8f9fa';"
                                        onmouseout="this.style.backgroundColor='transparent';">
                                        Profile
                                    </a>
                                </li>
                                <li>
                                    <hr class="dropdown-divider" style="margin: 4px 0;">
                                </li>
                                <li>
                                    <form action="<?php echo rtrim(BASE_URL, '/'); ?>/logout/logout.php" method="post" style="margin:0;">
                                        <button type="submit"
                                            class="dropdown-item text-danger"
                                            style="padding: 8px 16px; font-size: 14px; transition: background-color 0.2s ease; color: #d9534f; font-weight: 800; background:none; border:none; width:100%; text-align:left;"
                                            onmouseover="this.style.backgroundColor='#fbeaea';"
                                            onmouseout="this.style.backgroundColor='transparent';">
                                            Logout
                                        </button>
                                    </form>
                                </li>

                            </ul>
                        </div>


                    </div>
                </div>
            </header>

            <?php if ($canQuickAction): ?>
                <div class="modal fade" id="quickActionModal" tabindex="-1" aria-labelledby="quickActionLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-md">
                        <div class="modal-content shadow-lg rounded-4 border-0">
                            <div class="modal-header border-0 pb-0">
                                <h5 class="modal-title fw-semibold" id="quickActionLabel">
                                    <i class="fa-solid fa-bolt me-2 text-primary"></i> Quick Action
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body p-3">
                                <div class="list-group list-group-flush" id="qaList">
                                    <button type="button" class="list-group-item list-group-item-action quick-action-item" data-type="event">
                                        <i class="fa-solid fa-calendar-plus text-primary"></i>
                                        <div>
                                            <strong>Create Event</strong>
                                            <div class="small text-muted">Organize a new event</div>
                                        </div>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action quick-action-item" data-type="opportunity">
                                        <i class="fa-solid fa-briefcase text-primary"></i>
                                        <div>
                                            <strong>Job Opportunity</strong>
                                            <div class="small text-muted">Post a job offer</div>
                                        </div>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action quick-action-item" data-type="donation">
                                        <i class="fa-solid fa-hand-holding-heart text-primary"></i>
                                        <div>
                                            <strong>Donation</strong>
                                            <div class="small text-muted">Support a cause</div>
                                        </div>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action quick-action-item" data-type="study">
                                        <i class="fa-solid fa-graduation-cap text-primary"></i>
                                        <div>
                                            <strong>Study Session</strong>
                                            <div class="small text-muted">Plan a learning session</div>
                                        </div>
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action quick-action-item" data-type="success">
                                        <i class="fa-solid fa-trophy text-primary"></i>
                                        <div>
                                            <strong>Success History</strong>
                                            <div class="small text-muted">Share an achievement</div>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <div class="modal-footer border-0 pt-0">
                                <button type="button" class="btn cn-btn-outline" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" id="qaGo" class="btn cn-btn-primary" disabled>Continue</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>



            <!-- Main -->
            <main id="main" class="container-fluid px-3 px-lg-4 pb-4 flex-grow-1" tabindex="-1">
                <!-- Welcome -->
                <section class="mb-3" aria-labelledby="welcome-title">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h2 id="welcome-title" class="h5 mb-1">
                                Welcome back, <span style="color: #1a237e; font-weight: 800"><?php echo htmlspecialchars($current['name'] ?? 'Member'); ?></span> 👋
                            </h2>

                            <p class="text-muted mb-0">Here’s what’s happening across your campus network.</p>
                        </div>
                        <div class="text-end d-none d-sm-block">
                            <div class="small text-muted">Member status</div>
                            <div class="badge rounded-pill bg-warning text-dark">Active</div>
                        </div>
                    </div>
                </section>

                <!-- KPIs -->
                <section class="mb-4" aria-labelledby="kpi-title">
                    <h2 id="kpi-title" class="visually-hidden">Key Metrics</h2>
                    <div class="row g-3">
                        <!-- Events KPI -->
                        <div class="col-6 col-xl-3">
                            <article class="card cn-card p-3 h-100">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="stat-pill">
                                        <i class="fa-solid fa-calendar-days"></i> Events
                                    </span>
                                    <i class="fa-solid fa-chevron-right text-muted"></i>
                                </div>
                                <p class="display-6 mt-2 mb-0"><?= (int)$kpiEvents; ?></p>
                                <p class="text-muted small mb-2">Upcoming Six Month Events</p>
                                <div class="progress">
                                    <div class="progress-bar" style="width:60%"></div>
                                </div>
                            </article>
                        </div>




                        <!-- Opportunities KPI -->
                        <div class="col-6 col-xl-3">
                            <article class="card cn-card p-3 h-100">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class=" stat-pill"><i class="fa-solid fa-lightbulb me-2"></i>Opportunities</span>
                                    <i class="fa-solid fa-chevron-right text-muted"></i>
                                </div>
                                <p class="display-6 mt-2 mb-0"><?php echo (int)$kpiOpportunities; ?></p>
                                <p class="text-muted small mb-2">New this week</p>
                                <div class="progress" role="progressbar" aria-label="Opportunities progress"
                                    aria-valuenow="<?php echo min(100, $kpiOpportunities * 15); ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width: <?php echo min(100, $kpiOpportunities * 15); ?>%"></div>
                                </div>
                            </article>
                        </div>

                        <!-- Posts KPI -->
                        <div class="col-6 col-xl-3">
                            <article class="card cn-card p-3 h-100">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="stat-pill"><i class="fa-solid fa-comments me-2"></i>Posts</span>
                                    <i class="fa-solid fa-chevron-right text-muted"></i>
                                </div>
                                <p class="display-6 mt-2 mb-0"><?php echo (int)$kpiPosts; ?></p>
                                <p class="text-muted small mb-2">Since last month</p>
                                <div class="progress" role="progressbar" aria-label="Posts progress"
                                    aria-valuenow="<?php echo min(100, $kpiPosts * 3); ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width: <?php echo min(100, $kpiPosts * 3); ?>%"></div>
                                </div>
                            </article>
                        </div>

                        <!-- Connections KPI -->
                        <div class="col-6 col-xl-3">
                            <article class="card cn-card p-3 h-100">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="stat-pill"><i class="fa-solid fa-users me-2"></i>Connections</span>
                                    <i class="fa-solid fa-chevron-right text-muted"></i>
                                </div>
                                <p class="display-6 mt-2 mb-0"><?php echo (int)$kpiConnections; ?></p>
                                <p class="text-muted small mb-2">Total network</p>
                                <div class="progress" role="progressbar" aria-label="Connections progress"
                                    aria-valuenow="<?php echo min(100, ($kpiConnections / 2)); ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width: <?php echo min(100, ($kpiConnections / 2)); ?>%"></div>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>
                <!-- Events & Jobs -->
                <section class="mb-4">
                    <div class="row g-3">
                        <!-- Upcoming Events -->
                        <div class="col-lg-6">
                            <article class="card cn-card h-100">
                                <div class="card-body">
                                    <header class="d-flex align-items-center justify-content-between mb-2">
                                        <h2 class="section-title mb-0 stat-pill" id="events-title">Upcoming Events</h2>

                                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/events/event.php" class="btn btn-sm btn-outline-light stat-pill">View all</a>
                                    </header>
                                    <ul class="list-group list-group-flush">
                                        <?php if (!empty($events)): foreach ($events as $ev):
                                                $date = !empty($ev['event_date']) ? date('M d', strtotime($ev['event_date'])) : '';
                                                $loc  = $ev['location'] ?? '';
                                                $title = $ev['title'] ?? 'Event';
                                                $id   = (int)($ev['id'] ?? 0);
                                        ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <span class="badge rounded-pill bg-primary-subtle text-primary">
                                                            <i class="fa-solid fa-people-group"></i>
                                                        </span>
                                                        <div>
                                                            <div class="fw-semibold"><?php echo htmlspecialchars($title); ?></div>
                                                            <div class="text-muted small">
                                                                <?php echo htmlspecialchars($date . ($loc ? " · $loc" : "")); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <a class="btn btn-sm cn-btn-primary" href="<?php echo rtrim(BASE_URL, '/'); ?>/event.php?id=<?php echo $id; ?>">Register</a>
                                                </li>
                                            <?php endforeach;
                                        else: ?>
                                            <li class="list-group-item text-muted">No upcoming events.</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </article>
                        </div>

                        <!-- Job Opportunities -->
                        <div class="col-lg-6">
                            <article class="card cn-card h-100">
                                <div class="card-body">
                                    <header class="d-flex align-items-center justify-content-between mb-2">
                                        <h2 class="stat-pill section-title mb-0" id="jobs-title">Job Opportunities</h2>
                                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/jobs.php" class="btn btn-sm btn-outline-light stat-pill">View all</a>
                                    </header>
                                    <ul class="list-group list-group-flush">
                                        <?php if (!empty($jobs)): foreach ($jobs as $jb):
                                                $role = $jb['role'] ?? 'Role';
                                                $company = $jb['company'] ?? 'Company';
                                                $loc = $jb['location'] ?? '';
                                                $meta = trim($company . ($loc ? " · $loc" : ""));
                                                $jid = (int)($jb['id'] ?? 0);
                                                $tags = array_filter(array_map('trim', explode(',', $jb['tags'] ?? '')));
                                        ?>
                                                <li class="list-group-item">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="d-flex align-items-start gap-3">
                                                            <span class="badge rounded-pill bg-success-subtle text-success">
                                                                <i class="fa-solid fa-briefcase"></i>
                                                            </span>
                                                            <div>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($role); ?></div>
                                                                <div class="text-muted small"><?php echo htmlspecialchars($meta); ?></div>
                                                                <?php if ($tags): ?>
                                                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                                                        <?php foreach ($tags as $tg): ?>
                                                                            <span class="badge bg-secondary-subtle text-white-50 border"><?php echo htmlspecialchars($tg); ?></span>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <a class="btn btn-sm cn-btn-primary" href="<?php echo rtrim(BASE_URL, '/'); ?>/job.php?id=<?php echo $jid; ?>">Apply</a>
                                                    </div>
                                                </li>
                                            <?php endforeach;
                                        else: ?>
                                            <li class="list-group-item text-muted">No jobs found.</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>
                <!-- Community & Profile -->
                <?php
                // Ensure channels/profile data exist even if not defined earlier
                if (!isset($channels)) {
                    $channels = qrows($conn, "
                        SELECT id, name, member_count
                        FROM channels
                        ORDER BY member_count DESC
                        LIMIT 3
                    ", "", [], [ /* fallbacks… */]);
                }
                if (!isset($profileCompleteness)) {
                    // You can compute this from your users table fields if you track them.
                    $profileCompleteness = 82;
                }
                ?>
                <section class="mb-4">
                    <div class="row g-3">
                        <!-- Community Channels -->
                        <div class="col-lg-6">
                            <article class="card cn-card  h-100">
                                <div class="card-body">
                                    <header class="d-flex align-items-center justify-content-between mb-2">
                                        <h2 class="stat-pill section-title mb-0" id="community-title">Community Channels</h2>
                                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/community.php" class="btn btn-sm btn-outline-light  stat-pill">Explore</a>
                                    </header>
                                    <p class="mb-3 text-muted">Join channels to connect with peers and alumni.</p>
                                    <ul class="list-group list-group-flush">
                                        <?php if (!empty($channels)): foreach ($channels as $ch):
                                                $cid = (int)($ch['id'] ?? 0);
                                                $cname = $ch['name'] ?? '#channel';
                                                $cmembers = (int)($ch['member_count'] ?? 0);
                                        ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <span class="badge rounded-pill bg-info-subtle text-info">
                                                            <i class="fa-solid fa-hashtag"></i>
                                                        </span>
                                                        <div>
                                                            <div class="fw-semibold"><?php echo htmlspecialchars($cname); ?></div>
                                                            <div class="text-muted small"><?php echo $cmembers; ?> members</div>
                                                        </div>
                                                    </div>
                                                    <a class="btn btn-sm cn-btn-primary" href="<?php echo rtrim(BASE_URL, '/'); ?>/channel.php?id=<?php echo $cid; ?>">Join</a>
                                                </li>
                                            <?php endforeach;
                                        else: ?>
                                            <li class="list-group-item text-muted">No channels available.</li>
                                        <?php endif; ?>
                                    </ul>
                                    <p class="mb-0 text-muted mt-2">Connect with alumni, share experiences, and build relationships.</p>
                                </div>
                            </article>
                        </div>

                        <!-- Profile Health -->
                        <div class="col-lg-6">
                            <article class="card cn-card  h-100">
                                <div class="card-body">
                                    <header class="d-flex align-items-center justify-content-between mb-2">
                                        <h2 class="stat-pill section-title mb-0" id="profile-title">Profile Health</h2>
                                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/profile.php" class="btn btn-sm btn-outline-light  stat-pill">Update profile</a>
                                    </header>

                                    <div class="d-flex align-items-center gap-3">
                                        <div class="progress flex-grow-1" role="progressbar"
                                            aria-label="Profile completeness" aria-valuenow="<?php echo (int)$profileCompleteness; ?>"
                                            aria-valuemin="0" aria-valuemax="100">
                                            <div class="progress-bar" style="width: <?php echo (int)$profileCompleteness; ?>%">
                                                <?php echo (int)$profileCompleteness; ?>%
                                            </div>
                                        </div>
                                        <span class="text-muted small">
                                            <?php echo $profileCompleteness >= 80 ? 'Complete' : 'In progress'; ?>
                                        </span>
                                    </div>

                                    <ul class="list-unstyled mt-3 mb-0 small text-muted">
                                        <li><i class="fa-solid fa-check text-success me-2"></i>Headline & summary</li>
                                        <li><i class="fa-solid fa-check text-success me-2"></i>Education details</li>
                                        <li><i class="fa-solid fa-circle-exclamation text-warning me-2"></i>Add more skills</li>
                                    </ul>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>
            </main>

            <!-- Footer -->
            <footer class="px-3 px-lg-4 py-3 border-top border-opacity-25 small cn-footer">
                © <?php echo date('Y'); ?> Campus Networking
            </footer>

        </div> <!-- /.flex-grow-1 -->
    </div> <!-- /.app -->


    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (() => {
            const base = "<?php echo rtrim(BASE_URL, '/'); ?>"; // keep if you're in PHP
            const routes = {
                event: base + "/events/create_event.php", // ← change if your route is different
                opportunity: base + "/opportunity_create.php",
                donation: base + "/donation_create.php",
                study: base + "/study_session_create.php",
                success: base + "/success_story/success_story.php",
            };

            let selected = null;
            const qaList = document.getElementById('qaList');
            const goBtn = document.getElementById('qaGo');

            if (!qaList || !goBtn) return;

            // Delegate clicks to any .quick-action-item inside the list
            qaList.addEventListener('click', (e) => {
                const btn = e.target.closest('.quick-action-item');
                if (!btn) return;

                // visual state
                qaList.querySelectorAll('.quick-action-item').forEach(b => {
                    b.classList.remove('active');
                    b.setAttribute('aria-pressed', 'false');
                });
                btn.classList.add('active');
                btn.setAttribute('aria-pressed', 'true');

                // selection
                selected = btn.dataset.type;
                goBtn.disabled = !selected;
            });

            // Continue → navigate to the proper create page
            goBtn.addEventListener('click', () => {
                if (!selected) return;
                const url = routes[selected] || (base + "/dashboard/dashboard.php?type=" + encodeURIComponent(selected));
                window.location.href = url;
            });
        })();
    </script>


</body>

</html>