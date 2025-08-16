<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';

$conn = db();
if (!($conn instanceof mysqli)) {
    die('Database connection not initialized. Check core/db.php → db()');
}

// session user (for "Me/Others")
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = current_role();
$canQuickAction = in_array($role, ['admin', 'alumni'], true);

// Debug (view-source): remove when done
echo "<!-- role='{$role}', canQuickAction=" . ($canQuickAction ? '1' : '0') . " -->";

// helpers
function esc($s)
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function norm_date($s)
{
    $s = trim((string)$s);
    return $s !== '' ? date('Y-m-d', strtotime($s)) : '';
}

// read filters (GET)
$q          = trim($_GET['q'] ?? '');
$author     = trim($_GET['author'] ?? '');
$postedBy   = $_GET['posted_by'] ?? 'all'; // all | me | others
$fromDate   = norm_date($_GET['from'] ?? '');
$toDate     = norm_date($_GET['to'] ?? '');
$hasPhoto   = isset($_GET['has_photo']) ? 1 : 0;
$sort       = ($_GET['sort'] ?? 'new') === 'old' ? 'old' : 'new';

// build WHERE dynamically (prepared)
$conds = [];
$types = '';
$args  = [];

// search in story content
if ($q !== '') {
    $conds[] = 's.content LIKE ?';
    $types  .= 's';
    $args[]  = "%{$q}%";
}

// author filter: match snapshot OR users first+last
if ($author !== '') {
    $conds[] = '(COALESCE(NULLIF(s.author, \'\'), CONCAT(u.first_name, " ", u.last_name)) LIKE ?)';
    $types  .= 's';
    $args[]  = "%{$author}%";
}

// posted by filter
if ($postedBy === 'me' && $userId > 0) {
    $conds[] = 's.user_id = ?';
    $types  .= 'i';
    $args[]  = $userId;
} elseif ($postedBy === 'others' && $userId > 0) {
    $conds[] = '(s.user_id IS NULL OR s.user_id <> ?)';
    $types  .= 'i';
    $args[]  = $userId;
}

// date range (created_at is DATETIME)
if ($fromDate !== '') {
    $conds[] = 'DATE(s.created_at) >= ?';
    $types  .= 's';
    $args[]  = $fromDate;
}
if ($toDate !== '') {
    $conds[] = 'DATE(s.created_at) <= ?';
    $types  .= 's';
    $args[]  = $toDate;
}

// photo-only
if ($hasPhoto) {
    $conds[] = 's.photo IS NOT NULL AND s.photo <> ""';
}

// final SQL
$where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';
$order = ($sort === 'old') ? 'ASC' : 'DESC';

$sql = "
  SELECT
    s.id,
    s.user_id,
    COALESCE(NULLIF(s.author,''), CONCAT(u.first_name, ' ', u.last_name)) AS author,
    s.content,
    s.photo,
    s.created_at,
    u.profile_photo,
    u.gender,  -- ✅ added gender
    u.first_name,
    u.last_name
  FROM success_stories s
  LEFT JOIN users u ON u.id = s.user_id
  $where
  ORDER BY s.created_at $order
";


// run prepared
$stories = [];
if ($stmt = $conn->prepare($sql)) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$args);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $stories = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
    }
    $stmt->close();
} else {
    // fallback (shouldn't happen unless SQL syntax error)
    $result = $conn->query(str_replace('?', '/*?*/', $sql));
    $stories = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    if ($result) $result->free();
}
$BASE_URL = '/CAMPUS_NETWORK'; // or '/campus_network' depending on your actual folder name

