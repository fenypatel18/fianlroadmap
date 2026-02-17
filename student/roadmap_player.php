<?php
// student/roadmap_player.php

// --- 1. SETUP & SECURITY ---
session_start();
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
requireStudent();

$student_id = $_SESSION['user_id'];
$roadmap_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$requested_video_id = filter_input(INPUT_GET, 'video', FILTER_VALIDATE_INT);
$BASE_PATH = '/fianlroadmap';

// --- 2. AJAX HANDLER FOR PROGRESS UPDATES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $video_id = $input['video_id'] ?? null;
    $r_id = $input['roadmap_id'] ?? null;

    if ($video_id && $r_id) {
        // Use INSERT...ON DUPLICATE KEY UPDATE to either create or update the progress record.
        $stmt = $pdo->prepare(
            "INSERT INTO progress (student_id, video_id, completed, completed_at) 
             VALUES (?, ?, TRUE, NOW()) 
             ON DUPLICATE KEY UPDATE completed = TRUE, completed_at = NOW()"
        );
        $stmt->execute([$student_id, $video_id]);

        // Send a success response back to the JavaScript.
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Progress saved']);
        exit();
    }
    // Handle error case
    header('Content-Type: application/json', true, 400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit();
}

// --- 3. DATA VALIDATION & INITIAL FETCH ---
if (!$roadmap_id) {
    header('Location: explore_roadmaps.php');
    exit();
}

// Fetch roadmap details, ensuring it's approved.
$stmt = $pdo->prepare("SELECT * FROM roadmaps WHERE id = ? AND status = 'approved'");
$stmt->execute([$roadmap_id]);
$roadmap = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$roadmap) {
    die("Roadmap not found or is not currently available.");
}

