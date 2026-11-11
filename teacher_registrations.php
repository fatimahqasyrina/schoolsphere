<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'Teacher') { header('Location: login.php'); exit; }
$user = $_SESSION['user']; $userId = (int)$user['userID'];
if (empty($_SESSION['sems_reg_csrf'])) $_SESSION['sems_reg_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['sems_reg_csrf']; $message = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf, $_POST['csrf'] ?? '')) throw new RuntimeException('Your session expired. Refresh the page.');
        $rid=(int)($_POST['registrationID']??0); $status=$_POST['status']??'';
        if (!in_array($status,['Approved','Rejected'], true)) throw new RuntimeException('Invalid status.');
        $find=$pdo->prepare("SELECT r.studentID,r.status AS oldStatus,e.eventName,u.userID FROM registrations r JOIN events e ON e.eventID=r.eventID JOIN students s ON s.studentID=r.studentID JOIN users u ON u.userID=s.userID WHERE r.registrationID=? AND e.createdBy=? AND r.status IN ('Pending Verification','Approved','Rejected')");
        $find->execute([$rid,$userId]); $reg=$find->fetch();
        if (!$reg) throw new RuntimeException('Registration not found or it does not belong to your event.');
        $upd=$pdo->prepare("UPDATE registrations SET status=? WHERE registrationID=? AND status=?"); $upd->execute([$status,$rid,$reg['oldStatus']]);
        if(!$upd->rowCount() && $reg['oldStatus']!==$status)throw new RuntimeException('The registration changed before it could be updated. Refresh the page.');
        if($reg['oldStatus']!==$status){$note=$pdo->prepare('INSERT INTO notifications (userID,title,message,link) VALUES (?,?,?,?)'); $note->execute([(int)$reg['userID'],'Registration '.$status,'Your registration for '.$reg['eventName'].' was '.$status.'.','my_registrations.php']);}
        $message='Registration status has been updated.';
    } catch (Throwable $ex) { $error=$ex instanceof RuntimeException?$ex->getMessage():'Could not save the changes.'; }
}
$studentDetails=null;
if (isset($_GET['student'])) {
    $d=$pdo->prepare("SELECT s.studentID,s.class,s.formLevel,s.phoneNo,s.address,s.emergencyContactName,s.emergencyContactPhone,s.emergencyRelationship,u.name,u.email,COUNT(CASE WHEN r.status='Approved' AND e.status='Approved' THEN 1 END) approvedCount,COUNT(CASE WHEN r.status='Pending Verification' THEN 1 END) pendingCount FROM students s JOIN users u ON u.userID=s.userID LEFT JOIN registrations r ON r.studentID=s.studentID LEFT JOIN events e ON e.eventID=r.eventID WHERE s.studentID=? GROUP BY s.studentID,s.class,s.formLevel,s.phoneNo,s.address,s.emergencyContactName,s.emergencyContactPhone,s.emergencyRelationship,u.name,u.email");
    $d->execute([$_GET['student']]); $studentDetails=$d->fetch();
}
$stmt=$pdo->prepare("SELECT r.registrationID,r.registrationDate,r.status,e.eventName,e.eventDate,e.capacity,(SELECT COUNT(*) FROM registrations rr WHERE rr.eventID=e.eventID AND rr.status='Approved') approvedPlaces,s.studentID,u.name AS studentName,u.email AS studentEmail,s.class,s.formLevel FROM registrations r JOIN events e ON e.eventID=r.eventID JOIN students s ON s.studentID=r.studentID JOIN users u ON u.userID=s.userID WHERE e.createdBy=? ORDER BY r.registrationDate DESC,r.registrationID DESC");
$stmt->execute([$userId]); $registrations=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Registrations | SchoolSphere</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/teacher.css?v=5"><link rel="stylesheet" href="assets/ui-polish.css?v=28"><script src="assets/table-search.js?v=2" defer></script><link rel="stylesheet" href="assets/school-sphere.css?v=18"></head><body class="teacher-dashboard"><div class="teacher-layout"><?php require __DIR__ . '/teacher_nav.php'; ?><main class="teacher-main"><header class="teacher-topbar"><div><span class="teacher-eyebrow">STUDENT PARTICIPATION</span><h1>Registrations</h1></div><div class="teacher-account"><strong><?=school_h($user['name'])?></strong></div></header><div class="teacher-content">
<?php if($message): ?><div class="teacher-success"><?=school_h($message)?></div><?php endif; ?><?php if($error): ?><div class="teacher-alert"><?=school_h($error)?></div><?php endif; ?>
<?php if($studentDetails): ?><section class="teacher-panel"><h2>Student details</h2><p><strong><?=school_h($studentDetails['name'])?></strong> · <?=school_h($studentDetails['studentID'])?></p><p>Email: <?=school_h($studentDetails['email'])?> · Class: <?=school_h($studentDetails['class']??'—')?> · Form: <?=school_h($studentDetails['formLevel']??'—')?></p><p>Phone: <?=school_h($studentDetails['phoneNo']??'—')?> · Address: <?=school_h($studentDetails['address']??'—')?></p><p>Emergency contact: <?=school_h($studentDetails['emergencyContactName']??'—')?> / <?=school_h($studentDetails['emergencyContactPhone']??'—')?> (<?=school_h($studentDetails['emergencyRelationship']??'—')?>)</p><p>Approved registrations: <?= (int)$studentDetails['approvedCount']?> · Pending: <?= (int)$studentDetails['pendingCount']?></p><a href="teacher_registrations.php">Close details</a></section><?php endif; ?>
<section class="teacher-panel"><div class="teacher-panel-heading"><div><h2>Registration list</h2><p><?=count($registrations)?> registrations. Events handled by <?=school_h($user['name'])?> · <?=school_h(school_public_person_id($pdo,$userId))?>.</p></div></div><?php if($registrations): ?><div class="teacher-table-wrap"><table class="teacher-table"><thead><tr><th>Student / eligibility</th><th>Event</th><th>Event date</th><th>Status</th><th>Decision</th></tr></thead><tbody><?php foreach($registrations as $row): ?><tr><td><a href="teacher_registrations.php?student=<?=rawurlencode($row['studentID'])?>"><strong><?=school_h($row['studentName'])?></strong></a><small class="teacher-cell-note"><?=school_h($row['studentID'])?> · <?=school_h($row['class']??'Class not set')?> · <?=school_h($row['formLevel']??'Level not set')?></small><small class="teacher-cell-note"><?=school_h($row['studentEmail'])?></small><small class="teacher-cell-note">Places approved: <?= (int)$row['approvedPlaces']?> / <?= (int)$row['capacity']?></small></td><td><?=school_h($row['eventName'])?></td><td><?=school_h(date('d M Y',strtotime($row['eventDate'])))?></td><td><?=school_h($row['status'])?></td><td><?php if(in_array($row['status'],['Pending Verification','Approved','Rejected'],true)): ?><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?=school_h($csrf)?>"><input type="hidden" name="registrationID" value="<?=(int)$row['registrationID']?>"><button name="status" value="Approved" class="teacher-primary-button">Approve</button><button name="status" value="Rejected" class="teacher-secondary-button">Reject</button></form><?php else: ?><?=school_h($row['status'])?><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="teacher-empty"><strong>No registrations yet</strong><p>Student registrations for your events will appear here.</p></div><?php endif; ?></section><footer class="teacher-footer"><strong>SMK Tengku Lela Segara · SchoolSphere</strong><span><a href="tel:+6096182227">09-618 2227</a> · <a href="mailto:tea4014@moe.edu.my">tea4014@moe.edu.my</a></span><span><a href="https://smktls.blogspot.com/">Official website</a> · <a href="https://www.facebook.com/195530113841302">Facebook</a></span></footer></div></main></div></body></html>





















