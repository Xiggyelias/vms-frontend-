<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();
requireAuth();

// Generate CSRF token
$csrfToken = SecurityMiddleware::generateCSRFToken();



function escapeHTML($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------- Handle Manual Search Form Submission ----------
$vehicle = null;
$authorizedDrivers = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_value'])) {
    $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$token || !SecurityMiddleware::verifyCSRFToken($token)) {
        $error = 'Invalid CSRF token';
    } else {
    $searchValue = trim($_POST['search_value']);

    if (empty($searchValue)) {
        $error = "Please enter a registration number";
    } else {
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
                a.email
            FROM vehicles v
            LEFT JOIN applicants a ON v.applicant_id = a.applicant_id
            WHERE v.regNumber = ?
        ");
        $stmt->bind_param("s", $searchValue);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $vehicle = $result->fetch_assoc();
            // Fetch authorized drivers linked by applicant_id
            $drvStmt = $conn->prepare("
                SELECT fullname, licenseNumber
                FROM authorized_driver
                WHERE applicant_id = ?
                ORDER BY fullname
            ");
            $drvStmt->bind_param("i", $vehicle['applicant_id']);
            $drvStmt->execute();
            $drvRes = $drvStmt->get_result();
            if ($drvRes && $drvRes->num_rows > 0) {
                $authorizedDrivers = $drvRes->fetch_all(MYSQLI_ASSOC);
            }
            $drvStmt->close();
        } else {
            $error = "No vehicle found with the provided registration number";
        }

        $stmt->close();
        $conn->close();
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Search - Manual Entry</title>
    <link rel="stylesheet" href="assets/css/scanner.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<main class="container">
  <header>
    <img src="assets/images/AULogo.png" alt="AU Logo" />
            <h1>Vehicle Search</h1>
            <p>Search vehicle information by registration number</p>
  </header>

    <div class="nav-buttons" style="display:flex; gap:10px;">
        <a href="admin-dashboard.php" class="nav-btn" style="background:var(--gray-500); border-color:var(--gray-500); color:white; padding:0.5rem 1rem; border-radius:6px; text-decoration:none;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <a href="search-vehicle.php" class="nav-btn active" style="padding:0.5rem 1rem; border-radius:6px; text-decoration:none;">Manual Search</a>
    </div>

        <!-- Manual Entry Section -->
            <div class="search-method">
                <h3>Manual Entry</h3>
                <p style="color:#a9a9a9;margin:0 0 .75rem 0;font-size:.9rem">Enter the vehicle registration number below to view details.</p>
                <form method="POST" action="" autocomplete="off">
            <!-- CSRF Token -->
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="search-input">
                    <i class="fa fa-magnifying-glass"></i>
                    <input type="text" name="search_value" placeholder="e.g. ABC 123 or ABC123" 
      value="<?= escapeHTML($_POST['search_value'] ?? '') ?>" />
            </div>
                    <div class="buttons">
    <button type="submit">Search</button>
                        <button type="button" onclick="clearSearch()">Clear</button>
                    </div>
  </form>
        </div>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= escapeHTML($error) ?></div>
  <?php endif; ?>

        <div id="results" style="display: <?= $vehicle ? 'block' : 'none' ?>;">
    <div class="tabs">
      <div class="tab-buttons">
                    <button class="active" data-tab="vehicleTab">🚗 Vehicle Info</button>
                    <button data-tab="ownerTab">👤 Owner Info</button>
                    <button data-tab="driverTab">🧑‍✈️ Authorized Drivers</button>
      </div>

                <?php if ($vehicle): ?>
        <div id="vehicleTab" class="tab-content active">
          <div class="info-group"><div class="info-label">Make</div><div class="info-value"><?= escapeHTML($vehicle['make']) ?></div></div>
          <div class="info-group"><div class="info-label">Reg Number</div><div class="info-value"><?= escapeHTML($vehicle['regNumber']) ?></div></div>
          <div class="info-group"><div class="info-label">Disk Number</div><div class="info-value"><?= escapeHTML($vehicle['disk_number'] ?: 'Not Assigned') ?></div></div>
          <div class="info-group"><div class="info-label">Registration Date</div><div class="info-value"><?= escapeHTML($vehicle['registration_date']) ?></div></div>
        </div>

        <div id="ownerTab" class="tab-content">
          <div class="info-group"><div class="info-label">Owner</div><div class="info-value"><?= escapeHTML($vehicle['owner']) ?></div></div>
          <div class="info-group"><div class="info-label">ID Number</div><div class="info-value"><?= escapeHTML($vehicle['idNumber']) ?></div></div>
          <div class="info-group"><div class="info-label">Phone</div><div class="info-value"><?= escapeHTML($vehicle['phone']) ?></div></div>
          <div class="info-group"><div class="info-label">Email</div><div class="info-value"><?= escapeHTML($vehicle['email']) ?></div></div>
        </div>

        <div id="driverTab" class="tab-content">
          <?php if (!empty($authorizedDrivers)): ?>
            <?php foreach ($authorizedDrivers as $drv): ?>
              <div class="info-group" style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;align-items:center;">
                <div>
                  <div class="info-label">Full Name</div>
                  <div class="info-value"><?= escapeHTML($drv['fullname']) ?></div>
                </div>
                <div>
                  <div class="info-label">License Number</div>
                  <div class="info-value"><?= escapeHTML($drv['licenseNumber']) ?></div>
                </div>
              </div>
              <div style="height:1px;background:#2a2a2a;margin:.25rem 0 .6rem 0;border-radius:1px;"></div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="info-group"><div class="info-label">Authorized Drivers</div><div class="info-value">None</div></div>
          <?php endif; ?>
        </div>
                <?php endif; ?>
            </div>
    </div>
</main>

    <script>
        // Clear button functionality
    function clearSearch() {
                document.querySelector('input[name="search_value"]').value = '';
        document.getElementById('results').style.display = 'none';
                const errorAlert = document.querySelector('.alert-error');
                if (errorAlert) errorAlert.remove();
        }

    // Tab functionality
        document.querySelectorAll('.tab-buttons button').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-buttons button').forEach(btn => btn.classList.remove('active'));
                document.getElementById(button.dataset.tab).classList.add('active');
                button.classList.add('active');
            });
        });
    </script>
</body>
</html>

