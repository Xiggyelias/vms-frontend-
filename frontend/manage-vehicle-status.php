<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();
if (!legacyMutationsAllowed()) {
    rejectLegacyMutationEndpoint('manage-vehicle-status.php');
}

// Generate CSRF token for POST requests
$csrfToken = SecurityMiddleware::generateCSRFToken();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin access
requireAdmin();



$conn = getLegacyDatabaseConnection();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_id']) && isset($_POST['new_status'])) {
    $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$token || !SecurityMiddleware::verifyCSRFToken($token)) {
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $vehicle_id = $_POST['vehicle_id'];
    $new_status = $_POST['new_status'];
    
    // Validate status
    if (!in_array($new_status, ['active', 'inactive'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Update vehicle status
    $stmt = $conn->prepare("UPDATE vehicles SET status = ?, last_updated = NOW() WHERE vehicle_id = ?");
    $stmt->bind_param("si", $new_status, $vehicle_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Vehicle status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update vehicle status']);
    }
    exit;
}

// Get filter status from query parameter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Prepare the query based on filter
$query = "SELECT v.*, a.fullName as owner_name, a.Email, a.phone 
          FROM vehicles v 
          JOIN applicants a ON v.applicant_id = a.applicant_id";

if ($status_filter !== 'all') {
    $query .= " WHERE v.status = ?";
}

$query .= " ORDER BY v.last_updated DESC";

$stmt = $conn->prepare($query);

if ($status_filter !== 'all') {
    $stmt->bind_param("s", $status_filter);
}

$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vehicle Status - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    
</head>
<body>
    <div class="page-header">
        <div class="container">
            <div class="page-header-content">
                <div class="page-title">
                    <i class="fas fa-cogs icon"></i>
                    <h1>Vehicle Status Management</h1>
                </div>
                <div class="header-actions">
                    <a href="admin-dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <button onclick="logout()" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <nav class="admin-nav">
            <ul>
                <li><a href="admin-dashboard.php">Dashboard</a></li>
                <li><a href="owner-list.php">Manage Owners</a></li>
                <li><a href="vehicle-list.php">Manage Vehicles</a></li>
                <li><a href="manage-vehicle-status.php" class="active">Manage Vehicle Status</a></li>
                <li><a href="manage-disk-numbers.php">Manage Disk Numbers</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="user-dashboard.php">User View</a></li>
            </ul>
        </nav>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($vehicles) ?></div>
                <div class="stat-label">Total Vehicles</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_filter($vehicles, fn($v) => $v['status'] === 'active')) ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_filter($vehicles, fn($v) => $v['status'] === 'inactive')) ?></div>
                <div class="stat-label">Inactive</div>
            </div>
        </div>

        <div class="filter-container">
            <form class="filter-form" method="GET">
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Vehicles</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </form>
        </div>

        <div id="alert" class="alert" style="display: none;"></div>

        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Vehicle Status Directory</h3>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Registration Number</th>
                        <th>Make & Model</th>
                        <th>Owner</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vehicles)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-car"></i>
                                    <h3>No vehicles found</h3>
                                    <p>No vehicles match your filter criteria.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <tr>
                                <td>
                                    <div class="vehicle-info"><?= htmlspecialchars($vehicle['regNumber']) ?></div>
                                </td>
                                <td>
                                    <div class="vehicle-info"><?= htmlspecialchars($vehicle['make']) ?></div>
                                    <div class="vehicle-details"><?= htmlspecialchars($vehicle['model'] ?? '') ?></div>
                                </td>
                                <td>
                                    <div class="vehicle-info"><?= htmlspecialchars($vehicle['owner_name']) ?></div>
                                </td>
                                <td>
                                    <div class="vehicle-info"><?= htmlspecialchars($vehicle['phone']) ?></div>
                                    <div class="vehicle-details"><?= htmlspecialchars($vehicle['Email']) ?></div>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $vehicle['status'] ?>">
                                        <i class="fas fa-circle"></i>
                                    <?= ucfirst($vehicle['status']) ?>
                                </span>
                            </td>
                                <td>
                                    <div class="vehicle-details"><?= date('M j, Y g:i A', strtotime($vehicle['last_updated'])) ?></div>
                                </td>
                            <td>
                                    <div class="action-buttons">
                                <button 
                                            class="btn <?= $vehicle['status'] === 'active' ? 'btn-danger' : 'btn-success' ?> btn-icon"
                                    onclick="toggleStatus(<?= $vehicle['vehicle_id'] ?>, '<?= $vehicle['status'] ?>')"
                                >
                                            <i class="fas <?= $vehicle['status'] === 'active' ? 'fa-pause' : 'fa-play' ?>"></i>
                                    <?= $vehicle['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                </button>
                                    </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
            window.location.href = 'logout.php';
            }
        }

        function filterVehicles(status) {
            window.location.href = `manage-vehicle-status.php?status=${status}`;
        }

        function showAlert(message, type) {
            const alert = document.getElementById('alert');
            alert.textContent = message;
            alert.className = `alert alert-${type}`;
            alert.style.display = 'block';
            
            setTimeout(() => {
                alert.style.display = 'none';
            }, 3000);
        }

        function toggleStatus(vehicleId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            if (!confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this vehicle?`)) {
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch('/backend/manage-vehicle-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken,
                },
                body: `vehicle_id=${encodeURIComponent(vehicleId)}&new_status=${encodeURIComponent(newStatus)}&_token=${encodeURIComponent(csrfToken)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    // Reload the page to show updated status
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('An error occurred while updating the status', 'error');
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html> 

