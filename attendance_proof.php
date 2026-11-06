<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'Teacher') {
    http_response_code(403);
    exit('Access denied.');
}

$eventID = (int)($_GET['eventID'] ?? 0);
$studentID = trim((string)($_GET['studentID'] ?? ''));
$query = $pdo->prepare("SELECT a.proofFile FROM attendance a JOIN events e ON e.eventID=a.eventID JOIN registrations r ON r.eventID=a.eventID AND r.studentID=a.studentID WHERE a.eventID=? AND a.studentID=? AND e.createdBy=? AND e.status='Approved' AND r.status='Approved' AND a.checkInMethod='Student Photo' AND a.proofFile IS NOT NULL");
$query->execute([$eventID, $studentID, (int)$_SESSION['user']['userID']]);
$storedPath = (string)$query->fetchColumn();
$proofName = basename(str_replace('\\', '/', $storedPath));

if ($proofName === '' || !preg_match('/^[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp)$/i', $proofName)) {
    http_response_code(404);
    exit('Photo proof not found.');
}

$path = __DIR__ . '/uploads/attendance_proofs/' . $proofName;
if (!is_file($path)) {
    http_response_code(404);
    exit('Photo proof not found.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    exit('Unsupported photo type.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="attendance-proof.' . ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
