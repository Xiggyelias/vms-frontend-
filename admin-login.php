<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/common_functions.php';
SecurityMiddleware::initialize();

// Check if admin is already logged in
if (isAdmin()) {
    redirect('admin-dashboard.php');
    exit;
}

$error = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'empty_fields') {
        $error = 'Please fill in all fields.';
    } elseif ($_GET['error'] === 'invalid_password') {
        $error = 'Invalid username or password.';
    } elseif ($_GET['error'] === 'too_many_attempts') {
        $error = 'Too many login attempts. Please wait before trying again.';
    }
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? '';
    if (!$token || !SecurityMiddleware::verifyCSRFToken($token)) {
        redirect('admin-login.php?error=invalid_password');
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $maxAttempts = (int) ($_ENV['RATE_LIMITING_MAX_LOGIN_ATTEMPTS'] ?? $_ENV['LOGIN_MAX_ATTEMPTS'] ?? 5);
    $lockoutSeconds = (int) ($_ENV['LOGIN_LOCKOUT_TIME'] ?? 900);
    $rateLimitKey = SecurityMiddleware::requestFingerprint('admin:' . strtolower($username));
    $rateLimitStatus = SecurityMiddleware::rateLimitStatus('admin_login', $rateLimitKey, $maxAttempts, $lockoutSeconds);

    if (!$rateLimitStatus['allowed']) {
        redirect('admin-login.php?error=too_many_attempts');
    }

    if ($username === '' || $password === '') {
        redirect('admin-login.php?error=empty_fields');
    }

    $result = adminLogin($username, $password);
    if ($result['success']) {
        SecurityMiddleware::clearRateLimit('admin_login', $rateLimitKey);
        redirect('admin-dashboard.php');
    } else {
        SecurityMiddleware::registerRateLimitAttempt('admin_login', $rateLimitKey, $lockoutSeconds);
        redirect('admin-login.php?error=invalid_password');
    }
}

// GET request: render admin login page
$csrfToken = SecurityMiddleware::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?= htmlspecialchars(APP_NAME) ?></title>
    <?php includeCommonAssets(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-form">
            <div class="login-header">
                <div class="logo">
                    <img src="<?= htmlspecialchars(asset('images/AULogo.png')) ?>" alt="AU Logo">
                </div>
                <h1>Admin Login</h1>
                <p>Sign in to the system dashboard</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-circle" style="margin-right: 8px;"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form action="admin-login.php" method="POST" autocomplete="off">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="input-group has-icon">
                    <i class="fa fa-user-gear"></i>
                    <input type="text" name="username" id="Username" class="form-control" placeholder="Admin Username" required>
                </div>
                
                <div class="input-group has-icon">
                    <i class="fa fa-lock"></i>
                    <input type="password" name="password" id="adminPassword" class="form-control" placeholder="Admin Password" required autocomplete="current-password">
                </div>
                
                <button type="submit" class="login-button">
                    Secure Login <i class="fa fa-arrow-right" style="margin-left: 8px; font-size: 0.9em;"></i>
                </button>
            </form>
            
            <div class="auth-links">
                <a href="login.php" style="color:var(--gray-500);"><i class="fa fa-arrow-left"></i> Back to User Login</a>
            </div>
        </div>
    </div>
</body>
</html>
