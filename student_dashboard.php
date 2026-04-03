<?php
require 'config.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student'){
    header("Location: index.php");
    exit();
}

$_SESSION['display'] = $_SESSION['user']['display_name'];

$student_id = $_SESSION['user']['id'];
$student_id_string = $_SESSION['user']['student_id'];

// Fetch student data including image and class
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
$student_class = $student['class'] ?? '';
$student_arm = $student['arm'] ?? 'A';

// Load current session and term (used to scope results shown on dashboard)
$currentSession = get_current_session();
$currentTerm = get_current_term();
function gradeFrom($total){
    if($total >= 70) return 'A';
    if($total >= 60) return 'B';
    if($total >= 50) return 'C';
    if($total >= 45) return 'D';
    return 'F';
} 

$stmt = $pdo->prepare(
    "SELECT r.student_id, st.id, SUM(r.test1 + r.test2 + r.test3 + r.exam) as total
     FROM results r
     JOIN students st ON r.student_id = st.id
     WHERE st.class = ? AND st.arm = ? AND r.term = ? AND r.session = ? AND r.status = 'approved'
     GROUP BY r.student_id, st.id
     ORDER BY total DESC"
);
$stmt->execute([$student_class, $student_arm, $currentTerm, $currentSession]);
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

// Find current student's position and total for the scoped term/session
$position = 'N/A';
$total = 0;
foreach($rankingData as $row){
    if($row['id'] == $student_id){
        $position = $row['position'];
        $total = $row['total'];
        break;
    }
}

// Now fetch individual subject results for the CURRENT term & session only
$stmt = $pdo->prepare("SELECT r.*, s.name as subject FROM results r JOIN subjects s ON r.subject_id = s.id WHERE r.student_id = ? AND r.term = ? AND r.session = ? AND r.status = 'approved'");
$stmt->execute([$student_id, $currentTerm, $currentSession]);
$results = $stmt->fetchAll();

