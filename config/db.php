<?php
// Database connection settings.
// Update these to match your XAMPP/WAMP MySQL setup.
$DB_HOST = 'localhost';
$DB_NAME = 'ot_system';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Don't leak connection details in production; this is fine for local dev.
    http_response_code(500);
    die('Database connection failed. Check config/db.php settings.');
}
