<?php
// instructor/my_roadmaps.php
require_once __DIR__ . '/../auth/middleware.php';
requireInstructor();
require_once __DIR__ . '/../config/db.php';

$instructor_id = $_SESSION['user_id'];

// Fetch approved roadmaps and count phases
$stmt = $pdo->prepare("
    SELECT 
        r.id, 
        r.title, 
        r.price, 
        r.status, 
        r.created_at,
        (SELECT COUNT(*) FROM roadmap_phases WHERE roadmap_id = r.id) as phase_count
    FROM roadmaps r
    WHERE r.instructor_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC
");
$stmt->execute([$instructor_id]);
$roadmaps = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Approved Roadmaps</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
<div class="flex h-screen">
    <!-- Sidebar -->
    <div class="w-64 bg-gray-800 text-white flex flex-col fixed h-full">
        <div class="px-8 py-6 border-b border-gray-700">
            <h2 class="text-2xl font-bold">Instructor Panel</h2>
        </div>
        <nav class="flex-1 px-4 py-4 space-y-2">
            <a href="dashboard.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-md"><span>Dashboard</span></a>
            <a href="create_roadmap.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-md"><span>Create Roadmap</span></a>
            <a href="my_roadmaps.php" class="flex items-center px-4 py-2 text-white bg-gray-700 rounded-md"><span>My Roadmaps</span></a>
            <a href="rejected_roadmaps.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-md"><span>Rejected / Changed Roadmaps</span></a>
            <a href="#" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-md"><span>Students</span></a>
            <a href="#" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-md"><span>Feedback</span></a>
        </nav>
        <div class="px-4 py-4 border-t border-gray-700">
             <a href="../auth/logout.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-red-700 rounded-md"><span>Logout</span></a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 ml-64 p-10 overflow-y-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">My Approved Roadmaps</h1>
        <div class="bg-white p-8 rounded-lg shadow-md">
            <?php if (empty($roadmaps)): ?>
                <p class="text-center text-gray-500">You have no approved roadmaps yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                <th class="py-3 px-6 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Phases</th>
                                <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created Date</th>
                                <th class="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($roadmaps as $roadmap): ?>
                                <tr>
                                    <td class="py-4 px-6 whitespace-nowrap font-medium text-gray-900"><?= htmlspecialchars($roadmap['title']) ?></td>
                                    <td class="py-4 px-6 whitespace-nowrap">$<?= htmlspecialchars(number_format($roadmap['price'], 2)) ?></td>
                                    <td class="py-4 px-6 whitespace-nowrap text-center"><?= htmlspecialchars($roadmap['phase_count']) ?></td>
                                    <td class="py-4 px-6 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <?= htmlspecialchars(ucfirst($roadmap['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 whitespace-nowrap text-gray-500"><?= htmlspecialchars(date('M d, Y', strtotime($roadmap['created_at']))) ?></td>
                                    <td class="py-4 px-6 whitespace-nowrap text-sm font-medium space-x-2">
                                        <a href="#" class="text-indigo-600 hover:text-indigo-900">View Details</a>
                                        <a href="#" class="text-gray-600 hover:text-gray-900">Students</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
