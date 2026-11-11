<?php
session_start();require_once __DIR__.'/config/db.php';
require_once __DIR__ . '/config/helpers.php';
if(!isset($_SESSION['user'])||($_SESSION['user']['role']??'')!=='Teacher'){header('Location: login.php');exit;}
$user=$_SESSION['user'];$uid=(int)$user['userID'];if(empty($_SESSION['sems_csrf']))$_SESSION['sems_csrf']=bin2hex(random_bytes(32));$csrf=$_SESSION['sems_csrf'];$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{if(!hash_equals($csrf,(string)($_POST['csrf']??'')))throw new RuntimeException('Session expired. Refresh and try again.');$id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');if(!in_array($status,['Verified','Rejected'],true))throw new RuntimeException('Choose a valid review result.');$q=$pdo->prepare("SELECT p.userID,e.eventName FROM payments p JOIN events e ON e.eventID=p.eventID WHERE p.paymentID=? AND e.createdBy=? AND p.status='Pending'");$q->execute([$id,$uid]);$payment=$q->fetch();if(!$payment)throw new RuntimeException('This payment is no longer awaiting review or is not for your event.');$q=$pdo->prepare("UPDATE payments p JOIN events e ON e.eventID=p.eventID SET p.status=? WHERE p.paymentID=? AND e.createdBy=? AND p.status='Pending'");$q->execute([$status,$id,$uid]);if($q->rowCount()){$n=$pdo->prepare('INSERT INTO notifications (userID,title,message,link) VALUES (?,?,?,?)');$n->execute([(int)$payment['userID'],'Payment '.$status,'Your payment for '.$payment['eventName'].' was '.$status.'.','my_payments.php']);$message='Payment review saved.';}else $error='This payment was already reviewed.';}catch(RuntimeException $e){$error=$e->getMessage();}catch(Throwable $e){$error='Could not save the review.';}
}
$q=$pdo->prepare('SELECT p.paymentID,p.userID,p.amount,p.method,p.reference,p.proofPath,p.status,p.submittedAt,u.name,e.eventName FROM payments p JOIN users u ON u.userID=p.userID JOIN events e ON e.eventID=p.eventID WHERE e.createdBy=? ORDER BY p.submittedAt DESC');$q->execute([$uid]);$rows=$q->fetchAll();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment Review | SchoolSphere</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/teacher.css?v=5"><link rel="stylesheet" href="assets/ui-polish.css?v=28"><script src="assets/table-search.js" defer></script><script src="assets/proof-viewer.js?v=3" defer></script><link rel="stylesheet" href="assets/school-sphere.css?v=18"></head><body class="teacher-dashboard"><div class="teacher-layout"><?php require __DIR__.'/teacher_nav.php';?><main class="teacher-main"><header class="teacher-topbar"><div><span class="teacher-eyebrow">EVENT FINANCE</span><h1>Payment Review</h1></div></header><div class="teacher-content"><?php if($message):?><div class="portal-notice success"><?=school_h($message)?></div><?php endif;?><?php if($error):?><div class="portal-notice error"><?=school_h($error)?></div><?php endif;?><section class="teacher-panel"><div class="teacher-panel-heading"><h2>Payments for my events</h2><a class="teacher-secondary-button" href="teacher_payment_reminders.php">Remind unpaid students</a></div><p>Event handler: <?=school_h($user['name'])?> · <?=school_h(school_public_person_id($pdo,$uid))?>.</p><?php if(!$rows):?><p>No payment records for your events yet.</p><?php else:?><div class="teacher-table-wrap"><table class="teacher-table"><thead><tr><th>Student</th><th>Event</th><th>Amount</th><th>Method</th><th>Reference</th><th>Proof</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=school_h($r['name'])?></td><td><?=school_h($r['eventName'])?></td><td>RM <?=number_format((float)$r['amount'],2)?></td><td><?=school_h($r['method'])?></td><td><?=school_h($r['reference'])?></td><td><?php if($r['proofPath']):$ext=strtolower(pathinfo((string)$r['proofPath'],PATHINFO_EXTENSION));?><a class="payment-proof-button" data-proof-viewer data-proof-type="<?=$ext==='pdf'?'pdf':'image'?>" href="payment_proof.php?id=<?=(int)$r['paymentID']?>">Open submitted proof</a><?php else:?>—<?php endif;?></td><td><?=school_h($r['status'])?></td><td><?php if($r['status']==='Pending'):?><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?=school_h($csrf)?>"><input type="hidden" name="id" value="<?=(int)$r['paymentID']?>"><button class="small-button" name="status" value="Verified"><?=($r['method']==='Cash'?'Verify cash':'Verify payment')?></button><button class="small-button danger" name="status" value="Rejected">Reject</button></form><?php else:?>—<?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section></div></main></div></body></html>

















