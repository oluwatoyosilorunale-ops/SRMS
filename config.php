<?php
// config.php - DB connection and helpers (require this file everywhere)
session_start();

// change these to match your DB
define('DB_HOST','localhost');
define('DB_NAME','school_result_db');
define('DB_USER','root');
define('DB_PASS',''); // set your DB password

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Ensure essential settings tables exist so helpers are safe to call
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        `key` VARCHAR(50) PRIMARY KEY,
        `value` VARCHAR(255)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS academic_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_name VARCHAR(50) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // non-fatal here; if creation fails later pages will surface the error
}

// Simple auth helper
function is_logged_in(){
    return isset($_SESSION['user']);
}
function require_login(){
    if(!is_logged_in()){
        header('Location: index.php');
        exit;
    }
}
function flash($key = null, $message = null){
    if ($message === null) {
        if(isset($_SESSION['flash'][$key])){
            $m = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $m;
        }
        return null;
    } else {
        $_SESSION['flash'][$key] = $message;
    }
}

// settings helpers ------------------------------------------------
// simple key/value store; used for tracking current session/term etc.
function get_setting($key){
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : null;
}
function set_setting($key, $value){
    global $pdo;
    $stmt = $pdo->prepare("REPLACE INTO settings (`key`,`value`) VALUES (?, ?)");
    $stmt->execute([$key, $value]);
}

// convenience getters for the current academic session and term
function get_current_term(){
    $term = get_setting('current_term');
    return $term ?: 'First Term';
}
function get_current_session(){
    $id = get_setting('current_session_id');
    if($id){
        global $pdo;
        $stmt = $pdo->prepare("SELECT name FROM academic_sessions WHERE id = ?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r ? $r['name'] : null;
    }
    return null;
}

// Get next auto-incremented student ID
function get_next_student_id(){
    global $pdo;
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_id REGEXP '^ST[0-9]+$' ORDER BY CAST(SUBSTRING(student_id, 3) AS UNSIGNED) DESC LIMIT 1");
    $stmt->execute();
    $lastRecord = $stmt->fetch();
    
    if($lastRecord){
        $lastNum = (int)substr($lastRecord['student_id'], 2);
        $nextNum = $lastNum + 1;
    } else {
        $nextNum = 1;
    }
    return 'ST' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

// Get next auto-incremented teacher ID
function get_next_teacher_id(){
    global $pdo;
    $stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE teacher_id REGEXP '^TC[0-9]+$' ORDER BY CAST(SUBSTRING(teacher_id, 3) AS UNSIGNED) DESC LIMIT 1");
    $stmt->execute();
    $lastRecord = $stmt->fetch();
    
    if($lastRecord){
        $lastNum = (int)substr($lastRecord['teacher_id'], 2);
        $nextNum = $lastNum + 1;
    } else {
        $nextNum = 1;
    }
    return 'TC' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

// Promote/Demote student
function promote_student($id){
    global $pdo;
    $classMap = [
        'Nursery' => 'Pre-Primary',
        'Pre-Primary' => 'Primary 1',
        'Primary 1' => 'Primary 2',
        'Primary 2' => 'Primary 3',
        'Primary 3' => 'Primary 4',
        'Primary 4' => 'Primary 5',
        'Primary 5' => 'Primary 6',
        'Primary 6' => 'JSS1',
        'JSS1' => 'JSS2',
        'JSS2' => 'JSS3',
        'JSS3' => 'SS1',
        'SS1' => 'SS2',
        'SS2' => 'SS3',
    ];
    
    $stmt = $pdo->prepare("SELECT class FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    
    if($student && isset($classMap[$student['class']])){
        $nextClass = $classMap[$student['class']];
        $pdo->prepare("UPDATE students SET class = ? WHERE id = ?")->execute([$nextClass, $id]);
        return $nextClass;
    }
    return false;
}

// Demote student
function demote_student($id){
    global $pdo;
    $reverseMap = [
        'Pre-Primary' => 'Nursery',
        'Primary 1' => 'Pre-Primary',
        'Primary 2' => 'Primary 1',
        'Primary 3' => 'Primary 2',
        'Primary 4' => 'Primary 3',
        'Primary 5' => 'Primary 4',
        'Primary 6' => 'Primary 5',
        'JSS1' => 'Primary 6',
        'JSS2' => 'JSS1',
        'JSS3' => 'JSS2',
        'SS1' => 'JSS3',
        'SS2' => 'SS1',
        'SS3' => 'SS2',
    ];
    
    $stmt = $pdo->prepare("SELECT class FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    
    if($student && isset($reverseMap[$student['class']])){
        $prevClass = $reverseMap[$student['class']];
        $pdo->prepare("UPDATE students SET class = ? WHERE id = ?")->execute([$prevClass, $id]);
        return $prevClass;
    }
    return false;
}

?>