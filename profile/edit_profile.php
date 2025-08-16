<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';



$conn = db();
if (!($conn instanceof mysqli)) {
    die('Database connection not initialized. Check core/db.php → db()');
}

function e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) {
    header('Location: /CAMPUS_NETWORK/sign-in/sign_in.php');
    exit();
}

$viewerRole = current_role();
$targetId = isset($_GET['id']) ? (int)$_GET['id'] : $userId;
if ($viewerRole !== 'admin' && $targetId !== $userId) {
    http_response_code(403);
    exit('Forbidden');
}

// Fetch user data
$sql = "SELECT id, role, first_name, last_name, email, profile_photo, gender,
               department, degree, grad_year, student_id, batch,
               employer, job_title
        FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $targetId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    http_response_code(404);
    exit('User not found.');
}
$user = $result->fetch_assoc();

$errors = [];
$updated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $gender     = $_POST['gender'] ?? null;
    $department = trim($_POST['department'] ?? '');
    $degree     = trim($_POST['degree'] ?? '');
    $grad_year  = trim($_POST['grad_year'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $batch      = trim($_POST['batch'] ?? '');
    $employer   = trim($_POST['employer'] ?? '');
    $job_title  = trim($_POST['job_title'] ?? '');

    $new_role = $user['role'];
    if ($viewerRole === 'admin') {
        $role_in = $_POST['role'] ?? '';
        if (in_array($role_in, ['student', 'alumni', 'admin'], true)) {
            $new_role = $role_in;
        }
    }

    if ($first_name === '') $errors[] = 'First name is required.';
    if ($last_name === '') $errors[]  = 'Last name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

    // Optional avatar upload
    $photoPath = $user['profile_photo']; // keep current unless a new file is uploaded
    if (!empty($_FILES['profile_photo']['name'])) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['profile_photo']['tmp_name']);
        finfo_close($finfo);

        if (!isset($allowed[$mime])) {
            $errors[] = 'Invalid image format.';
        } else {
            $ext = $allowed[$mime];


            $uploadDir = __DIR__ . '/../uploads/avatars';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $filename = $targetId . '_' . time() . '.' . $ext;

            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadDir . '/' . $filename)) {

                $photoPath = 'uploads/avatars/' . $filename;
            } else {
                $errors[] = 'Failed to upload image.';
            }
        }
    }

    if (!$errors) {
        $update = "UPDATE users SET role=?, first_name=?, last_name=?, email=?, gender=?,
                   department=?, degree=?, grad_year=?, student_id=?, batch=?, employer=?, job_title=?, profile_photo=?
                   WHERE id=? LIMIT 1";
        $stmt2 = $conn->prepare($update);
        $stmt2->bind_param(
            'sssssssssssssi',
            $new_role,
            $first_name,
            $last_name,
            $email,
            $gender,
            $department,
            $degree,
            $grad_year,
            $student_id,
            $batch,
            $employer,
            $job_title,
            $photoPath,
            $targetId
        );


        if ($stmt2->execute()) {
            header("Location: /profile/profile.php?id={$targetId}&updated=1"); // ← CHANGED
            exit;
        } else {
            $errors[] = 'Update failed: ' . $conn->error;
        }
    }
}

// role icon mapping so badges look same as profile.php
$roleMap = [
    'student' => ['label' => 'Student', 'class' => 'badge--green',  'icon' => 'fa-user-graduate'],
    'alumni'  => ['label' => 'Alumni',  'class' => 'badge--indigo', 'icon' => 'fa-user-tie'],
    'admin'   => ['label' => 'Admin',   'class' => 'badge--rose',   'icon' => 'fa-shield-halved'],
];
$roleUI = $roleMap[$user['role']] ?? ['label' => 'User', 'class' => 'badge--slate', 'icon' => 'fa-user'];

$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

// ✅ build a public URL for the avatar (same logic as profile.php)
$BASE_URL = '/CAMPUS_NETWORK'; // ← set to your project folder under htdocs

