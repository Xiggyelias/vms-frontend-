<?php
// Admin authentication functions
function adminLogin($username, $password) {
    error_log("Admin login attempt - Username: " . $username);
    
    $conn = getLegacyDatabaseConnection();

    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    $admin = null;
    $adminTable = null;
    $statusColumn = null;

    foreach (['admins', 'admin_users'] as $candidateTable) {
        $tableCheck = $conn->query("SHOW TABLES LIKE '{$candidateTable}'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $adminTable = $candidateTable;
            if ($tableCheck instanceof mysqli_result) {
                $tableCheck->close();
            }
            break;
        }

        if ($tableCheck instanceof mysqli_result) {
            $tableCheck->close();
        }
    }

    if ($adminTable !== null) {
        $columns = [];
        if ($cols = $conn->query("SHOW COLUMNS FROM {$adminTable}")) {
            while ($row = $cols->fetch_assoc()) {
                $columns[strtolower($row['Field'])] = true;
            }
            $cols->close();
        }

        $statusColumn = isset($columns['status']) ? 'status' : null;
        $sql = "SELECT * FROM {$adminTable} WHERE username = ?";
        if ($statusColumn !== null) {
            $sql .= " AND {$statusColumn} = 'active'";
        }
        $sql .= " LIMIT 1";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result ? $result->fetch_assoc() : null;
            $stmt->close();
        }
    }

    error_log("Database admin user found: " . ($admin ? 'yes' : 'no'));

    $isProduction = strtolower((string) ($_ENV['APP_ENV'] ?? 'development')) === 'production';
    $allowLegacyPlaintext = function_exists('envAsBool')
        ? envAsBool('ALLOW_LEGACY_PLAINTEXT_PASSWORDS', !$isProduction)
        : in_array(strtolower((string) ($_ENV['ALLOW_LEGACY_PLAINTEXT_PASSWORDS'] ?? ($isProduction ? 'false' : 'true'))), ['1', 'true', 'yes', 'on'], true);

    $passwordMatches = false;
    $matchedLegacyPlaintext = false;
    if ($admin) {
        $storedPassword = (string) ($admin['password'] ?? '');
        $passwordMatches = password_verify($password, $storedPassword);

        if (!$passwordMatches && $allowLegacyPlaintext) {
            $matchedLegacyPlaintext = hash_equals($storedPassword, $password);
            $passwordMatches = $matchedLegacyPlaintext;
        }
    }

    if ($admin && $passwordMatches) {
        // Transparent upgrade: convert legacy plaintext passwords to secure hashes after successful login.
        if ($matchedLegacyPlaintext && $adminTable !== null) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            if ($newHash !== false && isset($admin['id'])) {
                if ($upgradeStmt = $conn->prepare("UPDATE {$adminTable} SET password = ? WHERE id = ? LIMIT 1")) {
                    $adminId = (int) $admin['id'];
                    $upgradeStmt->bind_param('si', $newHash, $adminId);
                    $upgradeStmt->execute();
                    $upgradeStmt->close();
                }
            }
        }

        error_log("Database admin authentication successful");
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) ($admin['id'] ?? $admin['admin_id'] ?? 0);
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['user_type'] = 'admin';

        return ['success' => true];
    }

    $allowDevAdminFallback = strtolower((string) ($_ENV['ALLOW_DEV_ADMIN_LOGIN'] ?? 'false'));
    if (
        (($_ENV['APP_ENV'] ?? 'development') !== 'production') &&
        in_array($allowDevAdminFallback, ['1', 'true', 'yes', 'on'], true) &&
        $username === 'admin' &&
        $password === 'admin123'
    ) {
        error_log("Fallback admin credentials matched");
        session_regenerate_id(true);
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_username'] = 'admin';
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['user_type'] = 'admin';

        return ['success' => true];
    }
    
    error_log("Authentication failed - returning error");
    return ['success' => false, 'message' => 'Invalid username or password'];
}

function isAdmin() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function redirect($url) {
    header("Location: $url");
    exit();
}
?>
