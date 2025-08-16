<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';

$conn = db();
if (!($conn instanceof mysqli)) {
    http_response_code(500);
    die('Database connection not initialized. Check core/db.php → db()');
}



// who is logged in?
$sessionUserId = (int)($_SESSION['user_id'] ?? 0);
if ($sessionUserId <= 0) {
    header('Location: /CAMPUS_NETWORK/sign-in/sign_in.php');
    exit();
}

// current user role from your auth helper
$role = current_role(); // 'student' | 'alumni' | 'admin'

// Whose profile to show (self by default; only admin can view others)
$viewUserId = isset($_GET['id']) ? (int)$_GET['id'] : $sessionUserId;
if ($role !== 'admin' && $viewUserId !== $sessionUserId) {
    http_response_code(403);
    die('Forbidden: you can only view your own profile.');
}




// tiny esc helper
// Utility
function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function val(array $row, string $key, string $fallback = '—'): string
{
    // returns safe string for display
    $v = $row[$key] ?? null;
    if ($v === null || $v === '') return $fallback;
    return (string)$v;
}

// Pull every column we need (avoid undefined key warnings)
$sql = "SELECT
          id, role, first_name, last_name, email, profile_photo,
          gender, department, degree, grad_year,
          student_id, batch,
          employer, job_title
        FROM users
        WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $viewUserId);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows !== 1) {
    http_response_code(404);
    die('Profile not found.');
}
$user = $res->fetch_assoc();

// Canonical pieces
$role     = ($user['role'] ?? 'student');
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$avatar   = $user['profile_photo'] ?? '';
if ($avatar === '') $avatar = '/assets/img/default-avatar.png';
$isSelf   = ($viewUserId === $sessionUserId);

// Role badge UI
$roleMap = [
    'student' => ['label' => 'Student', 'class' => 'badge--green',  'icon' => 'fa-user-graduate'],
    'alumni'  => ['label' => 'Alumni',  'class' => 'badge--indigo', 'icon' => 'fa-user-tie'],
    'admin'   => ['label' => 'Admin',   'class' => 'badge--rose',   'icon' => 'fa-shield-halved'],
];
$roleUI = $roleMap[$role] ?? ['label' => 'User', 'class' => 'badge--slate', 'icon' => 'fa-user'];
// ---------- CHANGE #3: BUILD A PUBLIC URL FOR AVATAR ----------
$BASE_URL = '/campus_network'; // your project folder at http://localhost/campus_network

$photoPath = trim($user['profile_photo'] ?? '');
if ($photoPath) {
    // Clean any ../ or leading slash, then prepend base
    $photoPath = ltrim(str_replace('../', '', $photoPath), '/');
    $avatar = rtrim($BASE_URL, '/') . '/' . $photoPath;   // e.g. /campus_network/uploads/avatars/xxx.jpg
} else {
    $gender = strtolower(trim($user['gender'] ?? ''));
    if ($gender === 'male') {
        $avatar = rtrim($BASE_URL, '/') . '/assets/img/default-male.png';
    } elseif ($gender === 'female') {
        $avatar = rtrim($BASE_URL, '/') . '/assets/img/default-female.png';
    } else {
        $avatar = rtrim($BASE_URL, '/') . '/assets/img/default-male.png';
    }
}
// ---------- END CHANGE #3 ----------
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?php echo e($fullName ?: 'Profile'); ?> — Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- fonts & icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <!-- styles -->
    <link rel="stylesheet" href="profile.css">
</head>

<body>

    <main class="wrap">
        <!-- Back button -->
        <div class="head-actions" style="margin-left:auto;">
            <a href="<?php echo rtrim(BASE_URL, '/'); ?>/dashboard/dashboard.php" class="btn btn--ghost">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </div>



        <section class="card profile">
            <div class="profile__cover"></div>

            <div class="profile__head">
                <div class="profile_head2" style="display: flex;align-items: center;gap: 25px;">
                    <img class="avatar-xl" src="<?php echo e($avatar); ?>" alt="Avatar of <?php echo e($fullName ?: 'User'); ?>" />
                    <div class="who">
                        <h1 class="name"><?php echo e($fullName ?: 'Unknown User'); ?></h1>
                        <div class="meta">
                            <span class="muted"><i class="fa-solid fa-envelope"></i> <?php echo e($user['email'] ?? ''); ?></span>
                            <span class="badge <?php echo e($roleUI['class']); ?>">
                                <i class="fa-solid <?php echo e($roleUI['icon']); ?>"></i> <?php echo e($roleUI['label']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="head-actions">
                    <?php if ($isSelf): ?>
                        <a href="edit_profile.php" class="btn btn--primary"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- compact read-only fields; every access is safe -->
            <div class="fields">
                <!-- Common fields -->
                <div class="field">
                    <label>Full Name</label>
                    <input type="text" value="<?php echo e($fullName ?: '—'); ?>" disabled>
                </div>
                <div class="field">
                    <label>Gender</label>
                    <input type="text" value="<?php echo e(val($user, 'gender')); ?>" disabled>
                </div>
                <div class="field">
                    <label>Department</label>
                    <input type="text" value="<?php echo e(val($user, 'department')); ?>" disabled>
                </div>
                <div class="field">
                    <label>Degree</label>
                    <input type="text" value="<?php echo e(val($user, 'degree')); ?>" disabled>
                </div>

                <!-- Student-only -->
                <?php if ($role === 'student'): ?>
                    <div class="field">
                        <label>Student ID</label>
                        <input type="text" value="<?php echo e(val($user, 'student_id')); ?>" disabled>
                    </div>
                    <div class="field">
                        <label>Batch</label>
                        <input type="text" value="<?php echo e(val($user, 'batch')); ?>" disabled>
                    </div>
                    <div class="field">
                        <label>Graduation Year</label>
                        <input type="text" value="<?php echo e(val($user, 'grad_year')); ?>" disabled>
                    </div>
                <?php endif; ?>

                <!-- Alumni-only -->
                <?php if ($role === 'alumni'): ?>
                    <div class="field">
                        <label>Employer</label>
                        <input type="text" value="<?php echo e(val($user, 'employer')); ?>" disabled>
                    </div>
                    <div class="field">
                        <label>Job Title</label>
                        <input type="text" value="<?php echo e(val($user, 'job_title')); ?>" disabled>
                    </div>
                    <div class="field">
                        <label>Graduation Year</label>
                        <input type="text" value="<?php echo e(val($user, 'grad_year')); ?>" disabled>
                    </div>
                <?php endif; ?>

                <!-- Admin-only -->
                <?php if ($role === 'admin'): ?>
                    <div class="field">
                        <label>Access</label>
                        <input type="text" value="Full admin permissions" disabled>
                    </div>
                <?php endif; ?>
            </div>

            <div class="email-block">
                <h3>My email address</h3>
                <div class="email-item">
                    <span class="email-icon"><i class="fa-solid fa-envelope"></i></span>
                    <div class="email-meta">
                        <div class="email-text"><?php echo e(val($user, 'email', '')); ?></div>
                    </div>
                </div>
                <?php if ($isSelf): ?>
                    <a class="btn btn--ghost head-actions" style="background: #0c44b7;
                        color: whitesmoke;" href="edit_profile.php#emails"><i class="fa-solid fa-plus"></i> Change Email Address</a>
                <?php endif; ?>
            </div>
        </section>
    </main>

</body>

</html>