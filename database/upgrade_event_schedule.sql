USE sems;

-- Add event start times and registration closing times to existing installations.
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='eventTime')=0, 'ALTER TABLE events ADD COLUMN eventTime TIME NOT NULL DEFAULT ''09:00:00'' AFTER eventDate', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='eventPaymentQR')=0, 'ALTER TABLE events ADD COLUMN eventPaymentQR VARCHAR(255) NULL AFTER eventPoster', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='eventEndTime')=0, 'ALTER TABLE events ADD COLUMN eventEndTime TIME NULL AFTER eventTime', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='registrationDeadline')=0, 'ALTER TABLE events ADD COLUMN registrationDeadline DATETIME NULL AFTER eventTime', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
