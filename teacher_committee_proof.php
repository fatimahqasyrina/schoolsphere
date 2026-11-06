<?php
session_start();
require_once __DIR__.'/config/db.php';
if (!isset($_SESSION['user']) || ($_SESSION['user']['role']??'')!=='Teacher') { http_response_code(401); exit('Sign in required.'); }
$userId=(int)$_SESSION['user']['userID'];
$taskId=(int)($_GET['taskID']??0);
$query=$pdo->prepare('SELECT t.assignedTo,t.completionPhoto,e.createdBy FROM event_tasks t JOIN events e ON e.eventID=t.eventID WHERE t.taskID=?');
$query->execute([$taskId]);
$task=$query->fetch(PDO::FETCH_ASSOC);
if (!$task || ((int)$task['assignedTo']!==$userId && (int)$task['createdBy']!==$userId)) { http_response_code(404); exit('Proof not found.'); }
$relative=(string)($task['completionPhoto']??'');
if (!preg_match('#^uploads/committee_task_proofs/[a-f0-9]{48}\.(jpg|png|webp)$#i',$relative)) { http_response_code(404); exit('Proof not found.'); }
$path=__DIR__.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
if (!is_file($path)) { http_response_code(404); exit('Proof not found.'); }
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime,['image/jpeg','image/png','image/webp'],true)) { http_response_code(404); exit('Proof not found.'); }
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header('Content-Disposition: inline; filename="committee-task-proof"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);



