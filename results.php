<?php
require 'config.php';
require_login();

// handle add/update result entry
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_result'){
    $student_id = $_POST['student_id'];
    $subject_id = (int)$_POST['subject_id'];
    // use the term/session set by admin if none provided
    $term = $_POST['term'] ?? get_current_term();
    $session = $_POST['session'] ?? get_current_session();
    $test1 = (float)$_POST['test1'];
    $test2 = (float)$_POST['test2'];
    $test3 = (float)$_POST['test3'];
    $exam = (float)$_POST['exam'];

    // Get student id for upsert
    $studentRecord = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
    $studentRecord->execute([$student_id]);
    $studentData = $studentRecord->fetch();
    if(!$studentData){
        flash('error','Student not found.');
        header('Location: results.php');
        exit;
    }
    $student_db_id = $studentData['id'];

    // Check if result already exists for this student/subject/term/session
    $check = $pdo->prepare("SELECT id FROM results WHERE student_id = ? AND subject_id = ? AND term = ? AND session = ?");
    $check->execute([$student_db_id, $subject_id, $term, $session]);
    if($check->fetch()){
        flash('error', 'Result already exists for this student and subject in this session/term. Delete the existing result first if you need to change it.');
        header('Location: results.php');
        exit;
    }
    
    // Insert new result
    $pdo->prepare("INSERT INTO results (student_id, subject_id, term, session, test1, test2, test3, exam) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$student_db_id, $subject_id, $term, $session, $test1, $test2, $test3, $exam]);
    flash('success','Result saved.');
    header('Location: results.php');
    exit;
}

// EDIT result
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_result'){
    $result_id = (int)($_POST['id'] ?? 0);
    $test1 = (float)$_POST['test1'];
    $test2 = (float)$_POST['test2'];
    $test3 = (float)$_POST['test3'];
    $exam = (float)$_POST['exam'];

    if($result_id > 0){
        $pdo->prepare("UPDATE results SET test1 = ?, test2 = ?, test3 = ?, exam = ? WHERE id = ?")
            ->execute([$test1, $test2, $test3, $exam, $result_id]);
        flash('success','Result updated.');
    }
    header('Location: results.php');
    exit;
}

// DELETE result
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM results WHERE id=?")->execute([$id]);
    flash('success','Result deleted.');
    header("Location: results.php");
    exit;
}

// DELETE selected results
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_selected'){
    if(isset($_POST['selected']) && is_array($_POST['selected'])){
        foreach($_POST['selected'] as $id){
            $pdo->prepare("DELETE FROM results WHERE id=?")->execute([(int)$id]);
        }
        flash('success','Selected results deleted.');
    }
    header('Location: results.php');
    exit;
}

// fetch all data
$students = $pdo->query("SELECT id, student_id, first_name, last_name, class, arm FROM students ORDER BY first_name")->fetchAll();
$subjects = $pdo->query("SELECT id, name, class, arm FROM subjects ORDER BY name")->fetchAll();
$classRows = $pdo->query("SELECT class_name FROM classes ORDER BY class_name")->fetchAll(PDO::FETCH_COLUMN);
$classes = array();
foreach($classRows as $className) {
    $classes[] = array('class_arm' => $className, 'class' => $className);
}
$sessions = $pdo->query("SELECT DISTINCT session FROM results WHERE session IS NOT NULL AND session != '' ORDER BY session DESC")->fetchAll(PDO::FETCH_ASSOC);

// Search and filter
$searchQuery = $_GET['search'] ?? '';
$filterClass = $_GET['class'] ?? '';
$filterTerm = $_GET['term'] ?? '';
$filterSession = $_GET['session'] ?? '';

// Build query for results with search/filter
$query = "SELECT r.id, r.student_id as result_student_id, r.subject_id, r.term, r.test1, r.test2, r.test3, r.exam, 
          (r.test1 + r.test2 + r.test3 + r.exam) AS total,
          CASE 
            WHEN (r.test1 + r.test2 + r.test3 + r.exam) >= 70 THEN 'A'
            WHEN (r.test1 + r.test2 + r.test3 + r.exam) >= 60 THEN 'B'
            WHEN (r.test1 + r.test2 + r.test3 + r.exam) >= 50 THEN 'C'
            WHEN (r.test1 + r.test2 + r.test3 + r.exam) >= 45 THEN 'D'
            ELSE 'F'
          END AS grade,
          s.first_name, s.last_name, s.student_id, s.class, s.arm as student_arm, subj.name as subject_name, subj.class as subject_class, subj.arm as subject_arm 
          FROM results r 
          JOIN students s ON r.student_id = s.id 
          JOIN subjects subj ON r.subject_id = subj.id 
          WHERE 1=1";
$params = [];

