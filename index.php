<?php
require 'config.php';
if(is_logged_in()){
    $role = $_SESSION['user']['role'];
    if($role === 'student'){
        header('Location: student_dashboard.php');
    } elseif($role === 'teacher'){
        header('Location: teacher_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}
$err = null;
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if($user && password_verify($password, $user['password'])){
    $_SESSION['user'] = $user;
    $_SESSION['role'] = $user['role'];

    
    if($user['role'] === 'admin'){
        header("Location: dashboard.php");
    } elseif($user['role'] === 'teacher'){
        header("Location: teacher_dashboard.php");
    } elseif($user['role'] === 'student'){
        header("Location: student_dashboard.php");
    }else{
        $err = "Unknown user role";
    }
    exit;
} else {
    // Check if it's a student login with student_id as username
    $stmt2 = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt2->execute([$username]);
    $student = $stmt2->fetch();
    if($student && (!isset($student['password']) && $password === $username || isset($student['password']) && password_verify($password, $student['password']))){
        $_SESSION['user'] = [
            'role' => 'student',
            'id' => $student['id'],
            'student_id' => $student['student_id'],
            'display_name' => ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''),
        ];
        header("Location: student_dashboard.php");
        exit;
    } else {
        // Check if it's a teacher login with username matching teacher_id
        $stmt3 = $pdo->prepare("SELECT * FROM teachers WHERE teacher_id = ?");
        $stmt3->execute([$username]);
        $teacher = $stmt3->fetch();
        if($teacher){
            $login_ok = false;
            if(isset($teacher['password']) && $teacher['password'] !== null && $teacher['password'] !== ''){
                // hashed password stored
                if(password_verify($password, $teacher['password'])){
                    $login_ok = true;
                }
            } else {
                // default credentials use teacher_id as password
                if($password === $username){
                    $login_ok = true;
                }
            }
            if($login_ok){
                $_SESSION['user'] = [
                    'role' => 'teacher',
                    'id' => $teacher['id'],
                    'teacher_id' => $teacher['teacher_id'],
                    'display_name' => $teacher['full_name'] ?? $teacher['teacher_id'],
                ];
                header("Location: teacher_dashboard.php");
                exit;
            }
        }
        $err = "Invalid username or password.";
    }
}

}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Mabest Academy Result Management — Login</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
  <!-- Ionicons -->
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    /* quick override for centered login */
    body { background: linear-gradient(180deg, #f4f7fb 0%, #e9f0fb 100%); display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .login-card { width:420px; max-width:95%; padding:32px; border-radius:12px; background:#fff; box-shadow: 0 8px 28px rgba(20,40,80,0.08); transform: translateY(0); transition: transform .45s cubic-bezier(.2,.9,.25,1); }
    .login-card:hover{ transform: translateY(-6px); }
    .brand { display:flex; gap:12px; align-items:center; margin-bottom:18px; }
    .brand .logo { width:56px; height:56px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#0f74ff,#00c0ff); color:#fff; font-weight:700; font-family: 'Poppins', sans-serif; box-shadow: 0 6px 20px rgba(13,71,161,0.12); }
    h1 { font-size:20px; margin:0 0 8px 0; color:#213547; font-weight:700; }
    p.lead { margin:0 0 18px 0; color:#6b7a86; }
    .form-row { margin-bottom:12px; }
    input[type="text"], input[type="password"]{ width:95%; padding:12px 35px 12px 14px; border-radius:8px; border:1px solid #e6eef7; font-size:14px; outline:none; transition: box-shadow .18s, border-color .18s; }
    input:focus{ box-shadow: 0 8px 24px rgba(15,116,255,0.08); border-color:#0f74ff; }
    .password-field { position: relative; }
    .toggle-password { position: absolute; right: 30px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #6b7a86; padding: 0; }
    .btn { background: linear-gradient(90deg,#0f74ff,#00c0ff); color:#fff; padding:12px 18px; border-radius:8px; border:0; font-weight:700; width:60%; cursor:pointer; box-shadow: 0 10px 30px rgba(15,116,255,0.12); margin-left:20%; transition: transform .14s; }
    .btn:active{ transform: translateY(2px); }
    .error { color:#d9534f; margin-top:10px; font-weight:600; }
    .small { font-size:13px; color:#798b99; margin-top:10px; display:block; text-align:center; }
  </style>
</head>
<body>
  <?php include 'partials/preloader.php'; ?>
  <div class="login-card" role="main" aria-labelledby="srm-heading">
    <div class="brand">
      <div class=""><img alt="Mabest Academy Logo" src="assets/images/designed2.png"></div>
      <div>
        <h1 id="srm-heading">Mabest Academy Result Management Portal</h1>
        <div class="lead">Login to your Portal</div>
      </div>
    </div>

    <form method="post" novalidate>
      <div class="form-row">
        <input name="username" placeholder="Username" type="text" required autofocus>
      </div>
      <div class="form-row password-field">
        <input name="password" placeholder="Password" type="password" required id="password">
        <button type="button" class="toggle-password" id="togglePassword">
          <ion-icon name="eye-outline" id="eyeIcon"></ion-icon>
        </button>
      </div>
      <button class="btn" type="submit"><ion-icon name="enter-outline"></ion-icon> Login</button>

      <?php if($err): ?>
        <div class="error"><?=htmlspecialchars($err)?></div>
      <?php endif; ?>

      <div class="small">Demo username: <strong>Jiggyy</strong> / <strong>admin123</strong></div>
    </form>
  </div>

  <script>
    document.querySelectorAll('input').forEach(input => {
      input.addEventListener('input', () => {
        const errorDiv = document.querySelector('.error');
        if (errorDiv) errorDiv.style.display = 'none';
      });
    });

    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    togglePassword.addEventListener('click', function() {
      const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
      password.setAttribute('type', type);
      eyeIcon.setAttribute('name', type === 'password' ? 'eye-outline' : 'eye-off-outline');
    });
  </script>
</body>
</html>