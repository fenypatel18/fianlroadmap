<?php
// admin/roadmaps.php

// --- SETUP & SECURITY ---
session_start();
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
requireAdmin();

// Check if it's fixed admin
$is_fixed_admin = isset($_SESSION['is_fixed_admin']) && $_SESSION['is_fixed_admin'] === true;
$admin_name = $_SESSION['name'] ?? 'Admin';

// Get stats for sidebar
try {
    // Get pending roadmaps
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE status = 'pending'");
    $stmt->execute();
    $pending_roadmaps = $stmt->fetchColumn();
    
    // Get recent feedback count (last 7 days)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $recent_feedback = $stmt->fetchColumn();
    
    // Get total active students for dashboard-like stats
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'");
    $stmt->execute();
    $total_students = $stmt->fetchColumn();
    
    // Get total approved roadmaps for stats
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE status = 'approved'");
    $stmt->execute();
    $total_roadmaps = $stmt->fetchColumn();
    
    // Get total revenue
    try {
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE status = 'success'");
        $stmt->execute();
        $total_revenue = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $total_revenue = 0;
    }
    
} catch (PDOException $e) {
    $pending_roadmaps = 0;
    $recent_feedback = 0;
    $total_students = 0;
    $total_roadmaps = 0;
    $total_revenue = 0;
}

