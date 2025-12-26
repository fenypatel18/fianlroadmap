<?php
session_start();
require_once __DIR__ . '/../auth/middleware.php';
requireStudent();

require_once __DIR__ . '/../config/db.php';

$student_name = $_SESSION['name'] ?? 'Student';

$enrolled_count = 0;
$completed_count = 0;
$certificates_count = 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - SkillPath Builder</title>
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
             <span class="text-xs text-gray-500">Student Panel</span>
        </div>
        <nav class="flex-grow pt-4">
            <a href="/student/dashboard.php" class="flex items-center px-6 py-3 text-gray-700 active-link">
                <i class="fas fa-tachometer-alt w-6 text-center"></i>
                <span class="ml-3">Dashboard</span>
            </a>
            <a href="/student/explore_roadmaps.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-search w-6 text-center"></i>
                <span class="ml-3">Explore</span>
            </a>
            <a href="/student/my_roadmaps.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-road w-6 text-center"></i>
                <span class="ml-3">My Roadmaps</span>
            </a>
            <a href="#" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-certificate w-6 text-center"></i>
                <span class="ml-3">Certificates</span>
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
        <h1 class="text-4xl font-bold text-gray-800">Welcome, <?php echo htmlspecialchars($student_name); ?>!</h1>
        <p class="text-gray-600 mt-2">Let's continue your learning journey.</p>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-8">
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-book-open text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Enrolled Roadmaps</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $enrolled_count; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Completed Roadmaps</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $completed_count; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-lg">
                        <i class="fas fa-award text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Certificates Earned</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $certificates_count; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Continue Learning Section -->
        <div class="mt-10 bg-white p-8 rounded-xl shadow-sm">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Start Your Journey</h2>
            <p class="text-gray-600 mb-6">You are not currently enrolled in any roadmaps. Explore the available paths and start learning a new skill today!</p>
            <a href="/student/explore_roadmaps.php" class="px-6 py-3 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-all">
                Explore Roadmaps
            </a>
        </div>
    </main>

</div>

</body>
</html>
