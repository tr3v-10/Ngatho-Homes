<?php
// ============================================
// config.php — Database Connection
// Place this in your propcore/ root folder
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Your XAMPP MySQL username
define('DB_PASS', '');            // Your XAMPP MySQL password (blank by default)
define('DB_NAME', 'propcore');

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// JSON response helper
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data);
    exit;
}

// Start session for auth
session_start();
?>
