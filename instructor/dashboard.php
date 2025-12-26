<?php
// instructor/dashboard.php
require_once __DIR__ . '/../auth/middleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Full protection for the instructor dashboard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    if(isset($_SESSION['user_id'])) session_destroy();
    redirect('login.php');
}

if (isset($_SESSION['first_login']) && $_SESSION['first_login']) {
    redirect('change_password.php');
}

// Re-verify `first_login` from DB for added security
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
    die("Database connection error.");
}

$instructor_name = $_SESSION['name'] ?? 'Instructor';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

<div class="flex h-screen">
    <!-- Sidebar -->
    <div class="w-64 bg-gray-800 text-white flex flex-col">
        <div class="px-8 py-6 border-b border-gray-700">
            <h2 class="text-2xl font-bold">Instructor Panel</h2>
        </div>
        <nav class="flex-1 px-4 py-4 space-y-2">
            <a href="dashboard.php" class="flex items-center px-4 py-2 text-white bg-gray-700 rounded-md">
                <span>Dashboard</span>
            </a>
            <a href="#" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-md">
                <span>Create Roadmap</span>
            </a>
            <a href="#" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-md">
                <span>My Roadmaps</span>
            </a>
             <a href="#" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-md">
                <span>Rejected / Changed Roadmaps</span>
            </a>
            <a href="#" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-md">
                <span>Students</span>
            </a>
            <a href="#" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-md">
                <span>Feedback</span>
            </a>
        </nav>
        <div class="px-4 py-4 border-t border-gray-700">
             <a href="../auth/logout.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-red-700 rounded-md">
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 p-10 overflow-y-auto">
        <h1 class="text-3xl font-bold text-gray-800">Instructor Dashboard</h1>
        <p class="mt-2 text-gray-600">Welcome back, <?= htmlspecialchars($instructor_name) ?>!</p>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-8">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold text-gray-600">Total Roadmaps Created</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2">8</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold text-gray-600">Approved Roadmaps</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2">5</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold text-gray-600">Total Students Enrolled</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2">256</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold text-gray-600">Total Revenue</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2">$4,800</p>
            </div>
        </div>
    </div>
</div>

</body>
</html>
