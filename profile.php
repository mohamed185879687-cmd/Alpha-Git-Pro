<?php
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// الحصول على بيانات المستخدم
$stmt = $conn->prepare("SELECT username, email, created_at, last_login FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action == 'update_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // التحقق من كلمة المرور الحالية
        $check_stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $db_user = $check_result->fetch_assoc();
        
        if (!password_verify($current_password, $db_user['password_hash'])) {
            $error = 'Current password is incorrect!';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match!';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters!';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                $success = 'Password updated successfully!';
            } else {
                $error = 'Failed to update password!';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alpha-Git | Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-code-branch"></i> Alpha-Git
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="checker.php">Card Checker</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="generator.php">Generator</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($user['username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Content -->
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-user-circle"></i> User Profile</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Account Information</h5>
                                <table class="table">
                                    <tr>
                                        <th>Username:</th>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Member Since:</th>
                                        <td><?php echo date('F j, Y', strtotime($user['created_at'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Last Login:</th>
                                        <td><?php echo $user['last_login'] ? date('F j, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div class="col-md-6">
                                <h5>Change Password</h5>
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_password">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" name="new_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" name="confirm_password" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Update Password</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white mt-5 py-3">
        <div class="container text-center">
            <p>&copy; 2024 Alpha-Git. All rights reserved. | Educational Purposes Only</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>