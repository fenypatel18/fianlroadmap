<?php
// instructor/dashboard.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../auth/middleware.php';
requireInstructor();

require_once __DIR__ . '/../config/db.php';

$instructor_id = $_SESSION['user_id'] ?? null;
$instructor_name = $_SESSION['name'] ?? 'Instructor';

// Debug: Check instructor ID
if (!$instructor_id) {
    die("Error: Instructor ID not found in session. Please log in again.");
}

// Check if this is first login
try {
    $stmt = $pdo->prepare("SELECT first_login FROM users WHERE id = ?");
    $stmt->execute([$instructor_id]);
    $first_login = $stmt->fetchColumn();
    
    // If first login, redirect to change password page
    if ($first_login) {
        header("Location: change_password.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("First login check error: " . $e->getMessage());
}

// Get dynamic stats for this instructor
try {
    // Get total roadmaps created by this instructor
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE instructor_id = ?");
    $stmt->execute([$instructor_id]);
    $total_roadmaps = $stmt->fetchColumn();
    
    // Get approved roadmaps
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE instructor_id = ? AND status = 'approved'");
    $stmt->execute([$instructor_id]);
    $approved_roadmaps = $stmt->fetchColumn();
    
    // Get pending roadmaps
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE instructor_id = ? AND status = 'pending'");
    $stmt->execute([$instructor_id]);
    $pending_roadmaps = $stmt->fetchColumn();
    
    // Get changed roadmaps
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE instructor_id = ? AND status = 'changed'");
    $stmt->execute([$instructor_id]);
    $changed_roadmaps = $stmt->fetchColumn();
    
    // Get rejected roadmaps
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE instructor_id = ? AND status = 'rejected'");
    $stmt->execute([$instructor_id]);
    $rejected_roadmaps = $stmt->fetchColumn();
    
    // Get total students enrolled in this instructor's roadmaps
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT e.student_id) 
        FROM enrollments e 
        JOIN roadmaps r ON e.roadmap_id = r.id 
        WHERE r.instructor_id = ?
    ");
    $stmt->execute([$instructor_id]);
    $total_students = $stmt->fetchColumn();
    
    // Get total revenue from this instructor's roadmaps
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(p.amount), 0) 
        FROM payments p 
        JOIN roadmaps r ON p.roadmap_id = r.id 
        WHERE r.instructor_id = ?
    ");
    $stmt->execute([$instructor_id]);
    $total_revenue = $stmt->fetchColumn();
    
    // Get recent enrollments (last 30 days)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM enrollments e 
        JOIN roadmaps r ON e.roadmap_id = r.id 
        WHERE r.instructor_id = ? 
        AND e.enrollment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$instructor_id]);
    $recent_enrollments = $stmt->fetchColumn();
    
    // Get average rating
    $stmt = $pdo->prepare("
        SELECT COALESCE(AVG(f.rating), 0) 
        FROM feedback f 
        JOIN roadmaps r ON f.roadmap_id = r.id 
        WHERE r.instructor_id = ?
    ");
    $stmt->execute([$instructor_id]);
    $average_rating = $stmt->fetchColumn();
    
    // Get top performing roadmap - FIXED: Added default values
    $stmt = $pdo->prepare("
        SELECT r.title, COUNT(e.student_id) as student_count
        FROM roadmaps r
        LEFT JOIN enrollments e ON r.id = e.roadmap_id
        WHERE r.instructor_id = ? AND r.status = 'approved'
        GROUP BY r.id
        ORDER BY student_count DESC
        LIMIT 1
    ");
    $stmt->execute([$instructor_id]);
    $top_roadmap = $stmt->fetch();
    
    // Set default values if no top roadmap
    if (!$top_roadmap) {
        $top_roadmap = [
            'title' => 'No approved roadmaps yet',
            'student_count' => 0
        ];
    }
    
    // Get roadmap status data for chart
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM roadmaps 
        WHERE instructor_id = ?
        GROUP BY status
    ");
    $stmt->execute([$instructor_id]);
    $roadmap_status_data = $stmt->fetchAll();
    
    // Get enrollment trend (last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(e.enrollment_date, '%Y-%m') as month,
            COUNT(*) as enrollments
        FROM enrollments e
        JOIN roadmaps r ON e.roadmap_id = r.id
        WHERE r.instructor_id = ?
        AND e.enrollment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(e.enrollment_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$instructor_id]);
    $enrollment_trend = $stmt->fetchAll();
    
    // Get revenue by month (last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(p.payment_date, '%Y-%m') as month,
            COALESCE(SUM(p.amount), 0) as revenue
        FROM payments p
        JOIN roadmaps r ON p.roadmap_id = r.id
        WHERE r.instructor_id = ?
        AND p.payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$instructor_id]);
    $revenue_trend = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Instructor dashboard error: " . $e->getMessage());
    $total_roadmaps = 0;
    $approved_roadmaps = 0;
    $pending_roadmaps = 0;
    $changed_roadmaps = 0;
    $rejected_roadmaps = 0;
    $total_students = 0;
    $total_revenue = 0;
    $recent_enrollments = 0;
    $average_rating = 0;
    $top_roadmap = ['title' => 'No roadmaps yet', 'student_count' => 0];
    $roadmap_status_data = [];
    $enrollment_trend = [];
    $revenue_trend = [];
}

