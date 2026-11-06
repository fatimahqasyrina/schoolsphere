<?php
session_start();
require_once 'config/db.php';
require_once __DIR__ . '/config/helpers.php';
school_ensure_username($pdo);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '' || $username === '' || $email === '' || $password === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
        $error = 'Username must be 3 to 30 characters and use only letters, numbers, dots, underscores or hyphens.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8 || !preg_match('/\d/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Password must be at least 8 characters and include a number and a symbol.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $check = $pdo->prepare(
                "SELECT userID FROM users WHERE email = ? OR username = ?"
            );
            $check->execute([$email, $username]);

            if ($check->fetch()) {
                $error = 'This email address or username is already in use.';
            } else {
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare(
                    "INSERT INTO users (name, username, email, password, role, status)
                     VALUES (?, ?, ?, ?, 'Student', 'Active')"
                );

                $stmt->execute([
                    $name,
                    $username,
                    $email,
                    $hashedPassword
                ]);

                $userID = $pdo->lastInsertId();

                $_SESSION['user'] = [
                    'userID' => $userID,
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'role' => 'Student'
                ];

                $_SESSION['flash_success'] = 'Your student account was created successfully.';
                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Unable to create account. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Sign Up | SchoolSphere</title>
    <link rel="stylesheet" href="assets/style.css?v=3"><link rel="stylesheet" href="assets/ui-polish.css?v=29">
<link rel="stylesheet" href="assets/school-sphere.css?v=18"></head>
<body>
    <main class="auth-page">
        <section class="auth-brand-panel" aria-label="SchoolSphere and SMK Tengku Lela Segara">
            <img src="assets/logo-smk-tls-transparent.png" alt="SMK Tengku Lela Segara logo">
            <span><strong>SchoolSphere</strong><small>SMK Tengku Lela Segara</small></span>
        </section>
        <section class="auth-card signup-card">
            <h1>Create Student Account</h1>
            <p>School Event Management System</p>

            <?php if ($error !== ''): ?>
                <p class="error-message">
                    <?= school_h($error) ?>
                </p>
            <?php endif; ?>

            <form method="POST" action="signup.php">
                <label for="name">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    required
                    value="<?= school_h($_POST['name'] ?? '') ?>"
                >

                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    minlength="3"
                    maxlength="30"
                    pattern="[A-Za-z0-9_.-]{3,30}"
                    title="Use 3 to 30 letters, numbers, dots, underscores or hyphens."
                    required
                    value="<?= school_h($_POST['username'] ?? '') ?>"
                >

                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    value="<?= school_h($_POST['email'] ?? '') ?>"
                >

                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    pattern="(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}"
                    title="Use at least 8 characters, including a number and a symbol."
                    required
                >

                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="8"
                    pattern="(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}"
                    title="Use at least 8 characters, including a number and a symbol."
                    required
                >

                <button type="submit" class="auth-button">
                    Sign Up
                </button>
            </form>

            <p class="auth-link">
                Already have an account?
                <a href="login.php">Login here</a>
            </p>
        </section>
    </main>
</body>
</html>











