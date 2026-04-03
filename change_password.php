<?php
require 'config.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student'){
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user']['id'];
$message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $current_password = $_POST['current_password'];
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Fetch current password
    $stmt = $pdo->prepare("SELECT password FROM students WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_hash = $stmt->fetchColumn();

    if(!password_verify($current_password, $current_hash)){
        $message = "Current password is incorrect.";
    } elseif(empty($new_password) || strlen($new_password) < 4){
        $message = "New password must be at least 4 characters.";
    } elseif($new_password !== $confirm_password){
        $message = "New passwords do not match.";
    } else {
        // Update password
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE students SET password = ? WHERE id = ?");
        $update->execute([$new_hash, $user_id]);
        $message = "Password changed successfully.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password — School RMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-layout">
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Change Password</div>
            <div class="top-actions">
                <span class="welcome">Welcome, <strong><?=htmlspecialchars($_SESSION['user']['display_name'])?></strong></span>
                <a class="btn-ghost" href="logout.php"><ion-icon name="log-out-outline"></ion-icon> Logout</a>
            </div>
        </header>

        <section class="card">
            <?php if($message): ?>
                <div class="toast <?= strpos($message, 'success') !== false ? 'success' : 'error' ?>"><?=$message?></div>
            <?php endif; ?>

            <p>Note: Your username remains your Student ID. Only the password is changed.</p>

            <form method="POST">
                <div class="form-grid">
                    <label>Current Password (Student ID)
                        <input type="password" name="current_password" required>
                    </label>
                    
                    <label>New Password
                        <input type="password" name="new_password" required>
                    </label>
                    
                    <label>Confirm New Password
                        <input type="password" name="confirm_password" required>
                    </label>
                </div>
                
                <div style="display:flex; gap:8px; margin-top:12px;">
                    <button class="btn" type="submit">Change Password</button>
                    <a href="student_info.php" class="btn outline">Cancel</a>
                </div>
            </form>
        </section>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>