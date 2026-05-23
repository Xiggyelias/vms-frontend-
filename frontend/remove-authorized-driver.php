<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();
if (!legacyMutationsAllowed()) {
    rejectLegacyMutationEndpoint('remove-authorized-driver.php');
}


requireAuth();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$driverId = (int) ($payload['driver_id'] ?? $_POST['driver_id'] ?? 0);
$token = $payload['_token'] ?? ($_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
$userId = (int) getCurrentUserId();

if ($driverId <= 0 || $userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid driver request']);
    exit;
}

if (!$token || !SecurityMiddleware::verifyCSRFToken($token)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$conn = getLegacyDatabaseConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    $stmt = $conn->prepare('DELETE FROM authorized_driver WHERE Id = ? AND applicant_id = ?');
    $stmt->bind_param('ii', $driverId, $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected <= 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Driver not found']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Driver removed']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => isDevelopment() ? $e->getMessage() : 'Failed to remove driver']);
} finally {
    $conn->close();
}
?>

