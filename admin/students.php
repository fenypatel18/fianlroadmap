<?php
// admin/students.php

// --- SETUP & SECURITY ---
require_once __DIR__ . '/../auth/middleware.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

// Check if it's fixed admin
$is_fixed_admin = isset($_SESSION['is_fixed_admin']) && $_SESSION['is_fixed_admin'] === true;
$admin_name = $_SESSION['name'] ?? 'Admin';

// Get stats for dashboard sidebar
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
    
    // Get pending roadmaps for review
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE status = 'pending'");
    $stmt->execute();
    $pending_roadmaps = $stmt->fetchColumn();
    
    // Get recent feedback count (last 7 days)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $recent_feedback = $stmt->fetchColumn();
    
    // Get total certificates issued
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates");
        $stmt->execute();
        $total_certificates = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $total_certificates = 0;
    }
    
    // Get total revenue for dashboard stats
    try {
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE status = 'success'");
        $stmt->execute();
        $total_revenue = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $total_revenue = 0;
    }
    
    // Get total enrollments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments");
    $stmt->execute();
    $total_enrollments = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Students page database error: " . $e->getMessage());
    $total_instructors = 0;
    $total_students = 0;
    $total_roadmaps = 0;
    $pending_roadmaps = 0;
    $recent_feedback = 0;
    $total_certificates = 0;
    $total_revenue = 0;
    $total_enrollments = 0;
}

