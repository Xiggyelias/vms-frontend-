<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();
if (!legacyMutationsAllowed()) {
    rejectLegacyMutationEndpoint('delete_user.php');
}


// Require admin
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// CSRF validation
$token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!$token || !SecurityMiddleware::verifyCSRFToken($token)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
if (!$userId) { echo json_encode(['success' => false, 'message' => 'Invalid user']); exit; }

// Optional: check foreign key constraints or soft delete
$conn = getLegacyDatabaseConnection();
$stmt = $conn->prepare('DELETE FROM applicants WHERE applicant_id = ?');
$stmt->bind_param('i', $userId);
$ok = $stmt->execute();
echo json_encode(['success' => $ok, 'message' => $ok ? 'User deleted' : 'Delete failed']);
exit;


