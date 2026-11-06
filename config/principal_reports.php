<?php
function sems_principal_report_rows(PDO $pdo, string $type, int $eventID = 0, string $month = ''): array
{
    if ($type === 'event' && $eventID > 0) { $where = ' WHERE e.eventID = ?'; $params = [$eventID]; }
    elseif ($type === 'month' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) { $where = " WHERE DATE_FORMAT(e.eventDate, '%Y-%m') = ?"; $params = [$month]; }
    else { return []; }
    $sql = "SELECT e.eventID,e.eventName,e.eventDate,e.eventTime,e.eventEndTime,e.registrationDeadline,e.location,e.capacity,e.status,
      organizer.name AS organizerName,organizer.userID AS organizerID,
      (SELECT COUNT(*) FROM registrations r WHERE r.eventID=e.eventID) AS registrationsTotal,
      (SELECT COUNT(*) FROM registrations r WHERE r.eventID=e.eventID AND r.status='Approved') AS registrationsApproved,
      (SELECT COUNT(*) FROM registrations r WHERE r.eventID=e.eventID AND r.status='Pending Verification') AS registrationsPending,
      (SELECT COUNT(*) FROM registrations r WHERE r.eventID=e.eventID AND r.status='Rejected') AS registrationsRejected,
      (SELECT COUNT(*) FROM registrations r WHERE r.eventID=e.eventID AND r.status='Cancelled') AS registrationsCancelled,
      (SELECT COUNT(*) FROM attendance a JOIN registrations r ON r.eventID=a.eventID AND r.studentID=a.studentID WHERE a.eventID=e.eventID AND r.status='Approved' AND a.status='Present') AS attendancePresent,
      (SELECT COUNT(*) FROM attendance a JOIN registrations r ON r.eventID=a.eventID AND r.studentID=a.studentID WHERE a.eventID=e.eventID AND r.status='Approved' AND a.status='Absent') AS attendanceAbsent,
      (SELECT COUNT(*) FROM payments p WHERE p.eventID=e.eventID AND p.status='Verified') AS paymentsVerifiedCount,
      (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.eventID=e.eventID AND p.status='Verified') AS paymentsVerifiedAmount,
      (SELECT COUNT(*) FROM payments p WHERE p.eventID=e.eventID AND p.status='Pending') AS paymentsPendingCount,
      (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.eventID=e.eventID AND p.status='Pending') AS paymentsPendingAmount,
      (SELECT COUNT(*) FROM financial_assistance fa WHERE fa.eventID=e.eventID AND fa.status='Pending') AS assistancePending,
      (SELECT COUNT(*) FROM financial_assistance fa WHERE fa.eventID=e.eventID AND fa.status='Approved') AS assistanceApproved,
      (SELECT COUNT(*) FROM financial_assistance fa WHERE fa.eventID=e.eventID AND fa.status='Rejected') AS assistanceRejected,
      (SELECT COALESCE(SUM(fa.amount),0) FROM financial_assistance fa WHERE fa.eventID=e.eventID AND fa.status='Approved') AS assistanceApprovedAmount,
      (SELECT COUNT(*) FROM feedback f WHERE f.eventID=e.eventID) AS feedbackCount,
      (SELECT COALESCE(ROUND(AVG(f.rating),2),0) FROM feedback f WHERE f.eventID=e.eventID) AS feedbackAverage,
      (SELECT GROUP_CONCAT(CONCAT(tu.name,' (',t.taskTitle,')') ORDER BY tu.name SEPARATOR '; ') FROM event_tasks t JOIN users tu ON tu.userID=t.assignedTo WHERE t.eventID=e.eventID AND t.taskStatus<>'Cancelled') AS committeeTasks
      FROM events e LEFT JOIN users organizer ON organizer.userID=e.createdBy".$where." ORDER BY e.eventDate,e.eventTime,e.eventName";
    $query=$pdo->prepare($sql);$query->execute($params);$rows=$query->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row){$approved=(int)$row['registrationsApproved'];$row['attendanceRate']=$approved?round(((int)$row['attendancePresent']/$approved)*100,1):0;$row['capacityUsed']=((int)$row['capacity'])?round(($approved/(int)$row['capacity'])*100,1):0;}
    unset($row);return $rows;
}
function sems_principal_report_totals(array $rows): array
{
    $sumKeys=['registrationsTotal','registrationsApproved','registrationsPending','registrationsRejected','registrationsCancelled','attendancePresent','attendanceAbsent','paymentsVerifiedCount','paymentsVerifiedAmount','paymentsPendingCount','paymentsPendingAmount','assistancePending','assistanceApproved','assistanceRejected','assistanceApprovedAmount','feedbackCount'];
    $total=array_fill_keys($sumKeys,0);$ratingSum=0.0;
    foreach($rows as $row){foreach($sumKeys as $key)$total[$key]+=(float)$row[$key];$ratingSum+=(float)$row['feedbackAverage']*(int)$row['feedbackCount'];}
    $total['feedbackAverage']=$total['feedbackCount']?round($ratingSum/$total['feedbackCount'],2):0;
    $total['attendanceRate']=$total['registrationsApproved']?round($total['attendancePresent']/$total['registrationsApproved']*100,1):0;
    $total['capacityUsed']=0;return $total;
}
