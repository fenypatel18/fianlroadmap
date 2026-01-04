<?php
// instructor/my_roadmaps.php

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
    
    // If first login, redirect to change password page
    if ($first_login) {
        header("Location: change_password.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("First login check error: " . $e->getMessage());
}

// Handle Delete Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_roadmap'])) {
    $roadmap_id = filter_input(INPUT_POST, 'roadmap_id', FILTER_SANITIZE_NUMBER_INT);
    
    if ($roadmap_id) {
        try {
            // Check if roadmap belongs to this instructor
            $stmt = $pdo->prepare("SELECT instructor_id FROM roadmaps WHERE id = ?");
            $stmt->execute([$roadmap_id]);
            $roadmap_owner = $stmt->fetchColumn();
            
            if ($roadmap_owner == $instructor_id) {
                // Delete roadmap (cascade will delete phases and videos)
                $stmt = $pdo->prepare("DELETE FROM roadmaps WHERE id = ?");
                $stmt->execute([$roadmap_id]);
                
                $_SESSION['success_message'] = "Roadmap deleted successfully!";
            } else {
                $_SESSION['error_message'] = "You don't have permission to delete this roadmap.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error deleting roadmap: " . $e->getMessage();
        }
        
        // Redirect back to my_roadmaps.php
        header("Location: my_roadmaps.php");
        exit();
    }
}

// Handle Duplicate Action
if (isset($_GET['duplicate'])) {
    $roadmap_id = filter_input(INPUT_GET, 'duplicate', FILTER_SANITIZE_NUMBER_INT);
    
    if ($roadmap_id) {
        try {
            // Check if roadmap belongs to this instructor
            $stmt = $pdo->prepare("SELECT * FROM roadmaps WHERE id = ? AND instructor_id = ?");
            $stmt->execute([$roadmap_id, $instructor_id]);
            $roadmap = $stmt->fetch();
            
            if ($roadmap) {
                // Store roadmap data in session for duplication
                $_SESSION['duplicate_roadmap'] = $roadmap;
                
                // Get phases and videos for duplication
                $stmt = $pdo->prepare("
                    SELECT p.*, v.id as video_id, v.title as video_title, v.video_url, v.video_order
                    FROM roadmap_phases p
                    LEFT JOIN roadmap_videos v ON p.id = v.phase_id
                    WHERE p.roadmap_id = ?
                    ORDER BY p.phase_order, v.video_order
                ");
                $stmt->execute([$roadmap_id]);
                $phases_data = $stmt->fetchAll();
                
                // Organize phases with videos
                $phases = [];
                foreach ($phases_data as $row) {
                    $phase_id = $row['id'];
                    if (!isset($phases[$phase_id])) {
                        $phases[$phase_id] = [
                            'title' => $row['title'],
                            'phase_order' => $row['phase_order'],
                            'videos' => []
                        ];
                    }
                    
                    if ($row['video_id']) {
                        $phases[$phase_id]['videos'][] = [
                            'title' => $row['video_title'],
                            'video_url' => $row['video_url'],
                            'video_order' => $row['video_order']
                        ];
                    }
                }
                
                $_SESSION['duplicate_phases'] = $phases;
                $_SESSION['duplicate_redirect'] = 'my_roadmaps.php';
                
                // Redirect to create_roadmap.php with duplicate data
                header("Location: create_roadmap.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Roadmap not found or you don't have permission to duplicate it.";
                header("Location: my_roadmaps.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error duplicating roadmap: " . $e->getMessage();
            header("Location: my_roadmaps.php");
            exit();
        }
    }
}

// Function to fetch roadmaps by status
function fetchRoadmapsByStatus($pdo, $instructor_id, $status) {
    $stmt = $pdo->prepare("
        SELECT 
            r.id, 
            r.title, 
            r.price, 
            r.status, 
            r.description,
            r.created_at,
            (SELECT COUNT(*) FROM roadmap_phases WHERE roadmap_id = r.id) as total_phases,
            (SELECT COUNT(*) FROM enrollments WHERE roadmap_id = r.id) as student_count
        FROM roadmaps r
        WHERE r.instructor_id = ? AND r.status = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$instructor_id, $status]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch roadmaps for each status
$approved_roadmaps = fetchRoadmapsByStatus($pdo, $instructor_id, 'approved');
$changed_roadmaps = fetchRoadmapsByStatus($pdo, $instructor_id, 'changed');
$rejected_roadmaps = fetchRoadmapsByStatus($pdo, $instructor_id, 'rejected');
$pending_roadmaps_data = fetchRoadmapsByStatus($pdo, $instructor_id, 'pending');

// Get stats for this instructor
try {
    // Get total roadmaps created by this instructor
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE instructor_id = ?");
    $stmt->execute([$instructor_id]);
    $total_roadmaps = $stmt->fetchColumn();
    
    // Get approved roadmaps
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE instructor_id = ? AND status = 'approved'");
    $stmt->execute([$instructor_id]);
    $approved_count = $stmt->fetchColumn();
    
    // Get changed roadmaps (request to change)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE instructor_id = ? AND status = 'changed'");
    $stmt->execute([$instructor_id]);
    $changed_count = $stmt->fetchColumn();
    
    // Get pending roadmaps
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE instructor_id = ? AND status = 'pending'");
    $stmt->execute([$instructor_id]);
    $pending_count = $stmt->fetchColumn();
    
    // Get rejected roadmaps
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE instructor_id = ? AND status = 'rejected'");
    $stmt->execute([$instructor_id]);
    $rejected_count = $stmt->fetchColumn();
    
    // Get total students enrolled
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT e.student_id) 
        FROM enrollments e
        JOIN roadmaps r ON e.roadmap_id = r.id
        WHERE r.instructor_id = ?
    ");
    $stmt->execute([$instructor_id]);
    $total_students = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("My roadmaps stats error: " . $e->getMessage());
    $total_roadmaps = 0;
    $approved_count = 0;
    $changed_count = 0;
    $pending_count = 0;
    $rejected_count = 0;
    $total_students = 0;
}

// Check for messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Roadmaps - SkillPath Builder</title>
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
            background-color: #f9fafb;
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
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-changed { background-color: #fef3c7; color: #92400e; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .status-pending { background-color: #e0e7ff; color: #3730a3; }
        
        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background-color: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
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
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-800">My Roadmaps</h1>
            <p class="text-gray-600 mt-2">View and manage all your roadmaps</p>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg animate-fade-in">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo $success_message; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg animate-fade-in">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo $error_message; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mt-8">
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <i class="fas fa-drafting-compass text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Roadmaps</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $total_roadmaps; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-check-double text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Approved</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $approved_count; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-lg">
                        <i class="fas fa-edit text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Need Changes</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $changed_count; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-clock text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Pending</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $pending_count; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-red-100 rounded-lg">
                        <i class="fas fa-times text-red-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Rejected</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $rejected_count; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approved Roadmaps Section -->
        <div class="bg-white rounded-xl shadow-sm mt-8 overflow-hidden">
            <div class="section-header px-6 py-4">
                <h2 class="text-lg font-medium text-white">
                    <i class="fas fa-check-circle mr-2"></i>Approved Roadmaps (<?php echo $approved_count; ?>)
                </h2>
                <p class="text-sm text-white/90 mt-1">Your roadmaps that have been approved and are visible to students</p>
            </div>
            
            <?php if (empty($approved_roadmaps)): ?>
                <div class="px-6 py-12 text-center">
                    <i class="fas fa-check-circle text-gray-300 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900">No approved roadmaps yet</h3>
                    <p class="text-gray-600 mt-1">Once your roadmaps are approved, they'll appear here</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                    <?php foreach ($approved_roadmaps as $roadmap): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-300">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-bold text-lg text-gray-800 truncate"><?php echo htmlspecialchars($roadmap['title']); ?></h3>
                                    <p class="text-sm text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars($roadmap['description']); ?></p>
                                </div>
                                <span class="status-badge status-approved">
                                    <i class="fas fa-check mr-1"></i>
                                    Approved
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">Price</p>
                                    <p class="text-lg font-bold text-indigo-600">$<?php echo number_format($roadmap['price'], 2); ?></p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">Phases</p>
                                    <p class="text-lg font-bold text-blue-600"><?php echo $roadmap['total_phases']; ?></p>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center pt-3 border-t border-gray-100">
                                <span class="text-xs text-gray-500">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    <?php echo date('M d, Y', strtotime($roadmap['created_at'])); ?>
                                </span>
                                <div class="flex space-x-2">
                                    <a href="view_roadmap.php?id=<?php echo $roadmap['id']; ?>" 
                                       class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-lg text-sm hover:bg-indigo-200">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_roadmap.php?id=<?php echo $roadmap['id']; ?>" 
                                       class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg text-sm hover:bg-blue-200">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="my_roadmaps.php?duplicate=<?php echo $roadmap['id']; ?>" 
                                       class="px-3 py-1 bg-purple-100 text-purple-700 rounded-lg text-sm hover:bg-purple-200">
                                        <i class="fas fa-copy"></i>
                                    </a>
                                    <button onclick="showDeleteModal(<?php echo $roadmap['id']; ?>, '<?php echo htmlspecialchars(addslashes($roadmap['title'])); ?>')" 
                                            class="px-3 py-1 bg-red-100 text-red-700 rounded-lg text-sm hover:bg-red-200">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Roadmaps Needing Changes Section -->
        <div class="bg-white rounded-xl shadow-sm mt-8 overflow-hidden">
            <div class="bg-yellow-50 px-6 py-4 border-b border-yellow-200">
                <h2 class="text-lg font-medium text-yellow-800">
                    <i class="fas fa-edit mr-2"></i>Roadmaps Needing Changes (<?php echo $changed_count; ?>)
                </h2>
                <p class="text-sm text-yellow-700 mt-1">Admin has requested changes. Please review and resubmit</p>
            </div>
            
            <?php if (empty($changed_roadmaps)): ?>
                <div class="px-6 py-12 text-center">
                    <i class="fas fa-edit text-gray-300 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900">No roadmaps needing changes</h3>
                    <p class="text-gray-600 mt-1">Great! All your roadmaps are either approved or pending</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                    <?php foreach ($changed_roadmaps as $roadmap): ?>
                        <div class="border border-yellow-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-yellow-50/50">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-bold text-lg text-gray-800 truncate"><?php echo htmlspecialchars($roadmap['title']); ?></h3>
                                    <p class="text-sm text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars($roadmap['description']); ?></p>
                                </div>
                                <span class="status-badge status-changed">
                                    <i class="fas fa-edit mr-1"></i>
                                    Changes Needed
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">Price</p>
                                    <p class="text-lg font-bold text-indigo-600">$<?php echo number_format($roadmap['price'], 2); ?></p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">Phases</p>
                                    <p class="text-lg font-bold text-blue-600"><?php echo $roadmap['total_phases']; ?></p>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center pt-3 border-t border-yellow-100">
                                <span class="text-xs text-gray-500">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    <?php echo date('M d, Y', strtotime($roadmap['created_at'])); ?>
                                </span>
                                <div class="flex space-x-2">
                                    <a href="view_roadmap.php?id=<?php echo $roadmap['id']; ?>" 
                                       class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-lg text-sm hover:bg-indigo-200">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_roadmap.php?id=<?php echo $roadmap['id']; ?>" 
                                       class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg text-sm hover:bg-blue-200">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="my_roadmaps.php?duplicate=<?php echo $roadmap['id']; ?>" 
                                       class="px-3 py-1 bg-purple-100 text-purple-700 rounded-lg text-sm hover:bg-purple-200">
                                        <i class="fas fa-copy"></i>
                                    </a>
                                    <button onclick="showDeleteModal(<?php echo $roadmap['id']; ?>, '<?php echo htmlspecialchars(addslashes($roadmap['title'])); ?>')" 
                                            class="px-3 py-1 bg-red-100 text-red-700 rounded-lg text-sm hover:bg-red-200">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Rejected Roadmaps Section -->
        <div class="bg-white rounded-xl shadow-sm mt-8 overflow-hidden">
            <div class="bg-red-50 px-6 py-4 border-b border-red-200">
                <h2 class="text-lg font-medium text-red-800">
                    <i class="fas fa-times-circle mr-2"></i>Rejected Roadmaps (<?php echo $rejected_count; ?>)
                </h2>
                <p class="text-sm text-red-700 mt-1">These roadmaps were not approved. You can review and create new ones</p>
            </div>
            
            <?php if (empty($rejected_roadmaps)): ?>
                <div class="px-6 py-12 text-center">
                    <i class="fas fa-check-circle text-gray-300 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900">No rejected roadmaps</h3>
                    <p class="text-gray-600 mt-1">Great! None of your roadmaps have been rejected</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                    <?php foreach ($rejected_roadmaps as $roadmap): ?>
                        <div class="border border-red-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-red-50/50">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-bold text-lg text-gray-800 truncate"><?php echo htmlspecialchars($roadmap['title']); ?></h3>
                                    <p class="text-sm text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars($roadmap['description']); ?></p>
                                </div>
                                <span class="status-badge status-rejected">
                                    <i class="fas fa-times mr-1"></i>
                                    Rejected
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">Price</p>
                                    <p class="text-lg font-bold text-indigo-600">$<?php echo number_format($roadmap['price'], 2); ?></p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">Phases</p>
                                    <p class="text-lg font-bold text-blue-600"><?php echo $roadmap['total_phases']; ?></p>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center pt-3 border-t border-red-100">
                                <span class="text-xs text-gray-500">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    <?php echo date('M d, Y', strtotime($roadmap['created_at'])); ?>
                                </span>
                                <div class="flex space-x-2">
                                    <a href="view_roadmap.php?id=<?php echo $roadmap['id']; ?>" 
                                       class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-lg text-sm hover:bg-indigo-200">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="my_roadmaps.php?duplicate=<?php echo $roadmap['id']; ?>" 
                                       class="px-3 py-1 bg-purple-100 text-purple-700 rounded-lg text-sm hover:bg-purple-200">
                                        <i class="fas fa-copy"></i>
                                    </a>
                                    <button onclick="showDeleteModal(<?php echo $roadmap['id']; ?>, '<?php echo htmlspecialchars(addslashes($roadmap['title'])); ?>')" 
                                            class="px-3 py-1 bg-red-100 text-red-700 rounded-lg text-sm hover:bg-red-200">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pending Roadmaps Section -->
        <div class="bg-white rounded-xl shadow-sm mt-8 overflow-hidden">
            <div class="bg-blue-50 px-6 py-4 border-b border-blue-200">
                <h2 class="text-lg font-medium text-blue-800">
                    <i class="fas fa-clock mr-2"></i>Pending Roadmaps (<?php echo $pending_count; ?>)
                </h2>
                <p class="text-sm text-blue-700 mt-1">Your roadmaps awaiting admin approval</p>
            </div>
            
            <?php if (empty($pending_roadmaps_data)): ?>
                <div class="px-6 py-12 text-center">
                    <i class="fas fa-clock text-gray-300 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900">No pending roadmaps</h3>
                    <p class="text-gray-600 mt-1">Create a new roadmap to get started</p>
                    <a href="create_roadmap.php" class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        <i class="fas fa-plus mr-2"></i>
                        Create Roadmap
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                    <?php foreach ($pending_roadmaps_data as $roadmap): ?>
                        <div class="border border-blue-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-300 bg-blue-50/50">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-bold text-lg text-gray-800 truncate"><?php echo htmlspecialchars($roadmap['title']); ?></h3>
                                    <p class="text-sm text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars($roadmap['description']); ?></p>
                                </div>
                                <span class="status-badge status-pending">
                                    <i class="fas fa-clock mr-1"></i>
                                    Pending
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">Price</p>
                                    <p class="text-lg font-bold text-indigo-600">$<?php echo number_format($roadmap['price'], 2); ?></p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xs text-gray-500">Phases</p>
                                    <p class="text-lg font-bold text-blue-600"><?php echo $roadmap['total_phases']; ?></p>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center pt-3 border-t border-blue-100">
                                <span class="text-xs text-gray-500">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    <?php echo date('M d, Y', strtotime($roadmap['created_at'])); ?>
                                </span>
                                <div class="flex space-x-2">
                                    <a href="view_roadmap.php?id=<?php echo $roadmap['id']; ?>" 
                                       class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-lg text-sm hover:bg-indigo-200">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_roadmap.php?id=<?php echo $roadmap['id']; ?>" 
                                       class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg text-sm hover:bg-blue-200">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="showDeleteModal(<?php echo $roadmap['id']; ?>, '<?php echo htmlspecialchars(addslashes($roadmap['title'])); ?>')" 
                                            class="px-3 py-1 bg-red-100 text-red-700 rounded-lg text-sm hover:bg-red-200">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <form method="POST" action="">
            <input type="hidden" name="roadmap_id" id="deleteRoadmapId">
            <input type="hidden" name="delete_roadmap" value="1">
            
            <div class="p-6">
                <div class="text-center mb-6">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <h3 id="deleteModalTitle" class="text-lg font-medium text-gray-900 mb-2">Delete Roadmap</h3>
                    <p id="deleteModalMessage" class="text-sm text-gray-500">
                        Are you sure you want to delete this roadmap? This action cannot be undone.
                    </p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDeleteModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Delete
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Delete Modal Functions
    function showDeleteModal(roadmapId, roadmapTitle) {
        const modal = document.getElementById('deleteModal');
        const title = document.getElementById('deleteModalTitle');
        const message = document.getElementById('deleteModalMessage');
        const input = document.getElementById('deleteRoadmapId');
        
        title.textContent = `Delete "${roadmapTitle}"`;
        message.textContent = `Are you sure you want to delete "${roadmapTitle}"? This action cannot be undone and all associated data will be lost.`;
        input.value = roadmapId;
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
    
    // Close modal when clicking outside
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeleteModal();
        }
    });
</script>

</body>
</html>