function avatar_url(?string $photoPath, ?string $gender = null, string $base = '/CAMPUS_NETWORK'): string
{
    $photoPath = trim((string)$photoPath);

    if ($photoPath !== '') {
        // Remove any ../ but keep forward slash if present
        $photoPath = str_replace('../', '', $photoPath);

        if (strpos($photoPath, '/') === 0) {
            $url = rtrim($base, '/') . $photoPath;
        } else {
            $url = rtrim($base, '/') . '/' . $photoPath;
        }

        // ✅ Send to PHP error log instead of echo
        error_log("DEBUG avatar_url(): {$url}");

        return $url;
    }

    // Fallbacks
    $g = strtolower(trim((string)$gender));
    if ($g === 'male') {
        $url = rtrim($base, '/') . '/assets/img/default-male.png';
    } elseif ($g === 'female') {
        $url = rtrim($base, '/') . '/assets/img/default-female.png';
    } else {
        $url = rtrim($base, '/') . '/assets/img/default-avatar.png';
    }

    error_log("DEBUG avatar_url() fallback: {$url}");

    return $url;
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>All Success Stories</title>

    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="../side_bar.css">
    <link rel="stylesheet" href="success_story.css">
</head>

<body class="stories-page">
    <?php include __DIR__ . '/../side_bar.php'; ?>
    <!-- Top bar -->
    <div class="feed">
        <div class="appbar">
            <div class="bar">
                <div class="brand">
                    <div class="logo" aria-hidden="true">
                        <!-- Star / brand icon (stroke, modern) -->
                        <svg class="icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                            <path d="M12 3l2.7 5.9 6.3.6-4.7 4.1 1.4 6.1L12 16.9 6.3 19.7l1.4-6.1L3 9.5l6.3-.6L12 3z" fill="currentColor" stroke="none" />
                        </svg>
                    </div>
                    <span>All Success Stories</span>
                </div>

                <?php if ($canQuickAction): ?>
                    <a href="success_story.php" class="icon-pill lav" style="text-decoration:none;">
                        <svg class="icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                            <path d="M12 5v14M5 12h14"></path>
                        </svg>
                        New Post
                    </a>
                <?php endif; ?>


            </div>
        </div>

        <div class="wrap">

            <!-- Filters (modern toolbar) -->
            <div class="card">
                <form method="get" class="stories-toolbar" action="success_story_feed.php">

                    <div class="stories-filters">
                        <div>
                            <label for="author">Author name</label>
                            <input class="stories-field" type="text" id="author" name="author" placeholder="e.g. author name">
                        </div>

                        <div>
                            <label for="posted_by">Posted by</label>
                            <select class="stories-field" id="posted_by" name="posted_by">
                                <option value="all">All</option>
                                <option value="me">Me</option>
                                <option value="others">Others</option>
                            </select>
                        </div>

                        <div>
                            <button class="stories-btn" type="submit">
                                <svg class="icon" viewBox="0 0 24 24" width="18" height="18">
                                    <path d="M3 6h18M6 12h12M10 18h4"></path>
                                </svg>
                                Filter
                            </button>
                        </div>
                    </div>



                </form>
            </div>

            <!-- Feed -->
            <div class="card feed">
                <?php if (!$stories): ?>
                    <div class="post" style="color:#6b7280;">No matching posts.</div>
                <?php else: ?>
                    <?php foreach ($stories as $s): ?>
                        <article class="post">
                            <div class="head">
                                <img
                                    class="avatar"
                                    src="<?php echo htmlspecialchars(
                                                avatar_url($s['profile_photo'] ?? '', $s['gender'] ?? '', $BASE_URL),
                                                ENT_QUOTES
                                            ); ?>"
                                    alt="Avatar of <?php echo htmlspecialchars(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''), ENT_QUOTES); ?>">





                                <div class="meta">
                                    <div class="author">
                                        <svg class="icon mint" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" style="margin-right:6px;">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                        <?= esc($s['author']) ?>
                                    </div>
                                    <div class="time">
                                        <svg class="icon muted" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" style="margin-right:6px;">
                                            <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                            <path d="M16 2v4M8 2v4M3 10h18"></path>
                                        </svg>
                                        <?= esc(date('M j, Y • H:i', strtotime($s['created_at']))) ?>
                                    </div>
                                </div>

                                <?php
                                $ownerId = (int)($s['user_id'] ?? 0);
                                $canManage = ($role === 'admin') || ($userId > 0 && $ownerId === $userId);
                                ?>

                                <?php if ($canManage): ?>
                                    <div class="post-actions" style="margin-left:auto; display:flex; gap:6px;">
                                        <a href="edit_story.php?id=<?= (int)$s['id'] ?>" class="stories-btn secondary" style="padding:6px 12px; font-size:0.8rem; text-decoration:none;">
                                            <svg class="icon" viewBox="0 0 24 24">
                                                <path d="M12 20h9" />
                                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" />
                                            </svg>
                                            Edit
                                        </a>
                                        <form method="post" action="delete_story.php" onsubmit="return confirm('Delete this post?');" style="display:inline;">
                                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                            <button type="submit" class="stories-btn" style="background:linear-gradient(135deg,#ef476f,#d61f48); padding:6px 12px; font-size:0.8rem;">
                                                <svg class="icon" viewBox="0 0 24 24">
                                                    <polyline points="3 6 5 6 21 6" />
                                                    <path d="M19 6l-2 14H7L5 6" />
                                                </svg>
                                                Delete
                                            </button>
                                        </form>

                                    </div>
                                <?php endif; ?>


                            </div>

                            <?php if (!empty($s['content'])): ?>
                                <div class="content"><?= esc($s['content']) ?></div>
                            <?php endif; ?>

                            <?php if (!empty($s['photo'])): ?>
                                <img class="photo" src="<?= esc($s['photo']) ?>" alt="">
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>