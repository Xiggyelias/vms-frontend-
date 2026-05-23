<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();
$csrfToken = SecurityMiddleware::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/main.css">
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
                <h1>Forgot Password</h1>
                <p>Enter your email to receive a reset link</p>
            </div>
            
            <?php if (isset($_GET['sent'])): ?>
                <div class="alert alert-success">
                    <i class="fa fa-check-circle" style="margin-right: 8px;"></i> If your email is registered, a reset link has been sent.
                </div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-circle" style="margin-right: 8px;"></i> An error occurred. Please try again.
                </div>
            <?php endif; ?>
            
            <form method="post" action="send-reset.php">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="input-group has-icon">
                    <i class="fa fa-envelope"></i>
                    <input type="email" name="email" class="form-control" placeholder="Email address" required autocomplete="email">
                </div>
                
                <button type="submit" class="login-button">
                    Send Reset Link <i class="fa fa-paper-plane" style="margin-left: 8px; font-size: 0.9em;"></i>
                </button>
            </form>
            
            <div class="auth-links">
                <a href="login.php" style="color:var(--gray-500);"><i class="fa fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html> 
