<?php
require 'config.php';
require_login();

// Fetch classes directly from database
$classes = $pdo->query("SELECT class_name FROM classes ORDER BY class_name")->fetchAll(PDO::FETCH_COLUMN);

// handle create student
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_student'){
  $inputStudentId = trim($_POST['student_id'] ?? '');
  if($inputStudentId === ''){
    // Auto-generate student ID: Get last numeric ID and increment by 1
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_id REGEXP '^ST[0-9]+$' ORDER BY CAST(SUBSTRING(student_id, 3) AS UNSIGNED) DESC LIMIT 1");
    $stmt->execute();
    $lastRecord = $stmt->fetch();
    
    if($lastRecord){
      $lastNum = (int)substr($lastRecord['student_id'], 2); // Extract number after 'ST'
      $nextNum = $lastNum + 1;
    } else {
      $nextNum = 1;
    }
    $student_id = 'ST' . str_pad($nextNum, 3, '0', STR_PAD_LEFT); // e.g., ST001, ST002
  } else {
    $student_id = $inputStudentId;
    // check duplicate
    $existsStmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
    $existsStmt->execute([$student_id]);
    if($existsStmt->fetch()){
      flash('error','Student ID already exists.');
      header('Location: students.php');
      exit;
    }
  }

  $first = $_POST['first_name'] ?? '';
  $last = $_POST['last_name'] ?? '';
  $gender = $_POST['gender'] ?? 'O';
  $class = $_POST['class'] ?? '';
  $arm = $_POST['arm'] ?? 'A';
  $dob = $_POST['dob'] ?: null;

    $imgName = null;
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
        // Create uploads directory if it doesn't exist
        $uploadsDir = __DIR__.'/uploads';
        if(!is_dir($uploadsDir)){
            mkdir($uploadsDir, 0755, true);
        }
        
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imgName = 'student_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $target = $uploadsDir.'/'.$imgName;
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
    }

    // hash default password (student_id)
    $defaultHash = password_hash($student_id, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO students (student_id, first_name, last_name, gender, class, arm, dob, image, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$student_id, $first, $last, $gender, $class, $arm, $dob, $imgName, $defaultHash]);
    flash('success','Student added.');
    header('Location: students.php');
    exit;
}

// DELETE
if(isset($_GET['delete'])){
    $ids = explode(',', $_GET['delete']);
    foreach($ids as $id){
        $pdo->prepare("DELETE FROM students WHERE id=?")->execute([(int)$id]);
    }
    flash('success', count($ids) . ' students deleted.');
    header("Location: students.php");
    exit;
}

// PROMOTE student (individual action from edit modal)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='promote_student'){
    $id = (int)($_POST['id'] ?? 0);
    $targetClass = trim($_POST['target_class'] ?? '');
    $targetArm = trim($_POST['target_arm'] ?? 'A');
    if($id > 0 && $targetClass){
        $stmt = $pdo->prepare("UPDATE students SET class = ?, arm = ? WHERE id = ?");
        $stmt->execute([$targetClass, $targetArm, $id]);
        flash('success', 'Student promoted to ' . $targetClass . ' ' . $targetArm . '.');
    } else {
        flash('error', 'Invalid promotion data.');
    }
    header('Location: students.php');
    exit;
}

// BULK PROMOTE selected
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='bulk_promote'){
    $idsString = trim($_POST['student_ids'] ?? '');
    $ids = $idsString ? explode(',', $idsString) : [];
    $targetClass = trim($_POST['target_class'] ?? '');
    $targetArm = trim($_POST['target_arm'] ?? 'A');
    if(!empty($ids) && $targetClass){
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("UPDATE students SET class = ?, arm = ? WHERE id IN ($placeholders)");
        $stmt->execute(array_merge([$targetClass, $targetArm], $ids));
        flash('success', count($ids) . ' students promoted to ' . $targetClass . ' ' . $targetArm . '.');
    } else {
        flash('error', 'Invalid promotion data.');
    }
    header('Location: students.php');
    exit;
}

