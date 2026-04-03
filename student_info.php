<?php
require 'config.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student'){
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user']['id'];

// Fetch student information
$query = "SELECT * FROM students WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Information — School RMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-layout">
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Student Information</div>
            <div class="top-actions">
                <span class="welcome">Welcome, <strong><?=htmlspecialchars($_SESSION['user']['display_name'])?></strong></span>
                <a class="btn-ghost" href="logout.php"><ion-icon name="log-out-outline"></ion-icon> Logout</a>
            </div>
        </header>

        <section class="card">
            <h2><?php echo htmlspecialchars($student['first_name'] . " " . $student['last_name']); ?></h2>
            
            <div class="info-grid">
                <div class="info-item">
                    <label>Student ID:</label>
                    <p><?php echo htmlspecialchars($student['student_id']); ?></p>
                </div>
                
                <div class="info-item">
                    <label>Class:</label>
                    <p><?php echo htmlspecialchars($student['class'] . ' ' . $student['arm']); ?></p>
                </div>
                
                <div class="info-item">
                    <label>Gender:</label>
                    <p><?php echo htmlspecialchars($student['gender']); ?></p>
                </div>
                
                <div class="info-item">
                    <label>Date of Birth:</label>
                    <p><?php echo htmlspecialchars($student['dob']); ?></p>
                </div>
            </div>

            <div class="actions">
                <a href="student_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <!-- <a href="profile.php" class="btn">Edit Profile</a> -->
                <!-- <a href="change_password.php" class="btn outline">Change Password</a> -->
            </div>
        </section>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>