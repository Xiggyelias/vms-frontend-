<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Determine user type before destroying session
$userType = '';
if (function_exists('getCurrentUserType')) {
    $userType = getCurrentUserType();
} elseif (isset($_SESSION['user_type'])) {
    $userType = $_SESSION['user_type'];
}

// Destroy all session data
session_unset();
session_destroy();

// Remove session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Redirect based on user's previous role
if ($userType === 'admin') {
    redirect('admin-login.php');
} else {
    redirect('login.php');
}
