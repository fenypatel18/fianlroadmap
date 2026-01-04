<?php
// instructor/feedback.php

require_once __DIR__ . '/../auth/middleware.php';
requireInstructor();

require_once __DIR__ . '/../config/db.php';

$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['name'] ?? 'Instructor';

// Check if this is first login
try {
    $stmt = $pdo->prepare("SELECT first_login FROM users WHERE id = ?");
    $stmt->execute([$instructor_id]);
    $first_login = $stmt->fetchColumn();
    
    if ($first_login) {
        header("Location: change_password.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("First login check error: " . $e->getMessage());
}

// --- DATA FETCHING ---
try {
    // Fetch feedback only for roadmaps created by the currently logged-in instructor.
    $stmt = $pdo->prepare("
        SELECT 
            f.id as feedback_id,
            f.rating, 
            f.comment, 
            f.created_at,
            r.id as roadmap_id,
            r.title AS roadmap_title,
            s.id as student_id,
            s.name AS student_name,
            s.email AS student_email
        FROM feedback f
        JOIN roadmaps r ON f.roadmap_id = r.id
        JOIN users s ON f.student_id = s.id
        WHERE r.instructor_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$instructor_id]);
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get feedback statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_feedback,
            AVG(rating) as avg_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
        FROM feedback f
        JOIN roadmaps r ON f.roadmap_id = r.id
        WHERE r.instructor_id = ?
    ");
    $stmt->execute([$instructor_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent feedback (last 30 days)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as recent_feedback
        FROM feedback f
        JOIN roadmaps r ON f.roadmap_id = r.id
        WHERE r.instructor_id = ? 
        AND f.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$instructor_id]);
    $recent_feedback = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Feedback page error: " . $e->getMessage());
    $feedbacks = [];
    $stats = [
        'total_feedback' => 0,
        'avg_rating' => 0,
        'five_star' => 0,
        'four_star' => 0,
        'three_star' => 0,
        'two_star' => 0,
        'one_star' => 0
    ];
    $recent_feedback = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
    .active-link {
        background-color: #eef2ff;
        color: #4f46e5;
        font-weight: 600;
    }
    .logout-hover:hover {
        background-color: #fee2e2 !important;
        color: #dc2626 !important;
    }
    
    /* Fixed sidebar styles */
    .sidebar-container {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 16rem; /* w-64 */
        z-index: 40;
        background-color: white;
        border-right: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
    }
    
    .main-content {
        margin-left: 16rem; /* Same as sidebar width */
        flex: 1;
        min-height: 100vh;
        background-color: #f9fafb; /* bg-gray-50 */
    }
    
    /* For the body to handle fixed sidebar */
    body {
        min-height: 100vh;
    }
    
    /* Scrollable sidebar content */
    .sidebar-content {
        flex: 1;
        overflow-y: auto;
    }
    
    /* Hide scrollbar for sidebar */
    .sidebar-content::-webkit-scrollbar {
        width: 4px;
    }
    
    .sidebar-content::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    
    .sidebar-content::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }
    
    .sidebar-content::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
</style>
</head>
<body class="bg-gray-50 font-sans">

<div class="flex min-h-screen">

    <!-- Sidebar -->
       <aside class="sidebar-container">
        <div class="px-6 py-5 border-b border-gray-200">
            <h1 class="text-xl font-bold text-indigo-600">SkillPath Builder</h1>
            <span class="text-xs text-gray-500">Instructor Panel</span>
        </div>
        
        <div class="sidebar-content">
            <nav class="pt-4">
                <a href="dashboard.php" 
                   class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg mx-2 mb-1 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active-link' : ''; ?>">
                    <i class="fas fa-tachometer-alt w-6 text-center"></i>
                    <span class="ml-3">Dashboard</span>
                </a>
                <a href="create_roadmap.php" 
                   class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg mx-2 mb-1 <?php echo basename($_SERVER['PHP_SELF']) == 'create_roadmap.php' ? 'active-link' : ''; ?>">
                    <i class="fas fa-plus-circle w-6 text-center"></i>
                    <span class="ml-3">Create Roadmap</span>
                </a>
                <a href="my_roadmaps.php" 
                   class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg mx-2 mb-1 <?php echo basename($_SERVER['PHP_SELF']) == 'my_roadmaps.php' ? 'active-link' : ''; ?>">
                    <i class="fas fa-road w-6 text-center"></i>
                    <span class="ml-3">My Roadmaps</span>
                </a>
                <a href="students.php" 
                   class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg mx-2 mb-1 <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active-link' : ''; ?>">
                    <i class="fas fa-user-graduate w-6 text-center"></i>
                    <span class="ml-3">Students</span>
                </a>
                <a href="feedback.php" 
                   class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg mx-2 mb-1 <?php echo basename($_SERVER['PHP_SELF']) == 'feedback.php' ? 'active-link' : ''; ?>">
                    <i class="fas fa-comment-dots w-6 text-center"></i>
                    <span class="ml-3">Feedback</span>
                </a>
            </nav>
        </div>
        
        <div class="p-4 border-t border-gray-200">
            <a href="/fianlroadmap/auth/logout.php" 
               class="logout-hover flex items-center w-full px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200">
                <i class="fas fa-sign-out-alt w-6 text-center"></i>
                <span class="ml-3 font-medium">Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content p-8">
        <h1 class="text-4xl font-bold text-gray-800">Student Feedback</h1>
        <p class="text-gray-600 mt-2">Feedback and ratings from students on your roadmaps</p>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-8">
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <i class="fas fa-comments text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Feedback</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $stats['total_feedback']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-lg">
                        <i class="fas fa-star text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Average Rating</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo number_format($stats['avg_rating'], 1); ?>/5</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-chart-line text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Recent Feedback</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $recent_feedback; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Last 30 days</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                 <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-road text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Roadmaps with Feedback</h3>
                        <?php
                        $roadmap_ids = array_unique(array_column($feedbacks, 'roadmap_id'));
                        $unique_roadmaps = count($roadmap_ids);
                        ?>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $unique_roadmaps; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rating Distribution -->
        <div class="bg-white rounded-xl shadow-sm mt-8 p-6">
            <h2 class="text-lg font-medium text-gray-800 mb-4">Rating Distribution</h2>
            <div class="space-y-4">
                <?php for ($i = 5; $i >= 1; $i--): 
                    $count = $stats[$i . '_star'] ?? 0;
                    $percentage = $stats['total_feedback'] > 0 ? ($count / $stats['total_feedback']) * 100 : 0;
                ?>
                <div class="flex items-center">
                    <div class="w-16 text-sm font-medium text-gray-600">
                        <?php echo $i; ?> star
                    </div>
                    <div class="flex-1 mx-4">
                        <div class="rating-distribution-bar">
                            <div class="h-full bg-yellow-500" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                    <div class="w-20 text-right text-sm text-gray-600">
                        <?php echo $count; ?> (<?php echo number_format($percentage, 1); ?>%)
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Feedback Table -->
        <div class="bg-white rounded-xl shadow-sm mt-8 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-medium text-gray-800">All Feedback</h2>
                        <p class="text-sm text-gray-600 mt-1">Student reviews and ratings for your roadmaps</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <select class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            <option value="all">All Ratings</option>
                            <option value="5">5 Stars</option>
                            <option value="4">4 Stars</option>
                            <option value="3">3 Stars</option>
                            <option value="2">2 Stars</option>
                            <option value="1">1 Star</option>
                        </select>
                        <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                            <i class="fas fa-download mr-2"></i>Export
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Student
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Roadmap
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Rating
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Comment
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($feedbacks)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <i class="fas fa-comment-slash text-gray-300 text-4xl mb-4"></i>
                                    <h3 class="text-lg font-medium text-gray-900">No feedback yet</h3>
                                    <p class="text-gray-600 mt-1">Students will appear here when they provide feedback on your roadmaps</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($feedbacks as $feedback): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-user text-indigo-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($feedback['student_name']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($feedback['student_email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($feedback['roadmap_title']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="star-rating">
                                            <?php 
                                            $rating = $feedback['rating'];
                                            for ($i = 1; $i <= 5; $i++): 
                                                if ($i <= $rating): ?>
                                                    <i class="fas fa-star text-yellow-500"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star text-gray-300"></i>
                                                <?php endif;
                                            endfor; ?>
                                        </div>
                                        <span class="ml-2 text-sm text-gray-600">(<?php echo $rating; ?>/5)</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 max-w-xs">
                                            <?php if (!empty($feedback['comment'])): ?>
                                                <?php echo nl2br(htmlspecialchars($feedback['comment'])); ?>
                                            <?php else: ?>
                                                <span class="text-gray-400 italic">No comment provided</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars(date('M d, Y', strtotime($feedback['created_at']))); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="reply_feedback.php?feedback_id=<?php echo $feedback['feedback_id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-4" title="Reply">
                                            <i class="fas fa-reply"></i>
                                        </a>
                                        <a href="view_student.php?student_id=<?php echo $feedback['student_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-4" title="View Student">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="#" class="text-gray-600 hover:text-gray-900" title="More Options">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo count($feedbacks); ?></span> of <span class="font-medium"><?php echo count($feedbacks); ?></span> feedback
                    </div>
                    <div class="flex space-x-2">
                        <button class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50" disabled>
                            Previous
                        </button>
                        <button class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

</div>

</body>
</html>