<?php
// auth/register_student.php

// Start session and include config FIRST
session_start();
require_once __DIR__ . '/../config/config.php';

// Define url() function if not exists
if (!function_exists('url')) {
    function url($path = '') {
        $path = ltrim($path, '/');
        $url = BASE_URL;
        if (!empty($path)) {
            $url .= '/' . $path;
        }
        return $url;
    }
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// Check if it's JSON or FormData
$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents("php://input"), true);
} else {
    $data = $_POST;
}

// Debug: Log what we receive
error_log("Registration data received: " . print_r($data, true));

$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

// Validation
if (empty($name)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Name is required']);
    exit();
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'A valid email is required']);
    exit();
}

if (empty($password) || strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters.']);
    exit();
}

// Include database
require_once __DIR__ . '/../config/db.php';

try {
    // Check for duplicate email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Email already in use.']);
        exit();
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert new student
    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password, role, status, first_login) VALUES (?, ?, ?, 'student', 'active', 0)"
    );
    $stmt->execute([$name, $email, $hashedPassword]);

    // Get the new user ID
    $user_id = $pdo->lastInsertId();

    // Auto-login after registration
    $_SESSION['user_id'] = $user_id;
    $_SESSION['name'] = $name;
    $_SESSION['role'] = 'student';
    $_SESSION['first_login'] = 0;

    http_response_code(201);
    echo json_encode([
        'status' => 'success',
        'message' => 'Student registered successfully',
        'user_id' => $user_id,
        'redirect' => url('student/dashboard.php') // Use relative path
    ]);

} catch (PDOException $e) {
    error_log("Database error in registration: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>