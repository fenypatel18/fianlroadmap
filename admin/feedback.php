<?php
// admin/feedback.php

// --- SETUP & SECURITY ---
session_start();
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
requireAdmin();

// --- DATA FETCHING ---
// Fetch all feedback, joining with users (for student/instructor names) and roadmaps.
$stmt = $pdo->prepare("
    SELECT 
        f.rating, f.comment, f.created_at,
        r.title AS roadmap_title,
        s.name AS student_name,
        i.name AS instructor_name
    FROM feedback f
    JOIN roadmaps r ON f.roadmap_id = r.id
    JOIN users s ON f.student_id = s.id
    JOIN users i ON r.instructor_id = i.id
    ORDER BY f.created_at DESC
");
$stmt->execute();
$all_feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Feedback - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex h-screen bg-gray-200">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 text-white p-4">
            <h2 class="text-2xl font-bold mb-10">Admin Panel</h2>
            <nav>
                <!-- Add admin navigation links here -->
                <a href="/admin/dashboard.php" class="block py-2 px-4 rounded hover:bg-gray-700">Dashboard</a>
                <a href="/admin/roadmaps.php" class="block py-2 px-4 rounded hover:bg-gray-700">Roadmaps</a>
                <a href="/admin/instructors.php" class="block py-2 px-4 rounded hover:bg-gray-700">Instructors</a>
                <a href="/admin/feedback.php" class="block py-2 px-4 rounded bg-gray-700">Feedback</a>
                <a href="/auth/logout.php" class="block py-2 px-4 rounded hover:bg-gray-700 mt-10">Logout</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-10 overflow-y-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">All Student Feedback</h1>
            
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <table class="min-w-full leading-normal">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Student</th>
                            <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Instructor</th>
                            <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Roadmap</th>
                            <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Rating</th>
                            <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Comment</th>
                            <th class="px-5 py-3 border-b-2 border-gray-300 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_feedback)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-10 text-gray-500">No feedback has been submitted on the platform yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_feedback as $feedback): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="px-5 py-5 bg-white text-sm"><?php echo htmlspecialchars($feedback['student_name']); ?></td>
                                    <td class="px-5 py-5 bg-white text-sm"><?php echo htmlspecialchars($feedback['instructor_name']); ?></td>
                                    <td class="px-5 py-5 bg-white text-sm"><?php echo htmlspecialchars($feedback['roadmap_title']); ?></td>
                                    <td class="px-5 py-5 bg-white text-sm">
                                        <span class="text-yellow-500"><?php echo str_repeat('&#9733;', $feedback['rating']); ?></span>
                                    </td>
                                    <td class="px-5 py-5 bg-white text-sm max-w-xs truncate"><?php echo htmlspecialchars($feedback['comment']); ?></td>
                                    <td class="px-5 py-5 bg-white text-sm"><?php echo date('M d, Y', strtotime($feedback['created_at'])); ?></td>
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
