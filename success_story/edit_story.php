<?php
// success_story/edit_story.php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function esc($s)
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function bad($msg = 'Bad request', $code = 400)
{
    http_response_code($code);
    echo $msg;
    exit;
}

$conn = db();
if (!($conn instanceof mysqli)) bad('DB not ready', 500);

$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = current_role();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) bad('Invalid id');

$story = null;
$stmt = $conn->prepare("SELECT id, user_id, content, photo FROM success_stories WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$story = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$story) bad('Not found', 404);

// only admin or owner can edit
if (!($role === 'admin' || ($userId > 0 && (int)$story['user_id'] === $userId))) {
    bad('Forbidden', 403);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim((string)($_POST['content'] ?? ''));
    $photo   = trim((string)($_POST['photo'] ?? ''));

    if ($content === '') $errors[] = 'Content is required.';
    if (mb_strlen($content) > 5000) $errors[] = 'Content is too long.';

    if (!$errors) {
        $stmt = $conn->prepare("UPDATE success_stories SET content = ?, photo = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
        $stmt->bind_param("ssi", $content, $photo, $id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            header("Location: success_story_feed.php");
            exit;
        } else {
            $errors[] = 'Failed to save changes.';
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Edit Story></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="success_story.css">
</head>

<body class="stories-page" style="margin:0; background:#EEEEEE; font-family:Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; color:#0f172a;">
    <div class="wrap" style="max-width:760px; width:100%; margin:28px auto; padding:0 16px;">
        <div class="card" style="background:linear-gradient(180deg,#fff,#fafbff); border:1px solid rgba(17,24,39,.06); border-radius:18px; box-shadow:0 2px 12px rgba(2,8,23,.06),0 10px 24px rgba(2,8,23,.06); padding:24px;">
            <h2 style="margin:0 0 14px; font-size:1.25rem; font-weight:700; letter-spacing:.2px; color:#1e293b;">✏️ Edit Success Story</h2>

            <?php if ($errors): ?>
                <div class="alert err" style="background:#ffeef0; color:#8f1d2c; border:1px solid #ffd6dc; padding:10px 12px; border-radius:10px; margin-bottom:12px;">
                    <?= esc(implode(' ', $errors)) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="edit_story.php">
                <input type="hidden" name="id" value="<?= (int)$story['id'] ?>">

                <label class="stories-label" for="content" style="display:block; font-size:12px; color:#6b7280; margin-left:4px; margin-bottom:6px;">Content</label>
                <textarea
                    id="content"
                    name="content"
                    class="stories-field"
                    style="
                        width:100%;
                        min-height:160px;
                        border:1px solid #e5e7eb;
                        background:linear-gradient(180deg,#fff,#fbfbff);
                        color:#0f172a;
                        padding:12px 14px;
                        border-radius:12px;
                        outline:none;
                        resize:vertical;
                        font-size:.98rem;
                        line-height:1.5;
                        transition:box-shadow .18s, border-color .18s;
                        box-shadow: inset 0 -1px 0 rgba(2,8,23,.04);
                        "
                    onfocus="this.style.borderColor='#2563eb'; this.style.boxShadow='0 0 0 4px rgba(37,99,235,.18)';"
                    onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='inset 0 -1px 0 rgba(2,8,23,.04)';"><?= esc($story['content']) ?></textarea>

                <div class="row" style="display:flex; gap:12px; align-items:flex-start; justify-content:space-between; margin-top:12px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:260px;">
                        <label class="stories-label" for="photo" style="display:block; font-size:12px; color:#6b7280; margin-left:4px; margin-bottom:6px;">Photo URL (optional)</label>
                        <input
                            id="photo"
                            name="photo"
                            class="stories-field"
                            style="
                                width:100%;
                                border:1px solid #e5e7eb;
                                background:linear-gradient(180deg,#fff,#fbfbff);
                                color:#0f172a;
                                padding:10px 12px;
                                border-radius:12px;
                                outline:none;
                                font-size:.95rem;
                                transition:box-shadow .18s, border-color .18s;
                                box-shadow: inset 0 -1px 0 rgba(2,8,23,.04);
                            "
                            onfocus="this.style.borderColor='#2563eb'; this.style.boxShadow='0 0 0 4px rgba(37,99,235,.18)';"
                            onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='inset 0 -1px 0 rgba(2,8,23,.04)';"
                            value="<?= esc($story['photo']) ?>"
                            placeholder="https://…">
                    </div>
                </div>

                <div class="row" style="display:flex; gap:10px; align-items:center; justify-content:flex-end; margin-top:18px; flex-wrap:wrap;">
                    <a
                        href="success_story_feed.php"
                        class="btn secondary"
                        style="
                            display:inline-flex; align-items:center; justify-content:center; gap:8px;
                            padding:10px 16px; border-radius:999px; text-decoration:none;
                            background:#fff; color:#0f172a; font-weight:600; border:1px solid #e5e7eb;
                            box-shadow:0 1px 8px rgba(0,0,0,.06);
                        ">Cancel</a>

                    <button
                        type="submit"
                        class="btn"
                        style="
                            display:inline-flex; align-items:center; justify-content:center; gap:8px;
                            padding:10px 16px; border-radius:999px; border:0; cursor:pointer;
                            background:linear-gradient(90deg,#6366f1,#8b5cf6); color:#fff; font-weight:700;
                            box-shadow:0 10px 24px rgba(99,102,241,.25);
                            transition:filter .2s, transform .12s;
                        "
                        onmouseover="this.style.filter='brightness(1.06)'; this.style.transform='translateY(-1px)';"
                        onmouseout="this.style.filter='none'; this.style.transform='none';"
                        onmousedown="this.style.transform='translateY(1px) scale(.99)';"
                        onmouseup="this.style.transform='translateY(-1px)';">Save Changes</button>
                </div>
            </form>
        </div>

        <?php if (!empty($story['photo'])): ?>
            <div class="card" style="background:#fff; border-radius:18px; padding:16px; box-shadow:0 2px 12px rgba(2,8,23,.06),0 10px 24px rgba(2,8,23,.06); margin-top:16px; border:1px solid rgba(17,24,39,.06);">
                <div class="stories-label" style="font-size:12px; color:#6b7280; margin-left:4px; margin-bottom:8px;">Preview</div>
                <img class="photo" src="<?= esc($story['photo']) ?>" alt="" style="max-width:100%; border-radius:14px; border:1px solid rgba(17,24,39,.06); box-shadow:0 8px 24px rgba(0,0,0,.08);">
            </div>
        <?php endif; ?>
    </div>
</body>

</html>