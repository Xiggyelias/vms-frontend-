<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

requireAdmin();

$csrfToken = SecurityMiddleware::generateCSRFToken();
$conn = getLegacyDatabaseConnection();

if (!$conn) {
    http_response_code(500);
    exit('Database connection failed.');
}

function adminReportsTableExists(mysqli $conn): bool {
    $result = $conn->query("SHOW TABLES LIKE 'admin_reports'");
    if (!$result) {
        return false;
    }

    $exists = $result->num_rows > 0;
    $result->close();

    return $exists;
}

function adminReportColumns(mysqli $conn): array {
    $columns = [];
    if ($result = $conn->query("SHOW COLUMNS FROM admin_reports")) {
        while ($row = $result->fetch_assoc()) {
            $columns[strtolower((string) $row['Field'])] = true;
        }
        $result->close();
    }

    return $columns;
}

function createReportFile(string $inputName): array {
    if (empty($_FILES[$inputName]['name'])) {
        return ['path' => null, 'error' => null];
    }

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $tmp = $_FILES[$inputName]['tmp_name'] ?? '';
    $mime = $tmp && file_exists($tmp) ? @mime_content_type($tmp) : '';

    if (!$tmp || !in_array($mime, $allowed, true)) {
        return ['path' => null, 'error' => 'Invalid file type. Allowed: JPG, PNG, GIF, PDF.'];
    }

    $ext = strtolower(pathinfo((string) $_FILES[$inputName]['name'], PATHINFO_EXTENSION));
    $safeName = 'report_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $uploadDir = __DIR__ . '/uploads/reports';

    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return ['path' => null, 'error' => 'Failed to create upload directory.'];
    }

    $destination = $uploadDir . '/' . $safeName;
    if (!move_uploaded_file($tmp, $destination)) {
        return ['path' => null, 'error' => 'Failed to upload file.'];
    }

    return ['path' => 'uploads/reports/' . $safeName, 'error' => null];
}

