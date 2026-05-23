<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$reset_url_base = $scheme . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/reset-password.php?token=';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $maxAttempts = max(3, (int) ($_ENV['RATE_LIMITING_MAX_LOGIN_ATTEMPTS'] ?? 5));
    $lockoutSeconds = max(300, (int) ($_ENV['LOGIN_LOCKOUT_TIME'] ?? 900));
    $rateLimitKey = SecurityMiddleware::requestFingerprint('password_reset');
    $rateLimitStatus = SecurityMiddleware::rateLimitStatus('password_reset', $rateLimitKey, $maxAttempts, $lockoutSeconds);

    if (!$rateLimitStatus['allowed']) {
        header('Location: forgot_password.php?error=1');
        exit;
    }

    if (!$token || !SecurityMiddleware::verifyCSRFToken($token)) {
        header('Location: forgot_password.php?error=1');
        exit;
    }

    if (!$email) {
        SecurityMiddleware::registerRateLimitAttempt('password_reset', $rateLimitKey, $lockoutSeconds);
        header('Location: forgot_password.php?error=1');
        exit;
    }

    $conn = getLegacyDatabaseConnection();
    if (!$conn) {
        SecurityMiddleware::registerRateLimitAttempt('password_reset', $rateLimitKey, $lockoutSeconds);
        header('Location: forgot_password.php?error=1');
        exit;
    }

    // Do not reveal if email exists
    $stmt = $conn->prepare('SELECT applicant_id FROM applicants WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($applicant_id);
        $stmt->fetch();
        
        // Generate token and expiry
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        // First delete any existing tokens for this user
        $deleteStmt = $conn->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
        $deleteStmt->bind_param('i', $applicant_id);
        $deleteStmt->execute();
        $deleteStmt->close();
        
        // Then insert the new token
        $stmt2 = $conn->prepare('INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
        $stmt2->bind_param('iss', $applicant_id, $token, $expires);
        $stmt2->execute();
        $stmt2->close();

        if (isDevelopment()) {
            error_log("Password reset token created for user: {$email}. Reset URL: " . $reset_url_base . urlencode($token));
        } else {
            error_log("Password reset token created for user: {$email}");
        }
    }
    $stmt->close();
    $conn->close();
    SecurityMiddleware::registerRateLimitAttempt('password_reset', $rateLimitKey, $lockoutSeconds);
    // Always redirect to the same page
    header('Location: forgot_password.php?sent=1');
    exit;
} else {
    header('Location: forgot_password.php?error=1');
    exit;
}
