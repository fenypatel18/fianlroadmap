<?php
// instructor/create_roadmap.php
session_start();

// Increase limits
// Increase these values as needed
ini_set('upload_max_filesize', '500M');  // Increase from 200M
ini_set('post_max_size', '510M');        // Increase from 210M
ini_set('max_execution_time', '300');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
requireInstructor();

$instructor_id = $_SESSION['user_id'];
$instructor_name = $_SESSION['name'] ?? 'Instructor';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $duration = $_POST['duration'] ?? '';
    $price = $_POST['price'] ?? '0.00';
    $phases = $_POST['phases'] ?? [];
    
    // Debug log
    error_log("=== FORM SUBMISSION ===");
    error_log("Title: $title");
    error_log("Phases count: " . count($phases));
    
    // Simple validation
    $errors = [];
    
    if (empty(trim($title))) {
        $errors[] = 'Roadmap title is required.';
    }
    
    if (empty(trim($description))) {
        $errors[] = 'Roadmap description is required.';
    }
    
    if (empty(trim($duration))) {
        $errors[] = 'Estimated duration is required.';
    }
    
    if (!is_numeric($price) || $price < 0) {
        $errors[] = 'Please enter a valid price (0 or greater).';
    }
    
    if (count($phases) < 2) {
        $errors[] = 'A roadmap must have at least two phases.';
    }
    
    // Validate phases and videos
    foreach ($phases as $phase_idx => $phase) {
        $phase_title = trim($phase['title'] ?? '');
        
        if (empty($phase_title)) {
            $errors[] = "Phase title is required for Phase " . ($phase_idx + 1) . ".";
        }
        
        // Check videos
        if (!isset($phase['videos']) || !is_array($phase['videos']) || count($phase['videos']) === 0) {
            $errors[] = "At least one video is required for Phase " . ($phase_idx + 1) . ".";
            continue;
        }
        
        // Validate each video title
        foreach ($phase['videos'] as $video_idx => $video) {
            $video_title = trim($video['title'] ?? '');
            
            if (empty($video_title)) {
                $errors[] = "Video title is required for Video " . ($video_idx + 1) . " in Phase " . ($phase_idx + 1) . ".";
            }
        }
    }
    
    // If no errors, process
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert roadmap
            $stmt = $pdo->prepare("
                INSERT INTO roadmaps (instructor_id, title, description, duration, price, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->execute([$instructor_id, $title, $description, $duration, $price]);
            $roadmap_id = $pdo->lastInsertId();
            error_log("Roadmap created with ID: $roadmap_id");
            
            // Insert phases
            foreach ($phases as $phase_idx => $phase) {
                $phase_title = trim($phase['title']);
                $phase_order = $phase_idx + 1;
                
                $stmt = $pdo->prepare("
                    INSERT INTO roadmap_phases (roadmap_id, title, phase_order, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$roadmap_id, $phase_title, $phase_order]);
                $phase_id = $pdo->lastInsertId();
                
                error_log("Phase created with ID: $phase_id");
                
                // Insert videos for this phase
                if (isset($phase['videos']) && is_array($phase['videos'])) {
                    foreach ($phase['videos'] as $video_idx => $video) {
                        $video_title = trim($video['title']);
                        
                        // Check if file was uploaded - USING SIMPLE STRUCTURE
                        if (!isset($_FILES['video_files']['name'][$phase_idx][$video_idx]) || 
                            empty($_FILES['video_files']['name'][$phase_idx][$video_idx])) {
                            throw new Exception("No video file uploaded for: $video_title");
                        }
                        
                        $file_name = $_FILES['video_files']['name'][$phase_idx][$video_idx];
                        $file_tmp = $_FILES['video_files']['tmp_name'][$phase_idx][$video_idx];
                        $file_error = $_FILES['video_files']['error'][$phase_idx][$video_idx];
                        $file_size = $_FILES['video_files']['size'][$phase_idx][$video_idx];
                        
                        if ($file_error !== UPLOAD_ERR_OK) {
                            throw new Exception("Upload error for video: $video_title (Error code: $file_error)");
                        }
                        
                        // Validate file type
                        $allowed_extensions = ['mp4', 'mpeg', 'mov', 'avi', 'wmv', 'webm'];
                        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        
                        if (!in_array($extension, $allowed_extensions)) {
                            throw new Exception("Invalid file type for: $video_title. Allowed: " . implode(', ', $allowed_extensions));
                        }
                        
                        // Validate file size (max 100MB)
                        $max_size = 100 * 1024 * 1024;
                        if ($file_size > $max_size) {
                            throw new Exception("File too large for: $video_title. Maximum size is 100MB.");
                        }
                        
                        // Create upload directory if not exists
                        $upload_dir = __DIR__ . '/../uploads/videos/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        // Generate unique filename
                        $unique_filename = uniqid('video_', true) . '_' . time() . '.' . $extension;
                        $destination = $upload_dir . $unique_filename;
                        
                        // Move uploaded file
                        if (!move_uploaded_file($file_tmp, $destination)) {
                            throw new Exception("Failed to save video file: $video_title");
                        }
                        
                        // Store relative path
                        $video_path = 'uploads/videos/' . $unique_filename;
                        $video_order = $video_idx + 1;
                        
                        // Insert video record
                        $stmt = $pdo->prepare("
                            INSERT INTO roadmap_videos (phase_id, title, video_url, video_order, created_at) 
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$phase_id, $video_title, $video_path, $video_order]);
                        
                        error_log("Video saved: $video_title");
                    }
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['toast_message'] = "🎉 Roadmap '$title' created successfully! It is now pending admin approval.";
            $_SESSION['toast_type'] = 'success';
            header('Location: create_roadmap.php');
            // Clear form data
            $form_data = [];
            $phases = [];
            exit();
            
        } catch (Exception $e) {
            // Rollback on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Error: " . $e->getMessage();
        }
    }
    
    // If there were errors, store them in session
    if (!empty($errors)) {
        $_SESSION['toast_message'] = implode('<br>', $errors);
        $_SESSION['toast_type'] = 'error';
        $_SESSION['form_data'] = $_POST; // Save form data for repopulation
    }
}

