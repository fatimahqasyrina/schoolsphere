USE sems;

-- Keep the user role enum compatible with the application.
ALTER TABLE users MODIFY role ENUM('Student','Teacher','Principal','Admin') NOT NULL;

CREATE TABLE IF NOT EXISTS attendance (
    attendanceID INT AUTO_INCREMENT PRIMARY KEY,
    studentID VARCHAR(20) NOT NULL,
    eventID INT NOT NULL,
    attendanceDate DATE NOT NULL,
    status ENUM('Present','Absent') NOT NULL DEFAULT 'Present',
    UNIQUE KEY unique_event_attendance (studentID,eventID),
    FOREIGN KEY (studentID) REFERENCES students(studentID) ON DELETE CASCADE,
    FOREIGN KEY (eventID) REFERENCES events(eventID) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS financial_assistance (
    assistanceID INT AUTO_INCREMENT PRIMARY KEY,
    userID INT NOT NULL,
    eventID INT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason TEXT NOT NULL,
    supportDocument VARCHAR(255) NULL,
    status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    submittedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE
);

SET @has_aid_event = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='financial_assistance' AND COLUMN_NAME='eventID');
SET @ddl = IF(@has_aid_event=0, 'ALTER TABLE financial_assistance ADD COLUMN eventID INT NULL AFTER userID', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_aid_document = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='financial_assistance' AND COLUMN_NAME='supportDocument');
SET @ddl = IF(@has_aid_document=0, 'ALTER TABLE financial_assistance ADD COLUMN supportDocument VARCHAR(255) NULL AFTER reason', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS feedback (
    feedbackID INT AUTO_INCREMENT PRIMARY KEY,
    userID INT NOT NULL,
    eventID INT NULL,
    rating TINYINT NOT NULL,
    comments TEXT NOT NULL,
    photoPath VARCHAR(255) NULL,
    submittedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE,
    FOREIGN KEY (eventID) REFERENCES events(eventID) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS payments (
    paymentID INT AUTO_INCREMENT PRIMARY KEY,
    userID INT NOT NULL,
    eventID INT NULL,
    amount DECIMAL(10,2) NOT NULL,
    method VARCHAR(50) NOT NULL,
    reference VARCHAR(100) NOT NULL,
    proofPath VARCHAR(255) NULL,
    status ENUM('Pending','Verified','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
    submittedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE,
    FOREIGN KEY (eventID) REFERENCES events(eventID) ON DELETE SET NULL
);

-- Existing payment tables gain the proof reference only once.
SET @has_proof_path = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payments' AND COLUMN_NAME='proofPath');
SET @ddl = IF(@has_proof_path=0, 'ALTER TABLE payments ADD COLUMN proofPath VARCHAR(255) NULL AFTER reference', 'SELECT 1');
PREPARE migration_stmt FROM @ddl; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

-- Principal history columns are conditionally added for older installations.
SET @has_principal_name = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='principals' AND COLUMN_NAME='principalName');
SET @ddl = IF(@has_principal_name=0, 'ALTER TABLE principals ADD COLUMN principalName VARCHAR(100) NULL', 'SELECT 1');
PREPARE migration_stmt FROM @ddl; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @has_start_date = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='principals' AND COLUMN_NAME='startDate');
SET @ddl = IF(@has_start_date=0, 'ALTER TABLE principals ADD COLUMN startDate DATE NULL', 'SELECT 1');
PREPARE migration_stmt FROM @ddl; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @has_end_date = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='principals' AND COLUMN_NAME='endDate');
SET @ddl = IF(@has_end_date=0, 'ALTER TABLE principals ADD COLUMN endDate DATE NULL', 'SELECT 1');
PREPARE migration_stmt FROM @ddl; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

UPDATE principals p JOIN users u ON u.userID=p.userID
SET p.principalName=COALESCE(p.principalName,u.name), p.startDate=COALESCE(p.startDate,CURDATE());
ALTER TABLE principals MODIFY principalName VARCHAR(100) NOT NULL, MODIFY startDate DATE NOT NULL;

-- Drop the legacy FK if present, then install the intended cascading FK once.
SET @old_fk = (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='principals' AND COLUMN_NAME='userID' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1);
SET @ddl = IF(@old_fk IS NULL, 'SELECT 1', CONCAT('ALTER TABLE principals DROP FOREIGN KEY `', @old_fk, '`'));
PREPARE migration_stmt FROM @ddl; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
-- Older installations may have a unique index named userID; history allows multiple appointments.
SET @has_old_user_index = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='principals' AND INDEX_NAME='userID');
SET @ddl = IF(@has_old_user_index>0, 'ALTER TABLE principals DROP INDEX userID', 'SELECT 1');
PREPARE migration_stmt FROM @ddl; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @has_principal_index = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='principals' AND INDEX_NAME='idx_principals_userID');
SET @ddl = IF(@has_principal_index=0, 'ALTER TABLE principals ADD INDEX idx_principals_userID (userID)', 'SELECT 1');
PREPARE migration_stmt FROM @ddl; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @has_principal_fk = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='principals' AND COLUMN_NAME='userID' AND REFERENCED_TABLE_NAME='users');
SET @ddl = IF(@has_principal_fk=0, 'ALTER TABLE principals ADD CONSTRAINT principals_ibfk_1 FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE', 'SELECT 1');
PREPARE migration_stmt FROM @ddl; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

-- A generated unique value enforces at most one current Principal account.
SET @has_principal_guard = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='principal_guard');
SET @ddl = IF(@has_principal_guard=0, 'ALTER TABLE users ADD COLUMN principal_guard TINYINT GENERATED ALWAYS AS (CASE WHEN role=''Principal'' THEN 1 ELSE NULL END) STORED', 'SELECT 1');
PREPARE migration_stmt FROM @ddl; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @has_principal_guard_index = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='uq_users_single_principal');
SET @ddl = IF(@has_principal_guard_index=0, 'ALTER TABLE users ADD UNIQUE KEY uq_users_single_principal (principal_guard)', 'SELECT 1');
PREPARE migration_stmt FROM @ddl; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

-- Event reports submitted by Teachers for Principal review.
CREATE TABLE IF NOT EXISTS event_reports (
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
    status ENUM('Submitted','Reviewed','Needs Revision') NOT NULL DEFAULT 'Submitted',
    principalRemarks TEXT NULL,
    submittedAt DATETIME NOT NULL,
    reviewedAt DATETIME NULL,
    INDEX idx_event_reports_principal (principalID,status,submittedAt),
    INDEX idx_event_reports_teacher (submittedBy,submittedAt),
    CONSTRAINT fk_event_reports_event FOREIGN KEY (eventID) REFERENCES events(eventID) ON DELETE SET NULL,
    CONSTRAINT fk_event_reports_teacher FOREIGN KEY (submittedBy) REFERENCES users(userID) ON DELETE CASCADE,
    CONSTRAINT fk_event_reports_principal FOREIGN KEY (principalID) REFERENCES users(userID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
