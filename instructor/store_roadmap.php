<?php
// instructor/store_roadmap.php
require_once __DIR__ . '/../auth/middleware.php';
requireInstructor();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: create_roadmap.php');
    exit();
}

$instructor_id = $_SESSION['user_id'];
$upload_dir = __DIR__ . '/../uploads/videos/';

// Basic Roadmap Details
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$duration = trim($_POST['duration'] ?? '');
$price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
$phases_data = $_POST['phases'] ?? [];
$files_data = $_FILES['phases'] ?? [];

// --- Validation ---
$errors = [];
if (empty($title)) $errors[] = 'Roadmap title is required.';
if (empty($description)) $errors[] = 'Roadmap description is required.';
if ($price === false) $errors[] = 'Invalid price provided.';
if (empty($phases_data) || count($phases_data) < 2) $errors[] = 'A roadmap must have at least two phases.';

// Phase and Video structure validation
foreach ($phases_data as $phase_idx => $phase) {
    if (empty(trim($phase['title']))) {
        $errors[] = "Title for Phase " . ($phase_idx + 1) . " is required.";
    }
    if (isset($phase['videos'])) {
        foreach ($phase['videos'] as $video_idx => $video) {
            if (empty(trim($video['title']))) {
                $errors[] = "Title for Video " . ($video_idx + 1) . " in Phase " . ($phase_idx + 1) . " is required.";
            }
            $file = $files_data['tmp_name']['videos'][$video_idx]['file'][$phase_idx] ?? null;
            if (empty($file) || $files_data['error']['videos'][$video_idx]['file'][$phase_idx] !== UPLOAD_ERR_OK) {
                $errors[] = "File for Video " . ($video_idx + 1) . " in Phase " . ($phase_idx + 1) . " is missing or failed to upload.";
            }
        }
    }
}

if (!empty($errors)) {
    $_SESSION['error_message'] = implode("<br>", $errors);
    header('Location: create_roadmap.php'); // Redirect back with errors
    exit();
}

// --- Database Transaction --- //
$pdo->beginTransaction();
try {
    // 1. Insert into `roadmaps` table
    $stmt = $pdo->prepare("INSERT INTO roadmaps (instructor_id, title, description, duration, price, free_phases_count, status, created_at) VALUES (?, ?, ?, ?, ?, 2, 'pending', NOW())");
    $stmt->execute([$instructor_id, $title, $description, $duration, $price]);
    $roadmap_id = $pdo->lastInsertId();

    // 2. Loop through phases and insert them
    foreach ($phases_data as $phase_idx => $phase) {
        $phase_title = trim($phase['title']);
        $phase_order = intval($phase['order']);

        $stmt = $pdo->prepare("INSERT INTO roadmap_phases (roadmap_id, title, phase_order) VALUES (?, ?, ?)");
        $stmt->execute([$roadmap_id, $phase_title, $phase_order]);
        $phase_id = $pdo->lastInsertId();
        
        // 3. Handle video uploads for each phase
        if (isset($phase['videos'])) {
            foreach ($phase['videos'] as $video_idx => $video) {
                $video_title = trim($video['title']);
                
                $file_tmp_name = $files_data['tmp_name']['videos'][$video_idx]['file'][$phase_idx];
                $file_name = $files_data['name']['videos'][$video_idx]['file'][$phase_idx];
                $file_error = $files_data['error']['videos'][$video_idx]['file'][$phase_idx];

                if ($file_error === UPLOAD_ERR_OK) {
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_ext = ['mp4', 'webm', 'mov'];

                    if (in_array($file_ext, $allowed_ext)) {
                        $new_file_name = uniqid('', true) . '.' . $file_ext;
                        $destination = $upload_dir . $new_file_name;

                        if (move_uploaded_file($file_tmp_name, $destination)) {
                            $video_path = 'uploads/videos/' . $new_file_name; // Relative path for DB
                            $stmt = $pdo->prepare("INSERT INTO roadmap_videos (phase_id, title, video_url) VALUES (?, ?, ?)");
                            $stmt->execute([$phase_id, $video_title, $video_path]);
                        } else {
                            throw new Exception("Failed to move uploaded file for video '{$video_title}'.");
                        }
                    } else {
                        throw new Exception("Invalid file type for video '{$video_title}'. Allowed types: " . implode(', ', $allowed_ext));
                    }
                } else {
                     throw new Exception("Upload error for video '{$video_title}'. Error code: {$file_error}");
                }
            }
        }
    }

    $pdo->commit();

    $_SESSION['success_message'] = "Roadmap submitted for admin approval!";
    header('Location: dashboard.php');
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    // Optional: Clean up any files that were successfully moved before the error
    $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
    header('Location: create_roadmap.php');
    exit();
}

?>