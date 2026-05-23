<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// // Check if user is logged in and is admin
// if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
//     header("Location: user-dashboard.php");
//     exit();
// }

// Check if owner ID is provided
if (!isset($_GET['id'])) {
    header("Location: owner-list.php");
    exit();
}

$owner_id = (int)$_GET['id'];



$conn = getLegacyDatabaseConnection();

// Get owner details
$stmt = $conn->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();

if (!$owner) {
    header("Location: owner-list.php");
    exit();
}

// Get owner's vehicles
$stmt = $conn->prepare("
    SELECT v.*,
           (SELECT COUNT(*) FROM authorized_driver ad WHERE ad.applicant_id = v.applicant_id) AS driver_count
    FROM vehicles v
    WHERE v.applicant_id = ?
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Details - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="header-left">
                <div class="header-logo" style="width: 80px;">
                    <a href="admin-dashboard.php">
                        <img src="assets/images/AULogo.png" alt="AULogo" style="width: 100%; height: auto;">
                    </a>
                </div>
                    <h1>Owner Details</h1>
                </div>
                <div class="header-right">
                    <a href="owner-list.php" class="btn btn-primary" style="text-decoration: none; background-color: var(--white); color: var(--primary-red);">
                        Back to Owner List
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <nav class="admin-nav">
            <ul>
                <li><a href="admin-dashboard.php">Dashboard</a></li>
                <li><a href="owner-list.php" class="active">Manage Owners</a></li>
                <li><a href="vehicle-list.php">Manage Vehicles</a></li>
                <li><a href="manage-disk-numbers.php">Manage Disk Numbers</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="user-dashboard.php">User View</a></li>
            </ul>
        </nav>

        <div class="owner-card">
            <div class="owner-header">
                <div class="owner-avatar">👤</div>
                <div>
                    <h2 style="margin-bottom: 0.5rem;"><?= htmlspecialchars($owner['fullName']) ?></h2>
                    <span style="color: #666;">ID: <?= htmlspecialchars($owner['idNumber']) ?></span>
                </div>
            </div>

            <div class="owner-info">
                <div class="info-item">
                    <div class="info-label">Phone Number</div>
                    <div class="info-value"><?= htmlspecialchars($owner['phone']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">College</div>
                    <div class="info-value"><?= htmlspecialchars($owner['college']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?= htmlspecialchars($owner['email'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Registration Date</div>
                    <div class="info-value"><?= htmlspecialchars($owner['registration_date'] ?? 'N/A') ?></div>
                </div>
            </div>
        </div>

        <h2 style="color: var(--primary-red); margin-bottom: 1.5rem;">Registered Vehicles</h2>
        
        <?php foreach ($vehicles as $vehicle): ?>
            <div class="vehicle-card">
                <div class="vehicle-header">
                    <div>
                        <h3 style="margin-bottom: 0.5rem;"><?= htmlspecialchars($vehicle['make']) ?></h3>
                        <div style="color: #666;">Registration: <?= htmlspecialchars($vehicle['regNumber']) ?></div>
                    </div>
                    <span class="vehicle-status status-active">Active</span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <div class="info-label">Registration Date</div>
                        <div class="info-value"><?= htmlspecialchars($vehicle['registration_date']) ?></div>
                    </div>
                    <div>
                        <div class="info-label">Authorized Drivers</div>
                        <div class="info-value driver-count"><?= $vehicle['driver_count'] ?> Driver(s)</div>
                    </div>
                </div>
                <div class="action-buttons" style="margin-top: 1rem;">
                    <button class="btn btn-primary btn-icon" 
                            onclick="viewVehicle(<?= $vehicle['vehicle_id'] ?>)">
                        View Details
                    </button>
                    <button class="btn btn-secondary btn-icon" 
                            onclick="editVehicle(<?= $vehicle['vehicle_id'] ?>, '<?= htmlspecialchars($vehicle['make']) ?>', '<?= htmlspecialchars($vehicle['regNumber']) ?>')">
                        Edit
                    </button>
                    <button class="btn btn-danger btn-icon" 
                            onclick="deleteVehicle(<?= $vehicle['vehicle_id'] ?>)">
                        Delete
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Edit Vehicle Modal -->
    <div id="editVehicleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Vehicle</h2>
                <button type="button" class="close" onclick="closeModal('editVehicleModal')">&times;</button>
            </div>
            <div id="vehicleAlert" class="alert"></div>
            <form id="editVehicleForm" onsubmit="return handleVehicleEdit(event)">
                <input type="hidden" id="edit_vehicle_id" name="vehicle_id">
                <div class="form-group">
                    <label for="edit_make">Vehicle Make</label>
                    <input type="text" id="edit_make" name="make" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="edit_regNumber">Registration Number</label>
                    <input type="text" id="edit_regNumber" name="regNumber" class="form-input" required>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editVehicleModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="loading-spinner"></span>
                        <span class="button-text">Update Vehicle</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function viewVehicle(vehicleId) {
            window.location.href = `vehicle-details.php?id=${vehicleId}`;
        }

        function editVehicle(vehicleId, make, regNumber) {
            document.getElementById('edit_vehicle_id').value = vehicleId;
            document.getElementById('edit_make').value = make;
            document.getElementById('edit_regNumber').value = regNumber;
            openModal('editVehicleModal');
        }

        function deleteVehicle(vehicleId) {
            if (confirm('Are you sure you want to delete this vehicle? This action cannot be undone.')) {
                fetch('/backend/delete_vehicle.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': '<?= htmlspecialchars($csrfToken ?? SecurityMiddleware::generateCSRFToken()) ?>'
                    },
                    body: `vehicle_id=${vehicleId}&_token=${encodeURIComponent('<?= htmlspecialchars($csrfToken ?? SecurityMiddleware::generateCSRFToken()) ?>')}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to delete vehicle');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the vehicle');
                });
            }
        }

        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function handleVehicleEdit(event) {
            event.preventDefault();
            const form = event.target;
            const submitButton = form.querySelector('button[type="submit"]');
            const spinner = submitButton.querySelector('.loading-spinner');
            const buttonText = submitButton.querySelector('.button-text');

            // Show loading state
            spinner.style.display = 'inline-block';
            buttonText.textContent = 'Updating...';
            submitButton.disabled = true;

            const formData = new FormData(form);
            
            fetch('/backend/update_vehicle.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': '<?= htmlspecialchars($csrfToken ?? SecurityMiddleware::generateCSRFToken()) ?>'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    const alert = document.getElementById('vehicleAlert');
                    alert.textContent = data.message || 'Failed to update vehicle';
                    alert.className = 'alert alert-error';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const alert = document.getElementById('vehicleAlert');
                alert.textContent = 'An error occurred while updating the vehicle';
                alert.className = 'alert alert-error';
            })
            .finally(() => {
                // Reset button state
                spinner.style.display = 'none';
                buttonText.textContent = 'Update Vehicle';
                submitButton.disabled = false;
            });

            return false;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html> 