// Prepare data for JavaScript charts
$status_labels = [];
$status_data = [];
$status_colors = [
    'approved' => '#10b981', // green
    'pending' => '#3b82f6',  // blue
    'changed' => '#f59e0b',  // yellow
    'rejected' => '#ef4444'  // red
];

foreach ($roadmap_status_data as $row) {
    $status_labels[] = ucfirst($row['status']);
    $status_data[] = $row['count'];
}

// Prepare enrollment trend data
$enrollment_months = [];
$enrollment_counts = [];
foreach ($enrollment_trend as $row) {
    $enrollment_months[] = date('M Y', strtotime($row['month'] . '-01'));
    $enrollment_counts[] = (int)$row['enrollments'];
}

// Prepare revenue trend data
$revenue_months = [];
$revenue_amounts = [];
foreach ($revenue_trend as $row) {
    $revenue_months[] = date('M Y', strtotime($row['month'] . '-01'));
    $revenue_amounts[] = (float)$row['revenue'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fade-in 0.3s ease-out;
        }
        
        /* Fixed sidebar styles */
        .sidebar-container {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 16rem;
            z-index: 40;
            background-color: white;
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
        }
        
        .main-content {
            margin-left: 16rem;
            flex: 1;
            min-height: 100vh;
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        }
        
        body {
            min-height: 100vh;
        }
        
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
        }
        
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
        
        /* Glass morphism effect */
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Gradient backgrounds for stats */
        .gradient-purple {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }
        .gradient-green {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .gradient-blue {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        .gradient-yellow {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        .gradient-red {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        .gradient-pink {
            background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
        }
        
        /* Chart container */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        /* Progress circle */
        .progress-circle {
            width: 120px;
            height: 120px;
        }
        
        /* Shine effect */
        .shine:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .transition-all {
            transition: all 0.3s ease;
        }
        
        /* Loading skeleton */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">

<div class="flex min-h-screen">
    <!-- Fixed Sidebar -->
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
        <!-- Header -->
        <div class="mb-8">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-4xl font-bold text-gray-800">Dashboard Overview</h1>
                    <p class="text-gray-600 mt-2">Welcome back, <?php echo htmlspecialchars($instructor_name ?? ''); ?>! Here's your performance summary.</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Last updated: <?php echo date('F j, Y g:i A'); ?></p>
                    <a href="create_roadmap.php" class="mt-2 inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        <i class="fas fa-plus mr-2"></i> Create New Roadmap
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Roadmaps -->
            <div class="glass-card rounded-xl shadow-sm p-6 gradient-purple text-white shine transition-all">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-drafting-compass text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium opacity-90">Total Roadmaps</h3>
                        <p class="text-3xl font-bold mt-1"><?php echo $total_roadmaps; ?></p>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-white/20">
                    <p class="text-sm opacity-90"><?php echo $approved_roadmaps; ?> approved • <?php echo $pending_roadmaps; ?> pending</p>
                </div>
            </div>
            
            <!-- Total Students -->
            <div class="glass-card rounded-xl shadow-sm p-6 gradient-blue text-white shine transition-all">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium opacity-90">Total Students</h3>
                        <p class="text-3xl font-bold mt-1"><?php echo $total_students; ?></p>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-white/20">
                    <p class="text-sm opacity-90"><?php echo $recent_enrollments; ?> new in last 30 days</p>
                </div>
            </div>
            
            <!-- Total Revenue -->
            <div class="glass-card rounded-xl shadow-sm p-6 gradient-green text-white shine transition-all">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-dollar-sign text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium opacity-90">Total Revenue</h3>
                        <p class="text-3xl font-bold mt-1">$<?php echo number_format($total_revenue, 2); ?></p>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-white/20">
                    <p class="text-sm opacity-90">Lifetime earnings</p>
                </div>
            </div>
            
            <!-- Average Rating -->
            <div class="glass-card rounded-xl shadow-sm p-6 gradient-yellow text-white shine transition-all">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-star text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium opacity-90">Average Rating</h3>
                        <p class="text-3xl font-bold mt-1"><?php echo number_format($average_rating, 1); ?>/5</p>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-white/20">
                    <div class="flex items-center">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star text-sm <?php echo $i <= round($average_rating) ? 'text-yellow-300' : 'text-white/30'; ?> mr-1"></i>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Roadmap Status Chart -->
            <div class="glass-card rounded-xl shadow-sm p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Roadmap Status Distribution</h2>
                    <div class="flex space-x-2">
                        <?php 
                        $badge_colors = [
                            'approved' => 'bg-green-100 text-green-800',
                            'pending' => 'bg-blue-100 text-blue-800',
                            'changed' => 'bg-yellow-100 text-yellow-800',
                            'rejected' => 'bg-red-100 text-red-800'
                        ];
                        ?>
                        <?php if (!empty($roadmap_status_data)): ?>
                            <?php foreach($roadmap_status_data as $status): ?>
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $badge_colors[$status['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo ucfirst($status['status']); ?>: <?php echo $status['count']; ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                No roadmaps yet
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="chart-container">
                    <?php if (!empty($roadmap_status_data)): ?>
                        <canvas id="roadmapStatusChart"></canvas>
                    <?php else: ?>
                        <div class="flex items-center justify-center h-full">
                            <div class="text-center">
                                <i class="fas fa-chart-pie text-gray-300 text-4xl mb-3"></i>
                                <p class="text-gray-500">No roadmap data available</p>
                                <p class="text-sm text-gray-400 mt-1">Create your first roadmap to see statistics</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Enrollment Trend Chart -->
            <div class="glass-card rounded-xl shadow-sm p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Enrollment Trend (6 Months)</h2>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Total: <?php echo array_sum($enrollment_counts); ?> enrollments</p>
                    </div>
                </div>
                <div class="chart-container">
                    <?php if (!empty($enrollment_trend)): ?>
                        <canvas id="enrollmentTrendChart"></canvas>
                    <?php else: ?>
                        <div class="flex items-center justify-center h-full">
                            <div class="text-center">
                                <i class="fas fa-chart-line text-gray-300 text-4xl mb-3"></i>
                                <p class="text-gray-500">No enrollment data available</p>
                                <p class="text-sm text-gray-400 mt-1">Students will appear here when they enroll</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Additional Stats and Info -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Performance Metrics -->
            <div class="glass-card rounded-xl shadow-sm p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">Performance Metrics</h2>
                <div class="space-y-6">
                    <!-- Approval Rate -->
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Approval Rate</span>
                            <span class="text-sm font-bold text-green-600">
                                <?php echo $total_roadmaps > 0 ? number_format(($approved_roadmaps / $total_roadmaps) * 100, 1) : 0; ?>%
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" 
                                 style="width: <?php echo $total_roadmaps > 0 ? ($approved_roadmaps / $total_roadmaps) * 100 : 0; ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- Student Growth -->
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Student Growth (30 days)</span>
                            <span class="text-sm font-bold text-blue-600">
                                +<?php echo $recent_enrollments; ?>
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" 
                                 style="width: <?php echo min(100, $recent_enrollments * 10); ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- Revenue Growth -->
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Revenue Growth (30 days)</span>
                            <span class="text-sm font-bold text-green-600">
                                <?php
                                // Calculate 30-day revenue (simplified)
                                $recent_revenue = 0;
                                if (!empty($revenue_amounts)) {
                                    $recent_revenue = end($revenue_amounts);
                                }
                                ?>
                                $<?php echo number_format($recent_revenue, 2); ?>
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" 
                                 style="width: <?php echo $total_revenue > 0 ? min(100, ($recent_revenue / $total_revenue) * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Performing Roadmap - FIXED: Added proper checks -->
            <div class="glass-card rounded-xl shadow-sm p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">Top Performing Roadmap</h2>
                <div class="text-center p-4">
                    <?php if ($top_roadmap && isset($top_roadmap['title']) && $top_roadmap['student_count'] > 0): ?>
                        <div class="inline-block p-4 bg-indigo-100 rounded-full mb-4">
                            <i class="fas fa-trophy text-indigo-600 text-3xl"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-800 mb-2 truncate"><?php echo htmlspecialchars($top_roadmap['title']); ?></h3>
                        <div class="flex items-center justify-center space-x-6 mt-4">
                            <div class="text-center">
                                <p class="text-sm text-gray-600">Students</p>
                                <p class="text-2xl font-bold text-indigo-600"><?php echo $top_roadmap['student_count']; ?></p>
                            </div>
                            <div class="text-center">
                                <p class="text-sm text-gray-600">Estimated Revenue</p>
                                <p class="text-2xl font-bold text-green-600">
                                    $<?php echo number_format($top_roadmap['student_count'] * 29.99, 2); ?>
                                </p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="inline-block p-4 bg-gray-100 rounded-full mb-4">
                            <i class="fas fa-road text-gray-400 text-3xl"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-800 mb-2">No Top Roadmap Yet</h3>
                        <p class="text-gray-600">Create and get your roadmaps approved to see performance data</p>
                        <a href="create_roadmap.php" class="mt-4 inline-block px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                            Create First Roadmap
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="glass-card rounded-xl shadow-sm p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-6">Quick Actions</h2>
                <div class="space-y-4">
                    <a href="create_roadmap.php" class="flex items-center p-4 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                        <div class="p-2 bg-indigo-100 rounded-lg mr-4">
                            <i class="fas fa-plus-circle text-indigo-600"></i>
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-800">Create New Roadmap</h3>
                            <p class="text-sm text-gray-600 mt-1">Start building a new course</p>
                        </div>
                    </a>
                    
                    <a href="my_roadmaps.php" class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                        <div class="p-2 bg-blue-100 rounded-lg mr-4">
                            <i class="fas fa-edit text-blue-600"></i>
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-800">Manage Roadmaps</h3>
                            <p class="text-sm text-gray-600 mt-1">View and edit all your roadmaps</p>
                        </div>
                    </a>
                    
                    <a href="students.php" class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                        <div class="p-2 bg-green-100 rounded-lg mr-4">
                            <i class="fas fa-user-graduate text-green-600"></i>
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-800">View Students</h3>
                            <p class="text-sm text-gray-600 mt-1">See all enrolled students</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Initialize Roadmap Status Chart
<?php if (!empty($roadmap_status_data)): ?>
const roadmapStatusCtx = document.getElementById('roadmapStatusChart');
if (roadmapStatusCtx) {
    const roadmapStatusChart = new Chart(roadmapStatusCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($status_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($status_data); ?>,
                backgroundColor: [
                    '#10b981', // approved - green
                    '#3b82f6', // pending - blue
                    '#f59e0b', // changed - yellow
                    '#ef4444'  // rejected - red
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            let value = context.raw || 0;
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '70%'
        }
    });
}
<?php endif; ?>

// Initialize Enrollment Trend Chart
<?php if (!empty($enrollment_trend)): ?>
const enrollmentTrendCtx = document.getElementById('enrollmentTrendChart');
if (enrollmentTrendCtx) {
    const enrollmentTrendChart = new Chart(enrollmentTrendCtx.getContext('2d'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($enrollment_months); ?>,
            datasets: [{
                label: 'Enrollments',
                data: <?php echo json_encode($enrollment_counts); ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3b82f6',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleFont: { size: 14 },
                    bodyFont: { size: 13 },
                    padding: 12
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 11
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        font: {
                            size: 11
                        },
                        callback: function(value) {
                            if (value % 1 === 0) {
                                return value;
                            }
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'nearest'
            }
        }
    });
}
<?php endif; ?>

// Add shine effect on cards
document.addEventListener('DOMContentLoaded', function() {
    const shineCards = document.querySelectorAll('.shine');
    shineCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.3s ease';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transition = 'all 0.3s ease';
        });
    });
    
    // Auto-refresh dashboard every 5 minutes
    setTimeout(() => {
        window.location.reload();
    }, 300000); // 5 minutes
});
</script>

</body>
</html>