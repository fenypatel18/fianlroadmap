<?php
// config/db.php

// --- Database Credentials ---
// Best practice: Use environment variables to store sensitive data.
// Fallback to default values for local development if .env is not used.
$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_name = getenv('DB_NAME') ?: 'skillpath_builder';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_charset = 'utf8mb4';

// --- PDO Data Source Name (DSN) ---
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

// --- PDO Options ---
$options = [
    // Set error mode to throw exceptions for better error handling
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Use associative arrays for fetching results
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Disable emulation of prepared statements for security
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// --- Create PDO Instance ---
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    // In a real application, you would log this error and show a generic
    // error message to the user, but not the detailed exception.
    // For development, it's okay to see the full error.
    http_response_code(500);
    // You can create a simple error page for a better user experience
    // include 'error_pages/500.php';
    exit('Database connection failed: ' . $e->getMessage());
}

// Now, any file that includes `config/db.php` will have access to the `$pdo` object.
// Example: require_once __DIR__ . '/../config/db.php';
?>