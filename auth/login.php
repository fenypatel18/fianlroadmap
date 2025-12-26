<?php
// auth/login.php
session_start();
require_once __DIR__ . '/../config/db.php';

// If a user is already logged in, redirect them to their dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    header('Location: /' . $_SESSION['role'] . '/dashboard.php');
    exit();
}

// Block direct GET access
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login_selector.php');
    exit();
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$role_expected = $_POST['role'] ?? ''; // e.g., 'student', 'admin'

if (empty($email) || empty($password) || empty($role_expected)) {
    // Redirect back to the specific login page with an error
    header('Location: /' . $role_expected . '/login.php?error=empty');
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $role_expected]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Check if user account is active
        if ($user['status'] !== 'active') {
            header('Location: /' . $role_expected . '/login.php?error=disabled');
            exit();
        }

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Store user data in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];

        // Special case for instructor first login
        if ($user['role'] === 'instructor' && $user['first_login']) {
            header('Location: /instructor/change_password.php');
            exit();
        }

        // --- ROLE-BASED REDIRECTION ---
        switch ($user['role']) {
            case 'admin':
                header('Location: /admin/dashboard.php');
                break;
            case 'instructor':
                header('Location: /instructor/dashboard.php');
                break;
            case 'student':
                header('Location: /student/dashboard.php');
                break;
            default:
                // Fallback, should not happen
                header('Location: /index.php');
                break;
        }
        exit();

    } else {
        // Invalid credentials
        header('Location: /' . $role_expected . '/login.php?error=invalid');
        exit();
    }

} catch (PDOException $e) {
    // In a real app, log this error instead of showing it.
    // die("Database error: " . $e->getMessage());
    header('Location: /' . $role_expected . '/login.php?error=db');
    exit();
}
?>
