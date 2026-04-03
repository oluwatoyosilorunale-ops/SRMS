<?php
require 'config.php';

// Ensure user is logged in as teacher
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'teacher'){
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['user']['teacher_id'];

// Fetch teacher data
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE teacher_id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();

// Check if teacher is a class teacher
$isClassTeacher = $teacher && $teacher['is_class_teacher'] && $teacher['managed_class'];

// If not a class teacher, show message
if(!$isClassTeacher){
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8"><title>My Students</title>
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
      <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
      <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body class="app-layout">
      <?php include 'partials/sidebar.php'; ?>
      <main class="main-content">
        <header class="topbar">
          <div class="page-title">My Students</div>
        </header>
        <section class="card">
          <div style="text-align: center; padding: 60px 20px;">
            <ion-icon name="information-circle-outline" style="font-size: 64px; color: var(--accent1); opacity: 0.5;"></ion-icon>
            <h2 style="color: var(--accent1); margin-top: 20px;">Not a Class Teacher</h2>
            <p style="color: var(--muted); font-size: 16px;">You are not assigned as a class teacher. Only class teachers can view and manage students in their class.</p>
            <a href="teacher_dashboard.php" class="btn" style="margin-top: 20px;"><ion-icon name="arrow-back-outline"></ion-icon> Back to Dashboard</a>
          </div>
        </section>
      </main>
      <script src="assets/js/main.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// Handle delete student
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    // Verify student belongs to this teacher's class
    $stmt = $pdo->prepare("SELECT CONCAT(class, ' ', arm) as class_arm FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    
    if($student && $student['class_arm'] === $teacher['managed_class']){
        $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$id]);
        flash('success','Student deleted.');
    } else {
        flash('error','Unauthorized action.');
    }
    header("Location: teacher_students.php");
    exit;
}

// Handle edit student
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_student'){
    $id = (int)$_POST['id'];
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $dob = $_POST['dob'] ?? '';
    
    // Verify student belongs to this teacher's class
    $stmt = $pdo->prepare("SELECT CONCAT(class, ' ', arm) as class_arm FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    
    if($student && $student['class_arm'] === $teacher['managed_class']){
        $pdo->prepare("UPDATE students SET first_name=?, last_name=?, gender=?, dob=? WHERE id=?")
            ->execute([$first_name, $last_name, $gender, $dob, $id]);
        flash('success','Student updated.');
    } else {
        flash('error','Unauthorized action.');
    }
    header("Location: teacher_students.php");
    exit;
}

