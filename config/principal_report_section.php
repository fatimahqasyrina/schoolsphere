<?php
require_once __DIR__.'/principal_reports.php';
$reportType=(string)($_GET['reportType']??'');$reportEvent=(int)($_GET['eventID']??0);$reportMonth=(string)($_GET['month']??'');
if(!in_array($reportType,['event','month'],true))$reportType='';
if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$reportMonth))$reportMonth='';
$reportEvents=$pdo->query('SELECT eventID,eventName,eventDate FROM events ORDER BY eventDate DESC,eventName')->fetchAll(PDO::FETCH_ASSOC);
$reportRows=$reportType?sems_principal_report_rows($pdo,$reportType,$reportEvent,$reportMonth):[];$reportTotals=sems_principal_report_totals($reportRows);
$reportIsValid=$reportType==='event'?$reportEvent>0:($reportType==='month'&&$reportMonth!=='');
$reportDownload=$reportIsValid?'admin_report_download.php?'.http_build_query(['reportType'=>$reportType,'eventID'=>$reportEvent,'month'=>$reportMonth]):'';
?>
<section class="portal-card principal-report-generator" id="generated-reports">
 <div class="principal-report-heading"><div><span class="portal-eyebrow">PRINCIPAL REPORTS</span><h2>Generate a report</h2><p>Choose one event for its full report, or choose a month to collect every event in that month.</p></div></div>
 <form method="get" action="admin_reports.php" class="portal-form principal-report-filters" id="principal-report-filter">
  <label>Report type<select name="reportType" id="principal-report-type" required><option value="">Choose report type</option><option value="event" <?=$reportType==='event'?'selected':''?>>Individual event</option><option value="month" <?=$reportType==='month'?'selected':''?>>Whole month</option></select></label>
  <label id="principal-event-filter">Event<select name="eventID" id="principal-report-event"><option value="">Choose an event</option><?php foreach($reportEvents as $event):?><option value="<?=(int)$event['eventID']?>" <?=(int)$event['eventID']===$reportEvent?'selected':''?>><?=sems_e($event['eventName'])?> · <?=sems_e(date('d M Y',strtotime($event['eventDate'])))?></option><?php endforeach;?></select></label>
  <label id="principal-month-filter">Month<input type="month" name="month" id="principal-report-month" value="<?=sems_e($reportMonth)?>"></label>
  <button class="small-button" type="submit">Generate report</button>
 </form>
 <script>document.addEventListener('DOMContentLoaded',function(){const type=document.getElementById('principal-report-type'),eventBox=document.getElementById('principal-event-filter'),monthBox=document.getElementById('principal-month-filter'),eventInput=document.getElementById('principal-report-event'),monthInput=document.getElementById('principal-report-month');function update(){const individual=type.value==='event',monthly=type.value==='month';eventBox.hidden=!individual;monthBox.hidden=!monthly;eventInput.disabled=!individual;eventInput.required=individual;monthInput.disabled=!monthly;monthInput.required=monthly;}type.addEventListener('change',update);update();});</script>
 <?php if($reportType&&$reportIsValid&&$reportRows):?>
 <div class="principal-report-result">
  <div class="principal-report-summary"><article><span>Events in report</span><strong><?=count($reportRows)?></strong></article><article><span>Approved registrations</span><strong><?=(int)$reportTotals['registrationsApproved']?></strong></article><article><span>Present</span><strong><?=(int)$reportTotals['attendancePresent']?> / <?=(int)$reportTotals['registrationsApproved']?></strong></article><article><span>Verified payments</span><strong>RM <?=number_format((float)$reportTotals['paymentsVerifiedAmount'],2)?></strong></article><article><span>Assistance pending</span><strong><?=(int)$reportTotals['assistancePending']?></strong></article></div>
  <div class="principal-report-download"><div><strong><?=$reportType==='event'?'Individual event report':'Monthly event report'?></strong><span><?=$reportType==='event'?'Detailed record for the selected event.':count($reportRows).' event(s) included for '.sems_e($reportMonth).'.'?></span></div><a class="small-button" href="<?=sems_e($reportDownload)?>" download>Download CSV report</a></div>
  <?php $displayRows=[];foreach($reportRows as $row){$displayRows[]=[sems_e($row['eventName']),sems_e(date('d M Y',strtotime($row['eventDate']))),sems_e($row['status']),sems_e($row['organizerName']??'—').' · '.sems_e(school_public_person_id($pdo,(int)($row['organizerID']??0))),(int)$row['registrationsApproved'].' approved · '.(int)$row['registrationsPending'].' pending',(int)$row['attendancePresent'].' present · '.(int)$row['attendanceAbsent'].' absent','RM '.number_format((float)$row['paymentsVerifiedAmount'],2),(int)$row['assistancePending'].' pending · '.(int)$row['assistanceApproved'].' approved',(int)$row['feedbackCount'].' · '.number_format((float)$row['feedbackAverage'],2).'/5'];}portal_table(['Event','Date','Status','Handled by','Registrations','Attendance','Verified payments','Financial assistance','Feedback'],$displayRows);?>
 </div>
 <?php elseif($reportType&&$reportIsValid):?><p class="report-empty-state">No event data was found for the selected report.</p><?php elseif($reportType):?><p class="report-empty-state">Choose a valid event or month, then generate the report.</p><?php endif;?>
</section>
<?php
