<?php
session_start();
require_once 'config/db.php';
require_once __DIR__ . '/config/helpers.php';
school_ensure_username($pdo);

$error = '';
$successMessage = (($_GET['principal'] ?? '') === 'updated') ? 'Principal updated. Sign in with the new Principal account.' : '';

// Redirect users who are already logged in
if (isset($_SESSION['user'])) {
    $role = $_SESSION['user']['role'];

    if ($role === 'Principal') {
        header('Location: admin_dashboard.php');
    } elseif ($role === 'Teacher') {
        header('Location: teacher_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }

    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT userID, name, username, email, password, role, status
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'Active') {
                    $error = 'This account is inactive. Contact the school administrator for assistance.';
                } else {
                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'userID' => $user['userID'],
                    'name'   => $user['name'],
                    'username' => $user['username'] ?: $user['name'],
                    'email'  => $user['email'],
                    'role'   => $user['role']
                ];
                $_SESSION['flash_success'] = 'Login successful.';

                // Redirect based on user role
                if (
                    $user['role'] === 'Admin' ||
                    $user['role'] === 'Principal'
                ) {
                    header('Location: admin_dashboard.php');

                } elseif ($user['role'] === 'Teacher') {
                    header('Location: teacher_dashboard.php');

                } elseif ($user['role'] === 'Student') {
                    header('Location: dashboard.php');

                } else {
                    // Unknown role: do not allow access
                    session_unset();
                    session_destroy();

                    header('Location: login.php');
                    exit;
                }

                exit;
                }

            } else {
                $error = 'Invalid email or password.';
            }

        } catch (PDOException $e) {
            $error = 'Unable to process login. Please check your database.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | SchoolSphere</title>

    <link rel="stylesheet" href="assets/style.css?v=3"><link rel="stylesheet" href="assets/ui-polish.css?v=29">
<link rel="stylesheet" href="assets/school-sphere.css?v=18"></head>

<body>

    <main class="auth-page">

        <section class="auth-brand-panel" aria-label="SchoolSphere and SMK Tengku Lela Segara">
            <img src="assets/logo-smk-tls-transparent.png" alt="SMK Tengku Lela Segara logo">
            <span><strong>SchoolSphere</strong><small>SMK Tengku Lela Segara</small></span>
        </section>

        <section class="auth-card">

            <h1>Welcome Back</h1>

            <p>School Event Management System</p>

            <?php if ($successMessage !== ''): ?><div class="auth-success"><?=school_h($successMessage)?></div><?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="error-message">
                    <?= school_h($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">

                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?= school_h(
                        $_POST['email'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    autocomplete="username"
                    required
                >

                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

                <button type="submit" class="auth-button">
                    Login
                </button>

            </form>

            <p class="auth-link">
                Don't have an account?
                <a href="signup.php">Sign Up</a>
            </p>

        </section>

    </main>

</body>
</html>











