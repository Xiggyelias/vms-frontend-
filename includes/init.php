<?php
// Load environment variables
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') === false) {
            continue;
        }
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Remove quotes if present
        if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
            $value = substr($value, 1, -1);
        } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
            $value = substr($value, 1, -1);
        }
        
        if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
    return true;
}

if (!function_exists('envAsBool')) {
    function envAsBool(string $key, bool $default = false): bool {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}

// Load environment variables from .env file
loadEnv(__DIR__ . '/../.env');

// Define constants from environment
define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
define('ALLOWED_GOOGLE_DOMAIN', $_ENV['ALLOWED_GOOGLE_DOMAIN'] ?? 'africau.edu');

// Define base URL and app name constants
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$detectedBaseUrl = isset($_SERVER['HTTP_HOST']) ? ($scheme . '://' . $_SERVER['HTTP_HOST']) : 'http://localhost';
define('BASE_URL', $_ENV['BASE_URL'] ?? $detectedBaseUrl);
define('BACKEND_URL', $_ENV['BACKEND_URL'] ?? (BASE_URL . '/backend'));
define('APP_NAME', 'Vehicle Registration System');

// Database connection function
function getLegacyDatabaseConnection() {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';
    $database = $_ENV['DB_DATABASE'] ?? $_ENV['DB_NAME'] ?? 'vehicleregistrationsystem';
    $port = (int) ($_ENV['DB_PORT'] ?? 3306);
    $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

    $hostsToTry = array_values(array_unique(array_filter([$host, '127.0.0.1', 'localhost'])));

    foreach ($hostsToTry as $candidateHost) {
        // Use 'p:' prefix for persistent connections to handle 500-1200 concurrent users efficiently
        $persistentHost = 'p:' . ltrim($candidateHost, 'p:'); // Prevent double p:
        $conn = @new mysqli($persistentHost, $username, $password, $database, $port);
        if (!$conn->connect_error) {
            $conn->set_charset($charset);
            return $conn;
        }

        error_log("Database connection failed for {$persistentHost}: " . $conn->connect_error);
    }

    return null;
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    $sessionName = $_ENV['SESSION_NAME'] ?? 'vehicle_registration_session';
    $lifetime = $_ENV['SESSION_LIFETIME'] ?? 3600;
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $isProd = strtolower((string) ($_ENV['APP_ENV'] ?? 'development')) === 'production';
    $secure = envAsBool('SESSION_SECURE', $isHttps || $isProd);
    $httpOnly = envAsBool('SESSION_HTTP_ONLY', true);
    $sameSite = $_ENV['SESSION_SAMESITE'] ?? 'Lax';
    
    session_name($sessionName);
    ini_set('session.cookie_lifetime', $lifetime);
    ini_set('session.cookie_secure', $secure);
    ini_set('session.cookie_httponly', $httpOnly);
    ini_set('session.cookie_samesite', $sameSite);
    
    session_start();
}

require_once __DIR__ . '/common_functions.php';
require_once __DIR__ . '/admin_auth.php';

if (!function_exists('isDevelopment')) {
    function isDevelopment() {
        $appEnv = strtolower((string) ($_ENV['APP_ENV'] ?? 'development'));
        $displayErrors = strtolower((string) ($_ENV['DISPLAY_ERRORS'] ?? ($appEnv === 'production' ? 'false' : 'true')));

        if ($appEnv === 'production') {
            return in_array($displayErrors, ['1', 'true', 'yes', 'on'], true);
        }

        return true;
    }
}

if (!function_exists('legacyMutationsAllowed')) {
    function legacyMutationsAllowed(): bool
    {
        return envAsBool('ALLOW_LEGACY_MUTATIONS', false);
    }
}

if (!function_exists('rejectLegacyMutationEndpoint')) {
    function rejectLegacyMutationEndpoint(string $endpoint): void
    {
        http_response_code(410);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => 'This legacy mutation endpoint is disabled. Use backend API endpoints.',
            'endpoint' => $endpoint,
        ]);
        exit;
    }
}
?>
