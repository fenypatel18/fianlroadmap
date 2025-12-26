<?php
// instructor/rejected_roadmaps.php

// Start the session to access user data
session_start();

// Include middleware for authentication and authorization
require_once __DIR__ . '/../auth/middleware.php';

// Ensure the user is an instructor
requireInstructor();

// Include the database connection file
require_once __DIR__ . '/../config/db.php';

// Get the logged-in instructor's ID from the session
$instructor_id = $_SESSION['user_id'];

// Prepare and execute the SQL query to fetch rejected or changed roadmaps
// for the currently logged-in instructor.
$stmt = $pdo->prepare("
    SELECT 
        id, 
        title, 
        status, 
        updated_at
    FROM roadmaps
    WHERE instructor_id = ? AND status IN ('rejected', 'changed')
    ORDER BY updated_at DESC
");
$stmt->execute([$instructor_id]);
$roadmaps = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejected / Changed Roadmaps</title>
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
                <li><a href="/instructor/my_roadmaps.php" class="block py-2 px-4 rounded hover:bg-gray-700">My Roadmaps</a></li>
                <li><a href="/instructor/rejected_roadmaps.php" class="block py-2 px-4 rounded bg-gray-700">Rejected Roadmaps</a></li>
                <li><a href="/auth/logout.php" class="block py-2 px-4 rounded hover:bg-red-500">Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-10">
            <h1 class="text-3xl font-bold mb-6">Rejected / Changed Roadmaps</h1>

            <div class="bg-white shadow-md rounded-lg overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roadmap Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($roadmaps)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">No roadmaps found that require changes.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($roadmaps as $roadmap): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($roadmap['title']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        // Display a colored status badge based on the roadmap status
                                        $status_color = 'bg-gray-100 text-gray-800';
                                        if ($roadmap['status'] == 'rejected') {
                                            $status_color = 'bg-red-100 text-red-800';
                                        } elseif ($roadmap['status'] == 'changed') {
                                            $status_color = 'bg-yellow-100 text-yellow-800';
                                        }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_color; ?>">
                                            <?php echo htmlspecialchars(ucfirst($roadmap['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars(date('M d, Y', strtotime($roadmap['updated_at']))); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <!-- The "Resubmit" button redirects to the create page with a clone_id. -->
                                        <!-- This allows the create page to pre-fill the form with data from the rejected/changed roadmap, -->
                                        <!-- making it easier for the instructor to edit and resubmit for approval. -->
                                        <a href="/instructor/create_roadmap.php?clone_id=<?php echo $roadmap['id']; ?>" class="text-indigo-600 hover:text-indigo-900">Resubmit</a>
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