// --- DATA FETCHING ---
// Fetch all students with their enrollments, progress, and roadmap details
$stmt = $pdo->prepare("
    SELECT 
        u.id AS student_id,
        u.name AS student_name,
        u.email AS student_email,
        u.created_at AS joined_date,
        u.status AS student_status,
        GROUP_CONCAT(DISTINCT r.title ORDER BY r.title SEPARATOR '; ') AS enrolled_roadmaps,
        GROUP_CONCAT(DISTINCT r.id ORDER BY r.id SEPARATOR ',') AS roadmap_ids,
        COUNT(DISTINCT e.roadmap_id) AS total_enrolled,
        COUNT(DISTINCT p.video_id) AS videos_completed,
        COUNT(DISTINCT rv.id) AS total_videos,
        GROUP_CONCAT(DISTINCT cert.id ORDER BY cert.id SEPARATOR ',') AS certificate_ids,
        COUNT(DISTINCT cert.id) AS certificates_earned
    FROM users u
    LEFT JOIN enrollments e ON u.id = e.student_id
    LEFT JOIN roadmaps r ON e.roadmap_id = r.id
    LEFT JOIN roadmap_phases rp ON r.id = rp.roadmap_id
    LEFT JOIN roadmap_videos rv ON rp.id = rv.phase_id
    LEFT JOIN progress p ON rv.id = p.video_id AND p.student_id = u.id AND p.completed = 1
    LEFT JOIN certificates cert ON u.id = cert.student_id AND r.id = cert.roadmap_id
    WHERE u.role = 'student'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate progress percentage for each student
foreach ($students as &$student) {
    if ($student['total_videos'] > 0) {
        $student['progress_percentage'] = round(($student['videos_completed'] / $student['total_videos']) * 100, 1);
    } else {
        $student['progress_percentage'] = 0;
    }
}
unset($student); // Break the reference

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Admin Panel</title>
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
        .progress-bar {
            transition: width 0.6s ease;
        }
        .roadmap-badge {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
            <a href="dashboard.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
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
            
            <!-- Students (Active) -->
            <a href="students.php" class="flex items-center px-6 py-3 text-gray-700 active-link">
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
                <h1 class="text-4xl font-bold text-gray-800">Student Management</h1>
                <p class="text-gray-600 mt-2">View and manage all students, their enrollments, and learning progress.</p>
            </div>
            <div class="text-sm text-gray-500">
                Last updated: <?php echo date('M j, Y g:i A'); ?>
            </div>
        </div>
        
        <!-- Dashboard Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
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

        <!-- Student Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-gradient-to-r from-green-500 to-emerald-600 text-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-user-graduate text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium opacity-90">Total Students</h3>
                        <p class="text-3xl font-bold mt-1"><?php echo $total_students; ?></p>
                        <p class="text-xs opacity-80 mt-1">Active learners</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-gradient-to-r from-blue-500 to-cyan-600 text-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-book-open text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium opacity-90">Total Enrollments</h3>
                        <p class="text-3xl font-bold mt-1"><?php echo $total_enrollments; ?></p>
                        <p class="text-xs opacity-80 mt-1">Course enrollments</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-gradient-to-r from-purple-500 to-pink-600 text-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-certificate text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium opacity-90">Certificates</h3>
                        <p class="text-3xl font-bold mt-1"><?php echo $total_certificates; ?></p>
                        <p class="text-xs opacity-80 mt-1">Awarded to students</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-gradient-to-r from-yellow-500 to-orange-600 text-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-chart-line text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium opacity-90">Avg Progress</h3>
                        <p class="text-3xl font-bold mt-1">
                            <?php 
                            if (!empty($students)) {
                                $total_progress = 0;
                                foreach ($students as $student) {
                                    $total_progress += $student['progress_percentage'];
                                }
                                echo round($total_progress / count($students), 1) . '%';
                            } else {
                                echo '0%';
                            }
                            ?>
                        </p>
                        <p class="text-xs opacity-80 mt-1">Across all students</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Students Table -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">All Students</h2>
                <div class="text-sm text-gray-500">
                    Showing <?php echo count($students); ?> student<?php echo count($students) !== 1 ? 's' : ''; ?>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled Roadmaps</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="text-gray-400 mb-4">
                                        <i class="fas fa-user-graduate text-5xl"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-600 mb-2">No Students Yet</h3>
                                    <p class="text-gray-500 max-w-md mx-auto">
                                        No students have registered yet. They will appear here when they sign up and enroll in roadmaps.
                                    </p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-12 w-12 bg-gradient-to-br from-green-400 to-emerald-600 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-user text-white text-lg"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($student['student_email']); ?></div>
                                                <div class="flex items-center mt-1">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mr-2">
                                                        <i class="fas fa-book-open mr-1 text-xs"></i>
                                                        <?php echo $student['total_enrolled'] ?: 0; ?> course<?php echo $student['total_enrolled'] != 1 ? 's' : ''; ?>
                                                    </span>
                                                    <?php if ($student['certificates_earned'] > 0): ?>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            <i class="fas fa-certificate mr-1 text-xs"></i>
                                                            <?php echo $student['certificates_earned']; ?> cert<?php echo $student['certificates_earned'] != 1 ? 's' : ''; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="space-y-2 max-w-xs">
                                            <?php if (!empty($student['enrolled_roadmaps'])): ?>
                                                <?php 
                                                $roadmaps = explode('; ', $student['enrolled_roadmaps']);
                                                foreach ($roadmaps as $roadmap):
                                                ?>
                                                    <div class="roadmap-badge inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800 mr-2 mb-2">
                                                        <i class="fas fa-road mr-1 text-xs"></i>
                                                        <?php echo htmlspecialchars($roadmap); ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-sm italic">Not enrolled in any roadmaps</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="space-y-2">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-700">Overall Progress</span>
                                                <span class="font-medium text-gray-900"><?php echo $student['progress_percentage']; ?>%</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div 
                                                    class="progress-bar h-2 rounded-full <?php echo $student['progress_percentage'] >= 80 ? 'bg-green-500' : ($student['progress_percentage'] >= 50 ? 'bg-blue-500' : 'bg-yellow-500'); ?>" 
                                                    style="width: <?php echo $student['progress_percentage']; ?>%"
                                                ></div>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo $student['videos_completed']; ?> of <?php echo $student['total_videos']; ?> videos completed
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $student['student_status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <span class="w-2 h-2 mr-2 rounded-full <?php echo $student['student_status'] === 'active' ? 'bg-green-400' : 'bg-red-400'; ?>"></span>
                                            <?php echo ucfirst($student['student_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo date('M d, Y', strtotime($student['joined_date'])); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php 
                                            $joined_time = strtotime($student['joined_date']);
                                            $now = time();
                                            $diff = $now - $joined_time;
                                            $days = floor($diff / (60 * 60 * 24));
                                            
                                            if ($days == 0) {
                                                echo 'Today';
                                            } elseif ($days == 1) {
                                                echo 'Yesterday';
                                            } else {
                                                echo $days . ' days ago';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewStudentDetails(<?php echo $student['student_id']; ?>)" class="text-indigo-600 hover:text-indigo-900 inline-flex items-center" title="View Details">
                                                <i class="fas fa-eye mr-1"></i> View
                                            </button>
                                            <?php if ($student['student_status'] === 'active'): ?>
                                                <a href="?action=disable&id=<?php echo $student['student_id']; ?>" class="text-red-600 hover:text-red-900 inline-flex items-center" onclick="return confirm('Are you sure you want to disable this student?')" title="Disable Student">
                                                    <i class="fas fa-ban mr-1"></i> Disable
                                                </a>
                                            <?php else: ?>
                                                <a href="?action=enable&id=<?php echo $student['student_id']; ?>" class="text-green-600 hover:text-green-900 inline-flex items-center" onclick="return confirm('Are you sure you want to enable this student?')" title="Enable Student">
                                                    <i class="fas fa-check mr-1"></i> Enable
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Student Statistics -->
            <?php if (!empty($students)): ?>
            <div class="mt-8 pt-6 border-t border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Student Statistics</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-700 mb-3">Progress Distribution</h4>
                        <div class="space-y-3">
                            <?php
                            // Group students by progress ranges
                            $progress_ranges = [
                                '0-20%' => 0,
                                '21-40%' => 0,
                                '41-60%' => 0,
                                '61-80%' => 0,
                                '81-100%' => 0
                            ];
                            
                            foreach ($students as $student) {
                                $progress = $student['progress_percentage'];
                                if ($progress <= 20) {
                                    $progress_ranges['0-20%']++;
                                } elseif ($progress <= 40) {
                                    $progress_ranges['21-40%']++;
                                } elseif ($progress <= 60) {
                                    $progress_ranges['41-60%']++;
                                } elseif ($progress <= 80) {
                                    $progress_ranges['61-80%']++;
                                } else {
                                    $progress_ranges['81-100%']++;
                                }
                            }
                            
                            $max_count = max($progress_ranges) ?: 1;
                            
                            foreach ($progress_ranges as $range => $count):
                            ?>
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm text-gray-600"><?php echo $range; ?></span>
                                        <span class="text-sm font-medium text-gray-700"><?php echo $count; ?> student<?php echo $count !== 1 ? 's' : ''; ?></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo ($count / $max_count) * 100; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-700 mb-3">Recent Activity</h4>
                        <div class="space-y-3">
                            <?php
                            // Get recent student activities
                            try {
                                $stmt = $pdo->prepare("
                                    SELECT 
                                        u.name AS student_name,
                                        p.completed_at,
                                        rv.title AS video_title,
                                        r.title AS roadmap_title
                                    FROM progress p
                                    JOIN users u ON p.student_id = u.id
                                    JOIN roadmap_videos rv ON p.video_id = rv.id
                                    JOIN roadmap_phases rp ON rv.phase_id = rp.id
                                    JOIN roadmaps r ON rp.roadmap_id = r.id
                                    WHERE p.completed = 1
                                    ORDER BY p.completed_at DESC
                                    LIMIT 3
                                ");
                                $stmt->execute();
                                $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($recent_activities as $activity):
                            ?>
                                <div class="flex items-start p-3 bg-white rounded-lg border border-gray-100">
                                    <div class="flex-shrink-0 h-10 w-10 bg-green-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-play-circle text-green-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($activity['student_name']); ?></p>
                                        <p class="text-xs text-gray-600">completed "<?php echo htmlspecialchars($activity['video_title']); ?>"</p>
                                        <div class="flex items-center mt-1">
                                            <i class="fas fa-road text-xs text-gray-400 mr-1"></i>
                                            <span class="text-xs text-gray-500"><?php echo htmlspecialchars($activity['roadmap_title']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                endforeach;
                            } catch (Exception $e) {
                                echo '<p class="text-gray-500 text-sm">No recent activity data available.</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Export Section -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Export Student Data</h3>
            <div class="flex flex-wrap gap-3">
                <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors flex items-center">
                    <i class="fas fa-file-csv mr-2"></i> Export as CSV
                </button>
                <button class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center">
                    <i class="fas fa-file-pdf mr-2"></i> Export as PDF
                </button>
                <button class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors flex items-center">
                    <i class="fas fa-print mr-2"></i> Print Report
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function viewStudentDetails(studentId) {
        // This function would typically open a modal or navigate to a detailed view
        // For now, we'll just show an alert
        alert('Viewing details for student ID: ' + studentId + '\n\nIn a real implementation, this would open a modal with detailed student information including:\n- Complete progress breakdown per roadmap\n- Quiz scores\n- Payment history\n- Certificate details\n- Activity timeline');
    }
    
    // Handle status updates
    <?php if (isset($_GET['action']) && isset($_GET['id'])): ?>
    window.onload = function() {
        const action = '<?php echo $_GET["action"]; ?>';
        const id = '<?php echo $_GET["id"]; ?>';
        
        if (action === 'enable' || action === 'disable') {
            fetch(`update_student_status.php?action=${action}&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating student status.');
                });
        }
    };
    <?php endif; ?>
</script>
</body>
</html>