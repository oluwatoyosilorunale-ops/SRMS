<?php
require 'config.php';
require_login();
if($_SESSION['user']['role'] !== 'admin'){
    header('Location: index.php');
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $action = $_POST['action'] ?? '';
    $ids = $_POST['selected'] ?? [];
    $comment = trim($_POST['comment'] ?? '');
    $admin_name = $_SESSION['user']['display_name'] ?? 'Admin';
    $now = date('Y-m-d H:i:s');

    if($action === 'bulk_update' && is_array($ids) && count($ids) > 0){
        $status = $_POST['bulk_status'] ?? 'pending';
        $sql = "UPDATE results SET status = ?, status_comment = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        foreach($ids as $id){
            $stmt->execute([$status, $comment ?: null, $admin_name, $now, (int)$id]);
        }
        flash('success', 'Bulk update applied to selected results.');
        header('Location: approve_results.php');
        exit;
    }

    if(in_array($action, ['approve', 'pend', 'disapprove'])){
        $id = (int)($_POST['id'] ?? 0);
        $status = ($action === 'approve' ? 'approved' : ($action === 'disapprove' ? 'disapproved' : 'pending'));
        $statusComment = trim($_POST['status_comment'] ?? '');

        $stmt = $pdo->prepare("UPDATE results SET status = ?, status_comment = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?");
        $stmt->execute([$status, $statusComment ?: null, $admin_name, $now, $id]);

        flash('success', 'Result has been marked as '.ucfirst($status).'.');
        header('Location: approve_results.php');
        exit;
    }
}

$term = $_GET['term'] ?? '';
$session = $_GET['session'] ?? '';
$class = $_GET['class'] ?? '';
$statusFilter = $_GET['status'] ?? 'pending';

$query = "SELECT r.*, s.first_name AS student_first, s.last_name AS student_last, s.student_id AS student_registration, sub.name AS subject_name, t.full_name AS uploaded_by_name FROM results r JOIN students s ON r.student_id=s.id JOIN subjects sub ON r.subject_id=sub.id LEFT JOIN teachers t ON r.uploaded_by_teacher_id=t.teacher_id WHERE 1=1";
$params = [];

if($term){ $query .= " AND r.term = ?"; $params[] = $term; }
if($session){ $query .= " AND r.session = ?"; $params[] = $session; }
if($class){ 
    $parts = explode(' ', $class);
    $className = $parts[0];
    $arm = $parts[1] ?? 'A';
    $query .= " AND s.class = ? AND s.arm = ?"; 
    $params[] = $className;
    $params[] = $arm;
}
if($statusFilter){ $query .= " AND r.status = ?"; $params[] = $statusFilter; }

$query .= " ORDER BY r.status ASC, r.session DESC, r.term DESC, s.first_name";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll();

