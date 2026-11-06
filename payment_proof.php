<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit('Please sign in to view this file.');
}

$user = $_SESSION['user'];
$userID = (int) ($user['userID'] ?? 0);
$role = $user['role'] ?? '';
if (!in_array($role, ['Student', 'Teacher', 'Admin', 'Principal'], true)) {
    http_response_code(403);
    exit('You are not allowed to view this file.');
}

$paymentID = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$paymentID) {
    http_response_code(404);
    exit('File not found.');
}

$sql = 'SELECT proofPath FROM payments WHERE paymentID = ?';
$params = [$paymentID];
if ($role === 'Student') {
    $sql .= ' AND userID = ?';
    $params[] = $userID;
} elseif ($role === 'Teacher') {
    $sql = 'SELECT p.proofPath FROM payments p JOIN events e ON e.eventID=p.eventID WHERE p.paymentID=? AND e.createdBy=?';
    $params[] = $userID;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proofPath = (string)$stmt->fetchColumn();
$proofName = basename(str_replace('\\', '/', $proofPath));
if ($proofName === '' || !preg_match('/^[A-Za-z0-9_-]+\.(jpg|jpeg|png|pdf)$/i', $proofName)) {
    http_response_code(404);
    exit('File not found.');
}

$file = __DIR__ . '/uploads/payment_proofs/' . $proofName;
if (!is_file($file)) {
    http_response_code(404);
    exit('File not found.');
}
$extension = strtolower(pathinfo($proofName, PATHINFO_EXTENSION));
$mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'][$extension] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($file));
header('Content-Disposition: inline; filename="payment-proof-' . (int) $paymentID . '.' . $extension . '"');
header('X-Content-Type-Options: nosniff');
readfile($file);
