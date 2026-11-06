<?php
require_once 'config/db.php';

$name = 'SEMS Principal';
$email = 'admin@sems.test';
$password = 'AdminSEMS123!';
$role = 'Principal';
$status = 'Active';

try {
    $check = $pdo->prepare(
        "SELECT userID FROM users WHERE email = ? LIMIT 1"
    );
    $check->execute([$email]);

    if ($check->fetch()) {
        exit('Account already exists. No changes made.');
    }

    $existingPrincipal = $pdo->query("SELECT userID FROM users WHERE role = 'Principal' AND status = 'Active' LIMIT 1")->fetchColumn();
    if ($existingPrincipal) {
        exit('A Principal account already exists. No changes made.');
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password, role, status)
         VALUES (?, ?, ?, ?, ?)"
    );

    $stmt->execute([
        $name,
        $email,
        $hashedPassword,
        $role,
        $status
    ]);

    $principalUserID = (int) $pdo->lastInsertId();
    $next = (int) $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(adminID,4) AS UNSIGNED)),0)+1 FROM principals WHERE adminID LIKE 'PRN%'")->fetchColumn();
    $principalID = 'PRN' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    $profile = $pdo->prepare('INSERT INTO principals (adminID,userID,principalName,startDate,endDate) VALUES (?,?,?,CURDATE(),NULL)');
    $profile->execute([$principalID, $principalUserID, $name]);

    echo 'Principal account created successfully.<br>';
    echo 'Email: admin@sems.test<br>';
    echo 'IMPORTANT: Delete create_admin.php after this setup.';

} catch (PDOException $e) {
    exit('Unable to create admin account. Check the users table structure.');
}
