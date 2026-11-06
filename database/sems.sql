
CREATE DATABASE IF NOT EXISTS sems;
USE sems;

CREATE TABLE IF NOT EXISTS users (
    userID INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Student','Teacher','Principal','Admin') NOT NULL,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    deletedAt DATETIME NULL,
    principal_guard TINYINT GENERATED ALWAYS AS (CASE WHEN role='Principal' THEN 1 ELSE NULL END) STORED,
    UNIQUE KEY uq_users_single_principal (principal_guard)
);

CREATE TABLE IF NOT EXISTS students (
    studentID VARCHAR(20) PRIMARY KEY,
    userID INT NOT NULL UNIQUE,
    class VARCHAR(20),
    FOREIGN KEY (userID) REFERENCES users(userID)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS teachers (
    staffID VARCHAR(20) PRIMARY KEY,
    userID INT NOT NULL UNIQUE,
    position VARCHAR(50),
    FOREIGN KEY (userID) REFERENCES users(userID)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS principals (
    adminID VARCHAR(20) PRIMARY KEY,
    userID INT NOT NULL,
    principalName VARCHAR(100) NOT NULL,
    startDate DATE NOT NULL,
    endDate DATE NULL,
    INDEX idx_principals_userID (userID),
    FOREIGN KEY (userID) REFERENCES users(userID)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS events (
    eventID INT AUTO_INCREMENT PRIMARY KEY,
    eventName VARCHAR(100) NOT NULL,
    description TEXT,
    principalNote TEXT NULL,
    eventPoster VARCHAR(255) NULL,
    eventPaymentQR VARCHAR(255) NULL,
    bankName VARCHAR(100) NULL,
    bankAccountName VARCHAR(150) NULL,
    bankAccountNumber VARCHAR(50) NULL,
    eventDate DATE NOT NULL,
    eventTime TIME NOT NULL DEFAULT '09:00:00',
    eventEndTime TIME NULL,
    registrationDeadline DATETIME NULL,
    location VARCHAR(100) NOT NULL,
    capacity INT NOT NULL DEFAULT 30,
    fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('Pending','Approved','Rejected','Cancelled')
        NOT NULL DEFAULT 'Pending',
    createdBy INT,
    FOREIGN KEY (createdBy) REFERENCES users(userID)
        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS registrations (
    registrationID INT AUTO_INCREMENT PRIMARY KEY,
    studentID VARCHAR(20) NOT NULL,
    eventID INT NOT NULL,
    registrationDate DATE NOT NULL,
    status ENUM('Draft','Pending Verification','Approved','Rejected','Cancelled')
        NOT NULL DEFAULT 'Draft',
    UNIQUE KEY unique_registration (studentID, eventID),
    FOREIGN KEY (studentID) REFERENCES students(studentID)
        ON DELETE CASCADE,
    FOREIGN KEY (eventID) REFERENCES events(eventID)
        ON DELETE CASCADE
);

-- Demo accounts. Password for all accounts: password
INSERT IGNORE INTO users
(name, email, password, role, status)
VALUES
('Demo Principal', 'principal@sems.test',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',
 'Principal', 'Active'),
('Demo Teacher', 'teacher@sems.test',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',
 'Teacher', 'Active'),
('Demo Student', 'student@sems.test',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',
 'Student', 'Active');

INSERT IGNORE INTO principals (adminID, userID, principalName, startDate, endDate)
SELECT 'ADM001', userID, name, CURDATE(), NULL FROM users
WHERE email = 'principal@sems.test';

INSERT IGNORE INTO teachers (staffID, userID, position)
SELECT 'TCH001', userID, 'Event Organiser' FROM users
WHERE email = 'teacher@sems.test';

INSERT IGNORE INTO students (studentID, userID, class)
SELECT 'STD001', userID, 'DCS 6A' FROM users
WHERE email = 'student@sems.test';

INSERT INTO events
(eventName, description, eventDate, location, capacity, fee, status, createdBy)
SELECT 'School Sports Day', 'Annual school sports event.',
       '2026-11-20', 'School Field', 200, 0.00, 'Approved', userID
FROM users
WHERE email = 'teacher@sems.test'
AND NOT EXISTS (
    SELECT 1 FROM events WHERE eventName = 'School Sports Day'
);

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
