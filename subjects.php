<?php
require 'config.php';
require_login();

// Fetch classes directly from database
$classes = $pdo->query("SELECT class_name FROM classes ORDER BY class_name")->fetchAll(PDO::FETCH_COLUMN);

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_subject'){
    $name = trim($_POST['name'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $arm = trim($_POST['arm'] ?? 'A');
    if($name && $class){
        // Check if same subject already exists for this class and arm
        $check = $pdo->prepare("SELECT id FROM subjects WHERE LOWER(name) = LOWER(?) AND class = ? AND arm = ?");
        $check->execute([$name, $class, $arm]);
        if($check->fetch()){
            flash('error', 'Subject already exists for this class and arm.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO subjects (name, class, arm) VALUES (?, ?, ?)");
            $stmt->execute([$name, $class, $arm]);
            flash('success','Subject added.');
        }
    }
    header('Location: subjects.php'); exit;
}

// DELETE
if(isset($_GET['delete'])){
    $ids = explode(',', $_GET['delete']);
    foreach($ids as $id){
        $pdo->prepare("DELETE FROM subjects WHERE id=?")->execute([(int)$id]);
    }
    flash('success', count($ids) . ' subjects deleted.');
    header("Location: subjects.php");
    exit;
}

if($_SERVER['REQUEST_METHOD']==='POST' && $_POST['action']==='edit_subject'){
    $pdo->prepare("UPDATE subjects SET name=?, class=?, arm=? WHERE id=?")
    ->execute([$_POST['name'],$_POST['class'],$_POST['arm'],$_POST['id']]);
}

$subjects = $pdo->query("SELECT * FROM subjects ORDER BY id DESC")->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Subjects</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-layout">
  <?php include 'partials/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div class="page-title">Subjects</div>
      <div class="top-actions">
        <input type="text" id="searchInput" placeholder="Search subjects..." style="padding: 8px 12px; border: 1px solid #eef6ff; border-radius: 8px; background: #fafcff; outline: none; font-size: 14px; width: 200px;">
        <button id="openAddSubject" class="btn">+ Add Subject</button>
        <a id="deleteSelected" class="btn danger" style="display:none;" data-confirm="Are you sure you want to delete the selected subjects?" data-confirm-name="Selected subjects" data-confirm-method="GET">Delete Selected</a>
      </div>
    </header>

    <section class="card">
      <?php if($m = flash('success')): ?><div class="toast success"><?=htmlspecialchars($m)?></div><?php endif; ?>
      <?php if($e = flash('error')): ?><div class="toast error"><?=htmlspecialchars($e)?></div><?php endif; ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><input type="checkbox" id="selectAll"></th><th>Subject</th><th>Class</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($subjects as $s): ?>
              <tr>
                <td><input type="checkbox" name="selected[]" value="<?=$s['id']?>"></td>
                <td><?=htmlspecialchars($s['name'])?></td>
                <td><?=htmlspecialchars($s['class'] . ' ' . ($s['arm'] ?? 'A'))?></td>
                <td class="actions">
                  <a class="link" href="edit_subject.php?id=<?=$s['id']?>"><ion-icon name="create-outline"></ion-icon></a>
                  <a class="link" href="subjects.php?delete=<?=$s['id']?>" data-confirm="Delete subject '%s'?" data-confirm-name="<?=htmlspecialchars($s['name'] ?? $s['subject'])?>"><ion-icon name="trash-outline"></ion-icon></a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($subjects)): ?><tr><td colspan="4" style="text-align:center;">No subjects yet</td></tr><?php endif; ?>
            </tbody>
          </table>
      </div>
    </section>
  </main>

  <!-- Add Subject Modal -->
  <div id="modalAddSubject" class="modal" aria-hidden="true">
    <div class="modal-panel">
      <header><h3>Add Subject</h3><button class="close-modal" data-close>&times;</button></header>
      <form method="post">
        <input type="hidden" name="action" value="add_subject">
        <div class="form-grid">
          <label>Subject name<input name="name" required></label>
          <label>Class
            <?php if(empty($classes)): ?>
              <div style="color: #dc3545; font-size: 0.9rem;">No classes available. Please create classes first in <a href="classes.php" target="_blank">Classes Management</a>.</div>
              <select name="class" required style="display:none;">
                <option value="">Select Class</option>
              </select>
            <?php else: ?>
              <select name="class" required>
                <option value="">Select Class</option>
                <?php foreach($classes as $class): ?>
                  <option value="<?php echo $class; ?>"><?php echo $class; ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </label>
          <label>Arm
            <select name="arm" required>
              <option value="A">A</option>
              <option value="B">B</option>
              <option value="C">C</option>
              <option value="D">D</option>
            </select>
          </label>
        </div>
        <div style="display:flex; gap:8px; margin-top:12px;">
          <button class="btn" type="submit">Save</button>
          <button type="button" class="btn outline" data-close>Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      // Search functionality
      document.getElementById('searchInput').addEventListener('input', function(){
        const query = this.value.toLowerCase();
        const rows = document.querySelectorAll('.table tbody tr');
        rows.forEach(row => {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(query) ? '' : 'none';
        });
      });

      // Select all functionality
      const selectAllCheckbox = document.getElementById('selectAll');
      const rowCheckboxes = document.querySelectorAll('input[name="selected[]"]');
      const deleteSelectedBtn = document.getElementById('deleteSelected');

      selectAllCheckbox.addEventListener('change', function() {
        rowCheckboxes.forEach(cb => cb.checked = this.checked);
        updateDeleteButton();
      });

      rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateDeleteButton);
      });

      function updateDeleteButton() {
        const anyChecked = Array.from(rowCheckboxes).some(cb => cb.checked);
        deleteSelectedBtn.style.display = anyChecked ? 'inline-block' : 'none';
      }
    });
  </script>
</body>
</html>