// DEMOTE student (individual action from edit modal)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='demote_student'){
    $id = (int)($_POST['id'] ?? 0);
    if($id > 0){
        $prevClass = demote_student($id);
        if($prevClass){
            flash('success', 'Student demoted to ' . $prevClass . '.');
        } else {
            flash('error', 'Cannot demote student from this class.');
        }
    }
    header('Location: students.php');
    exit;
}

if($_SERVER['REQUEST_METHOD']==='POST' && $_POST['action']==='edit_student'){
    $id = (int)($_POST['id'] ?? 0);
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $gender = $_POST['gender'] ?? 'O';
    $class = $_POST['class'] ?? '';
    $arm = $_POST['arm'] ?? 'A';
    $dob = $_POST['dob'] ?: null;

    $imageName = null;
    if(isset($_FILES['image']) && $_FILES['image']['error'] === 0){
        $uploadsDir = __DIR__.'/uploads';
        if(!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imageName = 'student_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $target = $uploadsDir.'/'.$imageName;
        move_uploaded_file($_FILES['image']['tmp_name'], $target);

        // optional remove old file
        $oldImg = $pdo->prepare('SELECT image FROM students WHERE id=?');
        $oldImg->execute([$id]);
        $oldRow = $oldImg->fetch();
        if($oldRow && $oldRow['image'] && file_exists($uploadsDir.'/'.$oldRow['image'])){
            @unlink($uploadsDir.'/'.$oldRow['image']);
        }
    }

    $sql = "UPDATE students SET first_name=?, last_name=?, class=?, arm=?, gender=?, dob=?";
    $params = [$first, $last, $class, $arm, $gender, $dob];
    if($imageName !== null){
        $sql .= ", image=?";
        $params[] = $imageName;
    }
    $sql .= " WHERE id=?";
    $params[] = $id;

    $pdo->prepare($sql)->execute($params);
    flash('success','Student updated.');
    header('Location: students.php');
    exit;
}

// PROMOTE ALL STUDENTS to next class (for new academic session)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='promote_all_students'){
    // Define class progression map
    $classMap = [
        'Nursery' => 'Primary 1',
        'Primary 1' => 'Primary 2',
        'Primary 2' => 'Primary 3',
        'Primary 3' => 'Primary 4',
        'Primary 4' => 'Primary 5',
        'Primary 5' => 'Primary 6',
        'Primary 6' => 'JSS1',
        'JSS1' => 'JSS2',
        'JSS2' => 'JSS3',
        'JSS3' => 'SS1',
        'SS1' => 'SS2',
        'SS2' => 'SS3',
        'SS3' => 'Graduated'
    ];
    
    try {
        $studentsToPromote = $pdo->query("SELECT id, class, arm FROM students")->fetchAll();
        $promoted = 0;
        foreach($studentsToPromote as $student){
            $currentClass = $student['class'];
            if(isset($classMap[$currentClass])){
                $nextClass = $classMap[$currentClass];
                $pdo->prepare("UPDATE students SET class=? WHERE id=?")->execute([$nextClass, $student['id']]);
                $promoted++;
            }
        }
        flash('success', "Promoted $promoted student(s) to next class.");
    } catch(Exception $e){
        flash('error', 'Error promoting students: ' . $e->getMessage());
    }
    header('Location: students.php');
    exit;
}


// fetch students
$students = $pdo->query("SELECT * FROM students ORDER BY id DESC")->fetchAll();
$nextStudentId = get_next_student_id();

