<?php
// profile_setup.php — Full version (MySQLi, post-signup flow)
// Assumes signup set $_SESSION['user_id'].

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php'; // for db() helper if defined here

// 1) Load current user from session
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(400);
    echo '<p>No user session found. Please complete sign up first.</p>';
    exit;
}

$mysqli = db(); // returns mysqli connection

// Fetch user row
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo '<p>User not found.</p>';
    exit;
}

// 2) Skip if already complete
if ((int)($user['profile_complete'] ?? 0) === 1) {
    header('Location: ' . BASE_URL . 'dashboard/dashboard.php');
    exit;
}

// 3) Role-based required fields
function required_fields_for_role($role)
{
    if ($role === 'student') return ['department', 'degree', 'batch'];
    if ($role === 'alumni')  return ['degree', 'employer', 'job_title', 'department'];
    return [];
}

$errors   = [];
$role     = $user['role'] ?? '';
$required = required_fields_for_role($role);

// Helper to prefer POST over DB when re-rendering after validation errors
$val = function (string $key) use ($user) {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : (isset($user[$key]) ? (string)$user[$key] : '');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Common fields
    $first_name = $val('first_name');
    $last_name  = $val('last_name');
    $gender     = $val('gender');

    // Student fields
    $department = $val('department');
    $degree     = $val('degree');
    $batch      = $val('batch');

    // Alumni fields
    $employer   = $val('employer');
    $job_title  = $val('job_title');

    // Validate required fields based on role
    foreach ($required as $field) {
        if ($val($field) === '') {
            $errors[$field] = 'Required';
        }
    }

    // 4) Handle profile photo upload (optional)
    $photoPath = $user['profile_photo'] ?? null;
    if (!empty($_FILES['profile_photo']['name']) && is_uploaded_file($_FILES['profile_photo']['tmp_name'])) {
        $tmp  = $_FILES['profile_photo']['tmp_name'];
        $mime = @mime_content_type($tmp) ?: '';
        $okM  = ['image/jpeg', 'image/png', 'image/webp'];
        $ext  = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $okE  = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($mime, $okM, true) && in_array($ext, $okE, true)) {
            $uploadDir = dirname(__DIR__) . '/uploads';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
            $fname  = 'u' . (int)$user['id'] . '_' . time() . '.' . $ext;
            $destFs = $uploadDir . '/' . $fname;
            if (move_uploaded_file($tmp, $destFs)) {
                $photoPath = '/uploads/' . $fname; // public web path
            } else {
                $errors['profile_photo'] = 'Upload failed';
            }
        } else {
            $errors['profile_photo'] = 'Invalid image type';
        }
    }

    // 5) Persist if no errors
    if (!$errors) {
        $sql = "UPDATE users SET
                    first_name = ?,
                    last_name  = ?,
                    gender     = ?,
                    profile_photo = ?,
                    department = ?,
                    degree     = ?,
                    batch      = ?,
                    employer   = ?,
                    job_title  = ?,
                    profile_complete = 1
                WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $uid = (int)$user['id'];
            $stmt->bind_param(
                'sssssssssi',
                $first_name,
                $last_name,
                $gender,
                $photoPath,
                $department,
                $degree,
                $batch,
                $employer,
                $job_title,
                $uid
            );
            $stmt->execute();
            $stmt->close();
            header('Location: ' . BASE_URL . 'dashboard/dashboard.php');
            exit;
        } else {
            error_log('Prepare failed: ' . $mysqli->error);
            echo '<p>Database error. Please try again later.</p>';
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Complete Your Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="profile_setup.css">

</head>

<body class="container py-4">
    <div class="page-header">
        <h1 class="h4 mb-1">Complete your profile</h1>
        <p class="small-meta mb-0">
            Role: <span class="role-chip"><?= htmlspecialchars($role ?: '—') ?></span>
        </p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">Please fill the required fields.</div>
    <?php endif; ?>

    <div class="ps-card">
        <form method="post" enctype="multipart/form-data" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input name="first_name" class="form-control" value="<?= htmlspecialchars($_POST['first_name'] ?? ($user['first_name'] ?? '')) ?>">
                <?= isset($errors['first_name']) ? '<div class="text-danger small">Required</div>' : '' ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Last Name</label>
                <input name="last_name" class="form-control" value="<?= htmlspecialchars($_POST['last_name'] ?? ($user['last_name'] ?? '')) ?>">
                <?= isset($errors['last_name']) ? '<div class="text-danger small">Required</div>' : '' ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Gender</label>
                <?php $genderValue = $_POST['gender'] ?? ($user['gender'] ?? ''); ?>
                <select name="gender" class="form-select">
                    <option value="">Select</option>
                    <?php foreach (['male', 'female', 'other'] as $g): ?>
                        <option value="<?= $g ?>" <?= $genderValue === $g ? 'selected' : '' ?>><?= ucfirst($g) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= isset($errors['gender']) ? '<div class="text-danger small">Required</div>' : '' ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Profile Photo</label>
                <input type="file" name="profile_photo" class="form-control">
                <?= isset($errors['profile_photo']) ? '<div class="text-danger small">' . htmlspecialchars($errors['profile_photo']) . '</div>' : '' ?>
                <?php if (!empty($user['profile_photo'])): ?>
                    <img src="<?= htmlspecialchars($user['profile_photo']) ?>" alt="Current photo" class="img-thumbnail mt-2" width="120">
                <?php endif; ?>
            </div>

            <?php if ($role === 'student'): ?>
                <div class="col-md-6">
                    <label class="form-label">Department</label>
                    <input name="department" class="form-control" value="<?= htmlspecialchars($_POST['department'] ?? ($user['department'] ?? '')) ?>">
                    <?= isset($errors['department']) ? '<div class="text-danger small">Required</div>' : '' ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Degree</label>
                    <input name="degree" class="form-control" value="<?= htmlspecialchars($_POST['degree'] ?? ($user['degree'] ?? '')) ?>">
                    <?= isset($errors['degree']) ? '<div class="text-danger small">Required</div>' : '' ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Batch</label>
                    <input name="batch" class="form-control" value="<?= htmlspecialchars($_POST['batch'] ?? ($user['batch'] ?? '')) ?>">
                    <?= isset($errors['batch']) ? '<div class="text-danger small">Required</div>' : '' ?>
                </div>
            <?php elseif ($role === 'alumni'): ?>
                <div class="col-md-6">
                    <label class="form-label">Department</label>
                    <input name="department" class="form-control" value="<?= htmlspecialchars($_POST['department'] ?? ($user['department'] ?? '')) ?>">
                    <?= isset($errors['department']) ? '<div class="text-danger small">Required</div>' : '' ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Degree</label>
                    <input name="degree" class="form-control" value="<?= htmlspecialchars($_POST['degree'] ?? ($user['degree'] ?? '')) ?>">
                    <?= isset($errors['degree']) ? '<div class="text-danger small">Required</div>' : '' ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Employer</label>
                    <input name="employer" class="form-control" value="<?= htmlspecialchars($_POST['employer'] ?? ($user['employer'] ?? '')) ?>">
                    <?= isset($errors['employer']) ? '<div class="text-danger small">Required</div>' : '' ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Job Title</label>
                    <input name="job_title" class="form-control" value="<?= htmlspecialchars($_POST['job_title'] ?? ($user['job_title'] ?? '')) ?>">
                    <?= isset($errors['job_title']) ? '<div class="text-danger small">Required</div>' : '' ?>
                </div>
            <?php endif; ?>

            <div class="col-12">
                <button class="btn btn-primary">Save & Continue</button>
            </div>
        </form>
    </div>
</body>

</html>