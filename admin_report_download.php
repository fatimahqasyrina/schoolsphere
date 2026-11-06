<?php
session_start();
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/config/principal_reports.php';
require_once __DIR__.'/config/helpers.php';
$role=$_SESSION['user']['role']??'';
if(!in_array($role,['Principal','Admin'],true)){http_response_code(403);exit('Access denied.');}
$type=(string)($_GET['reportType']??'');$eventID=(int)($_GET['eventID']??0);$month=(string)($_GET['month']??'');
if(!in_array($type,['event','month'],true)||($type==='event'&&$eventID<1)||($type==='month'&&!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month))){http_response_code(400);exit('Choose one event or a valid month first.');}
$rows=sems_principal_report_rows($pdo,$type,$eventID,$month);
if(!$rows){http_response_code(404);exit('No event data was found for this report.');}
function csv_value($value): string { $value=(string)$value;return preg_match('/^[\s]*[=+@\-]/u',$value)?"'".$value:$value; }
$scope=$type==='event'?'Individual event '.$eventID:'Monthly report '.$month;
$filename=$type==='event'?'SEMS-event-'.$eventID.'-report.csv':'SEMS-month-'.$month.'-report.csv';
header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$filename.'"');header('X-Content-Type-Options: nosniff');
$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");
$headers=['Report scope','Event ID','Event','Date','Start time','End time','Registration closes','Status','Location','Capacity','Organiser','Organiser teacher/student ID','Registrations total','Approved registrations','Pending registrations','Rejected registrations','Cancelled registrations','Present','Absent','Attendance rate %','Capacity used %','Verified payments count','Verified payments RM','Pending payments count','Pending payments RM','Assistance pending','Assistance approved','Assistance rejected','Approved assistance RM','Feedback count','Average feedback rating','Committee teachers and tasks'];
fputcsv($out,$headers);
foreach($rows as $r){fputcsv($out,array_map('csv_value',[$scope,$r['eventID'],$r['eventName'],$r['eventDate'],$r['eventTime'],$r['eventEndTime'],$r['registrationDeadline'],$r['status'],$r['location'],$r['capacity'],$r['organizerName'],school_public_person_id($pdo,(int)$r['organizerID']),$r['registrationsTotal'],$r['registrationsApproved'],$r['registrationsPending'],$r['registrationsRejected'],$r['registrationsCancelled'],$r['attendancePresent'],$r['attendanceAbsent'],$r['attendanceRate'],$r['capacityUsed'],$r['paymentsVerifiedCount'],number_format((float)$r['paymentsVerifiedAmount'],2,'.',''),$r['paymentsPendingCount'],number_format((float)$r['paymentsPendingAmount'],2,'.',''),$r['assistancePending'],$r['assistanceApproved'],$r['assistanceRejected'],number_format((float)$r['assistanceApprovedAmount'],2,'.',''),$r['feedbackCount'],$r['feedbackAverage'],$r['committeeTasks']]));}
if($type==='month'){$t=sems_principal_report_totals($rows);$totals=array_fill(0,count($headers),'');$totals[0]=$scope;$totals[2]='MONTH TOTAL';foreach([12=>'registrationsTotal',13=>'registrationsApproved',14=>'registrationsPending',15=>'registrationsRejected',16=>'registrationsCancelled',17=>'attendancePresent',18=>'attendanceAbsent',19=>'attendanceRate',22=>'paymentsVerifiedAmount',23=>'paymentsPendingCount',24=>'paymentsPendingAmount',25=>'assistancePending',26=>'assistanceApproved',27=>'assistanceRejected',28=>'assistanceApprovedAmount',29=>'feedbackCount',30=>'feedbackAverage'] as $i=>$key)$totals[$i]=$t[$key];$totals[21]=$t['paymentsVerifiedCount'];fputcsv($out,array_map('csv_value',$totals));}
fclose($out);
