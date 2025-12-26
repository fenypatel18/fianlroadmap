<?php
// student/view_roadmap.php

// Start the session to manage user state.
session_start();

// --- SETUP & SECURITY ---
// Include middleware for authentication and authorization.
require_once __DIR__ . '/../auth/middleware.php';
// Protect the page: ensure the user is logged in and has the 'student' role.
requireStudent();
// Include the database connection file.
require_once __DIR__ . '/../config/db.php';

// --- DATA VALIDATION ---
// Check if roadmap ID is provided in the URL, if not, redirect.
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: explore_roadmaps.php');
    exit();
}
$roadmap_id = $_GET['id'];

// --- DATA FETCHING: ROADMAP ---
// Fetch the main roadmap details. It's crucial to also check for 'approved' status.
// This prevents students from viewing pending, rejected, or changed roadmaps.
$stmt = $pdo->prepare("
    SELECT 
        r.title, 
        r.description, 
        r.price, 
        u.name AS instructor_name
    FROM roadmaps r
    JOIN users u ON r.instructor_id = u.id
    WHERE r.id = ? AND r.status = 'approved'
");
$stmt->execute([$roadmap_id]);
$roadmap = $stmt->fetch(PDO::FETCH_ASSOC);

// If no roadmap is found (or it's not approved), display an error and exit.
if (!$roadmap) {
    // A simple error page, could be replaced with a more user-friendly 404 page.
    die("Roadmap not found or not available.");
}

// --- DATA FETCHING: PHASES ---
// Fetch all phases associated with this roadmap, ordered correctly.
$phases_stmt = $pdo->prepare("SELECT title, phase_order FROM roadmap_phases WHERE roadmap_id = ? ORDER BY phase_order ASC");
$phases_stmt->execute([$roadmap_id]);
$phases = $phases_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($roadmap['title']); ?> - SkillPath Builder</title>
    <!-- Include Tailwind CSS from CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Include Heroicons for icons like the lock -->
    <link href="https://cdn.jsdelivr.net/npm/heroicons@1.0.6/dist/outline.min.css" rel="stylesheet">
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
                        <li class="mb-2"><a href="/student/dashboard.php" class="block py-2 px-4 rounded hover:bg-gray-700">Dashboard</a></li>
                        <li class="mb-2"><a href="/student/explore_roadmaps.php" class="block py-2 px-4 rounded hover:bg-gray-700">Explore Roadmaps</a></li>
                        <li class="mb-2"><a href="#" class="block py-2 px-4 rounded hover:bg-gray-700">My Roadmaps</a></li>
                        <li class="mb-2"><a href="#" class="block py-2 px-4 rounded hover:bg-gray-700">Certificates</a></li>
                        <li class="mb-2"><a href="#" class="block py-2 px-4 rounded hover:bg-gray-700">Feedback</a></li>
                    </ul>
                </nav>
            </div>
            <div>
                 <a href="/auth/logout.php" class="block py-2 px-4 rounded bg-red-600 hover:bg-red-700 text-center">Logout</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-10 overflow-y-auto">
            <!-- Roadmap Header -->
            <div class="bg-white shadow-md rounded-lg p-8 mb-8">
                <h1 class="text-4xl font-extrabold text-gray-900 mb-2"><?php echo htmlspecialchars($roadmap['title']); ?></h1>
                <p class="text-md text-gray-600 mb-4">Created by <span class="font-semibold"><?php echo htmlspecialchars($roadmap['instructor_name']); ?></span></p>
                <p class="text-gray-700 mb-6"><?php echo nl2br(htmlspecialchars($roadmap['description'])); ?></p>
                
                <!-- Call to Action / Price -->
                <div class="flex items-center justify-between bg-indigo-50 p-4 rounded-lg">
                    <p class="text-3xl font-bold text-indigo-800">$<?php echo htmlspecialchars(number_format($roadmap['price'], 2)); ?></p>
                    <button class="px-8 py-3 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 cursor-not-allowed" disabled>
                        Enroll Now (Coming Soon)
                    </button>
                </div>
            </div>

            <!-- Phase List -->
            <div class="space-y-4">
                <h2 class="text-2xl font-bold text-gray-800">Roadmap Curriculum</h2>
                <?php foreach ($phases as $phase): ?>
                    <?php
                    // --- PHASE LOCK LOGIC ---
                    // The first two phases (order 1 and 2) are marked as FREE.
                    // All subsequent phases are marked as LOCKED until the user enrolls.
                    $is_free = $phase['phase_order'] <= 2;
                    ?>

                    <?php if ($is_free): ?>
                        <!-- FREE PHASE UI -->
                        <div class="bg-white p-5 rounded-lg shadow flex items-center justify-between border-l-4 border-green-500">
                            <div class="flex items-center">
                                <span class="text-lg font-bold text-gray-400 mr-4">#<?php echo htmlspecialchars($phase['phase_order']); ?></span>
                                <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($phase['title']); ?></h3>
                            </div>
                            <span class="px-3 py-1 text-sm font-semibold text-green-800 bg-green-200 rounded-full">Free</span>
                        </div>
                    <?php else: ?>
                        <!-- LOCKED PHASE UI -->
                        <div class="bg-gray-200 p-5 rounded-lg flex items-center justify-between opacity-70">
                            <div class="flex items-center">
                                <span class="text-lg font-bold text-gray-400 mr-4">#<?php echo htmlspecialchars($phase['phase_order']); ?></span>
                                <h3 class="text-lg font-semibold text-gray-600"><?php echo htmlspecialchars($phase['title']); ?></h3>
                            </div>
                             <!-- Lock Icon from Heroicons -->
                            <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </div>
                    <?php endif; ?>

                <?php endforeach; ?>
            </div>
        </div>
    </div>

</body>
</html>
