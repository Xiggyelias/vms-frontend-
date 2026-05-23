<?php
class SecurityMiddleware {
    
    public static function initialize() {
        // Set security headers
        self::setSecurityHeaders();
        
        // Initialize CSRF protection if needed
        self::initializeCSRF();
    }
    
    private static function setSecurityHeaders() {
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');

        // Allow Google OAuth popup to postMessage back to this page while keeping
        // the page isolated from unrelated cross-origin openers.
        header('Cross-Origin-Opener-Policy: same-origin-allow-popups');

        // Content Security Policy
        $connectSources = [
            "'self'",
            'https://oauth2.googleapis.com',
            'https://accounts.google.com',
            'https://apis.google.com',
        ];

        // Include the backend API origin in connect-src so JS fetch() calls succeed.
        $backendOrigin = defined('BACKEND_URL') ? rtrim(BACKEND_URL, '/') : '';
        if ($backendOrigin !== '' && $backendOrigin !== "'self'") {
            $connectSources[] = $backendOrigin;
        }

        header(
            "Content-Security-Policy: default-src 'self'; " .
            "base-uri 'self'; frame-ancestors 'self'; object-src 'none'; " .
            // frame-src: Google Sign-In renders its button/One-Tap in a sandboxed iframe
            "frame-src https://accounts.google.com; " .
            "script-src 'self' 'unsafe-inline' https://accounts.google.com https://apis.google.com; " .
            // accounts.google.com injects its own stylesheet for the Sign-In button
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://accounts.google.com; " .
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
            "img-src 'self' data: https:; " .
            'connect-src ' . implode(' ', $connectSources) . '; ' .
            "form-action 'self';"
        );

        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // HSTS (only in production)
        if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }
    
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function verifyCSRFToken($token) {
        return self::validateCSRFToken($token);
    }
    
    private static function initializeCSRF() {
        // CSRF token is generated on demand when needed
    }
    
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validatePassword($password) {
        $minLength = $_ENV['PASSWORD_MIN_LENGTH'] ?? 12;
        
        if (strlen($password) < $minLength) {
            return false;
        }
        
        // Require at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) { return false; }
        // Require at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) { return false; }
        // Require at least one digit
        if (!preg_match('/\d/', $password)) { return false; }
        // Require at least one special character
        if (!preg_match('/[^A-Za-z0-9]/', $password)) { return false; }
        
        return true;
    }

    public static function rateLimitStatus(string $bucket, string $identifier, int $maxAttempts, int $windowSeconds): array
    {
        if (!self::rateLimitingEnabled()) {
            return [
                'allowed' => true,
                'remaining' => $maxAttempts,
                'retry_after' => 0,
                'attempts' => 0,
            ];
        }

        $file = self::rateLimitFile($bucket, $identifier);
        $state = self::readRateLimitFile($file, $windowSeconds);
        $remaining = max(0, $maxAttempts - $state['attempts']);
        $retryAfter = max(0, $state['expires_at'] - time());

        return [
            'allowed' => $state['attempts'] < $maxAttempts,
            'remaining' => $remaining,
            'retry_after' => $retryAfter,
            'attempts' => $state['attempts'],
        ];
    }

    public static function registerRateLimitAttempt(string $bucket, string $identifier, int $windowSeconds): array
    {
        if (!self::rateLimitingEnabled()) {
            return [
                'attempts' => 0,
                'expires_at' => time() + $windowSeconds,
            ];
        }

        $file = self::rateLimitFile($bucket, $identifier);
        $state = self::readRateLimitFile($file, $windowSeconds);
        $state['attempts']++;
        if ($state['expires_at'] <= time()) {
            $state['expires_at'] = time() + $windowSeconds;
        }

        self::writeRateLimitFile($file, $state);

        return $state;
    }

    public static function clearRateLimit(string $bucket, string $identifier): void
    {
        if (!self::rateLimitingEnabled()) {
            return;
        }

        $file = self::rateLimitFile($bucket, $identifier);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public static function requestFingerprint(?string $suffix = null): string
    {
        $parts = [
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $suffix ?? '',
        ];

        return implode('|', $parts);
    }

    private static function rateLimitingEnabled(): bool
    {
        $enabled = strtolower((string) ($_ENV['RATE_LIMITING_ENABLED'] ?? 'true'));

        return !in_array($enabled, ['0', 'false', 'off', 'no'], true);
    }

    private static function rateLimitFile(string $bucket, string $identifier): string
    {
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vrs_rate_limits';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir . DIRECTORY_SEPARATOR . hash('sha256', $bucket . '|' . $identifier) . '.json';
    }

    private static function readRateLimitFile(string $file, int $windowSeconds): array
    {
        $default = [
            'attempts' => 0,
            'expires_at' => time() + $windowSeconds,
        ];

        if (!is_file($file)) {
            return $default;
        }

        $decoded = json_decode((string) @file_get_contents($file), true);
        if (!is_array($decoded)) {
            return $default;
        }

        $attempts = (int) ($decoded['attempts'] ?? 0);
        $expiresAt = (int) ($decoded['expires_at'] ?? (time() + $windowSeconds));

        if ($expiresAt <= time()) {
            return $default;
        }

        return [
            'attempts' => max(0, $attempts),
            'expires_at' => $expiresAt,
        ];
    }

    private static function writeRateLimitFile(string $file, array $state): void
    {
        @file_put_contents($file, json_encode($state, JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}
?>
