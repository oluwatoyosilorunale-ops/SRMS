<?php
include 'config.php'; // Database connection
require_login();

$classes = $pdo->query("SELECT class_name FROM classes ORDER BY class_name")->fetchAll(PDO::FETCH_COLUMN);

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: subjects.php");
    exit();
}

$id = $_GET['id'];
$message = '';

// Fetch subject data
$query = "SELECT * FROM subjects WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$id]);
$subject = $stmt->fetch();

if (!$subject) {
    header("Location: subjects.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_name = trim($_POST['subject_name']);
    $class = $_POST['class'];
    $arm = $_POST['arm'];
    
    if (empty($subject_name) || empty($class)) {
        $message = "All fields are required!";
    } else {
        $update_query = "UPDATE subjects SET name = ?, class = ?, arm = ? WHERE id = ?";
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([$subject_name, $class, $arm, $id]);
        
        if ($update_stmt->rowCount() > 0) {
            flash('success', 'Subject updated successfully!');
            header("Location: subjects.php");
            exit();
        } else {
            $message = "Error updating subject!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Edit Subject — School RMS</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-layout">
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Edit Subject</div>
            <div class="top-actions">
                <a href="subjects.php" class="btn outline">← Back to Subjects</a>
            </div>
        </header>

        <section class="card">
            <?php if($m = flash('success')): ?><div class="toast success"><?=$m?></div><?php endif; ?>
            <?php if ($message): ?>
                <div class="toast error"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid">
                    <label>Subject Name
                        <input type="text" name="subject_name" value="<?php echo htmlspecialchars($subject['name']); ?>" required>
                    </label>
                    
                    <label>Class
                        <select name="class" required>
                            <option value="">Select Class</option>
                            <?php foreach($classes as $class): ?>
                              <option value="<?php echo $class; ?>" <?php if($subject['class'] == $class) echo 'selected'; ?>><?php echo $class; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    
                    <label>Arm
                        <select name="arm" required>
                            <option value="A" <?php if(($subject['arm'] ?? 'A') == 'A') echo 'selected'; ?>>A</option>
                            <option value="B" <?php if(($subject['arm'] ?? 'A') == 'B') echo 'selected'; ?>>B</option>
                            <option value="C" <?php if(($subject['arm'] ?? 'A') == 'C') echo 'selected'; ?>>C</option>
                            <option value="D" <?php if(($subject['arm'] ?? 'A') == 'D') echo 'selected'; ?>>D</option>
                        </select>
                    </label>
                </div>
                
                <div style="display:flex; gap:8px; margin-top:12px;">
                    <button class="btn" type="submit">Update Subject</button>
                    <a href="subjects.php" class="btn outline">Cancel</a>
                </div>
            </form>
        </section>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>