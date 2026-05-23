<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

requireAdmin();

$conn = getLegacyDatabaseConnection();

if (!$conn) {
    http_response_code(500);
    exit('Database connection failed.');
}

function fetchScalar(mysqli $conn, string $sql): int {
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_row();
    $result->close();

    return (int) ($row[0] ?? 0);
}

function tableExists(mysqli $conn, string $table): bool {
    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    if (!$result) {
        return false;
    }

    $exists = $result->num_rows > 0;
    $result->close();

    return $exists;
}

$stats = [
    'owners' => fetchScalar($conn, 'SELECT COUNT(*) FROM applicants'),
    'vehicles' => fetchScalar($conn, 'SELECT COUNT(*) FROM vehicles'),
    'drivers' => fetchScalar($conn, 'SELECT COUNT(*) FROM authorized_driver'),
    'active_vehicles' => fetchScalar($conn, "SELECT COUNT(*) FROM vehicles WHERE status = 'active'"),
    'inactive_vehicles' => fetchScalar($conn, "SELECT COUNT(*) FROM vehicles WHERE status = 'inactive'"),
    'reports' => tableExists($conn, 'admin_reports') ? fetchScalar($conn, 'SELECT COUNT(*) FROM admin_reports') : 0,
];

$recentVehicles = [];
$vehicleSql = "
    SELECT
        v.vehicle_id,
        v.regNumber,
        v.make,
        v.status,
        v.registration_date,
        v.last_updated,
        a.fullName AS owner_name
    FROM vehicles v
    LEFT JOIN applicants a ON a.applicant_id = v.applicant_id
    ORDER BY COALESCE(v.last_updated, v.registration_date) DESC
    LIMIT 8
";
if ($vehicleResult = $conn->query($vehicleSql)) {
    $recentVehicles = $vehicleResult->fetch_all(MYSQLI_ASSOC);
    $vehicleResult->close();
}

$recentReports = [];
if (tableExists($conn, 'admin_reports')) {
    $reportSql = "
        SELECT id, title, category, report_date, created_at
        FROM admin_reports
        ORDER BY COALESCE(report_date, created_at) DESC
        LIMIT 5
    ";
    if ($reportResult = $conn->query($reportSql)) {
        $recentReports = $reportResult->fetch_all(MYSQLI_ASSOC);
        $reportResult->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
</head>
<body>
    <header class="page-header">
        <div class="container">
            <div class="page-header-content">
                <div class="page-title">
                    <div class="header-logo" style="width:72px;">
                        <img src="assets/images/AULogo.png" alt="AU Logo" style="width:100%;height:auto;">
                    </div>
                    <div>
                        <h1>Admin Dashboard</h1>
                        <p class="page-subtitle">Overview of owners, vehicles, drivers, and reports.</p>
                    </div>
                </div>
                <div class="header-actions">
                    <a class="btn btn-secondary" href="user-dashboard.php"><i class="fa fa-user"></i> User View</a>
                    <a class="btn btn-primary" href="logout.php"><i class="fa fa-right-from-bracket"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <nav class="admin-nav">
            <ul>
                <li><a class="active" href="admin-dashboard.php">Dashboard</a></li>
                <li><a href="owner-list.php">Owners</a></li>
                <li><a href="vehicle-list.php">Vehicles</a></li>
                <li><a href="manage-vehicle-status.php">Vehicle Status</a></li>
                <li><a href="manage-disk-numbers.php">Disk Numbers</a></li>
                <li><a href="search-vehicle.php">Search Vehicle</a></li>
                <li><a href="admin-users.php">Users</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
            </ul>
        </nav>

        <section class="stats-grid">
            <article class="stat-card">
                <div class="stat-label">Registered Owners</div>
                <div class="stat-value"><?= number_format($stats['owners']) ?></div>
            </article>
            <article class="stat-card">
                <div class="stat-label">Total Vehicles</div>
                <div class="stat-value"><?= number_format($stats['vehicles']) ?></div>
            </article>
            <article class="stat-card">
                <div class="stat-label">Authorized Drivers</div>
                <div class="stat-value"><?= number_format($stats['drivers']) ?></div>
            </article>
            <article class="stat-card">
                <div class="stat-label">Active Vehicles</div>
                <div class="stat-value"><?= number_format($stats['active_vehicles']) ?></div>
            </article>
            <article class="stat-card">
                <div class="stat-label">Inactive Vehicles</div>
                <div class="stat-value"><?= number_format($stats['inactive_vehicles']) ?></div>
            </article>
            <article class="stat-card">
                <div class="stat-label">Reports Logged</div>
                <div class="stat-value"><?= number_format($stats['reports']) ?></div>
            </article>
        </section>

        <section class="quick-links">
            <a class="quick-link" href="owner-list.php"><i class="fa fa-address-book"></i> Manage Owners</a>
            <a class="quick-link" href="vehicle-list.php"><i class="fa fa-car"></i> Review Vehicles</a>
            <a class="quick-link" href="manage-vehicle-status.php"><i class="fa fa-traffic-light"></i> Update Status</a>
            <a class="quick-link" href="search-vehicle.php"><i class="fa fa-magnifying-glass"></i> Search Vehicle</a>
            <a class="quick-link" href="admin_reports.php"><i class="fa fa-file-lines"></i> Reports</a>
        </section>

        <section class="content-grid">
            <article class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Recent Vehicles</h2>
                    <a href="vehicle-list.php">View all</a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Registration</th>
                                <th>Vehicle</th>
                                <th>Owner</th>
                                <th>Status</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentVehicles)): ?>
                                <tr>
                                    <td class="empty-state" colspan="5">No vehicles found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentVehicles as $vehicle): ?>
                                    <?php
                                    $status = strtolower((string) ($vehicle['status'] ?? 'inactive'));
                                    $statusClass = $status === 'active' ? 'status-active' : ($status === 'inactive' ? 'status-inactive' : 'status-other');
                                    $updatedAt = $vehicle['last_updated'] ?? $vehicle['registration_date'] ?? null;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($vehicle['regNumber'] ?? 'N/A')) ?></td>
                                        <td><?= htmlspecialchars(trim((string) (($vehicle['make'] ?? '') ?: 'Unknown'))) ?></td>
                                        <td><?= htmlspecialchars((string) ($vehicle['owner_name'] ?? 'Unknown')) ?></td>
                                        <td><span class="status-pill <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
                                        <td><?= htmlspecialchars($updatedAt ? date('M j, Y g:i A', strtotime((string) $updatedAt)) : 'N/A') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Recent Reports</h2>
                    <a href="admin_reports.php">Open reports</a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentReports)): ?>
                                <tr>
                                    <td class="empty-state" colspan="3">No reports found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentReports as $report): ?>
                                    <?php $reportDate = $report['report_date'] ?? $report['created_at'] ?? null; ?>
                                    <tr>
                                        <td>
                                            <a href="edit_report.php?id=<?= (int) $report['id'] ?>">
                                                <?= htmlspecialchars((string) ($report['title'] ?? 'Untitled report')) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars(ucfirst((string) ($report['category'] ?? 'general'))) ?></td>
                                        <td><?= htmlspecialchars($reportDate ? date('M j, Y', strtotime((string) $reportDate)) : 'N/A') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>
</body>
</html>
