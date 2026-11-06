USE sems;

-- Preserve event, registration and attendance history when an organizer cancels.
ALTER TABLE events MODIFY status ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending';
ALTER TABLE registrations MODIFY status ENUM('Draft','Pending Verification','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Draft';

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='principalNote')=0, 'ALTER TABLE events ADD COLUMN principalNote TEXT NULL AFTER description', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='eventPoster')=0, 'ALTER TABLE events ADD COLUMN eventPoster VARCHAR(255) NULL AFTER principalNote', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feedback' AND COLUMN_NAME='photoPath')=0, 'ALTER TABLE feedback ADD COLUMN photoPath VARCHAR(255) NULL AFTER comments', 'SELECT 1'); PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
