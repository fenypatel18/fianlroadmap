<?php
// instructor/dashboard.php
require_once __DIR__ . '/../auth/middleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Full protection for the instructor dashboard
// 1. Must be logged in
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

// 2. Must have the 'instructor' role
if ($_SESSION['role'] !== 'instructor') {
    // If not an instructor, log them out and send to login
    // This prevents a student or admin from being stuck in a redirect loop
    session_destroy();
    redirect('login.php'); 
}

// 3. Must have completed the first-login password change
// We fetch this from the session, which was set at login
if (isset($_SESSION['first_login']) && $_SESSION['first_login']) {
    redirect('change_password.php');
}

// Re-verify `first_login` from DB for added security, in case session is stale.
try {
    require_once __DIR__ . '/../config/db.php';
    $stmt = $pdo->prepare("SELECT first_login FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && $user['first_login']) {
         $_SESSION['first_login'] = true; // Correct the session
         redirect('change_password.php');
    }
} catch (PDOException $e) {
    die("Database connection error. Please try again later.");
}

$instructor_name = $_SESSION['name'] ?? 'Instructor';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 text-white p-5">
            <h2 class="text-2xl font-bold mb-10">Instructor Panel</h2>
            <nav>
                <a href="dashboard.php" class="block py-2.5 px-4 rounded transition duration-200 bg-gray-700">Dashboard</a>
                <a href="#" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">My Roadmaps</a>
                <a href="#" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">Students</a>
                 <a href="../auth/logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-red-700 mt-10">Logout</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-10">
            <h1 class="text-3xl font-bold text-gray-800">Welcome, <?= htmlspecialchars($instructor_name) ?>!</h1>
            <p class="mt-2 text-gray-600">This is your dashboard. More features will be added soon.</p>
        </div>
    </div>
</body>
</html>
