<?php
// instructor/store_roadmap.php
session_start();

// Include necessary files
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';

// Authorization: Ensure the user is a logged-in instructor
requireInstructor();

$instructor_id = $_SESSION['user_id'];

// Debug: Log what we receive
error_log("=== STORE ROADMAP DEBUG ===");
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data keys: " . implode(', ', array_keys($_FILES)));
if (isset($_FILES['phases'])) {
    error_log("Files array structure: ");
    foreach ($_FILES['phases'] as $key => $value) {
        error_log("  [$key] => " . (is_array($value) ? 'Array' : $value));
    }
}

// Function to set toast message and redirect
function setToastAndRedirect($message, $type = 'error', $location = 'create_roadmap.php') {
    $_SESSION['toast_message'] = $message;
    $_SESSION['toast_type'] = $type;
    $_SESSION['form_data'] = $_POST; // Store form data for repopulation
    header("Location: $location");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setToastAndRedirect('Form was not submitted via POST method.', 'error');
}

// Get form data
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$duration = isset($_POST['duration']) ? trim($_POST['duration']) : '';
$price = isset($_POST['price']) ? $_POST['price'] : '';
$phases_data = isset($_POST['phases']) ? $_POST['phases'] : [];

error_log("Form values - Title: '$title', Duration: '$duration', Price: '$price'");
error_log("Phases count: " . count($phases_data));

// Simple validation
$errors = [];

if (empty($title)) {
    $errors[] = 'Roadmap title is required.';
}

if (empty($description)) {
    $errors[] = 'Roadmap description is required.';
}

if (empty($duration)) {
    $errors[] = 'Estimated duration is required.';
}

if (empty($price) || !is_numeric($price) || floatval($price) < 0) {
    $errors[] = 'Please enter a valid price (0 or greater).';
}

if (empty($phases_data) || count($phases_data) < 2) {
    $errors[] = 'A roadmap must have at least two phases.';
}

// Validate phases
foreach ($phases_data as $phase_idx => $phase) {
    $phase_title = isset($phase['title']) ? trim($phase['title']) : '';
    
    if (empty($phase_title)) {
        $errors[] = "Phase title is required for Phase " . ($phase_idx + 1) . ".";
    }
    
    // Check videos
    if (!isset($phase['videos']) || !is_array($phase['videos']) || count($phase['videos']) === 0) {
        $errors[] = "At least one video is required for Phase " . ($phase_idx + 1) . ".";
        continue;
    }
    
    // Validate each video
    foreach ($phase['videos'] as $video_idx => $video) {
        $video_title = isset($video['title']) ? trim($video['title']) : '';
        
        if (empty($video_title)) {
            $errors[] = "Video title is required for Video in Phase " . ($phase_idx + 1) . ".";
        }
        
        // Check if file exists in $_FILES array
        $file_key = "phases_{$phase_idx}_videos_{$video_idx}_file";
        if (!isset($_FILES['phases'])) {
            $errors[] = "No video file uploaded for '$video_title' in Phase " . ($phase_idx + 1) . ".";
        }
    }
}

// If there are errors
if (!empty($errors)) {
    error_log("Validation errors: " . implode(", ", $errors));
    setToastAndRedirect(implode("\n", $errors), 'error');
}

// Prepare upload directory
$upload_dir = __DIR__ . '/../uploads/videos/';
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        setToastAndRedirect('Failed to create upload directory. Please contact administrator.', 'error');
    }
}

// Start database transaction
$pdo->beginTransaction();

