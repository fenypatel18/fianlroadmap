<?php
// student/view_roadmap.php

// Start the session to manage user state.
session_start();

// --- SETUP & SECURITY ---
require_once __DIR__ . '/../auth/middleware.php';
requireStudent();
require_once __DIR__ . '/../config/db.php';

$BASE_PATH = '/fianlroadmap';
$student_id = $_SESSION['user_id'];
$MAX_FREE_PHASES = 2; // First 2 phases are free

// Get selected phase from URL or default to first phase
$selected_phase_index = isset($_GET['phase']) ? (int)$_GET['phase'] - 1 : 0;

// --- AJAX HANDLER FOR PROGRESS UPDATES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'mark_video_complete' && isset($input['video_id'])) {
        $video_id = $input['video_id'];
        
        // First check if user is enrolled or video is in free phase
        $stmt = $pdo->prepare("
            SELECT rp.roadmap_id, rp.phase_order
            FROM roadmap_videos rv 
            JOIN roadmap_phases rp ON rv.phase_id = rp.id 
            WHERE rv.id = ?
        ");
        $stmt->execute([$video_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $roadmap_id = $result['roadmap_id'];
            $phase_order = $result['phase_order'];
            
            // Check if user is enrolled
            $enrollment_stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND roadmap_id = ?");
            $enrollment_stmt->execute([$student_id, $roadmap_id]);
            $is_enrolled = $enrollment_stmt->fetch() ? true : false;
            
            // Allow progress tracking only if enrolled OR video is in free phase (phase_order <= 2)
            if ($is_enrolled || $phase_order <= $MAX_FREE_PHASES) {
                try {
                    // Mark video as completed for this student
                    $stmt = $pdo->prepare("
                        INSERT INTO progress (student_id, video_id, completed_at, completed) 
                        VALUES (?, ?, NOW(), TRUE)
                        ON DUPLICATE KEY UPDATE completed_at = NOW(), completed = TRUE
                    ");
                    $stmt->execute([$student_id, $video_id]);
                    
                    // Update enrollment last watched if enrolled
                    if ($is_enrolled) {
                        $stmt = $pdo->prepare("
                            UPDATE enrollments 
                            SET last_watched_video_id = ?
                            WHERE student_id = ? AND roadmap_id = ?
                        ");
                        $stmt->execute([$video_id, $student_id, $roadmap_id]);
                    }
                    
                    echo json_encode(['status' => 'success', 'message' => 'Progress saved']);
                } catch (Exception $e) {
                    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Enroll to track progress for this phase']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Video not found']);
        }
        exit();
    }
    
    if ($action === 'get_progress') {
        $roadmap_id = $input['roadmap_id'] ?? 0;
        
        // Check enrollment
        $enrollment_stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND roadmap_id = ?");
        $enrollment_stmt->execute([$student_id, $roadmap_id]);
        $is_enrolled = $enrollment_stmt->fetch() ? true : false;
        
        // For non-enrolled users, only count free phase videos
        if ($is_enrolled) {
            // Get ALL videos count
            $stmt = $pdo->prepare("
                SELECT COUNT(rv.id) as total_videos
                FROM roadmap_videos rv
                JOIN roadmap_phases rp ON rv.phase_id = rp.id
                WHERE rp.roadmap_id = ?
            ");
            $stmt->execute([$roadmap_id]);
            $total_videos = $stmt->fetch(PDO::FETCH_ASSOC)['total_videos'];
            
            // Get ALL completed videos
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT p.video_id) as completed_videos
                FROM progress p
                JOIN roadmap_videos rv ON p.video_id = rv.id
                JOIN roadmap_phases rp ON rv.phase_id = rp.id
                WHERE p.student_id = ? AND rp.roadmap_id = ? AND p.completed = TRUE
            ");
            $stmt->execute([$student_id, $roadmap_id]);
            $completed_videos = $stmt->fetch(PDO::FETCH_ASSOC)['completed_videos'];
        } else {
            // Only FREE phase videos (phase_order <= 2)
            $stmt = $pdo->prepare("
                SELECT COUNT(rv.id) as total_videos
                FROM roadmap_videos rv
                JOIN roadmap_phases rp ON rv.phase_id = rp.id
                WHERE rp.roadmap_id = ? AND rp.phase_order <= ?
            ");
            $stmt->execute([$roadmap_id, $MAX_FREE_PHASES]);
            $total_videos = $stmt->fetch(PDO::FETCH_ASSOC)['total_videos'];
            
            // Only completed videos from FREE phases
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT p.video_id) as completed_videos
                FROM progress p
                JOIN roadmap_videos rv ON p.video_id = rv.id
                JOIN roadmap_phases rp ON rv.phase_id = rp.id
                WHERE p.student_id = ? AND rp.roadmap_id = ? 
                AND p.completed = TRUE AND rp.phase_order <= ?
            ");
            $stmt->execute([$student_id, $roadmap_id, $MAX_FREE_PHASES]);
            $completed_videos = $stmt->fetch(PDO::FETCH_ASSOC)['completed_videos'];
        }
        
        $progress_percentage = $total_videos > 0 ? round(($completed_videos / $total_videos) * 100) : 0;
        
        echo json_encode([
            'status' => 'success',
            'completed' => $completed_videos,
            'total' => $total_videos,
            'percentage' => $progress_percentage,
            'is_enrolled' => $is_enrolled
        ]);
        exit();
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit();
}

// --- ORIGINAL CODE CONTINUES ---
// --- FETCH STUDENT PROFILE DATA ---
$student_name = $_SESSION['name'];

$stmt = $pdo->prepare("SELECT id, name, email, profile_picture FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $student['profile_picture'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($student['name']) . '&background=random';

// --- DATA VALIDATION ---
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: explore_roadmaps.php');
    exit();
}
$roadmap_id = $_GET['id'];

// --- DATA FETCHING: ROADMAP ---
$stmt = $pdo->prepare("
    SELECT 
        r.id,
        r.title, 
        r.description, 
        r.price, 
        u.name AS instructor_name,
        u.email AS instructor_email,
        COUNT(DISTINCT rp.id) as phase_count,
        COUNT(DISTINCT e.id) as enrollment_count
    FROM roadmaps r
    JOIN users u ON r.instructor_id = u.id 
    LEFT JOIN roadmap_phases rp ON r.id = rp.roadmap_id
    LEFT JOIN enrollments e ON r.id = e.roadmap_id
    WHERE r.id = ? AND r.status = 'approved'
    GROUP BY r.id
");
$stmt->execute([$roadmap_id]);
$roadmap = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$roadmap) {
    die("Roadmap not found or not available.");
}

// --- DATA FETCHING: PHASES ---
$phases_stmt = $pdo->prepare("SELECT id, title, phase_order FROM roadmap_phases WHERE roadmap_id = ? ORDER BY phase_order ASC");
$phases_stmt->execute([$roadmap_id]);
$phases = $phases_stmt->fetchAll(PDO::FETCH_ASSOC);

// Validate selected phase index
if ($selected_phase_index < 0) $selected_phase_index = 0;
if ($selected_phase_index >= count($phases)) $selected_phase_index = count($phases) - 1;

// Fetch videos for each phase
$total_videos = 0;
$total_duration_minutes = 0;
$free_phases_videos = 0;
$paid_phases_videos = 0;

foreach ($phases as &$phase) {
    $videos_stmt = $pdo->prepare("SELECT id, title, video_url, video_order FROM roadmap_videos WHERE phase_id = ? ORDER BY video_order ASC");
    $videos_stmt->execute([$phase['id']]);
    $videos = $videos_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add simulated durations to videos (5-30 minutes each)
    foreach ($videos as &$video) {
        $video['duration'] = rand(5, 30);
        $video['hours'] = floor($video['duration'] / 60);
        $video['minutes'] = $video['duration'] % 60;
        $total_duration_minutes += $video['duration'];
        
        // Count free vs paid phase videos
        if ($phase['phase_order'] <= $MAX_FREE_PHASES) {
            $free_phases_videos++;
        } else {
            $paid_phases_videos++;
        }
        
        // Fix video URL
        if (strpos($video['video_url'], 'uploads/videos/') === 0) {
            $video['full_url'] = $BASE_PATH . '/' . $video['video_url'];
        } else {
            $video['full_url'] = $video['video_url'];
        }
    }
    unset($video);
    
    $phase['videos'] = $videos;
    $phase['total_duration'] = array_sum(array_column($videos, 'duration'));
    $phase['total_videos'] = count($videos);
    $phase['hours'] = floor($phase['total_duration'] / 60);
    $phase['minutes'] = $phase['total_duration'] % 60;
    $total_videos += $phase['total_videos'];
    $phase['is_free'] = $phase['phase_order'] <= $MAX_FREE_PHASES;
}
unset($phase);

// Calculate total hours for roadmap
$total_hours = floor($total_duration_minutes / 60);
$total_minutes = $total_duration_minutes % 60;

// Check if user is enrolled in this roadmap
$enrollment_stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND roadmap_id = ?");
$enrollment_stmt->execute([$student_id, $roadmap_id]);
$is_enrolled = $enrollment_stmt->fetch() ? true : false;

// Get student's progress and completed videos
$student_progress = ['percentage' => 0, 'completed' => 0, 'total' => 0];
$completed_videos = [];

if ($is_enrolled) {
    // Get ALL progress
    $progressStmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT p.video_id) as completed_videos,
            COUNT(DISTINCT rv.id) as total_videos
        FROM roadmap_videos rv
        JOIN roadmap_phases rp ON rv.phase_id = rp.id
        LEFT JOIN progress p ON rv.id = p.video_id AND p.student_id = ? AND p.completed = TRUE
        WHERE rp.roadmap_id = ?
    ");
    $progressStmt->execute([$student_id, $roadmap_id]);
    $progress = $progressStmt->fetch();
    
    if ($progress['total_videos'] > 0) {
        $student_progress['percentage'] = ($progress['completed_videos'] / $progress['total_videos']) * 100;
        $student_progress['completed'] = $progress['completed_videos'];
        $student_progress['total'] = $progress['total_videos'];
    }
    
    // Get ALL completed video IDs
    $completedStmt = $pdo->prepare("
        SELECT video_id 
        FROM progress 
        WHERE student_id = ? 
        AND video_id IN (
            SELECT rv.id 
            FROM roadmap_videos rv
            JOIN roadmap_phases rp ON rv.phase_id = rp.id
            WHERE rp.roadmap_id = ?
        )
        AND completed = TRUE
    ");
    $completedStmt->execute([$student_id, $roadmap_id]);
    $completed_videos = $completedStmt->fetchAll(PDO::FETCH_COLUMN, 0);
} else {
    // Only FREE phase progress for non-enrolled users
    $progressStmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT p.video_id) as completed_videos,
            COUNT(DISTINCT rv.id) as total_videos
        FROM roadmap_videos rv
        JOIN roadmap_phases rp ON rv.phase_id = rp.id
        LEFT JOIN progress p ON rv.id = p.video_id AND p.student_id = ? AND p.completed = TRUE
        WHERE rp.roadmap_id = ? AND rp.phase_order <= ?
    ");
    $progressStmt->execute([$student_id, $roadmap_id, $MAX_FREE_PHASES]);
    $progress = $progressStmt->fetch();
    
    if ($progress['total_videos'] > 0) {
        $student_progress['percentage'] = ($progress['completed_videos'] / $progress['total_videos']) * 100;
        $student_progress['completed'] = $progress['completed_videos'];
        $student_progress['total'] = $progress['total_videos'];
    }
    
    // Only completed video IDs from FREE phases
    $completedStmt = $pdo->prepare("
        SELECT video_id 
        FROM progress 
        WHERE student_id = ? 
        AND video_id IN (
            SELECT rv.id 
            FROM roadmap_videos rv
            JOIN roadmap_phases rp ON rv.phase_id = rp.id
            WHERE rp.roadmap_id = ? AND rp.phase_order <= ?
        )
        AND completed = TRUE
    ");
    $completedStmt->execute([$student_id, $roadmap_id, $MAX_FREE_PHASES]);
    $completed_videos = $completedStmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

// Get the selected phase for display
$selected_phase = isset($phases[$selected_phase_index]) ? $phases[$selected_phase_index] : $phases[0];
$selected_phase['is_free'] = $selected_phase['phase_order'] <= $MAX_FREE_PHASES;
$selected_phase['is_unlocked'] = $is_enrolled || $selected_phase['is_free'];

// Function to get appropriate icon for roadmap
function getRoadmapIcon($title) {
    $titleLower = strtolower($title);
    $icons = [
        'javascript' => 'code',
        'typescript' => 'code-2',
        'react' => 'atom',
        'node' => 'server',
        'python' => 'python',
        'java' => 'coffee',
        'css' => 'palette',
        'sql' => 'database',
        'excel' => 'table',
        'english' => 'book-open',
        'php' => 'php',
        'html' => 'html5',
        'mongodb' => 'database',
        'docker' => 'container',
        'git' => 'git-branch',
        'data' => 'bar-chart-3',
        'analyst' => 'line-chart',
        'ai' => 'brain',
        'frontend' => 'layout',
        'backend' => 'server',
        'full stack' => 'layers',
        'devops' => 'server-cog',
        'mobile' => 'smartphone',
        'web' => 'globe',
        'design' => 'palette',
        'product' => 'package',
        'ux' => 'users',
        'ui' => 'palette'
    ];
    
    foreach ($icons as $keyword => $icon) {
        if (strpos($titleLower, $keyword) !== false) {
            return $icon;
        }
    }
    
    return 'book-open';
}

$roadmap_icon = getRoadmapIcon($roadmap['title']);
?>

<!DOCTYPE html>
<html lang="en" class="dark" style="color-scheme: dark;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($roadmap['title']); ?> | YourRoadmap</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        :root {
            --background: 19 20 23;
            --foreground: 255 255 255;
        }
        
        body {
            background-color: rgb(var(--background));
            color: rgb(var(--foreground));
            font-family: system-ui, -apple-system, sans-serif;
        }
        
        .skill-icon {
            width: 20px;
            height: 20px;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }
        
        .free-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .locked-badge {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        
        .enrolled-badge {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .text-purple {
            color: rgb(168, 85, 247);
        }
        
        .bg-hero-grid {
            position: fixed;
            inset: 0;
            background-color: #0a0a0f;
            background-image: 
                linear-gradient(to right, rgba(100, 149, 237, 0.1) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(100, 149, 237, 0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: -2;
        }
        
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            transition: width 0.3s ease;
        }
        
        .phase-card {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .locked-phase {
            opacity: 0.6;
            filter: grayscale(50%);
            position: relative;
        }
        
        .locked-phase::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 0.75rem;
            z-index: 1;
        }
        
        .locked-content {
            position: relative;
            z-index: 2;
        }
        
        .enroll-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }
        
        .enroll-button:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .video-item {
            background: rgba(30, 41, 59, 0.3);
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .video-item:hover {
            background: rgba(30, 41, 59, 0.5);
            cursor: pointer;
        }
        
        .video-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .video-modal.active {
            display: flex;
        }
        
        .modal-content {
            background: rgba(30, 31, 35, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .video-container {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 56.25%;
            background: #000;
        }
        
        .video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .phase-sidebar {
            position: sticky;
            top: 6rem;
            height: fit-content;
        }
        
        .phase-content {
            background: rgba(30, 41, 59, 0.3);
            border-radius: 1rem;
            padding: 2rem;
        }
        
        .checkmark {
            color: #10b981;
        }
        
        .free-tag {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .premium-tag {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        
        .phase-nav-btn.active {
            background: rgba(59, 130, 246, 0.3);
            border-color: rgba(59, 130, 246, 0.5);
        }
        
        .phase-nav-btn.active span {
            color: white;
        }
    </style>
</head>
<body class="dark min-h-screen flex flex-col">
    <!-- Background Grid -->
    <div class="bg-hero-grid"></div>
    
    <!-- Video Modal -->
    <div class="video-modal" id="videoModal">
        <div class="modal-content">
            <div class="flex justify-between items-center p-4 border-b border-gray-800">
                <h3 id="videoModalTitle" class="text-xl font-bold text-white"></h3>
                <button onclick="closeVideoModal()" class="text-white hover:text-gray-300 transition-colors bg-black/50 rounded-full p-2">
                    <i data-lucide="x" class="skill-icon"></i>
                </button>
            </div>
            <div class="video-container" id="videoModalPlayer">
                <!-- Video will be loaded here -->
            </div>
            <div class="p-4 border-t border-gray-800">
                <div class="flex items-center justify-between">
                    <div class="text-gray-400 text-sm">
                        <span id="videoModalDuration"></span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button id="markCompleteBtn" onclick="markCurrentVideoComplete()" 
                                class="flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <i data-lucide="check-circle" class="skill-icon mr-2"></i>
                            <span id="markCompleteText">Mark as Complete</span>
                        </button>
                        <button onclick="closeVideoModal()" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Header -->
    <nav class="bg-black border-b border-gray-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logo and Brand -->
                <div class="flex items-center">
                    <a href="<?php echo $BASE_PATH; ?>/index.php" class="flex items-center hover:opacity-80 transition-opacity">
                        <div class="w-10 h-10 bg-gradient-to-r from-purple-600 to-blue-600 rounded-xl flex items-center justify-center">
                            <span class="text-white font-bold text-lg">YR</span>
                        </div>
                        <span class="text-2xl font-bold text-white ml-3">
                            Your<span class="text-purple">Roadmap</span>
                        </span>
                    </a>
                </div>
                
                <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button class="flex items-center space-x-3 focus:outline-none" id="user-menu-button">
                            <img class="h-8 w-8 rounded-full border-2 border-purple-500/50" src="<?php echo htmlspecialchars($profile_picture); ?>" alt="<?php echo htmlspecialchars($student['name']); ?>" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($student['name']); ?>&background=random';">
                            <span class="hidden md:block text-sm font-medium text-white"><?php echo htmlspecialchars($student['name']); ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1" style="background-color:rgb(19, 20, 23)">
        <div class="w-full">
            <div class="space-y-8">
                <!-- Hero Section -->
                <div class="relative mb-12">
                    <div class="bg-gradient-to-r from-blue-900/20 to-indigo-900/20">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                            <div class="grid gap-8 md:grid-cols-2 items-center">
                                <div class="space-y-6">
                                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-900/30 text-blue-300 text-sm font-medium">
                                        <i data-lucide="book-open" class="skill-icon"></i>
                                        Learning Path
                                    </div>
                                    <h1 class="text-4xl font-bold text-white"><?php echo htmlspecialchars($roadmap['title']); ?></h1>
                                    <p class="text-xl text-gray-300"><?php echo htmlspecialchars($roadmap['description']); ?></p>
                                    
                                    <div class="flex items-center gap-4 text-sm">
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="user" class="skill-icon text-blue-400"></i>
                                            <span class="text-gray-300">Instructor:</span>
                                            <span class="font-medium text-white"><?php echo htmlspecialchars($roadmap['instructor_name']); ?></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="users" class="skill-icon text-purple-400"></i>
                                            <span class="text-gray-300">Students enrolled:</span>
                                            <span class="font-medium text-white"><?php echo $roadmap['enrollment_count'] ?? 0; ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Free Access Badge -->
                                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-green-900/30 text-green-300 text-sm font-medium">
                                        <i data-lucide="unlock" class="skill-icon"></i>
                                        First <?php echo $MAX_FREE_PHASES; ?> phases FREE
                                    </div>
                                    
                                    <div class="flex flex-wrap gap-4">
                                        <?php if ($is_enrolled): ?>
                                            <a href="<?php echo $BASE_PATH; ?>/student/roadmap_player.php?id=<?php echo $roadmap_id; ?>" class="inline-flex items-center gap-2 px-6 py-3 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                                <i data-lucide="play-circle" class="skill-icon"></i>
                                                Continue Learning
                                            </a>
                                        <?php else: ?>
                                            <button onclick="handleEnrollment()" class="inline-flex items-center gap-2 px-6 py-3 enroll-button text-white rounded-md">
                                                <i data-lucide="shopping-cart" class="skill-icon"></i>
                                                <?php if ($roadmap['price'] > 0): ?>
                                                    Unlock All Phases - $<?php echo htmlspecialchars(number_format($roadmap['price'], 2)); ?>
                                                <?php else: ?>
                                                    Enroll for Free
                                                <?php endif; ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 card-hover">
                                        <div class="flex items-center gap-4">
                                            <div class="h-12 w-12 rounded-lg bg-blue-900/30 flex items-center justify-center">
                                                <i data-lucide="layers" class="skill-icon h-6 w-6 text-blue-400"></i>
                                            </div>
                                            <div>
                                                <p class="text-2xl font-bold text-white"><?php echo count($phases); ?></p>
                                                <p class="text-gray-400">Learning Phases</p>
                                                <p class="text-xs text-green-400 mt-1">
                                                    <?php echo $MAX_FREE_PHASES; ?> free, <?php echo max(0, count($phases) - $MAX_FREE_PHASES); ?> premium
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 card-hover">
                                        <div class="flex items-center gap-4">
                                            <div class="h-12 w-12 rounded-lg bg-purple-900/30 flex items-center justify-center">
                                                <i data-lucide="video" class="skill-icon h-6 w-6 text-purple-400"></i>
                                            </div>
                                            <div>
                                                <p class="text-2xl font-bold text-white"><?php echo $total_videos; ?></p>
                                                <p class="text-gray-400">Video Lessons</p>
                                                <p class="text-xs text-green-400 mt-1">
                                                    <?php echo $free_phases_videos; ?> free, <?php echo $paid_phases_videos; ?> premium
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 card-hover">
                                        <div class="flex items-center gap-4">
                                            <div class="h-12 w-12 rounded-lg bg-green-900/30 flex items-center justify-center">
                                                <i data-lucide="clock" class="skill-icon h-6 w-6 text-green-400"></i>
                                            </div>
                                            <div>
                                                <p class="text-2xl font-bold text-white"><?php echo $total_hours; ?>h <?php echo $total_minutes; ?>m</p>
                                                <p class="text-gray-400">Total Duration</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 card-hover">
                                        <div class="flex items-center gap-4">
                                            <div class="h-12 w-12 rounded-lg bg-yellow-900/30 flex items-center justify-center">
                                                <i data-lucide="award" class="skill-icon h-6 w-6 text-yellow-400"></i>
                                            </div>
                                            <div>
                                                <p class="text-2xl font-bold text-white">Certificate</p>
                                                <p class="text-gray-400">On Completion</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div class="space-y-12">
                        <!-- Back Button -->
                        <div class="mb-6">
                            <a href="<?php echo $BASE_PATH; ?>/student/explore_roadmaps.php" class="inline-flex items-center text-gray-400 hover:text-white transition-colors">
                                <i data-lucide="arrow-left" class="skill-icon mr-2"></i>
                                Back to All Roadmaps
                            </a>
                        </div>

                        <!-- Progress Section -->
                        <div class="space-y-6">
                            <div class="rounded-lg border <?php echo $is_enrolled ? 'border-green-500/30' : 'border-blue-500/30'; ?> bg-gray-900 p-6">
                                <div class="flex items-center justify-between mb-6">
                                    <div>
                                        <h3 class="text-xl font-semibold text-white">Your Progress</h3>
                                        <p class="text-gray-400">
                                            <?php if ($is_enrolled): ?>
                                                Track your learning journey
                                            <?php else: ?>
                                                Free preview: First <?php echo $MAX_FREE_PHASES; ?> phases
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <span class="<?php echo $is_enrolled ? 'enrolled-badge' : 'free-badge'; ?> text-xs font-bold text-white px-3 py-1 rounded inline-flex items-center">
                                        <i data-lucide="<?php echo $is_enrolled ? 'check-circle' : 'unlock'; ?>" class="skill-icon h-3 w-3 mr-1"></i>
                                        <?php echo $is_enrolled ? 'Enrolled' : 'Free Preview'; ?>
                                    </span>
                                </div>
                                
                                <!-- Progress Bar -->
                                <div class="mb-4">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-400">Progress</span>
                                        <span id="progressPercentage" class="font-medium text-white"><?php echo round($student_progress['percentage']); ?>%</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div id="progressFill" class="progress-fill" style="width: <?php echo $student_progress['percentage']; ?>%"></div>
                                    </div>
                                    <div id="progressText" class="text-xs text-gray-400 mt-1">
                                        <?php if ($is_enrolled): ?>
                                            <?php echo $student_progress['completed']; ?> of <?php echo $student_progress['total']; ?> lessons completed
                                        <?php else: ?>
                                            <?php echo $student_progress['completed']; ?> of <?php echo $free_phases_videos; ?> free lessons completed
                                            <span class="text-yellow-400">(<?php echo $paid_phases_videos; ?> premium lessons locked)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Learning Phases Section -->
                        <div class="space-y-6">
                            <h2 class="text-2xl font-bold text-white flex items-center">
                                <i data-lucide="map" class="skill-icon mr-3 text-blue-400"></i>
                                Learning Journey
                            </h2>
                            
                            <!-- Phase Layout -->
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                                <!-- Phase Sidebar -->
                                <div class="lg:col-span-1">
                                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6 phase-sidebar">
                                        <h3 class="text-lg font-bold text-white mb-4">Learning Path</h3>
                                        <div class="space-y-2">
                                            <?php foreach ($phases as $index => $phase): ?>
                                                <?php 
                                                    $is_free = $phase['phase_order'] <= $MAX_FREE_PHASES;
                                                    $is_unlocked = $is_enrolled || $is_free;
                                                    $is_active = $index === $selected_phase_index;
                                                ?>
                                                <a href="?id=<?php echo $roadmap_id; ?>&phase=<?php echo $index + 1; ?>"
                                                   class="block w-full text-left p-3 rounded-lg transition-colors <?php echo $is_active ? 'phase-nav-btn active' : 'hover:bg-gray-800'; ?> <?php echo !$is_unlocked ? 'locked-phase' : ''; ?>">
                                                    <div class="flex items-center justify-between locked-content">
                                                        <div class="flex items-center space-x-3">
                                                            <div class="h-8 w-8 rounded-md <?php echo $is_free ? 'bg-green-900/30' : 'bg-purple-900/30'; ?> flex items-center justify-center">
                                                                <span class="text-white font-bold text-sm"><?php echo $index + 1; ?></span>
                                                            </div>
                                                            <div class="text-left">
                                                                <span class="font-medium <?php echo $is_active ? 'text-white' : 'text-gray-300'; ?> block">
                                                                    <?php echo htmlspecialchars($phase['title']); ?>
                                                                </span>
                                                                <span class="text-xs <?php echo $is_free ? 'text-green-400' : 'text-purple-400'; ?>">
                                                                    <?php echo $is_free ? 'FREE' : 'PREMIUM'; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <?php if (!$is_unlocked): ?>
                                                            <i data-lucide="lock" class="skill-icon text-gray-500"></i>
                                                        <?php elseif ($is_active): ?>
                                                            <i data-lucide="chevron-right" class="skill-icon text-blue-400"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mt-2 text-xs text-gray-400 pl-11 locked-content">
                                                        <?php echo $phase['total_videos']; ?> videos • 
                                                        <?php echo $phase['hours'] > 0 ? $phase['hours'] . 'h ' : ''; ?><?php echo $phase['minutes']; ?>m
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <!-- Enrollment CTA for locked phases -->
                                        <?php if (!$is_enrolled && count($phases) > $MAX_FREE_PHASES): ?>
                                            <div class="mt-6 p-4 bg-gradient-to-r from-purple-900/20 to-blue-900/20 border border-purple-500/30 rounded-lg">
                                                <h4 class="text-sm font-bold text-white mb-2">🔒 Unlock Premium Content</h4>
                                                <p class="text-xs text-gray-300 mb-3">
                                                    Enroll to access all <?php echo count($phases) - $MAX_FREE_PHASES; ?> premium phases
                                                </p>
                                                <button onclick="handleEnrollment()" class="w-full px-4 py-2 enroll-button text-white font-medium rounded-md text-sm">
                                                    <?php if ($roadmap['price'] > 0): ?>
                                                        Unlock for $<?php echo htmlspecialchars(number_format($roadmap['price'], 2)); ?>
                                                    <?php else: ?>
                                                        Enroll for Free
                                                    <?php endif; ?>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Phase Content -->
                                <div class="lg:col-span-2">
                                    <div class="phase-content">
                                        <!-- Phase Header -->
                                        <div class="mb-6">
                                            <div class="flex items-center justify-between mb-4">
                                                <h3 class="text-2xl font-bold text-white">
                                                    Phase <?php echo $selected_phase['phase_order']; ?>: 
                                                    <?php echo htmlspecialchars($selected_phase['title']); ?>
                                                </h3>
                                                <div class="flex items-center space-x-2">
                                                    <span class="text-sm px-3 py-1 rounded-full <?php echo $selected_phase['is_free'] ? 'free-tag' : 'premium-tag'; ?> text-white">
                                                        <?php echo $selected_phase['is_free'] ? 'FREE' : 'PREMIUM'; ?>
                                                    </span>
                                                    <span class="text-sm px-3 py-1 rounded-full <?php echo $selected_phase['is_unlocked'] ? 'bg-green-900/30 text-green-400' : 'bg-gray-800 text-gray-400'; ?>">
                                                        <?php echo $selected_phase['is_unlocked'] ? 'Unlocked' : 'Locked'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <p class="text-gray-300 mb-6">
                                                <?php if ($selected_phase['is_free']): ?>
                                                    This free phase covers foundational concepts. You'll learn through practical examples and hands-on exercises.
                                                <?php else: ?>
                                                    This premium phase covers advanced topics and skills. You'll learn through practical examples and hands-on exercises.
                                                <?php endif; ?>
                                            </p>
                                        </div>

                                        <!-- Video Grid -->
                                        <div class="space-y-4">
                                            <?php if (!empty($selected_phase['videos'])): ?>
                                                <?php foreach ($selected_phase['videos'] as $videoIndex => $video): ?>
                                                    <?php 
                                                        $is_completed = in_array($video['id'], $completed_videos);
                                                    ?>
                                                    <div onclick="playVideo(<?php echo $video['id']; ?>, '<?php echo htmlspecialchars($video['title']); ?>', '<?php echo $video['full_url']; ?>', <?php echo $video['duration']; ?>, <?php echo $is_completed ? 'true' : 'false'; ?>, <?php echo $selected_phase['is_unlocked'] ? 'true' : 'false'; ?>, <?php echo $selected_phase['is_free'] ? 'true' : 'false'; ?>)" 
                                                        class="video-item p-4 flex items-center justify-between hover:bg-gray-800/50 transition-colors <?php echo $selected_phase['is_unlocked'] ? 'cursor-pointer' : 'cursor-not-allowed'; ?>"
                                                        id="video-<?php echo $video['id']; ?>">
                                                        <div class="flex items-center space-x-4">
                                                            <div class="relative">
                                                                <?php if ($selected_phase['is_unlocked']): ?>
                                                                    <?php if ($is_completed): ?>
                                                                        <i data-lucide="check-circle" class="skill-icon text-green-400 text-2xl"></i>
                                                                    <?php else: ?>
                                                                        <i data-lucide="play-circle" class="skill-icon text-blue-400 text-2xl"></i>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <i data-lucide="lock" class="skill-icon text-gray-500 text-2xl"></i>
                                                                <?php endif; ?>
                                                                <span class="absolute -bottom-1 -right-1 text-xs bg-gray-800 text-gray-300 px-1 rounded">
                                                                    <?php echo sprintf('%02d', $videoIndex + 1); ?>
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <h4 class="font-medium <?php echo $selected_phase['is_unlocked'] ? 'text-white' : 'text-gray-500'; ?>"><?php echo htmlspecialchars($video['title']); ?></h4>
                                                                <div class="flex items-center space-x-3 text-sm text-gray-400 mt-1">
                                                                    <span class="flex items-center">
                                                                        <i data-lucide="clock" class="skill-icon mr-1"></i>
                                                                        <?php echo $video['duration']; ?> min
                                                                    </span>
                                                                    <span class="flex items-center">
                                                                        <i data-lucide="video" class="skill-icon mr-1"></i>
                                                                        <?php if ($selected_phase['is_unlocked']): ?>
                                                                            <?php if ($is_completed): ?>
                                                                                <span class="text-green-400">Completed</span>
                                                                            <?php else: ?>
                                                                                <span class="<?php echo $selected_phase['is_free'] ? 'text-green-400' : 'text-purple-400'; ?>">
                                                                                    <?php echo $selected_phase['is_free'] ? 'Free Video' : 'Premium Video'; ?>
                                                                                </span>
                                                                            <?php endif; ?>
                                                                        <?php else: ?>
                                                                            <span class="text-red-400">Locked - Enroll to access</span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php if ($selected_phase['is_unlocked']): ?>
                                                            <?php if ($is_completed): ?>
                                                                <i data-lucide="check" class="skill-icon text-green-400"></i>
                                                            <?php else: ?>
                                                                <i data-lucide="play" class="skill-icon text-blue-400"></i>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <i data-lucide="lock" class="skill-icon text-gray-500"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p class="text-gray-500 text-center py-8">No videos available for this phase.</p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Phase Lock Message -->
                                        <?php if (!$selected_phase['is_unlocked']): ?>
                                            <div class="mt-8 p-6 bg-gradient-to-r from-red-900/20 to-orange-900/20 border border-red-500/30 rounded-xl">
                                                <div class="flex items-center space-x-4">
                                                    <i data-lucide="lock" class="skill-icon text-red-400 text-3xl"></i>
                                                    <div class="flex-1">
                                                        <h4 class="text-lg font-bold text-white mb-2">🔒 Premium Phase Locked</h4>
                                                        <p class="text-gray-300 mb-3">
                                                            This phase requires enrollment. Enroll now to unlock all premium content including this phase.
                                                        </p>
                                                        <button onclick="handleEnrollment()" class="px-6 py-2 enroll-button text-white font-medium rounded-md">
                                                            <?php if ($roadmap['price'] > 0): ?>
                                                                Unlock for $<?php echo htmlspecialchars(number_format($roadmap['price'], 2)); ?>
                                                            <?php else: ?>
                                                                Enroll for Free
                                                            <?php endif; ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Enrollment CTA (if not enrolled) -->
                        <?php if (!$is_enrolled): ?>
                        <div class="space-y-6">
                            <div class="rounded-lg border border-blue-500/30 bg-gray-900 p-8">
                                <div class="flex flex-col lg:flex-row items-center justify-between gap-6">
                                    <div class="flex-1">
                                        <h3 class="text-2xl font-bold text-white mb-3">
                                            <?php if ($roadmap['price'] > 0): ?>
                                                Unlock Full Access!
                                            <?php else: ?>
                                                Complete Your Learning Journey!
                                            <?php endif; ?>
                                        </h3>
                                        <p class="text-gray-300 mb-4">
                                            You've previewed the first <?php echo $MAX_FREE_PHASES; ?> phases. 
                                            Enroll now to access the complete roadmap with <?php echo count($phases); ?> phases and <?php echo $total_videos; ?>+ lessons.
                                        </p>
                                        <ul class="space-y-2">
                                            <li class="flex items-center gap-2 text-gray-300">
                                                <i data-lucide="check-circle" class="skill-icon text-green-400"></i>
                                                Access to all <?php echo count($phases); ?> learning phases
                                            </li>
                                            <li class="flex items-center gap-2 text-gray-300">
                                                <i data-lucide="check-circle" class="skill-icon text-green-400"></i>
                                                <?php echo $total_videos; ?>+ video lessons
                                            </li>
                                            <li class="flex items-center gap-2 text-gray-300">
                                                <i data-lucide="check-circle" class="skill-icon text-green-400"></i>
                                                Certificate of completion
                                            </li>
                                            <li class="flex items-center gap-2 text-gray-300">
                                                <i data-lucide="check-circle" class="skill-icon text-green-400"></i>
                                                Track your progress across all phases
                                            </li>
                                            <?php if ($paid_phases_videos > 0): ?>
                                            <li class="flex items-center gap-2 text-yellow-300">
                                                <i data-lucide="star" class="skill-icon"></i>
                                                <?php echo $paid_phases_videos; ?> premium video lessons
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <div class="flex flex-col items-center">
                                        <?php if ($roadmap['price'] > 0): ?>
                                            <div class="text-center mb-4">
                                                <span class="text-4xl font-bold text-white">$<?php echo htmlspecialchars(number_format($roadmap['price'], 2)); ?></span>
                                                <p class="text-gray-400">One-time payment</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center mb-4">
                                                <span class="text-4xl font-bold text-white">FREE</span>
                                                <p class="text-gray-400">No payment required</p>
                                            </div>
                                        <?php endif; ?>
                                        <button onclick="handleEnrollment()" class="px-8 py-3 enroll-button text-white font-medium rounded-md text-lg w-full">
                                            <?php if ($roadmap['price'] > 0): ?>
                                                Enroll Now
                                            <?php else: ?>
                                                Get Full Access Free
                                            <?php endif; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            lucide.createIcons();
            
            // Global variables
            let completedVideos = <?php echo json_encode($completed_videos); ?>;
            let currentVideoId = null;
            let currentVideoElement = null;
            const roadmapId = <?php echo $roadmap_id; ?>;
            const MAX_FREE_PHASES = <?php echo $MAX_FREE_PHASES; ?>;
            const isEnrolled = <?php echo $is_enrolled ? 'true' : 'false'; ?>;
            
            // Function to play video
            window.playVideo = function(videoId, videoTitle, videoUrl, duration, isCompleted, isUnlocked, isFreePhase) {
                currentVideoId = videoId;
                currentVideoElement = document.getElementById('video-' + videoId);
                
                // Check if video is unlocked
                if (!isUnlocked) {
                    showEnrollmentPrompt();
                    return;
                }
                
                const modal = document.getElementById('videoModal');
                const title = document.getElementById('videoModalTitle');
                const player = document.getElementById('videoModalPlayer');
                const durationElem = document.getElementById('videoModalDuration');
                const markCompleteBtn = document.getElementById('markCompleteBtn');
                const markCompleteText = document.getElementById('markCompleteText');
                
                // Set modal content
                title.textContent = videoTitle;
                durationElem.textContent = duration + ' minutes';
                
                // Update complete button state
                if (isCompleted) {
                    markCompleteBtn.disabled = true;
                    markCompleteText.innerHTML = '<i data-lucide="check" class="skill-icon mr-2"></i>Already Completed';
                } else {
                    markCompleteBtn.disabled = false;
                    markCompleteText.innerHTML = '<i data-lucide="check-circle" class="skill-icon mr-2"></i>Mark as Complete';
                }
                
                // Load video player
                player.innerHTML = `
                    <video 
                        id="videoPlayer" 
                        controls 
                        autoplay
                        onended="onVideoEnded()"
                        style="width: 100%; height: 100%;"
                    >
                        <source src="${videoUrl}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                `;
                
                // Reinitialize icons
                lucide.createIcons();
                
                // Show modal
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
            
            // Function to show enrollment prompt
            function showEnrollmentPrompt() {
                if (confirm('This content requires enrollment. Would you like to enroll now?')) {
                    handleEnrollment();
                }
            }
            
            // Function when video ends
            window.onVideoEnded = function() {
                if (currentVideoId && !completedVideos.includes(currentVideoId)) {
                    markVideoComplete(currentVideoId);
                }
            }
            
            // Function to close video modal
            window.closeVideoModal = function() {
                const modal = document.getElementById('videoModal');
                const player = document.getElementById('videoModalPlayer');
                
                // Stop video
                const videoElement = document.getElementById('videoPlayer');
                if (videoElement) {
                    videoElement.pause();
                    videoElement.currentTime = 0;
                }
                
                player.innerHTML = '';
                modal.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
            
            // Function to mark video as complete
            window.markCurrentVideoComplete = function() {
                if (currentVideoId) {
                    markVideoComplete(currentVideoId);
                }
            }
            
            // Function to mark video as complete via AJAX
            function markVideoComplete(videoId) {
                if (!videoId) return;
                
                const markCompleteBtn = document.getElementById('markCompleteBtn');
                const originalText = markCompleteBtn.innerHTML;
                
                // Show loading state
                markCompleteBtn.disabled = true;
                markCompleteBtn.innerHTML = '<div class="loading-spinner mr-2"></div>Marking...';
                
                // Send AJAX request
                fetch('view_roadmap.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'mark_video_complete',
                        video_id: videoId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Update UI
                        completedVideos.push(parseInt(videoId));
                        
                        // Update video element in list
                        if (currentVideoElement) {
                            // Update icon to checkmark
                            const icon = currentVideoElement.querySelector('.skill-icon.text-2xl');
                            if (icon) {
                                icon.setAttribute('data-lucide', 'check-circle');
                                icon.classList.remove('text-blue-400');
                                icon.classList.add('text-green-400');
                            }
                            
                            // Update status text
                            const statusText = currentVideoElement.querySelector('.text-gray-400 span');
                            if (statusText) {
                                statusText.innerHTML = '<span class="text-green-400">Completed</span>';
                            }
                            
                            // Update play icon to check
                            const playIcon = currentVideoElement.querySelector('.skill-icon.text-blue-400');
                            if (playIcon) {
                                playIcon.setAttribute('data-lucide', 'check');
                                playIcon.classList.remove('text-blue-400');
                                playIcon.classList.add('text-green-400');
                            }
                        }
                        
                        // Update complete button in modal
                        const markCompleteBtn = document.getElementById('markCompleteBtn');
                        const markCompleteText = document.getElementById('markCompleteText');
                        markCompleteBtn.disabled = true;
                        markCompleteText.innerHTML = '<i data-lucide="check" class="skill-icon mr-2"></i>Already Completed';
                        
                        // Update progress in header
                        updateProgressDisplay();
                        
                        // Reinitialize icons
                        lucide.createIcons();
                    } else if (data.message.includes('Enroll to track progress')) {
                        alert('Please enroll to track progress for premium phases.');
                        markCompleteBtn.disabled = false;
                        markCompleteBtn.innerHTML = originalText;
                    } else {
                        alert('Error: ' + data.message);
                        markCompleteBtn.disabled = false;
                        markCompleteBtn.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                    markCompleteBtn.disabled = false;
                    markCompleteBtn.innerHTML = originalText;
                });
            }
            
            // Function to update progress display
            function updateProgressDisplay() {
                // Fetch updated progress from server
                fetch('view_roadmap.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'get_progress',
                        roadmap_id: roadmapId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Update progress bar
                        document.getElementById('progressPercentage').textContent = data.percentage + '%';
                        document.getElementById('progressFill').style.width = data.percentage + '%';
                        
                        // Update progress text
                        if (data.is_enrolled) {
                            document.getElementById('progressText').innerHTML = 
                                data.completed + ' of ' + data.total + ' lessons completed';
                        } else {
                            // For free users, show free vs premium counts
                            const freePhasesVideos = <?php echo $free_phases_videos; ?>;
                            const paidPhasesVideos = <?php echo $paid_phases_videos; ?>;
                            document.getElementById('progressText').innerHTML = 
                                data.completed + ' of ' + freePhasesVideos + ' free lessons completed ' +
                                '<span class="text-yellow-400">(' + paidPhasesVideos + ' premium lessons locked)</span>';
                        }
                    }
                })
                .catch(error => console.error('Error updating progress:', error));
            }
            
            // Enrollment handler
            window.handleEnrollment = function() {
                const price = <?php echo $roadmap['price']; ?>;
                
                if (price > 0) {
                    // Redirect to payment page for paid roadmaps
                    window.location.href = '<?php echo $BASE_PATH; ?>/student/enroll.php?id=' + roadmapId;
                } else {
                    // Free enrollment - use AJAX
                    if (confirm('Are you sure you want to enroll in this roadmap?')) {
                        fetch('<?php echo $BASE_PATH; ?>/student/enroll.php?id=' + roadmapId, {
                            method: 'GET'
                        })
                        .then(response => {
                            if (response.redirected) {
                                window.location.href = response.url;
                            } else {
                                window.location.reload();
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                        });
                    }
                }
            }
            
            // Close modal on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeVideoModal();
                }
            });
            
            // Close modal when clicking outside
            document.getElementById('videoModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeVideoModal();
                }
            });
        });
    </script>
</body>
</html>