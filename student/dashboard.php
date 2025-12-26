<?php
// student/dashboard.php

// Start the session to access user data.
session_start();

// Include middleware for authentication and authorization.
require_once __DIR__ . '/../auth/middleware.php';

// Protect the page: ensure the user is logged in and has the 'student' role.
requireStudent();

// Include the database connection file for future use (fetching stats).
require_once __DIR__ . '/../config/db.php';

// Get student's name from the session to personalize the dashboard.
$student_name = $_SESSION['user_name'] ?? 'Student';

// --- Placeholder Data --- 
// In a real application, you would fetch this data from the database.
// For example: COUNT from an 'enrollments' table where student_id matches.
$enrolled_count = 0; // Example: SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'in-progress'
$completed_count = 0; // Example: SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'completed'
$certificates_count = 0; // Example: SELECT COUNT(*) FROM certificates WHERE student_id = ?

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - SkillPath Builder</title>
    <!-- Include Tailwind CSS from CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

    <!-- Main Layout -->
    <div class="flex h-screen bg-gray-200">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 text-white p-4 flex flex-col justify-between">
            <div>
                <h2 class="text-2xl font-bold mb-10">Student Panel</h2>
                <nav>
                    <ul>
                        <li class="mb-2">
                            <a href="/student/dashboard.php" class="block py-2 px-4 rounded bg-gray-700">Dashboard</a>
                        </li>
                        <li class="mb-2">
                            <a href="#" class="block py-2 px-4 rounded hover:bg-gray-700">Explore Roadmaps</a>
                        </li>
                        <li class="mb-2">
                            <a href="#" class="block py-2 px-4 rounded hover:bg-gray-700">My Roadmaps</a>
                        </li>
                        <li class="mb-2">
                            <a href="#" class="block py-2 px-4 rounded hover:bg-gray-700">Certificates</a>
                        </li>
                        <li class="mb-2">
                            <a href="#" class="block py-2 px-4 rounded hover:bg-gray-700">Feedback</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <div>
                 <a href="/auth/logout.php" class="block py-2 px-4 rounded bg-red-600 hover:bg-red-700 text-center">Logout</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-10 overflow-y-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Welcome, <?php echo htmlspecialchars($student_name); ?>!</h1>

            <!-- Stats Cards Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                
                <!-- Placeholder Card: Enrolled Roadmaps -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Enrolled Roadmaps</h3>
                    <p class="text-4xl font-bold text-indigo-600"><?php echo $enrolled_count; ?></p>
                </div>

                <!-- Placeholder Card: Completed Roadmaps -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Completed Roadmaps</h3>
                    <p class="text-4xl font-bold text-green-600"><?php echo $completed_count; ?></p>
                </div>

                <!-- Placeholder Card: Certificates Earned -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Certificates Earned</h3>
                    <p class="text-4xl font-bold text-blue-600"><?php echo $certificates_count; ?></p>
                </div>

            </div>
            
            <!-- Future content will go here -->
            <div class="mt-10 bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-2xl font-bold mb-4">Your Learning Path</h2>
                <p class="text-gray-600">Your currently active roadmaps and progress will appear here. For now, feel free to explore the available roadmaps.</p>
            </div>

        </div>
    </div>

</body>
</html>
