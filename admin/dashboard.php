<?php
// admin/dashboard.php

// Include middleware FIRST
require_once __DIR__ . '/../auth/middleware.php';

// This will check if user is admin (either fixed or database)
requireAdmin();

// Include database connection
require_once __DIR__ . '/../config/db.php';

// Check if it's fixed admin
$is_fixed_admin = isset($_SESSION['is_fixed_admin']) && $_SESSION['is_fixed_admin'] === true;
$admin_name = $_SESSION['name'] ?? 'Admin';

// Get dynamic stats from database
try {
    // Get total active instructors
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'instructor' AND status = 'active'");
    $stmt->execute();
    $total_instructors = $stmt->fetchColumn();
    
    // Get total active students
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'");
    $stmt->execute();
    $total_students = $stmt->fetchColumn();
    
    // Get total approved roadmaps
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE status = 'approved'");
    $stmt->execute();
    $total_roadmaps = $stmt->fetchColumn();
    
    // Get total revenue from payments table (if exists)
    // If payments table doesn't exist or is empty, show 0
    try {
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE status = 'success'");
        $stmt->execute();
        $total_revenue = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $total_revenue = 0; // Payments table might not exist yet
    }
    
    // Get pending roadmaps for review
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE status = 'pending'");
    $stmt->execute();
    $pending_roadmaps = $stmt->fetchColumn();
    
    // Get total certificates issued
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates");
        $stmt->execute();
        $total_certificates = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $total_certificates = 0;
    }
    
    // Get recent feedback count (last 7 days)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $recent_feedback = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    // If database error, log it and set default values
    error_log("Dashboard database error: " . $e->getMessage());
    $total_instructors = 0;
    $total_students = 0;
    $total_roadmaps = 0;
    $total_revenue = 0;
    $pending_roadmaps = 0;
    $total_certificates = 0;
    $recent_feedback = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* Fixed Sidebar Styles */
        .fixed-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 16rem; /* 256px */
            background-color: white;
            border-right: 1px solid #e5e7eb;
            z-index: 50;
            display: flex;
            flex-direction: column;
        }
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding-bottom: 1rem;
        }
        .sidebar-footer {
            flex-shrink: 0;
            border-top: 1px solid #e5e7eb;
            background: white;
        }
        .main-content-with-sidebar {
            margin-left: 16rem;
            min-height: 100vh;
            background-color: #f9fafb;
        }

        /* Active link styling */
        .active-link {
            background-color: #eef2ff;
            color: #4f46e5;
            font-weight: 600;
        }
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Fixed Sidebar -->
<aside class="fixed-sidebar">
    <!-- Sidebar Header -->
    <div class="px-6 py-5 border-b border-gray-200">
        <h1 class="text-xl font-bold text-indigo-600">SkillPath Builder</h1>
        <span class="text-xs text-gray-500">Admin Panel</span>
        <?php if ($is_fixed_admin): ?>
            <span class="inline-block mt-1 text-xs px-2 py-1 bg-red-100 text-red-800 rounded">Fixed Admin</span>
        <?php endif; ?>
    </div>
    
    <!-- Scrollable Navigation -->
    <div class="sidebar-content">
        <nav class="pt-4">
            <!-- Dashboard -->
            <a href="dashboard.php" class="flex items-center px-6 py-3 text-gray-700 active-link">
                <i class="fas fa-tachometer-alt w-6 text-center"></i>
                <span class="ml-3">Dashboard</span>
            </a>
            
            <!-- Roadmaps -->
            <a href="roadmaps.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-road w-6 text-center"></i>
                <span class="ml-3">Roadmaps</span>
                <?php if ($pending_roadmaps > 0): ?>
                    <span class="ml-auto mr-2 bg-yellow-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $pending_roadmaps; ?> pending</span>
                <?php endif; ?>
            </a>
            
            <!-- Instructors -->
            <a href="instructors.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-users-cog w-6 text-center"></i>
                <span class="ml-3">Instructors</span>
            </a>
            
            <!-- Students -->
            <a href="students.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-user-graduate w-6 text-center"></i>
                <span class="ml-3">Students</span>
            </a>
            
            <!-- Feedback -->
            <a href="feedback.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-comments w-6 text-center"></i>
                <span class="ml-3">Feedback</span>
                <?php if ($recent_feedback > 0): ?>
                    <span class="ml-auto mr-2 bg-green-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $recent_feedback; ?> new</span>
                <?php endif; ?>
            </a>
            
            <!-- Certificates -->
            <a href="certificates.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-certificate w-6 text-center"></i>
                <span class="ml-3">Certificates</span>
            </a>
        </nav>
    </div>
    
    <!-- Fixed Footer with Logout -->
    <div class="sidebar-footer p-4">
        <div class="mb-3 px-4 py-2 text-sm text-gray-600 bg-gray-50 rounded">
            <p class="font-medium"><?php echo htmlspecialchars($admin_name); ?></p>
            <p class="text-xs text-gray-500">Administrator</p>
        </div>
        <a href="../auth/logout.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg hover:text-red-600">
            <i class="fas fa-sign-out-alt w-6 text-center"></i>
            <span class="ml-3">Logout</span>
        </a>
    </div>
