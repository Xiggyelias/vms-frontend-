<?php
require_once __DIR__ . '/includes/init.php';

function getDBConnection() {
    $conn = getLegacyDatabaseConnection();
    if (!$conn) {
        throw new RuntimeException('Database connection failed.');
    }

    return $conn;
}
?>
