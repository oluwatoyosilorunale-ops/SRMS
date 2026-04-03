<?php
require 'config.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher'){
    header('Location: index.php');
    exit;
}

$teacher_id = $_SESSION['user']['teacher_id'];

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resubmit') {
    $result_id = (int)($_POST['id'] ?? 0);
    $test1 = (float)($_POST['test1'] ?? 0);
    $test2 = (float)($_POST['test2'] ?? 0);
    $test3 = (float)($_POST['test3'] ?? 0);
    $exam = (float)($_POST['exam'] ?? 0);
    $total = $test1 + $test2 + $test3 + $exam;

    if($result_id > 0){
        $stmt = $pdo->prepare("UPDATE results SET test1 = ?, test2 = ?, test3 = ?, exam = ?, total = ?, status = 'pending', status_comment = '', reviewed_by = NULL, reviewed_at = NULL WHERE id = ? AND uploaded_by_teacher_id = ?");
        $stmt->execute([$test1, $test2, $test3, $exam, $total, $result_id, $teacher_id]);
        flash('success', 'Result updated and sent back to admin for approval.');
    }
    header('Location: teacher_result_status.php');
    exit;
}

$term = $_GET['term'] ?? '';
$session = $_GET['session'] ?? '';
$class = $_GET['class'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$query = "SELECT r.*, COALESCE(r.total, (r.test1 + r.test2 + r.test3 + r.exam)) AS total, st.first_name as student_first, st.last_name as student_last, st.student_id as student_registration, CONCAT(st.class, ' ', st.arm) as student_class, sub.name as subject_name
          FROM results r
          JOIN students st ON r.student_id = st.id
          JOIN subjects sub ON r.subject_id = sub.id
          WHERE r.uploaded_by_teacher_id = ?";
$params = [$teacher_id];
if($term){ $query .= " AND r.term = ?"; $params[] = $term; }
if($session){ $query .= " AND r.session = ?"; $params[] = $session; }
if($class){ $query .= " AND CONCAT(st.class, ' ', st.arm) = ?"; $params[] = $class; }
if($statusFilter){ $query .= " AND r.status = ?"; $params[] = $statusFilter; }
$query .= " ORDER BY r.status ASC, r.session DESC, r.term DESC, st.first_name";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll();

$classes = $pdo->query("SELECT class_name FROM classes ORDER BY class_name")->fetchAll(PDO::FETCH_COLUMN);
$terms = ['First Term','Second Term','Third Term'];
$sessions = $pdo->query("SELECT DISTINCT name FROM academic_sessions ORDER BY name DESC")->fetchAll(PDO::FETCH_COLUMN);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Uploaded Results Status</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-layout">
  <?php include 'partials/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div class="page-title">Uploaded Results Status</div>
    </header>

    <section class="card">
      <p style="color:var(--muted); margin-top:0;">Track your uploaded results and see admin feedback. Only approved results are visible to students.</p>
      <form method="GET" style="display:grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap:10px; margin-bottom:12px;">
        <select name="class"><option value="">All Classes</option><?php foreach($classes as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php echo $class===$c ? 'selected':''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach;?></select>
        <select name="term"><option value="">All Terms</option><?php foreach($terms as $t): ?><option value="<?php echo htmlspecialchars($t); ?>" <?php echo $term===$t ? 'selected':''; ?>><?php echo htmlspecialchars($t); ?></option><?php endforeach;?></select>
        <select name="session"><option value="">All Sessions</option><?php foreach($sessions as $s): ?><option value="<?php echo htmlspecialchars($s); ?>" <?php echo $session===$s ? 'selected':''; ?>><?php echo htmlspecialchars($s); ?></option><?php endforeach;?></select>
        <select name="status"><option value="">Any Status</option><option value="pending" <?= $statusFilter==='pending' ? 'selected':'' ?>>Pending</option><option value="approved" <?= $statusFilter==='approved' ? 'selected':'' ?>>Approved</option><option value="disapproved" <?= $statusFilter==='disapproved' ? 'selected':'' ?>>Disapproved</option></select>
        <button class="btn" type="submit">Filter</button>
      </form>
      <?php if(empty($results)): ?>
        <div class="no-results"><ion-icon name="document-text-outline" style="font-size: 48px; opacity: 0.3;"></ion-icon><p style="margin-top: 14px;">You haven't uploaded results yet.</p></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Student</th>
                <th>Class</th>
                <th>Subject</th>
                <th>Term/Session</th>
                <th>T1</th>
                <th>T2</th>
                <th>T3</th>
                <th>Exam</th>
                <th>Total</th>
                <th>Status</th>
                <th>Admin Note</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($results as $r): ?>
              <tr>
                <td><?=htmlspecialchars($r['student_first'].' '.$r['student_last'].' ('.$r['student_registration'].')')?></td>
                <td><?=htmlspecialchars($r['student_class'] ?? 'N/A')?></td>
                <td><?=htmlspecialchars($r['subject_name'])?></td>
                <td><?=htmlspecialchars($r['term'].' / '.$r['session'])?></td>
                <td><?=htmlspecialchars($r['test1'])?></td>
                <td><?=htmlspecialchars($r['test2'])?></td>
                <td><?=htmlspecialchars($r['test3'])?></td>
                <td><?=htmlspecialchars($r['exam'])?></td>
                <td><?=htmlspecialchars($r['total'])?></td>
                <td><span class="status-badge <?=strtolower($r['status'])?>"><?=htmlspecialchars(ucfirst($r['status']))?></span></td>
                <td><?=htmlspecialchars($r['status_comment'] ?? '-')?></td>
                <td>
                  <?php if($r['status'] !== 'approved'): ?>
                    <button class="link status-action <?=strtolower($r['status'])?>" type="button" onclick="openResubmitModal(<?= (int)$r['id'] ?>, <?= (float)$r['test1'] ?>, <?= (float)$r['test2'] ?>, <?= (float)$r['test3'] ?>, <?= (float)$r['exam'] ?>)">Edit</button>
                  <?php else: ?>
                    <span class="small">Locked</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <div id="resubmitModal" class="modal" aria-hidden="true">
    <div class="modal-panel" style="max-width:520px;">
      <header><h3>Edit Unapproved Result</h3><button class="close-modal" data-close>&times;</button></header>
      <form method="POST" id="resubmitForm" style="margin-top:10px;">
        <input type="hidden" name="action" value="resubmit">
        <input type="hidden" name="id" id="resubmit_id" value="">
        <label>Test 1<br><input type="number" step="0.1" min="0" max="100" name="test1" id="resubmit_test1" required></label>
        <label>Test 2<br><input type="number" step="0.1" min="0" max="100" name="test2" id="resubmit_test2" required></label>
        <label>Test 3<br><input type="number" step="0.1" min="0" max="100" name="test3" id="resubmit_test3" required></label>
        <label>Exam<br><input type="number" step="0.1" min="0" max="100" name="exam" id="resubmit_exam" required></label>
        <p style="font-size:13px; color:#707070; margin:8px 0;">Submitting will set status to pending and send result back to admin for review.</p>
        <div style="display:flex; gap:10px; margin-top:10px;">
          <button class="btn" type="submit">Send for Approval</button>
          <button type="button" class="btn outline" data-close>Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openResubmitModal(id, test1, test2, test3, exam){
      document.getElementById('resubmit_id').value = id;
      document.getElementById('resubmit_test1').value = test1;
      document.getElementById('resubmit_test2').value = test2;
      document.getElementById('resubmit_test3').value = test3;
      document.getElementById('resubmit_exam').value = exam;
      let modal = document.getElementById('resubmitModal');
      modal.setAttribute('aria-hidden','false');
      document.body.style.overflow='hidden';
    }
    document.querySelectorAll('[data-close]').forEach(btn=>btn.addEventListener('click', ()=>{
      let modal = document.getElementById('resubmitModal');
      modal.setAttribute('aria-hidden','true');
      document.body.style.overflow='';
    }));
  </script>

  <script src="assets/js/main.js"></script>
</body>
</html>