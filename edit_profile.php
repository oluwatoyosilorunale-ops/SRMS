<?php
require 'config.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student'){
    header("Location: index.php");
    exit();
}

$classes = $pdo->query("SELECT class_name FROM classes ORDER BY class_name")->fetchAll(PDO::FETCH_COLUMN);

$user_id = $_SESSION['user']['id'];

$message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);

    if(empty($first_name) || empty($last_name)){
        $message = "First and last names are required.";
    } else {
        $update = $pdo->prepare("UPDATE students SET first_name = ?, last_name = ? WHERE id = ?");
        $update->execute([$first_name, $last_name, $user_id]);
        $message = "Profile updated successfully.";
        // Update session display_name
        $_SESSION['user']['display_name'] = $first_name . ' ' . $last_name;
        $_SESSION['display'] = $_SESSION['user']['display_name'];
        // header("Location: student_info.php");
    }
}

// Fetch current data
$query = "SELECT * FROM students WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$student = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile — School RMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-layout">
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Edit Profile</div>
            <div class="top-actions">
                <span class="welcome">Welcome, <strong><?=htmlspecialchars($_SESSION['user']['display_name'])?></strong></span>
                <a class="btn-ghost" href="logout.php"><ion-icon name="log-out-outline"></ion-icon> Logout</a>
            </div>
        </header>

        <section class="card">
            <?php if($message): ?>
                <div class="toast <?= strpos($message, 'success') !== false ? 'success' : 'error' ?>"><?=$message?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid">
                    <label>First Name
                        <input type="text" name="first_name" value="<?=htmlspecialchars($student['first_name'])?>" required>
                    </label>
                    
                    <label>Last Name
                        <input type="text" name="last_name" value="<?=htmlspecialchars($student['last_name'])?>" required>
                    </label>
                    
                    <label>Class
                        <select name="class" disabled>
                            <?php foreach($classes as $class): ?>
                              <option value="<?php echo $class; ?>" <?php echo $student['class'] == $class ? 'selected' : ''; ?>><?php echo $class; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    
                    <label>Gender
                        <select name="gender" disabled>
                            <option value="M" <?= $student['gender'] == 'M' ? 'selected' : '' ?>>Male</option>
                            <option value="F" <?= $student['gender'] == 'F' ? 'selected' : '' ?>>Female</option>
                            <option value="O" <?= $student['gender'] == 'O' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </label>
                    
                    <label>Date of Birth
                        <input type="date" name="dob" value="<?=htmlspecialchars($student['dob'])?>" disabled>
                    </label>
                </div>
                
                <div style="display:flex; gap:8px; margin-top:12px;">
                    <button class="btn" type="submit">Update Profile</button>
                    <a href="student_info.php" class="btn outline">Cancel</a>
                </div>
            </form>
        </section>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>