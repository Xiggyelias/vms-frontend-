<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();
if (!legacyMutationsAllowed()) {
    rejectLegacyMutationEndpoint('update_vehicle.php');
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
$make = trim((string) ($_POST['make'] ?? ''));
$regNumber = trim((string) ($_POST['regNumber'] ?? ''));

if ($vehicleId <= 0 || $make === '' || $regNumber === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vehicle ID, make, and registration number are required']);
    exit;
}

$conn = getLegacyDatabaseConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    $stmt = $conn->prepare('SELECT vehicle_id FROM vehicles WHERE regNumber = ? AND vehicle_id <> ? LIMIT 1');
    $stmt->bind_param('si', $regNumber, $vehicleId);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($exists) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'That registration number is already in use']);
        exit;
    }

    $stmt = $conn->prepare('UPDATE vehicles SET make = ?, regNumber = ?, last_updated = NOW() WHERE vehicle_id = ?');
    $stmt->bind_param('ssi', $make, $regNumber, $vehicleId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Vehicle updated']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => isDevelopment() ? $e->getMessage() : 'Failed to update vehicle']);
} finally {
    $conn->close();
}
?>

