<?php
function sems_ensure_event_reports(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS event_reports (
        reportID INT AUTO_INCREMENT PRIMARY KEY,
        eventID INT NULL,
        submittedBy INT NOT NULL,
        principalID INT NOT NULL,
        title VARCHAR(180) NOT NULL,
        periodStart DATE NOT NULL,
        periodEnd DATE NOT NULL,
        objectives TEXT NOT NULL,
        outcomes TEXT NOT NULL,
        attendanceSummary TEXT NOT NULL,
        issues TEXT NULL,
        recommendations TEXT NULL,
        status ENUM('Submitted','Reviewed','Needs Revision','Resubmit') NOT NULL DEFAULT 'Submitted',
        principalRemarks TEXT NULL,
        submittedAt DATETIME NOT NULL,
        reviewedAt DATETIME NULL,
        INDEX idx_event_reports_principal (principalID,status,submittedAt),
        INDEX idx_event_reports_teacher (submittedBy,submittedAt),
        CONSTRAINT fk_event_reports_event FOREIGN KEY (eventID) REFERENCES events(eventID) ON DELETE SET NULL,
        CONSTRAINT fk_event_reports_teacher FOREIGN KEY (submittedBy) REFERENCES users(userID) ON DELETE CASCADE,
        CONSTRAINT fk_event_reports_principal FOREIGN KEY (principalID) REFERENCES users(userID) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach (['reportPhoto'=>'VARCHAR(255) NULL AFTER recommendations','reportPhotoDescription'=>'VARCHAR(1000) NULL AFTER reportPhoto'] as $column=>$definition) {
        $check=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_reports' AND COLUMN_NAME=?");$check->execute([$column]);
        if (!(int)$check->fetchColumn()) $pdo->exec("ALTER TABLE event_reports ADD COLUMN `$column` $definition");
    }
    $statusType=$pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_reports' AND COLUMN_NAME='status'")->fetchColumn();
    if ($statusType && strpos($statusType,'Resubmit')===false) $pdo->exec("ALTER TABLE event_reports MODIFY status ENUM('Submitted','Reviewed','Needs Revision','Resubmit') NOT NULL DEFAULT 'Submitted'");
    $pdo->exec("UPDATE event_reports SET status='Resubmit' WHERE status='Needs Revision'");
}





