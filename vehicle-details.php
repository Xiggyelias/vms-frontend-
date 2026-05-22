<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin access
requireAdmin();

// Check if vehicle ID is provided
if (!isset($_GET['id'])) {
    header("Location: admin-dashboard.php");
    exit();
}

$vehicle_id = (int)$_GET['id'];



$conn = getLegacyDatabaseConnection();

// Get vehicle details with owner information
$stmt = $conn->prepare("
    SELECT v.*, a.fullName as owner_name, a.idNumber as owner_id, a.phone as owner_phone, a.Email as owner_email, a.registrantType as owner_type
    FROM vehicles v 
    JOIN applicants a ON v.applicant_id = a.applicant_id 
    WHERE v.vehicle_id = ?
");

$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

if (!$vehicle) {
    header("Location: admin-dashboard.php");
    exit();
}

// Extract applicant_id from the first query result for subsequent queries
$applicant_id = (int)$vehicle['applicant_id'];

// Get authorized drivers for this vehicle
$stmt = $conn->prepare("SELECT * FROM authorized_driver WHERE applicant_id = ?");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Details - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <style>
        .details-wrapper { margin-top: 2rem; margin-bottom: 3rem; }
        .detail-row {
            display: flex;
            padding: 1.25rem 0;
            border-bottom: 1px solid var(--gray-100);
            align-items: center;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label {
            flex: 0 0 200px;
            color: var(--gray-500);
            font-weight: 600;
        }
        .detail-value {
            flex: 1;
            color: var(--gray-800);
            font-weight: 500;
        }
        .driver-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 1.25rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            box-shadow: var(--shadow-sm);
        }
        .driver-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-right: 1.5rem;
            background: var(--primary-100);
            padding: 1rem;
            border-radius: var(--border-radius-sm);
        }
        .driver-info {
            display: flex;
            flex-direction: column;
        }
        .driver-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }
        .driver-license {
            color: var(--gray-500);
            font-size: 0.9rem;
        }
        .reg-number-display {
            font-size: 1.25rem; 
            font-weight: 700; 
            background: var(--gray-100); 
            padding: 0.4rem 0.8rem; 
            border-radius: 6px; 
            border: 1px solid var(--gray-300);
            display: inline-block;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container">
            <div class="page-header-content">
                <div class="page-title d-flex align-items-center">
                    <i class="fas fa-car icon" style="margin-right: 1rem; font-size: 2rem;"></i>
                    <div>
                        <h1 class="m-0">Vehicle Details</h1>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="vehicle-list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Vehicles
                    </a>
                    <button onclick="logout()" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container details-wrapper">
        <nav class="admin-nav">
            <ul>
                <li><a href="admin-dashboard.php">Dashboard</a></li>
                <li><a href="owner-list.php">Manage Owners</a></li>
                <li><a href="vehicle-list.php" class="active">Manage Vehicles</a></li>
                <li><a href="manage-vehicle-status.php">Vehicle Status</a></li>
                <li><a href="manage-disk-numbers.php">Disk Numbers</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="user-dashboard.php">User View</a></li>
            </ul>
        </nav>

        <div class="content-grid" style="grid-template-columns: 1fr 1fr;">
            <!-- Left Column: Vehicle Info -->
            <div class="panel">
                <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="panel-title"><i class="fas fa-car-side" style="margin-right: 0.5rem; color: var(--primary);"></i> Vehicle Information</h2>
                </div>
                <div class="panel-body">
                    <div class="detail-row">
                        <div class="detail-label">Make & Model</div>
                        <div class="detail-value"><?= htmlspecialchars($vehicle['make'] . ' ' . ($vehicle['model'] ?? '')) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Registration Number</div>
                        <div class="detail-value">
                            <span class="reg-number-display">
                                <?= htmlspecialchars($vehicle['regNumber']) ?>
                            </span>
                        </div>
                    </div>
                    <?php if (!empty($vehicle['vin'])): ?>
                    <div class="detail-row">
                        <div class="detail-label">VIN / Chassis No</div>
                        <div class="detail-value"><?= htmlspecialchars($vehicle['vin']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <div class="detail-label">Disk Number</div>
                        <div class="detail-value">
                            <?php if (!empty($vehicle['disk_number'])): ?>
                                <span class="status-pill status-active"><?= htmlspecialchars($vehicle['disk_number']) ?></span>
                            <?php else: ?>
                                <span class="status-pill status-other">Not Assigned</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Registration Date</div>
                        <div class="detail-value"><?= htmlspecialchars($vehicle['registration_date']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Current Status</div>
                        <div class="detail-value">
                            <?php 
                                $status = strtolower($vehicle['status'] ?? 'active');
                                $statusClass = $status === 'active' ? 'status-active' : 'status-inactive';
                            ?>
                            <span class="status-pill <?= $statusClass ?>"><?= ucfirst($status) ?></span>
                        </div>
                    </div>
                    
                    <div style="margin-top: 2.5rem; display: flex; gap: 1rem;">
                        <button class="btn btn-primary" onclick="editVehicle(<?= $vehicle_id ?>)">
                            <i class="fas fa-edit"></i> Edit Vehicle
                        </button>
                        <button class="btn" style="background: var(--danger); color: white;" onclick="deleteVehicle(<?= $vehicle_id ?>)">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Column: Owner & Drivers Info -->
            <div>
                <div class="panel" style="margin-bottom: 2rem;">
                    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 class="panel-title"><i class="fas fa-user-circle" style="margin-right: 0.5rem; color: var(--primary);"></i> Owner Information</h2>
                        <a href="owner-details.php?id=<?= $applicant_id ?>" class="btn btn-secondary btn-sm" style="padding: 0.5rem 1rem;">View Profile</a>
                    </div>
                    <div class="panel-body">
                        <div class="detail-row">
                            <div class="detail-label">Full Name</div>
                            <div class="detail-value"><?= htmlspecialchars($vehicle['owner_name']) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">ID Number</div>
                            <div class="detail-value"><?= htmlspecialchars($vehicle['owner_id'] ?? 'N/A') ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Member Type</div>
                            <div class="detail-value"><?= ucfirst(htmlspecialchars($vehicle['owner_type'] ?? 'Standard')) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Contact Phone</div>
                            <div class="detail-value"><?= htmlspecialchars($vehicle['owner_phone'] ?? 'N/A') ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Email Address</div>
                            <div class="detail-value"><?= htmlspecialchars($vehicle['owner_email'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title"><i class="fas fa-id-card" style="margin-right: 0.5rem; color: var(--primary);"></i> Authorized Drivers</h2>
                    </div>
                    <div class="panel-body">
                        <?php if (count($drivers) > 0): ?>
                            <?php foreach ($drivers as $driver): ?>
                                <div class="driver-card">
                                    <div class="driver-icon"><i class="fas fa-steering-wheel"></i></div>
                                    <div class="driver-info">
                                        <div class="driver-name">
                                            <?= htmlspecialchars($driver['fullname'] ?? $driver['fullName'] ?? 'Unknown Driver') ?>
                                        </div>
                                        <div class="driver-license">
                                            License: <?= htmlspecialchars($driver['licenseNumber'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state" style="padding: 3rem 0; text-align: center;">
                                <i class="fas fa-user-slash" style="font-size: 2.5rem; margin-bottom: 1rem; color: var(--gray-300);"></i>
                                <div style="color: var(--gray-500);">No additional authorized drivers.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editVehicle(vehicleId) {
            window.location.href = `edit-vehicle.php?id=${vehicleId}`;
        }

        function deleteVehicle(vehicleId) {
            if (confirm('Are you sure you want to delete this vehicle? This action cannot be undone.')) {
                fetch('/backend/delete_vehicle.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': '<?= htmlspecialchars($csrfToken ?? SecurityMiddleware::generateCSRFToken()) ?>'
                    },
                    body: `vehicle_id=${encodeURIComponent(vehicleId)}&_token=${encodeURIComponent('<?= htmlspecialchars($csrfToken ?? SecurityMiddleware::generateCSRFToken()) ?>')}`
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Delete failed');
                    }

                    window.location.href = 'vehicle-list.php';
                })
                .catch(error => {
                    alert(error.message || 'Failed to delete vehicle');
                });
            }
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>
