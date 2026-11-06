<?php
session_start();
require_once __DIR__.'/config/db.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['user']) || ($_SESSION['user']['role']??'')!=='Teacher') { http_response_code(401); echo '[]'; exit; }
$userId=(int)$_SESSION['user']['userID'];
$eventId=(int)($_GET['eventID']??0);
$owner=$pdo->prepare('SELECT eventID FROM events WHERE eventID=? AND createdBy=?');
$owner->execute([$eventId,$userId]);
if (!$owner->fetchColumn()) { http_response_code(404); echo '[]'; exit; }
$query=$pdo->prepare("SELECT t.taskID,t.taskTitle,u.name AS teacher FROM event_tasks t JOIN users u ON u.userID=t.assignedTo WHERE t.eventID=? AND t.completionPhoto IS NOT NULL AND t.completionPhoto<>'' ORDER BY t.taskTitle,u.name");
$query->execute([$eventId]);
$proofs=[];
foreach($query as $row) $proofs[]=['task'=>$row['taskTitle'],'teacher'=>$row['teacher'],'url'=>'teacher_committee_proof.php?taskID='.(int)$row['taskID']];
echo json_encode($proofs,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);



