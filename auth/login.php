<?php
// auth/login.php

session_start();

// If the user is already logged in, redirect them to their dashboard.
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    header('Location: /' . $_SESSION['role'] . '/dashboard.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/db.php';

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    $error_message = 'Your account is not active. Please contact support.';
                } else {
                    // --- SESSION SETUP ---
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['first_login'] = (bool)$user['first_login'];

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
                            header('Location: /index.php'); // Fallback
                            break;
                    }
                    exit();
                }
            } else {
                $error_message = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            // In a real app, log this error.
            $error_message = 'A database error occurred. Please try again later.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(120deg, #f3e8ff, #eef2ff);
        }
    </style>
</head>
<body class="gradient-bg flex items-center justify-center min-h-screen font-sans">

    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-xl shadow-md m-4">
        <div class="text-center">
            <a href="/index.php" class="text-2xl font-bold text-indigo-600">SkillPath Builder</a>
            <h2 class="mt-2 text-3xl font-bold text-gray-900">Welcome Back</h2>
            <p class="mt-2 text-sm text-gray-600">Sign in to continue your journey.</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <form class="space-y-6" action="/auth/login.php" method="POST">
            <div>
                <label for="email" class="text-sm font-bold text-gray-600 block">Email Address</label>
                <input type="email" id="email" name="email" required
                       class="w-full mt-2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label for="password" class="text-sm font-bold text-gray-600 block">Password</label>
                <input type="password" id="password" name="password" required
                       class="w-full mt-2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <button type="submit"
                        class="w-full py-3 px-4 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all">
                    Sign In
                </button>
            </div>
        </form>

        <div class="text-center text-sm text-gray-600">
            <p>
                Don't have an account?
                <a href="/student/register.php" class="font-semibold text-indigo-600 hover:underline">
                    Register as a Student
                </a>
            </p>
             <p class="mt-4">
                <a href="/index.php" class="text-gray-500 hover:text-indigo-600 transition-colors"><i class="fas fa-arrow-left mr-1"></i> Back to Home</a>
            </p>
        </div>
    </div>

</body>
</html>
