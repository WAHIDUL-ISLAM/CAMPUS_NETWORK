<?php

declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../core/db.php'; // your DB config (should define db() and BASE_URL)

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $mysqli = db();
        $mysqli->set_charset('utf8mb4');

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Please enter both email and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Look up user by email
            $stmt = $mysqli->prepare("SELECT id, password_hash FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res->fetch_assoc();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Incorrect email or password.';
            } else {
                // Success: set session and redirect
                if (session_status() === PHP_SESSION_NONE) session_start();
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];

                header('Location: ' . BASE_URL . 'dashboard/dashboard.php');
                exit;
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log('Signin DB error: ' . $e->getMessage());
        $error = 'Database error. Please try again.';
    } catch (Throwable $e) {
        error_log('Signin fatal: ' . $e->getMessage());
        $error = 'Server error. Please try again.';
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Sign in · Campus Network</title>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- App CSS -->
    <link rel="stylesheet" href="sign_in.css" />
</head>

<body>
    <main class="auth d-flex">
        <!-- LEFT / NAVY PANEL -->
        <aside class="auth-visual d-none d-lg-flex">
            <div class="visual-center">
                <div class="logo-badge" aria-hidden="true">
                    <span class="logo-dot"></span>
                    <span class="logo-ring"></span>
                </div>
                <div class="visual-content">
                    <div class="brand">Campus Network</div>
                    <div class="animated-taglines">
                        <span>Learn</span>
                        <span>Grow</span>
                        <span>Connect</span>
                    </div>
                </div>
                <div class="shapes" aria-hidden="true">
                    <span class="shape s1"></span>
                    <span class="shape s2"></span>
                    <span class="shape s3"></span>
                    <span class="diamond"></span>
                    <span class="triangle"></span>
                </div>
            </div>
        </aside>

        <!-- RIGHT / FORM -->
        <section class="auth-form">
            <!-- Login form -->
            <form id="login-form" class="card form-card p-4 p-md-5" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>" method="POST" novalidate>

                <!-- Error Alert -->
                <div id="form-alert" class="alert alert-danger" role="alert" aria-live="polite" <?= $error === '' ? 'hidden' : '' ?>>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <header class="mb-4 text-center">
                    <h2 class="h4 fw-bold mb-1">Welcome back</h2>
                    <p class="text-secondary mb-0">Sign in to your account</p>
                </header>

                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <div class="input-icon">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        <input id="email" name="email" type="email" class="form-control" placeholder="you@school.edu"
                            autocomplete="email" required
                            value="<?= htmlspecialchars($email, ENT_QUOTES) ?>" />
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-icon">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input id="password" name="password" type="password" class="form-control" placeholder="••••••••"
                            autocomplete="current-password" required />
                        <button type="button" class="toggle-eye" data-target="password" aria-label="Show password">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button class="btn w-100 cn-btn-primary mb-3" type="submit">Sign in</button>

                <p class="text-center small mb-0">
                    New here?
                    <a class="link-primary fw-semibold" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES) ?>sign-up/sign_up.php">Create an account</a>
                </p>
            </form>
        </section>
    </main>

    <script>
        // show/hide password (kept, optional)
        document.querySelectorAll('.toggle-eye').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.target);
                const icon = btn.querySelector('i');
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>

</html>