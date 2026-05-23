<?php
/**
 * auth-establish.php — Cross-service session handoff.
 *
 * The backend issues a short-lived HMAC-signed token (60 s TTL) that encodes
 * the authenticated user's claims. This page:
 *   1. Verifies the token against AUTH_SHARED_SECRET.
 *   2. Populates the PHP session (used by frontend page guards).
 *   3. Calls /backend/auth-sync.php via the same-origin proxy so the backend
 *      also gets a Laravel session cookie scoped to the frontend domain — this
 *      is required for subsequent API calls via /backend/*.
 *   4. Redirects to the user dashboard.
 *
 * Token format: base64url(json_payload) . "." . hmac_sha256(payload, secret)
 */

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Already authenticated — nothing to do.
if (isLoggedIn()) {
    header('Location: user-dashboard.php');
    exit;
}

function verifyHmacToken(string $token): ?array
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return null;
    }
    [$payload, $sig] = $parts;
    $secrets = [];
    $explicitSecret = trim((string) ($_ENV['AUTH_SHARED_SECRET'] ?? ''));
    if ($explicitSecret !== '') {
        $secrets[] = $explicitSecret;
    }

    // Fallback parity with backend config/app.php so auth works even when
    // AUTH_SHARED_SECRET was not explicitly injected into frontend runtime env.
    $dbPasswordCandidates = array_filter(array_unique([
        (string) ($_ENV['DB_PASSWORD'] ?? ''),
        (string) ($_ENV['BACKEND_DB_PASSWORD'] ?? ''),
        (string) ($_ENV['MYSQL_PASSWORD'] ?? ''),
    ]), static fn ($v) => $v !== '');

    $dbNameCandidates = array_filter(array_unique([
        (string) ($_ENV['DB_DATABASE'] ?? ''),
        (string) ($_ENV['DB_NAME'] ?? ''),
        (string) ($_ENV['BACKEND_DB_DATABASE'] ?? ''),
        (string) ($_ENV['MYSQL_DATABASE'] ?? ''),
        'vehicleregistrationsystem',
    ]), static fn ($v) => $v !== '');

    foreach ($dbPasswordCandidates as $pwd) {
        foreach ($dbNameCandidates as $dbName) {
            $secrets[] = hash('sha256', $pwd . '|vms-auth|' . $dbName);
        }
    }

    $validSignature = false;
    foreach (array_values(array_unique($secrets)) as $secret) {
        if (hash_equals(hash_hmac('sha256', $payload, $secret), $sig)) {
            $validSignature = true;
            break;
        }
    }

    if (!$validSignature) {
        return null;
    }
    $json = base64_decode(strtr($payload, '-_', '+/'));
    $claims = json_decode($json, true);
    if (!is_array($claims) || ($claims['exp'] ?? 0) < time()) {
        return null;
    }
    return $claims;
}

$rawToken = trim($_GET['token'] ?? '');

if ($rawToken === '') {
    header('Location: login.php?error=google_failed');
    exit;
}

$claims = verifyHmacToken($rawToken);
if ($claims === null) {
    // Token invalid or expired — force re-login.
    header('Location: login.php?error=google_failed');
    exit;
}

// Establish the PHP session.
session_regenerate_id(true);
$_SESSION['logged_in']    = true;
$_SESSION['user_id']      = (int) ($claims['uid'] ?? 0);
$_SESSION['user_email']   = $claims['email'] ?? '';
$_SESSION['user_name']    = $claims['name'] ?? '';
$_SESSION['user_type']    = $claims['type'] ?? '';
$_SESSION['user_college'] = $claims['college'] ?? '';
$_SESSION['login_time']   = time();

// Pass the raw token to JS for the auth-sync call (it's base64url+hex — no XSS risk, but escape anyway).
$jsToken       = json_encode($rawToken);
$backendPath   = json_encode('/backend');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signing in…</title>
    <style>
        body { margin: 0; display: flex; align-items: center; justify-content: center;
               min-height: 100vh; font-family: sans-serif; background: #f8fafc; }
        .msg { text-align: center; color: #374151; }
        .spinner { border: 3px solid #e5e7eb; border-top-color: #d00000;
                   border-radius: 50%; width: 32px; height: 32px;
                   animation: spin .7s linear infinite; margin: 0 auto 1rem; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="msg">
    <div class="spinner"></div>
    <p>Signing you in&hellip;</p>
</div>
<script>
(function () {
    var token       = <?= $jsToken ?>;
    var backendPath = <?= $backendPath ?>;

    // Call auth-sync via the same-origin proxy so the backend sets a Laravel
    // session cookie scoped to the frontend domain (required for API calls).
    fetch(backendPath + '/auth-sync.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ token: token })
    })
    .catch(function () { /* sync failure is non-fatal; PHP session is already set */ })
    .finally(function () {
        window.location.replace('user-dashboard.php');
    });
})();
</script>
</body>
</html>
