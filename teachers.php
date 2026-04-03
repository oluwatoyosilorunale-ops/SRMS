<?php
require 'config.php';
require_login();

// Fetch all available classes from classes table
$classes = $pdo->query("SELECT class_name FROM classes ORDER BY class_name")->fetchAll(PDO::FETCH_COLUMN);

// Get available class-arm combinations
$classArmOptions = [];
foreach($classes as $class) {
    $arms = $pdo->prepare("SELECT DISTINCT arm FROM students WHERE class = ? ORDER BY arm");
    $arms->execute([$class]);
    $armList = $arms->fetchAll(PDO::FETCH_COLUMN);
    if(empty($armList)) {
        $armList = ['A']; // Default arm if no students yet
    }
    foreach($armList as $arm) {
        $classArmOptions[] = $class . ' ' . $arm;
    }
}

// handle add teacher with image upload
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_teacher'){
    $inputTeacherId = trim($_POST['teacher_id'] ?? '');
    if($inputTeacherId === ''){
      // Auto-generate teacher ID: Get last numeric ID and increment by 1
      $stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE teacher_id REGEXP '^TC[0-9]+$' ORDER BY CAST(SUBSTRING(teacher_id, 3) AS UNSIGNED) DESC LIMIT 1");
      $stmt->execute();
      $lastRecord = $stmt->fetch();
      
      if($lastRecord){
        $lastNum = (int)substr($lastRecord['teacher_id'], 2); // Extract number after 'TC'
        $nextNum = $lastNum + 1;
      } else {
        $nextNum = 1;
      }
      $teacher_id = 'TC' . str_pad($nextNum, 3, '0', STR_PAD_LEFT); // e.g., TC001, TC002
    } else {
      $teacher_id = $inputTeacherId;
      // if provided id already exists, abort with friendly message
      $existsStmt = $pdo->prepare("SELECT id FROM teachers WHERE teacher_id = ?");
      $existsStmt->execute([$teacher_id]);
      if($existsStmt->fetch()){
        flash('error', 'Teacher ID already exists. Choose a different ID.');
        header('Location: teachers.php');
        exit;
      }
    }
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $pan_id = trim($_POST['pan_id'] ?? '');
    $isClassTeacher = isset($_POST['is_class_teacher']) && $_POST['is_class_teacher'] == '1' ? 1 : 0;
    $managedClass = $isClassTeacher ? trim($_POST['managed_class'] ?? '') : null;

    // Validate: if class teacher selected, must have a class
    if($isClassTeacher && empty($managedClass)){
        flash('error', 'Class teachers must select a class to manage.');
        header('Location: teachers.php');
        exit;
    }

    // Validate: ensure no two teachers manage the same class
    if($isClassTeacher && !empty($managedClass)){
        $checkStmt = $pdo->prepare("SELECT id FROM teachers WHERE is_class_teacher = 1 AND managed_class = ?");
        $checkStmt->execute([$managedClass]);
        if($checkStmt->fetch()){
            flash('error', 'Another teacher already manages this class. A class can only have one class teacher.');
            header('Location: teachers.php');
            exit;
        }
    }

    // ensure PAN ID is unique when provided
    if($pan_id !== ''){
      $panStmt = $pdo->prepare("SELECT id FROM teachers WHERE pan_id = ?");
      $panStmt->execute([$pan_id]);
      if($panStmt->fetch()){
        flash('error', 'PAN ID already exists.');
        header('Location: teachers.php');
        exit;
      }
    }

    $imgName = null;
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
        // Create uploads directory if it doesn't exist
        $uploadsDir = __DIR__.'/uploads';
        if(!is_dir($uploadsDir)){
            mkdir($uploadsDir, 0755, true);
        }
        
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imgName = 'teacher_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $target = $uploadsDir.'/'.$imgName;
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
    }

    // set default hashed password equal to teacher_id
    $defaultHash = password_hash($teacher_id, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO teachers (teacher_id, full_name, email, pan_id, image, password, is_class_teacher, managed_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$teacher_id, $full_name, $email, $pan_id, $imgName, $defaultHash, $isClassTeacher, $managedClass]);
    flash('success','Teacher added.');
    header('Location: teachers.php');
    exit;
}

$teachers = $pdo->query("SELECT * FROM teachers ORDER BY id DESC")->fetchAll();
$nextTeacherId = get_next_teacher_id();

// DELETE
if(isset($_GET['delete'])){
    $ids = explode(',', $_GET['delete']);
    foreach($ids as $id){
        $pdo->prepare("DELETE FROM teachers WHERE id=?")->execute([(int)$id]);
    }
    flash('success', count($ids) . ' teachers deleted.');
    header("Location: teachers.php");
    exit;
}

