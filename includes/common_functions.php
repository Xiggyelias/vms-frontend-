<?php
// Common functions for frontend pages
function includeCommonAssets() {
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">';
    echo '<link rel="stylesheet" href="assets/css/styles.css">';
    echo '<link rel="stylesheet" href="assets/css/main.css">';
}

function asset($path) {
    $normalized = ltrim((string) $path, '/');
    if ($normalized !== '' && strpos($normalized, 'assets/') !== 0) {
        $normalized = 'assets/' . $normalized;
    }

    return $normalized;
}

function userLogout() {
    session_destroy();
    header('Location: login.php');
    exit;
}

function expectsJsonRequest() {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return stripos($accept, 'application/json') !== false
        || strcasecmp($requestedWith, 'XMLHttpRequest') === 0;
}

function requireAuth() {
    if (!isLoggedIn()) {
        if (expectsJsonRequest()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Authentication required.']);
            exit;
        }
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        if (expectsJsonRequest()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Admin authentication required.']);
            exit;
        }
        header('Location: admin-login.php');
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserType() {
    return $_SESSION['user_type'] ?? null;
}

function getCurrentUserEmail() {
    return $_SESSION['user_email'] ?? null;
}

function getCurrentUserName() {
    return $_SESSION['user_name'] ?? null;
}
?>
