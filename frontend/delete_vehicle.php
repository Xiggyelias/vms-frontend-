<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();
if (!legacyMutationsAllowed()) {
    rejectLegacyMutationEndpoint('delete_vehicle.php');
}


requireAdmin();

header('Content-Type: application/json; charset=UTF-8');

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

$vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
if ($vehicleId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid vehicle ID']);
    exit;
}

$conn = getLegacyDatabaseConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare('DELETE FROM authorized_driver WHERE vehicle_id = ?');
    $stmt->bind_param('i', $vehicleId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM vehicles WHERE vehicle_id = ?');
    $stmt->bind_param('i', $vehicleId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected <= 0) {
        throw new RuntimeException('Vehicle not found');
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Vehicle deleted']);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => isDevelopment() ? $e->getMessage() : 'Failed to delete vehicle']);
} finally {
    $conn->close();
}
?>

