<?php
function school_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function school_ensure_username(PDO $pdo): void
{
    $exists = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='username'")->fetchColumn();

    if (!(int) $exists) {
        $pdo->exec('ALTER TABLE users ADD COLUMN username VARCHAR(50) NULL AFTER name');
        $pdo->exec("UPDATE users SET username=name WHERE username IS NULL OR username=''");
    }
}

function school_public_person_id(PDO $pdo, int $userID): string
{
    static $cache = [];
    if ($userID < 1) return '';
    if (!array_key_exists($userID, $cache)) {
        $q = $pdo->prepare("SELECT u.role,t.staffID,s.studentID FROM users u LEFT JOIN teachers t ON t.userID=u.userID LEFT JOIN students s ON s.userID=u.userID WHERE u.userID=? LIMIT 1");
        $q->execute([$userID]);
        $person = $q->fetch(PDO::FETCH_ASSOC) ?: [];
        $cache[$userID] = ($person['role']??'') === 'Teacher' && !empty($person['staffID'])
            ? 'Teacher ID '.$person['staffID']
            : (($person['role']??'') === 'Student' && !empty($person['studentID']) ? 'Student ID '.$person['studentID'] : '');
    }
    return $cache[$userID];
}
