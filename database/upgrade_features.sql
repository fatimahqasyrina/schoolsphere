USE sems;

-- Add fields for complete profiles and the Form 6 attendance photo workflow.
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='username')=0, 'ALTER TABLE users ADD COLUMN username VARCHAR(50) NULL AFTER name', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE users SET username=name WHERE username IS NULL OR username='';
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='profilePhoto')=0, 'ALTER TABLE users ADD COLUMN profilePhoto VARCHAR(255) NULL', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='phoneNo')=0, 'ALTER TABLE students ADD COLUMN phoneNo VARCHAR(20) NULL', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='address')=0, 'ALTER TABLE students ADD COLUMN address TEXT NULL', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='formLevel')=0, 'ALTER TABLE students ADD COLUMN formLevel VARCHAR(10) NULL', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='emergencyContactName')=0, 'ALTER TABLE students ADD COLUMN emergencyContactName VARCHAR(100) NULL', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='emergencyContactPhone')=0, 'ALTER TABLE students ADD COLUMN emergencyContactPhone VARCHAR(20) NULL', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='emergencyRelationship')=0, 'ALTER TABLE students ADD COLUMN emergencyRelationship VARCHAR(50) NULL', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='teachers' AND COLUMN_NAME='phoneNo')=0, 'ALTER TABLE teachers ADD COLUMN phoneNo VARCHAR(20) NULL', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='attendance' AND COLUMN_NAME='proofFile')=0, 'ALTER TABLE attendance ADD COLUMN proofFile VARCHAR(255) NULL', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='attendance' AND COLUMN_NAME='proofStatus')=0, 'ALTER TABLE attendance ADD COLUMN proofStatus ENUM(''Not Required'',''Pending Verification'',''Verified'',''Rejected'') NOT NULL DEFAULT ''Not Required''', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='attendance' AND COLUMN_NAME='checkInMethod')=0, 'ALTER TABLE attendance ADD COLUMN checkInMethod ENUM(''Student Photo'',''Teacher Door'') NOT NULL DEFAULT ''Teacher Door''', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='attendance' AND COLUMN_NAME='checkedInBy')=0, 'ALTER TABLE attendance ADD COLUMN checkedInBy INT NULL', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='attendance' AND COLUMN_NAME='checkedInAt')=0, 'ALTER TABLE attendance ADD COLUMN checkedInAt DATETIME NULL', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- Committee task assignments are saved as tentative until the event is approved.
CREATE TABLE IF NOT EXISTS event_tasks (
 taskID INT AUTO_INCREMENT PRIMARY KEY,
 eventID INT NOT NULL,
 assignedTo INT NOT NULL,
 assignedBy INT NOT NULL,
 taskTitle VARCHAR(120) NOT NULL,
 taskDescription TEXT NULL,
 dueDate DATE NULL,
 taskStatus ENUM('Tentative','Active','Completed','Cancelled') NOT NULL DEFAULT 'Tentative',
 completionPhoto VARCHAR(255) NULL,
 createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_event_tasks_assigned (assignedTo,taskStatus,dueDate),
 CONSTRAINT fk_event_tasks_event FOREIGN KEY (eventID) REFERENCES events(eventID) ON DELETE CASCADE,
 CONSTRAINT fk_event_tasks_assigned FOREIGN KEY (assignedTo) REFERENCES users(userID) ON DELETE CASCADE,
 CONSTRAINT fk_event_tasks_assigner FOREIGN KEY (assignedBy) REFERENCES users(userID) ON DELETE CASCADE
);

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_tasks' AND COLUMN_NAME='completionPhoto')=0, 'ALTER TABLE event_tasks ADD COLUMN completionPhoto VARCHAR(255) NULL AFTER taskStatus', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='deletedAt')=0, 'ALTER TABLE users ADD COLUMN deletedAt DATETIME NULL AFTER status', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- Optional evidence submitted with an event-specific financial assistance request.
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='financial_assistance' AND COLUMN_NAME='supportDocument')=0, 'ALTER TABLE financial_assistance ADD COLUMN supportDocument VARCHAR(255) NULL AFTER reason', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS notifications (
 notificationID INT AUTO_INCREMENT PRIMARY KEY,
 userID INT NOT NULL,
 title VARCHAR(150) NOT NULL,
 message VARCHAR(500) NOT NULL,
 link VARCHAR(255) NULL,
 isRead TINYINT(1) NOT NULL DEFAULT 0,
 createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_notifications_user_read (userID,isRead,createdAt),
 CONSTRAINT fk_notifications_user FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE
);
