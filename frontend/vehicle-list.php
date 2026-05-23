<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Require admin access
requireAdmin();



// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build WHERE clause for search and filters
$where_clauses = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_clauses[] = "(v.regNumber LIKE ? OR v.vin LIKE ? OR a.fullName LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= 'sss';
}

if (!empty($status)) {
    $where_clauses[] = "v.status = ?";
    $params[] = $status;
    $types .= 's';
}

$where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$conn = getLegacyDatabaseConnection();

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as count 
    FROM vehicles v 
    JOIN applicants a ON v.applicant_id = a.applicant_id 
    $where_clause
";
if (!empty($search)) {
    $where_clauses[] = "(v.regNumber LIKE ? OR a.fullName LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
    $types .= 'ss';

} else {
    $result = $conn->query($count_sql);
    $total_records = $result->fetch_assoc()['count'];
}

$total_pages = ceil($total_records / $per_page);

// Get vehicles with owner information
$sql = "
    SELECT v.*, a.fullName as owner_name 
    FROM vehicles v 
    JOIN applicants a ON v.applicant_id = a.applicant_id 
    $where_clause 
    ORDER BY v.registration_date DESC 
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $params[] = $per_page;
    $params[] = $offset;
    $types .= 'ii';
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
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
    <title>Vehicle List - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <meta name="csrf-token" content="<?= htmlspecialchars(SecurityMiddleware::generateCSRFToken()) ?>">
    
</head>
<body>
    <div class="page-header">
        <div class="container">
            <div class="page-header-content">
                <div class="page-title">
                    <i class="fas fa-car icon"></i>
                    <h1>Vehicle Management</h1>
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
                <li><a href="vehicle-list.php" class="active">Manage Vehicles</a></li>
                <li><a href="manage-vehicle-status.php">Manage Vehicle Status</a></li>
                <li><a href="manage-disk-numbers.php">Manage Disk Numbers</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="user-dashboard.php">User View</a></li>
            </ul>
        </nav>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $total_records ?></div>
                <div class="stat-label">Total Vehicles</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($vehicles) ?></div>
                <div class="stat-label">Showing</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_pages ?></div>
                <div class="stat-label">Pages</div>
            </div>
        </div>

        <div class="search-container">
            <form class="search-form" method="GET">
                <input type="text" name="search" class="search-input" 
                       placeholder="Search by plate number, VIN, or owner name..." 
                       value="<?= htmlspecialchars($search ?? '') ?>">
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Expired</option>
                </select>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search) || !empty($status)): ?>
                    <a href="vehicle-list.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Vehicle Directory</h3>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Registration Number</th>
                        <th>Disk Number</th>
                        <th>Make & Model</th>
                        <th>Owner</th>
                        <th>Status</th>
                        <th>Registration Date</th>
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
                                    <p>No vehicles match your search criteria.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <tr>
                                <td>
                                    <div class="vehicle-info"><?= htmlspecialchars($vehicle['regNumber'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <div class="vehicle-details"><?= htmlspecialchars($vehicle['disk_number'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <div class="vehicle-info"><?= htmlspecialchars($vehicle['make'] ?? 'N/A') ?></div>
                                    <div class="vehicle-details"><?= htmlspecialchars($vehicle['model'] ?? '') ?></div>
                                </td>
                                <td>
                                    <div class="vehicle-info"><?= htmlspecialchars($vehicle['owner_name'] ?? 'N/A') ?></div>
                                </td>
                            <td>
                                <span class="status-badge status-<?= strtolower($vehicle['status'] ?? 'active') ?>">
                                        <i class="fas fa-circle"></i>
                                    <?= ucfirst($vehicle['status'] ?? 'Active') ?>
                                </span>
                            </td>
                                <td>
                                    <div class="vehicle-details"><?= htmlspecialchars($vehicle['registration_date'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="vehicle-details.php?id=<?= $vehicle['vehicle_id'] ?? 0 ?>" 
                                           class="btn btn-primary btn-icon">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status) ? '&status=' . urlencode($status) : '' ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status) ? '&status=' . urlencode($status) : '' ?>" 
                       class="<?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($status) ? '&status=' . urlencode($status) : '' ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
            window.location.href = 'logout.php';
        }
        }
    </script>
</body>
</html> 
