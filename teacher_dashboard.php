<?php
require 'config.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'teacher'){
    header("Location: index.php");
    exit();
}

if(!isset($_SESSION['user']['teacher_id'])){
    header("Location: index.php");
    exit();
}

$_SESSION['display'] = $_SESSION['user']['display_name'];

$teacher_id = $_SESSION['user']['teacher_id'];

// Load current session and term
$currentSession = get_current_session();
$currentTerm = get_current_term();

// Fetch teacher data including image and class teacher info
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE teacher_id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();

// Fetch students in the managed class if teacher is a class teacher
$classStudents = [];
if($teacher && $teacher['is_class_teacher'] && $teacher['managed_class']){
    $stmt = $pdo->prepare("SELECT id, student_id, first_name, last_name, CONCAT(class, ' ', arm) as class FROM students WHERE CONCAT(class, ' ', arm) = ? ORDER BY first_name");
    $stmt->execute([$teacher['managed_class']]);
    $classStudents = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Teacher Dashboard — School RMS</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .class-section {
            margin-top: 28px;
        }
        .class-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--accent1);
        }
        .class-header h3 {
            margin: 0;
            color: var(--accent1);
            font-size: 18px;
        }
        .class-badge {
            display: inline-block;
            background: linear-gradient(90deg, var(--accent1), var(--accent2));
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .students-table {
            width: 100%;
        }
        .students-table th, .students-table td {
            padding: 12px;
            border-bottom: 1px solid #eef4fb;
            text-align: left;
            font-size: 14px;
        }
        .students-table thead th {
            color: #4b6b84;
            font-weight: 700;
            background: transparent;
        }
        .students-table tbody tr:hover {
            background: rgba(15, 116, 255, 0.04);
        }
    </style>
</head>
<body class="app-layout">
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Teacher Dashboard</div>
            <div class="top-actions">
                <?php if($teacher && isset($teacher['image']) && $teacher['image'] && file_exists(__DIR__.'/uploads/'.$teacher['image'])): ?>
                    <img src="uploads/<?=htmlspecialchars($teacher['image'])?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover;">
                <?php else: ?>
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: linear-gradient(135deg, #0f74ff, #00c0ff); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 14px;">
                        <?=htmlspecialchars(substr($_SESSION['user']['display_name'], 0, 2))?>
                    </div>
                <?php endif; ?>
                <span class="welcome">Welcome, <strong><?=htmlspecialchars($_SESSION['user']['display_name'])?></strong></span>
                <a class="btn-ghost" href="logout.php"><ion-icon name="log-out-outline"></ion-icon> Logout</a>
                <a href="teacher_profile.php" class="btn-ghost"><ion-icon name="person-outline"></ion-icon> My Profile</a>
            </div>
        </header>

        <section class="cards-grid">
            <div class="card stat-card">
                <div class="stat-title">Active Session</div>
                <div class="stat-value"><?=htmlspecialchars($currentSession ?: 'n/a')?></div>
                <div class="stat-icon"><ion-icon name="calendar-outline"></ion-icon></div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Active Term</div>
                <div class="stat-value"><?=htmlspecialchars($currentTerm)?></div>
                <div class="stat-icon"><ion-icon name="calendar-outline"></ion-icon></div>
            </div>

            <!-- <div class="card stat-card">
                <div class="stat-title">My Students</div>
                <div class="stat-value">—</div>
                <div class="stat-icon"><ion-icon name="people-outline"></ion-icon></div>
                <a href="teacher_students.php" class="card-link">View Students</a>
            </div> -->

            <div class="card stat-card">
                <div class="stat-title">Enter Results</div>
                <!-- <div class="stat-value">—</div> -->
                <div class="stat-icon"><ion-icon name="create-outline"></ion-icon></div>
                <a href="enter_result.php" class="card-link">Add Result</a>
            </div>

            <!-- <div class="card stat-card">
                <div class="stat-title">View Results</div>
                <div class="stat-value">—</div>
                <div class="stat-icon"><ion-icon name="bar-chart-outline"></ion-icon></div>
                <a href="view_results_teacher.php" class="card-link">Check Results</a>
            </div> -->

            <div class="card stat-card">
                <div class="stat-title">Subjects</div>
                <!-- <div class="stat-value">—</div> -->
                <div class="stat-icon"><ion-icon name="book-outline"></ion-icon></div>
                <a href="subjects.php" class="card-link">Manage Subjects</a>
            </div>
        </section>

        <!-- Class Students Section (shown only if teacher is class teacher) -->
        <?php if($teacher && $teacher['is_class_teacher'] && $teacher['managed_class']): ?>
        <section class="card class-section">
            <div class="class-header">
                <h3><ion-icon name="people-outline" style="font-size: 20px;"></ion-icon> My Class</h3>
                <span class="class-badge"><?php echo htmlspecialchars($teacher['managed_class']); ?></span>
                <span style="color: var(--muted); font-size: 13px; margin-left: auto;">Total: <?=count($classStudents)?> students</span>
            </div>

            <?php if(count($classStudents) > 0): ?>
            <div class="table-wrap">
                <table class="students-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>Email</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($classStudents as $student): ?>
                        <tr>
                            <td><strong><?=htmlspecialchars($student['first_name'].' '.$student['last_name'])?></strong></td>
                            <td><?=htmlspecialchars($student['student_id'])?></td>
                            <td style="color: var(--muted); font-size: 13px;">View in students page</td>
                            <td><span style="display: inline-block; padding: 4px 10px; background: #c8f7c5; color: #207245; border-radius: 6px; font-size: 12px; font-weight: 600;">Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 30px; color: var(--muted);">
                <ion-icon name="people-outline" style="font-size: 48px; opacity: 0.3;"></ion-icon>
                <p style="margin-top: 12px;">No students in this class yet.</p>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>