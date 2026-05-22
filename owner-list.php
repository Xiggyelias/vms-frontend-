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

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];
$types = '';

if (!empty($search)) {
    $where_clause = "WHERE fullName LIKE ? OR idNumber LIKE ? OR phone LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
    $types = 'sss';
}

$conn = getLegacyDatabaseConnection();

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as count FROM applicants $where_clause";
if (!empty($params)) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['count'];
} else {
    $result = $conn->query($count_sql);
    $total_records = $result->fetch_assoc()['count'];
}

$total_pages = ceil($total_records / $per_page);

// Get owners with vehicle count
$sql = "
    SELECT a.*, COUNT(v.vehicle_id) as vehicle_count 
    FROM applicants a 
    LEFT JOIN vehicles v ON a.applicant_id = v.applicant_id 
    $where_clause 
    GROUP BY a.applicant_id 
    ORDER BY a.fullName 
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
$owners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner List - Vehicle Registration System</title>
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
                    <i class="fas fa-users icon"></i>
                    <h1>Owner Management</h1>
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
                <li><a href="owner-list.php" class="active">Manage Owners</a></li>
                <li><a href="vehicle-list.php">Manage Vehicles</a></li>
                <li><a href="manage-vehicle-status.php">Manage Vehicle Status</a></li>
                <li><a href="manage-disk-numbers.php">Manage Disk Numbers</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="user-dashboard.php">User View</a></li>
            </ul>
        </nav>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $total_records ?></div>
                <div class="stat-label">Total Owners</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($owners) ?></div>
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
                       placeholder="Search by name, ID number, or phone..." 
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search)): ?>
                    <a href="owner-list.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Owner Directory</h3>
            </div>
            <?php if (empty($owners)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No owners found</h3>
                    <p><?= !empty($search) ? 'Try adjusting your search criteria.' : 'No owners have been registered yet.' ?></p>
                </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                            <th><i class="fas fa-user"></i> Owner Details</th>
                            <th><i class="fas fa-id-card"></i> ID Number</th>
                            <th><i class="fas fa-phone"></i> Contact</th>
                            <th><i class="fas fa-car"></i> Vehicles</th>
                            <th><i class="fas fa-cogs"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($owners as $owner): ?>
                        <tr>
                                <td>
                                    <div class="owner-name"><?= htmlspecialchars($owner['fullName']) ?></div>
                                    <div class="owner-details"><?= ucfirst(htmlspecialchars($owner['registrantType'])) ?></div>
                                </td>
                            <td><?= htmlspecialchars($owner['idNumber']) ?></td>
                                <td>
                                    <div><?= htmlspecialchars($owner['phone'] ?? '') ?></div>
                                    <div class="owner-details"><?= htmlspecialchars($owner['Email'] ?? ($owner['email'] ?? 'N/A')) ?></div>
                                </td>
                            <td>
                                <a href="owner-details.php?id=<?= $owner['applicant_id'] ?>" 
                                   class="vehicle-count">
                                        <i class="fas fa-car"></i>
                                        <?= $owner['vehicle_count'] ?> Vehicle<?= $owner['vehicle_count'] != 1 ? 's' : '' ?>
                                </a>
                            </td>
                            <td class="action-buttons">
                                <button class="btn btn-primary btn-icon" 
                                            onclick="viewOwner(<?= $owner['applicant_id'] ?>)"
                                            title="View Details">
                                        <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn btn-secondary btn-icon" 
                                            onclick="editOwner(<?= $owner['applicant_id'] ?>)"
                                            title="Edit Owner">
                                        <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-danger btn-icon" 
                                            onclick="deleteOwner(<?= $owner['applicant_id'] ?>)"
                                            title="Delete Owner">
                                        <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                       class="<?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function logout() {
            window.location.href = 'logout.php';
        }

        function viewOwner(ownerId) {
            window.location.href = `owner-details.php?id=${ownerId}`;
        }

        function editOwner(ownerId) {
            window.location.href = `edit-owner.php?id=${ownerId}`;
        }

        function deleteOwner(ownerId) {
            if (!confirm('Are you sure you want to delete this owner? This will remove the owner record.')) {
                return;
            }

            const formData = new FormData();
            formData.append('user_id', ownerId);

            fetch('/backend/delete_user.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': '<?= htmlspecialchars(SecurityMiddleware::generateCSRFToken()) ?>' },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Refresh to reflect deletion
                    location.reload();
                } else {
                    alert(data.message || 'Failed to delete owner');
                }
            })
            .catch(() => alert('An error occurred. Please try again.'));
        }
    </script>
</body>
</html> 
