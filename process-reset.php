<?php
require_once __DIR__ . '/includes/init.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $error = '';
    $success = false;

    if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        $error = 'Invalid or missing token.';
    } elseif (!$csrf_token || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        $error = 'Invalid CSRF token.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } else {
        $conn = getLegacyDatabaseConnection();
        if ($conn) {
            $stmt = $conn->prepare('
                SELECT prt.id, prt.user_id, prt.expires_at 
                FROM password_reset_tokens prt 
                WHERE prt.token = ? AND prt.used = FALSE 
                LIMIT 1
            ');
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 1) {
                $stmt->bind_result($token_id, $user_id, $expires);
                $stmt->fetch();
                if (strtotime($expires) > time()) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt2 = $conn->prepare('UPDATE applicants SET password = ? WHERE applicant_id = ?');
                    $stmt2->bind_param('si', $password_hash, $user_id);
                    if ($stmt2->execute()) {
                        // Mark token as used
                        $stmt3 = $conn->prepare('UPDATE password_reset_tokens SET used = TRUE WHERE id = ?');
                        $stmt3->bind_param('i', $token_id);
                        $stmt3->execute();
                        $stmt3->close();
                        $success = true;
                        unset($_SESSION['csrf_token']);
                    }
                    $stmt2->close();
                } else {
                    $error = 'This reset link has expired.';
                }
            } else {
                $error = 'Invalid reset link.';
            }
            $stmt->close();
            $conn->close();
        } else {
            $error = 'Database connection error.';
        }
    }
} else {
    $error = 'Invalid request.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/main.css">
    
</head>
<body>
<div class="container">
    <img src="assets/images/AULogo.png" alt="AU Logo" class="logo" />
    <h2>Password Reset</h2>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">Your password has been reset. <a href="login.php">Login</a></div>
    <?php else: ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <div style="text-align:center;"><a href="forgot_password.php">Request new link</a></div>
    <?php endif; ?>
</div>
</body>
</html> 

