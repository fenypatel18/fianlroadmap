<?php
require_once __DIR__ . '/../auth/middleware.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

$roadmap_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$roadmap_id) {
    header('Location: roadmaps.php');
    exit();
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $allowed_statuses = ['approved', 'rejected', 'changed'];

    if (in_array($new_status, $allowed_statuses)) {
        try {
            $stmt = $pdo->prepare("UPDATE roadmaps SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $roadmap_id]);
            header("Location: roadmap_view.php?id={$roadmap_id}");
            exit();
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    }
}

// Fetch roadmap details
$stmt = $pdo->prepare("SELECT r.*, u.name AS instructor_name FROM roadmaps r JOIN users u ON r.instructor_id = u.id WHERE r.id = ?");
$stmt->execute([$roadmap_id]);
$roadmap = $stmt->fetch();

if (!$roadmap) {
    header('Location: roadmaps.php');
    exit();
}

// Fetch phases and video counts
$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.phase_order, COUNT(v.id) as video_count
    FROM roadmap_phases p
    LEFT JOIN roadmap_videos v ON p.id = v.phase_id
    WHERE p.roadmap_id = ?
    GROUP BY p.id
    ORDER BY p.phase_order ASC
");
$stmt->execute([$roadmap_id]);
$phases = $stmt->fetchAll();
$total_phases = count($phases);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Roadmap - <?= htmlspecialchars($roadmap['title']) ?></title>
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
            <a href="roadmaps.php" class="text-indigo-600 hover:text-indigo-900">&larr; Back to All Roadmaps</a>
            
            <div class="bg-white shadow-lg rounded-lg p-8 mt-4">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-4xl font-bold text-gray-800"><?= htmlspecialchars($roadmap['title']) ?></h1>
                        <p class="text-lg text-gray-600">by <?= htmlspecialchars($roadmap['instructor_name']) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-bold text-gray-800">$<?= number_format($roadmap['price'], 2) ?></p>
                        <span class="text-lg font-semibold px-3 py-1 rounded-full bg-yellow-200 text-yellow-800"><?= ucfirst(htmlspecialchars($roadmap['status'])) ?></span>
                    </div>
                </div>

                <div class="mt-6 border-t pt-6">
                    <h3 class="text-xl font-semibold text-gray-700">Description</h3>
                    <p class="text-gray-600 mt-2"><?= nl2br(htmlspecialchars($roadmap['description'])) ?></p>
                </div>
                
                 <!-- Admin Actions -->
                <div class="mt-8 border-t pt-6 flex items-center space-x-4">
                    <h3 class="text-xl font-semibold text-gray-700">Admin Actions:</h3>
                     <form method="POST" class="inline">
                        <input type="hidden" name="status" value="approved">
                        <button type="submit" name="update_status" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">Approve</button>
                    </form>
                    <form method="POST" class="inline">
                        <input type="hidden" name="status" value="rejected">
                        <button type="submit" name="update_status" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded">Reject</button>
                    </form>
                     <form method="POST" class="inline">
                        <input type="hidden" name="status" value="changed">
                        <button type="submit" name="update_status" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded">Send for Edit</button>
                    </form>
                </div>

                <!-- Phases Details -->
                <div class="mt-8 border-t pt-6">
                    <h3 class="text-2xl font-semibold text-gray-700 mb-4">Phases (<?= $total_phases ?>)</h3>
                    <div class="space-y-4">
                        <?php foreach($phases as $index => $phase): ?>
                            <div class="p-4 border rounded-lg flex justify-between items-center <?= ($index < 2) ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200' ?>">
                                <div>
                                    <h4 class="font-bold text-lg text-gray-800">Phase <?= $phase['phase_order'] ?>: <?= htmlspecialchars($phase['title']) ?></h4>
                                    <p class="text-sm text-gray-600"><?= $phase['video_count'] ?> videos</p>
                                </div>
                                 <div class="text-right font-bold">
                                     <?php if ($index < 2): ?>
                                        <span class="text-green-600">FREE</span>
                                     <?php else: ?>
                                        <span class="text-red-600">PAID</span>
                                     <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                         <?php if (empty($phases)): ?>
                            <p class="text-gray-500">No phases have been added to this roadmap yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>