$tableExists = adminReportsTableExists($conn);
$columns = $tableExists ? adminReportColumns($conn) : [];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? '';
    if (!$token || !SecurityMiddleware::verifyCSRFToken($token)) {
        $error = 'Invalid CSRF token.';
    } elseif (!$tableExists) {
        $error = 'The reports table is not available.';
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        $category = trim((string) ($_POST['type'] ?? 'general'));
        $regNumber = trim((string) ($_POST['regNumber'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? 'open'));
        $officer = trim((string) ($_POST['officer'] ?? ($_SESSION['admin_username'] ?? '')));
        $reportDate = trim((string) ($_POST['report_date'] ?? date('Y-m-d')));

        if ($title === '' || $content === '' || $category === '') {
            $error = 'Please fill in the title, type, and description.';
        } else {
            $upload = createReportFile('evidence');
            if ($upload['error']) {
                $error = $upload['error'];
            } else {
                $insertColumns = [];
                $placeholders = [];
                $values = [];
                $types = '';

                $fieldMap = [
                    'title' => $title,
                    'description' => $content,
                    'category' => $category,
                    'report_date' => $reportDate,
                    'file_path' => $upload['path'],
                    'admin_id' => (int) ($_SESSION['admin_id'] ?? 1),
                    'created_at' => date('Y-m-d H:i:s'),
                    'reg_number' => $regNumber,
                    'status' => $status,
                    'officer' => $officer,
                ];

                foreach ($fieldMap as $column => $value) {
                    if (!isset($columns[$column])) {
                        continue;
                    }

                    $insertColumns[] = $column;
                    $placeholders[] = '?';
                    $values[] = $value;
                    $types .= is_int($value) ? 'i' : 's';
                }

                if (empty($insertColumns)) {
                    $error = 'No writable report columns were found.';
                } else {
                    $sql = 'INSERT INTO admin_reports (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        $error = 'Failed to prepare the report insert.';
                    } else {
                        $stmt->bind_param($types, ...$values);
                        if ($stmt->execute()) {
                            $success = 'Report created successfully.';
                            $_POST = [];
                        } else {
                            $error = 'Failed to create report: ' . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

$search = trim((string) ($_GET['search'] ?? ''));
$categoryFilter = trim((string) ($_GET['category'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$reports = [];
$summary = [
    'total' => 0,
    'open' => 0,
    'closed' => 0,
];

if ($tableExists) {
    $where = [];
    $params = [];
    $types = '';

    if ($search !== '') {
        $searchParts = [];
        foreach (['title', 'description', 'reg_number', 'officer'] as $column) {
            if (!isset($columns[$column])) {
                continue;
            }

            $searchParts[] = "{$column} LIKE ?";
            $params[] = '%' . $search . '%';
            $types .= 's';
        }

        if (!empty($searchParts)) {
            $where[] = '(' . implode(' OR ', $searchParts) . ')';
        }
    }

    if ($categoryFilter !== '' && isset($columns['category'])) {
        $where[] = 'category = ?';
        $params[] = $categoryFilter;
        $types .= 's';
    }

    if ($statusFilter !== '' && isset($columns['status'])) {
        $where[] = 'status = ?';
        $params[] = $statusFilter;
        $types .= 's';
    }

    $sql = 'SELECT * FROM admin_reports';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $orderColumn = isset($columns['report_date']) ? 'report_date' : (isset($columns['created_at']) ? 'created_at' : 'id');
    $sql .= " ORDER BY {$orderColumn} DESC, id DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $reports = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    }

    $summary['total'] = count($reports);
    if (isset($columns['status'])) {
        foreach ($reports as $report) {
            $reportStatus = strtolower((string) ($report['status'] ?? ''));
            if ($reportStatus === 'open') {
                $summary['open']++;
            }
            if ($reportStatus === 'closed') {
                $summary['closed']++;
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Reports - Vehicle Registration System</title>
    <?php includeCommonAssets(); ?>
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
                        <h1>Admin Reports</h1>
                        <p>Create, review, and maintain incident and maintenance reports.</p>
                    </div>
                </div>
                <div class="header-actions">
                    <a class="btn btn-secondary" href="admin-dashboard.php"><i class="fa fa-arrow-left"></i> Dashboard</a>
                    <a class="btn btn-secondary" href="logout.php"><i class="fa fa-right-from-bracket"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <nav class="admin-nav">
            <ul>
                <li><a href="admin-dashboard.php">Dashboard</a></li>
                <li><a href="owner-list.php">Owners</a></li>
                <li><a href="vehicle-list.php">Vehicles</a></li>
                <li><a href="admin-users.php">Users</a></li>
                <li><a class="active" href="admin_reports.php">Reports</a></li>
            </ul>
        </nav>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!$tableExists): ?>
            <div class="alert alert-danger">The `admin_reports` table was not found. Run the database migrations before using this page.</div>
        <?php endif; ?>

        <section class="stats-grid">
            <article class="stat-card">
                <div class="stat-label">Visible Reports</div>
                <div class="stat-value"><?= number_format($summary['total']) ?></div>
            </article>
            <article class="stat-card">
                <div class="stat-label">Open Reports</div>
                <div class="stat-value"><?= number_format($summary['open']) ?></div>
            </article>
            <article class="stat-card">
                <div class="stat-label">Closed Reports</div>
                <div class="stat-value"><?= number_format($summary['closed']) ?></div>
            </article>
        </section>

        <section class="grid">
            <article class="card">
                <div class="toolbar" style="margin-bottom:1rem;">
                    <h2>Create Report</h2>
                    <span class="muted">Fields adapt to the current schema.</span>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="grid" style="margin-bottom:1rem;">
                        <div>
                            <label class="muted" for="title">Title</label>
                            <input class="form-input" id="title" name="title" required value="<?= htmlspecialchars((string) ($_POST['title'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="muted" for="type">Type</label>
                            <select class="form-input" id="type" name="type" required>
                                <?php $selectedType = (string) ($_POST['type'] ?? 'incident'); ?>
                                <option value="incident" <?= $selectedType === 'incident' ? 'selected' : '' ?>>Incident</option>
                                <option value="maintenance" <?= $selectedType === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                <option value="general" <?= $selectedType === 'general' ? 'selected' : '' ?>>General</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid" style="margin-bottom:1rem;">
                        <div>
                            <label class="muted" for="regNumber">Registration Number</label>
                            <input class="form-input" id="regNumber" name="regNumber" value="<?= htmlspecialchars((string) ($_POST['regNumber'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="muted" for="status">Status</label>
                            <select class="form-input" id="status" name="status">
                                <?php $selectedStatus = (string) ($_POST['status'] ?? 'open'); ?>
                                <option value="open" <?= $selectedStatus === 'open' ? 'selected' : '' ?>>Open</option>
                                <option value="in_progress" <?= $selectedStatus === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="closed" <?= $selectedStatus === 'closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid" style="margin-bottom:1rem;">
                        <div>
                            <label class="muted" for="officer">Officer</label>
                            <input class="form-input" id="officer" name="officer" value="<?= htmlspecialchars((string) ($_POST['officer'] ?? ($_SESSION['admin_username'] ?? ''))) ?>">
                        </div>
                        <div>
                            <label class="muted" for="report_date">Report Date</label>
                            <input class="form-input" type="date" id="report_date" name="report_date" value="<?= htmlspecialchars((string) ($_POST['report_date'] ?? date('Y-m-d'))) ?>">
                        </div>
                    </div>

                    <div style="margin-bottom:1rem;">
                        <label class="muted" for="content">Description</label>
                        <textarea class="form-input" id="content" name="content" rows="6" required><?= htmlspecialchars((string) ($_POST['content'] ?? '')) ?></textarea>
                    </div>

                    <div style="margin-bottom:1rem;">
                        <label class="muted" for="evidence">Evidence File</label>
                        <input class="form-input" type="file" id="evidence" name="evidence" accept="image/*,application/pdf">
                    </div>

                    <button class="btn btn-primary" type="submit"><i class="fa fa-floppy-disk"></i> Save Report</button>
                </form>
            </article>

            <article class="card">
                <div class="toolbar" style="margin-bottom:1rem;">
                    <h2>Filters</h2>
                    <a href="admin_reports.php">Reset</a>
                </div>
                <form method="GET">
                    <div style="margin-bottom:1rem;">
                        <input class="form-input" type="search" name="search" placeholder="Search title, description, reg number" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="grid" style="margin-bottom:1rem;">
                        <select class="form-input" name="category">
                            <option value="">All categories</option>
                            <option value="incident" <?= $categoryFilter === 'incident' ? 'selected' : '' ?>>Incident</option>
                            <option value="maintenance" <?= $categoryFilter === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            <option value="general" <?= $categoryFilter === 'general' ? 'selected' : '' ?>>General</option>
                        </select>
                        <select class="form-input" name="status">
                            <option value="">All statuses</option>
                            <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                    <button class="btn btn-secondary" type="submit"><i class="fa fa-filter"></i> Apply Filters</button>
                </form>
            </article>
        </section>

        <section class="card">
            <div class="toolbar" style="margin-bottom:1rem;">
                <h2>Reports</h2>
                <span class="muted"><?= number_format(count($reports)) ?> record(s)</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Reg Number</th>
                            <th>Status</th>
                            <th>Officer</th>
                            <th>Date</th>
                            <th>Evidence</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reports)): ?>
                            <tr>
                                <td class="empty-state" colspan="8">No reports matched the current filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reports as $report): ?>
                                <?php
                                $reportId = (int) ($report['id'] ?? 0);
                                $reportDate = $report['report_date'] ?? $report['created_at'] ?? null;
                                $filePath = trim((string) ($report['file_path'] ?? ''));
                                ?>
                                <tr id="report-row-<?= $reportId ?>">
                                    <td>
                                        <strong><?= htmlspecialchars((string) ($report['title'] ?? 'Untitled report')) ?></strong>
                                        <div class="muted" style="margin-top:0.35rem;max-width:380px;">
                                            <?= htmlspecialchars((string) ($report['description'] ?? '')) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars(ucfirst((string) ($report['category'] ?? 'general'))) ?></td>
                                    <td><?= htmlspecialchars((string) ($report['reg_number'] ?? 'N/A')) ?></td>
                                    <td><?= htmlspecialchars((string) ($report['status'] ?? 'N/A')) ?></td>
                                    <td><?= htmlspecialchars((string) ($report['officer'] ?? 'N/A')) ?></td>
                                    <td><?= htmlspecialchars($reportDate ? date('M j, Y', strtotime((string) $reportDate)) : 'N/A') ?></td>
                                    <td>
                                        <?php if ($filePath !== ''): ?>
                                            <a href="<?= htmlspecialchars($filePath) ?>" target="_blank" rel="noopener noreferrer">Open file</a>
                                        <?php else: ?>
                                            <span class="muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a class="btn btn-secondary" href="edit_report.php?id=<?= $reportId ?>"><i class="fa fa-pen"></i> Edit</a>
                                            <button class="btn btn-danger" type="button" onclick="deleteReport(<?= $reportId ?>)"><i class="fa fa-trash"></i> Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        function deleteReport(reportId) {
            if (!confirm('Delete this report? This cannot be undone.')) {
                return;
            }

            const body = new URLSearchParams({
                report_id: String(reportId),
                _token: <?= json_encode($csrfToken) ?>
            });

            fetch('/backend/delete_report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': <?= json_encode($csrfToken) ?>
                },
                body: body.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Delete failed');
                }

                const row = document.getElementById('report-row-' + reportId);
                if (row) {
                    row.remove();
                }
            })
            .catch(error => {
                alert(error.message || 'Failed to delete report.');
            });
        }
    </script>
</body>
</html>

