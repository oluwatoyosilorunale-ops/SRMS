<?php
require 'config.php';

if(!is_logged_in()){
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user']['id'];

// Fetch current user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$updateSuccess = false;
$password_success = false;
$errors = [];

// ================= UPDATE PROFILE =================
if(isset($_POST['update_profile'])){
    $display_name = $_POST['display_name'] ?? '';

    if(empty($display_name)){
        $errors[] = 'Display name cannot be empty';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET display_name = ? WHERE id = ?");
        $stmt->execute([$display_name, $user_id]);
        $_SESSION['display'] = $display_name;
        $updateSuccess = true;
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }
}

// ================= CHANGE PASSWORD =================
if(isset($_POST['change_password'])){
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if(empty($current_password) || empty($new_password) || empty($confirm_password)){
        $errors[] = 'All password fields are required';
    }
    elseif(!password_verify($current_password, $user['password'])){
        $errors[] = 'Current password is incorrect';
    }
    elseif($new_password !== $confirm_password){
        $errors[] = 'New passwords do not match';
    }
    elseif(strlen($new_password) < 6){
        $errors[] = 'New password must be at least 6 characters';
    }
    else{
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $user_id]);
        $password_success = true;
    }
}

// ================= HANDLE IMAGE UPLOAD =================
if(isset($_POST['update_image'])){
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
        // Create uploads directory if it doesn't exist
        $uploadsDir = __DIR__.'/uploads';
        if(!is_dir($uploadsDir)){
            mkdir($uploadsDir, 0755, true);
        }
        
        // Delete old image if exists
        if(isset($user['image']) && $user['image'] && file_exists($uploadsDir.'/'.$user['image'])){
            unlink($uploadsDir.'/'.$user['image']);
        }
        
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imgName = 'admin_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $target = $uploadsDir.'/'.$imgName;
        
        if(move_uploaded_file($_FILES['image']['tmp_name'], $target)){
            $stmt = $pdo->prepare("UPDATE users SET image = ? WHERE id = ?");
            $stmt->execute([$imgName, $user_id]);
            $updateSuccess = true;
            $user['image'] = $imgName;
            $_SESSION['user']['image'] = $imgName;
        } else {
            $errors[] = 'Failed to upload image';
        }
    } else {
        $errors[] = 'Please select an image to upload';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Profile — School RMS</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .profile-container {
            max-width: 600px;
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eef4fb;
        }
        .profile-avatar {
            position: relative;
        }
        .profile-avatar img {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: 0 6px 20px rgba(15, 116, 255, 0.2);
        }
        .profile-avatar-fallback {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            background: linear-gradient(135deg, #0f74ff, #00c0ff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 36px;
            box-shadow: 0 6px 20px rgba(15, 116, 255, 0.2);
        }
        .profile-info h2 {
            margin: 0;
            color: var(--accent1);
            font-size: 24px;
        }
        .profile-info p {
            margin: 4px 0;
            color: var(--muted);
            font-size: 14px;
        }
        .form-section {
            background: transparent;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #eef4fb;
            margin-bottom: 18px;
        }
        .form-section h3 {
            margin: 0 0 16px 0;
            color: var(--accent1);
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-section h3 ion-icon {
            font-size: 20px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 8px;
        }
        .form-group input {
            width: 100%;
            padding: 11px 12px;
            border-radius: 8px;
            border: 1px solid #eef6ff;
            background: #fafcff;
            outline: none;
            font-size: 14px;
            font-family: 'Poppins', system-ui;
            transition: all 0.2s;
        }
        .form-group input:focus {
            border-color: var(--accent1);
            background: white;
            box-shadow: 0 0 0 3px rgba(15, 116, 255, 0.1);
        }
        .password-field {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 38px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--accent1);
            font-size: 18px;
            display: flex;
            align-items: center;
            padding: 4px;
        }
        .toggle-password:hover {
            opacity: 0.7;
        }
        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-weight: 600;
            font-size: 13px;
        }
        .alert.success {
            background: linear-gradient(90deg, #e6fbf8, #e6fbf0);
            color: #1b6b55;
            border: 1px solid rgba(30, 160, 130, 0.06);
        }
        .alert.error {
            background: #ffd6d6;
            color: #a01717;
            border: 1px solid rgba(160, 23, 23, 0.1);
        }
        .button-group {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }
        .readonly-field {
            background: #f5f9fb;
            color: var(--muted);
            cursor: not-allowed;
        }
        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .image-preview {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .preview-item {
            text-align: center;
        }
        .preview-item img {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            box-shadow: 0 4px 12px rgba(15, 116, 255, 0.1);
        }
        .preview-text {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }
    </style>
</head>
<body class="app-layout">
    <?php include 'partials/sidebar.php'; ?>
    
    <main class="main-content">
        <header class="topbar">
            <div class="page-title">My Profile</div>
            <div class="top-actions">
                <a href="dashboard.php" class="btn outline" style="font-size: 12px;">← Back to Dashboard</a>
                <a class="btn-ghost" href="logout.php"><ion-icon name="log-out-outline"></ion-icon> Logout</a>
            </div>
        </header>

        <div class="profile-container">
            <!-- Success Messages -->
            <?php if($updateSuccess): ?>
                <div class="alert success">✓ Profile updated successfully</div>
            <?php endif; ?>
            
            <?php if($password_success): ?>
                <div class="alert success">✓ Password changed successfully</div>
            <?php endif; ?>

            <!-- Error Messages -->
            <?php if(!empty($errors)): ?>
                <?php foreach($errors as $error): ?>
                    <div class="alert error">✗ <?=htmlspecialchars($error)?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php if(isset($user['image']) && $user['image'] && file_exists(__DIR__.'/uploads/'.$user['image'])): ?>
                        <img src="uploads/<?=htmlspecialchars($user['image'])?>" alt="Profile">
                    <?php else: ?>
                        <div class="profile-avatar-fallback">
                            <?=htmlspecialchars(substr($user['display_name'], 0, 1))?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h2><?=htmlspecialchars($user['display_name'])?></h2>
                    <p><strong>Role:</strong> <?=htmlspecialchars(ucfirst($user['role']))?></p>
                    <p><strong>Username:</strong> <?=htmlspecialchars($user['username'])?></p>
                </div>
            </div>

            <!-- Update Profile Section -->
            <div class="form-section">
                <h3><ion-icon name="person-outline"></ion-icon> Account Settings</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Username (Cannot be changed)</label>
                        <input type="text" value="<?=htmlspecialchars($user['username'])?>" disabled class="readonly-field">
                    </div>

                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" name="display_name" value="<?=htmlspecialchars($user['display_name'])?>" required>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" value="<?=htmlspecialchars(ucfirst($user['role']))?>" disabled class="readonly-field">
                    </div>

                    <button type="submit" name="update_profile" class="btn" style="width: 100%; margin-top: 8px;">
                        <ion-icon name="checkmark-outline"></ion-icon> Update Profile
                    </button>
                </form>
            </div>

            <!-- Change Password Section -->
            <div class="form-section">
                <h3><ion-icon name="lock-closed-outline"></ion-icon> Change Password</h3>
                <form method="POST">
                    <div class="form-group password-field">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="current-pwd" required>
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility(this)">
                            <ion-icon name="eye-outline"></ion-icon>
                        </button>
                    </div>

                    <div class="form-group password-field">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="new-pwd" required>
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility(this)">
                            <ion-icon name="eye-outline"></ion-icon>
                        </button>
                    </div>

                    <div class="form-group password-field">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="confirm-pwd" required>
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility(this)">
                            <ion-icon name="eye-outline"></ion-icon>
                        </button>
                    </div>

                    <button type="submit" name="change_password" class="btn" style="width: 100%; margin-top: 8px;">
                        <ion-icon name="checkmark-outline"></ion-icon> Change Password
                    </button>
                </form>
            </div>

            <!-- Update Profile Image Section -->
            <div class="form-section">
                <h3><ion-icon name="image-outline"></ion-icon> Profile Picture</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="image-preview">
                        <div class="preview-item">
                            <?php if(isset($user['image']) && $user['image'] && file_exists(__DIR__.'/uploads/'.$user['image'])): ?>
                                <img src="uploads/<?=htmlspecialchars($user['image'])?>" alt="Current Profile">
                            <?php else: ?>
                                <div style="width: 80px; height: 80px; background: #eef4fb; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--muted);">
                                    <ion-icon name="image-outline" style="font-size: 32px; opacity: 0.5;"></ion-icon>
                                </div>
                            <?php endif; ?>
                            <div class="preview-text">Current</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Choose New Image</label>
                        <input type="file" name="image" accept="image/*" required>
                    </div>

                    <button type="submit" name="update_image" class="btn" style="width: 100%; margin-top: 8px;">
                        <ion-icon name="checkmark-outline"></ion-icon> Upload Photo
                    </button>
                </form>
            </div>
        </div>
    </main>

    <script>
        function togglePasswordVisibility(button) {
            const input = button.parentElement.querySelector('input');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            
            // Toggle icon
            const icon = button.querySelector('ion-icon');
            if(isPassword) {
                icon.setAttribute('name', 'eye-off-outline');
            } else {
                icon.setAttribute('name', 'eye-outline');
            }
        }
    </script>
</body>
</html>