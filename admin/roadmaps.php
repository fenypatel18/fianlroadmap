<?php
require_once __DIR__ . '/../auth/middleware.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

// Fetch all roadmaps with instructor names
$stmt = $pdo->prepare("
    SELECT 
        r.id, 
        r.title, 
        u.name as instructor_name, 
        r.price, 
        r.status, 
        r.created_at 
    FROM roadmaps r
    JOIN users u ON r.instructor_id = u.id
    ORDER BY r.created_at DESC
");
$stmt->execute();
$roadmaps = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Roadmaps - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="flex">
        <!-- Sidebar -->
        <div class="w-1/5 bg-gray-800 text-white h-screen p-4 fixed">
             <h2 class="text-2xl font-bold mb-10">Admin Panel</h2>
            <ul>
                <li><a href="dashboard.php" class="block py-2 px-4 rounded hover:bg-gray-700">Dashboard</a></li>
                <li><a href="instructors.php" class="block py-2 px-4 rounded hover:bg-gray-700">Instructors</a></li>
                <li><a href="roadmaps.php" class="block py-2 px-4 rounded bg-gray-700">Roadmaps</a></li>
            </ul>
             <a href="../auth/logout.php" class="block py-2 px-4 rounded hover:bg-red-700 mt-auto">Logout</a>
        </div>

        <!-- Main Content -->
        <div class="w-4/5 ml-auto p-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">Roadmap Review</h1>
            
            <!-- Roadmaps Table -->
            <div class="bg-white shadow-md rounded-lg overflow-x-auto">
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Roadmap Title</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Instructor</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Price</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Created</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($roadmaps)):
                            echo '<tr><td colspan="6" class="text-center p-4">No roadmaps pending review.</td></tr>';
                         else: 
                            foreach ($roadmaps as $roadmap): ?>
                                <tr>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-900 whitespace-no-wrap"><?= htmlspecialchars($roadmap['title']) ?></p>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-900 whitespace-no-wrap"><?= htmlspecialchars($roadmap['instructor_name']) ?></p>
                                    </td>
                                     <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-900 whitespace-no-wrap">$<?= number_format($roadmap['price'], 2) ?></p>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                         <span class="relative inline-block px-3 py-1 font-semibold leading-tight text-yellow-900">
                                            <span aria-hidden class="absolute inset-0 bg-yellow-200 opacity-50 rounded-full"></span>
                                            <span class="relative"><?= ucfirst(htmlspecialchars($roadmap['status'])) ?></span>
                                        </span>
                                    </td>
                                     <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-900 whitespace-no-wrap"><?= date('M d, Y', strtotime($roadmap['created_at'])) ?></p>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-right">
                                        <a href="roadmap_view.php?id=<?= $roadmap['id'] ?>" class="text-indigo-600 hover:text-indigo-900">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; 
                         endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>