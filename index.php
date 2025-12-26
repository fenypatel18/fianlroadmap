<?php
session_start();

// Determine the correct link for 'Explore Roadmaps'
$explore_link = isset($_SESSION['user_id']) ? '/student/explore_roadmaps.php' : '/student/login.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">

    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-indigo-600">SkillPath Builder</h1>
            <nav class="space-x-4">
                <a href="<?php echo $explore_link; ?>" class="text-gray-600 hover:text-indigo-600">Explore Roadmaps</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/<?php echo $_SESSION['role']; ?>/dashboard.php" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Dashboard</a>
                    <a href="/auth/logout.php" class="text-gray-600 hover:text-indigo-600">Logout</a>
                <?php else: ?>
                    <a href="/auth/login_selector.php" class="text-gray-600 hover:text-indigo-600">Login</a>
                    <a href="/student/register.php" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <main class="container mx-auto px-6 py-24 text-center">
        <h2 class="text-4xl md:text-5xl font-extrabold text-gray-900 leading-tight">
            Chart Your Course to Success.
        </h2>
        <p class="mt-4 text-lg text-gray-600 max-w-2xl mx-auto">
            SkillPath Builder offers expertly crafted learning roadmaps to guide you from beginner to master in any field. Choose your path and start learning today.
        </p>
        <div class="mt-8">
            <a href="<?php echo $explore_link; ?>" class="px-8 py-4 bg-indigo-600 text-white text-xl font-bold rounded-lg shadow-lg hover:bg-indigo-700 transform hover:scale-105 transition duration-300">
                Explore All Roadmaps
            </a>
        </div>
    </main>

</body>
</html>
