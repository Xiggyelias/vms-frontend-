<?php
require_once __DIR__ . '/includes/init.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    header('Location: forgot_password.php?error=1');
    exit;
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-form">
            <div class="login-header">
                <div class="logo">
                    <img src="assets/images/AULogo.png" alt="AU Logo">
                </div>
                <h1>Reset Password</h1>
                <p>Enter your new password below</p>
            </div>
            
            <form method="POST" action="process-reset.php">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="input-group has-icon">
                    <i class="fa fa-lock"></i>
                    <input type="password" name="password" class="form-control" placeholder="New password" minlength="8" required autocomplete="new-password">
                </div>
                
                <div class="input-group has-icon">
                    <i class="fa fa-lock"></i>
                    <input type="password" name="password_confirm" class="form-control" placeholder="Confirm new password" minlength="8" required autocomplete="new-password">
                </div>
                
                <button type="submit" class="login-button">
                    Update Password <i class="fa fa-check" style="margin-left: 8px; font-size: 0.9em;"></i>
                </button>
            </form>
            
            <div class="auth-links">
                <a href="login.php" style="color:var(--gray-500);"><i class="fa fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
