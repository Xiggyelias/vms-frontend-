<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();
requireAdmin();



$conn = getLegacyDatabaseConnection();
$message = '';
$error = '';
$csrfToken = SecurityMiddleware::generateCSRFToken();

// Get owner details
if (isset($_GET['id'])) {
    $owner_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $owner = $result->fetch_assoc();

    if (!$owner) {
        header("Location: owner-list.php");
        exit();
    }
} else {
    header("Location: owner-list.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$token || !SecurityMiddleware::verifyCSRFToken($token)) {
        $error = 'Invalid CSRF token.';
    } else {
    $fullName = trim($_POST['fullName']);
    $idNumber = trim($_POST['idNumber']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    // Validate input
    if (empty($fullName) || empty($idNumber) || empty($phone)) {
        $error = "Name, ID Number, and Phone are required fields.";
    } else {
        $stmt = $conn->prepare("UPDATE applicants SET fullName = ?, idNumber = ?, phone = ?, email = ?, address = ? WHERE applicant_id = ?");
        $stmt->bind_param("sssssi", $fullName, $idNumber, $phone, $email, $address, $owner_id);
        
        if ($stmt->execute()) {
            $message = "Owner information updated successfully!";
            // Refresh owner data
            $stmt = $conn->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
            $stmt->bind_param("i", $owner_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $owner = $result->fetch_assoc();
        } else {
            $error = "Error updating owner information: " . $conn->error;
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
    <title>Edit Owner - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="header-logo" style="width: 80px;">
                    <a href="admin-dashboard.php">
                        <img src="assets/images/AULogo.png" alt="AULogo">
                    </a>
                </div>
                <h1>Edit Owner</h1>
                <button onclick="logout()" class="btn btn-logout">Logout</button>
            </div>
        </div>
    </header>

    <div class="container">
        <nav class="admin-nav">
            <ul>
                <li><a href="admin-dashboard.php">Dashboard</a></li>
                <li><a href="owner-list.php">Manage Owners</a></li>
                <li><a href="vehicle-list.php">Manage Vehicles</a></li>
                <li><a href="user-dashboard.php">User View</a></li>
            </ul>
        </nav>

        <div class="form-container">
            <?php if ($message): ?>
                <div class="message success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="form-group">
                    <label for="fullName">Full Name *</label>
                    <input type="text" id="fullName" name="fullName" required
                           value="<?= htmlspecialchars($owner['fullName']) ?>">
                </div>

                <div class="form-group">
                    <label for="idNumber">ID Number *</label>
                    <input type="text" id="idNumber" name="idNumber" required
                           value="<?= htmlspecialchars($owner['idNumber']) ?>">
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" required
                           value="<?= htmlspecialchars($owner['phone']) ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($owner['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address"><?= htmlspecialchars($owner['address'] ?? '') ?></textarea>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">Update Owner</button>
                    <a href="owner-list.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function logout() {
            window.location.href = 'logout.php';
        }
    </script>
</body>
</html> 
