<?php
// install.php - run once, then delete or protect
require 'config.php';

try {
    // create tables
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE,
        password VARCHAR(255),
        role ENUM('admin','teacher','student') DEFAULT 'admin',
        display_name VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) UNIQUE,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        gender ENUM('M','F','O'),
        class VARCHAR(50),
        dob DATE,
        image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id VARCHAR(50) UNIQUE,
        full_name VARCHAR(255),
        email VARCHAR(150),
        pan_id VARCHAR(100),
        image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255),
        class VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50),
        subject_id INT,
        term VARCHAR(50),
        ca INT DEFAULT 0,
        exam INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
    );
    ");

    // Add image column to existing tables if they don't have it
    try {
        $pdo->exec("ALTER TABLE students ADD COLUMN image VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {
        // Column might already exist, ignore error
    }

    try {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN image VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {
        // Column might already exist, ignore error
    }

    // create admin user if not exists
    $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $check->execute(['admin']);
    if($check->fetchColumn() == 0) {
        $pw = password_hash('admin123', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO users (username,password,role,display_name) VALUES (?, ?, 'admin', 'Administrator')");
        $insert->execute(['admin',$pw]);
        echo "Admin user created: username=admin password=admin123<br>";
    } else {
        echo "Admin user exists already.<br>";
    }

    echo "Installation complete. Remove or protect install.php after use.";
} catch (Exception $e) {
    echo "Error creating DB structure: " . $e->getMessage();
}