// Fetch all students in the teacher's class
$stmt = $pdo->prepare("SELECT * FROM students WHERE CONCAT(class, ' ', arm) = ? ORDER BY first_name");
$stmt->execute([$teacher['managed_class']]);
$students = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>My Students</title>
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
    .student-view-card { width:100%; }
    .student-view-card.card:hover { transform:none; }
    .student-view-top { display:flex; align-items:center; gap:14px; margin-bottom:14px; }
    .student-view-top img { width:72px; height:72px; object-fit:cover; border-radius:50%; border:2px solid #0f74ff; }
    .student-view-title { font-size:1.2rem; font-weight:700; color:#0f3b70; }
    .student-view-sub { color:#64748b; }
    .student-view-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .student-view-item { background:var(--panel); border:1px solid #eef4fb; padding:10px 12px; border-radius:10px; }
    .student-view-item strong { display:block; margin-bottom:4px; color:var(--muted); }
    .student-view-item span { color:#213547; }
  </style>
</head>
<body class="app-layout">
  <?php include 'partials/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div class="page-title">My Students - <?php echo htmlspecialchars($teacher['managed_class']); ?></div>
      <div class="top-actions">
        <input type="text" id="searchInput" placeholder="Search students..." style="padding: 8px 12px; border: 1px solid #eef6ff; border-radius: 8px; background: #fafcff; outline: none; font-size: 14px; width: 200px;">
      </div>
    </header>

    <section class="card">
      <?php if($m = flash('success')): ?>
        <div class="toast success"><?=htmlspecialchars($m)?></div>
      <?php endif; ?>
      <?php if($e = flash('error')): ?>
        <div class="toast error"><?=htmlspecialchars($e)?></div>
      <?php endif; ?>

      <?php if(count($students) > 0): ?>
        <div class="cards-grid" style="grid-template-columns: repeat(3, minmax(260px, 1fr)); gap: 18px;">
          <?php foreach($students as $s): ?>
            <div class="card" style="padding: 16px; display: flex; flex-direction: column; gap: 10px;">
              <div style="display:flex; align-items:center; gap:12px;">
                <?php if($s['image'] && file_exists(__DIR__.'/uploads/'.$s['image'])): ?>
                  <img src="uploads/<?=htmlspecialchars($s['image'])?>" alt="<?=htmlspecialchars($s['first_name'].' '.$s['last_name'])?>" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid #0f74ff;">
                <?php else: ?>
                  <div style="width:72px;height:72px;border-radius:50%;display:flex;align-items:center;justify-content:center;background: linear-gradient(135deg,#0f74ff,#00c0ff);color:#fff;font-weight:700;font-size:24px;"><?=htmlspecialchars(substr($s['first_name'] ?? '',0,1).substr($s['last_name'] ?? '',0,1))?></div>
                <?php endif; ?>
                <div>
                  <h3 style="margin:0;font-size:1rem;"><?=htmlspecialchars($s['first_name'].' '.$s['last_name'])?></h3>
                  <div style="font-size:0.85rem;color:var(--muted);">STUDENT ID: <?=htmlspecialchars($s['student_id'] ?? 'N/A')?></div>
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:0.9rem;"> 
                <div><strong>Class:</strong> <?=htmlspecialchars($s['class'] ?? 'N/A')?></div>
                <div><strong>Gender:</strong> <?=htmlspecialchars($s['gender'] ?? 'N/A')?></div>
                <div><strong>DOB:</strong> <?=htmlspecialchars($s['dob'] ?? 'N/A')?></div>
                <div><strong>Phone:</strong> <?=htmlspecialchars($s['phone'] ?? 'N/A')?></div>
                <div style="grid-column:1 / -1;"><strong>Address:</strong> <?=htmlspecialchars($s['address'] ?? 'N/A')?></div>
                <?php foreach($s as $key => $val){ if(!in_array($key,['id','first_name','last_name','student_id','class','gender','dob','phone','address','image','password'])){ ?>
                  <div><strong><?=ucwords(str_replace('_',' ',$key))?>:</strong> <?=htmlspecialchars($val ?? 'N/A')?></div>
                <?php } } ?>
              </div>
              <div style="display:flex;justify-content:flex-end;">
                <button type="button" class="btn outline edit-student" data-id="<?= (int)$s['id'] ?>">Edit</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div style="text-align: center; padding: 40px 20px; color: var(--muted);">
          <ion-icon name="people-outline" style="font-size: 48px; opacity: 0.3;"></ion-icon>
          <p style="margin-top: 12px;">No students in your class yet.</p>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <!-- Edit Student Modal -->
  <div id="modalEditStudent" class="modal" aria-hidden="true">
    <div class="modal-panel">
      <header>
        <h3>Edit Student</h3>
        <button class="close-modal" data-close>&times;</button>
      </header>
      <form method="post">
        <input type="hidden" name="action" value="edit_student">
        <input type="hidden" name="id" id="edit_student_id">
        <div class="form-grid">
          <label>First Name<input id="edit_first_name" name="first_name" required></label>
          <label>Last Name<input id="edit_last_name" name="last_name" required></label>
          <label>Gender
            <select id="edit_gender" name="gender">
              <option value="">Select</option>
              <option value="M">Male</option>
              <option value="F">Female</option>
              <option value="O">Other</option>
            </select>
          </label>
          <label>Date of Birth<input id="edit_dob" name="dob" type="date"></label>
        </div>
        <div style="display: flex; gap: 8px; margin-top: 12px;">
          <button class="btn" type="submit">Update</button>
          <button type="button" class="btn outline" data-close>Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
  <script>
    const studentsData = <?= json_encode($students, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function openStudentEdit(id){
      const student = studentsData.find(s => s.id == id);
      if(!student) return;
      
      document.getElementById('edit_student_id').value = student.id;
      document.getElementById('edit_first_name').value = student.first_name;
      document.getElementById('edit_last_name').value = student.last_name;
      document.getElementById('edit_gender').value = student.gender || '';
      document.getElementById('edit_dob').value = student.dob || '';
      
      document.getElementById('modalEditStudent').setAttribute('aria-hidden','false');
      document.body.style.overflow = 'hidden';
    }

    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('.edit-student').forEach(btn => {
        btn.addEventListener('click', () => openStudentEdit(btn.dataset.id));
      });

      // Search functionality for cards
      document.getElementById('searchInput').addEventListener('input', function(){
        const query = this.value.toLowerCase();
        const cards = document.querySelectorAll('.cards-grid .card');
        cards.forEach(card => {
          const text = card.textContent.toLowerCase();
          card.style.display = text.includes(query) ? '' : 'none';
        });
      });
    });
  </script>
</body>
</html>