$photoPath = trim($user['profile_photo'] ?? '');
if ($photoPath) {
    // strip any ../ and leading slash, then prepend base
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

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <main class="wrap">
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
            </div>

            <?php if ($errors): ?>
                <div class="error-msg">
                    <?php foreach ($errors as $err) echo '<p>' . e($err) . '</p>'; ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="fields">
                <div class="field"><label>First Name</label><input name="first_name" value="<?php echo e($user['first_name']); ?>"></div>
                <div class="field"><label>Last Name</label><input name="last_name" value="<?php echo e($user['last_name']); ?>"></div>
                <div class="field"><label>Email</label><input type="email" name="email" value="<?php echo e($user['email']); ?>"></div>
                <div class="field">
                    <label for="gender">Gender</label>
                    <div class="select-wrapper">
                        <select id="gender" name="gender" class="styled-select">
                            <option value="">— Select Gender —</option>
                            <option value="male" <?php if ($user['gender'] === 'male')   echo 'selected'; ?>>♂ Male</option>
                            <option value="female" <?php if ($user['gender'] === 'female') echo 'selected'; ?>>♀ Female</option>
                            <option value="other" <?php if ($user['gender'] === 'other')  echo 'selected'; ?>>⚧ Other</option>
                        </select>
                    </div>
                </div>
                <div class="field"><label>Department</label><input name="department" value="<?php echo e($user['department']); ?>"></div>
                <div class="field"><label>Degree</label><input name="degree" value="<?php echo e($user['degree']); ?>"></div>
                <div class="field"><label>Graduation Year</label><input name="grad_year" value="<?php echo e($user['grad_year']); ?>"></div>

                <?php if ($user['role'] === 'student'): ?>
                    <div class="field"><label>Student ID</label><input name="student_id" value="<?php echo e($user['student_id']); ?>"></div>
                    <div class="field"><label>Batch</label><input name="batch" value="<?php echo e($user['batch']); ?>"></div>
                <?php elseif ($user['role'] === 'alumni'): ?>
                    <div class="field"><label>Employer</label><input name="employer" value="<?php echo e($user['employer']); ?>"></div>
                    <div class="field"><label>Job Title</label><input name="job_title" value="<?php echo e($user['job_title']); ?>"></div>
                <?php endif; ?>

                <?php if ($viewerRole === 'admin'): ?>
                    <div class="field"><label>Role</label>
                        <select name="role">
                            <option value="student" <?php if ($user['role'] === 'student') echo 'selected'; ?>>Student</option>
                            <option value="alumni" <?php if ($user['role'] === 'alumni') echo 'selected'; ?>>Alumni</option>
                            <option value="admin" <?php if ($user['role'] === 'admin') echo 'selected'; ?>>Admin</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="field upload-field">
                    <label for="profile_photo" style="font-weight: 600; font-size: 14px; color: #475569;">Profile Photo Update</label>
                    <label for="profile_photo" class="upload-label" id="file-label" style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                        <i class="fa-solid fa-upload"></i>
                        <span id="file-label-text">Choose a file…</span>
                    </label>
                    <input type="file" id="profile_photo" name="profile_photo" accept=".jpg,.jpeg,.png,.webp" class="upload-input" style="display:none;">
                </div>

                <script>
                    document.getElementById('profile_photo').addEventListener('change', function() {
                        const labelText = document.getElementById('file-label-text');
                        if (this.files && this.files.length > 0) {
                            labelText.textContent = this.files[0].name;
                            labelText.style.color = '#16a34a'; // optional: green text for "selected"
                        } else {
                            labelText.textContent = 'Choose a file…';
                            labelText.style.color = '';
                        }
                    });
                </script>

                <div class="actions">
                    <button type="submit" class="btn btn--primary"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                    <a href="/CAMPUS_NETWORK/profile/profile.php?id=<?php echo e($targetId); ?>" class="btn">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</body>

</html>