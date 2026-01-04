<?php
// student/view_roadmap.php

// Start the session to manage user state.
session_start();

// --- SETUP & SECURITY ---
// Include middleware for authentication and authorization.
require_once __DIR__ . '/../auth/middleware.php';
// Protect the page: ensure the user is logged in and has the 'student' role.
requireStudent();
// Include the database connection file.
require_once __DIR__ . '/../config/db.php';

$BASE_PATH = '/fianlroadmap';

// --- FETCH STUDENT PROFILE DATA ---
$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];
$UPLOADS_DIR = $_SERVER['DOCUMENT_ROOT'] . $BASE_PATH . '/uploads/profile_pictures/';

$stmt = $pdo->prepare("SELECT id, name, email, profile_picture FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $student['profile_picture'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($student['name']) . '&background=random';

// --- DATA VALIDATION ---
// Check if roadmap ID is provided in the URL, if not, redirect.
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: explore_roadmaps.php');
    exit();
}
$roadmap_id = $_GET['id'];

// --- DATA FETCHING: ROADMAP ---
// Fetch the main roadmap details. It's crucial to also check for 'approved' status.
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

// If no roadmap is found (or it's not approved), display an error and exit.
if (!$roadmap) {
    die("Roadmap not found or not available.");
}

// --- DATA FETCHING: PHASES ---
// Fetch all phases associated with this roadmap, ordered correctly.
$phases_stmt = $pdo->prepare("SELECT id, title, phase_order FROM roadmap_phases WHERE roadmap_id = ? ORDER BY phase_order ASC");
$phases_stmt->execute([$roadmap_id]);
$phases = $phases_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch videos for each phase
$total_videos = 0;
$total_duration_minutes = 0;
foreach ($phases as &$phase) {
    $videos_stmt = $pdo->prepare("SELECT id, title, video_url, video_order FROM roadmap_videos WHERE phase_id = ? ORDER BY video_order ASC");
    $videos_stmt->execute([$phase['id']]);
    $videos = $videos_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add simulated durations to videos (5-30 minutes each)
    foreach ($videos as &$video) {
        $video['duration'] = rand(5, 30); // Simulated duration in minutes
        $video['hours'] = floor($video['duration'] / 60);
        $video['minutes'] = $video['duration'] % 60;
        $total_duration_minutes += $video['duration'];
        
        // Fix video URL - add base path for local files
        if (strpos($video['video_url'], 'uploads/videos/') === 0) {
            // It's a local video file, add base path
            $video['full_url'] = $BASE_PATH . '/' . $video['video_url'];
        } else {
            // It's already a full URL (YouTube or other)
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
}
unset($phase);

// Calculate total hours for roadmap
$total_hours = floor($total_duration_minutes / 60);
$total_minutes = $total_duration_minutes % 60;

// Check if user is enrolled in this roadmap
$enrollment_stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND roadmap_id = ?");
$enrollment_stmt->execute([$student_id, $roadmap_id]);
$is_enrolled = $enrollment_stmt->fetch() ? true : false;

// Get student's progress if enrolled
$student_progress = ['percentage' => 0, 'completed' => 0, 'total' => 0];
$completed_videos = [];
if ($is_enrolled) {
    $progressStmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT p.video_id) as completed_videos,
            COUNT(DISTINCT rv.id) as total_videos
        FROM roadmap_videos rv
        JOIN roadmap_phases rp ON rv.phase_id = rp.id
        LEFT JOIN progress p ON rv.id = p.video_id AND p.student_id = ?
        WHERE rp.roadmap_id = ?
    ");
    $progressStmt->execute([$student_id, $roadmap_id]);
    $progress = $progressStmt->fetch();
    
    if ($progress['total_videos'] > 0) {
        $student_progress['percentage'] = ($progress['completed_videos'] / $progress['total_videos']) * 100;
        $student_progress['completed'] = $progress['completed_videos'];
        $student_progress['total'] = $progress['total_videos'];
    }
    
    // Get completed video IDs
    $completedStmt = $pdo->prepare("SELECT video_id FROM progress WHERE student_id = ? AND roadmap_id = ? AND completed_at IS NOT NULL");
    $completedStmt->execute([$student_id, $roadmap_id]);
    $completed_videos = $completedStmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

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
            --card: 30 30 30;
            --card-foreground: 255 255 255;
            --primary: 59 130 246;
            --primary-foreground: 255 255 255;
            --secondary: 124 58 237;
            --secondary-foreground: 255 255 255;
            --accent: 40 40 40;
            --accent-foreground: 255 255 255;
            --muted: 60 60 60;
            --muted-foreground: 180 180 180;
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
        
        .enrolled-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
        
        /* Background Grid */
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
        
        /* Progress Bar */
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
        
        /* Phase Card Styles */
        .phase-card {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .phase-card:hover {
            transform: translateY(-5px);
            border-color: rgba(168, 85, 247, 0.5);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .locked-phase {
            opacity: 0.6;
            filter: grayscale(50%);
        }
        
        .enroll-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }
        
        .enroll-button:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .hero-gradient {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }
        
        /* Video List Styles */
        .video-item {
            background: rgba(30, 41, 59, 0.3);
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .video-item:hover {
            background: rgba(30, 41, 59, 0.5);
            cursor: pointer;
        }
        
        /* Video Preview Modal */
        .video-preview-modal {
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
        
        .video-preview-modal.active {
            display: flex;
        }
        
        .preview-modal-content {
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
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            background: #000;
        }
        
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        
        /* Phase Layout */
        .phase-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        @media (max-width: 1024px) {
            .phase-layout {
                grid-template-columns: 1fr;
            }
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
        
        /* Checkmark */
        .checkmark {
            color: #10b981;
        }
    </style>
</head>
<body class="dark min-h-screen flex flex-col">
    <!-- Background Grid -->
    <div class="bg-hero-grid"></div>
    
    <!-- Video Preview Modal (For non-enrolled users only) -->
    <div class="video-preview-modal" id="videoPreviewModal">
        <div class="preview-modal-content">
            <div class="flex justify-between items-center p-4 border-b border-gray-800">
                <h3 id="videoPreviewTitle" class="text-xl font-bold text-white"></h3>
                <button onclick="closeVideoPreview()" class="text-white hover:text-gray-300 transition-colors bg-black/50 rounded-full p-2">
                    <i data-lucide="x" class="skill-icon"></i>
                </button>
            </div>
            <div class="video-container" id="videoPreviewPlayer">
                <!-- Video will be loaded here -->
            </div>
            <div class="p-4 border-t border-gray-800">
                <div class="flex items-center justify-between text-gray-400">
                    <div class="flex items-center space-x-4">
                        <span id="videoPreviewDuration" class="flex items-center">
                            <i data-lucide="clock" class="skill-icon mr-2"></i>
                        </span>
                        <span id="videoPreviewPhase" class="flex items-center">
                            <i data-lucide="layers" class="skill-icon mr-2"></i>
                        </span>
                    </div>
                    <?php if (!$is_enrolled): ?>
                    <button onclick="handleEnrollment()" class="flex items-center text-green-400 hover:text-green-300 transition-colors">
                        <i data-lucide="unlock" class="skill-icon mr-2"></i>
                        Enroll to Continue Watching
                    </button>
                    <?php endif; ?>
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
                                                    Enroll Now - $<?php echo htmlspecialchars(number_format($roadmap['price'], 2)); ?>
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

                        <?php if ($is_enrolled): ?>
                        <!-- Progress Section -->
                        <div class="space-y-6">
                            <div class="rounded-lg border border-green-500/30 bg-gray-900 p-6">
                                <div class="flex items-center justify-between mb-6">
                                    <div>
                                        <h3 class="text-xl font-semibold text-white">Your Progress</h3>
                                        <p class="text-gray-400">Track your learning journey</p>
                                    </div>
                                    <span class="enrolled-badge text-xs font-bold text-white px-3 py-1 rounded inline-flex items-center">
                                        <i data-lucide="check-circle" class="skill-icon h-3 w-3 mr-1"></i>
                                        Enrolled
                                    </span>
                                </div>
                                
                                <!-- Progress Bar -->
                                <div class="mb-4">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-400">Progress</span>
                                        <span class="font-medium text-white"><?php echo round($student_progress['percentage']); ?>%</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $student_progress['percentage']; ?>%"></div>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1">
                                        <?php echo $student_progress['completed']; ?> of <?php echo $student_progress['total']; ?> lessons completed
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Learning Phases Section -->
                        <div class="space-y-6">
                            <h2 class="text-2xl font-bold text-white flex items-center">
                                <i data-lucide="map" class="skill-icon mr-3 text-blue-400"></i>
                                Learning Journey
                            </h2>
                            
                            <!-- Phase Layout -->
                            <div class="phase-layout">
                                <!-- Phase Sidebar -->
                                <div class="phase-sidebar">
                                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                                        <h3 class="text-lg font-bold text-white mb-4">Learning Path</h3>
                                        <div class="space-y-2">
                                            <?php foreach ($phases as $index => $phase): ?>
                                                <?php 
                                                    $is_unlocked = $is_enrolled || $index < 2;
                                                    $is_active = $index === 0;
                                                ?>
                                                <button onclick="selectPhase(<?php echo $index; ?>)" 
                                                        class="w-full text-left p-3 rounded-lg transition-colors <?php echo $is_active ? 'bg-blue-900/30 border border-blue-500/30' : 'hover:bg-gray-800'; ?>">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex items-center space-x-3">
                                                            <div class="h-8 w-8 rounded-md bg-blue-900/30 flex items-center justify-center">
                                                                <span class="text-white font-bold text-sm"><?php echo $index + 1; ?></span>
                                                            </div>
                                                            <span class="font-medium <?php echo $is_active ? 'text-white' : 'text-gray-300'; ?>">
                                                                <?php echo htmlspecialchars($phase['title']); ?>
                                                            </span>
                                                        </div>
                                                        <?php if (!$is_unlocked): ?>
                                                            <i data-lucide="lock" class="skill-icon text-gray-500"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mt-2 text-xs text-gray-400 pl-11">
                                                        <?php echo $phase['total_videos']; ?> videos • 
                                                        <?php echo $phase['hours'] > 0 ? $phase['hours'] . 'h ' : ''; ?><?php echo $phase['minutes']; ?>m
                                                    </div>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Phase Content -->
                                <div class="phase-content">
                                    <div id="phaseContent">
                                        <?php if (!empty($phases)): 
                                            $firstPhase = $phases[0];
                                            $is_unlocked = $is_enrolled || 0 < 2;
                                        ?>
                                            <div class="mb-6">
                                                <div class="flex items-center justify-between mb-4">
                                                    <h3 class="text-2xl font-bold text-white">Phase 1: <?php echo htmlspecialchars($firstPhase['title']); ?></h3>
                                                    <span class="text-sm px-3 py-1 rounded-full <?php echo $is_unlocked ? 'bg-green-900/30 text-green-400' : 'bg-gray-800 text-gray-400'; ?>">
                                                        <?php echo $is_unlocked ? 'Unlocked' : 'Locked'; ?>
                                                    </span>
                                                </div>
                                                <p class="text-gray-300 mb-6">
                                                    This phase covers the foundational concepts and skills needed to get started. 
                                                    You'll learn the basics through practical examples and hands-on exercises.
                                                </p>
                                            </div>

                                            <!-- Video Grid -->
                                            <div class="space-y-4">
                                                <?php if (!empty($firstPhase['videos'])): ?>
                                                    <?php foreach ($firstPhase['videos'] as $videoIndex => $video): ?>
                                                        <?php 
                                                            $is_completed = in_array($video['id'], $completed_videos);
                                                            $max_preview_videos = 2; // Show only first 2 videos in preview
                                                        ?>
                                                        <div onclick="<?php 
                                                            if ($is_enrolled) {
                                                                // Enrolled users go to full player
                                                                echo "window.location.href='" . $BASE_PATH . "/student/roadmap_player.php?id=" . $roadmap_id . "&video=" . $video['id'] . "'";
                                                            } else {
                                                                // Non-enrolled users get preview (first 2 videos only)
                                                                if ($videoIndex < $max_preview_videos) {
                                                                    echo "playVideoPreview('" . htmlspecialchars($video['full_url']) . "', '" . htmlspecialchars($video['title']) . "', " . $video['duration'] . ", '" . htmlspecialchars($firstPhase['title']) . "')";
                                                                } else {
                                                                    echo "handleEnrollment()";
                                                                }
                                                            }
                                                        ?>" 
                                                            class="video-item p-4 flex items-center justify-between hover:bg-gray-800/50 transition-colors cursor-pointer">
                                                            <div class="flex items-center space-x-4">
                                                                <div class="relative">
                                                                    <?php if ($is_unlocked): ?>
                                                                        <?php if ($is_enrolled && $is_completed): ?>
                                                                            <i data-lucide="check-circle" class="skill-icon text-green-400 text-2xl"></i>
                                                                        <?php elseif ($is_enrolled): ?>
                                                                            <i data-lucide="play-circle" class="skill-icon text-blue-400 text-2xl"></i>
                                                                        <?php elseif ($videoIndex < $max_preview_videos): ?>
                                                                            <i data-lucide="play-circle" class="skill-icon text-blue-400 text-2xl"></i>
                                                                        <?php else: ?>
                                                                            <i data-lucide="lock" class="skill-icon text-gray-500 text-2xl"></i>
                                                                        <?php endif; ?>
                                                                    <?php else: ?>
                                                                        <i data-lucide="lock" class="skill-icon text-gray-500 text-2xl"></i>
                                                                    <?php endif; ?>
                                                                    <span class="absolute -bottom-1 -right-1 text-xs bg-gray-800 text-gray-300 px-1 rounded">
                                                                        <?php echo sprintf('%02d', $videoIndex + 1); ?>
                                                                    </span>
                                                                </div>
                                                                <div>
                                                                    <h4 class="font-medium text-white"><?php echo htmlspecialchars($video['title']); ?></h4>
                                                                    <div class="flex items-center space-x-3 text-sm text-gray-400 mt-1">
                                                                        <span class="flex items-center">
                                                                            <i data-lucide="clock" class="skill-icon mr-1"></i>
                                                                            <?php echo $video['duration']; ?> min
                                                                        </span>
                                                                        <span class="flex items-center">
                                                                            <i data-lucide="video" class="skill-icon mr-1"></i>
                                                                            <?php if ($is_enrolled && $is_completed): ?>
                                                                                <span class="text-green-400">Completed</span>
                                                                            <?php elseif ($is_enrolled): ?>
                                                                                Video Lesson
                                                                            <?php elseif ($videoIndex < $max_preview_videos): ?>
                                                                                Free Preview
                                                                            <?php else: ?>
                                                                                Enroll to Unlock
                                                                            <?php endif; ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php if ($is_enrolled): ?>
                                                                <?php if ($is_completed): ?>
                                                                    <i data-lucide="check" class="skill-icon text-green-400"></i>
                                                                <?php else: ?>
                                                                    <i data-lucide="play" class="skill-icon text-blue-400"></i>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <?php if ($videoIndex < $max_preview_videos): ?>
                                                                    <i data-lucide="play" class="skill-icon text-blue-400"></i>
                                                                <?php else: ?>
                                                                    <i data-lucide="lock" class="skill-icon text-gray-500"></i>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="text-gray-500 text-center py-8">No videos available for this phase.</p>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-gray-500 text-center py-8">No phases available for this roadmap.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!$is_enrolled): ?>
                        <!-- Enrollment CTA -->
                        <div class="space-y-6">
                            <div class="rounded-lg border border-blue-500/30 bg-gray-900 p-8">
                                <div class="flex flex-col lg:flex-row items-center justify-between gap-6">
                                    <div class="flex-1">
                                        <h3 class="text-2xl font-bold text-white mb-3">
                                            <?php if ($roadmap['price'] > 0): ?>
                                                Start Your Learning Journey Today!
                                            <?php else: ?>
                                                Start Learning for Free!
                                            <?php endif; ?>
                                        </h3>
                                        <p class="text-gray-300 mb-4">
                                            Join <?php echo $roadmap['enrollment_count'] ?? 0; ?> students who have already enrolled in this comprehensive learning path.
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
                                                Track your progress
                                            </li>
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
                                                Get Started Free
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
            let phases = <?php echo json_encode($phases); ?>;
            let isEnrolled = <?php echo $is_enrolled ? 'true' : 'false'; ?>;
            
            // Function to play video preview (for non-enrolled users)
            window.playVideoPreview = function(videoUrl, videoTitle, duration, phaseTitle) {
                const modal = document.getElementById('videoPreviewModal');
                const player = document.getElementById('videoPreviewPlayer');
                const title = document.getElementById('videoPreviewTitle');
                const durationElem = document.getElementById('videoPreviewDuration');
                const phaseElem = document.getElementById('videoPreviewPhase');
                
                // Extract YouTube video ID if it's a YouTube URL
                let embedUrl = videoUrl;
                if (videoUrl.includes('youtube.com') || videoUrl.includes('youtu.be')) {
                    const videoId = extractYouTubeId(videoUrl);
                    embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0&modestbranding=1&showinfo=0`;
                }
                
                player.innerHTML = `<iframe src="${embedUrl}" allow="autoplay; encrypted-media" allowfullscreen></iframe>`;
                title.textContent = videoTitle;
                durationElem.textContent = duration + ' minutes';
                phaseElem.textContent = 'Phase: ' + phaseTitle;
                
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
            
            // Function to extract YouTube video ID
            function extractYouTubeId(url) {
                const regExp = /^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/;
                const match = url.match(regExp);
                return (match && match[7].length === 11) ? match[7] : null;
            }
            
            // Function to close video preview modal
            window.closeVideoPreview = function() {
                const modal = document.getElementById('videoPreviewModal');
                const player = document.getElementById('videoPreviewPlayer');
                
                player.innerHTML = '';
                modal.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
            
            // Function to select phase
            window.selectPhase = function(phaseIndex) {
                const phase = phases[phaseIndex];
                const isUnlocked = isEnrolled || phaseIndex < 2;
                
                // Update active state in sidebar
                document.querySelectorAll('.phase-sidebar button').forEach((btn, index) => {
                    if (index === phaseIndex) {
                        btn.classList.add('bg-blue-900/30', 'border', 'border-blue-500/30');
                        btn.classList.remove('hover:bg-gray-800');
                        btn.querySelector('span').classList.add('text-white');
                        btn.querySelector('span').classList.remove('text-gray-300');
                    } else {
                        btn.classList.remove('bg-blue-900/30', 'border', 'border-blue-500/30');
                        btn.classList.add('hover:bg-gray-800');
                        btn.querySelector('span').classList.remove('text-white');
                        btn.querySelector('span').classList.add('text-gray-300');
                    }
                });
                
                // Update phase content (simplified for demo)
                const phaseContent = document.getElementById('phaseContent');
                let videosHTML = '';
                
                if (phase.videos && phase.videos.length > 0) {
                    const maxPreviewVideos = 2;
                    phase.videos.forEach((video, videoIndex) => {
                        const videoData = {
                            full_url: video.full_url,
                            title: video.title.replace(/'/g, "\\'"),
                            duration: video.duration,
                            id: video.id
                        };
                        
                        videosHTML += `
                            <div onclick="${isEnrolled ? 
                                `window.location.href='<?php echo $BASE_PATH; ?>/student/roadmap_player.php?id=<?php echo $roadmap_id; ?>&video=${video.id}'` : 
                                (videoIndex < maxPreviewVideos ? 
                                    `playVideoPreview('${videoData.full_url}', '${videoData.title}', ${videoData.duration}, '${phase.title.replace(/'/g, "\\'")}')` : 
                                    'handleEnrollment()')}" 
                                class="video-item p-4 flex items-center justify-between hover:bg-gray-800/50 transition-colors cursor-pointer">
                                <div class="flex items-center space-x-4">
                                    <div class="relative">
                                        ${isUnlocked ? 
                                            (isEnrolled ? 
                                                '<i data-lucide="play-circle" class="skill-icon text-blue-400 text-2xl"></i>' : 
                                                (videoIndex < maxPreviewVideos ? 
                                                    '<i data-lucide="play-circle" class="skill-icon text-blue-400 text-2xl"></i>' : 
                                                    '<i data-lucide="lock" class="skill-icon text-gray-500 text-2xl"></i>')) : 
                                            '<i data-lucide="lock" class="skill-icon text-gray-500 text-2xl"></i>'}
                                        <span class="absolute -bottom-1 -right-1 text-xs bg-gray-800 text-gray-300 px-1 rounded">
                                            ${String(videoIndex + 1).padStart(2, '0')}
                                        </span>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-white">${video.title}</h4>
                                        <div class="flex items-center space-x-3 text-sm text-gray-400 mt-1">
                                            <span class="flex items-center">
                                                <i data-lucide="clock" class="skill-icon mr-1"></i>
                                                ${video.duration} min
                                            </span>
                                            <span class="flex items-center">
                                                <i data-lucide="video" class="skill-icon mr-1"></i>
                                                ${isEnrolled ? 'Video Lesson' : 
                                                 (videoIndex < maxPreviewVideos ? 'Free Preview' : 'Enroll to Unlock')}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                ${isEnrolled ? 
                                    '<i data-lucide="play" class="skill-icon text-blue-400"></i>' : 
                                    (videoIndex < maxPreviewVideos ? 
                                        '<i data-lucide="play" class="skill-icon text-blue-400"></i>' : 
                                        '<i data-lucide="lock" class="skill-icon text-gray-500"></i>')}
                            </div>
                        `;
                    });
                } else {
                    videosHTML = '<p class="text-gray-500 text-center py-8">No videos available for this phase.</p>';
                }
                
                phaseContent.innerHTML = `
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-2xl font-bold text-white">Phase ${phaseIndex + 1}: ${phase.title}</h3>
                            <span class="text-sm px-3 py-1 rounded-full ${isUnlocked ? 'bg-green-900/30 text-green-400' : 'bg-gray-800 text-gray-400'}">
                                ${isUnlocked ? 'Unlocked' : 'Locked'}
                            </span>
                        </div>
                        <p class="text-gray-300 mb-6">
                            This phase covers important concepts and skills. You'll learn through practical examples and hands-on exercises.
                        </p>
                    </div>
                    <div class="space-y-4">
                        ${videosHTML}
                    </div>
                `;
                
                // Re-initialize icons
                lucide.createIcons();
            }
            
            // Enrollment handler
            window.handleEnrollment = function() {
                const roadmapId = <?php echo $roadmap_id; ?>;
                const price = <?php echo $roadmap['price']; ?>;
                
                if (price > 0) {
                    // Redirect to payment page for paid roadmaps
                    window.location.href = '<?php echo $BASE_PATH; ?>/student/enroll.php?id=' + roadmapId;
                } else {
                    // Free enrollment - use AJAX
                    if (confirm('Are you sure you want to enroll in this free roadmap?')) {
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
                    closeVideoPreview();
                }
            });
            
            // Close modal when clicking outside
            document.getElementById('videoPreviewModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeVideoPreview();
                }
            });
        });
    </script>
</body>
</html>