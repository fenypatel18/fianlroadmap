<?php
session_start();

$explore_link = isset($_SESSION['user_id']) ? '/student/explore_roadmaps.php' : '/auth/login.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillPath Builder - Master Your Learning Journey</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(120deg, #f3e8ff, #eef2ff);
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">

    <!-- Header -->
    <header class="bg-white/80 backdrop-blur-sm sticky top-0 z-50 border-b border-gray-200">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <a href="/index.php" class="text-xl font-bold text-indigo-600">SkillPath Builder</a>
            <nav class="hidden md:flex items-center space-x-6">
                <a href="<?php echo $explore_link; ?>" class="text-gray-600 hover:text-indigo-600 transition-colors">Explore Roadmaps</a>
                 <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/<?php echo $_SESSION['role']; ?>/dashboard.php" class="px-5 py-2.5 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-all">Dashboard</a>
                <?php else: ?>
                    <a href="/auth/login.php" class="text-gray-600 hover:text-indigo-600 transition-colors">Login</a>
                    <a href="/student/register.php" class="px-5 py-2.5 bg-indigo-600 text-white font-semibold rounded-lg shadow-sm hover:bg-indigo-700 transition-all">Register</a>
                <?php endif; ?>
            </nav>
            <button class="md:hidden flex items-center">
                 <i class="fas fa-bars text-gray-600"></i>
            </button>
        </div>
    </header>

    <!-- Hero Section -->
    <main>
        <section class="relative gradient-bg py-24 md:py-32">
             <div class="container mx-auto px-6 text-center">
                <h1 class="text-4xl md:text-6xl font-extrabold text-gray-900 leading-tight">
                    Craft Your Future, One Skill at a Time.
                </h1>
                <p class="mt-6 text-lg text-gray-700 max-w-3xl mx-auto">
                    Navigate your learning journey with our community-driven roadmaps. From software development to design, find your path to mastery.
                </p>
                <div class="mt-10">
                    <a href="<?php echo $explore_link; ?>" class="px-8 py-4 bg-indigo-600 text-white text-lg font-bold rounded-lg shadow-lg hover:bg-indigo-700 transform hover:scale-105 transition-all duration-300">
                        Explore Roadmaps <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>
        </section>

        <!-- Roadmap Previews -->
        <section class="bg-gray-50 py-20">
            <div class="container mx-auto px-6">
                <h2 class="text-3xl font-bold text-center text-gray-800 mb-12">Popular Roadmaps</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Example Card 1 -->
                    <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300 overflow-hidden group">
                        <div class="p-6">
                             <div class="flex items-center mb-4">
                                <div class="p-3 bg-red-100 rounded-lg">
                                    <i class="fab fa-laravel text-red-600 text-2xl"></i>
                                </div>
                                <h3 class="ml-4 text-xl font-bold text-gray-800">Laravel for Beginners</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Master the fundamentals of the most popular PHP framework.</p>
                            <a href="#" class="font-semibold text-indigo-600 group-hover:underline">View Path <i class="fas fa-arrow-right ml-1"></i></a>
                        </div>
                    </div>
                    <!-- Example Card 2 -->
                    <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300 overflow-hidden group">
                        <div class="p-6">
                             <div class="flex items-center mb-4">
                                <div class="p-3 bg-blue-100 rounded-lg">
                                    <i class="fab fa-react text-blue-600 text-2xl"></i>
                                </div>
                                <h3 class="ml-4 text-xl font-bold text-gray-800">React State Management</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Go beyond useState and learn advanced state management techniques.</p>
                            <a href="#" class="font-semibold text-indigo-600 group-hover:underline">View Path <i class="fas fa-arrow-right ml-1"></i></a>
                        </div>
                    </div>
                    <!-- Example Card 3 -->
                    <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300 overflow-hidden group">
                        <div class="p-6">
                             <div class="flex items-center mb-4">
                                <div class="p-3 bg-green-100 rounded-lg">
                                    <i class="fab fa-node-js text-green-600 text-2xl"></i>
                                </div>
                                <h3 class="ml-4 text-xl font-bold text-gray-800">Node.js API Development</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Build scalable and secure RESTful APIs with Node.js and Express.</p>
                            <a href="#" class="font-semibold text-indigo-600 group-hover:underline">View Path <i class="fas fa-arrow-right ml-1"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200">
        <div class="container mx-auto px-6 py-8 text-center text-gray-600">
            <p>&copy; <?php echo date('Y'); ?> SkillPath Builder. All Rights Reserved.</p>
        </div>
    </footer>

</body>
</html>
