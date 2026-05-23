<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Generate CSRF token
$csrfToken = SecurityMiddleware::generateCSRFToken();

// Require admin access
requireAdmin();



$conn = getLegacyDatabaseConnection();

// Initialize searched vehicle holder
$searched_vehicle = null;

// Handle disk number assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_disk') {
    $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$token || !SecurityMiddleware::verifyCSRFToken($token)) {
        http_response_code(419);
        $error_message = 'Invalid CSRF token';
    } else {
    $vehicle_id = $_POST['vehicle_id'];
    $disk_number = $_POST['disk_number'];
    
    // Standardize to the correct column name 'disk_number'
    $stmt = $conn->prepare("UPDATE vehicles SET disk_number = ? WHERE vehicle_id = ?");
    $stmt->bind_param("si", $disk_number, $vehicle_id);
    
    if ($stmt->execute()) {
        $success_message = "Disk number assigned successfully!";
    } else {
        $error_message = "Failed to assign disk number: " . $stmt->error;
    }
    $stmt->close();
    }
}

// If a registration number is searched, fetch that specific vehicle pending assignment
if (isset($_GET['search_reg']) && trim($_GET['search_reg']) !== '') {
    $reg = trim($_GET['search_reg']);
    $stmt = $conn->prepare("
        SELECT v.*, a.fullName as owner_name
        FROM vehicles v
        JOIN applicants a ON v.applicant_id = a.applicant_id
        WHERE v.regNumber = ? AND (v.disk_number IS NULL OR v.disk_number = '')
        LIMIT 1
    ");
    $stmt->bind_param("s", $reg);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $searched_vehicle = $res->fetch_assoc();
    }
    $stmt->close();
}

// Get vehicles without disk numbers
$stmt = $conn->prepare("
    SELECT v.*, a.fullName as owner_name 
    FROM vehicles v 
    JOIN applicants a ON v.applicant_id = a.applicant_id 
    WHERE v.disk_number IS NULL OR v.disk_number = ''
    ORDER BY v.registration_date DESC
");
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Disk Numbers - Vehicle Registration System</title>
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
                    <i class="fas fa-hashtag icon"></i>
                    <h1>Disk Number Management</h1>
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
                <li><a href="manage-vehicle-status.php">Manage Vehicle Status</a></li>
                <li><a href="manage-disk-numbers.php" class="active">Manage Disk Numbers</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="user-dashboard.php">User View</a></li>
            </ul>
        </nav>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($vehicles) ?></div>
                <div class="stat-label">Pending Assignment</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= !empty($searched_vehicle) ? '1' : '0' ?></div>
                <div class="stat-label">Selected Vehicle</div>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

<div class="search-container">
    <form class="search-form" method="GET">
        <input type="text" name="search_reg" class="search-input" 
               placeholder="Enter Registration Number..." 
                       value="<?= htmlspecialchars($_GET['search_reg'] ?? '') ?>" required>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($_GET['search_reg'])): ?>
                    <a href="manage-disk-numbers.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
        <?php endif; ?>
    </form>
</div>

        <?php if (!empty($searched_vehicle)): ?>
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-car"></i> Vehicle Details</h3>
                </div>
                <div style="padding: 2rem;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                        <div>
                            <div class="vehicle-details">Registration Number</div>
                            <div class="vehicle-info"><?= htmlspecialchars($searched_vehicle['regNumber']) ?></div>
                        </div>
                        <div>
                            <div class="vehicle-details">Make & Model</div>
                            <div class="vehicle-info"><?= htmlspecialchars($searched_vehicle['make']) ?></div>
                    </div>
                        <div>
                            <div class="vehicle-details">Owner</div>
                            <div class="vehicle-info"><?= htmlspecialchars($searched_vehicle['owner_name']) ?></div>
                    </div>
                        <div>
                            <div class="vehicle-details">Status</div>
                            <div class="vehicle-info">
                                <span class="status-badge status-pending">
                                    <i class="fas fa-clock"></i> Pending Assignment
                                </span>
                    </div>
                        </div>
                    </div>
                    
                    <form method="POST" style="background: var(--gray-100); padding: 1.5rem; border-radius: var(--border-radius);">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="assign_disk">
                    <input type="hidden" name="vehicle_id" value="<?= $searched_vehicle['vehicle_id'] ?>">
                        
                        <div class="form-group">
                            <label for="disk_number">Assign Disk Number</label>
                            <input type="text" name="disk_number" id="disk_number" class="form-control" 
                                   placeholder="Enter disk number (e.g., AU-001)" required
                           pattern="[A-Za-z0-9-]+" title="Only letters, numbers, and hyphens allowed">
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Assign Disk Number
                        </button>
                </form>
                </div>
            </div>
        <?php else: ?>
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-list"></i> Vehicles Pending Disk Number Assignment</h3>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Registration Number</th>
                            <th>Make & Model</th>
                            <th>Owner</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vehicles)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fas fa-check-circle"></i>
                                        <h3>All vehicles have disk numbers assigned</h3>
                                        <p>No vehicles are pending disk number assignment.</p>
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
                                        <div class="vehicle-details"><?= htmlspecialchars($vehicle['registration_date']) ?></div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?search_reg=<?= urlencode($vehicle['regNumber']) ?>" 
                                               class="btn btn-primary btn-icon">
                                                <i class="fas fa-hashtag"></i> Assign Disk
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        // Add form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const diskNumberInput = this.querySelector('input[name="disk_number"]');
                if (diskNumberInput && !/^[A-Za-z0-9-]+$/.test(diskNumberInput.value)) {
                    e.preventDefault();
                    alert('Disk number can only contain letters, numbers, and hyphens');
                }
            });
        });
    </script>
</body>
</html> 