// --- 4. ACCESS CONTROL & ENROLLMENT STATUS ---
$stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND roadmap_id = ?");
$stmt->execute([$student_id, $roadmap_id]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
$is_enrolled = (bool)$enrollment;

if (!$is_enrolled) {
    header('Location: view_roadmap.php?id=' . $roadmap_id . '&error=not_enrolled');
    exit();
}

// --- 5. CHECK QUIZ STATUS ---
$stmt = $pdo->prepare("
    SELECT 
        EXISTS(SELECT 1 FROM quiz_attempts WHERE student_id = ? AND roadmap_id = ? AND passed = 1) as has_passed,
        COUNT(*) as attempt_count,
        (SELECT certificate_id FROM quiz_attempts WHERE student_id = ? AND roadmap_id = ? AND passed = 1 ORDER BY attempt_date DESC LIMIT 1) as certificate_id
    FROM quiz_attempts 
    WHERE student_id = ? AND roadmap_id = ?
");
$stmt->execute([$student_id, $roadmap_id, $student_id, $roadmap_id, $student_id, $roadmap_id]);
$quiz_status = $stmt->fetch(PDO::FETCH_ASSOC);

$has_passed_quiz = (bool)$quiz_status['has_passed'];
$attempt_count = (int)$quiz_status['attempt_count'];
$max_attempts = 3;

// --- 6. FETCH FULL CURRICULUM & PROGRESS ---
$curriculum = [];
$phases_stmt = $pdo->prepare("SELECT id, title, phase_order FROM roadmap_phases WHERE roadmap_id = ? ORDER BY phase_order ASC");
$phases_stmt->execute([$roadmap_id]);
$phases = $phases_stmt->fetchAll(PDO::FETCH_ASSOC);

$progress_stmt = $pdo->prepare("SELECT video_id FROM progress WHERE student_id = ? AND completed = TRUE");
$progress_stmt->execute([$student_id]);
$completed_videos = $progress_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// Build the nested curriculum structure
foreach ($phases as $phase) {
    $phase_id = $phase['id'];
    $phase['videos'] = [];
    
    $videos_stmt = $pdo->prepare("SELECT id, title, video_url, video_order FROM roadmap_videos WHERE phase_id = ? ORDER BY video_order ASC");
    $videos_stmt->execute([$phase_id]);
    $videos = $videos_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($videos as &$video) {
        if (strpos($video['video_url'], 'uploads/videos/') === 0) {
            $video['full_url'] = $BASE_PATH . '/' . $video['video_url'];
        } else {
            $video['full_url'] = $video['video_url'];
        }
        $video['is_youtube'] = (strpos($video['video_url'], 'youtube.com') !== false || strpos($video['video_url'], 'youtu.be') !== false);
    }
    unset($video);
    
    $phase['videos'] = $videos;
    $curriculum[] = $phase;
}

// --- 7. DETERMINE THE VIDEO TO PLAY ---
$active_video = null;
$active_phase = null;

function findVideoById($curriculum, $video_id, &$found_phase) {
    foreach ($curriculum as $phase) {
        foreach ($phase['videos'] as $video) {
            if ($video['id'] == $video_id) {
                $found_phase = $phase;
                return $video;
            }
        }
    }
    return null;
}

if ($requested_video_id) {
    $active_video = findVideoById($curriculum, $requested_video_id, $active_phase);
} else {
    foreach ($curriculum as $phase) {
        foreach ($phase['videos'] as $video) {
            if (!in_array($video['id'], $completed_videos)) {
                $active_video = $video;
                $active_phase = $phase;
                break 2;
            }
        }
    }
}

if (!$active_video && !empty($curriculum[0]['videos'][0])) {
    $active_phase = $curriculum[0];
    $active_video = $curriculum[0]['videos'][0];
}

// --- 8. CALCULATE PROGRESS ---
$total_videos = 0;
$completed_count = 0;
foreach ($curriculum as $phase) {
    $total_videos += count($phase['videos']);
    foreach ($phase['videos'] as $video) {
        if (in_array($video['id'], $completed_videos)) {
            $completed_count++;
        }
    }
}
$progress_percentage = $total_videos > 0 ? round(($completed_count / $total_videos) * 100) : 0;
$all_videos_completed = ($total_videos > 0 && $completed_count >= $total_videos);

// --- 9. STUDENT PROFILE ---
$stmt = $pdo->prepare("SELECT name, profile_picture FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $student['profile_picture'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($student['name']) . '&background=random';
?>

<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning: <?php echo htmlspecialchars($roadmap['title']); ?> | YourRoadmap</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- Video.js for HTML5 video player -->
    <link href="https://vjs.zencdn.net/8.0.4/video-js.css" rel="stylesheet" />
    
    <style>
        :root {
            --background: 19 20 23;
            --foreground: 255 255 255;
        }
        
        body {
            background-color: rgb(var(--background));
            color: rgb(var(--foreground));
            font-family: system-ui, -apple-system, sans-serif;
            height: 100vh;
            overflow: hidden;
        }
        
        .skill-icon {
            width: 20px;
            height: 20px;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }
        
        .progress-bar {
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
            transition: width 0.3s ease;
        }
        
        .video-container {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 56.25%;
            background: #000;
        }
        
        .video-container iframe,
        .video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .video-js {
            width: 100% !important;
            height: 100% !important;
        }
        
        .vjs-theme-dark {
            --vjs-theme-primary: #3b82f6;
            --vjs-theme-secondary: #7c3aed;
        }
        
        .sidebar-scroll {
            scrollbar-width: thin;
            scrollbar-color: rgba(124, 58, 237, 0.5) rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-scroll::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar-scroll::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(124, 58, 237, 0.5);
            border-radius: 3px;
        }
        
        .lock-icon {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 0.7; }
            50% { opacity: 1; }
            100% { opacity: 0.7; }
        }
    </style>
</head>
<body class="flex h-screen">
    <!-- Sidebar: Curriculum -->
    <aside class="w-80 bg-gray-900 border-r border-gray-800 flex flex-col flex-shrink-0">
        <!-- Header -->
        <div class="p-4 border-b border-gray-800">
            <div class="flex items-center justify-between mb-4">
                <a href="<?php echo $BASE_PATH; ?>/student/dashboard.php" class="flex items-center text-gray-400 hover:text-white transition-colors">
                    <i data-lucide="arrow-left" class="skill-icon mr-2"></i>
                    Back
                </a>
                <div class="flex items-center space-x-3">
                    <img class="h-8 w-8 rounded-full border-2 border-purple-500/50" 
                         src="<?php echo htmlspecialchars($profile_picture); ?>" 
                         alt="<?php echo htmlspecialchars($student['name']); ?>"
                         onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($student['name']); ?>&background=random';">
                </div>
            </div>
            <h2 class="text-xl font-bold text-white truncate"><?php echo htmlspecialchars($roadmap['title']); ?></h2>
            
            <!-- Progress Bar -->
            <div class="mt-4">
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-400">Your Progress</span>
                    <span class="font-medium text-white"><?php echo $progress_percentage; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
                </div>
                <div class="text-xs text-gray-400 mt-1">
                    <?php echo $completed_count; ?> of <?php echo $total_videos; ?> lessons completed
                </div>
                
                <!-- Quiz Status -->
                <div class="mt-4 pt-4 border-t border-gray-800">
                    <div class="text-sm">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-gray-400">Quiz Status:</span>
                            <?php if ($has_passed_quiz): ?>
                                <span class="text-green-400 font-medium">
                                    <i data-lucide="check-circle" class="skill-icon inline mr-1"></i>
                                    Passed
                                </span>
                            <?php elseif ($attempt_count > 0): ?>
                                <span class="text-amber-400 font-medium">
                                    <i data-lucide="alert-circle" class="skill-icon inline mr-1"></i>
                                    Attempt <?php echo $attempt_count; ?>/<?php echo $max_attempts; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-blue-400 font-medium">
                                    <i data-lucide="clipboard" class="skill-icon inline mr-1"></i>
                                    Not Attempted
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Curriculum Navigation -->
        <div class="flex-1 overflow-y-auto sidebar-scroll">
            <nav class="p-4">
                <ul class="space-y-6">
                    <?php foreach ($curriculum as $phase): ?>
                        <?php 
                            $phase_completed = 0;
                            $phase_total = count($phase['videos']);
                            foreach ($phase['videos'] as $video) {
                                if (in_array($video['id'], $completed_videos)) {
                                    $phase_completed++;
                                }
                            }
                            $phase_progress = $phase_total > 0 ? round(($phase_completed / $phase_total) * 100) : 0;
                        ?>
                        <li class="space-y-2">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">
                                    Phase <?php echo $phase['phase_order']; ?>: <?php echo htmlspecialchars($phase['title']); ?>
                                </h3>
                                <span class="text-xs text-gray-500"><?php echo $phase_progress; ?>%</span>
                            </div>
                            
                            <ul class="space-y-1">
                                <?php foreach ($phase['videos'] as $video): ?>
                                    <?php
                                        $is_active = $active_video && $video['id'] == $active_video['id'];
                                        $is_completed = in_array($video['id'], $completed_videos);
                                    ?>
                                    <li>
                                        <a href="?id=<?php echo $roadmap_id; ?>&video=<?php echo $video['id']; ?>" 
                                           class="flex items-center text-sm px-3 py-2 rounded-lg transition-colors duration-200 <?php echo $is_active ? 'bg-indigo-600 text-white' : ($is_completed ? 'hover:bg-gray-800 text-green-400' : 'hover:bg-gray-800 text-gray-300'); ?>">
                                            
                                            <?php if ($is_completed): ?>
                                                <i data-lucide="check-circle" class="skill-icon mr-3 flex-shrink-0"></i>
                                            <?php else: ?>
                                                <i data-lucide="play-circle" class="skill-icon mr-3 flex-shrink-0 text-gray-500"></i>
                                            <?php endif; ?>
                                            
                                            <span class="flex-1 truncate"><?php echo htmlspecialchars($video['title']); ?></span>
                                            
                                            <?php if ($is_active): ?>
                                                <i data-lucide="play" class="skill-icon ml-2 flex-shrink-0"></i>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
        
        <!-- Footer Actions -->
        <!-- Footer Actions -->
<div class="p-4 border-t border-gray-800">
    <?php if ($has_passed_quiz): ?>
        <!-- User has passed quiz - Show Certificate Button -->
        <a href="<?php echo $BASE_PATH; ?>/student/certificate.php?id=<?php echo $roadmap_id; ?>" 
           class="w-full flex items-center justify-center px-4 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-lg hover:opacity-90 transition-opacity">
            <i data-lucide="award" class="skill-icon mr-2"></i>
            View Certificate
        </a>
        
        <p class="text-xs text-center text-gray-400 mt-2">
            You passed the quiz! Download your certificate.
        </p>
        
    <?php elseif ($all_videos_completed): ?>
        <!-- All videos completed - Show Quiz Button -->
        <?php if ($attempt_count < $max_attempts): ?>
            <a href="<?php echo $BASE_PATH; ?>/student/quiz.php?id=<?php echo $roadmap_id; ?>" 
               class="w-full flex items-center justify-center px-4 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-lg hover:opacity-90 transition-opacity">
                <i data-lucide="clipboard-check" class="skill-icon mr-2"></i>
                Take Final Quiz
            </a>
            
            <p class="text-xs text-center text-gray-400 mt-2">
                <?php echo $max_attempts - $attempt_count; ?> attempt(s) remaining
            </p>
        <?php else: ?>
            <button class="w-full flex items-center justify-center px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg cursor-not-allowed opacity-75">
                <i data-lucide="x-circle" class="skill-icon mr-2"></i>
                No Attempts Remaining
            </button>
            
            <p class="text-xs text-center text-red-400 mt-2">
                You've used all <?php echo $max_attempts; ?> attempts
            </p>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- Videos not completed - Show Locked Button -->
        <button class="w-full flex items-center justify-center px-4 py-3 bg-gradient-to-r from-gray-700 to-gray-800 text-gray-400 rounded-lg cursor-not-allowed"
                onclick="alert('Complete all <?php echo $total_videos; ?> videos to unlock the quiz!')">
            <i data-lucide="lock" class="skill-icon mr-2 lock-icon"></i>
            Complete All Videos First
        </button>
        
        <p class="text-xs text-center text-gray-500 mt-2">
            <?php echo $total_videos - $completed_count; ?> videos remaining
        </p>
    <?php endif; ?>
</div>
    </aside>

    <!-- Main Content: Video Player -->
    <main class="flex-1 flex flex-col h-screen bg-black">
        <?php if ($active_video): ?>
            <!-- Video Player -->
            <div class="flex-1">
                <div class="video-container">
                    <?php if ($active_video['is_youtube']): ?>
                        <!-- YouTube Video -->
                        <?php 
                            $url = $active_video['full_url'];
                            $video_id = '';
                            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches)) {
                                $video_id = $matches[1];
                            }
                        ?>
                        <iframe src="https://www.youtube.com/embed/<?php echo $video_id; ?>?autoplay=1&rel=0&modestbranding=1&showinfo=0" 
                                allow="autoplay; encrypted-media" 
                                allowfullscreen
                                id="youtube-player">
                        </iframe>
                    <?php else: ?>
                        <!-- Local MP4 Video -->
                        <video id="html5-video-player" 
                               class="video-js vjs-theme-dark"
                               controls 
                               preload="auto"
                               autoplay
                               data-video-id="<?php echo $active_video['id']; ?>"
                               data-setup='{"fluid": true}'>
                            <source src="<?php echo htmlspecialchars($active_video['full_url']); ?>" type="video/mp4">
                            <p class="vjs-no-js">
                                To view this video please enable JavaScript, and consider upgrading to a
                                web browser that <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>
                            </p>
                        </video>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Video Info -->
            <div class="p-6 bg-gray-900 border-t border-gray-800">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h1 class="text-2xl font-bold text-white mb-2"><?php echo htmlspecialchars($active_video['title']); ?></h1>
                        <div class="flex items-center space-x-4 text-gray-400">
                            <span class="flex items-center">
                                <i data-lucide="layers" class="skill-icon mr-2"></i>
                                Phase <?php echo $active_phase['phase_order']; ?>: <?php echo htmlspecialchars($active_phase['title']); ?>
                            </span>
                            <span class="flex items-center">
                                <i data-lucide="check-circle" class="skill-icon mr-2"></i>
                                <?php echo in_array($active_video['id'], $completed_videos) ? 'Completed' : 'In Progress'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Next Video Button -->
                    <div id="nextVideoContainer">
                        <!-- Dynamic content will be inserted here -->
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- No Video Selected -->
            <div class="flex-1 flex items-center justify-center text-center">
                <div>
                    <i data-lucide="video" class="skill-icon text-gray-600 text-6xl mx-auto mb-4"></i>
                    <h1 class="text-2xl font-bold text-white mb-2">Select a video to begin</h1>
                    <p class="text-gray-400">Choose a lesson from the sidebar to start learning</p>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Video.js library -->
    <script src="https://vjs.zencdn.net/8.0.4/video.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            lucide.createIcons();
            
            const roadmapId = <?php echo json_encode($roadmap_id); ?>;
            const curriculumData = <?php echo json_encode($curriculum); ?>;
            const completedVideos = <?php echo json_encode($completed_videos); ?>;
            const totalVideos = <?php echo $total_videos; ?>;
            let currentVideoId = <?php echo $active_video ? $active_video['id'] : 'null'; ?>;
            let videoPlayer = null;
            
            // Function to check quiz status
            function checkQuizStatus() {
                return fetch(`check_quiz_status.php?roadmap_id=${roadmapId}`)
                    .then(response => response.json())
                    .then(data => data);
            }
            
            // Function to show appropriate button after video completion
            async function showNextActionButton(currentVideoId) {
                const nextVideo = findNextVideo(currentVideoId);
                const nextVideoContainer = document.getElementById('nextVideoContainer');
                
                if (nextVideo && nextVideoContainer) {
                    // Show next video button
                    nextVideoContainer.innerHTML = `
                        <a href="?id=${roadmapId}&video=${nextVideo.id}" 
                           class="flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:opacity-90 transition-opacity">
                            <i data-lucide="skip-forward" class="skill-icon mr-2"></i>
                            Next Lesson
                        </a>
                    `;
                    lucide.createIcons();
                } else if (nextVideoContainer) {
                    // All videos completed - check what to show
                    const quizStatus = await checkQuizStatus();
                    
                    if (quizStatus.has_passed) {
                        // User passed quiz - show certificate button
                        nextVideoContainer.innerHTML = `
                            <a href="<?php echo $BASE_PATH; ?>/student/certificate.php?id=${roadmapId}" 
                               class="flex items-center px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-lg hover:opacity-90 transition-opacity">
                                <i data-lucide="award" class="skill-icon mr-2"></i>
                                Get Certificate
                            </a>
                        `;
                    } else if (quizStatus.attempt_count < 3) {
                        // Can take quiz - show quiz button
                        nextVideoContainer.innerHTML = `
                            <a href="<?php echo $BASE_PATH; ?>/student/quiz.php?id=${roadmapId}" 
                               class="flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-lg hover:opacity-90 transition-opacity">
                                <i data-lucide="clipboard-check" class="skill-icon mr-2"></i>
                                Take Final Quiz
                            </a>
                        `;
                    } else {
                        // No attempts left
                        nextVideoContainer.innerHTML = `
                            <button class="flex items-center px-6 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg cursor-not-allowed opacity-75">
                                <i data-lucide="x-circle" class="skill-icon mr-2"></i>
                                No Quiz Attempts Left
                            </button>
                        `;
                    }
                    lucide.createIcons();
                }
            }
            
            // Initialize video.js player if it exists
            const videoElement = document.getElementById('html5-video-player');
            if (videoElement) {
                videoPlayer = videojs('html5-video-player', {
                    controls: true,
                    autoplay: true,
                    preload: 'auto',
                    fluid: true,
                    responsive: true,
                    playbackRates: [0.5, 0.75, 1, 1.25, 1.5, 2],
                    userActions: {
                        hotkeys: true
                    }
                });
                
                // Listen for video end
                videoPlayer.on('ended', function() {
                    markVideoComplete(currentVideoId);
                    showNextActionButton(currentVideoId);
                });
                
                // Listen for time updates to track progress
                let progressSent = false;
                videoPlayer.on('timeupdate', function() {
                    const currentTime = videoPlayer.currentTime();
                    const duration = videoPlayer.duration();
                    
                    // If user watched more than 90% of video, mark as complete
                    if (duration > 0 && currentTime > 0 && !progressSent) {
                        const progressPercent = (currentTime / duration) * 100;
                        if (progressPercent >= 90 && !completedVideos.includes(parseInt(currentVideoId))) {
                            markVideoComplete(currentVideoId);
                            progressSent = true;
                        }
                    }
                });
            }
            
            // For YouTube videos
            const youtubePlayer = document.getElementById('youtube-player');
            if (youtubePlayer) {
                // Add manual complete button for YouTube videos
                const nextVideoContainer = document.getElementById('nextVideoContainer');
                if (nextVideoContainer) {
                    nextVideoContainer.innerHTML = `
                        <button onclick="markVideoComplete(<?php echo $active_video['id']; ?>)" 
                                class="flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i data-lucide="check-circle" class="skill-icon mr-2"></i>
                            Mark as Complete
                        </button>
                    `;
                    lucide.createIcons();
                }
            }
            
            // Function to mark video as complete
            async function markVideoComplete(videoId) {
                if (!videoId) return;
                
                try {
                    const response = await fetch('roadmap_player.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ video_id: videoId, roadmap_id: roadmapId })
                    });
                    
                    const data = await response.json();
                    
                    if (data.status === 'success') {
                        // Update UI
                        const videoLink = document.querySelector(`a[href="?id=${roadmapId}&video=${videoId}"]`);
                        if (videoLink) {
                            videoLink.classList.remove('text-gray-300', 'hover:bg-gray-800');
                            videoLink.classList.add('text-green-400', 'hover:bg-gray-800');
                            
                            // Update icon
                            const icon = videoLink.querySelector('i');
                            if (icon) {
                                icon.setAttribute('data-lucide', 'check-circle');
                                lucide.createIcons();
                            }
                        }
                        
                        // Show next action button
                        await showNextActionButton(videoId);
                    }
                } catch (error) {
                    console.error('Error saving progress:', error);
                }
            }
            
            // Function to find next video
            function findNextVideo(currentVideoId) {
                let found = false;
                
                for (const phase of curriculumData) {
                    for (const video of phase.videos) {
                        if (found) {
                            return video;
                        }
                        if (video.id == currentVideoId) {
                            found = true;
                        }
                    }
                }
                return null; // No next video found (last video)
            }
            
            // Auto-show next video button if current video is already completed
            if (currentVideoId && completedVideos.includes(parseInt(currentVideoId))) {
                showNextActionButton(currentVideoId);
            }
            
            // Global function for manual completion
            window.markVideoComplete = markVideoComplete;
            
            // Clean up video player on page unload
            window.addEventListener('beforeunload', function() {
                if (videoPlayer) {
                    videoPlayer.dispose();
                }
            });
        });
    </script>
</body>
</html>