if($_SERVER['REQUEST_METHOD']==='POST' && $_POST['action']==='edit_teacher'){
    $pdo->prepare("UPDATE teachers SET full_name=?, email=?, pan_id=? WHERE id=?")
    ->execute([$_POST['full_name'],$_POST['email'],$_POST['pan_id'],$_POST['id']]);
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Teachers</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .table .actions button.link,
    .table .actions a.link {
      color: var(--accent1);
      text-decoration: none;
      font-size: 16px;
      background: transparent;
      border: 0;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 5px 8px;
      border-radius: 8px;
      transition: background .15s ease;
    }
    .table .actions button.link:hover,
    .table .actions a.link:hover {
      background: rgba(15,116,255,0.08);
      color: var(--accent2);
    }

    .teacher-view-card { width:100%; }
    .teacher-view-card.card:hover { transform:none; }
    .teacher-view-top { display:flex; align-items:center; gap:14px; margin-bottom:14px; }
    .teacher-view-top img { width:72px; height:72px; object-fit:cover; border-radius:50%; border:2px solid #0f74ff; }
    .teacher-view-title { font-size:1.2rem; font-weight:700; color:#0f3b70; }
    .teacher-view-sub { color:#64748b; }
    .teacher-view-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .teacher-view-item { background:var(--panel); border:1px solid #eef4fb; padding:10px 12px; border-radius:10px; }
    .teacher-view-item strong { display:block; margin-bottom:4px; color:var(--muted); }
    .teacher-view-item span { color:#213547; }
    .modal-panel { max-width:760px; width:calc(100% - 40px); position:relative; }
    .modal-panel .close-modal { z-index:5; }
  </style>
</head>
<body class="app-layout">
  <?php include 'partials/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div class="page-title">Teachers</div>
      <div class="top-actions">
        <input type="text" id="searchInput" placeholder="Search teachers..." style="padding: 8px 12px; border: 1px solid #eef6ff; border-radius: 8px; background: #fafcff; outline: none; font-size: 14px; width: 200px;">
        <button id="openAddTeacher" class="btn">+ Add Teacher</button>
        <a id="deleteSelected" class="btn danger" style="display:none;" data-confirm="Are you sure you want to delete the selected teachers?" data-confirm-name="Selected teachers" data-confirm-method="GET">Delete Selected</a>
      </div>
    </header>

    <section class="card">
      <?php if($m = flash('success')): ?>
        <div class="toast success"><?=htmlspecialchars($m)?></div>
      <?php endif; ?>
      <?php if($e = flash('error')): ?>
        <div class="toast error"><?=htmlspecialchars($e)?></div>
      <?php endif; ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><input type="checkbox" id="selectAll"></th><th>Photo</th><th>Teacher ID</th><th>Name</th><th>Email</th><th>PAN ID</th><th>Role / Managed Class</th><th>Actions</th></tr></thead>
          <tbody>
              <?php foreach($teachers as $t): ?>
                <tr>
                  <td><input type="checkbox" name="selected[]" value="<?=$t['id']?>"></td>
                  <td class="avatar">
                    <?php if($t['image'] && file_exists(__DIR__.'/uploads/'.$t['image'])): ?>
                      <img src="uploads/<?=htmlspecialchars($t['image'])?>" alt="<?=htmlspecialchars($t['full_name'])?>">
                    <?php else: ?>
                      <div class="avatar-fallback"><?=htmlspecialchars(substr($t['full_name'],0,2))?></div>
                    <?php endif; ?>
                  </td>
                  <td><?=htmlspecialchars($t['teacher_id'])?></td>
                  <td><?=htmlspecialchars($t['full_name'])?></td>
                  <td><?=htmlspecialchars($t['email'])?></td>
                  <td><?=htmlspecialchars($t['pan_id'])?></td>
                  <td><?= $t['is_class_teacher'] ? htmlspecialchars($t['managed_class'] ?: 'Class Teacher') : 'Subject Teacher Only' ?></td>
                  <td class="actions">
                    <button type="button" class="link view-teacher" data-id="<?= (int)$t['id'] ?>" title="View details"><ion-icon name="eye-outline"></ion-icon></button>
                    <a class="link" href="edit_teacher.php?id=<?=$t['id']?>"><ion-icon name="create-outline"></ion-icon></a>
                    <a class="link" href="teachers.php?delete=<?=$t['id']?>" data-confirm="Delete teacher '%s'?" data-confirm-name="<?=htmlspecialchars($t['full_name'])?>"><ion-icon name="trash-outline"></ion-icon></a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
      </div>
    </section>
  </main>

  <!-- Add Teacher Modal -->
  <div id="modalAddTeacher" class="modal" aria-hidden="true">
    <div class="modal-panel">
      <header>
        <h3>Add Teacher</h3>
        <button class="close-modal" data-close>&times;</button>
      </header>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_teacher">
        <div class="form-grid">
          <label>Teacher ID<input name="teacher_id" value="<?=htmlspecialchars($nextTeacherId)?>" placeholder="Auto-generated"></label>
          <label>Full name<input name="full_name" required></label>
          <label>Email<input name="email" type="email"></label>
          <label>PAN ID<input name="pan_id"></label>
          <label>Image<input name="image" type="file" accept="image/*"></label>
          
          <!-- Teacher Type Selection -->
          <label style="grid-column: 1 / -1; margin-top: 8px;"><strong>Teacher Type</strong></label>
          <div style="grid-column: 1 / -1; display: flex; gap: 20px; padding: 12px; background: #fafcff; border-radius: 8px; margin-bottom: 12px;">
            <label style="display: flex; align-items: center; gap: 8px; margin: 0;">
              <input type="radio" name="is_class_teacher" value="0" checked> Subject Teacher Only
            </label>
            <label style="display: flex; align-items: center; gap: 8px; margin: 0;">
              <input type="radio" name="is_class_teacher" value="1" id="classTeacherCheckbox"> Class Teacher
            </label>
          </div>

          <!-- Class Selection (shown when class teacher is selected) -->
          <label id="classSelectLabel" style="grid-column: 1 / -1; display: none;">
            Class to Manage
            <select name="managed_class" id="classSelect">
              <option value="">Select a class</option>
              <?php foreach($classArmOptions as $option): ?>
                <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>

        <div style="display: flex; gap: 8px; margin-top: 12px;">
          <button class="btn" type="submit">Save</button>
          <button type="button" class="btn outline" data-close>Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- View Teacher Modal -->
  <div id="modalViewTeacher" class="modal" aria-hidden="true">
    <div class="modal-panel">
      <button class="close-modal" data-close style="position:absolute; top:12px; right:12px; font-size:1.2rem">&times;</button>
      <div id="viewTeacherContent" class="teacher-view-card card glass-card"></div>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
  <script>
    const teachersData = <?= json_encode($teachers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // Toggle class selection based on teacher type
    document.getElementById('classTeacherCheckbox').addEventListener('change', function(){
      const classSelectLabel = document.getElementById('classSelectLabel');
      if(this.checked){
        classSelectLabel.style.display = 'block';
        document.getElementById('classSelect').required = true;
      } else {
        classSelectLabel.style.display = 'none';
        document.getElementById('classSelect').required = false;
        document.getElementById('classSelect').value = '';
      }
    });

    // Also handle when switching radio buttons
    document.querySelectorAll('input[name="is_class_teacher"]').forEach(radio => {
      radio.addEventListener('change', function(){
        const classSelectLabel = document.getElementById('classSelectLabel');
        if(this.value === '1'){
          classSelectLabel.style.display = 'block';
          document.getElementById('classSelect').required = true;
        } else {
          classSelectLabel.style.display = 'none';
          document.getElementById('classSelect').required = false;
          document.getElementById('classSelect').value = '';
        }
      });
    });

    function renderTeacherCard(teacher){
      const imageSrc = teacher.image && teacher.image !== '' ? 'uploads/'+teacher.image : null;
      const teacherType = teacher.is_class_teacher ? `Class Teacher (${teacher.managed_class})` : 'Subject Teacher';
      return `
        <div class="card glass-card teacher-view-card">
          <div class="teacher-view-top">
            ${imageSrc ? `<img src="${imageSrc}" alt="${teacher.full_name}">` : `<div style="width:72px;height:72px;border-radius:50%;background:#cbd5e0;display:flex;align-items:center;justify-content:center;color:#2d3748;font-weight:700;">${teacher.full_name?.charAt(0)||''}</div>`}
            <div>
              <div class="teacher-view-title">${teacher.full_name}</div>
              <div class="teacher-view-sub">ID: ${teacher.teacher_id}</div>
            </div>
          </div>
          <div class="teacher-view-grid">
            <div class="teacher-view-item"><strong>Email</strong><span>${teacher.email || 'N/A'}</span></div>
            <div class="teacher-view-item"><strong>PAN ID</strong><span>${teacher.pan_id || 'N/A'}</span></div>
            <div class="teacher-view-item"><strong>Type</strong><span>${teacherType}</span></div>
            <div class="teacher-view-item"><strong>Password</strong><span>*******</span></div>
          </div>
        </div>
      `;
    }

    function openTeacherView(id){
      const teacher = teachersData.find(t => t.id == id);
      if(!teacher) return;
      document.getElementById('viewTeacherContent').innerHTML = renderTeacherCard(teacher);
      document.getElementById('modalViewTeacher').setAttribute('aria-hidden','false');
      document.body.style.overflow = 'hidden';
    }

    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('.view-teacher').forEach(btn=>{ btn.addEventListener('click', () => openTeacherView(btn.dataset.id)); });

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