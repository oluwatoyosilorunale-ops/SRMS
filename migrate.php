<?php
// migrate.php - Add image column to students and teachers tables
require 'config.php';

try {
    // Add image column to students table if it doesn't exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM students LIKE 'image'");
    $stmt->execute();
    
    if($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN image VARCHAR(255) DEFAULT NULL");
        echo "✓ Added 'image' column to students table<br>";
    } else {
        echo "✓ 'image' column already exists in students table<br>";
    }
    
    // Add password column to students table if it doesn't exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM students LIKE 'password'");
    $stmt->execute();
    if($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN password VARCHAR(255) DEFAULT NULL");
        echo "✓ Added 'password' column to students table<br>";
        // populate existing student passwords with hash of student_id
        $rows = $pdo->query("SELECT id, student_id FROM students")->fetchAll();
        foreach($rows as $r){
            $hash = password_hash($r['student_id'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE students SET password = ? WHERE id = ?")->execute([$hash, $r['id']]);
        }
        echo "✓ Populated default passwords for existing students<br>";
    } else {
        echo "✓ 'password' column already exists in students table<br>";
    }
    
    // Add image column to teachers table if it doesn't exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM teachers LIKE 'image'");
    $stmt->execute();
    
    if($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN image VARCHAR(255) DEFAULT NULL");
        echo "✓ Added 'image' column to teachers table<br>";
    } else {
        echo "✓ 'image' column already exists in teachers table<br>";
    }

    // Add password column to teachers table if it doesn't exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM teachers LIKE 'password'");
    $stmt->execute();
    if($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN password VARCHAR(255) DEFAULT NULL");
        echo "✓ Added 'password' column to teachers table<br>";
        // set default hashed passwords for existing records
        $rows = $pdo->query("SELECT id, teacher_id FROM teachers")->fetchAll();
        foreach($rows as $r){
            $hash = password_hash($r['teacher_id'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE teachers SET password = ? WHERE id = ?")->execute([$hash, $r['id']]);
        }
        echo "✓ Populated default passwords for existing teachers<br>";
    } else {
        echo "✓ 'password' column already exists in teachers table<br>";
    }

    // Add result approval fields to results table if they don't exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM results LIKE 'uploaded_by_teacher_id'");
    $stmt->execute();
    if($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE results ADD COLUMN uploaded_by_teacher_id VARCHAR(50) DEFAULT NULL");
        $pdo->exec("ALTER TABLE results ADD COLUMN uploaded_by_teacher_name VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE results ADD COLUMN status VARCHAR(20) DEFAULT 'approved'");
        $pdo->exec("ALTER TABLE results ADD COLUMN status_comment TEXT DEFAULT NULL");
        $pdo->exec("ALTER TABLE results ADD COLUMN reviewed_by VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE results ADD COLUMN reviewed_at DATETIME DEFAULT NULL");
        echo "✓ Added result approval columns to results table<br>";

        // Set existing results as approved
        $pdo->exec("UPDATE results SET status = 'approved'");
        echo "✓ Existing results set as approved<br>";
    } else {
        echo "✓ Result approval columns already exist in results table<br>";
    }
    
    // Add image column to users table if it doesn't exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'image'");
    $stmt->execute();
    
    if($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN image VARCHAR(255) DEFAULT NULL");
        echo "✓ Added 'image' column to users table<br>";
    } else {
        echo "✓ 'image' column already exists in users table<br>";
    }
    
    // Add total and grade columns to results table if they don't exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM results LIKE 'total'");
    $stmt->execute();
    if($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE results ADD COLUMN total INT DEFAULT NULL");
        echo "✓ Added 'total' column to results table<br>";
    } else {
        echo "✓ 'total' column already exists in results table<br>";
    }

    $stmt = $pdo->prepare("SHOW COLUMNS FROM results LIKE 'grade'");
    $stmt->execute();
    if($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE results ADD COLUMN grade VARCHAR(2) DEFAULT NULL");
        echo "✓ Added 'grade' column to results table<br>";
        // Backfill total and grade for existing rows
        $rows = $pdo->query("SELECT id, test1, test2, test3, exam FROM results")->fetchAll(PDO::FETCH_ASSOC);
        $update = $pdo->prepare("UPDATE results SET total = ?, grade = ? WHERE id = ?");
        foreach($rows as $r){
            $t1 = isset($r['test1']) ? (int)$r['test1'] : 0;
            $t2 = isset($r['test2']) ? (int)$r['test2'] : 0;
            $t3 = isset($r['test3']) ? (int)$r['test3'] : 0;
            $exam = isset($r['exam']) ? (int)$r['exam'] : 0;
            $total = $t1 + $t2 + $t3 + $exam;
            if($total >= 70) $grade = 'A';
            elseif($total >= 60) $grade = 'B';
            elseif($total >= 50) $grade = 'C';
            elseif($total >= 45) $grade = 'D';
            else $grade = 'F';
            $update->execute([$total, $grade, $r['id']]);
        }
        echo "✓ Backfilled 'total' and 'grade' for existing results<br>";
    } else {
        echo "✓ 'grade' column already exists in results table<br>";
    }

    // add session column to results table for academic session tracking
    $stmt = $pdo->prepare("SHOW COLUMNS FROM results LIKE 'session'");
    $stmt->execute();
    if($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE results ADD COLUMN session VARCHAR(50) DEFAULT ''");
        echo "✓ Added 'session' column to results table<br>";
    } else {
        echo "✓ 'session' column already exists in results table<br>";
    }

    // create academic_sessions table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS academic_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Ensured academic_sessions table exists<br>";

    // if there are no sessions, insert a sensible default (current year / next year)
    $cnt = $pdo->query("SELECT COUNT(*) FROM academic_sessions")->fetchColumn();
    if($cnt == 0){
        $defaultName = date('Y') . '/' . (date('Y')+1);
        $stmt = $pdo->prepare("INSERT INTO academic_sessions (name) VALUES (?)");
        $stmt->execute([$defaultName]);
        $newId = $pdo->lastInsertId();
        echo "✓ Inserted default academic session: $defaultName<br>";
        // set as current session in settings
        $pdo->prepare("REPLACE INTO settings (`key`,`value`) VALUES ('current_session_id', ?) ")->execute([$newId]);
        echo "✓ Set current_session_id to default session<br>";
    }

    // create settings table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        `key` VARCHAR(50) PRIMARY KEY,
        `value` VARCHAR(255)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Ensured settings table exists<br>";

    // ensure a default current_term is set
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'current_term'");
    $stmt->execute();
    if(!$stmt->fetch()){
        $pdo->prepare("REPLACE INTO settings (`key`,`value`) VALUES ('current_term','First Term')")->execute();
        echo "✓ Set default current_term = First Term<br>";
    }

    // Convert results table columns to DECIMAL for decimal mark support
    $stmt = $pdo->prepare("SHOW COLUMNS FROM results LIKE 'test1'");
    $stmt->execute();
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    if($col && strpos($col['Type'], 'DECIMAL') === false && strpos($col['Type'], 'FLOAT') === false){
        try {
            $pdo->exec("ALTER TABLE results MODIFY test1 DECIMAL(5,2) DEFAULT 0");
            $pdo->exec("ALTER TABLE results MODIFY test2 DECIMAL(5,2) DEFAULT 0");
            $pdo->exec("ALTER TABLE results MODIFY test3 DECIMAL(5,2) DEFAULT 0");
            $pdo->exec("ALTER TABLE results MODIFY exam DECIMAL(5,2) DEFAULT 0");
            echo "✓ Converted result columns to DECIMAL(5,2) for decimal support<br>";
        } catch (Exception $e) {
            echo "✗ Error converting result columns: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "✓ Result columns already support decimal values<br>";
    }

    // Add is_class_teacher and managed_class columns to teachers table for class management
    $stmt = $pdo->prepare("SHOW COLUMNS FROM teachers LIKE 'is_class_teacher'");
    $stmt->execute();
    if($stmt->rowCount() == 0) {
        try {
            $pdo->exec("ALTER TABLE teachers ADD COLUMN is_class_teacher TINYINT(1) DEFAULT 0");
            $pdo->exec("ALTER TABLE teachers ADD COLUMN managed_class VARCHAR(50) DEFAULT NULL");
            echo "✓ Added 'is_class_teacher' and 'managed_class' columns to teachers table<br>";
        } catch (Exception $e) {
            echo "✗ Error adding class teacher columns: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "✓ Class teacher columns already exist in teachers table<br>";
    }

    // Add arm column to students table if it doesn't exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM students LIKE 'arm'");
    $stmt->execute();
    if($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN arm VARCHAR(10) DEFAULT 'A'");
        echo "✓ Added 'arm' column to students table<br>";
    } else {
        echo "✓ 'arm' column already exists in students table<br>";
    }

    // Add arm column to subjects table if it doesn't exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM subjects LIKE 'arm'");
    $stmt->execute();
    if($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE subjects ADD COLUMN arm VARCHAR(10) DEFAULT 'A'");
        echo "✓ Added 'arm' column to subjects table<br>";
    } else {
        echo "✓ 'arm' column already exists in subjects table<br>";
    }

    // Update existing class values to separate class and arm
    // For students table
    $students = $pdo->query("SELECT id, class FROM students WHERE class IS NOT NULL AND class != ''")->fetchAll();
    foreach($students as $student) {
        $classArm = $student['class'];
        // Extract class and arm from format like "JSS1 A" or "JSS1A"
        if (preg_match('/^([A-Z]+[0-9]+)\s*([A-Z])$/', $classArm, $matches)) {
            $class = $matches[1];
            $arm = $matches[2];
        } elseif (preg_match('/^([A-Z]+[0-9]+)([A-Z])$/', $classArm, $matches)) {
            $class = $matches[1];
            $arm = $matches[2];
        } else {
            // If no arm found, assume 'A'
            $class = $classArm;
            $arm = 'A';
        }
        $pdo->prepare("UPDATE students SET class = ?, arm = ? WHERE id = ?")->execute([$class, $arm, $student['id']]);
    }
    echo "✓ Updated existing student class/arm data<br>";

    // For subjects table
    $subjects = $pdo->query("SELECT id, class FROM subjects WHERE class IS NOT NULL AND class != ''")->fetchAll();
    foreach($subjects as $subject) {
        $classArm = $subject['class'];
        // Extract class and arm from format like "JSS1 A" or "JSS1A"
        if (preg_match('/^([A-Z]+[0-9]+)\s*([A-Z])$/', $classArm, $matches)) {
            $class = $matches[1];
            $arm = $matches[2];
        } elseif (preg_match('/^([A-Z]+[0-9]+)([A-Z])$/', $classArm, $matches)) {
            $class = $matches[1];
            $arm = $matches[2];
        } else {
            // If no arm found, assume 'A'
            $class = $classArm;
            $arm = 'A';
        }
        $pdo->prepare("UPDATE subjects SET class = ?, arm = ? WHERE id = ?")->execute([$class, $arm, $subject['id']]);
    }
    echo "✓ Updated existing subject class/arm data<br>";
    
    echo "<br><strong style='color:green;'>Migration completed successfully!</strong><br>";
    echo "You can now delete this migration file.<br>";
    
} catch (Exception $e) {
    echo "<strong style='color:red;'>Error: " . $e->getMessage() . "</strong>";
}
?>
<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f9ff; }
</style>
