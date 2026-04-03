<?php
require 'config.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'teacher'){
    header("Location: index.php");
    exit();
}

$message = '';

$selected_class_arm = $_POST['class_arm'] ?? '';
$students = [];
$subjects = [];

// Load current session and term
$currentSession = get_current_session();
$currentTerm = get_current_term();

if($selected_class_arm){
    // Parse class and arm
    $parts = explode(' ', $selected_class_arm);
    $class = $parts[0];
    $arm = $parts[1] ?? 'A';
    
    // Get students in the selected class and arm
    $stmt = $pdo->prepare("SELECT * FROM students WHERE class = ? AND arm = ? ORDER BY first_name, last_name");
    $stmt->execute([$class, $arm]);
    $students = $stmt->fetchAll();

    // Get subjects for the selected class and arm
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE class = ? AND arm = ? ORDER BY name");
    $stmt->execute([$class, $arm]);
    $subjects = $stmt->fetchAll();
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_result'])){
    $student_id = $_POST['student_id'];
    $subject_id = $_POST['subject_id'];
    $test1 = (float)($_POST['test1'] ?? 0);
    $test2 = (float)($_POST['test2'] ?? 0);
    $test3 = (float)($_POST['test3'] ?? 0);
    $exam = (float)($_POST['exam'] ?? 0);

    if($test1 < 0 || $test1 > 15 || $test2 < 0 || $test2 > 15 || $test3 < 0 || $test3 > 10 || $exam < 0 || $exam > 60){
        $message = "Scores must be within limits: 1st/2nd Test ≤15, 3rd Test ≤10, Exam ≤60.";
    } else {
        // Determine current term/session set by admin
        $term = get_current_term();
        $session = get_current_session();

        // Check if result already exists for this term/session
        $check = $pdo->prepare("SELECT COUNT(*) FROM results WHERE student_id = ? AND subject_id = ? AND term = ? AND session = ?");
        $check->execute([$student_id, $subject_id, $term, $session]);
        if($check->fetchColumn() > 0){
            $message = "Result already exists for this student and subject for the current session/term.";
        } else {
            // Include teacher details for approval workflow
            $teacher_id = $_SESSION['user']['teacher_id'];
            $teacherInfo = $pdo->prepare("SELECT full_name FROM teachers WHERE teacher_id = ?");
            $teacherInfo->execute([$teacher_id]);
            $teacherData = $teacherInfo->fetch();
            $teacherName = $teacherData['full_name'] ?? $teacher_id;

            $stmt = $pdo->prepare("INSERT INTO results (student_id, subject_id, test1, test2, test3, exam, term, session, uploaded_by_teacher_id, uploaded_by_teacher_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $subject_id, $test1, $test2, $test3, $exam, $term, $session, $teacher_id, $teacherName, 'pending']);
            $message = "Result submitted for approval for $term ($session).";
        }
    }
}

// Get all classes from classes table for consistency
$classRows = $pdo->query("SELECT class_name FROM classes ORDER BY class_name")->fetchAll(PDO::FETCH_COLUMN);
$classes = $classRows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Enter Results — School RMS</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-layout">
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Enter Results</div>
            <div class="top-actions">
                <span class="welcome">Welcome, <strong><?=htmlspecialchars($_SESSION['user']['display_name'])?></strong></span>
                <a class="btn-ghost" href="logout.php"><ion-icon name="log-out-outline"></ion-icon> Logout</a>
            </div>
        </header>

        <section class="card">
            <div style="margin-bottom:16px; padding:12px; background:#f0f8ff; border-radius:8px; border-left:4px solid #0f74ff;">
                <strong>Active Session/Term:</strong> <?=htmlspecialchars($currentSession ?: 'N/A')?> — <?=htmlspecialchars($currentTerm)?>
            </div>
            <h3>Select Class</h3>
            <form method="POST">
                <div class="form-grid">
                    <label>Class & Arm
                        <select name="class_arm" onchange="this.form.submit()" required>
                            <option value="">Select Class & Arm</option>
                            <?php foreach($classes as $class_arm): ?>
                            <option value="<?=$class_arm?>" <?= $selected_class_arm == $class_arm ? 'selected' : '' ?>><?=$class_arm?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </form>

            <?php if($selected_class_arm): ?>
            <h3>Enter Result for <?=$selected_class_arm?></h3>
            <?php if($message): ?>
                <div class="toast <?= strpos($message, 'success') !== false ? 'success' : 'error' ?>"><?=$message?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="class_arm" value="<?=$selected_class_arm?>">
                <div class="form-grid">
                    <label>Student
                        <select name="student_id" required>
                            <option value="">Select Student</option>
                            <?php foreach($students as $student): ?>
                            <option value="<?=$student['id']?>"><?=$student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['student_id'] . ')'?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>Subject
                        <select name="subject_id" required>
                            <option value="">Select Subject</option>
                            <?php foreach($subjects as $subject): ?>
                            <option value="<?=$subject['id']?>"><?=$subject['name']?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>1st Test (15 marks)
                        <input type="number" name="test1" min="0" max="15" step="0.01" required>
                    </label>

                    <label>2nd Test (15 marks)
                        <input type="number" name="test2" min="0" max="15" step="0.01" required>
                    </label>

                    <label>3rd Test (10 marks)
                        <input type="number" name="test3" min="0" max="10" step="0.01" required>
                    </label>

                    <label>Exam (60 marks)
                        <input type="number" name="exam" min="0" max="60" step="0.01" required>
                    </label>
                </div>
                
                <div style="display:flex; gap:8px; margin-top:12px;">
                    <button class="btn" type="submit" name="submit_result">Save Result</button>
                    <a href="teacher_dashboard.php" class="btn outline">Back to Dashboard</a>
                </div>
            </form>
            <?php endif; ?>
        </section>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>