$count = count($results);
$totalSum = 0;
foreach($results as $r){
    $ca = $r['test1'] + $r['test2'] + $r['test3'];
    $totalSum += $ca + $r['exam'];
}
$average = $count > 0 ? $totalSum / $count : 0;
// Use termly total for the stat display
$total = $totalSum;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Student Dashboard — School RMS</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-layout">
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Student Dashboard</div>
            <div class="top-actions">
                <?php if(isset($student['image']) && $student['image'] && file_exists(__DIR__.'/uploads/'.$student['image'])): ?>
                    <img src="uploads/<?=htmlspecialchars($student['image'])?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover;">
                <?php else: ?>
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: linear-gradient(135deg, #0f74ff, #00c0ff); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 14px;">
                        <?=htmlspecialchars(substr($student['first_name'], 0, 1).substr($student['last_name'], 0, 1))?>
                    </div>
                <?php endif; ?>
                <span class="welcome">Welcome, <strong><?=htmlspecialchars($_SESSION['user']['display_name'])?></strong></span>
                <a class="btn-ghost" href="logout.php"><ion-icon name="log-out-outline"></ion-icon> Logout</a>
                <a href="student_profile.php" class="btn-ghost"><ion-icon name="person-outline"></ion-icon> My Profile</a>
            </div>
        </header>

        <section class="cards-grid">
            <form id="termSessionForm" style="grid-column: 1/-1; display:flex; gap:12px; align-items:center;">
                <label>Session:
                    <select id="sessionSelect" name="session">
                        <option value="" <?=($currentSession===''||$currentSession===null?'selected':'')?>>-- any session --</option>
                        <?php foreach($pdo->query("SELECT * FROM academic_sessions ORDER BY name DESC")->fetchAll() as $s): ?>
                            <option value="<?=htmlspecialchars($s['name'])?>" <?=(($currentSession!=''&&$currentSession!=null&&$currentSession===$s['name'])?'selected':'')?>><?=htmlspecialchars($s['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Term:
                    <select id="termSelect" name="term">
                        <?php foreach(['First Term','Second Term','Third Term'] as $t): ?>
                            <option value="<?=htmlspecialchars($t)?>" <?=(($currentTerm==$t)?'selected':'')?>><?=htmlspecialchars($t)?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="button" id="applyTermSession" class="btn">Apply</button>
                <div id="ajaxStatus" style="margin-left:8px;color:#666;font-size:13px;">Showing current term</div>
            </form>

            <div class="card stat-card">
                <div class="stat-title">Active Session</div>
                <div id="statSession" class="stat-value"><?=htmlspecialchars($currentSession ?: 'n/a')?></div>
                <div class="stat-icon"><ion-icon name="calendar-outline"></ion-icon></div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Active Term</div>
                <div id="statTerm" class="stat-value"><?=htmlspecialchars($currentTerm)?></div>
                <div class="stat-icon"><ion-icon name="calendar-outline"></ion-icon></div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Total Score</div>
                <div id="statTotal" class="stat-value"><?=$total?></div>
                <div class="stat-icon"><ion-icon name="calculator-outline"></ion-icon></div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Average</div>
                <div id="statAverage" class="stat-value"><?=number_format($average, 2)?></div>
                <div class="stat-icon"><ion-icon name="stats-chart-outline"></ion-icon></div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Class Position</div>
                <div id="statPosition" class="stat-value"><?=$position?></div>
                <div class="stat-icon"><ion-icon name="trophy-outline"></ion-icon></div>
            </div>

            <div class="card stat-card">
                <div class="stat-title">Subjects</div>
                <div id="statSubjects" class="stat-value"><?=$count?></div>
                <div class="stat-icon"><ion-icon name="book-outline"></ion-icon></div>
            </div>
        </section>

        <section class="card">
            <h3>Your Results</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>1st Test</th>
                            <th>2nd Test</th>
                            <th>3rd Test</th>
                            <th>Exam</th>
                            <th>CA</th>
                            <th>Total</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody id="resultsBody">
                        <?php foreach($results as $row): 
                            $ca = $row['test1'] + $row['test2'] + $row['test3'];
                            $total_score = $ca + $row['exam'];
                            $grade = gradeFrom($total_score);
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
                            <td><?=htmlspecialchars($row['subject'])?></td>
                            <td><?=htmlspecialchars($row['test1'])?></td>
                            <td><?=htmlspecialchars($row['test2'])?></td>
                            <td><?=htmlspecialchars($row['test3'])?></td>
                            <td><?=htmlspecialchars($row['exam'])?></td>
                            <td><?=$ca?></td>
                            <td><?=$total_score?></td>
                            <td><span style="padding:4px 12px;border-radius:8px;<?=$gradeColor?>"><?=$grade?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($results)): ?>
                        <tr id="noResultsRow"><td colspan="8" style="text-align:center;">No results yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="recent">
            <div class="card">
                <h3>Quick Actions</h3>
                <div class="actions-row">
                    <a id="pdfLink" href="generate_pdf.php?term=<?=urlencode($currentTerm)?>&session=<?=urlencode($currentSession)?>" class="big-btn"><ion-icon name="download-outline"></ion-icon> Download Report Card</a>
                    <a href="view_result.php?student_id=<?=urlencode($student_id_string)?>" class="big-btn"><ion-icon name="eye-outline"></ion-icon> View / Print Previous Results</a>
                </div>
            </div>
        </section>
    </main>

    <script src="assets/js/main.js"></script>
    <script>
    (function(){
        const sessionSelect = document.getElementById('sessionSelect');
        const termSelect = document.getElementById('termSelect');
        const applyBtn = document.getElementById('applyTermSession');
        const status = document.getElementById('ajaxStatus');
        const pdfLink = document.getElementById('pdfLink');

        const statSession = document.getElementById('statSession');
        const statTerm = document.getElementById('statTerm');
        const statTotal = document.getElementById('statTotal');
        const statAverage = document.getElementById('statAverage');
        const statPosition = document.getElementById('statPosition');
        const statSubjects = document.getElementById('statSubjects');
        const resultsBody = document.getElementById('resultsBody');

        function setLoading(loading){
            applyBtn.disabled = loading;
            status.textContent = loading ? 'Loading...' : 'Showing selected term';
        }

        async function fetchAndUpdate(){
            const session = sessionSelect.value;
            const term = termSelect.value;
            setLoading(true);
            try{
                const form = new FormData();
                form.append('session', session);
                form.append('term', term);
                const res = await fetch('ajax_student_results.php', { method: 'POST', body: form });
                const data = await res.json();
                if(data.error){
                    status.textContent = data.error;
                } else {
                    statSession.textContent = data.session || 'n/a';
                    statTerm.textContent = data.term || 'n/a';
                    statTotal.textContent = data.total || '0';
                    statAverage.textContent = data.average || '0.00';
                    statPosition.textContent = data.position || 'N/A';
                    statSubjects.textContent = data.subjects || '0';
                    resultsBody.innerHTML = data.rowsHtml || '';
                    // Update PDF link to match selection
                    pdfLink.href = `generate_pdf.php?term=${encodeURIComponent(data.term)}&session=${encodeURIComponent(data.session||'')}`;
                }
            }catch(err){
                status.textContent = 'Error fetching results';
            } finally{
                setLoading(false);
            }
        }

        applyBtn.addEventListener('click', fetchAndUpdate);
    })();
    </script>
</body>
</html>
