<?php
// instructor/change_password.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/middleware.php';

// Start session and check permissions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Must be a logged-in instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    redirect('login.php');
}

// Fetch the first_login flag from DB to ensure it is current
try {
    $stmt = $pdo->prepare("SELECT first_login FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || !$user['first_login']) {
        // If not a first login, they shouldn't be here. Go to dashboard.
        redirect('dashboard.php');
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $error = 'Both password fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ?, first_login = 0 WHERE id = ?");
            $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
            
            // Update session and redirect to dashboard
            $_SESSION['first_login'] = false;
            redirect('dashboard.php');

        } catch (PDOException $e) {
            $error = 'Database error: Could not update password.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-center text-gray-900">Create Your New Password</h2>
        <p class="text-center text-gray-600">Welcome! As a new instructor, you must set a permanent password.</p>
        <form class="space-y-6" method="POST">
            <div>
                <label for="password" class="text-sm font-medium text-gray-700">New Password</label>
                <input id="password" name="password" type="password" required
                    class="block w-full px-3 py-2 mt-1 text-gray-900 placeholder-gray-500 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label for="confirm_password" class="text-sm font-medium text-gray-700">Confirm New Password</label>
                <input id="confirm_password" name="confirm_password" type="password" required
                    class="block w-full px-3 py-2 mt-1 text-gray-900 placeholder-gray-500 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <button type="submit"
                    class="w-full px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Set Password and Continue
                </button>
            </div>
        </form>
        <?php if ($error): ?>
            <div class="text-center text-red-500 font-medium"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