// Get toast message if exists
$toast_message = $_SESSION['toast_message'] ?? '';
$toast_type = $_SESSION['toast_type'] ?? '';
$form_data = $_SESSION['form_data'] ?? [];

// Clear session messages
unset($_SESSION['toast_message'], $_SESSION['toast_type'], $_SESSION['form_data']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Roadmap - SkillPath Builder</title>
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
        .phase-item {
            transition: all 0.3s ease;
        }
        .video-item {
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
            <h1 class="text-4xl font-bold text-gray-800">Create a New Roadmap</h1>
            <p class="text-gray-600 mt-2">Welcome, <?php echo htmlspecialchars($instructor_name); ?>!</p>
        </div>

        <!-- Toast Message -->
        <?php if ($toast_message): ?>
        <div id="toast-message" class="fixed top-4 right-4 z-50 max-w-md animate-fade-in">
            <div class="<?php echo $toast_type === 'success' 
                ? 'bg-green-100 border-green-400 text-green-800' 
                : 'bg-red-100 border-red-400 text-red-800'; ?> 
                border-l-4 p-4 rounded-lg shadow-lg flex items-center">
                <div class="flex-shrink-0">
                    <?php if ($toast_type === 'success'): ?>
                        <i class="fas fa-check-circle text-green-500 text-xl"></i>
                    <?php else: ?>
                        <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                    <?php endif; ?>
                </div>
                <div class="ml-3">
                    <p class="font-medium"><?php echo $toast_type === 'success' ? 'Success!' : 'Error!'; ?></p>
                    <p class="text-sm"><?php echo htmlspecialchars($toast_message); ?></p>
                </div>
                <button onclick="document.getElementById('toast-message').remove()" 
                        class="ml-auto text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <script>
            // Auto-remove toast after 5 seconds
            setTimeout(() => {
                const toast = document.getElementById('toast-message');
                if (toast) toast.remove();
            }, 5000);
        </script>
        <?php endif; ?>
        

        <!-- Main Form -->
        <form action="" method="POST" enctype="multipart/form-data" id="roadmap-form" class="space-y-8">
            
            <!-- Roadmap Details Card -->
            <div class="bg-white p-6 rounded-xl shadow-sm">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6 pb-3 border-b border-gray-200">
                    <i class="fas fa-info-circle text-indigo-600 mr-2"></i>Roadmap Details
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Roadmap Title *</label>
                        <input type="text" name="title" id="title" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="e.g., Full Stack Web Development"
                               value="<?php echo htmlspecialchars($form_data['title'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="duration" class="block text-sm font-medium text-gray-700 mb-1">Estimated Duration *</label>
                        <input type="text" name="duration" id="duration" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="e.g., 8 Weeks"
                               value="<?php echo htmlspecialchars($form_data['duration'] ?? ''); ?>">
                    </div>
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                        <textarea name="description" id="description" rows="4" required 
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                  placeholder="Describe what students will learn in this roadmap..."><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Price ($) *</label>
                        <input type="number" name="price" id="price" min="0" step="0.01" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               value="<?php echo htmlspecialchars($form_data['price'] ?? '0.00'); ?>">
                        <div class="mt-2 p-3 bg-green-50 rounded-lg border border-green-200">
                            <p class="text-sm text-green-700 font-medium">
                                <i class="fas fa-info-circle mr-1"></i>
                                Note: The first 2 phases are always FREE. Remaining phases unlock after payment.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Phases Section -->
            <div class="bg-white p-6 rounded-xl shadow-sm">
                <div class="flex justify-between items-center mb-6 pb-3 border-b border-gray-200">
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <i class="fas fa-layer-group text-indigo-600 mr-2"></i>Course Phases
                    </h2>
                    <button type="button" id="add-phase" 
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 flex items-center">
                        <i class="fas fa-plus mr-2"></i>Add Phase
                    </button>
                </div>

                <div id="phases-container" class="space-y-6">
                    <!-- Phases will be added here by JavaScript -->
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end pt-6">
                <button type="submit" id="submit-btn"
                        class="px-8 py-3 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 flex items-center">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Submit for Approval
                </button>
            </div>
        </form>
    </main>
</div>

<!-- JavaScript for dynamic form -->
<script>
    // JavaScript code remains exactly the same...
    document.addEventListener('DOMContentLoaded', function () {
        const phasesContainer = document.getElementById('phases-container');
        const addPhaseBtn = document.getElementById('add-phase');
        let phaseCounter = 0;
        let videoCounters = {};
        const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB

        // Function to repopulate form data from PHP session
        function repopulateFormData() {
            const formData = <?php echo json_encode($form_data['phases'] ?? []); ?>;
            
            if (formData && formData.length > 0) {
                formData.forEach((phase, index) => {
                    addPhase(index, phase.title || '', phase.videos || []);
                });
            } else {
                // Add initial 2 phases
                addPhase(0);
                addPhase(1);
            }
        }

        // Initialize form
        repopulateFormData();

        // Function to add a new phase
        function addPhase(index = null, title = '', videos = []) {
            if (index === null) {
                index = phaseCounter;
            }
            
            phaseCounter = Math.max(phaseCounter, index + 1);
            videoCounters[index] = videos.length || 0;
            
            const phaseHTML = `
                <div class="phase-item border border-gray-200 rounded-xl overflow-hidden" id="phase-${index}">
                    <div class="bg-indigo-50 px-6 py-4 border-b border-indigo-100 flex justify-between items-center">
                        <div class="flex items-center">
                            <i class="fas fa-grip-vertical text-indigo-400 mr-3"></i>
                            <h3 class="text-lg font-semibold text-gray-800">Phase ${index + 1}</h3>
                        </div>
                        <button type="button" class="remove-phase-btn px-3 py-1 bg-red-100 text-red-600 rounded-lg text-sm hover:bg-red-200 flex items-center ${index < 2 ? 'hidden' : ''}" data-phase="${index}">
                            <i class="fas fa-trash mr-1"></i> Remove
                        </button>
                    </div>
                    <div class="p-6">
                        <input type="hidden" name="phases[${index}][order]" value="${index + 1}">
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phase Title *</label>
                            <input type="text" name="phases[${index}][title]" required 
                                   class="phase-title-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                   placeholder="e.g., Introduction to HTML & CSS"
                                   value="${title.replace(/"/g, '&quot;')}">
                        </div>
                        
                        <!-- Videos Container -->
                        <div id="videos-container-${index}" class="space-y-4 mb-4">
                            <!-- Videos will be added here -->
                        </div>
                        
                        <button type="button" class="add-video-btn px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 flex items-center" data-phase="${index}">
                            <i class="fas fa-video mr-2"></i> Add Video
                        </button>
                    </div>
                </div>
            `;
            
            phasesContainer.insertAdjacentHTML('beforeend', phaseHTML);
            
            // Add event listeners for this phase
            const phaseElement = document.getElementById(`phase-${index}`);
            phaseElement.querySelector('.remove-phase-btn').addEventListener('click', function() {
                removePhase(index);
            });
            
            phaseElement.querySelector('.add-video-btn').addEventListener('click', function() {
                addVideo(index);
            });
            
            // Add videos from saved data or add one default video
            const videosContainer = document.getElementById(`videos-container-${index}`);
            if (videos.length > 0) {
                videos.forEach((video, videoIndex) => {
                    addVideoToContainer(videosContainer, index, videoIndex, video.title || '');
                });
            } else {
                addVideoToContainer(videosContainer, index, 0);
            }
        }

        // Function to add a video to a specific phase
        function addVideo(phaseIndex) {
            const videosContainer = document.getElementById(`videos-container-${phaseIndex}`);
            const videoIndex = videoCounters[phaseIndex] || 0;
            addVideoToContainer(videosContainer, phaseIndex, videoIndex);
            videoCounters[phaseIndex] = videoIndex + 1;
        }

        // Function to add video HTML to container
        function addVideoToContainer(container, phaseIndex, videoIndex, title = '') {
            if (!videoCounters[phaseIndex]) {
                videoCounters[phaseIndex] = 0;
            }
            
            const videoHTML = `
                <div class="video-item p-4 border border-gray-200 rounded-lg bg-gray-50" id="video-${phaseIndex}-${videoIndex}">
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex items-center">
                            <i class="fas fa-play-circle text-indigo-500 mr-2"></i>
                            <span class="text-sm font-medium text-gray-700">Video ${videoIndex + 1} *</span>
                        </div>
                        <button type="button" class="remove-video-btn text-red-500 hover:text-red-700" data-phase="${phaseIndex}" data-video="${videoIndex}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Video Title *</label>
                            <input type="text" name="phases[${phaseIndex}][videos][${videoIndex}][title]" required 
                                   class="video-title-input w-full px-3 py-2 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                   placeholder="e.g., Introduction to HTML"
                                   value="${title.replace(/"/g, '&quot;')}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Video File *</label>
                            <div class="relative">
                                <input type="file" name="video_files[${phaseIndex}][${videoIndex}]" required 
                                       accept="video/*" 
                                       class="video-file-input w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Max: 100MB • MP4, MOV, AVI, WMV, WEBM</p>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', videoHTML);
            
            // Add event listeners
            const videoElement = document.getElementById(`video-${phaseIndex}-${videoIndex}`);
            
            // File size validation
            const fileInput = videoElement.querySelector('.video-file-input');
            fileInput.addEventListener('change', function() {
                if (this.files[0] && this.files[0].size > MAX_FILE_SIZE) {
                    alert('File size exceeds 100MB limit. Please choose a smaller file.');
                    this.value = '';
                }
            });
            
            // Remove button listener
            videoElement.querySelector('.remove-video-btn').addEventListener('click', function() {
                removeVideo(phaseIndex, videoIndex);
            });
        }

        // Function to remove a phase
        function removePhase(phaseIndex) {
            if (phaseIndex >= 2) {
                const phase = document.getElementById(`phase-${phaseIndex}`);
                if (phase) {
                    phase.remove();
                    reindexPhases();
                }
            }
        }

        // Function to remove a video
        function removeVideo(phaseIndex, videoIndex) {
            const videoDiv = document.getElementById(`video-${phaseIndex}-${videoIndex}`);
            if (videoDiv) {
                videoDiv.remove();
                reindexVideos(phaseIndex);
            }
        }

        // Function to reindex all phases after removal
        function reindexPhases() {
            const phases = phasesContainer.querySelectorAll('.phase-item');
            const newVideoCounters = {};
            
            phases.forEach((phase, newIndex) => {
                const oldIndex = parseInt(phase.id.replace('phase-', ''));
                phase.id = `phase-${newIndex}`;
                
                // Update phase number display
                const title = phase.querySelector('h3');
                title.textContent = `Phase ${newIndex + 1}`;
                
                // Update hidden order input
                const orderInput = phase.querySelector('input[name$="[order]"]');
                orderInput.value = newIndex + 1;
                orderInput.name = `phases[${newIndex}][order]`;
                
                // Update phase title input
                const titleInput = phase.querySelector('.phase-title-input');
                titleInput.name = `phases[${newIndex}][title]`;
                
                // Update buttons
                const removeBtn = phase.querySelector('.remove-phase-btn');
                removeBtn.setAttribute('data-phase', newIndex);
                if (newIndex < 2) {
                    removeBtn.classList.add('hidden');
                } else {
                    removeBtn.classList.remove('hidden');
                }
                
                // Update add video button
                const addVideoBtn = phase.querySelector('.add-video-btn');
                addVideoBtn.setAttribute('data-phase', newIndex);
                
                // Update videos container ID
                const videosContainer = phase.querySelector(`[id^="videos-container-"]`);
                videosContainer.id = `videos-container-${newIndex}`;
                
                // Reindex videos in this phase and count them
                reindexVideos(newIndex);
                const videoItems = videosContainer.querySelectorAll('.video-item');
                newVideoCounters[newIndex] = videoItems.length;
            });
            
            // Update video counters
            videoCounters = newVideoCounters;
            phaseCounter = phases.length;
        }

        // Function to reindex videos in a phase
        function reindexVideos(phaseIndex) {
            const videosContainer = document.getElementById(`videos-container-${phaseIndex}`);
            if (!videosContainer) return;
            
            const videos = videosContainer.querySelectorAll('.video-item');
            videos.forEach((video, newIndex) => {
                video.id = `video-${phaseIndex}-${newIndex}`;
                
                // Update video number display
                const titleSpan = video.querySelector('span');
                titleSpan.textContent = `Video ${newIndex + 1} *`;
                
                // Update video title input
                const titleInput = video.querySelector('.video-title-input');
                titleInput.name = `phases[${phaseIndex}][videos][${newIndex}][title]`;
                
                // Update video file input - IMPORTANT: Simple array structure
                const fileInput = video.querySelector('.video-file-input');
                fileInput.name = `video_files[${phaseIndex}][${newIndex}]`;
                
                // Update remove button
                const removeBtn = video.querySelector('.remove-video-btn');
                removeBtn.setAttribute('data-phase', phaseIndex);
                removeBtn.setAttribute('data-video', newIndex);
            });
            
            videoCounters[phaseIndex] = videos.length;
        }

        // Add phase button click handler
        addPhaseBtn.addEventListener('click', function() {
            addPhase();
        });

        // Form validation before submit
        document.getElementById('roadmap-form').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submit-btn');
            
            // Basic validation
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const duration = document.getElementById('duration').value.trim();
            const price = document.getElementById('price').value.trim();
            
            let errors = [];
            
            if (!title) errors.push('Roadmap title is required');
            if (!description) errors.push('Roadmap description is required');
            if (!duration) errors.push('Estimated duration is required');
            if (!price || isNaN(price) || parseFloat(price) < 0) errors.push('Please enter a valid price');
            
            // Check phase count
            const phaseCount = phasesContainer.querySelectorAll('.phase-item').length;
            if (phaseCount < 2) errors.push('At least 2 phases are required');
            
            // Check each phase has a title and at least one video
            for (let i = 0; i < phaseCount; i++) {
                const phaseTitleInput = document.querySelector(`#phase-${i} .phase-title-input`);
                if (phaseTitleInput && !phaseTitleInput.value.trim()) {
                    errors.push(`Phase ${i + 1} title is required`);
                }
                
                const videosContainer = document.getElementById(`videos-container-${i}`);
                if (videosContainer) {
                    const videoCount = videosContainer.querySelectorAll('.video-item').length;
                    if (videoCount === 0) {
                        errors.push(`Phase ${i + 1} must have at least one video`);
                    } else {
                        // Check each video has title
                        const videoTitles = videosContainer.querySelectorAll('.video-title-input');
                        const videoFiles = videosContainer.querySelectorAll('.video-file-input');
                        
                        videoTitles.forEach((input, index) => {
                            if (!input.value.trim()) {
                                errors.push(`Video ${index + 1} title in Phase ${i + 1} is required`);
                            }
                        });
                        
                        videoFiles.forEach((input, index) => {
                            if (!input.files || input.files.length === 0) {
                                errors.push(`Video ${index + 1} file in Phase ${i + 1} is required`);
                            } else if (input.files[0].size > MAX_FILE_SIZE) {
                                errors.push(`Video ${index + 1} file in Phase ${i + 1} exceeds 100MB limit`);
                            }
                        });
                    }
                }
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n• ' + errors.join('\n• '));
                submitBtn.disabled = false;
                return;
            }
            
            // Disable submit button to prevent double submission
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating Roadmap...';
            
            // Allow form to submit
            return true;
        });
    });
</script>
</body>
</html>