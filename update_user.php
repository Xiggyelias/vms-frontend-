<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();
if (!legacyMutationsAllowed()) {
    rejectLegacyMutationEndpoint('update_user.php');
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

$action = $_POST['action'] ?? '';

$conn = getLegacyDatabaseConnection();

if ($action === 'toggle_status') {
    $userId = (int)($_POST['user_id'] ?? 0);
    if (!$userId) { echo json_encode(['success' => false, 'message' => 'Invalid user']); exit; }
    // Read current status
    $stmt = $conn->prepare('SELECT COALESCE(status, "active") as status FROM applicants WHERE applicant_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    if (!$current) { echo json_encode(['success' => false, 'message' => 'User not found']); exit; }
    $newStatus = strtolower($current['status']) === 'suspended' ? 'active' : 'suspended';
    $stmt = $conn->prepare('UPDATE applicants SET status = ? WHERE applicant_id = ?');
    $stmt->bind_param('si', $newStatus, $userId);
    $ok = $stmt->execute();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Status updated' : 'Failed to update']);
    exit;
}

// Update profile
$userId = (int)($_POST['user_id'] ?? 0);
$fullName = trim($_POST['fullName'] ?? '');
$email = trim($_POST['Email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$type = strtolower(trim($_POST['registrantType'] ?? ''));

if (!$userId || !$fullName || !$email || !in_array($type, ['student','staff','guest'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']); exit;
}

$stmt = $conn->prepare('UPDATE applicants SET fullName = ?, Email = ?, phone = ?, registrantType = ? WHERE applicant_id = ?');
$stmt->bind_param('ssssi', $fullName, $email, $phone, $type, $userId);
$ok = $stmt->execute();
echo json_encode(['success' => $ok, 'message' => $ok ? 'User updated' : 'Update failed']);
exit;


