<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();
if (!legacyMutationsAllowed()) {
    rejectLegacyMutationEndpoint('mark_notification_read.php');
}


header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);
$notification_id = $data['notification_id'] ?? null;
$token = $data['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

if (!$notification_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
    exit();
}

if (!$token || !SecurityMiddleware::verifyCSRFToken($token)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}


try {
    $conn = getLegacyDatabaseConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Mark notification as read
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to mark notification as read");
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error marking notification as read: ' . $e->getMessage()
    ]);
}

$conn->close();
?>