try {
    // 1. Insert into roadmaps table
    // First check if duration column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM roadmaps LIKE 'duration'");
    $stmt->execute();
    $has_duration = $stmt->fetch();
    
    if ($has_duration) {
        $stmt = $pdo->prepare("
            INSERT INTO roadmaps (instructor_id, title, description, duration, price, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$instructor_id, $title, $description, $duration, $price]);
    } else {
        // If duration column doesn't exist
        $stmt = $pdo->prepare("
            INSERT INTO roadmaps (instructor_id, title, description, price, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$instructor_id, $title, $description, $price]);
    }
    
    $roadmap_id = $pdo->lastInsertId();
    
    // 2. Insert phases
    foreach ($phases_data as $phase_idx => $phase) {
        $phase_title = trim($phase['title']);
        $phase_order = intval($phase['order'] ?? ($phase_idx + 1));
        
        $stmt = $pdo->prepare("
            INSERT INTO roadmap_phases (roadmap_id, title, phase_order, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        if (!$stmt->execute([$roadmap_id, $phase_title, $phase_order])) {
            throw new Exception("Failed to create phase: $phase_title");
        }
        
        $phase_id = $pdo->lastInsertId();
        
        // 3. Insert videos for this phase
        if (isset($phase['videos']) && is_array($phase['videos'])) {
            foreach ($phase['videos'] as $video_idx => $video) {
                $video_title = trim($video['title']);
                
                // Handle file upload - PHP renames array keys for multiple files
                $file_tmp_name = null;
                $file_name = null;
                $file_error = null;
                $file_size = null;
                
                // Check multiple possible array structures
                if (isset($_FILES['phases']['tmp_name'][$phase_idx]['videos'][$video_idx]['file'])) {
                    // Structure 1: phases[phase_idx][videos][video_idx][file]
                    $file_tmp_name = $_FILES['phases']['tmp_name'][$phase_idx]['videos'][$video_idx]['file'];
                    $file_name = $_FILES['phases']['name'][$phase_idx]['videos'][$video_idx]['file'];
                    $file_error = $_FILES['phases']['error'][$phase_idx]['videos'][$video_idx]['file'];
                    $file_size = $_FILES['phases']['size'][$phase_idx]['videos'][$video_idx]['file'];
                } elseif (isset($_FILES['phases']['tmp_name'][$phase_idx]['videos']['file'][$video_idx])) {
                    // Structure 2: phases[phase_idx][videos][file][video_idx]
                    $file_tmp_name = $_FILES['phases']['tmp_name'][$phase_idx]['videos']['file'][$video_idx];
                    $file_name = $_FILES['phases']['name'][$phase_idx]['videos']['file'][$video_idx];
                    $file_error = $_FILES['phases']['error'][$phase_idx]['videos']['file'][$video_idx];
                    $file_size = $_FILES['phases']['size'][$phase_idx]['videos']['file'][$video_idx];
                } else {
                    // Try to find the file by iterating
                    foreach ($_FILES['phases']['tmp_name'] as $phase_key => $phase_files) {
                        if (isset($phase_files['videos'][$video_idx]['file'])) {
                            $file_tmp_name = $phase_files['videos'][$video_idx]['file'];
                            $file_name = $_FILES['phases']['name'][$phase_key]['videos'][$video_idx]['file'];
                            $file_error = $_FILES['phases']['error'][$phase_key]['videos'][$video_idx]['file'];
                            $file_size = $_FILES['phases']['size'][$phase_key]['videos'][$video_idx]['file'];
                            break;
                        }
                    }
                }
                
                if (!$file_tmp_name || $file_error !== UPLOAD_ERR_OK) {
                    throw new Exception("No valid video file uploaded for: $video_title in Phase " . ($phase_idx + 1));
                }
                
                if ($file_size == 0) {
                    throw new Exception("Empty file uploaded for: $video_title");
                }
                
                // Validate file type by extension
                $allowed_extensions = ['mp4', 'mpeg', 'mov', 'avi', 'wmv', 'webm'];
                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    throw new Exception("Invalid file type for: $video_title. Allowed: MP4, MPEG, MOV, AVI, WMV, WEBM");
                }
                
                // Validate file size (max 100MB)
                $max_size = 100 * 1024 * 1024; // 100MB
                if ($file_size > $max_size) {
                    throw new Exception("File too large for: $video_title. Maximum size is 100MB.");
                }
                
                // Generate unique filename
                $unique_filename = uniqid('video_', true) . '_' . time() . '_' . $phase_idx . '_' . $video_idx . '.' . $file_extension;
                $destination = $upload_dir . $unique_filename;
                
                // Move uploaded file
                if (!move_uploaded_file($file_tmp_name, $destination)) {
                    throw new Exception("Failed to save video file: $video_title");
                }
                
                // Store relative path in database
                $video_path = 'uploads/videos/' . $unique_filename;
                $video_order = $video_idx + 1;
                
                $stmt = $pdo->prepare("
                    INSERT INTO roadmap_videos (phase_id, title, video_url, video_order, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                
                if (!$stmt->execute([$phase_id, $video_title, $video_path, $video_order])) {
                    // Clean up uploaded file if DB insert fails
                    if (file_exists($destination)) {
                        unlink($destination);
                    }
                    throw new Exception("Failed to save video details: $video_title");
                }
                
                error_log("Successfully saved video: $video_title for phase $phase_idx");
            }
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Clear form data from session
    unset($_SESSION['form_data']);
    
    // Set success message and redirect to dashboard
    $_SESSION['toast_message'] = "Roadmap '$title' created successfully! It is now pending admin approval.";
    $_SESSION['toast_type'] = 'success';
    header("Location: dashboard.php");
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Clean up any uploaded files
    if (isset($upload_dir)) {
        // Find and delete any uploaded files in this session
        $files = glob($upload_dir . '*_' . session_id() . '_*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    // Set error message
    setToastAndRedirect("Failed to create roadmap: " . $e->getMessage(), 'error');
}