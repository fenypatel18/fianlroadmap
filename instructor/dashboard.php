<?php
require_once __DIR__ . '/../auth/middleware.php';
requireInstructor();

require_once __DIR__ . '/../config/db.php';

$instructor_name = $_SESSION['name'] ?? 'Instructor';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .active-link {
            background-color: #eef2ff; /* indigo-50 */
            color: #4f46e5; /* indigo-600 */
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">

<div class="flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-200 min-h-screen flex flex-col">
        <div class="px-6 py-5 border-b border-gray-200">
            <h1 class="text-xl font-bold text-indigo-600">SkillPath Builder</h1>
            <span class="text-xs text-gray-500">Instructor Panel</span>
        </div>
        <nav class="flex-grow pt-4">
            <a href="/instructor/dashboard.php" class="flex items-center px-6 py-3 text-gray-700 active-link">
                <i class="fas fa-tachometer-alt w-6 text-center"></i>
                <span class="ml-3">Dashboard</span>
            </a>
            <a href="#" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-plus-circle w-6 text-center"></i>
                <span class="ml-3">Create Roadmap</span>
            </a>
            <a href="#" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-road w-6 text-center"></i>
                <span class="ml-3">My Roadmaps</span>
            </a>
            <a href="#" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-user-graduate w-6 text-center"></i>
                <span class="ml-3">Students</span>
            </a>
            <a href="#" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-comment-dots w-6 text-center"></i>
                <span class="ml-3">Feedback</span>
            </a>
        </nav>
        <div class="p-4 border-t border-gray-200">
            <a href="/auth/logout.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-sign-out-alt w-6 text-center"></i>
                <span class="ml-3">Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8">
        <h1 class="text-4xl font-bold text-gray-800">Instructor Dashboard</h1>
        <p class="text-gray-600 mt-2">Welcome back, <?php echo htmlspecialchars($instructor_name); ?>!</p>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-8">
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <i class="fas fa-drafting-compass text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Roadmaps Created</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1">8</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-check-double text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Approved Roadmaps</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1">5</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Students</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1">256</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                 <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-lg">
                        <i class="fas fa-dollar-sign text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Revenue</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1">$4,800</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

</div>

</body>
</html>