// Fetch class names for the Add Student form (from classes table)
// Note: $classes is already set at the top of the file from the classes table
$classFilterOptions = $pdo->query("SELECT DISTINCT CONCAT(class, ' ', arm) as class_arm FROM students ORDER BY class, arm")->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Students — School RMS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    /* action buttons in table use consistent link style, same as delete */
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

    /* view student uses same card layout as edit student modal style */
    .student-view-card { width: 100%; }
    .student-view-card.card:hover { transform: none; }
    .student-view-card .student-view-top { display:flex; align-items:center; gap:14px; margin-bottom:14px; }
    .student-view-card .student-view-grid{display:grid; grid-template-columns:1fr 1fr; gap:10px;}
    .student-view-card .student-view-item{background:var(--panel); border:1px solid #eef4fb; padding:10px 12px; border-radius:10px;}
    .student-view-card .student-view-item strong{display:block; margin-bottom:4px; color:var(--muted);}
    .student-view-card .student-view-item span{color:#213547;}
    .student-view-card .student-view-top img{width:72px; height:72px; object-fit:cover; border-radius:50%; border:2px solid #0f74ff;}
    .student-view-card .student-view-title{font-size:1.2rem; font-weight:700; color:#0f3b70;}
    .student-view-card .student-view-sub{color:#64748b;}
    .modal-panel{max-width:760px; width:calc(100% - 40px); position:relative; }
    .modal-panel .close-modal { z-index: 5; }

    .class-filter.selected { background: var(--accent2) !important; color: #fff; }
  </style>
</head>
<body class="app-layout">
  <?php include 'partials/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div class="page-title">Students</div>
      <div class="top-actions">
        <input type="text" id="searchInput" placeholder="Search students..." style="padding: 8px 12px; border: 1px solid #eef6ff; border-radius: 8px; background: #fafcff; outline: none; font-size: 14px; width: 200px;">
        <button id="openAddStudent" class="btn">+ Add Student</button>
        <a id="deleteSelected" class="btn danger" style="display:none;" data-confirm="Are you sure you want to delete the selected students?" data-confirm-name="Selected students" data-confirm-method="GET">Delete Selected</a>
        <a id="promoteSelected" class="btn" style="display:none; background:linear-gradient(90deg, #10b981, #34d399); color:#fff; border:none;" onclick="openBulkPromoteModal()">⬆ Promote Selected</a>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action" value="promote_all_students">
          <button type="submit" class="btn outline" style="background:linear-gradient(90deg, #ff9800, #ffa726); color:#fff; border:none;" data-confirm="Promote ALL students to next class? This action cannot be undone." data-confirm-name="All students" data-confirm-method="POST" data-confirm-action="Promote">📈 Promote All</button>
        </form>
      </div>
    </header>

    <section class="card">
      <?php if($m = flash('success')): ?>
        <div class="toast success"><?=htmlspecialchars($m)?></div>
      <?php endif; ?>
      <?php if($e = flash('error')): ?>
        <div class="toast error"><?=htmlspecialchars($e)?></div>
      <?php endif; ?>

      <h3 style="margin-bottom: 16px;">Students by Class</h3>
      <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px;">
        <button class="btn class-filter selected" data-class="all" style="padding: 14px 24px; font-size: 16px; font-weight: 600;">All Classes</button>
        <?php foreach($classFilterOptions as $class): ?>
          <button class="btn class-filter" data-class="<?php echo htmlspecialchars($class); ?>" style="padding: 14px 24px; font-size: 16px; font-weight: 600;"><?php echo $class; ?></button>
        <?php endforeach; ?>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th><input type="checkbox" id="selectAll"></th>
              <th>Photo</th>
                <th>Reg. No</th>
                <th>Name</th>
                <th>Class</th>
                <th>Gender</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($students as $s): ?>
                <tr>
                  <td><input type="checkbox" name="selected[]" value="<?php echo $s['id']; ?>"></td>
                  <td class="avatar">
                    <?php if($s['image'] && file_exists(__DIR__.'/uploads/'.$s['image'])): ?>
                      <img src="uploads/<?php echo htmlspecialchars($s['image']); ?>" alt="<?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?>">
                    <?php else: ?>
                      <div class="avatar-fallback"><?php echo htmlspecialchars(substr($s['first_name'],0,1).substr($s['last_name'],0,1)); ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($s['student_id']); ?></td>
                  <td><?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?></td>
                  <td><?php echo htmlspecialchars($s['class'] . ' ' . ($s['arm'] ?? 'A')); ?></td>
                  <td><?php echo htmlspecialchars($s['gender']); ?></td>
                  <td class="actions">
                    <button type="button" class="link view-student" data-id="<?php echo (int)$s['id']; ?>" title="View details"><ion-icon name="eye-outline"></ion-icon></button>
                    <button type="button" class="link edit-student" data-id="<?php echo (int)$s['id']; ?>" title="Edit"><ion-icon name="pencil-outline"></ion-icon></button>
                    <a class="link" href="students.php?delete=<?php echo $s['id']; ?>" title="Delete" data-confirm="Delete student '%s'?" data-confirm-name="<?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?>"><ion-icon name="trash-outline"></ion-icon></a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if(empty($students)): ?>
                <tr><td colspan="7" style="text-align:center;">No students yet — add one.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
      </div>
    </section>
  </main>

  <!-- Add Student Modal -->
  <div id="modalAddStudent" class="modal" aria-hidden="true">
    <div class="modal-panel">
      <header>
        <h3>Add Student</h3>
        <button class="close-modal" data-close>&times;</button>
      </header>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_student">
        <div class="form-grid">
          <label>Student ID<input name="student_id" value="<?php echo htmlspecialchars($nextStudentId); ?>" placeholder="Auto-generated"></label>
          <label>First name<input name="first_name" required></label>
          <label>Last name<input name="last_name" required></label>
          <label>Class
            <?php if(empty($classes)): ?>
              <div style="color: #dc3545; font-size: 0.9rem;">No classes available. Please create classes first in <a href="classes.php" target="_blank">Classes Management</a>.</div>
              <select name="class" id="add_class" required style="display:none;">
                <option value="">Select Class</option>
              </select>
            <?php else: ?>
              <select name="class" id="add_class" required>
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
          <label>Gender
            <select name="gender">
              <option value="M">Male</option>
              <option value="F">Female</option>
            </select>
          </label>
          <label>Date of Birth<input name="dob" type="date"></label>
          <label>Profile Image<input name="image" type="file" accept="image/*"></label>
        </div>
        <div style="display:flex; gap:8px; margin-top:12px;">
          <button class="btn" type="submit">Save</button>
          <button type="button" class="btn outline" data-close>Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- View Student Modal -->
  <div id="modalViewStudent" class="modal" aria-hidden="true">
    <div class="modal-panel">
      <button class="close-modal" data-close style="position:absolute; top:12px; right:12px; font-size:1.2rem">&times;</button>
      <div id="viewStudentContent" class="student-card-wrapper"></div>
    </div>
  </div>

  <!-- Edit Student Modal -->
  <div id="modalEditStudent" class="modal" aria-hidden="true">
    <div class="modal-panel">
      <header>
        <h3>Edit Student</h3>
        <button class="close-modal" data-close>&times;</button>
      </header>
      <form id="editStudentForm" method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="edit_student">
        <input type="hidden" name="id" id="edit_student_id">
        <div class="form-grid">
          <label>First name<input name="first_name" id="edit_first_name" required></label>
          <label>Last name<input name="last_name" id="edit_last_name" required></label>
          <label>Class
            <select name="class" id="edit_class" required>
              <option value="">Select Class</option>
              <?php foreach($classes as $class): ?>
                <option value="<?php echo $class; ?>"><?php echo $class; ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Arm
            <select name="arm" id="edit_arm" required>
              <option value="A">A</option>
              <option value="B">B</option>
              <option value="C">C</option>
              <option value="D">D</option>
            </select>
          </label>
          <label>Gender
            <select name="gender" id="edit_gender">
              <option value="M">Male</option>
              <option value="F">Female</option>
              <option value="O">Other</option>
            </select>
          </label>
          <label>Date of Birth<input name="dob" id="edit_dob" type="date"></label>
          <label>Profile Image<input name="image" type="file" accept="image/*"></label>
        </div>
        <div style="display:flex; gap:8px; margin-top:12px;">
          <button class="btn" type="submit">Save</button>
          <button type="button" class="btn outline" data-close>Cancel</button>
        </div>
      </form>
      
      <!-- Promote/Demote Actions -->
      <div style="margin-top:18px; padding-top:14px; border-top:1px solid #eef4fb;">
        <p style="margin:0 0 10px; font-size:0.85rem; color:#64748b;"><strong>Class Management</strong></p>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
          <button type="button" class="btn" style="background:linear-gradient(90deg, #10b981, #34d399); padding:8px 12px; font-size:0.9rem;" onclick="openPromoteModal(document.getElementById('edit_student_id').value)">⬆ Promote</button>
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="demote_student">
            <input type="hidden" name="id" id="edit_student_id_demote">
            <button type="submit" class="btn outline" style="padding:8px 12px; font-size:0.9rem;" data-confirm="Demote this student to the previous class?" data-confirm-name="Student">⬇ Demote</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Promote Student Modal -->
  <div id="modalPromoteStudent" class="modal" aria-hidden="true">
    <div class="modal-panel">
      <header>
        <h3>Promote Student</h3>
        <button class="close-modal" data-close>&times;</button>
      </header>
      <form method="post">
        <input type="hidden" name="action" value="promote_student">
        <input type="hidden" name="id" id="promote_student_id">
        <div class="form-grid">
          <label>Target Class
            <select name="target_class" required>
              <option value="">Select Class</option>
              <?php foreach($classes as $class): ?>
                <option value="<?php echo htmlspecialchars($class); ?>"><?php echo htmlspecialchars($class); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Target Arm
            <select name="target_arm" required>
              <option value="A">A</option>
              <option value="B">B</option>
              <option value="C">C</option>
              <option value="D">D</option>
            </select>
          </label>
        </div>
        <div style="display:flex; gap:8px; margin-top:12px;">
          <button class="btn" type="submit" style="background:linear-gradient(90deg, #10b981, #34d399);">Promote</button>
          <button type="button" class="btn outline" data-close>Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Bulk Promote Modal -->
  <div id="modalBulkPromote" class="modal" aria-hidden="true">
    <div class="modal-panel">
      <header>
        <h3>Bulk Promote Students</h3>
        <button class="close-modal" data-close>&times;</button>
      </header>
      <form method="post">
        <input type="hidden" name="action" value="bulk_promote">
        <input type="hidden" name="student_ids" id="bulk_promote_ids">
        <div class="form-grid">
          <label>Target Class
            <select name="target_class" required>
              <option value="">Select Class</option>
              <?php foreach($classes as $class): ?>
                <option value="<?php echo htmlspecialchars($class); ?>"><?php echo htmlspecialchars($class); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Target Arm
            <select name="target_arm" required>
              <option value="A">A</option>
              <option value="B">B</option>
              <option value="C">C</option>
              <option value="D">D</option>
            </select>
          </label>
        </div>
        <div style="display:flex; gap:8px; margin-top:12px;">
          <button class="btn" type="submit" style="background:linear-gradient(90deg, #10b981, #34d399);">Promote Selected</button>
          <button type="button" class="btn outline" data-close>Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
  <script>
    const studentsData = <?= json_encode($students, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function renderStudentCard(student){
      const imageSrc = student.image && student.image !== '' ? 'uploads/'+student.image : null;
      return `
        <div class="card glass-card student-view-card">
          <div class="student-view-top">
            ${imageSrc ? `<img src="${imageSrc}" alt="${student.first_name} ${student.last_name}">` : `<div style="width:72px;height:72px;border-radius:50%;background:#cbd5e0;display:flex;align-items:center;justify-content:center;color:#2d3748;font-weight:700;">${student.first_name?.charAt(0)||''}${student.last_name?.charAt(0)||''}</div>`}
            <div>
              <div class="student-view-title">${student.first_name} ${student.last_name}</div>
              <div class="student-view-sub">ID: ${student.student_id} • Class: ${student.class} ${student.arm || 'A'}</div>
            </div>
          </div>
          <div class="student-view-grid">
            <div class="student-view-item"><strong>Gender</strong><span>${student.gender}</span></div>
            <div class="student-view-item"><strong>DOB</strong><span>${student.dob || 'N/A'}</span></div>
            <div class="student-view-item"><strong>Registered</strong><span>${student.created_at || 'N/A'}</span></div>
            <div class="student-view-item"><strong>Password</strong><span>*******</span></div>
          </div>
        </div>
      `;
    }

    function openStudentView(id){
      const student = studentsData.find(s=>s.id == id);
      if(!student) return;
      document.getElementById('viewStudentContent').innerHTML = renderStudentCard(student);
      document.getElementById('modalViewStudent').setAttribute('aria-hidden','false');
      document.body.style.overflow='hidden';
    }

    function openStudentEdit(id){
      const student = studentsData.find(s=>s.id == id);
      if(!student) return;

      document.getElementById('edit_student_id').value = student.id;
      document.getElementById('edit_first_name').value = student.first_name;
      document.getElementById('edit_last_name').value = student.last_name;
      document.getElementById('edit_class').value = student.class;
      document.getElementById('edit_arm').value = student.arm || 'A';
      document.getElementById('edit_gender').value = student.gender || 'O';
      document.getElementById('edit_dob').value = student.dob || '';
      document.getElementById('edit_student_id_demote').value = student.id;
      document.getElementById('modalEditStudent').setAttribute('aria-hidden','false');
      document.body.style.overflow='hidden';
    }

    function openPromoteModal(studentId) {
      document.getElementById('promote_student_id').value = studentId;
      document.getElementById('modalPromoteStudent').setAttribute('aria-hidden','false');
      document.body.style.overflow='hidden';
    }

    function openBulkPromoteModal() {
      const selectedIds = Array.from(document.querySelectorAll('input[name="selected[]"]:checked')).map(cb => cb.value);
      if(selectedIds.length === 0) return;
      document.getElementById('bulk_promote_ids').value = selectedIds.join(',');
      document.getElementById('modalBulkPromote').setAttribute('aria-hidden','false');
      document.body.style.overflow='hidden';
    }

    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('.view-student').forEach(btn=>{
        btn.addEventListener('click', () => openStudentView(btn.dataset.id));
      });
      document.querySelectorAll('.edit-student').forEach(btn=>{
        btn.addEventListener('click', () => openStudentEdit(btn.dataset.id));
      });

      // Search functionality
      document.getElementById('searchInput').addEventListener('input', function(){
        const query = this.value.toLowerCase();
        const rows = document.querySelectorAll('.table tbody tr');
        rows.forEach(row => {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(query) ? '' : 'none';
        });
      });

      // Class filter functionality
      document.querySelectorAll('.class-filter').forEach(btn => {
        btn.addEventListener('click', () => {
          // Remove selected class from all buttons
          document.querySelectorAll('.class-filter').forEach(b => b.classList.remove('selected'));
          // Add selected to clicked button
          btn.classList.add('selected');

          const selectedClass = btn.dataset.class;
          const rows = document.querySelectorAll('.table tbody tr');
          rows.forEach(row => {
            const classCell = row.querySelector('td:nth-child(5)'); // Class is 5th column now
            if (classCell) {
              const rowClass = classCell.textContent.trim();
              row.style.display = (selectedClass === 'all' || rowClass === selectedClass) ? '' : 'none';
            }
          });
        });
      });

      // Select all functionality
      const selectAllCheckbox = document.getElementById('selectAll');
      const rowCheckboxes = document.querySelectorAll('input[name="selected[]"]');
      const deleteSelectedBtn = document.getElementById('deleteSelected');
      const promoteSelectedBtn = document.getElementById('promoteSelected');

      selectAllCheckbox.addEventListener('change', function() {
        rowCheckboxes.forEach(cb => cb.checked = this.checked);
        updateActionButtons();
      });

      rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateActionButtons);
      });

      function updateActionButtons() {
        const anyChecked = Array.from(rowCheckboxes).some(cb => cb.checked);
        deleteSelectedBtn.style.display = anyChecked ? 'inline-block' : 'none';
        promoteSelectedBtn.style.display = anyChecked ? 'inline-block' : 'none';
      }
    });
  </script>
</body>
</html>