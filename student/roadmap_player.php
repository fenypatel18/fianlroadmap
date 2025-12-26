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

// --- 2. AJAX HANDLER FOR PROGRESS UPDATES ---
// This block handles POST requests from the video player to mark videos as complete.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $video_id = $input['video_id'] ?? null;
    $r_id = $input['roadmap_id'] ?? null;

    if ($video_id && $r_id) {
        // Use INSERT...ON DUPLICATE KEY UPDATE to either create or update the progress record.
        $stmt = $pdo->prepare(
            "INSERT INTO progress (student_id, roadmap_id, video_id, completed_at, last_watched_at) 
             VALUES (?, ?, ?, NOW(), NOW()) 
             ON DUPLICATE KEY UPDATE completed_at = NOW(), last_watched_at = NOW()"
        );
        $stmt->execute([$student_id, $r_id, $video_id]);
        
        // Also update the main enrollment record with the last watched video ID for quick resuming.
        $stmt = $pdo->prepare("UPDATE enrollments SET last_watched_video_id = ? WHERE student_id = ? AND roadmap_id = ?");
        $stmt->execute([$video_id, $student_id, $r_id]);

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
// Check if the student is enrolled in this roadmap.
$stmt = $pdo->prepare("SELECT id, last_watched_video_id FROM enrollments WHERE student_id = ? AND roadmap_id = ?");
$stmt->execute([$student_id, $roadmap_id]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
$is_enrolled = (bool)$enrollment;

// --- 5. FETCH FULL CURRICULUM & PROGRESS ---
$curriculum = [];
// Fetch all phases for the roadmap
$phases_stmt = $pdo->prepare("SELECT id, title, phase_order FROM roadmap_phases WHERE roadmap_id = ? ORDER BY phase_order ASC");
$phases_stmt->execute([$roadmap_id]);
$phases = $phases_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch student's progress for this roadmap into an array for easy lookup
$progress_stmt = $pdo->prepare("SELECT video_id FROM progress WHERE student_id = ? AND roadmap_id = ? AND completed_at IS NOT NULL");
$progress_stmt->execute([$student_id, $roadmap_id]);
$completed_videos = $progress_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// Build the nested curriculum structure (Phases -> Videos)
foreach ($phases as $phase) {
    $phase_id = $phase['id'];
    $phase['videos'] = [];
    
    // Fetch videos for each phase
    $videos_stmt = $pdo->prepare("SELECT id, title, video_filename FROM roadmap_videos WHERE phase_id = ?");
    $videos_stmt->execute([$phase_id]);
    $videos = $videos_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $phase['videos'] = $videos;
    $curriculum[] = $phase;
}


// --- 6. DETERMINE THE VIDEO TO PLAY ---
$active_video = null;
$active_phase = null;

// Function to find a video by ID in the curriculum
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

// Logic to select which video to show
if ($requested_video_id) {
    // If a specific video is requested via URL, try to load it.
    $active_video = findVideoById($curriculum, $requested_video_id, $active_phase);
} elseif ($is_enrolled && $enrollment['last_watched_video_id']) {
    // If enrolled and has a "last watched" video, resume from there.
    $active_video = findVideoById($curriculum, $enrollment['last_watched_video_id'], $active_phase);
}

// If no video is selected yet, find the first available (or uncompleted) video.
if (!$active_video) {
    foreach ($curriculum as $phase) {
        $phase_is_accessible = $is_enrolled || $phase['phase_order'] <= 2;
        if ($phase_is_accessible) {
            foreach ($phase['videos'] as $video) {
                 if ($is_enrolled && !in_array($video['id'], $completed_videos)) {
                    $active_video = $video;
                    $active_phase = $phase;
                    break 2;
                 }
                 if (!$is_enrolled) { // For non-enrolled, just play the first one
                    $active_video = $video;
                    $active_phase = $phase;
                    break 2;
                 }
            }
        }
    }
}
// Fallback: if all are completed or something went wrong, just play the very first video
if (!$active_video && !empty($curriculum[0]['videos'][0])) {
    $active_phase = $curriculum[0];
    $active_video = $curriculum[0]['videos'][0];
}

// Final access check: is the determined active video actually accessible?
if ($active_video) {
    $phase_order_of_active_video = $active_phase['phase_order'];
    if (!$is_enrolled && $phase_order_of_active_video > 2) {
        // If not enrolled and trying to access a locked video, block it.
        die("You must enroll to view this content.");
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player: <?php echo htmlspecialchars($roadmap['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white font-sans flex h-screen">

    <!-- Sidebar: Curriculum -->
    <aside class="w-80 bg-gray-800 h-full overflow-y-auto flex-shrink-0">
        <div class="p-4 border-b border-gray-700">
            <h2 class="text-xl font-bold"><?php echo htmlspecialchars($roadmap['title']); ?></h2>
            <a href="/student/dashboard.php" class="text-sm text-indigo-400 hover:underline">Back to Dashboard</a>
        </div>
        <nav class="p-2">
            <ul>
                <?php foreach ($curriculum as $phase): ?>
                    <?php 
                        // Determine if the phase is locked for the user
                        $is_phase_locked = !$is_enrolled && $phase['phase_order'] > 2;
                    ?>
                    <li class="mb-4">
                        <h3 class="flex justify-between items-center text-lg font-semibold px-2 py-2 <?php echo $is_phase_locked ? 'text-gray-500' : 'text-white'; ?>">
                            Phase <?php echo $phase['phase_order']; ?>: <?php echo htmlspecialchars($phase['title']); ?>
                            <?php if ($is_phase_locked): ?>
                                <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a5 5 0 00-5 5v2H3a2 2 0 00-2 2v5a2 2 0 002 2h14a2 2 0 002-2v-5a2 2 0 00-2-2h-2V7a5 5 0 00-5-5zm-3 7v2h6V9H7z"></path></svg>
                            <?php endif; ?>
                        </h3>
                        <?php if (!$is_phase_locked): ?>
                            <ul>
                                <?php foreach ($phase['videos'] as $video): ?>
                                    <?php
                                        $is_active = $active_video && $video['id'] == $active_video['id'];
                                        $is_completed = in_array($video['id'], $completed_videos);
                                    ?>
                                    <li>
                                        <a href="?id=<?php echo $roadmap_id; ?>&video=<?php echo $video['id']; ?>" 
                                           class="flex items-center text-sm px-4 py-3 rounded-md transition-colors duration-200 <?php echo $is_active ? 'bg-indigo-600' : 'hover:bg-gray-700'; ?>">
                                            
                                            <!-- Icon for completion status -->
                                            <?php if ($is_completed): ?>
                                                <svg class="w-5 h-5 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            <?php else: ?>
                                                 <svg class="w-5 h-5 text-gray-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            <?php endif; ?>
                                            
                                            <span class="flex-1"><?php echo htmlspecialchars($video['title']); ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </aside>

    <!-- Main Content: Video Player -->
    <main class="flex-1 flex flex-col h-screen">
        <?php if ($active_video): ?>
            <div class="bg-black aspect-video">
                <video id="video-player" 
                       src="/uploads/videos/<?php echo htmlspecialchars($active_video['video_filename']); ?>" 
                       data-video-id="<?php echo $active_video['id']; ?>"
                       controls autoplay class="w-full h-full">
                    Your browser does not support the video tag.
                </video>
            </div>
            <div class="p-6 bg-gray-800 border-t border-gray-700 flex-grow">
                <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($active_video['title']); ?></h1>
                <h2 class="text-lg text-indigo-400 font-semibold">
                    From Phase <?php echo $active_phase['phase_order']; ?>: <?php echo htmlspecialchars($active_phase['title']); ?>
                </h2>
            </div>
        <?php else: ?>
            <div class="flex-1 flex items-center justify-center text-center">
                <div>
                    <h1 class="text-2xl font-bold">Select a video to begin.</h1>
                    <p class="text-gray-400">If you are not enrolled, you can access the first two phases for free.</p>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const videoPlayer = document.getElementById('video-player');
        if (!videoPlayer) return;

        // --- PROGRESS TRACKING ---
        // Listen for when the video has ended.
        videoPlayer.addEventListener('ended', function() {
            const videoId = this.dataset.videoId;
            const roadmapId = <?php echo json_encode($roadmap_id); ?>;

            // Send video completion data to the server via Fetch API
            fetch('roadmap_player.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ video_id: videoId, roadmap_id: roadmapId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log('Progress saved.');
                    // Find and navigate to the next video
                    findAndPlayNextVideo(videoId);
                }
            })
            .catch(error => console.error('Error saving progress:', error));
        });

        // --- AUTO-PLAY NEXT VIDEO ---
        const curriculumData = <?php echo json_encode($curriculum); ?>;
        
        function findAndPlayNextVideo(currentVideoId) {
            let found = false;
            let nextVideoUrl = null;

            for (const phase of curriculumData) {
                for (const video of phase.videos) {
                    if (found) {
                        // The previous video in the loop was the one that just ended, so this is the next one.
                        nextVideoUrl = `?id=${roadmapId}&video=${video.id}`;
                        break;
                    }
                    if (video.id == currentVideoId) {
                        found = true;
                    }
                }
                if (nextVideoUrl) break;
            }

            if (nextVideoUrl) {
                // If a next video is found, go to its URL.
                window.location.href = nextVideoUrl;
            } else {
                // If it was the last video, maybe go to a "Roadmap Complete" page.
                alert("Congratulations! You've completed the roadmap!");
                window.location.href = '/student/dashboard.php';
            }
        }
    });
    </script>
</body>
</html>
