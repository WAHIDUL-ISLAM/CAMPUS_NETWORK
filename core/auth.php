<?php
// core/auth.php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/db.php'; // brings in db() -> mysqli

/** Map anything (strings/ints) to canonical role strings */
function normalize_role($val): string
{
    if ($val === null) return '';
    $s = strtolower(trim((string)$val));
    // fix common typo
    if ($s === 'alamni') $s = 'alumni';
    // numeric mapping (adjust if your schema differs)
    $map = [
        '1' => 'admin',
        '2' => 'alumni',
        '3' => 'student',
        1   => 'admin',
        2  => 'alumni',
        3  => 'student',
    ];
    if (array_key_exists($s, $map)) return $map[$s];
    if (array_key_exists($val, $map)) return $map[$val];
    // allow only known roles
    if (in_array($s, ['admin', 'alumni', 'student'], true)) return $s;
    return ''; // unknown/empty
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int)($user['id'] ?? 0);
    $_SESSION['email']      = $user['email'] ?? '';
    $_SESSION['first_name'] = $user['first_name'] ?? '';
    $_SESSION['last_name']  = $user['last_name'] ?? '';
    // normalize whatever is in $user['role'] (string or numeric); default to student
    $role = normalize_role($user['role'] ?? 'student');
    $_SESSION['role'] = $role;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) return null;
    $mysqli = db();
    $stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

/** Always use this to read the current normalized role */
function current_role(): string
{
    // prefer session for speed
    $sessRole = $_SESSION['role'] ?? '';
    if ($sessRole !== '') return normalize_role($sessRole);

    // fallback to DB (when session role is missing)
    $u = current_user();
    if ($u && isset($u['role'])) return normalize_role($u['role']);

    return '';
}

function require_login(string $redirect = '/login.php'): void
{
    if (!is_logged_in()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function require_profile_complete(string $redirect = '/profile_setup.php'): void
{
    require_login();
    $u = current_user();
    if (!$u) {
        header('Location: /login.php');
        exit;
    }
    if (empty($u['profile_complete'])) {
        header('Location: ' . $redirect);
        exit;
    }
}

function is_admin(): bool
{
    return (current_role() === 'admin');
}

/** Gate pages by roles */
function require_role(array $roles, string $redirect = '/'): void
{
    require_login();
    $role = current_role();
    // normalize expected roles too, in case you pass 'Admin' from somewhere
    $roles = array_map('normalize_role', $roles);
    if (!in_array($role, $roles, true)) {
        header('Location: ' . $redirect);
        exit;
    }
}
