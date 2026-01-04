<?php
// instructor/view_roadmap.php
session_start();

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
requireInstructor();

$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['name'] ?? 'Instructor';
$roadmap_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if (!$roadmap_id) {
    $_SESSION['error_message'] = "No roadmap specified.";
    header('Location: my_roadmaps.php');
    exit();
}

// Fetch roadmap details
try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.name as instructor_name,
               (SELECT COUNT(*) FROM enrollments WHERE roadmap_id = r.id) as student_count
        FROM roadmaps r
        JOIN users u ON r.instructor_id = u.id
        WHERE r.id = ? AND r.instructor_id = ?
    ");
    $stmt->execute([$roadmap_id, $instructor_id]);
    $roadmap = $stmt->fetch();
    
    if (!$roadmap) {
        $_SESSION['error_message'] = "Roadmap not found or you don't have permission to view it.";
        header('Location: my_roadmaps.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header('Location: my_roadmaps.php');
    exit();
}

// Fetch phases and videos
try {
    $stmt = $pdo->prepare("
        SELECT p.id as phase_id, p.title as phase_title, p.phase_order,
               v.id as video_id, v.title as video_title, v.video_url, v.video_order
        FROM roadmap_phases p
        LEFT JOIN roadmap_videos v ON p.id = v.phase_id
        WHERE p.roadmap_id = ?
        ORDER BY p.phase_order ASC, v.video_order ASC
    ");
    $stmt->execute([$roadmap_id]);
    $phases_data = $stmt->fetchAll();
    
    // Organize data
    $phases = [];
    $total_videos = 0;
    foreach ($phases_data as $row) {
        $phase_id = $row['phase_id'];
        if (!isset($phases[$phase_id])) {
            $phases[$phase_id] = [
                'id' => $phase_id,
                'title' => $row['phase_title'],
                'order' => $row['phase_order'],
                'videos' => []
            ];
        }
        if ($row['video_id']) {
            $phases[$phase_id]['videos'][] = [
                'id' => $row['video_id'],
                'title' => $row['video_title'],
                'url' => $row['video_url'],
                'order' => $row['video_order']
            ];
            $total_videos++;
        }
    }
    
    // Get total phases count
    $total_phases = count($phases);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error loading roadmap data: " . $e->getMessage();
    header('Location: my_roadmaps.php');
    exit();
}

// Check for messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Function to get correct video URL
function getFullVideoUrl($video_path) {
    // Remove leading slash if it exists
    $video_path = ltrim($video_path, '/');
    
    // Get base URL
    $base_url = rtrim(url(''), '/');
    
    // Construct full URL
    return $base_url . '/' . $video_path;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Roadmap - <?php echo htmlspecialchars($roadmap['title']); ?> - SkillPath Builder</title>
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
            background-color: #f9fafb;
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
        
        /* Status badge styles */
        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-changed { background-color: #fef3c7; color: #92400e; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .status-pending { background-color: #e0e7ff; color: #3730a3; }
        
        /* Phase card styles */
        .phase-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        .phase-card:hover {
            transform: translateX(4px);
        }
        .free-phase {
            border-left-color: #10b981;
        }
        .paid-phase {
            border-left-color: #6366f1;
        }
        
        /* Video modal styles */
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
            max-width: 900px;
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
        
        /* Video link styles */
        .video-link {
            cursor: pointer;
            transition: color 0.2s;
            color: #3b82f6;
        }
        .video-link:hover {
            color: #1d4ed8;
            text-decoration: underline;
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

        <!-- Back Button -->
        <a href="my_roadmaps.php" class="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6">
            <i class="fas fa-arrow-left mr-2"></i> Back to My Roadmaps
        </a>

        <!-- Roadmap Header -->
        <div class="bg-white rounded-xl shadow-sm p-8 mb-8">
            <div class="flex justify-between items-start mb-6">
                <div class="flex-1">
                    <div class="flex items-center mb-4">
                        <h1 class="text-4xl font-bold text-gray-800 mr-4"><?php echo htmlspecialchars($roadmap['title']); ?></h1>
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
                            <i class="fas 
                                <?php 
                                if ($roadmap['status'] == 'approved') echo 'fa-check';
                                elseif ($roadmap['status'] == 'changed') echo 'fa-edit';
                                elseif ($roadmap['status'] == 'rejected') echo 'fa-times';
                                else echo 'fa-clock';
                                ?> 
                                mr-2">
                            </i>
                            <?php echo ucfirst($roadmap['status']); ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">Price</p>
                            <p class="text-3xl font-bold text-indigo-600">$<?php echo number_format($roadmap['price'], 2); ?></p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">Duration</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($roadmap['duration']); ?></p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">Phases</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo $total_phases; ?></p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">Videos</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo $total_videos; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="ml-6 flex space-x-2">
                    <?php if ($roadmap['status'] != 'rejected'): ?>
                        <a href="edit_roadmap.php?id=<?php echo $roadmap_id; ?>" 
                           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                            <i class="fas fa-edit mr-2"></i>
                            Edit
                        </a>
                    <?php endif; ?>
                    <a href="my_roadmaps.php?duplicate=<?php echo $roadmap_id; ?>" 
                       class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 flex items-center">
                        <i class="fas fa-copy mr-2"></i>
                        Duplicate
                    </a>
                </div>
            </div>

            <!-- Description -->
            <div class="mt-6 border-t pt-6">
                <h3 class="text-xl font-semibold text-gray-700 mb-3">Description</h3>
                <div class="prose max-w-none">
                    <p class="text-gray-600 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($roadmap['description'])); ?></p>
                </div>
            </div>

            <!-- Stats -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm text-blue-600">Created Date</p>
                    <p class="text-lg font-bold text-gray-800"><?php echo date('F j, Y', strtotime($roadmap['created_at'])); ?></p>
                    <p class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($roadmap['created_at'])); ?></p>
                </div>
                <div class="p-4 bg-green-50 rounded-lg">
                    <p class="text-sm text-green-600">Students Enrolled</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $roadmap['student_count']; ?></p>
                </div>
                <div class="p-4 bg-yellow-50 rounded-lg">
                    <p class="text-sm text-yellow-600">Estimated Revenue</p>
                    <p class="text-2xl font-bold text-gray-800">
                        $<?php echo number_format($roadmap['price'] * $roadmap['student_count'], 2); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Phases Section -->
        <div class="bg-white rounded-xl shadow-sm p-8">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Course Phases (<?php echo $total_phases; ?>)</h2>
                    <p class="text-gray-600 mt-1">The first 2 phases are FREE, remaining phases unlock after payment</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Total Videos: <span class="font-bold"><?php echo $total_videos; ?></span></p>
                </div>
            </div>

            <div class="space-y-6">
                <?php 
                $phase_index = 0;
                foreach ($phases as $phase): 
                ?>
                    <div class="phase-card p-6 border border-gray-200 rounded-lg <?php echo ($phase_index < 2) ? 'free-phase bg-green-50' : 'paid-phase bg-indigo-50'; ?>">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="font-bold text-xl text-gray-800">
                                    Phase <?php echo $phase['order']; ?>: <?php echo htmlspecialchars($phase['title']); ?>
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    <?php echo count($phase['videos']); ?> video<?php echo count($phase['videos']) != 1 ? 's' : ''; ?>
                                </p>
                            </div>
                            <div class="text-right">
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
                        
                        <?php if (!empty($phase['videos'])): ?>
                            <div class="mt-4">
                                <p class="text-sm font-medium text-gray-700 mb-3">Videos:</p>
                                <div class="space-y-2">
                                    <?php 
                                    foreach ($phase['videos'] as $video): 
                                        $full_video_url = getFullVideoUrl($video['url']);
                                    ?>
                                        <div class="flex items-center justify-between p-3 bg-white rounded-lg border border-gray-200 hover:bg-gray-50">
                                            <div class="flex items-center">
                                                <i class="fas fa-play-circle text-gray-400 mr-3"></i>
                                                <div>
                                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($video['title']); ?></p>
                                                    <p class="text-xs text-gray-500 mt-1">Video <?php echo $video['order']; ?></p>
                                                </div>
                                            </div>
                                            <button class="video-link text-sm text-blue-600 hover:text-blue-800 flex items-center"
                                                    onclick="openVideoModal('<?php echo htmlspecialchars(addslashes($video['title'])); ?>', 
                                                                           '<?php echo htmlspecialchars(addslashes($full_video_url)); ?>',
                                                                           '<?php echo htmlspecialchars(addslashes($phase['title'])); ?>',
                                                                           <?php echo $phase['order']; ?>)">
                                                <i class="fas fa-eye mr-1"></i> Preview
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                                <p class="text-gray-500 text-center">No videos added to this phase yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php 
                $phase_index++;
                endforeach; 
                ?>
                
                <?php if (empty($phases)): ?>
                    <div class="p-8 text-center">
                        <i class="fas fa-layer-group text-gray-300 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900">No phases added yet</h3>
                        <p class="text-gray-600 mt-1">Edit this roadmap to add phases and videos</p>
                    </div>
                <?php endif; ?>
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
    // Video Modal Functions
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