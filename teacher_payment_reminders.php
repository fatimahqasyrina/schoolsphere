<?php
session_start();require_once __DIR__.'/config/db.php';
require_once __DIR__ . '/config/helpers.php';
if(!isset($_SESSION['user'])||($_SESSION['user']['role']??'')!=='Teacher'){header('Location: login.php');exit;}
$user=$_SESSION['user'];$uid=(int)$user['userID'];if(empty($_SESSION['sems_csrf']))$_SESSION['sems_csrf']=bin2hex(random_bytes(32));$csrf=$_SESSION['sems_csrf'];$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{if(!hash_equals($csrf,(string)($_POST['csrf']??'')))throw new RuntimeException('Session expired. Refresh and try again.');$eventID=(int)($_POST['eventID']??0);$eventQ=$pdo->prepare("SELECT eventName FROM events WHERE eventID=? AND createdBy=? AND status='Approved' AND fee>0");$eventQ->execute([$eventID,$uid]);$eventName=$eventQ->fetchColumn();if(!$eventName)throw new RuntimeException('Choose one of your approved paid events.');
 $targets=$pdo->prepare("SELECT DISTINCT u.userID FROM registrations r JOIN students s ON s.studentID=r.studentID JOIN users u ON u.userID=s.userID LEFT JOIN payments p ON p.userID=u.userID AND p.eventID=r.eventID AND p.status IN ('Pending','Verified') LEFT JOIN financial_assistance fa ON fa.userID=u.userID AND fa.eventID=r.eventID AND fa.status IN ('Pending','Approved') WHERE r.eventID=? AND r.status='Approved' AND p.paymentID IS NULL AND fa.assistanceID IS NULL");$targets->execute([$eventID]);$students=$targets->fetchAll(PDO::FETCH_COLUMN);$notice=$pdo->prepare('INSERT INTO notifications (userID,title,message,link) VALUES (?,?,?,?)');foreach($students as $studentID)$notice->execute([(int)$studentID,'Payment reminder','Please complete payment for '.$eventName.'. Open My Payments to choose a method or pay later.','my_payments.php']);$message=count($students).' student(s) reminded for '.$eventName.'.';
 }catch(RuntimeException $e){$error=$e->getMessage();}catch(Throwable $e){$error='Could not send payment reminders.';}
}
$q=$pdo->prepare("SELECT eventID,eventName,eventDate FROM events WHERE createdBy=? AND status='Approved' AND fee>0 ORDER BY eventDate DESC");$q->execute([$uid]);$events=$q->fetchAll();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment reminders | SchoolSphere</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/teacher.css?v=5"><link rel="stylesheet" href="assets/ui-polish.css?v=28"><link rel="stylesheet" href="assets/portal.css?v=12"><link rel="stylesheet" href="assets/school-sphere.css?v=18"></head><body class="teacher-dashboard"><div class="teacher-layout"><?php require __DIR__.'/teacher_nav.php';?><main class="teacher-main"><header class="teacher-topbar"><div><span class="teacher-eyebrow">EVENT FINANCE</span><h1>Payment reminders</h1></div></header><div class="teacher-content"><?php if($message):?><div class="portal-notice success"><?=school_h($message)?></div><?php endif;?><?php if($error):?><div class="portal-notice error"><?=school_h($error)?></div><?php endif;?><section class="teacher-panel"><h2>Remind students for one event</h2><p>Everyone with an approved registration and no payment or assistance request will receive a reminder.</p><?php if(!$events):?><p>No approved paid events to remind students about.</p><?php else:?><form method="post" class="teacher-form" style="grid-template-columns:minmax(240px,1fr) auto"><input type="hidden" name="csrf" value="<?=school_h($csrf)?>"><label>Event<select name="eventID" required><option value="">Choose one event</option><?php foreach($events as $event):?><option value="<?=(int)$event['eventID']?>"><?=school_h($event['eventName'])?> · <?=school_h($event['eventDate'])?></option><?php endforeach;?></select></label><div class="teacher-form-actions"><button class="teacher-primary-button">Send reminders</button></div></form><?php endif;?></section></div></main></div></body></html>


















