<?php
// admin/certificates.php

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
    
    // Get certificates issued this month
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE MONTH(issue_date) = MONTH(CURRENT_DATE()) AND YEAR(issue_date) = YEAR(CURRENT_DATE())");
    $stmt->execute();
    $certificates_this_month = $stmt->fetchColumn();
    
    // Get total revenue for dashboard stats
    try {
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE status = 'success'");
        $stmt->execute();
        $total_revenue = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $total_revenue = 0;
    }
    
} catch (PDOException $e) {
    error_log("Certificates page database error: " . $e->getMessage());
    $total_instructors = 0;
    $total_students = 0;
    $total_roadmaps = 0;
    $pending_roadmaps = 0;
    $recent_feedback = 0;
    $total_certificates = 0;
    $certificates_this_month = 0;
    $total_revenue = 0;
}

// --- DATA FETCHING ---
// Fetch all issued certificates with relevant details from other tables.
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.issue_date,
        c.certificate_url,
        s.name AS student_name,
        s.email AS student_email,
        r.title AS roadmap_title,
        i.name AS instructor_name
    FROM certificates c
    JOIN users s ON c.student_id = s.id
    JOIN roadmaps r ON c.roadmap_id = r.id
    JOIN users i ON r.instructor_id = i.id
    ORDER BY c.issue_date DESC
");
$stmt->execute();
$certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issued Certificates - Admin Panel</title>
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
            
            <!-- Students (New Option) -->
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
            
            <!-- Certificates (Active) -->
            <a href="certificates.php" class="flex items-center px-6 py-3 text-gray-700 active-link">
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
                <h1 class="text-4xl font-bold text-gray-800">Issued Certificates</h1>
                <p class="text-gray-600 mt-2">View all certificates awarded to students upon roadmap completion.</p>
            </div>
            <div class="text-sm text-gray-500">
                Last updated: <?php echo date('M j, Y g:i A'); ?>
            </div>
        </div>

        <!-- Dashboard Stats -->
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
        
        <!-- Certificate Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-gradient-to-r from-blue-500 to-cyan-600 text-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-certificate text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium opacity-90">Total Certificates</h3>
                        <p class="text-3xl font-bold mt-1"><?php echo $total_certificates; ?></p>
                        <p class="text-xs opacity-80 mt-1">All-time awards</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-gradient-to-r from-green-500 to-emerald-600 text-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-calendar-alt text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium opacity-90">This Month</h3>
                        <p class="text-3xl font-bold mt-1"><?php echo $certificates_this_month; ?></p>
                        <p class="text-xs opacity-80 mt-1">Issued this month</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-gradient-to-r from-purple-500 to-pink-600 text-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-user-graduate text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium opacity-90">Students</h3>
                        <p class="text-3xl font-bold mt-1"><?php echo $total_students; ?></p>
                        <p class="text-xs opacity-80 mt-1">Active learners</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-gradient-to-r from-yellow-500 to-orange-600 text-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-road text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium opacity-90">Roadmaps</h3>
                        <p class="text-3xl font-bold mt-1"><?php echo $total_roadmaps; ?></p>
                        <p class="text-xs opacity-80 mt-1">Available courses</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Certificates Table -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">All Issued Certificates</h2>
                <div class="text-sm text-gray-500">
                    Showing <?php echo count($certificates); ?> certificate<?php echo count($certificates) !== 1 ? 's' : ''; ?>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Certificate ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roadmap</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Instructor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issued Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($certificates)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="text-gray-400 mb-4">
                                        <i class="fas fa-certificate text-5xl"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-600 mb-2">No Certificates Yet</h3>
                                    <p class="text-gray-500 max-w-md mx-auto">
                                        No certificates have been issued yet. They will appear here when students complete roadmaps.
                                    </p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($certificates as $cert): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-mono text-gray-700">#<?php echo str_pad($cert['id'], 6, '0', STR_PAD_LEFT); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-green-100 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-user-graduate text-green-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($cert['student_name']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($cert['student_email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-road text-blue-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($cert['roadmap_title']); ?></div>
                                                <div class="text-xs text-gray-500">Completed roadmap</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-chalkboard-teacher text-indigo-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($cert['instructor_name']); ?></div>
                                                <div class="text-xs text-gray-500">Instructor</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo date('M d, Y', strtotime($cert['issue_date'])); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo date('g:i A', strtotime($cert['issue_date'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if (!empty($cert['certificate_url'])): ?>
                                            <a href="<?php echo htmlspecialchars($cert['certificate_url']); ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900 inline-flex items-center mr-4" title="View Certificate">
                                                <i class="fas fa-eye mr-1"></i> View
                                            </a>
                                            <a href="<?php echo htmlspecialchars($cert['certificate_url']); ?>" download class="text-green-600 hover:text-green-900 inline-flex items-center" title="Download Certificate">
                                                <i class="fas fa-download mr-1"></i> Download
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400 inline-flex items-center" title="Certificate URL not available">
                                                <i class="fas fa-times-circle mr-1"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Certificate Statistics -->
            <?php if (!empty($certificates)): ?>
            <div class="mt-8 pt-6 border-t border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Certificate Statistics</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-700 mb-3">Issuance Timeline</h4>
                        <div class="space-y-3">
                            <?php
                            // Group certificates by month
                            $monthly_data = [];
                            foreach ($certificates as $cert) {
                                $month = date('M Y', strtotime($cert['issue_date']));
                                if (!isset($monthly_data[$month])) {
                                    $monthly_data[$month] = 0;
                                }
                                $monthly_data[$month]++;
                            }
                            
                            // Get last 6 months
                            $recent_months = array_slice($monthly_data, 0, 6, true);
                            $max_count = max($recent_months) ?: 1;
                            
                            foreach ($recent_months as $month => $count):
                            ?>
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm text-gray-600"><?php echo $month; ?></span>
                                        <span class="text-sm font-medium text-gray-700"><?php echo $count; ?> certificate<?php echo $count !== 1 ? 's' : ''; ?></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo ($count / $max_count) * 100; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-700 mb-3">Recent Awards</h4>
                        <div class="space-y-3">
                            <?php
                            $recent_certificates = array_slice($certificates, 0, 3);
                            foreach ($recent_certificates as $recent):
                            ?>
                                <div class="flex items-start p-3 bg-white rounded-lg border border-gray-100">
                                    <div class="flex-shrink-0 h-10 w-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-award text-yellow-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($recent['student_name']); ?></p>
                                        <p class="text-xs text-gray-600">completed <?php echo htmlspecialchars($recent['roadmap_title']); ?></p>
                                        <div class="flex items-center mt-1">
                                            <i class="fas fa-clock text-xs text-gray-400 mr-1"></i>
                                            <span class="text-xs text-gray-500">
                                                <?php 
                                                $issued_time = strtotime($recent['issue_date']);
                                                $now = time();
                                                $diff = $now - $issued_time;
                                                
                                                if ($diff < 3600) {
                                                    echo 'Just now';
                                                } elseif ($diff < 86400) {
                                                    echo floor($diff / 3600) . ' hours ago';
                                                } else {
                                                    echo floor($diff / 86400) . ' days ago';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Export Section -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Export Certificates Data</h3>
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

</body>
</html>