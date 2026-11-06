<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (($_SESSION['user']['role'] ?? '') !== 'Teacher') {
    session_write_close();
    require __DIR__ . '/_sems_portal.php';
    return;
}
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
$user = $_SESSION['user'];
$uid = (int)$user['userID'];
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) { $month = date('Y-m'); }
$start = $month . '-01';
$end = date('Y-m-t', strtotime($start));
$q = $pdo->prepare("SELECT e.eventID,e.eventDate AS itemDate,e.eventName AS itemName,e.location AS itemPlace,e.status AS itemStatus,'Event' AS itemType,COALESCE(owner.name,'School') AS itemHandler,e.eventPoster,e.eventTime,e.eventEndTime,e.capacity,e.fee,(SELECT COUNT(*) FROM registrations r WHERE r.eventID=e.eventID AND r.status NOT IN ('Rejected','Cancelled')) AS registered FROM events e LEFT JOIN users owner ON owner.userID=e.createdBy WHERE e.createdBy=? AND e.eventDate BETWEEN ? AND ? AND e.status<>'Cancelled' ORDER BY e.eventDate,e.eventTime");
$q->execute([$uid,$start,$end]);
$items=$q->fetchAll(PDO::FETCH_ASSOC);
$taskQuery=$pdo->prepare("SELECT t.eventID,t.dueDate AS itemDate,CONCAT(e.eventName,' — ',t.taskTitle) AS itemName,e.location AS itemPlace,t.taskStatus AS itemStatus,'Committee task' AS itemType,COALESCE(owner.name,'School') AS itemHandler,e.eventPoster,e.eventTime,e.eventEndTime,e.capacity,e.fee,(SELECT COUNT(*) FROM registrations r WHERE r.eventID=e.eventID AND r.status NOT IN ('Rejected','Cancelled')) AS registered FROM event_tasks t JOIN events e ON e.eventID=t.eventID LEFT JOIN users owner ON owner.userID=e.createdBy WHERE t.assignedTo=? AND t.dueDate BETWEEN ? AND ? AND t.taskStatus<>'Cancelled' ORDER BY t.dueDate,e.eventName");
$taskQuery->execute([$uid,$start,$end]);
$items=array_merge($items,$taskQuery->fetchAll(PDO::FETCH_ASSOC));
usort($items,static fn($a,$b)=>strcmp($a['itemDate'].' '.$a['itemName'],$b['itemDate'].' '.$b['itemName']));
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Calendar | SchoolSphere</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/portal.css?v=12"><link rel="stylesheet" href="assets/teacher.css?v=5"><link rel="stylesheet" href="assets/ui-polish.css?v=28"><link rel="stylesheet" href="assets/school-sphere.css?v=18"></head><body class="teacher-dashboard"><div class="teacher-layout"><?php require __DIR__.'/teacher_nav.php'; ?><main class="teacher-main"><header class="teacher-topbar"><div><span class="teacher-eyebrow">EVENT SCHEDULE</span><h1>Calendar</h1></div></header><div class="teacher-content"><section class="teacher-panel"><div class="teacher-panel-heading"><div><h2>Events and committee tasks</h2><p>Your events and assigned tasks for the selected month.</p></div></div><form method="get" class="teacher-form calendar-month-form"><label>Month<input type="month" name="month" value="<?=school_h($month)?>"></label><div class="teacher-form-actions"><button class="teacher-primary-button calendar-submit-button">Show month</button></div></form><?php if(!$items):?><div class="calendar-empty"><strong>No events or tasks this month</strong><p>Choose another month to see your schedule.</p></div><?php else:?><div class="calendar-result-grid"><?php foreach($items as $item):$poster=$item['eventPoster']?:'assets/sems-school-activities.jpg';$badge=$item['itemType']==='Committee task'?'Committee task':$item['itemStatus'];?><article class="calendar-result-card"><a class="calendar-result-image" href="event_detail.php?eventID=<?=(int)$item['eventID']?>"><img src="<?=school_h($poster)?>" alt="<?=school_h($item['itemName'])?>"><span class="calendar-result-type"><?=school_h($badge)?></span></a><div class="calendar-result-body"><h3><a href="event_detail.php?eventID=<?=(int)$item['eventID']?>"><?=school_h($item['itemName'])?></a></h3><p><?=school_h(date('D, d M Y',strtotime($item['itemDate'])))?><?php if($item['itemType']==='Event'&&$item['eventTime']):?> · <?=school_h(date('h:i A',strtotime($item['eventTime'])))?>–<?=school_h(date('h:i A',strtotime($item['eventEndTime']?:$item['eventTime'])))?><?php endif;?> · <?=school_h($item['itemPlace'])?></p><p><?= (int)$item['registered']?> / <?= (int)$item['capacity']?> registered · RM <?=school_h(number_format((float)$item['fee'],2))?></p><p class="calendar-result-handler">Handled by <?=school_h($item['itemHandler'])?></p><a class="calendar-result-link" href="<?= $item['itemType']==='Committee task'?'teacher_tasks.php':'event_detail.php?eventID='.(int)$item['eventID'] ?>"><?= $item['itemType']==='Committee task'?'View assigned task':'View event details' ?></a></div></article><?php endforeach;?></div><?php endif;?></section></div></main></div></body></html>













