<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <div class="flex md:flex-row-reverse flex-wrap">

        <!-- Main Content -->
        <div class="w-full md:w-4/5 bg-gray-100">
            <div class="container bg-white rounded-lg shadow-lg p-6 mx-4 mt-4">
                <h1 class="text-2xl font-bold mb-6">Admin Dashboard</h1>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Card 1 -->
                    <div class="bg-blue-500 text-white p-6 rounded-lg shadow-lg">
                        <h3 class="text-lg font-bold">Total Instructors</h3>
                        <p class="text-3xl">12</p>
                    </div>
                    <!-- Card 2 -->
                    <div class="bg-green-500 text-white p-6 rounded-lg shadow-lg">
                        <h3 class="text-lg font-bold">Total Students</h3>
                        <p class="text-3xl">150</p>
                    </div>
                    <!-- Card 3 -->
                    <div class="bg-yellow-500 text-white p-6 rounded-lg shadow-lg">
                        <h3 class="text-lg font-bold">Total Roadmaps</h3>
                        <p class="text-3xl">25</p>
                    </div>
                    <!-- Card 4 -->
                    <div class="bg-red-500 text-white p-6 rounded-lg shadow-lg">
                        <h3 class="text-lg font-bold">Total Revenue</h3>
                        <p class="text-3xl">$12,345</p>
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
                            <i class="fas fa-chart-area pr-0 md:pr-3 text-blue-600"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Dashboard</span>
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
                        <a href="#" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-red-500">
                            <i class="fas fa-wallet pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Payments</span>
                        </a>
                    </li>
                     <li class="mr-3 flex-1">
                        <a href="#" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-yellow-500">
                            <i class="fas fa-comment-dots pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Feedback</span>
                        </a>
                    </li>
                     <li class="mr-3 flex-1">
                        <a href="login.php" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-gray-500">
                            <i class="fas fa-sign-out-alt pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>