<?php
session_start();
require_once __DIR__.'/config/db.php';
require_once __DIR__ . '/config/helpers.php';
if(!isset($_SESSION['user'])||($_SESSION['user']['role']??'')!=='Teacher'){header('Location: login.php');exit;}
$uid=(int)$_SESSION['user']['userID'];
if(empty($_SESSION['task_csrf']))$_SESSION['task_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['task_csrf'];$message='';$error='';
try{$has=$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_tasks' AND COLUMN_NAME='completionPhoto'")->fetchColumn();if(!(int)$has)$pdo->exec('ALTER TABLE event_tasks ADD COLUMN completionPhoto VARCHAR(255) NULL');}catch(Throwable $schemaError){$error='Task photo storage could not be prepared. Ask the system administrator to apply database/upgrade_features.sql.';}
if($_SERVER['REQUEST_METHOD']==='POST'&&hash_equals($csrf,$_POST['csrf']??'')){
 try{
  $id=(int)($_POST['taskID']??0);$status=(string)($_POST['taskStatus']??'Completed');if(!in_array($status,['Active','Completed'],true))throw new RuntimeException('Choose Active or Completed.');
  $q=$pdo->prepare("SELECT t.taskTitle,t.taskStatus,t.completionPhoto,e.eventName,e.createdBy FROM event_tasks t JOIN events e ON e.eventID=t.eventID WHERE t.taskID=? AND t.assignedTo=? AND t.taskStatus IN ('Active','Completed')");$q->execute([$id,$uid]);$task=$q->fetch();if(!$task)throw new RuntimeException('Task not found or unavailable.');
  $photo=$task['completionPhoto'];$upload=$_FILES['completionPhoto']??null;
  if($upload&&$upload['error']!==UPLOAD_ERR_NO_FILE){if($upload['error']!==UPLOAD_ERR_OK||$upload['size']<1||$upload['size']>5242880)throw new RuntimeException('Choose a task photo up to 5 MB.');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;if(!$ext)throw new RuntimeException('Task proof must be JPG, PNG or WebP.');$dir=__DIR__.'/uploads/committee_task_proofs';if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Could not prepare task photo storage.');$filename=bin2hex(random_bytes(24)).'.'.$ext;if(!move_uploaded_file($upload['tmp_name'],$dir.'/'.$filename))throw new RuntimeException('Could not save the task photo.');$photo='uploads/committee_task_proofs/'.$filename;}
  $save=$pdo->prepare('UPDATE event_tasks SET taskStatus=?,completionPhoto=? WHERE taskID=? AND assignedTo=?');$save->execute([$status,$photo,$id,$uid]);
  if($status==='Completed'&&$task['taskStatus']!=='Completed'&&(int)$task['createdBy']!==$uid){$notice=$pdo->prepare("INSERT INTO notifications(userID,title,message,link) VALUES(?,'Committee task completed',?, 'teacher_events.php')");$notice->execute([(int)$task['createdBy'],$_SESSION['user']['name'].' completed "'.$task['taskTitle'].'" for '.$task['eventName'].'.']);}
  $message=$status==='Completed'?'Task marked completed.':'Task status changed to Active.';
 }catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'Could not update task status.';}
}
$q=$pdo->prepare("SELECT t.taskID,t.taskTitle,t.taskDescription,t.dueDate,t.taskStatus,t.completionPhoto,e.eventName,e.eventDate,e.location,owner.userID AS organizerID,owner.name AS organizer FROM event_tasks t JOIN events e ON e.eventID=t.eventID LEFT JOIN users owner ON owner.userID=e.createdBy WHERE t.assignedTo=? ORDER BY FIELD(t.taskStatus,'Active','Tentative','Completed','Cancelled'),t.dueDate");$q->execute([$uid]);$tasks=$q->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Committee Tasks | SchoolSphere</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/teacher.css?v=5"><link rel="stylesheet" href="assets/ui-polish.css?v=28"><script src="assets/table-search.js" defer></script><link rel="stylesheet" href="assets/school-sphere.css?v=18"></head><body class="teacher-dashboard"><div class="teacher-layout"><?php require __DIR__.'/teacher_nav.php';?><main class="teacher-main"><header class="teacher-topbar"><div><span class="teacher-eyebrow">ASSIGNED WORK</span><h1>Committee Tasks</h1></div></header><div class="teacher-content"><?php if($message):?><div class="teacher-success"><?=school_h($message)?></div><?php endif;?><?php if($error):?><div class="teacher-alert"><?=school_h($error)?></div><?php endif;?><section class="teacher-panel"><h2>My assigned events and tasks</h2><p>You can optionally attach a photo and correct an accidental status change.</p><?php if($tasks):?><div class="teacher-table-wrap"><table class="teacher-table"><thead><tr><th>Task</th><th>Event</th><th>Date / location</th><th>Due</th><th>Status</th><th>Completion photo</th><th>Update</th></tr></thead><tbody><?php foreach($tasks as $t):?><tr><td><strong><?=school_h($t['taskTitle'])?></strong><small><?=school_h($t['taskDescription']??'')?></small></td><td><?=school_h($t['eventName'])?><small>Organizer: <?=school_h($t['organizer']??'Account unavailable')?><?=($label=school_public_person_id($pdo,(int)($t['organizerID']??0)))!==''?' · '.school_h($label):''?>?></small></td><td><?=school_h($t['eventDate'])?> · <?=school_h($t['location'])?></td><td><?=school_h($t['dueDate']??'—')?></td><td><?=school_h($t['taskStatus'])?></td><td><?php if($t['completionPhoto']):?><a class="teacher-secondary-button" data-proof-viewer data-proof-type="image" href="<?=school_h($t['completionPhoto'])?>">View photo</a><?php else:?>—<?php endif;?></td><td><?php if(in_array($t['taskStatus'],['Active','Completed'],true)):?><form method="post" enctype="multipart/form-data" class="task-update-form"><input type="hidden" name="csrf" value="<?=school_h($csrf)?>"><input type="hidden" name="taskID" value="<?=(int)$t['taskID']?>"><label>Status<select name="taskStatus"><option value="Active" <?=$t['taskStatus']==='Active'?'selected':''?>>Active</option><option value="Completed" <?=$t['taskStatus']==='Completed'?'selected':''?>>Completed</option></select></label><label>Photo (optional)<input type="file" name="completionPhoto" accept="image/jpeg,image/png,image/webp"></label><button class="teacher-primary-button">Save task</button></form><?php else:?><?=school_h($t['taskStatus'])?><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php else:?><p>No committee task assignments yet.</p><?php endif;?></section></div></main></div><script src="assets/proof-viewer.js?v=3" defer></script></body></html>
















