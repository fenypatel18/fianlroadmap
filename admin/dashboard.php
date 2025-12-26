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
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

<div class="flex md:flex-row-reverse flex-wrap">

    <!-- Main Content -->
    <div class="w-full md:w-4/5 bg-gray-100">
        <div class="container bg-gray-100 pt-8 px-6">
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

    <!-- Sidebar -->
    <div class="w-full md:w-1/5 bg-gray-900 md:bg-gray-800 px-2 text-center fixed bottom-0 md:pt-8 md:top-0 md:left-0 h-16 md:h-screen md:border-r-4 md:border-gray-600">
         <div class="md:relative mx-auto lg:float-right lg:px-6">
            <ul class="list-reset flex flex-row md:flex-col text-center md:text-left">
                <li class="mr-3 flex-1">
                    <a href="#" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-blue-600">
                        <i class="fas fa-chart-area pr-0 md:pr-3 text-blue-600"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-white md:font-bold block md:inline-block">Dashboard</span>
                    </a>
                </li>
                <li class="mr-3 flex-1">
                    <a href="#" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-pink-500">
                        <i class="fas fa-road pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Roadmaps</span>
                    </a>
                </li>
                 <li class="mr-3 flex-1">
                    <a href="#" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-purple-500">
                        <i class="fas fa-users-cog pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Instructors</span>
                    </a>
                </li>
                <li class="mr-3 flex-1">
                    <a href="#" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-green-500">
                        <i class="fas fa-user-graduate pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Students</span>
                    </a>
                </li>
                 <li class="mr-3 flex-1">
                    <a href="#" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-yellow-500">
                        <i class="fas fa-wallet pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Payments</span>
                    </a>
                </li>
                 <li class="mr-3 flex-1">
                    <a href="#" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-red-500">
                        <i class="fas fa-comments pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Feedback</span>
                    </a>
                </li>
                 <li class="mr-3 flex-1">
                    <a href="#" id="logout-btn" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-gray-500">
                        <i class="fas fa-sign-out-alt pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<script>
    document.getElementById('logout-btn').addEventListener('click', async function(e) {
        e.preventDefault();
        const response = await fetch('../auth/logout.php');
        if(response.ok) {
            window.location.href = 'login.php';
        }
    });
</script>

</body>
</html>