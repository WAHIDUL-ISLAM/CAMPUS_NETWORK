<?php
// signup.php — Full working version (MySQLi)
// - Starts session
// - Validates input
// - Checks duplicate email
// - Inserts user
// - Sets $_SESSION['user_id']
// - Redirects to /sign-up/profile_setup.php

declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../core/db.php'; // provides db(): mysqli

// Optional BASE_URL fallback
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

$error = '';
$formData = [
    'firstName' => '',
    'lastName'  => '',
    'email'     => '',
    'password'  => '',
    'confirm'   => '',
    'role'      => 'student',
    'gender'    => null,
    'studentId' => '',
    'gradYear'  => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $mysqli = db();
        $mysqli->set_charset('utf8mb4');

        // Collect POST into $formData
        $formData = [
            'firstName' => trim($_POST['firstName'] ?? ''),
            'lastName'  => trim($_POST['lastName'] ?? ''),
            'email'     => trim($_POST['email'] ?? ''),
            'password'  => $_POST['password'] ?? '',
            'confirm'   => $_POST['confirm'] ?? '',
            'role'      => in_array($_POST['role'] ?? '', ['student', 'alumni'], true) ? $_POST['role'] : 'student',
            // Allow 'other' to match profile setup
            'gender'    => in_array($_POST['gender'] ?? null, ['male', 'female', 'other'], true) ? $_POST['gender'] : null,
            'studentId' => trim($_POST['studentId'] ?? ''),
            'gradYear'  => trim($_POST['gradYear'] ?? '')
        ];

        // Server-side validation
        if ($formData['firstName'] === '' || $formData['lastName'] === '' || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please fill the form correctly.';
        } elseif (strlen($formData['password']) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($formData['password'] !== $formData['confirm']) {
            $error = 'Passwords do not match.';
        } else {
            // Check if email already exists
            $check = $mysqli->prepare('SELECT id FROM users WHERE email = ?');
            $check->bind_param('s', $formData['email']);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $error = 'Email already exists. Please sign in.';
            }
            $check->close();

            if ($error === '') {
                $hash = password_hash($formData['password'], PASSWORD_DEFAULT);
                $stmt = $mysqli->prepare(
                    'INSERT INTO users
                     (first_name, last_name, email, password_hash, role, gender, student_id, grad_year, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->bind_param(
                    'ssssssss',
                    $formData['firstName'],
                    $formData['lastName'],
                    $formData['email'],
                    $hash,
                    $formData['role'],
                    $formData['gender'],
                    $formData['studentId'],
                    $formData['gradYear']
                );
                $stmt->execute();
                $newUserId = $stmt->insert_id ?: $mysqli->insert_id;
                $stmt->close();

                // Create login session for profile setup
                $_SESSION['user_id'] = (int)$newUserId;

                header('Location: ' . BASE_URL . 'sign-up/profile_setup.php');
                exit;
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log('Signup error: ' . $e->getMessage());
        $error = 'Database error. Please try again.';
    } catch (Throwable $e) {
        error_log('Signup fatal: ' . $e->getMessage());
        $error = 'Server error. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Create Account · Campus Network</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="sign_up.css" />
</head>

<body>
    <main class="auth">
        <aside class="auth-visual">
            <div class="visual-gradient"></div>
            <div class="visual-shapes">
                <span class="shape s1"></span>
                <span class="shape s2"></span>
                <span class="shape s3"></span>
                <span class="diamond"></span>
                <span class="triangle"></span>
            </div>
            <div class="visual-content">
                <h1 class="brand"><i class="fa-solid fa-satellite-dish me-2"></i>Campus Networking</h1>
                <p id="dynamic-text" class="lead">Start your journey now with us</p>
            </div>
        </aside>

        <section class="auth-form">
            <div class="card form-card">
                <header class="mb-3 text-center">
                    <h2 class="h4 fw-bold mb-1">Create an Account</h2>
                    <p class="text-secondary mb-0">Join the network—connect, learn, and grow.</p>
                </header>

                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>" method="POST" novalidate>
                    <div id="signup-alert" class="alert alert-danger" role="alert" aria-live="polite" <?= $error === '' ? 'hidden' : '' ?>>
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="firstName" class="form-label">First name</label>
                            <div class="input-icon">
                                <i class="fa-solid fa-user" aria-hidden="true"></i>
                                <input id="firstName" name="firstName" type="text" class="form-control"
                                    placeholder="First name" required value="<?= htmlspecialchars($formData['firstName'] ?? '', ENT_QUOTES) ?>" />
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label for="lastName" class="form-label">Last name</label>
                            <div class="input-icon">
                                <i class="fa-solid fa-user" aria-hidden="true"></i>
                                <input id="lastName" name="lastName" type="text" class="form-control"
                                    placeholder="Last name" required value="<?= htmlspecialchars($formData['lastName'] ?? '', ENT_QUOTES) ?>" />
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-icon">
                                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                <input id="email" name="email" type="email" class="form-control"
                                    placeholder="you@school.edu" required value="<?= htmlspecialchars($formData['email'] ?? '', ENT_QUOTES) ?>" />
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-icon">
                                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                <input id="password" name="password" type="password" class="form-control"
                                    placeholder="••••••••" minlength="8" required />
                                <button type="button" class="toggle-eye" aria-label="Show password" data-target="password">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">8+ characters</div>
                        </div>

                        <div class="col-sm-6">
                            <label for="confirm" class="form-label">Confirm password</label>
                            <div class="input-icon">
                                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                <input id="confirm" name="confirm" type="password" class="form-control"
                                    placeholder="••••••••" required />
                                <button type="button" class="toggle-eye" aria-label="Show password" data-target="confirm">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label for="studentId" class="form-label">Student ID</label>
                            <div class="input-icon">
                                <i class="fa-regular fa-id-card" aria-hidden="true"></i>
                                <input id="studentId" name="studentId" type="text" class="form-control"
                                    placeholder="e.g., 20251234" value="<?= htmlspecialchars($formData['studentId'] ?? '', ENT_QUOTES) ?>" />
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label for="gradYear" class="form-label">Graduation Year / Batch</label>
                            <div class="input-icon">
                                <i class="fa-solid fa-calendar" aria-hidden="true"></i>
                                <input id="gradYear" name="gradYear" type="text" class="form-control"
                                    placeholder="e.g., 2027" pattern="\d{4}" value="<?= htmlspecialchars($formData['gradYear'] ?? '', ENT_QUOTES) ?>" />
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <fieldset class="fieldset">
                                        <legend class="form-label mb-2">Gender</legend>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="gender" id="male" value="male"
                                                <?= ($formData['gender'] === 'male') ? 'checked' : '' ?> required />
                                            <label class="form-check-label" for="male">Male</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="gender" id="female" value="female"
                                                <?= ($formData['gender'] === 'female') ? 'checked' : '' ?> />
                                            <label class="form-check-label" for="female">Female</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="gender" id="other" value="other"
                                                <?= ($formData['gender'] === 'other') ? 'checked' : '' ?> />
                                            <label class="form-check-label" for="other">Other</label>
                                        </div>
                                    </fieldset>
                                </div>

                                <div class="col-sm-6">
                                    <fieldset class="fieldset role-highlight">
                                        <legend class="form-label mb-2">Role</legend>

                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="role" id="rStudent" value="student"
                                                <?= (isset($formData['role']) && $formData['role'] === 'student') ? 'checked' : '' ?> required>
                                            <label class="form-check-label" for="rStudent">Student</label>
                                        </div>

                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="role" id="rAlumni" value="alumni"
                                                <?= (isset($formData['role']) && $formData['role'] === 'alumni') ? 'checked' : '' ?> required>
                                            <label class="form-check-label" for="rAlumni">Alumni</label>
                                        </div>
                                    </fieldset>
                                </div>


                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" required />
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" class="link-primary">Terms &amp; Conditions</a>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button id="signup-submit" type="submit" class="btn w-100 cn-btn-primary mt-3">Create Account</button>

                    <p class="text-center small mt-3 mb-0 ">
                        Already have an account?
                        <a class="link-primary fw-semibold" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES) ?>sign-in/sign_in.php">Log in</a>
                    </p>
                </form>
            </div>
        </section>
    </main>

    <script>
        const messages = [
            "Empower your future with knowledge",
            "Learning never stops",
            "Innovation starts here"
        ];
        let i = 0;
        setInterval(() => {
            const el = document.getElementById("dynamic-text");
            if (el) el.textContent = messages[i];
            i = (i + 1) % messages.length;
        }, 3000);

        document.querySelectorAll('.toggle-eye').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.target);
                const icon = btn.querySelector('i');
                const isPwd = input.type === 'password';
                input.type = isPwd ? 'text' : 'password';
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>

</html>