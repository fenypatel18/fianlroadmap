<?php
require_once __DIR__ . '/../auth/middleware.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

<div class="flex">

    <!-- Sidebar -->
    <div class="w-64 bg-gray-800 text-white min-h-screen p-4">
        <h2 class="text-2xl font-bold mb-10">Admin Panel</h2>
        <nav>
            <a href="/admin/dashboard.php" class="block py-2 px-4 rounded bg-gray-700">Dashboard</a>
            <a href="/admin/roadmaps.php" class="block py-2 px-4 rounded hover:bg-gray-700">Roadmaps</a>
            <a href="/admin/instructors.php" class="block py-2 px-4 rounded hover:bg-gray-700">Instructors</a>
            <a href="/admin/feedback.php" class="block py-2 px-4 rounded hover:bg-gray-700">Feedback</a>
            <a href="/admin/certificates.php" class="block py-2 px-4 rounded hover:bg-gray-700">Certificates</a>
            <a href="/auth/logout.php" class="block py-2 px-4 rounded hover:bg-red-700 mt-10">Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="w-full p-10">
        <h1 class="text-3xl font-bold text-gray-800">Admin Dashboard</h1>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-6">
            <!-- Stat Card 1 -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold text-gray-600">Total Instructors</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2">12</p>
            </div>
            <!-- Stat Card 2 -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold text-gray-600">Total Students</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2">1,204</p>
            </div>
            <!-- Stat Card 3 -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold text-gray-600">Total Roadmaps</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2">48</p>
            </div>
            <!-- Stat Card 4 -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold text-gray-600">Total Revenue</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2">$24,890</p>
            </div>
        </div>
    </div>

</div>

</body>
</html>