$classes = $pdo->query("SELECT DISTINCT CONCAT(s.class, ' ', s.arm) as class_arm FROM students s ORDER BY s.class, s.arm")->fetchAll(PDO::FETCH_COLUMN);
$terms = ['First Term','Second Term','Third Term'];
// academic_sessions table uses `name` field for session names
$sessions = $pdo->query("SELECT DISTINCT name FROM academic_sessions ORDER BY name DESC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Approve Results — School RMS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-layout">
  <?php include 'partials/sidebar.php'; ?>
  <main class="main-content">
    <header class="topbar">
      <div class="page-title">Approve Results</div>
    </header>

    <section class="card">
      <?php if($m=flash('success')): ?><div class="toast success"><?php echo htmlspecialchars($m); ?></div><?php endif; ?>
      <?php if($m=flash('error')): ?><div class="toast error"><?php echo htmlspecialchars($m); ?></div><?php endif; ?>

      <form method="GET" style="display:grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap:10px; margin-bottom:12px;">
        <select name="class"><option value="">All Classes</option><?php foreach($classes as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php echo $class===$c ? 'selected':''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach;?></select>
        <select name="term"><option value="">All Terms</option><?php foreach($terms as $t): ?><option value="<?php echo htmlspecialchars($t); ?>" <?php echo $term===$t ? 'selected':''; ?>><?php echo htmlspecialchars($t); ?></option><?php endforeach;?></select>
        <select name="session"><option value="">All Sessions</option><?php foreach($sessions as $s): ?><option value="<?php echo htmlspecialchars($s); ?>" <?php echo $session===$s ? 'selected':''; ?>><?php echo htmlspecialchars($s); ?></option><?php endforeach;?></select>
        <select name="status"><option value="">Any Status</option><option value="pending" <?php echo $statusFilter==='pending' ? 'selected':''; ?>>Pending</option><option value="approved" <?php echo $statusFilter==='approved' ? 'selected':''; ?>>Approved</option><option value="disapproved" <?php echo $statusFilter==='disapproved' ? 'selected':''; ?>>Disapproved</option></select>
        <button class="btn" type="submit">Filter</button>
      </form>

      <form method="POST" id="bulkForm">
        <input type="hidden" name="action" value="bulk_update">
        <div style="display:flex; gap:8px; align-items:center; margin-bottom:12px;">
          <select name="bulk_status" required>
            <option value="pending">Mark Pending</option>
            <option value="approved">Mark Approved</option>
            <option value="disapproved">Mark Disapproved</option>
          </select>
          <input type="text" name="comment" placeholder="Bulk comment (optional)" style="flex:1; padding:8px; border-radius:6px; border:1px solid #eef6ff;">
          <button class="btn" type="submit">Apply Bulk</button>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th><input type="checkbox" id="selectAll"></th>
                <th>Student</th>
                <th>Class</th>
                <th>Subject</th>
                <th>Term/Session</th>
                <th>Score</th>
                <th>Teacher</th>
                <th>Status</th>
                <th>Admin Note</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($results)): ?>
                <tr><td colspan="10" style="text-align:center; padding:16px;color:var(--muted);">No results found for selected filter.</td></tr>
              <?php endif; ?>
              <?php foreach($results as $r): ?>
              <tr>
                <td><input type="checkbox" name="selected[]" value="<?php echo (int)$r['id']; ?>"></td>
                <td><?php echo htmlspecialchars($r['student_first'].' '.$r['student_last'].' ('.$r['student_registration'].')'); ?></td>
                <td><?php echo htmlspecialchars($r['class'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($r['subject_name']); ?></td>
                <td><?php echo htmlspecialchars($r['term'].' / '.$r['session']); ?></td>
                <td><?php echo htmlspecialchars($r['test1']+$r['test2']+$r['test3']+$r['exam']); ?></td>
                <td><?php echo htmlspecialchars($r['uploaded_by_teacher_name'].' ('.$r['uploaded_by_teacher_id'].')'); ?></td>
                <td><span class="status-badge <?=strtolower($r['status'])?>"><?=htmlspecialchars(ucfirst($r['status']))?></span></td>
                <td><?=htmlspecialchars($r['status_comment'] ?? '')?></td>
                <td>
                  <form method="POST" style="display:inline-block;">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="status_comment" value="<?=htmlspecialchars($r['status_comment'] ?? '')?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="link status-action approve" type="submit" title="Approve"><ion-icon name="checkmark-done-outline"></ion-icon></button>
                  </form>
                  <button class="link status-action pending" type="button" onclick="openReviewPrompt(<?= (int)$r['id'] ?>, 'pending', '<?=htmlspecialchars(addslashes($r['status_comment'] ?? ''))?>')" title="Pend"><ion-icon name="time-outline"></ion-icon></button>
                  <button class="link status-action disapproved" type="button" onclick="openReviewPrompt(<?= (int)$r['id'] ?>, 'disapprove', '<?=htmlspecialchars(addslashes($r['status_comment'] ?? ''))?>')" title="Disapprove"><ion-icon name="close-outline"></ion-icon></button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </form>
    </section>
  </main>

  <div id="modalReview" class="modal" aria-hidden="true">
    <div class="modal-panel" style="max-width:520px;">
      <header><h3>Review Result</h3><button class="close-modal" data-close>&times;</button></header>
      <form method="POST" id="reviewForm" style="margin-top:10px;">
        <input type="hidden" name="action" id="reviewAction" value="">
        <input type="hidden" name="id" id="reviewId" value="">
        <label>Status
          <input type="text" id="reviewStatus" readonly style="width:100%;padding:10px;border:1px solid #eef6ff;border-radius:8px;margin-bottom:8px;">
        </label>
        <label>Comment
          <textarea name="status_comment" id="reviewComment" rows="3" style="width:100%;padding:10px;border:1px solid #eef6ff;border-radius:8px;" placeholder="Reason / instructions"></textarea>
        </label>
        <div style="display:flex; gap:10px; margin-top:10px;">
          <button class="btn" type="submit">Save</button>
          <button type="button" class="btn outline" data-close>Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script src="assets/js/main.js"></script>
  <script>
    document.getElementById('selectAll').addEventListener('change', function(){
      document.querySelectorAll('input[name="selected[]"]').forEach(cb=>cb.checked = this.checked);
    });

    function openReviewPrompt(id, status, comment){
      document.getElementById('reviewForm').action = '';
      document.getElementById('reviewAction').value = status;
      document.getElementById('reviewId').value = id;
      document.getElementById('reviewStatus').value = status.charAt(0).toUpperCase() + status.slice(1);
      document.getElementById('reviewComment').value = comment;
      document.getElementById('modalReview').setAttribute('aria-hidden','false');
      document.body.style.overflow='hidden';
    }
    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('.link[onclick^="openReviewPrompt"]').forEach(btn=>{ btn.addEventListener('click', e=>e.stopPropagation()); });
    });
  </script>
</body>
</html>
