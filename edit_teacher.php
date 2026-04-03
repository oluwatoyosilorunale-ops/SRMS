<?php
require 'config.php';
require_login();

$teacher = null;
$id = $_GET['id'] ?? null;

if(!$id){
    header('Location: teachers.php');
    exit;
}

// Fetch teacher
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->execute([$id]);
$teacher = $stmt->fetch();

if(!$teacher){
    header('Location: teachers.php');
    exit;
}

// Handle update
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $pan_id = $_POST['pan_id'] ?? '';
    $isClassTeacher = isset($_POST['is_class_teacher']) && $_POST['is_class_teacher'] === '1' ? 1 : 0;
    $managed_class = $isClassTeacher ? trim($_POST['managed_class'] ?? '') : null;

    if($isClassTeacher && empty($managed_class)){
        flash('error', 'Class teachers must have an assigned class/arm.');
        header('Location: edit_teacher.php?id=' . urlencode($id));
        exit;
    }

    $imgName = $teacher['image'];
    
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imgName = 'teacher_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $target = __DIR__.'/uploads/'.$imgName;
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
    }

    $pdo->prepare("UPDATE teachers SET full_name=?, email=?, pan_id=?, image=?, is_class_teacher=?, managed_class=? WHERE id=?")
        ->execute([$full_name, $email, $pan_id, $imgName, $isClassTeacher, $managed_class, $id]);
    
    flash('success','Teacher updated successfully.');
    header('Location: teachers.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Edit Teacher</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-layout">
  <?php include 'partials/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div class="page-title">Edit Teacher</div>
    </header>

    <section class="card">
      <form method="post" enctype="multipart/form-data">
        <div class="form-grid">
          <label>Full Name<input name="full_name" value="<?=htmlspecialchars($teacher['full_name'])?>" required></label>
          <label>Email<input name="email" type="email" value="<?=htmlspecialchars($teacher['email'])?>"></label>
          <label>PAN ID<input name="pan_id" value="<?=htmlspecialchars($teacher['pan_id'])?>"></label>
          <label>Change Image<input name="image" type="file" accept="image/*"></label>
          <label style="grid-column:1/-1;margin-top:10px;"><strong>Teacher Role</strong></label>
          <label style="display:flex;align-items:center;gap:8px;">
            <input type="radio" name="is_class_teacher" value="0" <?php echo $teacher['is_class_teacher'] ? '' : 'checked'; ?>> Subject Teacher
          </label>
          <label style="display:flex;align-items:center;gap:8px;">
            <input type="radio" name="is_class_teacher" value="1" id="editIsClassTeacher" <?php echo $teacher['is_class_teacher'] ? 'checked' : ''; ?>> Class Teacher
          </label>
          <label id="editManagedClassLabel" style="grid-column: 1 / -1; display:<?php echo $teacher['is_class_teacher'] ? 'block' : 'none'; ?>;">
            Managed Class
            <select name="managed_class" id="editManagedClass">
              <option value="">Select class + arm</option>
              <?php
              $classOptions = $pdo->query("SELECT DISTINCT CONCAT(class, ' ', arm) AS class_arm FROM students ORDER BY class, arm")->fetchAll(PDO::FETCH_COLUMN);
              foreach($classOptions as $option):
              ?>
                <option value="<?=htmlspecialchars($option)?>" <?=($teacher['managed_class'] == $option ? 'selected' : '')?>><?=htmlspecialchars($option)?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div style="display:flex; gap:8px; margin-top:12px;">
          <button class="btn" type="submit">Update</button>
          <a href="teachers.php" class="btn outline">Cancel</a>
        </div>
      </form>
    </section>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', function(){
      const classRadio = document.querySelector('input[name="is_class_teacher"][value="1"]');
      const subjectRadio = document.querySelector('input[name="is_class_teacher"][value="0"]');
      const manageLabel = document.getElementById('editManagedClassLabel');
      const managedClassSelect = document.getElementById('editManagedClass');
      function toggleManagedClass() {
        if(classRadio.checked){
          manageLabel.style.display = 'block';
          managedClassSelect.required = true;
        } else {
          manageLabel.style.display = 'none';
          managedClassSelect.required = false;
          managedClassSelect.value = '';
        }
      }
      classRadio.addEventListener('change', toggleManagedClass);
      subjectRadio.addEventListener('change', toggleManagedClass);
      toggleManagedClass();
    });
  </script>

</body>
</html>

  <script src="assets/js/main.js"></script>
</body>
</html>