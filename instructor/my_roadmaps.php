<?php
// instructor/my_roadmaps.php

// Start the session to access user data
session_start();

// Include middleware for authentication and authorization
require_once __DIR__ . '/../auth/middleware.php';

// Ensure the user is an instructor and has completed the initial password change
requireInstructor();
if ($_SESSION['first_login'] == 1) {
    // Redirect to change password page if it's their first login
    header('Location: /instructor/change_password.php');
    exit();
}

// Include the database connection file
require_once __DIR__ . '/../config/db.php';

// Get the logged-in instructor's ID from the session
$instructor_id = $_SESSION['user_id'];

// Prepare and execute the SQL query to fetch approved roadmaps for the instructor.
// A subquery is used to count the total number of phases for each roadmap.
$stmt = $pdo->prepare("
    SELECT 
        r.id, 
        r.title, 
        r.price, 
        r.status, 
        r.created_at,
        (SELECT COUNT(*) FROM roadmap_phases WHERE roadmap_id = r.id) as total_phases
    FROM roadmaps r
    WHERE r.instructor_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC
");
$stmt->execute([$instructor_id]);
$roadmaps = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Approved Roadmaps</title>
    <!-- Include Tailwind CSS from CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Instructor Dashboard Layout -->
    <div class="flex h-screen bg-gray-200">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 text-white p-4">
            <h2 class="text-2xl font-bold mb-10">Instructor Panel</h2>
            <ul>
                <li><a href="/instructor/dashboard.php" class="block py-2 px-4 rounded hover:bg-gray-700">Dashboard</a></li>
                <li><a href="/instructor/create_roadmap.php" class="block py-2 px-4 rounded hover:bg-gray-700">Create Roadmap</a></li>
                <li><a href="/instructor/my_roadmaps.php" class="block py-2 px-4 rounded bg-gray-700">My Roadmaps</a></li>
                <li><a href="/instructor/rejected_roadmaps.php" class="block py-2 px-4 rounded hover:bg-gray-700">Rejected Roadmaps</a></li>
                <li><a href="/auth/logout.php" class="block py-2 px-4 rounded hover:bg-red-500">Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-10">
            <h1 class="text-3xl font-bold mb-6">My Approved Roadmaps</h1>

            <div class="bg-white shadow-md rounded-lg overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roadmap Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Phases</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($roadmaps)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">You have no approved roadmaps yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($roadmaps as $roadmap): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($roadmap['title']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">$<?php echo htmlspecialchars(number_format($roadmap['price'], 2)); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($roadmap['total_phases']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <?php echo htmlspecialchars(ucfirst($roadmap['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars(date('M d, Y', strtotime($roadmap['created_at']))); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
