<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/middleware/security.php';
SecurityMiddleware::initialize();

// Get the authorization code from Google
$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;
$error = $_GET['error'] ?? null;

if ($error) {
    // Handle OAuth error
    header('Location: ' . BASE_URL . '/login.php?error=oauth_failed&message=' . urlencode($error));
    exit;
}

if (!$code) {
    // No authorization code received
    header('Location: ' . BASE_URL . '/login.php?error=no_code');
    exit;
}

try {
    // Exchange authorization code for access token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $postData = [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => BASE_URL . '/auth/google/callback.php',
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
    
    if ($httpCode !== 200) {
        throw new Exception('Failed to exchange authorization code for token');
    }
    
    $tokenData = json_decode($response, true);
    $accessToken = $tokenData['access_token'] ?? null;
    $idToken = $tokenData['id_token'] ?? null;
    
    if (!$idToken) {
        throw new Exception('No ID token received from Google');
    }
    
    // Get user info from Google
    $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $userInfoResponse = curl_exec($ch);
    curl_close($ch);
    
    $userInfo = json_decode($userInfoResponse, true);
    
    // Verify the ID token (similar to google_auth.php)
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
    
    // Create or find user record (similar to google_auth.php logic)
    $conn = getLegacyDatabaseConnection();
    
    // Check existing user
    $stmt = $conn->prepare("SELECT applicant_id, registrantType, fullName FROM applicants WHERE Email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        // Create new user
        $fullName = $userInfo['name'] ?? '';
        $stmt = $conn->prepare("INSERT INTO applicants (fullName, Email, registrantType, applicationStatus) VALUES (?, ?, 'pending', 'draft')");
        $stmt->bind_param('ss', $fullName, $email);
        $stmt->execute();
        $userId = $conn->insert_id;
        $stmt->close();
        
        // Redirect to role selection for new users
        header('Location: ' . BASE_URL . '/login.php?requires_role_selection=1&temp_user_id=' . $userId);
        exit;
    } else {
        // Existing user - start session
        $userId = $user['applicant_id'];
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $user['fullName'];
        $_SESSION['user_type'] = $user['registrantType'];
        $_SESSION['logged_in'] = true;
        
        // Redirect to dashboard
        header('Location: ' . BASE_URL . '/user-dashboard.php');
        exit;
    }
    
} catch (Exception $e) {
    // Log error and redirect to login with error
    error_log('Google OAuth callback error: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/login.php?error=oauth_failed&message=' . urlencode($e->getMessage()));
    exit;
}
?>
