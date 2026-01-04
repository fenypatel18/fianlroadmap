<?php
// instructor/students.php

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

// Get enrolled students data
try {
    // Get students enrolled in this instructor's roadmaps with progress
    $stmt = $pdo->prepare("
        SELECT 
            u.id as student_id,
            u.name as student_name,
            u.email as student_email,
            r.id as roadmap_id,
            r.title as roadmap_title,
            e.enrollment_date,
            COUNT(DISTINCT rv.id) as total_videos,
            COUNT(DISTINCT p.video_id) as completed_videos
        FROM users u
        JOIN enrollments e ON u.id = e.student_id
        JOIN roadmaps r ON e.roadmap_id = r.id
        JOIN roadmap_phases rp ON r.id = rp.roadmap_id
        JOIN roadmap_videos rv ON rp.id = rv.phase_id
        LEFT JOIN progress p ON rv.id = p.video_id AND p.student_id = u.id AND p.completed = 1
        WHERE r.instructor_id = ? AND u.role = 'student'
        GROUP BY u.id, r.id, e.enrollment_date
        ORDER BY e.enrollment_date DESC
    ");
    $stmt->execute([$instructor_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate progress percentage for each student
    foreach ($students as &$student) {
        if ($student['total_videos'] > 0) {
            $student['progress_percentage'] = round(($student['completed_videos'] / $student['total_videos']) * 100);
        } else {
            $student['progress_percentage'] = 0;
        }
    }
    
    // Get total students count
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT u.id) 
        FROM users u
        JOIN enrollments e ON u.id = e.student_id
        JOIN roadmaps r ON e.roadmap_id = r.id
        WHERE r.instructor_id = ? AND u.role = 'student'
    ");
    $stmt->execute([$instructor_id]);
    $total_students = $stmt->fetchColumn();
    
    // Get active students (with progress in last 7 days)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT p.student_id) 
        FROM progress p
        JOIN roadmap_videos rv ON p.video_id = rv.id
        JOIN roadmap_phases rp ON rv.phase_id = rp.id
        JOIN roadmaps r ON rp.roadmap_id = r.id
        WHERE r.instructor_id = ? 
        AND p.completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$instructor_id]);
    $active_students = $stmt->fetchColumn();
    
    // Get average progress percentage
    $stmt = $pdo->prepare("
        SELECT AVG(progress_percentage) as avg_progress
        FROM (
            SELECT 
                u.id,
                COUNT(DISTINCT rv.id) as total_videos,
                COUNT(DISTINCT p.video_id) as completed_videos,
                CASE 
                    WHEN COUNT(DISTINCT rv.id) > 0 
                    THEN (COUNT(DISTINCT p.video_id) * 100.0 / COUNT(DISTINCT rv.id))
                    ELSE 0 
                END as progress_percentage
            FROM users u
            JOIN enrollments e ON u.id = e.student_id
            JOIN roadmaps r ON e.roadmap_id = r.id
            JOIN roadmap_phases rp ON r.id = rp.roadmap_id
            JOIN roadmap_videos rv ON rp.id = rv.phase_id
            LEFT JOIN progress p ON rv.id = p.video_id AND p.student_id = u.id AND p.completed = 1
            WHERE r.instructor_id = ? AND u.role = 'student'
            GROUP BY u.id
        ) as student_progress
    ");
    $stmt->execute([$instructor_id]);
    $avg_progress = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Students page error: " . $e->getMessage());
    $students = [];
    $total_students = 0;
    $active_students = 0;
    $avg_progress = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
        <style>
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fade-in 0.3s ease-out;
        }
        
        /* Fixed sidebar styles - EXACT SAME AS create_roadmap.php */
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
        
        .active-link {
            background-color: #eef2ff;
            color: #4f46e5;
            font-weight: 600;
        }
        .logout-hover:hover {
            background-color: #fee2e2 !important;
            color: #dc2626 !important;
        }
        .progress-bar {
            height: 8px;
            background-color: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
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
                <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
                <a href="dashboard.php" 
                   class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg mx-2 mb-1 <?php echo $current_page == 'dashboard.php' ? 'active-link' : ''; ?>">
                    <i class="fas fa-tachometer-alt w-6 text-center"></i>
                    <span class="ml-3">Dashboard</span>
                </a>
                <a href="create_roadmap.php" 
                   class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg mx-2 mb-1 <?php echo $current_page == 'create_roadmap.php' ? 'active-link' : ''; ?>">
                    <i class="fas fa-plus-circle w-6 text-center"></i>
                    <span class="ml-3">Create Roadmap</span>
                </a>
                <a href="my_roadmaps.php" 
                   class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg mx-2 mb-1 <?php echo $current_page == 'my_roadmaps.php' ? 'active-link' : ''; ?>">
                    <i class="fas fa-road w-6 text-center"></i>
                    <span class="ml-3">My Roadmaps</span>
                </a>
                <a href="students.php" 
                   class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg mx-2 mb-1 <?php echo $current_page == 'students.php' ? 'active-link' : ''; ?>">
                    <i class="fas fa-user-graduate w-6 text-center"></i>
                    <span class="ml-3">Students</span>
                </a>
                <a href="feedback.php" 
                   class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg mx-2 mb-1 <?php echo $current_page == 'feedback.php' ? 'active-link' : ''; ?>">
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
        <h1 class="text-4xl font-bold text-gray-800">Students</h1>
        <p class="text-gray-600 mt-2">Students enrolled in your roadmaps and their progress</p>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-8">
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <i class="fas fa-user-graduate text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Students</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $total_students; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-chart-line text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Active Students</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $active_students; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Last 7 days</p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-road text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Enrollments</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo count($students); ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                 <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-lg">
                        <i class="fas fa-percentage text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Avg. Progress</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo round($avg_progress, 1); ?>%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Students Table -->
        <div class="bg-white rounded-xl shadow-sm mt-8 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-medium text-gray-800">Student Progress</h2>
                        <p class="text-sm text-gray-600 mt-1">Students enrolled in your roadmaps and their learning progress</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <input type="text" placeholder="Search students..." class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                            <i class="fas fa-filter mr-2"></i>Filter
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
                                Enrollment Date
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Progress
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <i class="fas fa-user-graduate text-gray-300 text-4xl mb-4"></i>
                                    <h3 class="text-lg font-medium text-gray-900">No students enrolled yet</h3>
                                    <p class="text-gray-600 mt-1">Students will appear here when they enroll in your roadmaps</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-user text-indigo-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['student_email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['roadmap_title']); ?></div>
                                        <div class="text-xs text-gray-500">ID: <?php echo $student['roadmap_id']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars(date('M d, Y', strtotime($student['enrollment_date']))); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-full mr-3">
                                                <div class="flex justify-between text-xs text-gray-500 mb-1">
                                                    <span><?php echo $student['progress_percentage']; ?>% Complete</span>
                                                    <span><?php echo $student['completed_videos']; ?>/<?php echo $student['total_videos']; ?> videos</span>
                                                </div>
                                                <div class="progress-bar">
                                                    <div class="progress-fill 
                                                        <?php 
                                                        if ($student['progress_percentage'] >= 75) echo 'bg-green-500';
                                                        elseif ($student['progress_percentage'] >= 50) echo 'bg-blue-500';
                                                        elseif ($student['progress_percentage'] >= 25) echo 'bg-yellow-500';
                                                        else echo 'bg-red-500';
                                                        ?>"
                                                        style="width: <?php echo $student['progress_percentage']; ?>%">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($student['progress_percentage'] == 0): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                <i class="fas fa-clock mr-1"></i> Not Started
                                            </span>
                                        <?php elseif ($student['progress_percentage'] == 100): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i> Completed
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <i class="fas fa-spinner mr-1"></i> In Progress
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="student_details.php?student_id=<?php echo $student['student_id']; ?>&roadmap_id=<?php echo $student['roadmap_id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-4" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="send_message.php?student_id=<?php echo $student['student_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-4" title="Send Message">
                                            <i class="fas fa-envelope"></i>
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
            
            <!-- Pagination (if needed) -->
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo count($students); ?></span> of <span class="font-medium"><?php echo count($students); ?></span> enrollments
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