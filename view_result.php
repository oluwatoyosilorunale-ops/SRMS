<?php
require 'config.php';
require_login();

$student_id = $_GET['student_id'] ?? null;
$term = $_GET['term'] ?? null;
$session = $_GET['session'] ?? null;
// default to current settings if not specified
$term = $term ?: get_current_term();
$session = $session ?: get_current_session();
if(!$student_id){
    echo "No student selected.";
    exit;
}

// student details
$st = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
$st->execute([$student_id]);
$student = $st->fetch();
if(!$student){ echo "Student not found."; exit; }

// results for term (and optionally session)
$params = [$student['id'], $term];
$whereSession = '';
if($session !== ''){
    // include rows where session blank/null so old entries appear
    $whereSession = " AND (r.session = ? OR r.session = '' OR r.session IS NULL)";
    $params[] = $session;
}
$stmt = $pdo->prepare("SELECT r.*, s.name as subject_name FROM results r JOIN subjects s ON r.subject_id = s.id WHERE r.student_id = ? AND r.term = ? AND r.status = 'approved'" . $whereSession);
$stmt->execute($params); // Use id, not student_id string
$rows = $stmt->fetchAll();

$total = 0;
$subjectCount = count($rows);
foreach($rows as $r) {
    $ca = $r['test1'] + $r['test2'] + $r['test3'];
    $total += $ca + $r['exam'];
}
$average = $subjectCount ? round($total / $subjectCount, 1) : 0;

function gradeFrom($avg){
    if($avg >= 70) return 'A';
    if($avg >= 60) return 'B';
    if($avg >= 50) return 'C';
    if($avg >= 45) return 'D';
    return 'F';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><title>Result — <?=htmlspecialchars($student['first_name'].' '.$student['last_name'])?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .print-panel { max-width:900px; margin:24px auto; padding:28px; background:#fff; border-radius:8px; box-shadow: 0 12px 50px rgba(30,60,100,0.08); }
    .print-head { text-align:center; margin-bottom:18px; }
    .print-head h2{ letter-spacing:2px; }
    table.print { width:100%; border-collapse:collapse; margin-top:10px; }
    table.print th, table.print td { padding:10px; border:1px solid #eef4fb; text-align:left; }
    .print-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:12px; }
  </style>
</head>
<body>
  <div class="print-panel">
    <div class="print-head">
        <img src="assets/images/designed2.png" alt="MABEST ACADEMY Logo">
      <h2>MABEST ACADEMY</h2>
      <div>Oke-Ijebu</div>
        <div style="margin-top:8px;font-weight:700; font-size:16px;">Result — <?=htmlspecialchars($term)?> (<?=htmlspecialchars($session?:'N/A')?>)</div>
    </div>

      <form method="GET" style="margin-top:12px; display:flex; gap:12px; align-items:center;">
        <input type="hidden" name="student_id" value="<?=htmlspecialchars($student_id)?>">
        <label>Session:
          <select name="session">
            <option value="" <?=($session===''? 'selected' : '')?>>-- any/unspecified --</option>
            <?php foreach($pdo->query("SELECT * FROM academic_sessions ORDER BY name DESC")->fetchAll() as $s): ?>
              <option value="<?=htmlspecialchars($s['name'])?>" <?=($session===$s['name']?'selected':'')?>><?=htmlspecialchars($s['name'])?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Term:
          <select name="term">
            <?php foreach(['First Term','Second Term','Third Term'] as $t): ?>
              <option value="<?=htmlspecialchars($t)?>" <?=(($term==$t)?'selected':'')?>><?=htmlspecialchars($t)?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn" type="submit">Show</button>
      </form>

      <div style="display:flex; gap:30px;">
      <div><strong>Student:</strong> <?=htmlspecialchars($student['first_name'].' '.$student['last_name'])?></div>
      <div><strong>Class:</strong> <?=htmlspecialchars($student['class'] . ' ' . ($student['arm'] ?? 'A'))?></div>
      <div><strong>Reg No:</strong> <?=htmlspecialchars($student['student_id'])?></div>
    </div>

    <table class="print" aria-label="Student results">
      <thead><tr><th>Subject</th><th>1st Test</th><th>2nd Test</th><th>3rd Test</th><th>Exam</th><th>CA</th><th>Total</th><th>Grade</th></tr></thead>
      <tbody>
        <?php foreach($rows as $r): 
            $ca = $r['test1'] + $r['test2'] + $r['test3'];
            $t = $ca + $r['exam'];
            $grade = gradeFrom($t);
            $gradeColor = '';
            switch($grade) {
                case 'A': $gradeColor = 'background:#c8f7c5;color:#207245;font-weight:bold;'; break;
                case 'B': $gradeColor = 'background:#d4f1fa;color:#176ba0;font-weight:bold;'; break;
                case 'C': $gradeColor = 'background:#fff7c0;color:#a08a17;font-weight:bold;'; break;
                case 'D': $gradeColor = 'background:#ffe0b2;color:#a05d17;font-weight:bold;'; break;
                case 'F': $gradeColor = 'background:#ffd6d6;color:#a01717;font-weight:bold;'; break;
            }
        ?>
          <tr>
            <td><?=htmlspecialchars($r['subject_name'])?></td>
            <td><?=$r['test1']?></td>
            <td><?=$r['test2']?></td>
            <td><?=$r['test3']?></td>
            <td><?=$r['exam']?></td>
            <td><?=$ca?></td>
            <td><?=$t?></td>
            <td><span style="padding:4px 12px;border-radius:8px;<?=$gradeColor?>"><?=$grade?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><th colspan="6">Total</th><th><?=$total?></th><th>Average: <?=$average?>%</th></tr>
      </tfoot>
    </table>

    <div class="print-actions">
      <button class="btn" onclick="window.print()">Print Result</button>
      <a href="results.php" class="btn outline">Back</a>
    </div>
  </div>
</body>
</html>