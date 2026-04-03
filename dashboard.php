<?php
require 'config.php';
require_login();
$user = $_SESSION['user'];
$_SESSION['display'] = $user['display_name'];

// quick stats
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
$totalSubjects = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
// load current session/term
$currentSession = get_current_session();
$currentTerm = get_current_term();
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Dashboard — School RMS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .actions-row {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 16px;
    }
    .big-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 16px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.2s;
      background: linear-gradient(90deg, #0f74ff, #00c0ff);
      color: #fff;
      box-shadow: 0 4px 12px rgba(15, 116, 255, 0.2);
      border: none;
      cursor: pointer;
    }
    .big-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(15, 116, 255, 0.3);
    }
    .big-btn.outline {
      background: transparent;
      color: #0f74ff;
      border: 2px solid #0f74ff;
    }
    .big-btn.outline:hover {
      background: #0f74ff;
      color: #fff;
    }
  </style>
</head>
<body class="app-layout">
  <?php include 'partials/sidebar.php' ?? null; ?>

  <main class="main-content">
    <header class="topbar">
      <div class="page-title">Admin Dashboard</div>
      <div class="top-actions">
        <span class="welcome">Welcome, <strong><?=htmlspecialchars($_SESSION['display'])?></strong></span>
        <a class="btn-ghost" href="logout.php"><ion-icon name="log-out-outline"></ion-icon> Logout</a>

        <a href="profile.php" style="display: flex; align-items: center; gap: 8px; text-decoration: none; color: inherit;">
          <?php if(isset($user['image']) && $user['image'] && file_exists(__DIR__.'/uploads/'.$user['image'])): ?>
            <img src="uploads/<?=htmlspecialchars($user['image'])?>" alt="Profile" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
          <?php else: ?>
            <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #0f74ff, #00c0ff); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px;">
              <?=strtoupper(substr($user['display_name'] ?? 'A', 0, 1))?>
            </div>
          <?php endif; ?>
          <span>My Profile</span>
        </a>
      </div>
    </header>

    <section class="cards-grid">
      <div class="card stat-card">
        <div class="stat-title">Total Students</div>
        <div class="stat-value"><?=$totalStudents?></div>
        <div class="stat-icon"><ion-icon name="people-outline"></ion-icon></div>
      </div>

      <div class="card stat-card">
        <div class="stat-title">Total Teachers</div>
        <div class="stat-value"><?=$totalTeachers?></div>
        <div class="stat-icon"><ion-icon name="person-outline"></ion-icon></div>
      </div>

      <div class="card stat-card">
        <div class="stat-title">Total Subjects</div>
        <div class="stat-value"><?=$totalSubjects?></div>
        <div class="stat-icon"><ion-icon name="book-outline"></ion-icon></div>
      </div>

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
    </section>

    <section class="recent">
      <div class="card">
        <h3>Quick Actions</h3>
        <div class="actions-row">
          <a href="students.php" class="big-btn"><ion-icon name="school-outline"></ion-icon> Manage Students</a>
          <a href="teachers.php" class="big-btn outline"><ion-icon name="people-circle-outline"></ion-icon> Manage Teachers</a>
          <a href="subjects.php" class="big-btn"><ion-icon name="layers-outline"></ion-icon> Subjects</a>
          <a href="results.php" class="big-btn"><ion-icon name="calculator-outline"></ion-icon> Result Computation</a>
        </div>
      </div>
    </section>

  </main>

  <script src="assets/js/main.js"></script>
</body>
</html>