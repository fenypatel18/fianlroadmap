<?php
// instructor/save_roadmap.php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
requireInstructor();

$instructor_id = $_SESSION['user_id'];

// Debug log
error_log("=== ROADMAP SUBMISSION START ===");
error_log("POST keys: " . implode(', ', array_keys($_POST)));
error_log("FILES keys: " . implode(', ', array_keys($_FILES)));

// Function to set error and redirect
function setError($message) {
    $_SESSION['toast'] = [
        'message' => $message,
        'type' => 'error'
    ];
    $_SESSION['form_data'] = $_POST;
    header('Location: create_roadmap.php');
    exit();
}

function setSuccess($message) {
    $_SESSION['toast'] = [
        'message' => $message,
        'type' => 'success'
    ];
    header('Location: dashboard.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setError('Invalid request method.');
}

// Get form data - using $_POST directly
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$duration = $_POST['duration'] ?? '';
$price = $_POST['price'] ?? '0.00';
$phases = $_POST['phases'] ?? [];

error_log("Title: $title");
error_log("Description length: " . strlen($description));
error_log("Duration: $duration");
error_log("Price: $price");
error_log("Phases count: " . count($phases));

// Basic validation
if (empty(trim($title))) {
    setError('Roadmap title is required.');
}

if (empty(trim($description))) {
    setError('Roadmap description is required.');
}

if (empty(trim($duration))) {
    setError('Estimated duration is required.');
}

if (!is_numeric($price) || floatval($price) < 0) {
    setError('Please enter a valid price (0 or greater).');
}

if (count($phases) < 2) {
    setError('A roadmap must have at least two phases.');
}

// Validate each phase
foreach ($phases as $phaseIndex => $phase) {
    if (empty(trim($phase['title'] ?? ''))) {
        setError("Phase title is required for Phase " . ($phaseIndex + 1) . ".");
    }
    
    if (!isset($phase['videos']) || !is_array($phase['videos']) || count($phase['videos']) === 0) {
        setError("At least one video is required for Phase " . ($phaseIndex + 1) . ".");
    }
    
    // Validate video titles
    foreach ($phase['videos'] as $videoIndex => $video) {
        if (empty(trim($video['title'] ?? ''))) {
            setError("Video title is required for video in Phase " . ($phaseIndex + 1) . ".");
        }
    }
}

// Prepare upload directory
$uploadDir = __DIR__ . '/../uploads/videos/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Start database transaction
$pdo->beginTransaction();

try {
    // 1. Insert roadmap
    $stmt = $pdo->prepare("
        INSERT INTO roadmaps (instructor_id, title, description, duration, price, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    if (!$stmt->execute([$instructor_id, $title, $description, $duration, $price])) {
        throw new Exception("Failed to create roadmap.");
    }
    
    $roadmapId = $pdo->lastInsertId();
    error_log("Roadmap created with ID: $roadmapId");
    
    // 2. Insert phases
    foreach ($phases as $phaseIndex => $phase) {
        $phaseTitle = trim($phase['title']);
        $phaseOrder = (int)($phase['order'] ?? $phaseIndex + 1);
        
        $stmt = $pdo->prepare("
            INSERT INTO roadmap_phases (roadmap_id, title, phase_order, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        if (!$stmt->execute([$roadmapId, $phaseTitle, $phaseOrder])) {
            throw new Exception("Failed to create phase: $phaseTitle");
        }
        
        $phaseId = $pdo->lastInsertId();
        error_log("Phase created with ID: $phaseId");
        
        // 3. Handle videos for this phase
        if (isset($phase['videos']) && is_array($phase['videos'])) {
            foreach ($phase['videos'] as $videoIndex => $video) {
                $videoTitle = trim($video['title']);
                
                // Get uploaded file - using the simpler naming convention
                $fileKey = "video_files[$phaseIndex][$videoIndex]";
                
                if (!isset($_FILES['video_files']['name'][$phaseIndex][$videoIndex])) {
                    throw new Exception("No video file uploaded for: $videoTitle");
                }
                
                $fileName = $_FILES['video_files']['name'][$phaseIndex][$videoIndex];
                $fileTmpName = $_FILES['video_files']['tmp_name'][$phaseIndex][$videoIndex];
                $fileError = $_FILES['video_files']['error'][$phaseIndex][$videoIndex];
                $fileSize = $_FILES['video_files']['size'][$phaseIndex][$videoIndex];
                
                if ($fileError !== UPLOAD_ERR_OK) {
                    throw new Exception("Upload error for video: $videoTitle (Error code: $fileError)");
                }
                
                // Validate file type
                $allowedExtensions = ['mp4', 'mpeg', 'mov', 'avi', 'wmv', 'webm'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                if (!in_array($fileExtension, $allowedExtensions)) {
                    throw new Exception("Invalid file type for: $videoTitle. Allowed: " . implode(', ', $allowedExtensions));
                }
                
                // Validate file size (max 100MB)
                $maxSize = 100 * 1024 * 1024;
                if ($fileSize > $maxSize) {
                    throw new Exception("File too large for: $videoTitle. Maximum size is 100MB.");
                }
                
                // Generate unique filename
                $uniqueFilename = uniqid('video_', true) . '_' . time() . '.' . $fileExtension;
                $destination = $uploadDir . $uniqueFilename;
                
                // Move uploaded file
                if (!move_uploaded_file($fileTmpName, $destination)) {
                    throw new Exception("Failed to save video file: $videoTitle");
                }
                
                // Store relative path
                $videoPath = 'uploads/videos/' . $uniqueFilename;
                $videoOrder = $videoIndex + 1;
                
                // Insert video record
                $stmt = $pdo->prepare("
                    INSERT INTO roadmap_videos (phase_id, title, video_url, video_order, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                
                if (!$stmt->execute([$phaseId, $videoTitle, $videoPath, $videoOrder])) {
                    // Clean up uploaded file
                    if (file_exists($destination)) {
                        unlink($destination);
                    }
                    throw new Exception("Failed to save video details: $videoTitle");
                }
                
                error_log("Video saved: $videoTitle");
            }
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Clear form data
    unset($_SESSION['form_data']);
    
    // Success!
    setSuccess("Roadmap '$title' created successfully! It is now pending admin approval.");
    
} catch (Exception $e) {
    // Rollback on error
    $pdo->rollBack();
    
    // Clean up any uploaded files
    if (isset($uploadDir) && file_exists($uploadDir)) {
        $files = glob($uploadDir . '*.{mp4,mpeg,mov,avi,wmv,webm}', GLOB_BRACE);
        foreach ($files as $file) {
            if (is_file($file) && filectime($file) > (time() - 300)) { // Files created in last 5 minutes
                unlink($file);
            }
        }
    }
    
    setError("Failed to create roadmap: " . $e->getMessage());
}