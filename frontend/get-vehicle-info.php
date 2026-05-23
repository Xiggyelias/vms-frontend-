<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();
requireAuth();

header('Content-Type: application/json');

$response = [
    'success' => false,
    'data' => null,
    'error' => null,
    'isRegistered' => false
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plate_number'])) {
    $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$token || !SecurityMiddleware::verifyCSRFToken($token)) {
        http_response_code(419);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $plateNumber = trim($_POST['plate_number']);
    if (empty($plateNumber)) {
        $response['error'] = "No plate number provided";
        echo json_encode($response);
        exit;
    }
    $conn = getLegacyDatabaseConnection();
    $stmt = $conn->prepare("
        SELECT 
            v.vehicle_id,
            v.applicant_id,
            v.regNumber,
            v.make,
            v.owner,
            v.address,
            v.PlateNumber,
            v.registration_date,
            v.disk_number,
            a.idNumber,
            a.phone,
            a.email,
            d.fullname,
            d.licenseNumber
        FROM vehicles v
        LEFT JOIN applicants a ON v.applicant_id = a.applicant_id
        LEFT JOIN authorized_driver d ON v.vehicle_id = d.vehicle_id
        WHERE v.PlateNumber = ?
    ");
    $stmt->bind_param("s", $plateNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $response['success'] = true;
        $response['data'] = $result->fetch_assoc();
        $response['isRegistered'] = true;
    } else {
        $response['error'] = "Unregistered vehicle detected";
        $response['isRegistered'] = false;
    }
    $stmt->close();
    $conn->close();
    echo json_encode($response);
    exit;
} else {
    $response['error'] = "Invalid request";
    echo json_encode($response);
    exit;
} 