// Fetch all roadmaps with instructor names
$stmt = $pdo->prepare("
    SELECT 
        r.id, 
        r.title, 
        u.name as instructor_name, 
        r.price, 
        r.status, 
        r.created_at,
        (SELECT COUNT(*) FROM roadmap_phases WHERE roadmap_id = r.id) as phase_count,
        (SELECT COUNT(*) FROM enrollments WHERE roadmap_id = r.id) as enrollment_count
    FROM roadmaps r
    JOIN users u ON r.instructor_id = u.id
    ORDER BY 
        CASE r.status 
            WHEN 'pending' THEN 1
            WHEN 'changed' THEN 2
            ELSE 3 
        END,
        r.created_at DESC
");
$stmt->execute();
$roadmaps = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Roadmaps - Admin Panel</title>
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
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .status-changed { background-color: #fef3c7; color: #92400e; }
        .hover-card:hover {
            transform: translateY(-2px);
            transition: transform 0.2s ease;
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
            <a href="<?php echo url('admin/dashboard.php'); ?>" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-tachometer-alt w-6 text-center"></i>
                <span class="ml-3">Dashboard</span>
            </a>
            
            <!-- Roadmaps (Active) -->
            <a href="<?php echo url('admin/roadmaps.php'); ?>" class="flex items-center px-6 py-3 text-gray-700 active-link">
                <i class="fas fa-road w-6 text-center"></i>
                <span class="ml-3">Roadmaps</span>
                <?php if ($pending_roadmaps > 0): ?>
                    <span class="ml-auto mr-2 bg-yellow-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $pending_roadmaps; ?> pending</span>
                <?php endif; ?>
            </a>
            
            <!-- Instructors -->
            <a href="<?php echo url('admin/instructors.php'); ?>" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-users-cog w-6 text-center"></i>
                <span class="ml-3">Instructors</span>
            </a>
            
            <!-- Students (New Option) -->
            <a href="<?php echo url('admin/students.php'); ?>" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-user-graduate w-6 text-center"></i>
                <span class="ml-3">Students</span>
            </a>
            
            <!-- Feedback -->
            <a href="<?php echo url('admin/feedback.php'); ?>" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-comments w-6 text-center"></i>
                <span class="ml-3">Feedback</span>
                <?php if ($recent_feedback > 0): ?>
                    <span class="ml-auto mr-2 bg-green-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $recent_feedback; ?> new</span>
                <?php endif; ?>
            </a>
            
            <!-- Certificates -->
            <a href="<?php echo url('admin/certificates.php'); ?>" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
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
        <a href="<?php echo url('auth/logout.php'); ?>" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg hover:text-red-600">
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
                <h1 class="text-4xl font-bold text-gray-800">Roadmap Management</h1>
                <p class="text-gray-600 mt-2">Review and manage all submitted roadmaps.</p>
            </div>
            <div class="text-sm text-gray-500">
                Last updated: <?php echo date('M j, Y g:i A'); ?>
            </div>
        </div>

        <!-- Quick Stats (Dashboard Style) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
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
            
            <!-- Stat Card: Pending Review -->
            <div class="stat-card bg-white p-6 rounded-xl shadow-sm border-l-4 border-red-500">
                <div class="flex items-center">
                    <div class="p-3 bg-red-100 rounded-lg">
                        <i class="fas fa-clock text-red-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Pending Review</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $pending_roadmaps; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Awaiting approval</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Filter Tabs -->
        <div class="mb-6 bg-white rounded-xl shadow-sm p-4">
            <div class="flex space-x-4">
                <button class="px-4 py-2 rounded-lg bg-indigo-100 text-indigo-700 font-medium" onclick="filterRoadmaps('all')">
                    All Roadmaps
                </button>
                <button class="px-4 py-2 rounded-lg hover:bg-gray-100 text-gray-700" onclick="filterRoadmaps('pending')">
                    Pending
                    <span class="ml-1 bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full"><?php 
                        echo count(array_filter($roadmaps, function($r) { return $r['status'] == 'pending'; }));
                    ?></span>
                </button>
                <button class="px-4 py-2 rounded-lg hover:bg-gray-100 text-gray-700" onclick="filterRoadmaps('approved')">
                    Approved
                    <span class="ml-1 bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full"><?php 
                        echo count(array_filter($roadmaps, function($r) { return $r['status'] == 'approved'; }));
                    ?></span>
                </button>
                <button class="px-4 py-2 rounded-lg hover:bg-gray-100 text-gray-700" onclick="filterRoadmaps('rejected')">
                    Rejected
                </button>
            </div>
        </div>

        <!-- Roadmaps Grid/Table -->
        <div class="bg-white shadow-lg rounded-xl overflow-hidden">
            <?php if (empty($roadmaps)): ?>
                <div class="text-center py-16">
                    <i class="fas fa-road text-gray-300 text-5xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700">No Roadmaps Found</h3>
                    <p class="text-gray-500 mt-2">No roadmaps have been submitted yet.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Roadmap</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Instructor</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Details</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($roadmaps as $roadmap): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-200 roadmap-row" data-status="<?php echo $roadmap['status']; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-road text-indigo-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($roadmap['title']); ?></div>
                                                <div class="text-sm text-gray-500">$<?php echo number_format($roadmap['price'], 2); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($roadmap['instructor_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-2">
                                                <i class="fas fa-layer-group mr-1"></i> <?php echo $roadmap['phase_count']; ?> phases
                                            </span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-users mr-1"></i> <?php echo $roadmap['enrollment_count']; ?> enrolled
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status_class = '';
                                        switch($roadmap['status']) {
                                            case 'pending': $status_class = 'status-pending'; break;
                                            case 'approved': $status_class = 'status-approved'; break;
                                            case 'rejected': $status_class = 'status-rejected'; break;
                                            case 'changed': $status_class = 'status-changed'; break;
                                            default: $status_class = 'bg-gray-100 text-gray-800';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($roadmap['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($roadmap['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="<?php echo url('admin/roadmap_view.php?id=' . $roadmap['id']); ?>" 
                                           class="text-indigo-600 hover:text-indigo-900 font-medium">
                                            <i class="fas fa-eye mr-1"></i> Review
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Stats Footer -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-lg">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Pending Review</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1">
                            <?php echo count(array_filter($roadmaps, function($r) { return $r['status'] == 'pending'; })); ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Approved</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1">
                            <?php echo count(array_filter($roadmaps, function($r) { return $r['status'] == 'approved'; })); ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 bg-red-100 rounded-lg">
                        <i class="fas fa-times-circle text-red-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Rejected</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1">
                            <?php echo count(array_filter($roadmaps, function($r) { return $r['status'] == 'rejected'; })); ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-edit text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Needs Changes</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1">
                            <?php echo count(array_filter($roadmaps, function($r) { return $r['status'] == 'changed'; })); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Filter roadmaps by status
    function filterRoadmaps(status) {
        const rows = document.querySelectorAll('.roadmap-row');
        rows.forEach(row => {
            if (status === 'all' || row.dataset.status === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        // Update active tab
        document.querySelectorAll('button').forEach(btn => {
            btn.classList.remove('bg-indigo-100', 'text-indigo-700');
            btn.classList.add('hover:bg-gray-100', 'text-gray-700');
        });
        
        event.target.classList.add('bg-indigo-100', 'text-indigo-700');
        event.target.classList.remove('hover:bg-gray-100', 'text-gray-700');
    }
</script>

</body>
</html>