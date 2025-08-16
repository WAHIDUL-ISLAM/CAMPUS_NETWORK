<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';

// DB connection
$conn = db();
if (!($conn instanceof mysqli)) {
    die('Database connection not initialized. Check core/db.php → db()');
}


$userId   = (int)($_SESSION['user_id'] ?? 0);
$userName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));

if ($userName === '') {
    // fallback to DB if session name missing
    if ($userId > 0) {
        $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        $userName = trim($row['name'] ?? '');
    }
}


// Helper
function esc($s)
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Handle new post
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');
    $photoRel = null;

    if ($content === '' && empty($_FILES['photo']['name'])) {
        $errors[] = 'Write something or upload an image.';
    }

    // Handle upload
    if (!$errors && !empty($_FILES['photo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            $errors[] = 'Invalid file type.';
        } elseif ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed.';
        } else {
            $dir = __DIR__ . '/uploads';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $fileName = 'story_' . uniqid() . '.' . $ext;
            $dest = $dir . '/' . $fileName;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                $photoRel = 'uploads/' . $fileName;
            } else {
                $errors[] = 'Error saving the file.';
            }
        }
    }

    // Insert into DB
    if (!$errors) {
        $stmt = $conn->prepare("INSERT INTO success_stories (user_id, author, content, photo) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $userId, $userName, $content, $photoRel);
        $stmt->execute();
        $stmt->close();
        header("Location: success_story_feed.php");
        exit;
    }
}

// Fetch feed
$result = $conn->query("
    SELECT s.id, s.author, s.content, s.photo, s.created_at, u.profile_photo
    FROM success_stories s
    LEFT JOIN users u ON u.id = s.user_id
    ORDER BY s.created_at DESC
");
$stories = $result->fetch_all(MYSQLI_ASSOC);
$result->free();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Success Stories</title>
    <link rel="stylesheet" href="success_story.css">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
</head>

<body>

    <!-- Frosted top bar -->
    <div class="appbar">
        <div class="bar">
            <div class="brand">
                <div class="logo" aria-hidden="true">
                    <!-- simple star icon -->
                    <svg class="icon" viewBox="0 0 24 24" width="18" height="18">
                        <path d="M12 3l2.7 5.9 6.3.6-4.7 4.1 1.4 6.1L12 16.9 6.3 19.7l1.4-6.1L3 9.5l6.3-.6L12 3z" />
                    </svg>
                </div>
                <span>Success Stories</span>
            </div>
            <div class="head-actions" style="margin-left:auto;">
                <a href="success_story_feed.php"
                    class="btn btn--ghost"
                    style="display:flex; align-items:center; gap:6px; padding:4px 10px; font-size:14px; border-radius:6px; text-decoration:none; transition:background-color 0.2s ease;"
                    onmouseover="this.style.backgroundColor='#f5f5f5';"
                    onmouseout="this.style.backgroundColor='transparent';">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
            </div>

        </div>
    </div>

    <div class="wrap">
        <!-- Composer -->
        <div class="card composer">
            <h2 style="margin:0 0 10px;">Share your success ✨</h2>

            <?php if ($errors): ?>
                <div class="alert err">
                    <?php foreach ($errors as $er): ?>
                        <div><?= esc($er) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <textarea name="content" placeholder="What's your story?" maxlength="5000"></textarea>

                <div class="row">
                    <div class="left-group" style="display:flex;align-items:center;gap:10px;">
                        <label class="icon-pill" for="photo">
                            <svg class="icon lav" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                                <path d="M4 7h3l2-2h6l2 2h3v12H4zM12 9a4 4 0 100 8 4 4 0 000-8z" fill="currentColor" />
                            </svg>
                            Add photo
                        </label>
                        <input id="photo" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" hidden>

                        <span id="fileMsg" style="font-size:1rem;color:#10b981;display:none;">
                            ✅ File selected
                        </span>
                    </div>

                    <button class="btn" type="submit" title="Post">
                        <svg class="icon amber" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                            <path d="M3 12l7 7L21 5" color='red' fill="white" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                        &nbsp;Post
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
        document.getElementById('photo').addEventListener('change', function() {
            const msg = document.getElementById('fileMsg');
            if (this.files && this.files.length > 0) {
                msg.style.display = 'inline';
            } else {
                msg.style.display = 'none';
            }
        });
    </script>
</body>

</html>