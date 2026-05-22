<?php
require_once __DIR__ . '/includes/init.php';

$conn = getLegacyDatabaseConnection();
if (!$conn) {
    die("Database connection failed.");
}

$sql = "
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bucket VARCHAR(64) NOT NULL,
    identifier VARCHAR(128) NOT NULL,
    attempts INT DEFAULT 0,
    expires_at INT NOT NULL,
    UNIQUE KEY idx_bucket_identifier (bucket, identifier),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql)) {
    echo "Successfully created api_rate_limits table.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
?>
