<?php
session_start();
require_once __DIR__.'/config/db.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__.'/config/report_schema.php';
$role=$_SESSION['user']['role']??'';
$uid=(int)($_SESSION['user']['userID']??0);
if(!in_array($role,['Principal','Admin'],true)){http_response_code(403);exit('Access denied.');}
try{sems_ensure_event_reports($pdo);}catch(Throwable $e){http_response_code(500);exit('The report database is not ready.');}
$reportID=(int)($_GET['reportID']??0);
$sql="SELECT er.*,e.eventName,e.eventDate,e.eventTime,e.eventEndTime,e.location,u.name AS teacherName,u.email AS teacherEmail,u.userID AS teacherID,p.name AS principalName FROM event_reports er LEFT JOIN events e ON e.eventID=er.eventID JOIN users u ON u.userID=er.submittedBy LEFT JOIN users p ON p.userID=er.principalID WHERE er.reportID=?";
$params=[$reportID];if($role==='Principal'){$sql.=' AND er.principalID=?';$params[]=$uid;}
$q=$pdo->prepare($sql);$q->execute($params);$report=$q->fetch(PDO::FETCH_ASSOC);
if(!$report){http_response_code(404);exit('Report not found.');}
$eventDate=$report['eventDate']?:$report['periodStart'];
$photo=(string)($report['reportPhoto']??'');
if(!preg_match('#^uploads/event_reports/[a-f0-9]{48}\.(jpg|png|webp)$#i',$photo))$photo='';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=school_h($report['title'])?> | SEMS Event Report</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#edf1eb;color:#243a2d;font-family:Arial,Helvetica,sans-serif}
.toolbar{position:sticky;top:0;z-index:2;display:flex;justify-content:space-between;align-items:center;gap:14px;padding:14px max(24px,calc((100vw - 880px)/2));background:#203d2e;color:#fff}
.toolbar a,.toolbar button{color:#fff;font:700 13px Arial,sans-serif}
.toolbar a{text-decoration:none}
.toolbar button{padding:11px 17px;background:#4c805d;border:0;border-radius:8px;cursor:pointer}
.report{width:min(880px,calc(100% - 32px));margin:26px auto;padding:48px 58px;background:#fff;box-shadow:0 12px 36px #203d2e18}
.letterhead{display:grid;grid-template-columns:66px 1fr auto;align-items:center;gap:15px;padding-bottom:20px;border-bottom:2px solid #315c40}
.brandmark{display:grid;place-items:center;width:58px;height:58px;color:#fff;background:#28523a;border-radius:15px;font-size:17px;font-weight:800;letter-spacing:.7px}
.school-name{color:#263f30;font-size:14px;font-weight:750;line-height:1.45}
.school-name small{display:block;margin-top:4px;color:#728174;font-size:10px;font-weight:500;letter-spacing:.5px}
.document-kind{padding:7px 10px;color:#315940;background:#edf4e9;border-radius:20px;font-size:9px;font-weight:800;letter-spacing:1px}
.title-block{padding:30px 0 22px}
.eyebrow{margin:0 0 8px;color:#68846d;font-size:10px;font-weight:800;letter-spacing:1.8px;text-transform:uppercase}
h1{margin:0;color:#203d2e;font-size:27px;line-height:1.2;letter-spacing:-.3px}
.event-subtitle{margin:9px 0 0;color:#5e6e60;font-size:14px;line-height:1.5}
.metadata{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:4px 0 25px}
.metadata div{min-height:66px;padding:12px 14px;background:#f4f7f2;border:1px solid #e5ebe1;border-radius:9px}
.metadata small,.metadata strong{display:block}
.metadata small{margin-bottom:7px;color:#738174;font-size:9px;font-weight:700;letter-spacing:.7px;text-transform:uppercase}
.metadata strong{color:#314b37;font-size:11px;line-height:1.45;overflow-wrap:anywhere}
.section{margin:20px 0;padding:0 0 18px;border-bottom:1px solid #e7ece5;break-inside:avoid}
.section h2{display:flex;align-items:center;gap:10px;margin:0 0 10px;color:#294b34;font-size:14px}
.section-number{display:grid;place-items:center;flex:0 0 24px;height:24px;color:#315940;background:#edf4e9;border-radius:50%;font-size:10px}
.section p{margin:0;color:#4c5c4f;font-size:11px;line-height:1.75;white-space:pre-wrap}
.metrics{padding:15px 17px;background:#f5f8f3;border:1px solid #e4ebe1;border-left:4px solid #6f986e;border-radius:8px;color:#435545;font-size:11px;line-height:1.7;white-space:pre-wrap}
figure{margin:18px 0 24px;padding:14px;border:1px solid #e5ebe2;border-radius:10px;break-inside:avoid}
figure img{display:block;width:100%;max-height:360px;object-fit:contain}
figcaption{margin-top:10px;color:#58695a;font-size:10px;line-height:1.6}
.review-note{padding:14px 16px;background:#f4f7f2;border-radius:8px}
.report-footer{display:flex;justify-content:space-between;gap:14px;margin-top:28px;padding-top:12px;border-top:1px solid #dfe6dc;color:#879286;font-size:9px}
.print-tip{margin:0 auto 30px;width:min(880px,calc(100% - 32px));color:#6c7a6e;text-align:center;font-size:11px}
@page{size:A4;margin:15mm 16mm}
@media print{
body{background:#fff}
.toolbar,.print-tip{display:none!important}
.report{width:auto;margin:0;padding:0;box-shadow:none}
.letterhead{padding-bottom:13px}
.title-block{padding:20px 0 15px}
h1{font-size:24px}
.metadata{gap:8px;margin-bottom:18px}
.metadata div{min-height:58px;padding:10px}
.section{margin:15px 0;padding-bottom:13px}
.report-footer{margin-top:20px}
}
@media(max-width:620px){
.report{padding:25px 20px}
.letterhead{grid-template-columns:52px 1fr}.brandmark{width:48px;height:48px}.document-kind{grid-column:2;justify-self:start}
.metadata{grid-template-columns:1fr 1fr}.report-footer{flex-direction:column}
}
</style>
</head>
<body>
<div class="toolbar no-print"><a href="admin_reports.php">← Back to Teacher Reports</a><button type="button" onclick="window.print()">Download as PDF</button></div>
<main class="report">
<header class="letterhead"><div class="brandmark">SEMS</div><div class="school-name">SMK Tengku Lela Segara<small>School Event Management System</small></div><span class="document-kind">EVENT REPORT</span></header>
<section class="title-block"><p class="eyebrow">Teacher submission</p><h1><?=school_h($report['title'])?></h1><p class="event-subtitle"><?=school_h($report['eventName']??'Event record unavailable')?><?php if(!empty($report['location'])):?> · <?=school_h($report['location'])?><?php endif;?></p></section>
<section class="metadata"><div><small>Event date</small><strong><?=school_h(date('d F Y',strtotime($eventDate)))?></strong></div><div><small>Prepared by</small><strong><?=school_h($report['teacherName'])?></strong></div><div><small>Submitted</small><strong><?=school_h(date('d F Y, h:i A',strtotime($report['submittedAt'])))?></strong></div><div><small>Report status</small><strong><?=school_h($report['status'])?></strong></div><div><small>Event time</small><strong><?=!empty($report['eventTime'])?school_h(date('h:i A',strtotime($report['eventTime'])).(!empty($report['eventEndTime'])?' – '.date('h:i A',strtotime($report['eventEndTime'])):'')):'Not recorded'?></strong></div><div><small>Reviewed by</small><strong><?=school_h($report['principalName']??'Principal')?></strong></div></section>
<section class="section"><h2><span class="section-number">1</span>Objectives and planned activities</h2><p><?=school_h($report['objectives'])?></p></section>
<section class="section"><h2><span class="section-number">2</span>Outcomes and achievements</h2><p><?=school_h($report['outcomes'])?></p></section>
<section class="section"><h2><span class="section-number">3</span>SEMS event metrics</h2><div class="metrics"><?=school_h($report['attendanceSummary'])?></div></section>
<section class="section"><h2><span class="section-number">4</span>Issues and follow-up</h2><p><?=school_h($report['issues']?:'None reported.')?></p></section>
<section class="section"><h2><span class="section-number">5</span>Recommendations</h2><p><?=school_h($report['recommendations']?:'None provided.')?></p></section>
<?php if($photo):?><figure><img src="<?=school_h($photo)?>" alt="Event report photograph"><?php if(!empty($report['reportPhotoDescription'])):?><figcaption><strong>Photo description:</strong> <?=school_h($report['reportPhotoDescription'])?></figcaption><?php endif;?></figure><?php endif;?>
<?php if(!empty($report['principalRemarks'])):?><section class="section review-note"><h2>Principal review notes</h2><p><?=school_h($report['principalRemarks'])?></p></section><?php endif;?>
<footer class="report-footer"><span>SEMS · School Event Management System</span><span>Prepared by <?=school_h($report['teacherName'])?></span><span>Report ID <?= (int)$report['reportID']?></span></footer>
</main>
<p class="print-tip">Use “Save as PDF” in the print dialog to download this formatted report.</p>
</body></html>



