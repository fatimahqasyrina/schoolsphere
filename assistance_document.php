<?php
session_start();
require_once __DIR__ . '/config/db.php';
if (empty($_SESSION['user']['userID'])) { http_response_code(401); exit('Sign in required.'); }
$userId=(int)$_SESSION['user']['userID'];
$role=(string)($_SESSION['user']['role']??'');
$id=(int)($_GET['id']??0);
$query=$pdo->prepare('SELECT userID,supportDocument FROM financial_assistance WHERE assistanceID=?');
$query->execute([$id]);
$record=$query->fetch(PDO::FETCH_ASSOC);
if (!$record || ((int)$record['userID']!==$userId && !in_array($role,['Admin','Principal'],true))) { http_response_code(404); exit('Document not found.'); }
$relative=(string)($record['supportDocument']??'');
if (!preg_match('#^uploads/assistance_documents/[a-f0-9]{48}\.(pdf|jpg|png)$#i',$relative)) { http_response_code(404); exit('Document not found.'); }
$path=__DIR__.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
if (!is_file($path)) { http_response_code(404); exit('Document not found.'); }
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime,['application/pdf','image/jpeg','image/png'],true)) { http_response_code(404); exit('Document not found.'); }
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
$extension=$mime==='application/pdf'?'pdf':($mime==='image/jpeg'?'jpg':'png');
header('Content-Disposition: inline; filename="assistance-document.'.$extension.'"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
