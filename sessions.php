<?php
require 'config.php';
require_login();

// only admin should access this page
if($_SESSION['user']['role'] !== 'admin'){
    header('Location: index.php');
    exit;
}

// handle form submissions
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(isset($_POST['add_session'])){
        $name = trim($_POST['add_session']);
        if($name !== ''){
            $stmt = $pdo->prepare("INSERT IGNORE INTO academic_sessions (name) VALUES (?)");
            $stmt->execute([$name]);
            flash('success', "Academic session '$name' added.");
        }
    }
    if(isset($_POST['current_session_id'])){
        set_setting('current_session_id', $_POST['current_session_id']);
        flash('success', 'Current academic session updated.');
    }
    if(isset($_POST['current_term'])){
        set_setting('current_term', $_POST['current_term']);
        flash('success', 'Current term updated.');
    }

    header('Location: sessions.php');
    exit;
}

// load data for display
$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY name DESC")->fetchAll();
$current_session_id = get_setting('current_session_id');
$current_term = get_setting('current_term') ?: 'First Term';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Academic Sessions &amp; Terms</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-layout">
  <?php include 'partials/sidebar.php'; ?>

  <main class="main-content">
    <header class="topbar">
      <div class="page-title">Session / Term Settings</div>
    </header>

    <?php if($msg = flash('success')): ?>
      <div class="toast success"><?=htmlspecialchars($msg)?></div>
    <?php endif; ?>

    <section class="card">
      <h3>Add New Academic Session</h3>
      <form method="POST" style="display:flex;gap:8px;align-items:center;">
        <input type="text" name="add_session" placeholder="e.g. 2024/2025" required style="flex:1;">
        <button class="btn" type="submit">Add</button>
      </form>

      <?php if($sessions): ?>
        <h3 style="margin-top:24px;">Existing Sessions</h3>
        <ul>
          <?php foreach($sessions as $s): ?>
            <li><?=htmlspecialchars($s['name'])?> <?=($current_session_id == $s['id'] ? '(current)' : '')?></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p>No sessions defined yet.</p>
      <?php endif; ?>

      <h3 style="margin-top:24px;">Set Current Session and Term</h3>
      <form method="POST" style="display:flex;gap:16px;flex-wrap:wrap;align-items:center;">
        <label>Session
          <select name="current_session_id" required>
            <option value="">-- select --</option>
            <?php foreach($sessions as $s): ?>
              <option value="<?=htmlspecialchars($s['id'])?>" <?=($current_session_id == $s['id'] ? 'selected' : '')?>><?=htmlspecialchars($s['name'])?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Term
          <select name="current_term" required>
            <?php foreach(['First Term','Second Term','Third Term'] as $t): ?>
              <option value="<?=htmlspecialchars($t)?>" <?=(($current_term==$t)?'selected':'')?>><?=htmlspecialchars($t)?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn" type="submit">Save Settings</button>
      </form>
    </section>

  </main>
</body>
</html>
