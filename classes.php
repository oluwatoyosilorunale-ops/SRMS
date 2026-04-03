<?php
require 'config.php';
require_login();
$user = $_SESSION['user'];
$_SESSION['display'] = $user['display_name'];

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(isset($_POST['action'])){
        if($_POST['action'] === 'add_class'){
            $newClass = trim($_POST['class_name'] ?? '');
            
            if($newClass){
                try {
                    // Check master class list
                    $check = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE class_name = ?");
                    $check->execute([$newClass]);
                    if($check->fetchColumn() == 0){
                        $stmt = $pdo->prepare("INSERT INTO classes (class_name) VALUES (?)");
                        $stmt->execute([$newClass]);
                        flash('success', 'Class added. You can now assign students to it.');
                    } else {
                        flash('error', 'Class already exists.');
                    }
                } catch (Exception $e) {
                    flash('error', 'Database error: ' . $e->getMessage());
                }
            } else {
                flash('error', 'Class name cannot be empty.');
            }
            header('Location: classes.php');
            exit;
        } elseif($_POST['action'] === 'edit_class'){
            $oldClass = trim($_POST['old_class'] ?? '');
            $newClass = trim($_POST['new_class_name'] ?? '');
            
            if($newClass && $newClass !== $oldClass){
                try {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE class_name = ?");
                    $check->execute([$newClass]);
                    if($check->fetchColumn() == 0){
                        $pdo->prepare("UPDATE classes SET class_name = ? WHERE class_name = ?")->execute([$newClass, $oldClass]);
                        $pdo->prepare("UPDATE students SET class = ? WHERE class = ?")->execute([$newClass, $oldClass]);
                        flash('success', 'Class renamed successfully.');
                    } else {
                        flash('error', 'New class name already exists.');
                    }
                } catch (Exception $e) {
                    flash('error', 'Database error: ' . $e->getMessage());
                }
            } else {
                flash('error', 'New class name cannot be empty or same as old.');
            }
            header('Location: classes.php');
            exit;
        }
    }
}

// DELETE selected classes
if(isset($_GET['delete'])){
    $ids = explode(',', $_GET['delete']);
    foreach($ids as $class){
        $pdo->prepare("DELETE FROM classes WHERE class_name = ?")->execute([$class]);
    }
    flash('success', count($ids) . ' classes deleted.');
    header('Location: classes.php');
    exit;
}

// Get classes from table
$classes = $pdo->query("SELECT class_name FROM classes ORDER BY class_name")->fetchAll(PDO::FETCH_COLUMN);

// Sort classes logically
$order = ['Nursery', 'Pre-Primary', 'Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5', 'Primary 6', 'JSS1', 'JSS2', 'JSS3', 'SS1', 'SS2', 'SS3'];
usort($classes, function($a, $b) use ($order) {
    $posA = array_search($a, $order) !== false ? array_search($a, $order) : 999;
    $posB = array_search($b, $order) !== false ? array_search($b, $order) : 999;
    return $posA <=> $posB;
});

$classData = [];
foreach($classes as $class){
    $arms = $pdo->prepare("SELECT DISTINCT arm FROM students WHERE class = ? ORDER BY arm");
    $arms->execute([$class]);
    $armList = $arms->fetchAll(PDO::FETCH_COLUMN);
    
    $totalStudents = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class = ?");
    $totalStudents->execute([$class]);
    $total = $totalStudents->fetchColumn();
    
    $armCounts = [];
    foreach($armList as $arm){
        $count = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class = ? AND arm = ?");
        $count->execute([$class, $arm]);
        $armCounts[$arm] = $count->fetchColumn();
    }
    
    $classData[$class] = [
        'arms' => $armCounts,
        'total' => $total
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classes - MABEST Academy</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body class="app-layout">
  <?php include 'partials/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div class="page-title">Classes Management</div>
      <div class="top-actions">
        <button class="btn" data-modal="modalAddClass">+ Add New Class</button>
      </div>
    </header>
    <?php if($m = flash('success')): ?><div class="toast success"><?php echo htmlspecialchars($m); ?></div><?php endif; ?>
    <?php if($e = flash('error')): ?><div class="toast error"><?php echo htmlspecialchars($e); ?></div><?php endif; ?>
        
        <section>
            <form method="get">
                <div class="table-wrap">
                    <table class="table">
                    <thead><tr><th><input type="checkbox" id="selectAll"></th><th>Class</th><th>Arms & Student Counts</th><th>Total Students</th><th>Actions</th></tr></thead>
                                    <tbody>
                    <?php foreach($classData as $class => $data): ?>
                        <tr>
                            <td><input type="checkbox" name="selected[]" value="<?php echo htmlspecialchars($class); ?>"></td>
                            <td><?php echo htmlspecialchars($class); ?></td>
                            <td>
                                <?php if(!empty($data['arms'])): ?>
                                    <?php foreach($data['arms'] as $arm => $count): ?>
                                        Arm <?php echo htmlspecialchars($arm); ?>: <?php echo $count; ?> students<br>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <em>No students in this class yet</em>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $data['total']; ?></td>
                            <td>
                                <button class="btn small" style="background: linear-gradient(90deg, #0f74ff, #0066c6); color: #fff; border: none;" data-modal="modalEditClass" data-class="<?php echo htmlspecialchars($class); ?>">✏️ Edit</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($classData)): ?>
                        <tr><td colspan="5" style="text-align:center;">No classes found. Add a class to begin.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </form>
                <?php if(!empty($classData)): ?>
                    <div style="margin-top:16px; display:flex; gap:8px;">
                        <a id="deleteSelected" class="btn danger" style="cursor:pointer; display:none;" data-confirm="Delete selected classes?" data-confirm-name="Selected classes" data-confirm-method="GET">Delete Selected</a>
                    </div>
                <?php endif; ?>
        </section>
    </main>

    <!-- Add Class Modal -->
    <div id="modalAddClass" class="modal" aria-hidden="true">
        <div class="modal-panel">
            <header>
                <h3>Add New Class</h3>
                <button class="close-modal" data-close>&times;</button>
            </header>
            <form method="post">
                <input type="hidden" name="action" value="add_class">
                <div class="form-grid">
                    <label>Class Name<input name="class_name" required placeholder="e.g., JSS1, SS2"></label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn">Add Class</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Class Modal -->
    <div id="modalEditClass" class="modal" aria-hidden="true">
        <div class="modal-panel">
            <header>
                <h3>Edit Class</h3>
                <button class="close-modal" data-close>&times;</button>
            </header>
            <form method="post">
                <input type="hidden" name="action" value="edit_class">
                <input type="hidden" name="old_class" id="edit_old_class">
                <div class="form-grid">
                    <label>New Class Name<input name="new_class_name" id="edit_class_name" required></label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn">Update Class</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // Handle edit modal - pre-fill data when modal opens
        document.addEventListener('DOMContentLoaded', function(){
            document.querySelectorAll('[data-modal="modalEditClass"]').forEach(btn => {
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    const className = this.getAttribute('data-class');
                    document.getElementById('edit_old_class').value = className;
                    document.getElementById('edit_class_name').value = className;
                });
            });
        });

        // Select all checkboxes for bulk delete
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
    </script>
</body>
</html>