<?php
session_start();
require_once 'config/db.php';
require_once __DIR__ . '/config/helpers.php';
try { foreach (['eventEndTime'=>'TIME NULL AFTER eventTime','eventPaymentQR'=>'VARCHAR(255) NULL AFTER eventPoster','bankName'=>'VARCHAR(100) NULL AFTER eventPaymentQR','bankAccountName'=>'VARCHAR(150) NULL AFTER bankName','bankAccountNumber'=>'VARCHAR(50) NULL AFTER bankAccountName'] as $column=>$definition) { $check=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME=?"); $check->execute([$column]); if (!(int)$check->fetchColumn()) $pdo->exec("ALTER TABLE events ADD COLUMN `$column` $definition"); } } catch (Throwable $schemaError) { }
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Teacher') { header('Location: login.php'); exit; }
$user = $_SESSION['user'];
$userId = (int) $user['userID'];
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];
$message = $_SESSION['teacher_event_notice'] ?? '';
unset($_SESSION['teacher_event_notice']);
$error = '';
$form = ['eventID' => '', 'eventName' => '', 'description' => '', 'principalNote' => '', 'eventPoster' => '', 'eventPaymentQR' => '', 'bankName'=>'','bankAccountName'=>'','bankAccountNumber'=>'', 'eventDate' => '', 'eventTime' => '09:00', 'eventEndTime' => '10:00', 'registrationDeadline' => '', 'location' => '', 'capacity' => '30', 'fee' => '0.00', 'feeType' => 'free'];


if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT eventID,eventName,description,principalNote,eventPoster,eventPaymentQR,bankName,bankAccountName,bankAccountNumber,eventDate,eventTime,eventEndTime,registrationDeadline,location,capacity,fee,status FROM events WHERE eventID=? AND createdBy=?");
    $stmt->execute([(int) $_GET['edit'], $userId]);
    $editEvent = $stmt->fetch();
    if (!$editEvent) { $error = 'Event not found or it does not belong to your account.'; }
    elseif ($editEvent['status']==='Cancelled') { $error = 'Cancelled events cannot be edited.'; }
    else { $form = $editEvent; $form['feeType']=(float)$form['fee']>0?'paid':'free'; $form['eventTime'] = substr((string)$form['eventTime'], 0, 5); $form['eventEndTime'] = substr((string)($form['eventEndTime'] ?: '10:00'), 0, 5); $form['registrationDeadline'] = $form['registrationDeadline'] ? date('Y-m-d\\TH:i', strtotime($form['registrationDeadline'])) : ''; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_event'])) {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Refresh the page and try again.';
    } else {
        $deleteEventId = (int)$_POST['cancel_event'];
        try {
            if ($deleteEventId < 1) { throw new RuntimeException('Choose a valid event to cancel.'); }
            $pdo->beginTransaction();
            $eventCheck = $pdo->prepare("SELECT eventName,eventDate,eventTime,eventEndTime,status FROM events WHERE eventID=? AND createdBy=? AND status<>'Cancelled' FOR UPDATE");
            $eventCheck->execute([$deleteEventId, $userId]);
            $deleteEvent = $eventCheck->fetch();
            if (!$deleteEvent) { throw new RuntimeException('This event was not found in your account.'); } if ($deleteEvent['status']==='Pending') { throw new RuntimeException('Pending events cannot be cancelled before the Principal makes a decision.'); } if (strtotime($deleteEvent['eventDate'].' '.($deleteEvent['eventEndTime']?:$deleteEvent['eventTime'])) < time()) { throw new RuntimeException('Past events can no longer be cancelled.'); }

            $studentRecipients = $pdo->prepare("SELECT DISTINCT u.userID FROM registrations r JOIN students s ON s.studentID=r.studentID JOIN users u ON u.userID=s.userID WHERE r.eventID=? AND r.status IN ('Pending Verification','Approved')");
            $studentRecipients->execute([$deleteEventId]);
            $studentNotice = $pdo->prepare("INSERT INTO notifications (userID,title,message,link) VALUES (?,'Event cancelled',?,'my_registrations.php')");
            foreach ($studentRecipients as $recipient) {
                $studentNotice->execute([(int)$recipient['userID'], 'The event "' . $deleteEvent['eventName'] . '" was cancelled. Your registration and attendance history remain saved.']);
            }

            $committeeRecipients = $pdo->prepare("SELECT DISTINCT assignedTo FROM event_tasks WHERE eventID=? AND taskStatus IN ('Tentative','Active')");
            $committeeRecipients->execute([$deleteEventId]);
            $committeeNotice = $pdo->prepare("INSERT INTO notifications (userID,title,message,link) VALUES (?,'Event cancelled',?,'teacher_tasks.php')");
            foreach ($committeeRecipients as $recipient) {
                $committeeNotice->execute([(int)$recipient['assignedTo'], 'The event "' . $deleteEvent['eventName'] . '" was cancelled. Its assignment history remains saved.']);
            }

            $delete = $pdo->prepare("UPDATE events SET status='Cancelled' WHERE eventID=? AND createdBy=? AND status<>'Cancelled'");
            $delete->execute([$deleteEventId, $userId]);
            if (!$delete->rowCount()) { throw new RuntimeException('The event could not be cancelled. Refresh and try again.'); }
            $pdo->prepare("UPDATE event_tasks SET taskStatus='Cancelled' WHERE eventID=? AND taskStatus IN ('Tentative','Active')")->execute([$deleteEventId]);
            $principalNotice=$pdo->prepare("SELECT userID FROM users WHERE role='Principal' AND status='Active'");$principalNotice->execute();$sendPrincipalNotice=$pdo->prepare("INSERT INTO notifications(userID,title,message,link) VALUES(?,'Event cancelled by Teacher',?,'admin_events.php')");foreach($principalNotice as $principal)$sendPrincipalNotice->execute([(int)$principal['userID'],'Teacher '.$user['name'].' cancelled the event "'.$deleteEvent['eventName'].'". Its event and student history remain saved.']);
            $pdo->commit();
            $_SESSION['teacher_event_notice'] = 'Event "' . $deleteEvent['eventName'] . '" was cancelled. Its records are retained.';
            header('Location: teacher_events.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Could not cancel the event. No changes were saved.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['cancel_event'])) {
    $form = [
        'eventID' => trim($_POST['eventID'] ?? ''),
        'eventName' => trim($_POST['eventName'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'principalNote' => trim($_POST['principalNote'] ?? ''),
        'bankName' => trim($_POST['bankName'] ?? ''), 'bankAccountName' => trim($_POST['bankAccountName'] ?? ''), 'bankAccountNumber' => trim($_POST['bankAccountNumber'] ?? ''),
        'taskDueDate' => trim($_POST['taskDueDate'] ?? ''),
        'eventDate' => trim($_POST['eventDate'] ?? ''),
        'eventTime' => trim($_POST['eventTime'] ?? ''),
        'eventEndTime' => trim($_POST['eventEndTime'] ?? ''),
        'registrationDeadline' => trim($_POST['registrationDeadline'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'capacity' => trim($_POST['capacity'] ?? ''),
        'fee' => trim($_POST['fee'] ?? '0.00'),
        'feeType' => (($_POST['feeType'] ?? 'free') === 'paid' ? 'paid' : 'free')
    ];
    if ($form['feeType'] === 'free') { $form['fee']='0.00'; $form['bankName']=$form['bankAccountName']=$form['bankAccountNumber']=''; }
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) { $error = 'Invalid request. Refresh the page and try again.'; }
    elseif ($form['eventName'] === '' || mb_strlen($form['eventName']) > 100 || $form['location'] === '' || mb_strlen($form['location']) > 100) { $error = 'Event name and location are required (maximum 100 characters).'; }
    elseif (!($eventDate = DateTime::createFromFormat('!Y-m-d', $form['eventDate'])) || ($dateErrors = DateTime::getLastErrors()) && ($dateErrors['warning_count'] || $dateErrors['error_count']) || ($form['eventID']==='' && $form['eventDate'] < date('Y-m-d'))) { $error = 'Choose today or a future date.'; }
    elseif (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $form['eventTime'])) { $error = 'Enter a valid event start time.'; }
    elseif (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $form['eventEndTime']) || $form['eventEndTime'] <= $form['eventTime']) { $error = 'Event end time must be later than its start time.'; }
    elseif (!($eventStart = DateTime::createFromFormat('!Y-m-d H:i:s', $form['eventDate'] . ' ' . $form['eventTime'] . ':00', new DateTimeZone('Asia/Kuala_Lumpur')))) { $error = 'Choose a valid event date and start time.'; }
    elseif ($form['eventID']==='' && $eventStart <= new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'))) { $error = 'The event date and start time must be in the future.'; }
    elseif (!($deadline = DateTime::createFromFormat('!Y-m-d\\TH:i', $form['registrationDeadline'])) || ($deadlineErrors = DateTime::getLastErrors()) && ($deadlineErrors['warning_count'] || $deadlineErrors['error_count'])) { $error = 'Choose a valid registration closing date and time.'; }
    elseif ($form['eventID']==='' && $deadline <= new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'))) { $error = 'The selected registration closing time has passed. Choose a future time before the event starts.'; }
    elseif ($deadline >= $eventStart) { $error = 'Registration must close before the event starts.'; }
    elseif (filter_var($form['capacity'], FILTER_VALIDATE_INT) === false || (int) $form['capacity'] < 1) { $error = 'Capacity must be a whole number of at least 1.'; }
    elseif (!is_numeric($form['fee']) || (float) $form['fee'] < 0 || (float) $form['fee'] > 99999999.99) { $error = 'Fee must be a valid non-negative number.'; }
    elseif ($form['feeType'] === 'paid' && (float)$form['fee'] <= 0) { $error = 'Enter a fee greater than RM 0 for a paid event.'; }
    elseif ((($form['bankName']!=='')+($form['bankAccountName']!=='')+($form['bankAccountNumber']!==''))!==0 && !(($form['bankName']!=='')&&($form['bankAccountName']!=='')&&($form['bankAccountNumber']!==''))) { $error = 'Enter the bank name, account holder name and account number together, or leave all three blank.'; }
    elseif ($form['bankAccountNumber']!=='' && !preg_match('/^[A-Za-z0-9 -]{5,50}$/',$form['bankAccountNumber'])) { $error = 'Enter a valid bank account number.'; }
    else {
        try {
            $posterPath=null;
            $qrPath=null;
            if($form['eventID']!==''){$posterQuery=$pdo->prepare('SELECT eventPoster,eventPaymentQR FROM events WHERE eventID=? AND createdBy=?');$posterQuery->execute([(int)$form['eventID'],$userId]);$existingMedia=$posterQuery->fetch();$posterPath=$existingMedia['eventPoster']??null;$qrPath=$existingMedia['eventPaymentQR']??null;}
            $posterUpload=$_FILES['eventPoster']??null;
            if($posterUpload&&$posterUpload['error']!==UPLOAD_ERR_NO_FILE){if($posterUpload['error']!==UPLOAD_ERR_OK||$posterUpload['size']<1||$posterUpload['size']>5242880)throw new RuntimeException('Event banner must be an image up to 5 MB.');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($posterUpload['tmp_name']);$allowedPosters=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($allowedPosters[$mime]))throw new RuntimeException('Event banner must be JPG, PNG, or WebP.');$posterDir=__DIR__.'/uploads/event_posters';if(!is_dir($posterDir)&&!mkdir($posterDir,0700,true)&&!is_dir($posterDir))throw new RuntimeException('Could not prepare event image storage.');$posterName=bin2hex(random_bytes(24)).'.'.$allowedPosters[$mime];if(!move_uploaded_file($posterUpload['tmp_name'],$posterDir.'/'.$posterName))throw new RuntimeException('Could not save the event banner.');$posterPath='uploads/event_posters/'.$posterName;}
            $qrUpload=$_FILES['eventPaymentQR']??null;
            if($qrUpload&&$qrUpload['error']!==UPLOAD_ERR_NO_FILE){if($qrUpload['error']!==UPLOAD_ERR_OK||$qrUpload['size']<1||$qrUpload['size']>5242880)throw new RuntimeException('Payment QR must be an image up to 5 MB.');$qrMime=(new finfo(FILEINFO_MIME_TYPE))->file($qrUpload['tmp_name']);$qrExt=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$qrMime]??null;if(!$qrExt)throw new RuntimeException('Payment QR must be JPG, PNG, or WebP.');$qrDir=__DIR__.'/uploads/payment_qr';if(!is_dir($qrDir)&&!mkdir($qrDir,0700,true)&&!is_dir($qrDir))throw new RuntimeException('Could not prepare QR image storage.');$qrName=bin2hex(random_bytes(24)).'.'.$qrExt;if(!move_uploaded_file($qrUpload['tmp_name'],$qrDir.'/'.$qrName))throw new RuntimeException('Could not save the payment QR.');$qrPath='uploads/payment_qr/'.$qrName;}
                        $taskDrafts=[];$committeeIds=[];
            foreach((array)($_POST['tasks']??[]) as $taskInput){$taskTitle=trim((string)($taskInput['title']??''));$taskDescription=trim((string)($taskInput['description']??''));$dueDate=(string)($taskInput['dueDate']??$form['eventDate']);$teacherIds=array_values(array_unique(array_filter(array_map('intval',(array)($taskInput['teachers']??[])),fn($id)=>$id>0)));if($taskTitle===''||strlen($taskTitle)>120||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dueDate)||$dueDate<$form['eventDate']||strlen($taskDescription)>2000||!$teacherIds)throw new RuntimeException('Set each task title and due date, then choose at least one teacher.');foreach($teacherIds as $teacherId){$capacity=$pdo->prepare("SELECT COUNT(DISTINCT t.eventID) FROM event_tasks t JOIN events e ON e.eventID=t.eventID WHERE t.assignedTo=? AND t.taskStatus IN ('Tentative','Active') AND e.status IN ('Pending','Approved') AND t.eventID<>?");$capacity->execute([$teacherId,(int)($form['eventID']?:0)]);if((int)$capacity->fetchColumn()>=3)throw new RuntimeException('A selected teacher already has tasks on 3 active events. Choose another teacher.');$sameDay=$pdo->prepare("SELECT COUNT(*) FROM events e WHERE e.eventDate=? AND e.status IN ('Pending','Approved') AND e.eventID<>? AND (e.createdBy=? OR EXISTS(SELECT 1 FROM event_tasks t WHERE t.eventID=e.eventID AND t.assignedTo=? AND t.taskStatus IN ('Tentative','Active')))");$sameDay->execute([$form['eventDate'],(int)($form['eventID']?:0),$teacherId,$teacherId]);if((int)$sameDay->fetchColumn())throw new RuntimeException('A selected teacher already has committee work for this date. Choose another teacher.');$valid=$pdo->prepare("SELECT COUNT(*) FROM users WHERE userID=? AND role='Teacher' AND status='Active'");$valid->execute([$teacherId]);if(!(int)$valid->fetchColumn())throw new RuntimeException('A selected teacher is not available.');$committeeIds[]=$teacherId;}$taskDrafts[]=['title'=>$taskTitle,'description'=>$taskDescription,'dueDate'=>$dueDate,'teachers'=>$teacherIds];}
            $committeeIds=array_values(array_unique($committeeIds));            if ($form['eventID'] !== '') {
                $ownerCheck = $pdo->prepare("SELECT eventID,status FROM events WHERE eventID=? AND createdBy=? AND status<>'Cancelled'");
                $ownerCheck->execute([(int) $form['eventID'], $userId]);
                $ownedEvent=$ownerCheck->fetch(PDO::FETCH_ASSOC);
                if (!$ownedEvent) { throw new RuntimeException('Event not found in your account or already cancelled.'); }
                $newTaskStatus=$ownedEvent['status']==='Approved'?'Active':'Tentative';
                $pdo->beginTransaction();
                try {
                $stmt = $pdo->prepare("UPDATE events SET eventName=?, description=?, principalNote=?, eventPoster=?, eventPaymentQR=?, bankName=?,bankAccountName=?,bankAccountNumber=?, eventDate=?, eventTime=?, eventEndTime=?, registrationDeadline=?, location=?, capacity=?, fee=? WHERE eventID=? AND createdBy=? AND status<>'Cancelled'");
                $stmt->execute([$form['eventName'], $form['description'], $form['principalNote'], $posterPath, $qrPath, $form['bankName']?:null,$form['bankAccountName']?:null,$form['bankAccountNumber']?:null,$form['eventDate'], $form['eventTime'] . ':00', $form['eventEndTime'] . ':00', $deadline->format('Y-m-d H:i:s'), $form['location'], (int) $form['capacity'], number_format((float) $form['fee'], 2, '.', ''), (int) $form['eventID'], $userId]);
                $pdo->prepare("UPDATE event_tasks SET taskStatus='Cancelled' WHERE eventID=? AND taskStatus IN ('Tentative','Active')")->execute([(int)$form['eventID']]);
                $task=$pdo->prepare("INSERT INTO event_tasks (eventID,assignedTo,assignedBy,taskTitle,taskDescription,dueDate,taskStatus) VALUES (?,?,?,?,?,?,?)");
                foreach($taskDrafts as $taskDraft){foreach($taskDraft['teachers'] as $teacherId){$task->execute([(int)$form['eventID'],$teacherId,$userId,$taskDraft['title'],$taskDraft['description']?:null,$taskDraft['dueDate'],$newTaskStatus]);if($newTaskStatus==='Active'){$n=$pdo->prepare("INSERT INTO notifications(userID,title,message,link) VALUES(?,'Committee assignment updated',?,'teacher_tasks.php')");$n->execute([$teacherId,'You were assigned "'.$taskDraft['title'].'" for '.$form['eventName'].'.']);}}}
                $pdo->commit();
                } catch(Throwable $editSaveError) { if($pdo->inTransaction())$pdo->rollBack(); throw $editSaveError; }
                $principalNotice=$pdo->prepare("SELECT userID FROM users WHERE role='Principal' AND status='Active'");$principalNotice->execute();$notify=$pdo->prepare("INSERT INTO notifications(userID,title,message,link) VALUES(?,'Event details updated by organizer',?,'admin_events.php')");foreach($principalNotice as $principal)$notify->execute([(int)$principal['userID'],$user['name'].' updated and resubmitted "'.$form['eventName'].'" for approval.']);
                $message = 'Event details updated.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO events (eventName,description,principalNote,eventPoster,eventPaymentQR,bankName,bankAccountName,bankAccountNumber,eventDate,eventTime,eventEndTime,registrationDeadline,location,capacity,fee,status,createdBy) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'Pending',?)");
                $pdo->beginTransaction();
                try {
                    $stmt->execute([$form['eventName'], $form['description'], $form['principalNote'], $posterPath, $qrPath,$form['bankName']?:null,$form['bankAccountName']?:null,$form['bankAccountNumber']?:null,$form['eventDate'], $form['eventTime'] . ':00', $form['eventEndTime'] . ':00', $deadline->format('Y-m-d H:i:s'), $form['location'], (int) $form['capacity'], number_format((float) $form['fee'], 2, '.', ''), $userId]);
                    $eventId=(int)$pdo->lastInsertId();
                                        $task=$pdo->prepare("INSERT INTO event_tasks (eventID,assignedTo,assignedBy,taskTitle,taskDescription,dueDate,taskStatus) VALUES (?,?,?,?,?,?,'Tentative')");
                    foreach($taskDrafts as $taskDraft){foreach($taskDraft['teachers'] as $teacherId){$task->execute([$eventId,$teacherId,$userId,$taskDraft['title'],$taskDraft['description']?:null,$taskDraft['dueDate']]);}}                    $pdo->commit();
                } catch(Throwable $saveError) { if($pdo->inTransaction()) $pdo->rollBack(); throw $saveError; }
                $principalNotice=$pdo->prepare("SELECT userID FROM users WHERE role='Principal' AND status='Active'");$principalNotice->execute();$notify=$pdo->prepare("INSERT INTO notifications(userID,title,message,link) VALUES(?,'New event request',?,'admin_events.php')");foreach($principalNotice as $principal)$notify->execute([(int)$principal['userID'],$user['name'].' submitted "'.$form['eventName'].'" for approval.']);
                $message = 'Event submitted for approval.';
            }
            $form = ['eventID' => '', 'eventName' => '', 'description' => '', 'principalNote' => '', 'eventDate' => '', 'eventTime' => '09:00', 'eventEndTime' => '10:00', 'registrationDeadline' => '', 'location' => '', 'capacity' => '30', 'fee' => '0.00', 'feeType' => 'free'];
        } catch (Throwable $e) { $error = $e instanceof RuntimeException ? $e->getMessage() : 'Could not save the event. Check the database.'; }
    }
}

$stmt = $pdo->prepare("SELECT e.eventID,e.createdBy,owner.name AS organizer,e.eventName,e.description,e.eventPoster,e.eventDate,e.eventTime,e.eventEndTime,e.registrationDeadline,e.location,e.capacity,e.fee,e.status,COUNT(CASE WHEN r.status NOT IN ('Rejected','Cancelled') THEN r.registrationID END) AS registrations,(SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.eventID=e.eventID AND p.status='Verified') AS collectedTotal FROM events e LEFT JOIN users owner ON owner.userID=e.createdBy LEFT JOIN registrations r ON r.eventID=e.eventID GROUP BY e.eventID,e.createdBy,owner.name,e.eventName,e.description,e.eventPoster,e.eventDate,e.eventTime,e.eventEndTime,e.registrationDeadline,e.location,e.capacity,e.fee,e.status ORDER BY e.eventDate DESC,e.eventTime DESC");
$stmt->execute();
$events = $stmt->fetchAll();
$committeeByEvent=[];$committeeQuery=$pdo->prepare("SELECT DISTINCT t.eventID,t.taskTitle,t.taskStatus,t.completionPhoto,u.name FROM event_tasks t JOIN events e ON e.eventID=t.eventID JOIN users u ON u.userID=t.assignedTo WHERE e.createdBy=? OR t.assignedTo=? ORDER BY t.eventID,t.taskTitle,u.name");$committeeQuery->execute([$userId,$userId]);foreach($committeeQuery as $committeeRow)$committeeByEvent[(int)$committeeRow['eventID']][]=$committeeRow;$feedbackByEvent=[]; $feedbackQuery=$pdo->query('SELECT f.eventID,f.rating,f.comments,f.photoPath,f.submittedAt,u.name AS studentName FROM feedback f JOIN events e ON e.eventID=f.eventID JOIN users u ON u.userID=f.userID ORDER BY f.submittedAt DESC');foreach($feedbackQuery as $feedbackRow)$feedbackByEvent[(int)$feedbackRow['eventID']][]=$feedbackRow;
$editingEventId=(int)($form['eventID']??0);$committeeTeachers=$pdo->prepare("SELECT u.userID,u.name,COUNT(DISTINCT CASE WHEN t.taskStatus IN ('Tentative','Active') AND e.status IN ('Pending','Approved') AND t.eventID<>? THEN t.eventID END) AS assigned,(SELECT GROUP_CONCAT(DISTINCT ev.eventDate ORDER BY ev.eventDate SEPARATOR ',') FROM events ev WHERE ev.status IN ('Pending','Approved') AND ev.eventID<>? AND (ev.createdBy=u.userID OR EXISTS(SELECT 1 FROM event_tasks tt WHERE tt.eventID=ev.eventID AND tt.assignedTo=u.userID AND tt.taskStatus IN ('Tentative','Active')))) AS busyDates FROM users u LEFT JOIN event_tasks t ON t.assignedTo=u.userID LEFT JOIN events e ON e.eventID=t.eventID WHERE u.role='Teacher' AND u.status='Active' GROUP BY u.userID,u.name ORDER BY u.name");$committeeTeachers->execute([$editingEventId,$editingEventId]);$committeeTeachers=$committeeTeachers->fetchAll();
$myAssignedQ=$pdo->prepare("SELECT DISTINCT eventID FROM event_tasks WHERE assignedTo=? AND taskStatus IN ('Tentative','Active','Completed')");$myAssignedQ->execute([$userId]);$myAssignedEventIds=array_fill_keys(array_map('intval',$myAssignedQ->fetchAll(PDO::FETCH_COLUMN)),true);$eventStage=in_array($_GET['stage']??'all',['all','upcoming','ongoing','past'],true)?($_GET['stage']??'all'):'all';$now=time();$displayEvents=array_values(array_filter($events,function($event)use($eventStage,$userId,$myAssignedEventIds,$now){if((int)$event['createdBy']!==$userId&&!isset($myAssignedEventIds[(int)$event['eventID']]))return false;if($event['status']==='Cancelled')return false;if($eventStage==='all')return true;$start=strtotime($event['eventDate'].' '.$event['eventTime']);$end=strtotime($event['eventDate'].' '.($event['eventEndTime']?:$event['eventTime']));return $eventStage==='upcoming'?$start>$now:($eventStage==='ongoing'?$start<=$now&&$end>=$now:$end<$now);}));
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>My Events | SchoolSphere</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/teacher.css?v=5"><link rel="stylesheet" href="assets/portal.css?v=12"><link rel="stylesheet" href="assets/ui-polish.css?v=28"><script src="assets/table-search.js?v=2" defer></script><script src="assets/proof-viewer.js?v=3" defer></script><link rel="stylesheet" href="assets/school-sphere.css?v=18"></head>
<body class="teacher-dashboard"><div class="teacher-layout"><?php require __DIR__ . '/teacher_nav.php'; ?><main class="teacher-main"><header class="teacher-topbar"><div><span class="teacher-eyebrow">EVENT MANAGEMENT</span><h1>My Events</h1></div><div class="teacher-account"><span class="teacher-avatar"><?= school_h(strtoupper(substr($user['name'], 0, 1))) ?></span><span><strong><?= school_h($user['name']) ?></strong><small>Teacher</small></span></div></header><div class="teacher-content">
    <?php if ($message): ?><div class="teacher-success"><?= school_h($message) ?></div><?php endif; ?><?php if ($error): ?><div class="teacher-alert"><?= school_h($error) ?></div><?php endif; ?>
    <section class="teacher-panel" id="event-form"><div class="teacher-panel-heading"><div><h2><?= $form['eventID'] ? 'Edit event' : 'Create a new event' ?></h2><p><?= $form['eventID'] ? 'The event will be resubmitted for approval.' : 'New events are submitted for approval.' ?></p></div></div>
        <form method="post" enctype="multipart/form-data" class="teacher-form"><input type="hidden" name="csrf_token" value="<?= school_h($csrf) ?>"><input type="hidden" name="eventID" value="<?= school_h($form['eventID']) ?>">
            <label>Event name<input name="eventName" maxlength="100" required value="<?= school_h($form['eventName']) ?>" placeholder="Example: School Sports Day"></label>
            <label>Event Date<input type="date" name="eventDate" min="<?= school_h(date('Y-m-d')) ?>" required value="<?= school_h($form['eventDate']) ?>"></label>
            <label>Event start time<input type="time" name="eventTime" required value="<?= school_h($form['eventTime']) ?>"></label>
            <label>Event end time<input type="time" name="eventEndTime" required value="<?= school_h($form['eventEndTime']) ?>"><small>Used to determine whether the event is ongoing or finished.</small></label>
            <label>Registration closes<input type="datetime-local" name="registrationDeadline" min="<?= school_h(date('Y-m-d\\TH:i')) ?>" required value="<?= school_h($form['registrationDeadline']) ?>"><small>Choose a future date and time before the event starts.</small></label>
            <label>Location<input name="location" maxlength="100" required value="<?= school_h($form['location']) ?>" placeholder="Example: School field"></label>
            <label>Student capacity<input type="number" name="capacity" min="1" max="100000" required value="<?= school_h($form['capacity']) ?>"></label>
                        <label>Event payment<select name="feeType" id="event-fee-type"><option value="free" <?=($form['feeType']??'free')==='free'?'selected':''?>>Free</option><option value="paid" <?=($form['feeType']??'free')==='paid'?'selected':''?>>Paid</option></select></label>
            <div id="event-payment-fields" class="teacher-form-wide" <?=($form['feeType']??'free')!=='paid'?'hidden':''?>><div class="teacher-form">
            <label>Fee (RM)<input type="number" name="fee" min="0.01" step="0.01" value="<?=school_h((float)$form['fee']>0?$form['fee']:'')?>"></label>
            <label>Cashless payment QR (optional, JPG, PNG or WebP; max 5 MB)<input type="file" name="eventPaymentQR" accept="image/jpeg,image/png,image/webp"><?php if(!empty($form['eventPaymentQR'])):?><img class="teacher-payment-qr-preview" src="<?=school_h($form['eventPaymentQR'])?>" alt="Current payment QR code"><?php endif;?></label>
            <label>Bank name<input name="bankName" maxlength="100" value="<?=school_h($form['bankName']??'')?>" placeholder="Example: Maybank"></label>
            <label>Account holder name<input name="bankAccountName" maxlength="150" value="<?=school_h($form['bankAccountName']??'')?>" placeholder="Name shown on the bank account"></label>
            <label>Bank account number<input name="bankAccountNumber" maxlength="50" value="<?=school_h($form['bankAccountNumber']??'')?>" placeholder="Account number"></label></div></div>
            <label>Event banner (JPG, PNG or WebP; max 5 MB)<input type="file" name="eventPoster" accept="image/jpeg,image/png,image/webp"><?php if(!empty($form['eventPoster'])):?><img class="teacher-event-poster-preview" src="<?=school_h($form['eventPoster'])?>" alt="Current event banner"><?php endif;?></label>
            <div class="teacher-form-wide"><div class="teacher-panel-heading"><div><h3>Committee tasks</h3><p>Add a task, then select one or more teachers to handle it.</p></div><button type="button" class="teacher-secondary-button" id="add-committee-task">Add task</button></div>
            <div id="committee-tasks" class="committee-task-list" aria-live="polite"><p class="teacher-empty">No committee tasks added. Use “Add task” to assign work to one or more Teachers.</p></div>
            <template id="committee-task-template"><fieldset class="committee-assignment-card"><legend>New task</legend><div class="teacher-form"><label>Task title<input name="tasks[__INDEX__][title]" maxlength="120" required></label><label>Task due date<input type="date" name="tasks[__INDEX__][dueDate]" min="<?=school_h(date('Y-m-d'))?>" value="<?=school_h($form['eventDate'])?>" required></label><label class="teacher-form-wide">Instructions<textarea name="tasks[__INDEX__][description]" rows="2" maxlength="2000"></textarea></label><fieldset class="committee-teacher-picks"><legend>Teachers for this task (choose any number)</legend><?php foreach($committeeTeachers as $teacher):$busyDates=array_values(array_filter(explode(',',(string)($teacher['busyDates']??''))));$atCapacity=(int)$teacher['assigned']>=3;$sameDay=in_array($form['eventDate'],$busyDates,true);$unavailable=$atCapacity||$sameDay;?><label class="<?=$unavailable?'unavailable':''?>"><input class="committee-teacher-option" type="checkbox" name="tasks[__INDEX__][teachers][]" value="<?=(int)$teacher['userID']?>" data-busy-dates="<?=school_h(json_encode($busyDates))?>" data-capacity="<?=$atCapacity?'1':'0'?>" <?=$unavailable?'disabled':''?>><span><?=school_h($teacher['name'])?> (<?=(int)$teacher['assigned']?>/3 events)<?=$atCapacity?' — task limit reached':($sameDay?' — already assigned that date':'')?></span></label><?php endforeach;?></fieldset></div><button type="button" class="teacher-danger-button teacher-danger-button-small remove-committee-task">Remove task</button></fieldset></template></div>
            <label class="teacher-form-wide">Description<textarea name="description" rows="4" placeholder="Brief event details"><?= school_h($form['description']) ?></textarea></label>
            <label class="teacher-form-wide">Private note for Principal / Admin<textarea name="principalNote" rows="3" placeholder="Internal event purpose or details for approval only"><?= school_h($form['principalNote'] ?? '') ?></textarea><small>This note is shown only to school administrators.</small></label>
            <div class="teacher-form-wide teacher-form-actions"><button class="teacher-primary-button" type="submit"><?= $form['eventID'] ? 'Save changes' : 'Save event' ?></button><?php if ($form['eventID']): ?><a class="teacher-secondary-button" href="teacher_events.php">Cancel</a><?php endif; ?></div>
            <?php if ($form['eventID'] && ($form['status']??'')!=='Pending' && strtotime($form['eventDate'].' '.($form['eventEndTime']?:$form['eventTime']))>=time()): ?><div class="teacher-form-wide teacher-event-delete-zone"><div><strong>Cancel this event</strong><small>Event, registration and attendance history are retained. Cancellation stops new registrations.</small></div><button class="teacher-danger-button" type="submit" name="cancel_event" value="<?= (int)$form['eventID'] ?>" formnovalidate onclick="return confirm('Cancel this event? Its records and history will remain saved.')">Cancel event</button></div><?php endif; ?>
        </form>
    </section>
    <section class="teacher-panel"><div class="teacher-panel-heading"><div><h2>My Events</h2><p>Events you organise and events where you have a committee task.</p></div></div>
        <nav class="teacher-event-filters" aria-label="Event filters"><?php foreach(["all"=>"All Events","upcoming"=>"Upcoming","ongoing"=>"Ongoing","past"=>"Past"] as $stageKey=>$stageLabel):?><a class="<?= $eventStage===$stageKey?"active":"" ?>" href="teacher_events.php?stage=<?=school_h($stageKey)?>"><?=school_h($stageLabel)?></a><?php endforeach;?></nav><?php if ($displayEvents): ?><label class="teacher-event-search">Search event name, date, location or status<input type="search" id="teacher-event-search" placeholder="Try an event name, date or venue"></label><div class="teacher-event-gallery" id="teacher-event-gallery">
        <?php foreach ($displayEvents as $event): $poster=$event['eventPoster']?:(((int)$event['eventID']%2)?'assets/sems-school-open-day.jpg':'assets/sems-school-activities.jpg'); $eventNotPast=strtotime($event['eventDate'].' '.($event['eventEndTime']?:$event['eventTime']))>=time(); ?><article class="teacher-event-card" data-teacher-event data-search="<?=school_h(strtolower($event['eventName'].' '.$event['eventDate'].' '.$event['location'].' '.$event['status']))?>"><img src="<?=school_h($poster)?>" alt="Event banner"><div class="teacher-event-card-body"><div class="teacher-event-card-top"><span class="teacher-status status-<?=school_h(str_replace(' ','-',strtolower($event['status'])))?>"><?=school_h($event['status'])?></span><span><?= (int)$event['registrations']?> registered</span></div><h3><?=school_h($event['eventName'])?></h3><p><?=school_h(date('d M Y',strtotime($event['eventDate'])))?> · <?=school_h(date('h:i A',strtotime($event['eventTime'])))?>–<?=school_h(date('h:i A',strtotime($event['eventEndTime']?:$event['eventTime'])))?> · <?=school_h($event['location'])?></p><p>Handled by <?=school_h($event['organizer']??'Account unavailable')?> · <?=school_h(school_public_person_id($pdo,(int)($event['createdBy']??0)))?>?> · RM <?=school_h(number_format((float)$event['fee'],2))?> · Capacity <?= (int)$event['capacity']?> · Verified collected RM <?=school_h(number_format((float)$event['collectedTotal'],2))?></p><?php $eventCommittee=$committeeByEvent[(int)$event['eventID']]??[];$doneTasks=count(array_filter($eventCommittee,fn($task)=>$task['taskStatus']==='Completed'));?><details class="teacher-event-committee"><summary>Committee tasks · <?=$doneTasks?> / <?=count($eventCommittee)?> completed</summary><?php if($eventCommittee):foreach($eventCommittee as $committeeTask):?><p><strong><?=school_h($committeeTask['taskTitle'])?></strong> · <?=school_h($committeeTask['name'])?> · <?=school_h($committeeTask['taskStatus'])?></p><?php endforeach;else:?><p>No committee tasks assigned.</p><?php endif;?></details><a class="teacher-secondary-button" href="event_detail.php?eventID=<?=(int)$event['eventID']?>">View event &amp; feedback</a><?php if(!empty($feedbackByEvent[(int)$event['eventID']])):?><details class="teacher-feedback-details"><summary>Student feedback (<?=count($feedbackByEvent[(int)$event['eventID']])?>)</summary><?php foreach($feedbackByEvent[(int)$event['eventID']] as $feedback):?><article><strong><?=school_h($feedback['studentName'])?> · <?= (int)$feedback['rating']?>/5</strong><p><?=nl2br(school_h($feedback['comments']))?></p><?php if(!empty($feedback['photoPath'])):?><a class="feedback-photo-open teacher-feedback-photo-open" data-proof-viewer data-proof-type="image" data-proof-title="Feedback photo" href="<?=school_h($feedback['photoPath'])?>"><span>View photo</span></a><?php endif;?></article><?php endforeach;?></details><?php endif;?><div class="teacher-event-actions"><?php if((int)$event['createdBy']===$userId&&$event['status']!=='Cancelled'):?><a class="teacher-action-edit" href="teacher_events.php?edit=<?=(int)$event['eventID']?>#event-form">Edit</a><?php endif;?><?php if((int)$event['createdBy']===$userId&&$event['status']!=='Cancelled'&&$event['status']!=='Pending'&&$eventNotPast):?><form method="post" onsubmit="return confirm('Cancel this event? Its history will remain saved.')"><input type="hidden" name="csrf_token" value="<?=school_h($csrf)?>"><button class="teacher-danger-button teacher-danger-button-small" type="submit" name="cancel_event" value="<?=(int)$event['eventID']?>">Cancel</button></form><?php endif;?></div></div></article><?php endforeach;?></div><p class="teacher-event-empty" id="teacher-event-empty" hidden>No events match your search.</p><script>document.getElementById('teacher-event-search').addEventListener('input',function(){var value=this.value.trim().toLowerCase(),visible=0;document.querySelectorAll('[data-teacher-event]').forEach(function(card){var show=card.dataset.search.includes(value);card.hidden=!show;if(show)visible++;});document.getElementById('teacher-event-empty').hidden=visible>0;});</script>
        <?php else: ?><div class="teacher-empty"><strong>No events in this view</strong><p>Complete the form above to create an event.</p></div><?php endif; ?>
    </section><footer class="teacher-footer"><strong>SMK Tengku Lela Segara · SchoolSphere</strong><span><a href="tel:+6096182227">09-618 2227</a> · <a href="mailto:tea4014@moe.edu.my">tea4014@moe.edu.my</a></span><span><a href="https://smktls.blogspot.com/">Official website</a> · <a href="https://www.facebook.com/195530113841302">Facebook</a></span><span>&copy; <?= date('Y') ?></span></footer>
</div></main></div><script>
document.addEventListener('DOMContentLoaded',function(){
 const feeType=document.getElementById('event-fee-type'),paymentFields=document.getElementById('event-payment-fields');
 if(feeType&&paymentFields){function togglePayment(){paymentFields.hidden=feeType.value!=='paid';const fee=paymentFields.querySelector('[name=fee]');if(fee)fee.required=feeType.value==='paid';}feeType.addEventListener('change',togglePayment);togglePayment();}
 const list=document.getElementById('committee-tasks'),template=document.getElementById('committee-task-template'),add=document.getElementById('add-committee-task');let next=0;
 const eventDate=document.querySelector('[name=eventDate]');function syncTeacherOptions(){const date=eventDate?eventDate.value:'';document.querySelectorAll('.committee-teacher-option').forEach(function(input){let busy=[];try{busy=JSON.parse(input.dataset.busyDates||'[]')}catch(e){}const conflict=busy.includes(date),capacity=input.dataset.capacity==='1';input.disabled=conflict||capacity;const label=input.closest('label');if(label){label.classList.toggle('unavailable',input.disabled);const text=label.querySelector('span');if(text&&!capacity)text.dataset.conflict=conflict?'1':'0';}});}if(eventDate)eventDate.addEventListener('change',syncTeacherOptions);function addTask(){const wrap=document.createElement('div');wrap.innerHTML=template.innerHTML.replaceAll('__INDEX__',String(next++));const task=wrap.firstElementChild;list.appendChild(task);task.querySelector('.remove-committee-task').addEventListener('click',()=>task.remove());const due=task.querySelector('input[type=date]');if(due&&eventDate){due.min=eventDate.value;due.value=eventDate.value;}syncTeacherOptions(); }
  if(list&&template&&add){add.addEventListener('click',function(){const empty=list.querySelector('.teacher-empty');if(empty)empty.remove();addTask();});}
});</script></body></html>




























