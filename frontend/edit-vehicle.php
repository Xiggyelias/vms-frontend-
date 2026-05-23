<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

requireAdmin();

$vehicleId = (int) ($_GET['id'] ?? $_POST['vehicle_id'] ?? 0);
if ($vehicleId <= 0) {
    header('Location: vehicle-list.php');
    exit;
}

$conn = getLegacyDatabaseConnection();
if (!$conn) {
    http_response_code(500);
    exit('Database connection failed.');
}

$error = '';
$csrfToken = SecurityMiddleware::generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$token || !SecurityMiddleware::verifyCSRFToken($token)) {
        $error = 'Invalid CSRF token.';
    } else {
    $make = trim((string) ($_POST['make'] ?? ''));
    $regNumber = trim((string) ($_POST['regNumber'] ?? ''));

    if ($make === '' || $regNumber === '') {
        $error = 'Make and registration number are required.';
    } else {
        $stmt = $conn->prepare('SELECT vehicle_id FROM vehicles WHERE regNumber = ? AND vehicle_id <> ? LIMIT 1');
        $stmt->bind_param('si', $regNumber, $vehicleId);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $error = 'That registration number is already in use.';
        } else {
            $stmt = $conn->prepare('UPDATE vehicles SET make = ?, regNumber = ?, last_updated = NOW() WHERE vehicle_id = ?');
            $stmt->bind_param('ssi', $make, $regNumber, $vehicleId);
            $stmt->execute();
            $stmt->close();

            $conn->close();
            header('Location: vehicle-details.php?id=' . $vehicleId);
            exit;
        }
    }
    }
}

$stmt = $conn->prepare('SELECT vehicle_id, make, regNumber FROM vehicles WHERE vehicle_id = ? LIMIT 1');
$stmt->bind_param('i', $vehicleId);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$vehicle) {
    header('Location: vehicle-list.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Vehicle - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    
</head>
<body>
    <div class="card">
        <h1 style="margin-top:0;">Edit Vehicle</h1>
        <p style="color:#6c757d;">Update the vehicle record and return to the detail view.</p>

        <?php if ($error): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="vehicle_id" value="<?= (int) $vehicle['vehicle_id'] ?>">
            <div class="form-group">
                <label for="make">Vehicle Make</label>
                <input class="form-input" id="make" name="make" required value="<?= htmlspecialchars((string) $vehicle['make']) ?>">
            </div>
            <div class="form-group">
                <label for="regNumber">Registration Number</label>
                <input class="form-input" id="regNumber" name="regNumber" required value="<?= htmlspecialchars((string) $vehicle['regNumber']) ?>">
            </div>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Save Changes</button>
                <a class="btn btn-secondary" href="vehicle-details.php?id=<?= (int) $vehicle['vehicle_id'] ?>">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>