if($searchQuery){
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ? OR subj.name LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if($filterClass){
    $query .= " AND s.class = ?";
    $params[] = $filterClass;
}

if($filterTerm){
    $query .= " AND r.term = ?";
    $params[] = $filterTerm;
}
if($filterSession){
    $query .= " AND r.session = ?";
    $params[] = $filterSession;
}

$query .= " ORDER BY s.first_name, r.session DESC, r.term DESC, subj.name";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Add class position for each student
$classPositions = [];
foreach($results as &$r){
    $studentClass = $r['class'] . '_' . ($r['student_arm'] ?? 'A');
    $studentId = $r['result_student_id'];
    if(!isset($classPositions[$studentClass])){
        // Build position query with filters applied BEFORE GROUP BY
        $posQuery = "SELECT s.id, SUM(res.test1 + res.test2 + res.test3 + res.exam) as total 
                    FROM results res 
                    JOIN students s ON res.student_id = s.id 
                    WHERE s.class = ? AND s.arm = ?";
        $posParams = [$r['class'], $r['student_arm'] ?? 'A'];
        
        // Add filter conditions to WHERE clause (before GROUP BY)
        if($filterTerm && $filterSession){
            $posQuery .= " AND res.term = ? AND res.session = ?";
            $posParams[] = $filterTerm;
            $posParams[] = $filterSession;
        } elseif($filterTerm){
            $posQuery .= " AND res.term = ?";
            $posParams[] = $filterTerm;
        } elseif($filterSession){
            $posQuery .= " AND res.session = ?";
            $posParams[] = $filterSession;
        }
        
        // Now add GROUP BY and ORDER BY after WHERE clause is complete
        $posQuery .= " GROUP BY s.id ORDER BY total DESC";
        
        $posStmt = $pdo->prepare($posQuery);
        $posStmt->execute($posParams);
        $classStudents = $posStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($classStudents as $idx => $cs){
            if(!isset($classPositions[$studentClass])) {
                $classPositions[$studentClass] = [];
            }
            $classPositions[$studentClass][$cs['id']] = $idx + 1;
        }

    }
    $r['class_position'] = $classPositions[$studentClass][$studentId] ?? '-';
}
unset($r);

// Prepare results data for edit modal
$resultsForEdit = json_encode($results, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Results Management</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .search-section {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
      margin-bottom: 18px;
      align-items: end;
    }
    .search-section input,
    .search-section select {
      width: 100%;
      padding: 10px;
      border-radius: 8px;
      border: 1px solid #eef6ff;
      background: #fafcff;
      outline: none;
      font-size: 13px;
      font-family: 'Poppins', system-ui;
    }
    .search-actions {
      display: flex;
      gap: 8px;
    }
    .grade-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 12px;
    }
    .grade-badge.a { background: #c8f7c5; color: #207245; }
    .grade-badge.b { background: #d4f1fa; color: #176ba0; }
    .grade-badge.c { background: #fff7c0; color: #a08a17; }
    .grade-badge.d { background: #ffe0b2; color: #a05d17; }
    .grade-badge.f { background: #ffd6d6; color: #a01717; }
    .score-cell {
      font-weight: 600;
      color: var(--accent1);
    }
    .no-results {
      text-align: center;
      padding: 40px 20px;
      color: var(--muted);
    }
  </style>
</head>
<body class="app-layout">
  <?php include 'partials/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div class="page-title">Results Management</div>
      <div class="top-actions">
        <button id="openAddResult" class="btn">+ Add Result</button>
        <button id="deleteSelected" class="btn danger" type="submit" form="bulkForm" style="display:none;" data-confirm="Are you sure you want to delete the selected results?" data-confirm-name="Selected results">Delete Selected</button>
      </div>
    </header>

    <section class="card">
      <?php if($m = flash('success')): ?><div class="toast success"><?=$m?></div><?php endif; ?>
      <?php if($m = flash('error')): ?><div class="toast error"><?=$m?></div><?php endif; ?>

      <!-- Search and Filter Section -->
      <div class="search-section">
        <form method="get" style="display: contents;">
          <input type="text" name="search" placeholder="Search by name, ID, or subject..." value="<?=htmlspecialchars($searchQuery)?>">
          <select name="class">
            <option value="">All Classes</option>
            <?php foreach($classes as $c): ?>
              <option value="<?=htmlspecialchars($c['class'])?>" <?=$filterClass === $c['class'] ? 'selected' : ''?>>
                <?=htmlspecialchars($c['class'])?>
              </option>
            <?php endforeach; ?>
          </select>
          <select name="term">
            <option value="">All Terms</option>
            <option value="First Term" <?=$filterTerm === 'First Term' ? 'selected' : ''?>>First Term</option>
            <option value="Second Term" <?=$filterTerm === 'Second Term' ? 'selected' : ''?>>Second Term</option>
            <option value="Third Term" <?=$filterTerm === 'Third Term' ? 'selected' : ''?>>Third Term</option>
          </select>
          <select name="session">
            <option value="">All Sessions</option>
            <?php foreach($sessions as $s): ?>
              <option value="<?=htmlspecialchars($s['session'])?>" <?=$filterSession === $s['session'] ? 'selected' : ''?>>
                <?=htmlspecialchars($s['session'])?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="search-actions">
            <button class="btn" type="submit">Search</button>
            <a href="results.php" class="btn outline">Clear</a>
          </div>
        </form>
      </div>

      <hr style="margin: 18px 0;">

      <!-- Results Table -->
      <?php if(count($results) > 0): ?>
        <form method="post" id="bulkForm">
          <input type="hidden" name="action" value="delete_selected">
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th><input type="checkbox" id="selectAll"></th>
                  <th>Student Name</th>
                  <th>Reg. No</th>
                  <th>Class</th>
                  <th>Position</th>
                  <th>Subject</th>
                  <th>Term</th>
                  <th>T1</th>
                  <th>T2</th>
                  <th>T3</th>
                  <th>Exam</th>
                  <th>Total</th>
                  <th>Grade</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($results as $r): ?>
                  <tr>
                    <td><input type="checkbox" name="selected[]" value="<?=$r['id']?>"></td>
                    <td><?=htmlspecialchars($r['first_name'].' '.$r['last_name'])?></td>
                    <td><?=htmlspecialchars($r['student_id'])?></td>
                    <td><?=htmlspecialchars($r['class'] . ' ' . ($r['student_arm'] ?? 'A'))?></td>
                    <td><strong style="color:#0f74ff;font-size:16px;"><?=$r['class_position']?></strong></td>
                    <td><?=htmlspecialchars($r['subject_name'])?></td>
                    <td><?=htmlspecialchars($r['term'])?></td>
                    <td class="score-cell"><?=$r['test1']?></td>
                    <td class="score-cell"><?=$r['test2']?></td>
                    <td class="score-cell"><?=$r['test3']?></td>
                    <td class="score-cell"><?=$r['exam']?></td>
                    <td class="score-cell"><?=$r['total']?></td>
                    <td>
                      <span class="grade-badge <?=strtolower($r['grade'])?>"><?=$r['grade']?></span>
                    </td>
                    <td class="actions">
                      <a class="link" href="view_result.php?student_id=<?=urlencode($r['student_id'])?>&amp;term=<?=urlencode($r['term'])?>&amp;session=<?=urlencode($r['session'] ?? '')?>" title="View full result"><ion-icon name="eye-outline"></ion-icon></a>
                      <button type="button" class="link edit-result" data-id="<?= (int)$r['id'] ?>" title="Edit result"><ion-icon name="pencil-outline"></ion-icon></button>
                      <a class="link" href="results.php?delete=<?=$r['id']?>" title="Delete" data-confirm="Delete result for %s?" data-confirm-name="<?=htmlspecialchars($r['first_name'].' '.$r['last_name'])?>"><ion-icon name="trash-outline"></ion-icon></a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </form>
      <?php else: ?>
        <div class="no-results">
          <ion-icon name="search-outline" style="font-size: 48px; opacity: 0.3;"></ion-icon>
          <p style="margin-top: 12px;">No results found. Try adjusting your search filters.</p>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <!-- Add Result Modal -->
  <div id="modalAddResult" class="modal" aria-hidden="true">
    <div class="modal-panel">
      <header>
        <h3>Add New Result</h3>
        <button class="close-modal" data-close>&times;</button>
      </header>
      <form method="post">
        <input type="hidden" name="action" value="add_result">
        <div class="form-grid">
          <label>Student
            <select id="studentSelect" name="student_id" required>
              <option value="">Select a student</option>
              <?php foreach($students as $st): ?>
                <option value="<?=htmlspecialchars($st['student_id'])?>" data-class="<?=htmlspecialchars($st['class'] . ' ' . ($st['arm'] ?? 'A'))?>"><?=htmlspecialchars($st['first_name'].' '.$st['last_name'].' ('.$st['student_id'].')')?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label>Class
            <input type="text" id="classDisplay" readonly placeholder="Select a student first">
          </label>

          <label>Subject
            <select id="subjectSelect" name="subject_id" required>
              <option value="">Select subject</option>
              <?php foreach($subjects as $s): ?>
                <option value="<?=$s['id']?>" data-class="<?=htmlspecialchars($s['class'] . ' ' . ($s['arm'] ?? 'A'))?>"><?=htmlspecialchars($s['name'])?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <!-- term/session are fixed by admin; keep hidden but show current values -->
          <input type="hidden" name="term" value="<?=htmlspecialchars(get_current_term())?>">
          <input type="hidden" name="session" value="<?=htmlspecialchars(get_current_session())?>">
          <label>Term
            <input type="text" value="<?=htmlspecialchars(get_current_term())?>" readonly>
          </label>
          <label>Session
            <input type="text" value="<?=htmlspecialchars(get_current_session()?:'N/A')?>" readonly>
          </label>

          <label>Test 1 (0-15)<input type="number" name="test1" min="0" max="15" value="0" required></label>
          <label>Test 2 (0-15)<input type="number" name="test2" min="0" max="15" value="0" required></label>
          <label>Test 3 (0-10)<input type="number" name="test3" min="0" max="10" value="0" required></label>
          <label>Exam (0-60)<input type="number" name="exam" min="0" max="60" value="0" required></label>
        </div>

        <div style="display: flex; gap: 8px; margin-top: 12px;">
          <button class="btn" type="submit">Save Result</button>
          <button type="button" class="btn outline" data-close>Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Result Modal -->
  <div id="modalEditResult" class="modal" aria-hidden="true">
    <div class="modal-panel">
      <header>
        <h3>Edit Result</h3>
        <button class="close-modal" data-close>&times;</button>
      </header>
      <form method="post">
        <input type="hidden" name="action" value="edit_result">
        <input type="hidden" name="id" id="edit_result_id">
        <div class="form-grid">
          <label>Student
            <input type="text" id="edit_student_name" readonly>
          </label>

          <label>Class
            <input type="text" id="edit_result_class" readonly>
          </label>

          <label>Subject
            <input type="text" id="edit_subject_name" readonly>
          </label>

          <label>Term
            <input type="text" id="edit_result_term" readonly>
          </label>

          <label>Test 1 (0-15)<input type="number" id="edit_test1" name="test1" min="0" max="15" step="0.01" required></label>

          <label>Test 2 (0-15)<input type="number" id="edit_test2" name="test2" min="0" max="15" step="0.01" required></label>

          <label>Test 3 (0-10)<input type="number" id="edit_test3" name="test3" min="0" max="10" step="0.01" required></label>

          <label>Exam (0-60)<input type="number" id="edit_exam" name="exam" min="0" max="60" step="0.01" required></label>
        </div>

        <div style="display: flex; gap: 8px; margin-top: 12px;">
          <button class="btn" type="submit">Update Result</button>
          <button type="button" class="btn outline" data-close>Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
  <script>
    const resultsData = <?php echo $resultsForEdit; ?>;

    // Add result modal opener
    document.getElementById('openAddResult').addEventListener('click', () => {
      const modal = document.getElementById('modalAddResult');
      if(modal) {
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
      }
    });

    // Edit result functionality
    function openEditResult(resultId){
      const result = resultsData.find(r => r.id == resultId);
      if(!result) return;
      
      document.getElementById('edit_result_id').value = result.id;
      document.getElementById('edit_student_name').value = result.first_name + ' ' + result.last_name + ' (' + result.student_id + ')';
      document.getElementById('edit_result_class').value = result.class;
      document.getElementById('edit_subject_name').value = result.subject_name;
      document.getElementById('edit_result_term').value = result.term;
      document.getElementById('edit_test1').value = result.test1;
      document.getElementById('edit_test2').value = result.test2;
      document.getElementById('edit_test3').value = result.test3;
      document.getElementById('edit_exam').value = result.exam;
      
      const modal = document.getElementById('modalEditResult');
      if(modal) {
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
      }
    }

    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('.edit-result').forEach(btn => {
        btn.addEventListener('click', () => openEditResult(btn.dataset.id));
      });
    });


    // Smart form handling: auto-fill class and filter subjects
    const studentSelect = document.getElementById('studentSelect');
    const classDisplay = document.getElementById('classDisplay');
    const subjectSelect = document.getElementById('subjectSelect');
    const allSubjectOptions = Array.from(subjectSelect.querySelectorAll('option'));

    studentSelect.addEventListener('change', function() {
      const selectedOption = this.options[this.selectedIndex];
      const studentClass = selectedOption.getAttribute('data-class');
      
      if(studentClass) {
        classDisplay.value = studentClass;
        
        // Filter subjects to only show subjects for this class
        subjectSelect.innerHTML = '<option value="">Select subject</option>';
        allSubjectOptions.forEach(option => {
          if(option.value === '') return;
          if(option.getAttribute('data-class') === studentClass) {
            subjectSelect.appendChild(option.cloneNode(true));
          }
        });
      } else {
        classDisplay.value = '';
        subjectSelect.innerHTML = '<option value="">Select subject first</option>';
      }
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
  </script>
</body>
</html>