</aside>

<!-- Main Content Area -->
<div class="main-content-with-sidebar">
    <div class="p-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-4xl font-bold text-gray-800">Admin Dashboard</h1>
                <p class="text-gray-600 mt-2">Global overview of the SkillPath Builder platform.</p>
            </div>
            <div class="text-sm text-gray-500">
                Last updated: <?php echo date('M j, Y g:i A'); ?>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Stat Card: Total Instructors -->
            <div class="stat-card bg-white p-6 rounded-xl shadow-sm border-l-4 border-indigo-500">
                <div class="flex items-center">
                    <div class="p-3 bg-indigo-100 rounded-lg">
                        <i class="fas fa-chalkboard-teacher text-indigo-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Instructors</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $total_instructors; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Active teaching staff</p>
                    </div>
                </div>
            </div>
            
            <!-- Stat Card: Total Students -->
            <div class="stat-card bg-white p-6 rounded-xl shadow-sm border-l-4 border-green-500">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-user-graduate text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Students</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $total_students; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Active learners</p>
                    </div>
                </div>
            </div>
            
            <!-- Stat Card: Total Roadmaps -->
            <div class="stat-card bg-white p-6 rounded-xl shadow-sm border-l-4 border-blue-500">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-road text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Roadmaps</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $total_roadmaps; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Approved courses</p>
                    </div>
                </div>
            </div>
            
            <!-- Stat Card: Total Revenue -->
            <div class="stat-card bg-white p-6 rounded-xl shadow-sm border-l-4 border-yellow-500">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-lg">
                        <i class="fas fa-wallet text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Revenue</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1">$<?php echo number_format($total_revenue, 2); ?></p>
                        <p class="text-xs text-gray-500 mt-1">From all payments</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Additional Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <!-- Pending Roadmaps -->
            <div class="bg-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Pending Roadmaps</h3>
                        <p class="text-3xl font-bold text-yellow-600 mt-2"><?php echo $pending_roadmaps; ?></p>
                        <p class="text-sm text-gray-600 mt-1">Awaiting approval</p>
                    </div>
                    <div class="p-3 bg-yellow-100 rounded-lg">
                        <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                    </div>
                </div>
                <a href="roadmaps.php" class="mt-4 inline-block text-sm font-medium text-yellow-600 hover:text-yellow-700">
                    Review now →
                </a>
            </div>
            
            <!-- Certificates Issued -->
            <div class="bg-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Certificates Issued</h3>
                        <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $total_certificates; ?></p>
                        <p class="text-sm text-gray-600 mt-1">Total awarded</p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-award text-blue-600 text-2xl"></i>
                    </div>
                </div>
                <a href="certificates.php" class="mt-4 inline-block text-sm font-medium text-blue-600 hover:text-blue-700">
                    View all →
                </a>
            </div>
            
            <!-- Recent Feedback -->
            <div class="bg-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Recent Feedback</h3>
                        <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $recent_feedback; ?></p>
                        <p class="text-sm text-gray-600 mt-1">Last 7 days</p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-star text-green-600 text-2xl"></i>
                    </div>
                </div>
                <a href="feedback.php" class="mt-4 inline-block text-sm font-medium text-green-600 hover:text-green-700">
                    Read feedback →
                </a>
            </div>
        </div>
        
        <!-- Recent Activity Section -->
        <div class="bg-white rounded-xl shadow-sm p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Recent Activity</h2>
            
            <div class="space-y-4">
                <?php
                // Get recent activities from various tables
                try {
                    // Get recent roadmaps
                    $stmt = $pdo->prepare("
                        SELECT r.title, r.created_at, u.name as instructor_name, r.status 
                        FROM roadmaps r 
                        JOIN users u ON r.instructor_id = u.id 
                        ORDER BY r.created_at DESC 
                        LIMIT 5
                    ");
                    $stmt->execute();
                    $recent_roadmaps = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get recent feedback
                    $stmt = $pdo->prepare("
                        SELECT f.rating, f.created_at, u.name as student_name, r.title as roadmap_title 
                        FROM feedback f 
                        JOIN users u ON f.student_id = u.id 
                        JOIN roadmaps r ON f.roadmap_id = r.id 
                        ORDER BY f.created_at DESC 
                        LIMIT 5
                    ");
                    $stmt->execute();
                    $recent_feedback_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                } catch (PDOException $e) {
                    $recent_roadmaps = [];
                    $recent_feedback_list = [];
                }
                ?>
                
                <!-- Recent Roadmaps -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">Recently Submitted Roadmaps</h3>
                    <?php if (!empty($recent_roadmaps)): ?>
                        <div class="space-y-3">
                            <?php foreach ($recent_roadmaps as $roadmap): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($roadmap['title']); ?></p>
                                        <p class="text-sm text-gray-600">By <?php echo htmlspecialchars($roadmap['instructor_name']); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full 
                                            <?php echo $roadmap['status'] == 'approved' ? 'bg-green-100 text-green-800' : 
                                                   ($roadmap['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                   'bg-gray-100 text-gray-800'); ?>">
                                            <?php echo ucfirst($roadmap['status']); ?>
                                        </span>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <?php echo date('M j', strtotime($roadmap['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">No recent roadmaps submitted.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Feedback -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">Recent Student Feedback</h3>
                    <?php if (!empty($recent_feedback_list)): ?>
                        <div class="space-y-3">
                            <?php foreach ($recent_feedback_list as $feedback): ?>
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($feedback['student_name']); ?></p>
                                            <p class="text-sm text-gray-600">on <?php echo htmlspecialchars($feedback['roadmap_title']); ?></p>
                                        </div>
                                        <div class="flex items-center">
                                            <span class="text-yellow-500 mr-2">
                                                <?php echo str_repeat('★', $feedback['rating']); ?>
                                            </span>
                                            <span class="text-gray-400">
                                                <?php echo str_repeat('★', 5 - $feedback['rating']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-2">
                                        <?php echo date('M j, g:i A', strtotime($feedback['created_at'])); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">No recent feedback received.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Database Status -->
        <?php if ($is_fixed_admin): ?>
            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <h3 class="font-bold text-yellow-800 flex items-center">
                    <i class="fas fa-database mr-2"></i>
                    Database Status
                </h3>
                <p class="text-yellow-700 text-sm mt-1">
                    All data is fetched dynamically from the database. If you see zeros, it means the corresponding tables are empty.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>