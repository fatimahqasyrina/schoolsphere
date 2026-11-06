<?php
session_start();
require_once 'config/db.php';
require_once __DIR__ . '/config/helpers.php';
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'Teacher') { header('Location: login.php'); exit; }
$user = $_SESSION['user'];
$userId = (int) $user['userID'];
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];
$message = '';
$error = '';
$events = [];
$selectedEvent = null;
$eventNotStarted = false;
$roster = [];

try {
    $eventQuery = $pdo->prepare("SELECT eventID,eventName,eventDate,location FROM events WHERE createdBy=? AND status='Approved' ORDER BY eventDate DESC,eventName");
    $eventQuery->execute([$userId]);
    $events = $eventQuery->fetchAll(PDO::FETCH_ASSOC);

    $eventID = (int) ($_POST['eventID'] ?? $_GET['eventID'] ?? 0);
    if ($eventID > 0) {
        $eventQuery = $pdo->prepare("SELECT eventID,eventName,eventDate,location FROM events WHERE eventID=? AND createdBy=? AND status='Approved' LIMIT 1");
        $eventQuery->execute([$eventID, $userId]);
        $selectedEvent = $eventQuery->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$selectedEvent) { $error = 'This approved event could not be found in your account.'; }
        elseif ((string)$selectedEvent['eventDate'] > date('Y-m-d')) {
            $eventNotStarted = true;
            $error = 'Attendance is not open yet. You can record attendance only on the event date (' . date('d M Y', strtotime($selectedEvent['eventDate'])) . ').';
        }
    }

    if ($selectedEvent) {
        $rosterQuery = $pdo->prepare("SELECT r.studentID,u.name AS studentName,s.class,s.formLevel,a.status AS attendanceStatus,a.proofFile,a.proofStatus,a.checkInMethod,a.checkedInAt FROM registrations r JOIN students s ON s.studentID=r.studentID JOIN users u ON u.userID=s.userID LEFT JOIN attendance a ON a.studentID=r.studentID AND a.eventID=r.eventID WHERE r.eventID=? AND r.status='Approved' ORDER BY s.class,u.name");
        $rosterQuery->execute([$eventID]);
        $roster = $rosterQuery->fetchAll(PDO::FETCH_ASSOC);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) { throw new RuntimeException('Your form session expired. Refresh the page and try again.'); }
            if ($eventNotStarted) { throw new RuntimeException('Attendance cannot be recorded before the event date (' . date('d M Y', strtotime($selectedEvent['eventDate'])) . ').'); }
            $studentID = (string)($_POST['studentID'] ?? '');
            $action = (string)($_POST['attendance_action'] ?? '');
            $allowedStudents = array_fill_keys(array_column($roster, 'studentID'), true);
            if (!isset($allowedStudents[$studentID])) { throw new RuntimeException('This student is not on the approved roster for your event.'); }
            $studentQuery = $pdo->prepare("SELECT u.userID,u.name FROM registrations r JOIN students s ON s.studentID=r.studentID JOIN users u ON u.userID=s.userID WHERE r.eventID=? AND r.studentID=? AND r.status='Approved'");
            $studentQuery->execute([$eventID, $studentID]);
            $student = $studentQuery->fetch(PDO::FETCH_ASSOC);
            $eventDate = (string)$selectedEvent['eventDate'];

            if (in_array($action, ['door_checkin', 'mark_absent', 'edit_present', 'edit_absent'], true)) {
                if ($eventDate !== date('Y-m-d') && !in_array($action, ['edit_present', 'edit_absent'], true)) { throw new RuntimeException('Attendance can only be recorded on the event date.'); }
                $existing = $pdo->prepare('SELECT proofStatus,checkInMethod FROM attendance WHERE studentID=? AND eventID=?');
                $existing->execute([$studentID, $eventID]);
                $prior = $existing->fetch(PDO::FETCH_ASSOC) ?: [];
                if (($prior['proofStatus'] ?? '') === 'Verified' && !in_array($action, ['edit_present', 'edit_absent'], true)) { throw new RuntimeException('This student already has verified photo attendance. Use Edit to change the record.'); }
                $attendanceStatus = in_array($action, ['door_checkin', 'edit_present'], true) ? 'Present' : 'Absent';
                $isEdit = in_array($action, ['edit_present', 'edit_absent'], true);
                $save=$pdo->prepare("INSERT INTO attendance (studentID,eventID,attendanceDate,status,proofStatus,checkInMethod,checkedInBy,checkedInAt) VALUES (?,?,?,?,'Not Required','Teacher Door',?,NOW()) ON DUPLICATE KEY UPDATE attendanceDate=VALUES(attendanceDate),status=VALUES(status),proofFile=NULL,proofStatus='Not Required',checkInMethod='Teacher Door',checkedInBy=VALUES(checkedInBy),checkedInAt=NOW()");
                $save->execute([$studentID,$eventID,$eventDate,$attendanceStatus,$userId]);
                $message = $isEdit ? 'Attendance updated for ' . $student['name'] . '.' : ($action === 'door_checkin' ? 'Door check-in recorded for ' . $student['name'] . '.' : 'Absence recorded for ' . $student['name'] . '.');
                $notice=$pdo->prepare("INSERT INTO notifications (userID,title,message,link) VALUES (?,?,?,'attendance.php')");$attendanceNotice=$isEdit?'Your Teacher changed your attendance to '.$attendanceStatus.' for '.$selectedEvent['eventName'].'.':($attendanceStatus==='Absent'?'Your Teacher recorded you as absent for '.$selectedEvent['eventName'].'.':'Your Teacher recorded your attendance at the event door. No photo is needed.');$notice->execute([(int)$student['userID'],'Attendance '.$attendanceStatus,$attendanceNotice]);
            } elseif ($action === 'verify_photo' || $action === 'reject_photo') {
                $proofStatus = $action === 'verify_photo' ? 'Verified' : 'Rejected';
                $update = $pdo->prepare("UPDATE attendance a JOIN registrations r ON r.studentID=a.studentID AND r.eventID=a.eventID JOIN events e ON e.eventID=a.eventID SET a.proofStatus=?,a.status=IF(?='Verified','Present','Absent') WHERE a.studentID=? AND a.eventID=? AND a.checkInMethod='Student Photo' AND a.proofStatus='Pending Verification' AND r.status='Approved' AND e.createdBy=? AND e.status='Approved'");
                $update->execute([$proofStatus, $proofStatus, $studentID, $eventID, $userId]);
                if (!$update->rowCount()) { throw new RuntimeException('There is no pending photo proof for this student. Refresh and try again.'); }
                $notice=$pdo->prepare("INSERT INTO notifications (userID,title,message,link) VALUES (?,?,?,'attendance.php')"); $notice->execute([(int)$student['userID'],'Attendance photo '.$proofStatus,'Your attendance photo for '.$selectedEvent['eventName'].' was '.$proofStatus.'.']);
                $message = 'Attendance photo ' . strtolower($proofStatus) . ' for ' . $student['name'] . '.';
            } else {
                throw new RuntimeException('Choose a valid attendance action.');
            }
            $rosterQuery->execute([$eventID]);
            $roster = $rosterQuery->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $error = $e instanceof RuntimeException ? $e->getMessage() : 'Attendance could not be loaded or saved. Check the database and try again.';
}
$present = count(array_filter($roster, static fn($row) => ($row['attendanceStatus'] ?? '') === 'Present' && (($row['checkInMethod'] ?? '') !== 'Student Photo' || ($row['proofStatus'] ?? '') === 'Verified')));
$absent = count(array_filter($roster, static fn($row) => ($row['attendanceStatus'] ?? '') === 'Absent'));
$pendingVerification = count(array_filter($roster, static fn($row) => ($row['proofStatus'] ?? '') === 'Pending Verification'));
$approvedTotal = count($roster);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Attendance | SchoolSphere</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/teacher.css?v=5"><link rel="stylesheet" href="assets/ui-polish.css?v=28"><script src="assets/table-search.js" defer></script><script src="assets/proof-viewer.js?v=3" defer></script><link rel="stylesheet" href="assets/school-sphere.css?v=18"></head>
<body class="teacher-dashboard"><div class="teacher-layout">
<?php require __DIR__ . '/teacher_nav.php'; ?>
<main class="teacher-main"><header class="teacher-topbar"><div><span class="teacher-eyebrow">STUDENT PARTICIPATION</span><h1>Attendance</h1></div><div class="teacher-account"><span class="teacher-avatar"><?= school_h(strtoupper(substr($user['name'],0,1))) ?></span><span><strong><?= school_h($user['name']) ?></strong><small>Teacher</small></span></div></header>
<div class="teacher-content">
<?php if ($message): ?><div class="teacher-success"><?= school_h($message) ?></div><?php endif; ?><?php if ($error): ?><div class="teacher-alert"><?= school_h($error) ?></div><?php endif; ?>
<section class="teacher-panel"><div class="teacher-panel-heading"><div><h2>Choose an event</h2><p>Only your approved events and approved student registrations are included.</p></div></div>
<form method="get" class="teacher-form"><label class="teacher-form-wide">Event<select name="eventID" required><option value="">Select an approved event</option><?php foreach ($events as $event): ?><option value="<?= (int) $event['eventID'] ?>" <?= $selectedEvent && (int)$selectedEvent['eventID']===(int)$event['eventID'] ? 'selected' : '' ?>><?= school_h($event['eventName']) ?> — <?= school_h(date('d M Y',strtotime($event['eventDate']))) ?></option><?php endforeach; ?></select></label><div class="teacher-form-wide teacher-form-actions"><button class="teacher-primary-button" type="submit">View attendance</button></div></form>
<?php if (!$events): ?><div class="teacher-empty"><strong>No approved events yet</strong><p>Attendance will be available after the Principal approves one of your events.</p><a href="teacher_events.php">View my events</a></div><?php endif; ?></section>
<?php if ($selectedEvent): ?>
<section class="teacher-stats"><article class="teacher-stat"><span>Approved students</span><strong><?= $approvedTotal ?></strong><small>Event roster</small></article><article class="teacher-stat"><span>Present</span><strong><?= $present ?>/<?= $approvedTotal ?></strong><small>Door check-in or verified photo</small></article><article class="teacher-stat"><span>Photos to verify</span><strong><?= $pendingVerification ?></strong><small>Teacher review needed</small></article><article class="teacher-stat"><span>Absent</span><strong><?= $absent ?>/<?= $approvedTotal ?></strong><small>Approved students</small></article></section>
<section class="teacher-panel" id="attendance-roster"><div class="teacher-panel-heading"><div><h2><?= school_h($selectedEvent['eventName']) ?></h2><p><?= school_h(date('d M Y',strtotime($selectedEvent['eventDate']))) ?> · <?= school_h($selectedEvent['location']) ?> · Handled by <?=school_h($user['name'])?> (<?=school_h(school_public_person_id($pdo,$userId))?>). Use Door check-in for any student without a phone. Submitted photos can be reviewed here.</p></div></div>
<?php if ($roster): ?><div class="teacher-table-wrap"><table class="teacher-table"><thead><tr><th>Student</th><th>Class</th><th>Attendance</th><th>Check-in / verification</th><th>Teacher action</th></tr></thead><tbody><?php foreach ($roster as $row): $hasAttendance=in_array($row['attendanceStatus'] ?? '', ['Present','Absent'], true); ?><tr><td><strong><?= school_h($row['studentName']) ?></strong><small class="teacher-cell-note"><?= school_h($row['studentID']) ?> · <?= school_h($row['formLevel'] ?? '') ?></small></td><td><?= school_h($row['class'] ?: 'Class not set') ?></td><td><?= school_h($row['attendanceStatus'] ?? 'Not recorded') ?></td><td><?php if (($row['checkInMethod'] ?? '') === 'Student Photo'): ?><span><?= school_h($row['proofStatus'] ?? 'Pending Verification') ?></span><?php if (!empty($row['proofFile'])): ?> <a class="teacher-action-link" data-proof-viewer data-proof-type="image" href="attendance_proof.php?eventID=<?= (int)$selectedEvent['eventID'] ?>&amp;studentID=<?= rawurlencode($row['studentID']) ?>">View photo</a><?php endif; ?><?php elseif (($row['checkInMethod'] ?? '') === 'Teacher Door' && ($row['attendanceStatus'] ?? '') === 'Present' && !empty($row['checkedInAt'])): ?><span class="teacher-status status-approved">Door check-in recorded</span><small class="teacher-cell-note">At <?= school_h(date('h:i A', strtotime($row['checkedInAt']))) ?></small><?php else: ?><span class="teacher-muted">Not checked in</span><?php endif; ?></td><td><?php if ($eventNotStarted): ?><span class="teacher-muted">Available on event date</span><?php else: ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= school_h($csrf) ?>"><input type="hidden" name="eventID" value="<?= (int)$selectedEvent['eventID'] ?>"><input type="hidden" name="studentID" value="<?= school_h($row['studentID']) ?>"><?php if (($row['checkInMethod'] ?? '') === 'Student Photo' && ($row['proofStatus'] ?? '') === 'Pending Verification'): ?><button class="teacher-primary-button" name="attendance_action" value="verify_photo">Verify photo</button><button class="teacher-secondary-button" name="attendance_action" value="reject_photo">Reject</button><?php elseif ($hasAttendance && (string)($_GET['editStudent'] ?? '') !== (string)$row['studentID']): ?><a class="teacher-action-link" href="teacher_attendance.php?eventID=<?= (int)$selectedEvent['eventID'] ?>&amp;editStudent=<?= rawurlencode($row['studentID']) ?>#attendance-roster">Edit</a><?php else: ?><button class="teacher-primary-button" name="attendance_action" value="<?= $hasAttendance ? 'edit_present' : 'door_checkin' ?>" <?= !$hasAttendance && $selectedEvent['eventDate'] !== date('Y-m-d') ? 'disabled' : '' ?>><?= $hasAttendance ? 'Save as present' : 'Door check-in' ?></button><button class="teacher-secondary-button" name="attendance_action" value="<?= $hasAttendance ? 'edit_absent' : 'mark_absent' ?>" <?= !$hasAttendance && $selectedEvent['eventDate'] !== date('Y-m-d') ? 'disabled' : '' ?>><?= $hasAttendance ? 'Save as absent' : 'Mark absent' ?></button><?php endif; ?></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="teacher-empty"><strong>No approved registrations</strong><p>Students approved for this event will appear here.</p><a href="teacher_registrations.php">View registrations</a></div><?php endif; ?></section>
<?php endif; ?><footer class="teacher-footer"><strong>SMK Tengku Lela Segara · SchoolSphere</strong><span><a href="tel:+6096182227">09-618 2227</a> · <a href="mailto:tea4014@moe.edu.my">tea4014@moe.edu.my</a></span><span><a href="https://smktls.blogspot.com/">Official website</a> · <a href="https://www.facebook.com/195530113841302">Facebook</a></span><span>&copy; <?= date('Y') ?></span></footer></div></main></div></body></html>


















