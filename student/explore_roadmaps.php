<?php
// student/explore_roadmaps.php

// Start the session to access user data.
session_start();

// Include middleware for authentication and authorization.
require_once __DIR__ . '/../auth/middleware.php';

// Protect the page: ensure the user is logged in and has the 'student' role.
requireStudent();

// Include the database connection file.
require_once __DIR__ . '/../config/db.php';

// --- Data Fetching Logic ---
// Prepare and execute the SQL query to fetch all 'approved' roadmaps.
// We join with the 'users' table to get the instructor's name for each roadmap.
$stmt = $pdo->prepare("
    SELECT 
        r.id, 
        r.title, 
        r.description, 
        r.price, 
        u.name AS instructor_name
    FROM roadmaps r
    JOIN users u ON r.instructor_id = u.id
    WHERE r.status = 'approved'
    ORDER BY r.created_at DESC
");
$stmt->execute();
$roadmaps = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Roadmaps - SkillPath Builder</title>
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
                            <a href="/student/dashboard.php" class="block py-2 px-4 rounded hover:bg-gray-700">Dashboard</a>
                        </li>
                        <li class="mb-2">
                            <a href="/student/explore_roadmaps.php" class="block py-2 px-4 rounded bg-gray-700">Explore Roadmaps</a>
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
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Explore Available Roadmaps</h1>

            <!-- Grid for Roadmap Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                
                <?php if (empty($roadmaps)): ?>
                    <!-- Display message if no approved roadmaps are found -->
                    <div class="col-span-full text-center py-12 bg-white rounded-lg shadow-md">
                        <h2 class="text-xl font-semibold text-gray-700">No Roadmaps Available Yet</h2>
                        <p class="text-gray-500 mt-2">Please check back later. New learning paths are being added regularly!</p>
                    </div>
                <?php else: ?>
                    <!-- Loop through each roadmap and display it as a card -->
                    <?php foreach ($roadmaps as $roadmap): ?>
                        <div class="bg-white rounded-lg shadow-lg overflow-hidden flex flex-col">
                            <div class="p-6 flex-grow">
                                <h3 class="text-xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($roadmap['title']); ?></h3>
                                <p class="text-gray-600 text-sm mb-4">By <?php echo htmlspecialchars($roadmap['instructor_name']); ?></p>
                                
                                <!-- Truncate long descriptions -->
                                <p class="text-gray-700 flex-grow">
                                    <?php 
                                        $description = htmlspecialchars($roadmap['description']);
                                        echo strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description;
                                    ?>
                                </p>
                            </div>
                            <div class="p-6 bg-gray-50 border-t border-gray-200">
                                <div class="flex justify-between items-center">
                                    <!-- Display the price -->
                                    <p class="text-2xl font-bold text-indigo-600">$<?php echo htmlspecialchars(number_format($roadmap['price'], 2)); ?></p>
                                    
                                    <!-- "View Roadmap" button linking to the roadmap's specific page -->
                                    <a href="student/view_roadmap.php?id=<?php echo $roadmap['id']; ?>" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700">
                                        View Roadmap
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>
    </div>

</body>
</html>
