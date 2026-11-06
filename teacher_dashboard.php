<?php
session_start();
require_once 'config/db.php';
require_once __DIR__ . '/config/helpers.php';
try { if (!(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='eventEndTime'")->fetchColumn()) $pdo->exec('ALTER TABLE events ADD COLUMN eventEndTime TIME NULL AFTER eventTime'); } catch (Throwable $schemaError) { }

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Teacher') {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$userId = (int) $user['userID'];
$firstName = explode(' ', trim($user['name']))[0] ?? $user['name'];
$stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'registrations' => 0];
$events = [];
$schoolEvents = [];
$dbError = '';
$successMessage = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total,
        COALESCE(SUM(status = 'Pending'), 0) AS pending,
        COALESCE(SUM(status = 'Approved'), 0) AS approved
        FROM events WHERE createdBy = ?");
    $stmt->execute([$userId]);
    $stats = array_merge($stats, $stmt->fetch() ?: []);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations r
        INNER JOIN events e ON e.eventID = r.eventID WHERE e.createdBy = ?");
    $stmt->execute([$userId]);
    $stats['registrations'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT e.eventID, e.eventName, e.eventDate, e.eventTime, e.eventEndTime, e.eventPoster, e.location,
        e.status, e.fee, e.capacity, COUNT(CASE WHEN r.status NOT IN ('Rejected','Cancelled') THEN r.registrationID END) AS registrations
        FROM events e LEFT JOIN registrations r ON r.eventID = e.eventID
        WHERE e.createdBy = ?
        GROUP BY e.eventID, e.eventName, e.eventDate, e.eventTime, e.eventEndTime, e.eventPoster, e.location, e.status, e.fee, e.capacity
        ORDER BY e.eventDate ASC LIMIT 5");
    $stmt->execute([$userId]);
    $events = $stmt->fetchAll();
    $stmt = $pdo->prepare("SELECT e.eventID,e.eventName,e.eventDate,e.eventTime,e.eventEndTime,e.eventPoster,e.location,e.fee,e.registrationDeadline,u.userID AS organizerID,u.name AS organizer,u.email AS organizerEmail FROM events e LEFT JOIN users u ON u.userID=e.createdBy WHERE e.status='Approved' ORDER BY e.eventDate DESC,e.eventTime DESC");
    $stmt->execute();
    $schoolEvents = $stmt->fetchAll();
} catch (PDOException $e) {
    $dbError = 'Information could not be loaded. Check the database connection.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Homepage | SchoolSphere</title>
    <link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/teacher.css?v=5"><link rel="stylesheet" href="assets/ui-polish.css?v=28"><link rel="stylesheet" href="assets/portal.css?v=12"><script src="assets/table-search.js" defer></script>
<link rel="stylesheet" href="assets/school-sphere.css?v=18"></head>
<body class="teacher-dashboard"><div class="teacher-layout">
    <?php require __DIR__ . '/teacher_nav.php'; ?>
    <main class="teacher-main">
        <header class="teacher-topbar"><div><span class="teacher-eyebrow">TEACHER PORTAL</span><h1>Homepage</h1></div>
            <div class="teacher-account"><span class="teacher-avatar"><?= school_h(strtoupper(substr($firstName, 0, 1))) ?></span><span><strong><?= school_h($user['name']) ?></strong><small>Teacher</small></span></div>
        </header>
        <div class="teacher-content">
            <?php if ($successMessage !== ''): ?><div class="portal-notice success"><?= school_h($successMessage) ?></div><?php endif; ?>
            <section class="teacher-welcome"><div class="teacher-welcome-copy"><span class="teacher-eyebrow">SCHOOL EVENTS · <?=school_h(date('l, d F Y'))?></span><h2>Hello, <?= school_h($firstName) ?>.</h2>
                <p>Your event workspace is ready. Review registrations, follow approvals and keep upcoming school activities moving.</p><div class="teacher-dashboard-actions"><a class="teacher-primary-button" href="teacher_events.php#event-form">Create an event</a><a class="teacher-secondary-button" href="teacher_attendance.php">Take attendance</a><a class="teacher-secondary-button" href="teacher_reports.php">Prepare a report</a></div></div><img class="teacher-welcome-image" src="assets/sems-school-community-banner.jpg" alt="Students and teachers taking part in a school event">
            </section>
            <section class="teacher-stats teacher-dashboard-stats">
                <article class="teacher-stat"><span>My events</span><strong><?= (int) $stats['total'] ?></strong><small>Total events created</small></article>
                <article class="teacher-stat"><span>Awaiting approval</span><strong><?= (int) $stats['pending'] ?></strong><small>Events under review</small></article>
                <article class="teacher-stat"><span>Approved events</span><strong><?= (int) $stats['approved'] ?></strong><small>Open for student registration</small></article>
                <article class="teacher-stat"><span>Registrations</span><strong><?= (int) $stats['registrations'] ?></strong><small>Across your events</small></article>
            </section>
            <section class="teacher-panel teacher-dashboard-events"><div class="teacher-panel-heading"><div><h2>School Events</h2><p>All approved school events, including past activities.</p></div><a href="teacher_events.php?stage=all">Browse every event</a></div>
                <?php if ($schoolEvents): ?><div class="teacher-dashboard-event-grid"><?php foreach ($schoolEvents as $event): $poster=$event['eventPoster']?:(((int)$event['eventID']%2)?'assets/sems-school-open-day.jpg':'assets/sems-school-activities.jpg'); ?><article class="teacher-dashboard-event-card"><img src="<?=school_h($poster)?>" alt="<?=school_h($event['eventName'])?>"><div><span class="teacher-dashboard-card-label">SCHOOL EVENT</span><h3><?=school_h($event['eventName'])?></h3><p><?=school_h(date('d M Y',strtotime($event['eventDate'])))?> · <?=school_h(date('h:i A',strtotime($event['eventTime'])))?>–<?=school_h(date('h:i A',strtotime($event['eventEndTime']?:$event['eventTime'])))?></p><p><?=school_h($event['location'])?></p><small>Handled by <?=school_h($event['organizer']??'School')?><?=($label=school_public_person_id($pdo,(int)($event['organizerID']??0)))!==''?' · '.school_h($label):''?>?></small><a href="event_detail.php?eventID=<?=(int)$event['eventID']?>">View event and feedback</a></div></article><?php endforeach;?></div><?php else: ?><div class="teacher-empty"><strong>No school events yet</strong><p>Approved school events will appear here.</p></div><?php endif; ?>
            </section>
            <?php if ($dbError !== ''): ?><div class="teacher-alert"><?= school_h($dbError) ?></div><?php endif; ?>
            
        </div>
        <footer class="teacher-footer"><strong>SMK Tengku Lela Segara · SchoolSphere</strong><span><a href="tel:+6096182227">09-618 2227</a> · <a href="mailto:tea4014@moe.edu.my">tea4014@moe.edu.my</a></span><span><a href="https://smktls.blogspot.com/">Official website</a> · <a href="https://www.facebook.com/195530113841302">Facebook</a></span><span>&copy; <?= date('Y') ?></span></footer>
    </main>
</div></body></html>






















