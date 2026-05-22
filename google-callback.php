<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

error_log("Google OAuth callback accessed");

// Get the authorization code from Google
$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;
$error = $_GET['error'] ?? null;

error_log("Callback data - Code: " . substr($code, 0, 10) . "..., Error: " . ($error ?? 'none'));

if ($error) {
    error_log("OAuth Error: " . $error);
    header('Location: login.php?error=oauth_failed&message=' . urlencode($error));
    exit;
}

if (!$code) {
    error_log("No authorization code received");
    header('Location: login.php?error=no_code');
    exit;
}

try {
    error_log("Starting token exchange");
    
    // Exchange authorization code for access token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $postData = [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => BASE_URL . '/google-callback.php',
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("Token exchange response code: " . $httpCode);
    
    if ($httpCode !== 200) {
        throw new Exception('Failed to exchange authorization code for token. HTTP Code: ' . $httpCode);
    }
    
    $tokenData = json_decode($response, true);
    $idToken = $tokenData['id_token'] ?? null;
    
    if (!$idToken) {
        throw new Exception('No ID token received from Google');
    }
    
    error_log("Got ID token, verifying...");
    
    // Verify the ID token
    $verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    $tokenInfo = json_decode(file_get_contents($verifyUrl), true);
    
    if (!is_array($tokenInfo) || ($tokenInfo['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
        throw new Exception('Invalid token audience');
    }
    
    $email = $tokenInfo['email'] ?? '';
    $emailVerified = ($tokenInfo['email_verified'] ?? 'false') === 'true';
    
    if (!$email || !$emailVerified) {
        throw new Exception('Email not verified');
    }
    
    // Restrict to africau.edu
    $allowedDomain = ALLOWED_GOOGLE_DOMAIN;
    if (strtolower(substr(strrchr($email, '@'), 1)) !== strtolower($allowedDomain)) {
        throw new Exception('Only Africa University emails are allowed');
    }
    
    error_log("Email verified: " . $email);
    
    // Create or find user record
    $conn = getLegacyDatabaseConnection();
    
    $stmt = $conn->prepare("SELECT applicant_id, registrantType, fullName FROM applicants WHERE Email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        // Create new user
        $fullName = $tokenInfo['name'] ?? '';
        $stmt = $conn->prepare("INSERT INTO applicants (fullName, Email, registrantType, applicationStatus) VALUES (?, ?, 'pending', 'draft')");
        $stmt->bind_param('ss', $fullName, $email);
        $stmt->execute();
        $userId = $conn->insert_id;
        $stmt->close();
        
        error_log("New user created with ID: " . $userId . ", redirecting to login for role selection");
        header('Location: login.php?requires_role_selection=1&temp_user_id=' . $userId);
        exit;
    } else {
        // Existing user - start session
        $userId = $user['applicant_id'];
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $user['fullName'];
        $_SESSION['user_type'] = $user['registrantType'];
        $_SESSION['logged_in'] = true;
        
        error_log("Existing user found with ID: " . $userId . ", redirecting to dashboard");
        header('Location: user-dashboard.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log('Google OAuth callback error: ' . $e->getMessage());
    header('Location: login.php?error=oauth_failed&message=' . urlencode($e->getMessage()));
    exit;
}
?>
