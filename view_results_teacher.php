<?php
require 'config.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'teacher'){
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['user']['teacher_id'];

// Fetch subjects taught by this teacher


// Fetch all subjects and unique classes from subjects table
$subjects = $pdo->query("SELECT id, name, CONCAT(class, ' ', arm) as class_arm, class, arm FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$classes = array_unique(array_map(function($s){ return $s['class_arm']; }, $subjects));
sort($classes);

// sessions for filtering
$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY name DESC")->fetchAll(PDO::FETCH_ASSOC);
$terms = ['First Term', 'Second Term', 'Third Term'];

// remember full list before filtering
$allSubjects = $subjects;

// Handle filter form
$selected_subject = isset($_POST['subject_id']) ? $_POST['subject_id'] : '';
$selected_class = isset($_POST['class']) ? $_POST['class'] : '';
$selected_term = isset($_POST['term']) ? $_POST['term'] : '';
$selected_session = isset($_POST['session']) ? $_POST['session'] : get_current_session();
// treat empty string as no session filtering


// adjust subjects dropdown options when class selected
if($selected_class){
    $subjects = array_values(array_filter($allSubjects, function($s) use ($selected_class){
        return $s['class'] === $selected_class;
    }));
} else {
    $subjects = $allSubjects;
}


$classResults = [];
$subjectResults = [];

if($selected_class){
    // fetch all results for the class (all subjects) for the selected session and term
    $params = [$selected_class];
    $sessionClause = '';
    if($selected_session !== ''){
        $sessionClause = " AND (r.session = ? OR r.session = '' OR r.session IS NULL)";
        $params[] = $selected_session;
    }
    $termClause = '';
    if($selected_term !== ''){
        $termClause = " AND r.term = ?";
        $params[] = $selected_term;
    }
    $stmt = $pdo->prepare("SELECT r.*, st.first_name, st.last_name, CONCAT(st.class, ' ', st.arm) as class, subj.name AS subject_name FROM results r JOIN students st ON r.student_id = st.id JOIN subjects subj ON r.subject_id = subj.id WHERE CONCAT(st.class, ' ', st.arm) = ?" . $termClause . $sessionClause);
    $stmt->execute($params);
    $classResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add class positions for each student
    if(!empty($classResults)){
        $posParams = [$selected_class];
        $posClause = '';
        if($selected_term !== ''){
            $posClause .= " AND r.term = ?";
            $posParams[] = $selected_term;
        }
        if($selected_session !== ''){
            $posClause .= " AND (r.session = ? OR r.session = '' OR r.session IS NULL)";
            $posParams[] = $selected_session;
        }
        $posQuery = "SELECT st.id, SUM(r.test1 + r.test2 + r.test3 + r.exam) as total 
                FROM results r 
                JOIN students st ON r.student_id = st.id 
                WHERE CONCAT(st.class, ' ', st.arm) = ?" . $posClause . " 
                GROUP BY st.id 
                ORDER BY total DESC";
        $posStmt = $pdo->prepare($posQuery);
        $posStmt->execute($posParams);
        $positions = $posStmt->fetchAll(PDO::FETCH_ASSOC);
        $posMap = [];
        foreach($positions as $idx => $p){
            $posMap[$p['id']] = $idx + 1;
        }
        foreach($classResults as &$r){
            $r['class_position'] = $posMap[$r['student_id']] ?? '-';
        }
        unset($r);
    }
}

if($selected_subject){
    // fetch results for specific subject; if class also selected add condition
    $query = "SELECT r.*, st.first_name, st.last_name, st.class, subj.name AS subject_name FROM results r JOIN students st ON r.student_id = st.id JOIN subjects subj ON r.subject_id = subj.id WHERE r.subject_id = ?";
    $params = [$selected_subject];
    if($selected_class){
        $query .= " AND st.class = ?";
        $params[] = $selected_class;
    }
    if($selected_term !== ''){
        $query .= " AND r.term = ?";
        $params[] = $selected_term;
    }
    // include session filter for subject queries (and blank/null)
    if($selected_session !== ''){
        $query .= " AND (r.session = ? OR r.session = '' OR r.session IS NULL)";
        $params[] = $selected_session;
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $subjectResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add class positions for subject results
    if(!empty($subjectResults)){
        $posParams = [$selected_class ?: (isset($subjectResults[0]['class']) ? $subjectResults[0]['class'] : '')];
        $posClause = '';
        if($selected_term !== ''){
            $posClause .= " AND r.term = ?";
            $posParams[] = $selected_term;
        }
        if($selected_session !== ''){
            $posClause .= " AND (r.session = ? OR r.session = '' OR r.session IS NULL)";
            $posParams[] = $selected_session;
        }
        $posQuery = "SELECT st.id, SUM(r.test1 + r.test2 + r.test3 + r.exam) as total 
                FROM results r 
                JOIN students st ON r.student_id = st.id 
                WHERE st.class = ?" . $posClause . " 
                GROUP BY st.id 
                ORDER BY total DESC";
        $posStmt = $pdo->prepare($posQuery);
        $posStmt->execute($posParams);
        $positions = $posStmt->fetchAll(PDO::FETCH_ASSOC);
        $posMap = [];
        foreach($positions as $idx => $p){
            $posMap[$p['id']] = $idx + 1;
        }
        foreach($subjectResults as &$r){
            $r['class_position'] = $posMap[$r['student_id']] ?? '-';
        }
        unset($r);
    }
}

// Handle update/delete actions
if(isset($_POST['action'])){
    if($_POST['action'] == 'update' && isset($_POST['id'])){
        // Update result (use primary key `id`) - only persist raw scores; total/grade are derived on demand
        $stmt = $pdo->prepare("UPDATE results SET test1=?, test2=?, test3=?, exam=? WHERE id=?");
        $stmt->execute([
            $_POST['test1'],
            $_POST['test2'],
            $_POST['test3'],
            $_POST['exam'],
            $_POST['id']
        ]);
    } elseif($_POST['action'] == 'delete' && isset($_POST['id'])){
        // Delete result (use primary key `id`)
        $stmt = $pdo->prepare("DELETE FROM results WHERE id=?");
        $stmt->execute([$_POST['id']]);
    }
    // Refresh results after action (re-run the filter logic)
    if($selected_class){
        $stmt = $pdo->prepare("SELECT r.*, st.first_name, st.last_name, CONCAT(st.class, ' ', st.arm) as class, subj.name AS subject_name FROM results r JOIN students st ON r.student_id = st.id JOIN subjects subj ON r.subject_id = subj.id WHERE CONCAT(st.class, ' ', st.arm) = ? AND r.session = ?");
        $stmt->execute([$selected_class, $selected_session]);
        $classResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Recalculate class positions
        if(!empty($classResults)){
            $posQuery = "SELECT st.id, SUM(r.test1 + r.test2 + r.test3 + r.exam) as total 
                        FROM results r 
                        JOIN students st ON r.student_id = st.id 
                        WHERE CONCAT(st.class, ' ', st.arm) = ? AND r.session = ? 
                        GROUP BY st.id 
                        ORDER BY total DESC";
            $posStmt = $pdo->prepare($posQuery);
            $posStmt->execute([$selected_class, $selected_session]);
            $positions = $posStmt->fetchAll(PDO::FETCH_ASSOC);
            $posMap = [];
            foreach($positions as $idx => $p){
                $posMap[$p['id']] = $idx + 1;
            }
            foreach($classResults as &$r){
                $r['class_position'] = $posMap[$r['student_id']] ?? '-';
            }
            unset($r);
        }
    }
    if($selected_subject){
        $query = "SELECT r.*, st.first_name, st.last_name, CONCAT(st.class, ' ', st.arm) as class, subj.name AS subject_name FROM results r JOIN students st ON r.student_id = st.id JOIN subjects subj ON r.subject_id = subj.id WHERE r.subject_id = ?";
        $params = [$selected_subject];
        if($selected_class){
            $query .= " AND CONCAT(st.class, ' ', st.arm) = ?";
            $params[] = $selected_class;
        }
        // apply session filter
        $query .= " AND r.session = ?";
        $params[] = $selected_session;
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $subjectResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Recalculate class positions for subject results
        if(!empty($subjectResults)){
            $studentClass = $selected_class ?: (isset($subjectResults[0]['class']) ? $subjectResults[0]['class'] : '');
            if($studentClass){
                    $posQuery = "SELECT st.id, SUM(r.test1 + r.test2 + r.test3 + r.exam) as total 
                                FROM results r 
                                JOIN students st ON r.student_id = st.id 
                                WHERE CONCAT(st.class, ' ', st.arm) = ? AND r.session = ? 
                                GROUP BY st.id 
                                ORDER BY total DESC";
                    $posStmt = $pdo->prepare($posQuery);
                    $posStmt->execute([$studentClass, $selected_session]);
                $positions = $posStmt->fetchAll(PDO::FETCH_ASSOC);
                $posMap = [];
                foreach($positions as $idx => $p){
                    $posMap[$p['id']] = $idx + 1;
                }
                foreach($subjectResults as &$r){
                    $r['class_position'] = $posMap[$r['student_id']] ?? '-';
                }
                unset($r);
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>View Results — Teacher</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>


<body class="app-layout">
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="page-title">View & Manage Results</div>
        </header>
        <section class="card">
            <form method="post" class="filter-form" style="margin-bottom: 24px; display: flex; gap: 16px; align-items: center;">
                <label style="min-width:180px;">Subject:
                    <select name="subject_id" style="width:100%;">
                        <option value="">All Subjects</option>
                        <?php foreach($subjects as $sub): ?>
                            <option value="<?=$sub['id']?>" <?=($selected_subject==$sub['id']?'selected':'')?>><?=htmlspecialchars($sub['name'])?> (<?=htmlspecialchars($sub['class'])?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="min-width:120px;">Class:
                    <select name="class" style="width:100%;">
                        <option value="">All Classes</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?=htmlspecialchars($class)?>" <?=($selected_class==$class?'selected':'')?>><?=htmlspecialchars($class)?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="min-width:160px;">Term:
                    <select name="term" style="width:100%;">
                        <option value="">All Terms</option>
                        <?php foreach($terms as $term): ?>
                            <option value="<?=htmlspecialchars($term)?>" <?=($selected_term===$term?'selected':'')?>><?=htmlspecialchars($term)?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="min-width:160px;">Session:
                    <select name="session" style="width:100%;">
                        <option value="">Current (<?=htmlspecialchars(get_current_session()?:'N/A')?>)</option>
                        <?php foreach($sessions as $sess): ?>
                            <option value="<?=htmlspecialchars($sess['name'])?>" <?=($selected_session===$sess['name']?'selected':'')?>><?=htmlspecialchars($sess['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="btn">Filter</button>
            </form>

            <?php if($selected_class): ?>
                <h4>Results for class <?=htmlspecialchars($selected_class)?> (all subjects)</h4>
                <?php if($classResults): ?>
                <div class="table-wrap">
                <table class="table results-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Position</th>
                            <th>Subject</th>
                            <th>Test 1</th>
                            <th>Test 2</th>
                            <th>Test 3</th>
                            <th>Exam</th>
                            <th>Total</th>
                            <th>Grade</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($classResults as $row): 
                            $t1 = isset($row['test1']) ? (int)$row['test1'] : 0;
                            $t2 = isset($row['test2']) ? (int)$row['test2'] : 0;
                            $t3 = isset($row['test3']) ? (int)$row['test3'] : 0;
                            $exam = isset($row['exam']) ? (int)$row['exam'] : 0;
                            $total = isset($row['total']) && $row['total'] !== null ? (int)$row['total'] : ($t1 + $t2 + $t3 + $exam);
                            if(isset($row['grade']) && $row['grade'] !== null && $row['grade'] !== ''){
                                $grade = $row['grade'];
                            } else {
                                if($total >= 70) $grade = 'A';
                                elseif($total >= 60) $grade = 'B';
                                elseif($total >= 50) $grade = 'C';
                                elseif($total >= 45) $grade = 'D';
                                else $grade = 'F';
                            }
                            $gradeClass = strtolower($grade);
                        ?>
                        <tr>
                            <td><?=htmlspecialchars($row['first_name'].' '.$row['last_name'])?></td>
                            <td><strong style="color:#0f74ff;font-size:16px;"><?=htmlspecialchars($row['class_position'] ?? '-')?></strong></td>
                            <td><?=htmlspecialchars($row['subject_name'] ?? $row['subject_id'])?></td>
                            <form method="post" style="display:contents;">
                                <td><input type="number" name="test1" value="<?=htmlspecialchars($t1)?>" min="0" max="20" required style="width:60px;"></td>
                                <td><input type="number" name="test2" value="<?=htmlspecialchars($t2)?>" min="0" max="20" required style="width:60px;"></td>
                                <td><input type="number" name="test3" value="<?=htmlspecialchars($t3)?>" min="0" max="20" required style="width:60px;"></td>
                                <td><input type="number" name="exam" value="<?=htmlspecialchars($exam)?>" min="0" max="60" required style="width:60px;"></td>
                                <td><input type="number" name="total" value="<?=htmlspecialchars($total)?>" min="0" max="100" required style="width:60px;"></td>
                                <td><span class="grade-cell grade-<?=htmlspecialchars($gradeClass)?>"><?=htmlspecialchars($grade)?></span></td>
                                <td style="min-width:120px;">
                                    <input type="hidden" name="id" value="<?=htmlspecialchars($row['id'] ?? '')?>">
                                    <input type="hidden" name="subject_id" value="<?=$selected_subject?>">
                                    <input type="hidden" name="class" value="<?=$selected_class?>">
                                    <button type="submit" name="action" value="update" class="btn outline" style="margin-right:4px;">Update</button>
                                    <button type="submit" name="action" value="delete" class="btn danger" data-confirm="Delete result for %s?" data-confirm-name="<?=htmlspecialchars($row['first_name'].' '.$row['last_name'])?>">Delete</button>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php else: ?>
                    <p style="margin-top:24px;">No results found for this class.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if($subjectResults): ?>
                <h4>Subject-specific results for <?=htmlspecialchars((function(){
    global $allSubjects, $selected_subject;
    foreach($allSubjects as $s){ if($s['id']==$selected_subject) return $s['name']; }
    return '';
})())?></h4>
                <div class="table-wrap">
                <table class="table results-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Position</th>
                            <th>Test 1</th>
                            <th>Test 2</th>
                            <th>Test 3</th>
                            <th>Exam</th>
                            <th>Total</th>
                            <th>Grade</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($subjectResults as $row): 
                            $t1 = isset($row['test1']) ? (int)$row['test1'] : 0;
                            $t2 = isset($row['test2']) ? (int)$row['test2'] : 0;
                            $t3 = isset($row['test3']) ? (int)$row['test3'] : 0;
                            $exam = isset($row['exam']) ? (int)$row['exam'] : 0;
                            $total = isset($row['total']) && $row['total'] !== null ? (int)$row['total'] : ($t1 + $t2 + $t3 + $exam);
                            if(isset($row['grade']) && $row['grade'] !== null && $row['grade'] !== ''){
                                $grade = $row['grade'];
                            } else {
                                if($total >= 70) $grade = 'A';
                                elseif($total >= 60) $grade = 'B';
                                elseif($total >= 50) $grade = 'C';
                                elseif($total >= 45) $grade = 'D';
                                else $grade = 'F';
                            }
                            $gradeClass = strtolower($grade);
                        ?>
                        <tr>
                            <td><?=htmlspecialchars($row['first_name'].' '.$row['last_name'])?></td>
                            <td><strong style="color:#0f74ff;font-size:16px;"><?=htmlspecialchars($row['class_position'] ?? '-')?></strong></td>
                            <form method="post" style="display:contents;">
                                <td><input type="number" name="test1" value="<?=htmlspecialchars($t1)?>" min="0" max="20" required style="width:60px;"></td>
                                <td><input type="number" name="test2" value="<?=htmlspecialchars($t2)?>" min="0" max="20" required style="width:60px;"></td>
                                <td><input type="number" name="test3" value="<?=htmlspecialchars($t3)?>" min="0" max="20" required style="width:60px;"></td>
                                <td><input type="number" name="exam" value="<?=htmlspecialchars($exam)?>" min="0" max="40" required style="width:60px;"></td>
                                <td><input type="number" name="total" value="<?=htmlspecialchars($total)?>" min="0" max="100" required style="width:60px;"></td>
                                <td><span class="grade-cell grade-<?=htmlspecialchars($gradeClass)?>"><?=htmlspecialchars($grade)?></span></td>
                                <td style="min-width:120px;">
                                    <input type="hidden" name="id" value="<?=htmlspecialchars($row['id'] ?? '')?>">
                                    <input type="hidden" name="subject_id" value="<?=$selected_subject?>">
                                    <input type="hidden" name="class" value="<?=$selected_class?>">
                                    <button type="submit" name="action" value="update" class="btn outline" style="margin-right:4px;">Update</button>
                                    <button type="submit" name="action" value="delete" class="btn danger" data-confirm="Delete result for %s?" data-confirm-name="<?=htmlspecialchars($row['first_name'].' '.$row['last_name'])?>">Delete</button>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php elseif($selected_subject && $selected_class): ?>
                <p style="margin-top:24px;">No results found for this subject and class.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
<style>
.grade-cell.grade-a { color: #2ecc40; font-weight: bold; }
.grade-cell.grade-b { color: #0074d9; font-weight: bold; }
.grade-cell.grade-c { color: #ff851b; font-weight: bold; }
.grade-cell.grade-d { color: #ffdc00; font-weight: bold; }
.grade-cell.grade-f { color: #ff4136; font-weight: bold; }
.results-table input[type=number] { padding: 4px; border-radius: 4px; border: 1px solid #ddd; }
.results-table .btn { font-size: 13px; padding: 4px 10px; }
.results-table .btn.danger { background: #ff4136; color: #fff; border: none; }
.results-table .btn.outline { background: #fff; color: #0074d9; border: 1px solid #0074d9; }
.table-wrap { overflow-x: auto; }
.filter-form label { font-weight: 600; }
.card { margin-top: 24px; }
</style>
