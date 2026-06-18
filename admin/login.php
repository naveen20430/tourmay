<?php
require_once '../config/config.php';

$error = '';

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if ($username && $password) {
        $user = $db->fetch(
            "SELECT * FROM admin_users WHERE username = ? AND status = 'active'", 
            [$username]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_name'] = $user['full_name'];
            $_SESSION['admin_role'] = $user['role'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Please fill all fields';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo getSetting('site_name'); ?></title>
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/images/favicons/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>assets/images/favicons/favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #1bbc9b; min-height: 100vh; }
        .login-container { min-height: 100vh; display: flex; align-items: center; }
        .login-card { background: rgba(255,255,255,0.95); border-radius: 10px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        .login-header { background: #1bbc9b; color: white; border-radius: 10px 10px 0 0; }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="row justify-content-center w-100">
            <div class="col-md-4">
                <div class="login-card">
                    <div class="login-header p-4 text-center">
                        <h2 class="mb-0">Admin Login</h2>
                        <small><?php echo getSetting('site_name'); ?></small>
                    </div>
                    <div class="p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required autocomplete="username">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required autocomplete="current-password">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="../index.php" class="text-decoration-none">← Back to Website</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
