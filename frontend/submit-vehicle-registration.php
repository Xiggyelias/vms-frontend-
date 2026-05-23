<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();
if (!legacyMutationsAllowed()) {
    rejectLegacyMutationEndpoint('submit-vehicle-registration.php');
}


requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: vehicle-registration-form.php');
    exit;
}

$token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!$token || !SecurityMiddleware::verifyCSRFToken($token)) {
    http_response_code(419);
    exit('Invalid CSRF token.');
}

$applicantId = (int) ($_POST['applicant_id'] ?? getCurrentUserId());
$sessionUserId = (int) getCurrentUserId();

if ($applicantId <= 0 || $sessionUserId <= 0 || $applicantId !== $sessionUserId) {
    http_response_code(403);
    exit('Invalid registration request.');
}

$conn = getLegacyDatabaseConnection();
if (!$conn) {
    http_response_code(500);
    exit('Database connection failed.');
}

function tableColumns(mysqli $conn, string $table): array {
    $columns = [];
    if ($result = $conn->query("SHOW COLUMNS FROM {$table}")) {
        while ($row = $result->fetch_assoc()) {
            $columns[strtolower((string) $row['Field'])] = true;
        }
        $result->close();
    }

    return $columns;
}

$fullName = trim((string) ($_POST['fullName'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$idNumber = trim((string) ($_POST['idNumber'] ?? ''));
$licenseNumber = trim((string) ($_POST['licenseNumber'] ?? ''));
$licenseClass = trim((string) ($_POST['licenseClass'] ?? ''));
$licenseDate = trim((string) ($_POST['licenseDate'] ?? ''));
$vehicleRegNumber = trim((string) ($_POST['vehicleRegistrationNumber'] ?? ''));
$vehicleMake = trim((string) ($_POST['vehicleMake'] ?? ''));
$registeredOwner = trim((string) ($_POST['registeredOwner'] ?? ''));
$vehicleAddress = trim((string) ($_POST['vehicleAddress'] ?? ''));
$plateNumber = trim((string) ($_POST['plateNumber'] ?? ''));
$dateOfBirth = trim((string) ($_POST['dateOfBirth'] ?? ''));
$college = trim((string) ($_POST['college'] ?? ''));
$drivers = $_POST['drivers'] ?? [];

if ($fullName === '' || $phone === '' || $idNumber === '' || $licenseNumber === '' || $licenseClass === '' || $licenseDate === '' || $vehicleRegNumber === '' || $vehicleMake === '' || $registeredOwner === '' || $vehicleAddress === '' || $plateNumber === '') {
    $conn->close();
    http_response_code(400);
    exit('Missing required registration fields.');
}

try {
    $conn->begin_transaction();

    $applicantColumns = tableColumns($conn, 'applicants');
    $vehicleColumns = tableColumns($conn, 'vehicles');
    $driverColumns = tableColumns($conn, 'authorized_driver');

    $stmt = $conn->prepare('SELECT registrantType FROM applicants WHERE applicant_id = ? LIMIT 1');
    $stmt->bind_param('i', $applicantId);
    $stmt->execute();
    $applicant = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$applicant) {
        throw new RuntimeException('Applicant not found.');
    }

    $applicantUpdates = [];
    $applicantValues = [];
    $applicantTypes = '';
    $applicantFieldMap = [
        'fullname' => $fullName,
        'dateofbirth' => $dateOfBirth,
        'phone' => $phone,
        'idnumber' => $idNumber,
        'college' => $college,
        'licensenumber' => $licenseNumber,
        'licenseclass' => $licenseClass,
        'licensedate' => $licenseDate,
    ];

    foreach ($applicantFieldMap as $column => $value) {
        if (!isset($applicantColumns[$column])) {
            continue;
        }

        $realColumn = array_search(true, array_intersect_key($applicantColumns, [$column => true]), true);
        $applicantUpdates[] = "{$realColumn} = ?";
        $applicantValues[] = $value;
        $applicantTypes .= 's';
    }

    if (!empty($applicantUpdates)) {
        $sql = 'UPDATE applicants SET ' . implode(', ', $applicantUpdates) . ' WHERE applicant_id = ?';
        $applicantValues[] = $applicantId;
        $applicantTypes .= 'i';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($applicantTypes, ...$applicantValues);
        $stmt->execute();
        $stmt->close();
    }

    $registrantType = strtolower((string) ($applicant['registrantType'] ?? ''));
    $vehicleStatus = $registrantType === 'student' ? 'active' : 'inactive';

    if ($registrantType === 'student') {
        $stmt = $conn->prepare("UPDATE vehicles SET status = 'inactive', last_updated = NOW() WHERE applicant_id = ?");
        $stmt->bind_param('i', $applicantId);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare('SELECT vehicle_id FROM vehicles WHERE regNumber = ? LIMIT 1');
    $stmt->bind_param('s', $vehicleRegNumber);
    $stmt->execute();
    $existingVehicle = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existingVehicle) {
        throw new RuntimeException('That registration number is already registered.');
    }

    $vehicleInsertColumns = ['applicant_id', 'regNumber', 'make', 'owner', 'address', 'PlateNumber', 'status'];
    $vehicleInsertValues = [$applicantId, $vehicleRegNumber, $vehicleMake, $registeredOwner, $vehicleAddress, $plateNumber, $vehicleStatus];
    $vehicleInsertTypes = 'issssss';

    if (isset($vehicleColumns['registration_date'])) {
        $vehicleInsertColumns[] = 'registration_date';
        $vehicleInsertValues[] = date('Y-m-d H:i:s');
        $vehicleInsertTypes .= 's';
    }

    if (isset($vehicleColumns['last_updated'])) {
        $vehicleInsertColumns[] = 'last_updated';
        $vehicleInsertValues[] = date('Y-m-d H:i:s');
        $vehicleInsertTypes .= 's';
    }

    $placeholders = implode(', ', array_fill(0, count($vehicleInsertColumns), '?'));
    $stmt = $conn->prepare('INSERT INTO vehicles (' . implode(', ', $vehicleInsertColumns) . ') VALUES (' . $placeholders . ')');
    $stmt->bind_param($vehicleInsertTypes, ...$vehicleInsertValues);
    $stmt->execute();
    $vehicleId = (int) $conn->insert_id;
    $stmt->close();

    if (is_array($drivers)) {
        foreach ($drivers as $driverKey => $driver) {
            $driverFullName = trim((string) ($driver['fullName'] ?? ''));
            $driverLicenseNumber = trim((string) ($driver['licenseNumber'] ?? ''));
            $driverContact = trim((string) ($driver['contactInfo'] ?? ''));

            if ($driverFullName === '' || $driverLicenseNumber === '') {
                continue;
            }

            $driverId = is_numeric($driverKey) ? (int) $driverKey : 0;

            if ($driverId > 0) {
                $stmt = $conn->prepare('UPDATE authorized_driver SET vehicle_id = ?, fullname = ?, licenseNumber = ?, contact = ?, applicant_id = ? WHERE Id = ? AND applicant_id = ?');
                $stmt->bind_param('isssiii', $vehicleId, $driverFullName, $driverLicenseNumber, $driverContact, $applicantId, $driverId, $applicantId);
                $stmt->execute();
                $stmt->close();
                continue;
            }

            $insertColumns = ['vehicle_id', 'fullname', 'licenseNumber', 'contact'];
            $insertValues = [$vehicleId, $driverFullName, $driverLicenseNumber, $driverContact];
            $insertTypes = 'isss';

            if (isset($driverColumns['applicant_id'])) {
                $insertColumns[] = 'applicant_id';
                $insertValues[] = $applicantId;
                $insertTypes .= 'i';
            }

            $driverPlaceholders = implode(', ', array_fill(0, count($insertColumns), '?'));
            $stmt = $conn->prepare('INSERT INTO authorized_driver (' . implode(', ', $insertColumns) . ') VALUES (' . $driverPlaceholders . ')');
            $stmt->bind_param($insertTypes, ...$insertValues);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0) {
        $message = sprintf('New vehicle registration submitted for %s (%s).', $vehicleMake, $vehicleRegNumber);
        $stmt = $conn->prepare("INSERT INTO notifications (type, message, created_at, is_read) VALUES ('new_registration', ?, NOW(), FALSE)");
        if ($stmt) {
            $stmt->bind_param('s', $message);
            $stmt->execute();
            $stmt->close();
        }
    }

    $conn->commit();
    $conn->close();

    header('Location: user-dashboard.php?registration=success');
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    http_response_code(500);
    exit(isDevelopment() ? $e->getMessage() : 'Vehicle registration failed.');
}
?>

