<?php
require 'config.php';
header('Content-Type: application/json');
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student'){
    echo json_encode(['error'=>'unauthorized']);
    exit;
}
$student_id = $_SESSION['user']['id'];
$student_id_string = $_SESSION['user']['student_id'];
// retrieve parameters; empty string = show all/any sessions; null/missing = use current
$term = $_POST['term'] ?? $_GET['term'] ?? null;
$session = isset($_POST['session']) ? $_POST['session'] : (isset($_GET['session']) ? $_GET['session'] : null);
// default term if null (missing)
if($term === null) {
    $term = get_current_term();
}
// default session if null (missing); empty string means "show all/any sessions"
if($session === null) {
    $session = get_current_session();
}


// fetch student class and arm
$st = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$st->execute([$student_id]);
$student = $st->fetch();
$student_class = $student['class'] ?? '';
$student_arm = $student['arm'] ?? 'A';

// ranking for class and arm scoped to term and optionally session
$params = [$student_class, $student_arm, $term];
$sessionClause = '';
if($session !== ''){
    // restrict to a specific session but also include legacy rows where session was empty/NULL
    $sessionClause = " AND (r.session = ? OR r.session = '' OR r.session IS NULL)";
    $params[] = $session;
} 
// if session is empty string, don't filter by it (show all/any sessions)
$stmt = $pdo->prepare(
    "SELECT r.student_id, st.id, SUM(r.test1 + r.test2 + r.test3 + r.exam) as total
     FROM results r
     JOIN students st ON r.student_id = st.id
     WHERE st.class = ? AND st.arm = ? AND r.term = ?" . $sessionClause . "
     GROUP BY r.student_id, st.id
     ORDER BY total DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$rankingData = [];
$rank = 0;
$prevTotal = null;
$displayIdx = 0;
foreach($rows as $r){
    $displayIdx++;
    if($prevTotal === null || $r['total'] != $prevTotal){
        $rank = $displayIdx;
        $prevTotal = $r['total'];
    }
    $rankingData[] = ['student_id'=>$r['student_id'],'id'=>$r['id'],'total'=>$r['total'],'position'=>$rank];
}

$position = 'N/A';
$total = 0; // intentional: will set below
foreach($rankingData as $row){
    if($row['id'] == $student_id){
        $position = $row['position'];
        $total = $row['total'];
        break;
    }
}

// fetch subject results for term and optionally session
$params = [$student_id, $term];
$whereSession = '';
if($session !== ''){
    $whereSession = " AND (r.session = ? OR r.session = '' OR r.session IS NULL)";
    $params[] = $session;
}
$stmt = $pdo->prepare("SELECT r.*, s.name as subject FROM results r JOIN subjects s ON r.subject_id = s.id WHERE r.student_id = ? AND r.term = ?" . $whereSession);
$stmt->execute($params);
$results = $stmt->fetchAll();

$count = count($results);
$totalSum = 0;
$rowsHtml = '';
foreach($results as $r){
    $ca = $r['test1'] + $r['test2'] + $r['test3'];
    $rowTotal = $ca + $r['exam'];
    $totalSum += $rowTotal;
    $grade = 'F';
    if($rowTotal >= 70) $grade = 'A';
    else if($rowTotal >= 60) $grade = 'B';
    else if($rowTotal >= 50) $grade = 'C';
    else if($rowTotal >= 45) $grade = 'D';
    $gradeColor = '';
    switch($grade){
        case 'A': $gradeColor = 'background:#c8f7c5;color:#207245;font-weight:bold;'; break;
        case 'B': $gradeColor = 'background:#d4f1fa;color:#176ba0;font-weight:bold;'; break;
        case 'C': $gradeColor = 'background:#fff7c0;color:#a08a17;font-weight:bold;'; break;
        case 'D': $gradeColor = 'background:#ffe0b2;color:#a05d17;font-weight:bold;'; break;
        case 'F': $gradeColor = 'background:#ffd6d6;color:#a01717;font-weight:bold;'; break;
    }
    $rowsHtml .= '<tr>';
    $rowsHtml .= '<td>'.htmlspecialchars($r['subject']).'</td>';
    $rowsHtml .= '<td>'.htmlspecialchars($r['test1']).'</td>';
    $rowsHtml .= '<td>'.htmlspecialchars($r['test2']).'</td>';
    $rowsHtml .= '<td>'.htmlspecialchars($r['test3']).'</td>';
    $rowsHtml .= '<td>'.htmlspecialchars($r['exam']).'</td>';
    $rowsHtml .= '<td>'.$ca.'</td>';
    $rowsHtml .= '<td>'.$rowTotal.'</td>';
    $rowsHtml .= '<td><span style="padding:4px 12px;border-radius:8px;'.$gradeColor.'">'.$grade.'</span></td>';
    $rowsHtml .= '</tr>';
}

$average = $count > 0 ? $totalSum / $count : 0;

if($count === 0){
    $rowsHtml = '<tr id="noResultsRow"><td colspan="8" style="text-align:center;">No results yet</td></tr>';
}

echo json_encode([
    'session'=>$session,
    'term'=>$term,
    'total'=>$totalSum,
    'average'=>number_format($average,2),
    'position'=>$position,
    'subjects'=>$count,
    'rowsHtml'=>$rowsHtml
]);
