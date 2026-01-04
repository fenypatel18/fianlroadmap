<?php
// admin/roadmap_view.php

require_once __DIR__ . '/../auth/middleware.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

$is_fixed_admin = isset($_SESSION['is_fixed_admin']) && $_SESSION['is_fixed_admin'] === true;
$admin_name = $_SESSION['name'] ?? 'Admin';

// Function to get correct video URL
function getFullVideoUrl($video_path) {
    // Remove leading slash if it exists
    $video_path = ltrim($video_path, '/');
    
    // Get base URL
    $base_url = rtrim(url(''), '/');
    
    // Construct full URL
    return $base_url . '/' . $video_path;
}

// Get stats for sidebar
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE status = 'pending'");
    $stmt->execute();
    $pending_roadmaps = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $recent_feedback = $stmt->fetchColumn();
} catch (PDOException $e) {
    $pending_roadmaps = 0;
    $recent_feedback = 0;
}

$roadmap_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$roadmap_id) {
    header('Location: ' . url('admin/roadmaps.php'));
    exit();
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check which button was clicked
    if (isset($_POST['approve'])) {
        $new_status = 'approved';
    } elseif (isset($_POST['reject'])) {
        $new_status = 'rejected';
    } elseif (isset($_POST['request_changes'])) {
        $new_status = 'changed';
    } else {
        $new_status = null;
    }
    
    if ($new_status) {
        try {
            $stmt = $pdo->prepare("UPDATE roadmaps SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $roadmap_id]);
            
            // Store admin notes in session temporarily
            if (isset($_POST['admin_notes']) && !empty(trim($_POST['admin_notes']))) {
                $_SESSION['admin_notes_' . $roadmap_id] = trim($_POST['admin_notes']);
            }
            
            // Set success message
            $status_display = ucfirst($new_status);
            if ($new_status == 'changed') {
                $status_display = 'Changes Requested';
            }
            
            $_SESSION['success_message'] = "Roadmap status updated to " . $status_display . " successfully!";
            header("Location: " . url('admin/roadmap_view.php?id=' . $roadmap_id));
            exit();
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch roadmap details
$stmt = $pdo->prepare("
    SELECT r.*, u.name AS instructor_name, u.email AS instructor_email 
    FROM roadmaps r 
    JOIN users u ON r.instructor_id = u.id 
    WHERE r.id = ?
");
$stmt->execute([$roadmap_id]);
$roadmap = $stmt->fetch();

if (!$roadmap) {
    header('Location: ' . url('admin/roadmaps.php'));
    exit();
}

// Fetch phases with video details
$stmt = $pdo->prepare("
    SELECT p.id, p.title AS phase_title, p.phase_order, 
           v.id as video_id, v.title as video_title, v.video_url, v.video_order
    FROM roadmap_phases p
    LEFT JOIN roadmap_videos v ON p.id = v.phase_id
    WHERE p.roadmap_id = ?
    ORDER BY p.phase_order ASC, v.video_order ASC
");
$stmt->execute([$roadmap_id]);
$all_videos = $stmt->fetchAll();

// Group videos by phase for display
$phases = [];
$phase_videos = [];

foreach ($all_videos as $row) {
    $phase_id = $row['id'];
    
    if (!isset($phases[$phase_id])) {
        $phases[$phase_id] = [
            'id' => $phase_id,
            'title' => $row['phase_title'],
            'phase_order' => $row['phase_order'],
            'video_count' => 0,
            'video_titles' => []
        ];
        $phase_videos[$phase_id] = [];
    }
    
    if ($row['video_id']) {
        $phases[$phase_id]['video_count']++;
        $phases[$phase_id]['video_titles'][] = $row['video_title'];
        
        $phase_videos[$phase_id][] = [
            'id' => $row['video_id'],
            'title' => $row['video_title'],
            'video_url' => getFullVideoUrl($row['video_url']),
            'original_url' => $row['video_url'],
            'video_order' => $row['video_order']
        ];
    }
}

$total_phases = count($phases);

// Get enrollment count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE roadmap_id = ?");
$stmt->execute([$roadmap_id]);
$enrollment_count = $stmt->fetchColumn();

// Get feedback stats
$stmt = $pdo->prepare("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_feedback 
    FROM feedback 
    WHERE roadmap_id = ?
");
$stmt->execute([$roadmap_id]);
$feedback_stats = $stmt->fetch();

// Check for stored admin notes
$stored_admin_notes = $_SESSION['admin_notes_' . $roadmap_id] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Roadmap - <?= htmlspecialchars($roadmap['title']) ?> - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .active-link {
            background-color: #eef2ff;
            color: #4f46e5;
            font-weight: 600;
        }
        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .status-changed { background-color: #fef3c7; color: #92400e; }
        .phase-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        .phase-card:hover {
            transform: translateX(4px);
        }
        .video-link {
            cursor: pointer;
            transition: color 0.2s;
            text-decoration: underline;
            color: #3b82f6;
        }
        .video-link:hover {
            color: #1d4ed8;
        }
        /* Modal Styles */
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
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-body {
            padding: 1.5rem;
        }
        .video-container {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            margin-bottom: 1rem;
            background-color: #000;
        }
        .video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6b7280;
            cursor: pointer;
            padding: 0.5rem;
        }
        .close-btn:hover {
            color: #374151;
        }
        #videoError {
            padding: 0.75rem;
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 0.375rem;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">

<div class="flex">
    <!-- Sidebar (Same as dashboard) -->
    <aside class="w-64 bg-white border-r border-gray-200 min-h-screen flex flex-col">
        <div class="px-6 py-5 border-b border-gray-200">
            <h1 class="text-xl font-bold text-indigo-600">SkillPath Builder</h1>
            <span class="text-xs text-gray-500">Admin Panel</span>
            <?php if ($is_fixed_admin): ?>
                <span class="inline-block mt-1 text-xs px-2 py-1 bg-red-100 text-red-800 rounded">Fixed Admin</span>
            <?php endif; ?>
        </div>
        <nav class="flex-grow pt-4">
            <a href="<?php echo url('admin/dashboard.php'); ?>" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-tachometer-alt w-6 text-center"></i>
                <span class="ml-3">Dashboard</span>
            </a>
            <a href="<?php echo url('admin/roadmaps.php'); ?>" class="flex items-center px-6 py-3 text-gray-700 active-link">
                <i class="fas fa-road w-6 text-center"></i>
                <span class="ml-3">Roadmaps</span>
                <?php if ($pending_roadmaps > 0): ?>
                    <span class="ml-auto mr-2 bg-yellow-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $pending_roadmaps; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo url('admin/instructors.php'); ?>" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-users-cog w-6 text-center"></i>
                <span class="ml-3">Instructors</span>
            </a>
            <a href="<?php echo url('admin/feedback.php'); ?>" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-comments w-6 text-center"></i>
                <span class="ml-3">Feedback</span>
                <?php if ($recent_feedback > 0): ?>
                    <span class="ml-auto mr-2 bg-green-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $recent_feedback; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo url('admin/certificates.php'); ?>" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-certificate w-6 text-center"></i>
                <span class="ml-3">Certificates</span>
            </a>
        </nav>
        <div class="p-4 border-t border-gray-200">
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

    <!-- Main Content -->
    <main class="flex-1 p-8">
        <!-- Back Button -->
        <a href="<?php echo url('admin/roadmaps.php'); ?>" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
            <i class="fas fa-arrow-left mr-2"></i> Back to Roadmaps
        </a>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                <?php echo $_SESSION['success_message']; ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Roadmap Header -->
        <div class="bg-white rounded-xl shadow-sm p-8 mb-8">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center mb-4">
                        <h1 class="text-4xl font-bold text-gray-800 mr-4"><?= htmlspecialchars($roadmap['title']) ?></h1>
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
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">Instructor</p>
                            <p class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($roadmap['instructor_name']) ?></p>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($roadmap['instructor_email']) ?></p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">Price</p>
                            <p class="text-3xl font-bold text-indigo-600">$<?= number_format($roadmap['price'], 2) ?></p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">Created</p>
                            <p class="text-lg font-semibold text-gray-800"><?= date('F j, Y', strtotime($roadmap['created_at'])) ?></p>
                            <p class="text-sm text-gray-500"><?= date('g:i A', strtotime($roadmap['created_at'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="mt-6 border-t pt-6">
                <h3 class="text-xl font-semibold text-gray-700 mb-3">Description</h3>
                <div class="prose max-w-none">
                    <p class="text-gray-600 whitespace-pre-line"><?= nl2br(htmlspecialchars($roadmap['description'])) ?></p>
                </div>
            </div>

            <!-- Stats -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm text-blue-600">Total Phases</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $total_phases ?></p>
                </div>
                <div class="p-4 bg-green-50 rounded-lg">
                    <p class="text-sm text-green-600">Total Videos</p>
                    <p class="text-2xl font-bold text-gray-800"><?= array_sum(array_column($phases, 'video_count')) ?></p>
                </div>
                <div class="p-4 bg-purple-50 rounded-lg">
                    <p class="text-sm text-purple-600">Students Enrolled</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $enrollment_count ?></p>
                </div>
                <div class="p-4 bg-yellow-50 rounded-lg">
                    <p class="text-sm text-yellow-600">Average Rating</p>
                    <p class="text-2xl font-bold text-gray-800">
                        <?= $feedback_stats['avg_rating'] ? number_format($feedback_stats['avg_rating'], 1) : 'N/A' ?>
                        <span class="text-sm text-yellow-500">
                            (<?= $feedback_stats['total_feedback'] ?> reviews)
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Left Column: Phases -->
            <div>
                <div class="bg-white rounded-xl shadow-sm p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Phases (<?= $total_phases ?>)</h2>
                    <div class="space-y-4">
                        <?php 
                        $phase_index = 0;
                        foreach($phases as $phase_id => $phase): 
                        ?>
                            <div class="phase-card p-6 border border-gray-200 rounded-lg <?= ($phase_index < 2) ? 'border-l-green-500 bg-green-50' : 'border-l-indigo-500' ?>">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h4 class="font-bold text-lg text-gray-800">
                                            Phase <?= $phase['phase_order'] ?>: <?= htmlspecialchars($phase['title']) ?>
                                        </h4>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <?= $phase['video_count'] ?> video<?= $phase['video_count'] != 1 ? 's' : '' ?>
                                        </p>
                                    </div>
                                    <div class="text-right font-bold">
                                        <?php if ($phase_index < 2): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-unlock mr-1"></i> FREE
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                                                <i class="fas fa-lock mr-1"></i> PAID
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($phase_videos[$phase_id])): ?>
                                    <div class="mt-4">
                                        <p class="text-sm font-medium text-gray-700 mb-2">Videos:</p>
                                        <ul class="space-y-2">
                                            <?php 
                                            foreach($phase_videos[$phase_id] as $video):
                                                if (!empty(trim($video['title']))):
                                            ?>
                                                <li class="flex items-center text-sm text-gray-600">
                                                    <i class="fas fa-play-circle text-gray-400 mr-2"></i>
                                                    <span class="video-link" 
                                                          data-video-title="<?= htmlspecialchars($video['title'], ENT_QUOTES) ?>"
                                                          data-video-url="<?= htmlspecialchars($video['video_url'], ENT_QUOTES) ?>"
                                                          data-phase-title="<?= htmlspecialchars($phase['title'], ENT_QUOTES) ?>"
                                                          data-phase-order="<?= $phase['phase_order'] ?>">
                                                        <?= htmlspecialchars($video['title']) ?>
                                                    </span>
                                                </li>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php 
                        $phase_index++;
                        endforeach; 
                        ?>
                        
                        <?php if (empty($phases)): ?>
                            <p class="text-gray-500 text-center py-8">No phases have been added to this roadmap yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Actions & Admin Tools -->
            <div>
                <!-- Admin Actions -->
                <div class="bg-white rounded-xl shadow-sm p-8 mb-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Admin Actions</h2>
                    
                    <div class="space-y-4">
                        <form method="POST" class="space-y-4" id="statusForm">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Update Status</label>
                                <div class="flex flex-wrap gap-3">
                                    <button type="submit" name="approve" value="1"
                                            class="flex-1 px-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center">
                                        <i class="fas fa-check mr-2"></i> Approve
                                    </button>
                                    <button type="submit" name="reject" value="1"
                                            class="flex-1 px-6 py-3 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center">
                                        <i class="fas fa-times mr-2"></i> Reject
                                    </button>
                                    <button type="submit" name="request_changes" value="1"
                                            class="flex-1 px-6 py-3 bg-yellow-600 text-white font-semibold rounded-lg hover:bg-yellow-700 transition-colors flex items-center justify-center">
                                        <i class="fas fa-edit mr-2"></i> Request Changes
                                    </button>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Add Notes (Optional)</label>
                                <textarea name="admin_notes" rows="3" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                          placeholder="Add any notes for the instructor..."><?= htmlspecialchars($stored_admin_notes) ?></textarea>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="bg-white rounded-xl shadow-sm p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Quick Stats</h2>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <p class="text-sm text-gray-600">Free Phases</p>
                                <p class="text-lg font-semibold text-green-600">2</p>
                            </div>
                            <div class="text-green-500">
                                <i class="fas fa-unlock text-2xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <p class="text-sm text-gray-600">Paid Phases</p>
                                <p class="text-lg font-semibold text-indigo-600"><?= max(0, $total_phases - 2) ?></p>
                            </div>
                            <div class="text-indigo-500">
                                <i class="fas fa-lock text-2xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <p class="text-sm text-gray-600">Estimated Revenue</p>
                                <p class="text-lg font-semibold text-yellow-600">
                                    $<?= number_format($roadmap['price'] * $enrollment_count, 2) ?>
                                </p>
                            </div>
                            <div class="text-yellow-500">
                                <i class="fas fa-dollar-sign text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Video Modal -->
<div id="videoModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h3 id="modalVideoTitle" class="text-xl font-bold text-gray-800"></h3>
                <p id="modalPhaseInfo" class="text-sm text-gray-600 mt-1"></p>
            </div>
            <button class="close-btn" onclick="closeVideoModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="video-container">
                <video id="videoPlayer" controls>
                    Your browser does not support the video tag.
                </video>
            </div>
            <div class="mt-4">
                <p class="text-sm text-gray-600">Video URL:</p>
                <p id="videoUrl" class="text-sm text-blue-600 break-words mt-1"></p>
                <div id="videoError" class="mt-2 p-3 bg-red-50 border border-red-200 text-red-700 rounded hidden">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <span id="errorMessage">Unable to load video.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Simple video modal functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Add click handlers to all video links
        const videoLinks = document.querySelectorAll('.video-link');
        videoLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const videoTitle = this.getAttribute('data-video-title');
                const videoUrl = this.getAttribute('data-video-url');
                const phaseTitle = this.getAttribute('data-phase-title');
                const phaseOrder = this.getAttribute('data-phase-order');
                
                openVideoModal(videoTitle, videoUrl, phaseTitle, phaseOrder);
            });
        });
        
        // Add confirmation for reject and request changes
        const rejectBtn = document.querySelector('button[name="reject"]');
        const changesBtn = document.querySelector('button[name="request_changes"]');
        
        if (rejectBtn) {
            rejectBtn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to reject this roadmap? This will notify the instructor.')) {
                    e.preventDefault();
                }
            });
        }
        
        if (changesBtn) {
            changesBtn.addEventListener('click', function(e) {
                if (!confirm('Request changes from the instructor? They will need to resubmit.')) {
                    e.preventDefault();
                }
            });
        }
    });
    
    function openVideoModal(videoTitle, videoUrl, phaseTitle, phaseOrder) {
        console.log('Opening video modal with URL:', videoUrl);
        
        const modal = document.getElementById('videoModal');
        const modalVideoTitle = document.getElementById('modalVideoTitle');
        const modalPhaseInfo = document.getElementById('modalPhaseInfo');
        const videoPlayer = document.getElementById('videoPlayer');
        const videoUrlElement = document.getElementById('videoUrl');
        const videoError = document.getElementById('videoError');
        const errorMessage = document.getElementById('errorMessage');
        
        // Reset error
        videoError.classList.add('hidden');
        
        // Set modal content
        modalVideoTitle.textContent = videoTitle;
        modalPhaseInfo.textContent = `Phase ${phaseOrder}: ${phaseTitle}`;
        videoUrlElement.innerHTML = `<a href="${videoUrl}" target="_blank" class="text-blue-600 hover:underline break-all">${videoUrl}</a>`;
        
        // Clear previous video
        videoPlayer.pause();
        videoPlayer.src = '';
        videoPlayer.innerHTML = '';
        
        // Try to load the video directly with the provided URL
        videoPlayer.src = videoUrl;
        videoPlayer.load();
        
        // Add error handler
        videoPlayer.onerror = function() {
            console.error('Failed to load video from:', videoUrl);
            errorMessage.textContent = `Unable to load video. Please check if the file exists at: ${videoUrl}`;
            videoError.classList.remove('hidden');
        };
        
        // Show the modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeVideoModal() {
        const modal = document.getElementById('videoModal');
        const videoPlayer = document.getElementById('videoPlayer');
        
        // Stop video
        if (videoPlayer) {
            videoPlayer.pause();
            videoPlayer.currentTime = 0;
        }
        
        // Hide modal
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
    
    // Close modal when clicking outside
    document.getElementById('videoModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeVideoModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeVideoModal();
        }
    });
</script>

</body>
</html>