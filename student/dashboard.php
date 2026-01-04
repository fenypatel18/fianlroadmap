<?php
// student/dashboard.php

// --- 1. SETUP & SECURITY ---
session_start();
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
requireStudent();

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];

// Define base path for uploads
$BASE_PATH = '/fianlroadmap';
$UPLOADS_DIR = $_SERVER['DOCUMENT_ROOT'] . $BASE_PATH . '/uploads/profile_pictures/';

// --- 2. FETCH STUDENT DATA ---
$stmt = $pdo->prepare("SELECT id, name, email, profile_picture FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Default profile picture if none exists
$profile_picture = $student['profile_picture'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($student['name']) . '&background=random';

// --- 3. HANDLE PROFILE UPDATE ---
$update_message = '';
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_name = trim($_POST['name']);
    
    if (empty($new_name)) {
        $update_error = "Name cannot be empty";
    } else {
        // Handle profile picture upload
        $profile_pic_path = $student['profile_picture'] ?: '';
        
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            // Create directory if it doesn't exist
            if (!file_exists($UPLOADS_DIR)) {
                mkdir($UPLOADS_DIR, 0777, true);
            }
            
            // Check file size (max 2MB)
            if ($_FILES['profile_picture']['size'] > 2097152) {
                $update_error = "File is too large. Maximum size is 2MB.";
            } else {
                $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $file_name = 'user_' . $student_id . '_' . time() . '.' . $file_extension;
                    $file_path = $UPLOADS_DIR . $file_name;
                    
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $file_path)) {
                        $profile_pic_path = $BASE_PATH . '/uploads/profile_pictures/' . $file_name;
                        
                        // Delete old profile picture if it exists and is not the default
                        if ($student['profile_picture'] && !empty($student['profile_picture']) && strpos($student['profile_picture'], 'ui-avatars.com') === false) {
                            $old_file_path = str_replace($BASE_PATH, '', $student['profile_picture']);
                            $old_file = $_SERVER['DOCUMENT_ROOT'] . $BASE_PATH . $old_file_path;
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                    } else {
                        $update_error = "Failed to upload file. Please try again.";
                    }
                } else {
                    $update_error = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
                }
            }
        } else if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE) {
            // No file was uploaded, keep existing picture
            $profile_pic_path = $student['profile_picture'];
        } else if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            // Other upload error
            $update_error = "File upload error: " . $_FILES['profile_picture']['error'];
        }
        
        if (empty($update_error)) {
            try {
                // UPDATE THE DATABASE
                $update_stmt = $pdo->prepare("UPDATE users SET name = ?, profile_picture = ? WHERE id = ?");
                $update_result = $update_stmt->execute([$new_name, $profile_pic_path, $student_id]);
                
                if ($update_result) {
                    // Update session and refresh student data
                    $_SESSION['name'] = $new_name;
                    
                    // Refresh student data from database
                    $stmt = $pdo->prepare("SELECT name, email, profile_picture FROM users WHERE id = ?");
                    $stmt->execute([$student_id]);
                    $student = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $profile_picture = $student['profile_picture'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($new_name) . '&background=random';
                    
                    $update_message = "Profile updated successfully!";
                    
                    // Refresh the page to show updated data
                    echo "<script>setTimeout(function() { location.reload(); }, 1500);</script>";
                } else {
                    $update_error = "Failed to update profile in database.";
                }
            } catch (PDOException $e) {
                $update_error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// --- 4. FETCH DASHBOARD STATISTICS ---
// Count enrolled roadmaps
$stmt = $pdo->prepare("SELECT COUNT(*) as enrolled_count FROM enrollments WHERE student_id = ?");
$stmt->execute([$student_id]);
$enrolled_count = $stmt->fetch(PDO::FETCH_ASSOC)['enrolled_count'];

// Count completed videos
$stmt = $pdo->prepare("
    SELECT COUNT(*) as completed_videos 
    FROM progress p
    JOIN roadmap_videos v ON p.video_id = v.id
    WHERE p.student_id = ? AND p.completed = 1
");
$stmt->execute([$student_id]);
$completed_videos = $stmt->fetch(PDO::FETCH_ASSOC)['completed_videos'];

// Count earned certificates
$stmt = $pdo->prepare("SELECT COUNT(*) as certificate_count FROM certificates WHERE student_id = ?");
$stmt->execute([$student_id]);
$certificate_count = $stmt->fetch(PDO::FETCH_ASSOC)['certificate_count'];

// Count total quiz attempts
$stmt = $pdo->prepare("SELECT COUNT(*) as quiz_count FROM quiz_attempts WHERE student_id = ?");
$stmt->execute([$student_id]);
$quiz_count = $stmt->fetch(PDO::FETCH_ASSOC)['quiz_count'];

// --- 5. FETCH ENROLLED ROADMAPS WITH PROGRESS ---
$enrolled_roadmaps = [];
$stmt = $pdo->prepare("
    SELECT 
        r.id,
        r.title,
        r.description,
        u.name as instructor_name,
        e.enrollment_date,
        (SELECT COUNT(*) FROM roadmap_videos v 
         JOIN roadmap_phases p ON v.phase_id = p.id 
         WHERE p.roadmap_id = r.id) as total_videos,
        (SELECT COUNT(*) FROM progress pr 
         JOIN roadmap_videos v2 ON pr.video_id = v2.id
         JOIN roadmap_phases p2 ON v2.phase_id = p2.id
         WHERE pr.student_id = ? AND p2.roadmap_id = r.id AND pr.completed = 1) as completed_videos
    FROM enrollments e
    JOIN roadmaps r ON e.roadmap_id = r.id
    JOIN users u ON r.instructor_id = u.id
    WHERE e.student_id = ?
    ORDER BY e.enrollment_date DESC
    LIMIT 5
");
$stmt->execute([$student_id, $student_id]);
$enrolled_roadmaps = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate progress percentage for each roadmap
foreach ($enrolled_roadmaps as &$roadmap) {
    $total_videos = (int)$roadmap['total_videos'];
    $completed_videos = (int)$roadmap['completed_videos'];
    $roadmap['progress_percent'] = $total_videos > 0 ? round(($completed_videos / $total_videos) * 100) : 0;
}

// --- 6. FETCH RECENT ACTIVITIES ---
$recent_activities = [];

// Recent video completions
$stmt = $pdo->prepare("
    SELECT 
        p.completed_at,
        v.title as video_title,
        r.title as roadmap_title,
        'video' as type
    FROM progress p
    JOIN roadmap_videos v ON p.video_id = v.id
    JOIN roadmap_phases ph ON v.phase_id = ph.id
    JOIN roadmaps r ON ph.roadmap_id = r.id
    WHERE p.student_id = ? AND p.completed = 1
    ORDER BY p.completed_at DESC
    LIMIT 5
");
$stmt->execute([$student_id]);
$video_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent quiz attempts
$stmt = $pdo->prepare("
    SELECT 
        q.attempt_date,
        q.score,
        q.passed,
        r.title as roadmap_title,
        ph.title as phase_title,
        'quiz' as type
    FROM quiz_attempts q
    JOIN roadmap_phases ph ON q.phase_id = ph.id
    JOIN roadmaps r ON ph.roadmap_id = r.id
    WHERE q.student_id = ?
    ORDER BY q.attempt_date DESC
    LIMIT 5
");
$stmt->execute([$student_id]);
$quiz_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Merge and sort activities
$recent_activities = array_merge($video_activities, $quiz_activities);
usort($recent_activities, function($a, $b) {
    return strtotime($b['completed_at'] ?? $b['attempt_date']) - strtotime($a['completed_at'] ?? $a['attempt_date']);
});
$recent_activities = array_slice($recent_activities, 0, 5);

// --- 7. FETCH RECENT CERTIFICATES ---
$recent_certificates = [];
$stmt = $pdo->prepare("
    SELECT 
        c.issue_date,
        r.title as roadmap_title,
        u.name as instructor_name,
        r.id as roadmap_id
    FROM certificates c
    JOIN roadmaps r ON c.roadmap_id = r.id
    JOIN users u ON r.instructor_id = u.id
    WHERE c.student_id = ?
    ORDER BY c.issue_date DESC
    LIMIT 3
");
$stmt->execute([$student_id]);
$recent_certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get appropriate icon for roadmap
function getRoadmapIcon($title) {
    $titleLower = strtolower($title);
    $icons = [
        'javascript' => 'code', 'typescript' => 'code', 'react' => 'atom', 
        'node' => 'server', 'python' => 'python', 'java' => 'coffee', 
        'css' => 'palette', 'sql' => 'database', 'excel' => 'table', 
        'english' => 'book-open', 'php' => 'php', 'html' => 'code', 
        'mongodb' => 'database', 'docker' => 'box', 'git' => 'git-branch',
        'data' => 'bar-chart', 'analyst' => 'line-chart', 'ai' => 'brain',
        'frontend' => 'layout', 'backend' => 'server', 'full stack' => 'layers',
        'devops' => 'server-cog', 'mobile' => 'smartphone', 'web' => 'globe',
        'design' => 'palette', 'product' => 'package', 'ux' => 'users', 
        'ui' => 'palette'
    ];
    
    foreach ($icons as $keyword => $icon) {
        if (strpos($titleLower, $keyword) !== false) {
            return $icon;
        }
    }
    
    return 'book-open';
}

// Calculate overall progress
$total_enrolled_videos = array_sum(array_column($enrolled_roadmaps, 'total_videos'));
$total_completed_videos = array_sum(array_column($enrolled_roadmaps, 'completed_videos'));
$overall_progress = $total_enrolled_videos > 0 ? round(($total_completed_videos / $total_enrolled_videos) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en" class="dark" style="color-scheme: dark;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | YourRoadmap</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        
        :root {
            --primary: 124, 58, 237;
            --primary-dark: 109, 40, 217;
            --secondary: 59, 130, 246;
            --background: 19, 20, 23;
            --card: 30, 31, 35;
            --muted: 63, 63, 70;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: rgb(var(--background));
            color: #f8fafc;
            min-height: 100vh;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(124, 58, 237, 0.5);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(124, 58, 237, 0.7);
        }
        
        /* Navigation */
        .nav-gradient {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(30, 41, 59, 0.95) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        
        .nav-link {
            position: relative;
            transition: all 0.3s ease;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #7c3aed, #3b82f6);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }
        
        /* Cards */
        .card {
            background: linear-gradient(145deg, rgba(30, 31, 35, 0.8) 0%, rgba(30, 41, 59, 0.6) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-8px);
            border-color: rgba(124, 58, 237, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(124, 58, 237, 0.1);
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(124, 58, 237, 0.6), transparent);
        }
        
        /* Stats cards */
        .stat-card {
            background: linear-gradient(145deg, rgba(30, 31, 35, 0.6) 0%, rgba(30, 31, 35, 0.3) 100%);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            border-color: rgba(124, 58, 237, 0.3);
            transform: translateY(-2px);
        }
        
        /* Progress bar */
        .progress-bar {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 9999px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 9999px;
            background: linear-gradient(90deg, #7c3aed, #3b82f6);
            transition: width 1s ease-in-out;
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #7c3aed 0%, #3b82f6 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(124, 58, 237, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 12px 24px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(124, 58, 237, 0.3);
        }
        
        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-enrolled {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .badge-featured {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        
        .badge-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .badge-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: linear-gradient(145deg, rgba(30, 31, 35, 0.95) 0%, rgba(30, 41, 59, 0.95) 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            backdrop-filter: blur(20px);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        /* User dropdown */
        .user-dropdown {
            background: linear-gradient(145deg, rgba(30, 31, 35, 0.95) 0%, rgba(30, 41, 59, 0.95) 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out forwards;
        }
        
        .delay-100 { animation-delay: 100ms; }
        .delay-200 { animation-delay: 200ms; }
        .delay-300 { animation-delay: 300ms; }
        
        /* Loading skeleton */
        .skeleton {
            background: linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.05) 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* Line clamp */
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Lucide icon styles */
        .lucide {
            width: 1em;
            height: 1em;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <!-- Edit Profile Modal -->
    <div class="modal" id="editProfileModal">
        <div class="modal-content p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-white">Edit Profile</h2>
                <button onclick="closeModal()" class="text-gray-400 hover:text-white transition-colors">
                    <i data-lucide="x" class="lucide"></i>
                </button>
            </div>
            
            <?php if ($update_message): ?>
                <div class="mb-4 p-3 bg-gradient-to-r from-green-500/20 to-green-600/20 border border-green-500/30 rounded-lg">
                    <p class="text-green-400 text-sm flex items-center">
                        <i data-lucide="check-circle" class="lucide mr-2"></i>
                        <?php echo htmlspecialchars($update_message); ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if ($update_error): ?>
                <div class="mb-4 p-3 bg-gradient-to-r from-red-500/20 to-red-600/20 border border-red-500/30 rounded-lg">
                    <p class="text-red-400 text-sm flex items-center">
                        <i data-lucide="alert-circle" class="lucide mr-2"></i>
                        <?php echo htmlspecialchars($update_error); ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="update_profile" value="1">
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-3">Profile Picture</label>
                    <div class="flex items-center space-x-6">
                        <div class="relative">
                            <img id="profilePreview" class="h-24 w-24 rounded-full border-4 border-purple-500/30 object-cover" 
                                 src="<?php echo htmlspecialchars($profile_picture); ?>" 
                                 alt="Current Profile"
                                 onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($student['name']); ?>&background=random'">
                            <div class="absolute bottom-0 right-0 w-8 h-8 bg-purple-600 rounded-full flex items-center justify-center border-2 border-gray-900">
                                <i data-lucide="camera" class="lucide text-white text-sm"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <input type="file" name="profile_picture" id="profilePicture" accept="image/*" 
                                   class="block w-full text-sm text-gray-400 file:mr-4 file:py-3 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-gradient-to-r file:from-purple-600 file:to-blue-600 file:text-white hover:file:opacity-90">
                            <p class="text-xs text-gray-500 mt-2">JPG, PNG or GIF (Max 2MB)</p>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($student['name']); ?>" required 
                           class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                    <input type="email" value="<?php echo htmlspecialchars($student['email']); ?>" disabled 
                           class="w-full px-4 py-3 bg-white/10 border border-white/10 rounded-xl text-gray-400 cursor-not-allowed">
                    <p class="text-xs text-gray-500 mt-2">Email cannot be changed</p>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeModal()" class="btn-secondary px-6 py-3">
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary px-6 py-3">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="nav-gradient fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="<?php echo $BASE_PATH; ?>/index.php" class="flex items-center space-x-3 group">
                        <div class="w-10 h-10 bg-gradient-to-br from-purple-600 to-blue-600 rounded-xl flex items-center justify-center">
                            <i data-lucide="map" class="lucide text-white"></i>
                        </div>
                        <div>
                            <span class="text-xl font-bold text-white">Your</span>
                            <span class="text-xl font-bold text-purple-400">Roadmap</span>
                        </div>
                    </a>
                </div>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-1">
                    <a href="<?php echo $BASE_PATH; ?>/index.php" class="nav-link px-4 py-2 text-sm font-medium text-gray-300 hover:text-white transition-colors">
                        <i data-lucide="home" class="lucide mr-2"></i>
                        Home
                    </a>
                    <a href="<?php echo $BASE_PATH; ?>/student/explore_roadmaps.php" class="nav-link px-4 py-2 text-sm font-medium text-gray-300 hover:text-white transition-colors">
                        <i data-lucide="compass" class="lucide mr-2"></i>
                        Browse Roadmaps
                    </a>
                    <a href="#" class="nav-link px-4 py-2 text-sm font-medium text-gray-300 hover:text-white transition-colors">
                        <i data-lucide="award" class="lucide mr-2"></i>
                        My Certificates
                    </a>
                    <a href="<?php echo $BASE_PATH; ?>/student/dashboard.php" class="nav-link active px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-purple-600/20 to-blue-600/20 rounded-lg">
                        <i data-lucide="layout-dashboard" class="lucide mr-2"></i>
                        Dashboard
                    </a>
                    <a href="#" class="nav-link px-4 py-2 text-sm font-medium text-gray-300 hover:text-white transition-colors">
                        <i data-lucide="message-square" class="lucide mr-2"></i>
                        Feedback
                    </a>
                </div>

                <!-- User Profile -->
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button id="user-menu-button" class="flex items-center space-x-3 focus:outline-none group">
                            <div class="relative">
                                <img class="h-8 w-8 rounded-full border-2 border-transparent group-hover:border-purple-500 transition-all duration-300" 
                                     src="<?php echo htmlspecialchars($profile_picture); ?>" 
                                     alt="<?php echo htmlspecialchars($student['name']); ?>"
                                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($student['name']); ?>&background=random'">
                                <div class="absolute -bottom-1 -right-1 w-3 h-3 bg-green-500 rounded-full border-2 border-gray-900"></div>
                            </div>
                            <span class="hidden md:block text-sm font-medium text-white"><?php echo htmlspecialchars($student['name']); ?></span>
                            <i data-lucide="chevron-down" class="lucide text-gray-400 text-sm transition-transform group-hover:rotate-180"></i>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div id="user-menu" class="user-dropdown hidden absolute right-0 mt-2 w-48 py-2 shadow-xl">
                            <a href="<?php echo $BASE_PATH; ?>/student/dashboard.php" class="flex items-center px-4 py-3 text-sm text-gray-300 hover:text-white hover:bg-white/5 transition-colors">
                                <i data-lucide="layout-dashboard" class="lucide mr-3"></i>
                                Dashboard
                            </a>
                            <a href="#" onclick="openEditProfile()" class="flex items-center px-4 py-3 text-sm text-gray-300 hover:text-white hover:bg-white/5 transition-colors">
                                <i data-lucide="user" class="lucide mr-3"></i>
                                Edit Profile
                            </a>
                            <a href="<?php echo $BASE_PATH; ?>/auth/logout.php" class="flex items-center px-4 py-3 text-sm text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-colors">
                                <i data-lucide="log-out" class="lucide mr-3"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1 pt-16">
        <!-- Hero Section -->
        <section class="relative overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12">
                <div class="card p-6 md:p-8 animate-fade-in">
                    <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                        <div class="flex items-center space-x-6">
                            <div class="relative">
                                <img class="h-20 w-20 md:h-24 md:w-24 rounded-full border-4 border-purple-500/30" 
                                     src="<?php echo htmlspecialchars($profile_picture); ?>" 
                                     alt="<?php echo htmlspecialchars($student['name']); ?>"
                                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($student['name']); ?>&background=random'">
                                <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-green-500 rounded-full border-2 border-gray-900"></div>
                            </div>
                            <div>
                                <h1 class="text-2xl md:text-3xl font-bold text-white">Welcome back, <?php echo htmlspecialchars($student['name']); ?>! 👋</h1>
                                <p class="text-gray-400 mt-1"><?php echo htmlspecialchars($student['email']); ?></p>
                                <p class="text-gray-300 mt-3">Track your progress and continue your learning journey</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <a href="<?php echo $BASE_PATH; ?>/student/explore_roadmaps.php" class="btn-primary flex items-center">
                                <i data-lucide="compass" class="lucide mr-2"></i>
                                Browse Roadmaps
                            </a>
                            <button onclick="openEditProfile()" class="btn-secondary flex items-center">
                                <i data-lucide="user" class="lucide mr-2"></i>
                                Edit Profile
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="pb-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Enrolled Roadmaps Card -->
                    <div class="stat-card p-6 animate-fade-in delay-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-400">Enrolled Roadmaps</p>
                                <p class="text-3xl font-bold text-white mt-2"><?php echo $enrolled_count; ?></p>
                            </div>
                            <div class="p-3 bg-gradient-to-br from-purple-500/20 to-purple-600/20 rounded-xl">
                                <i data-lucide="book-open" class="lucide text-purple-400"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="<?php echo $BASE_PATH; ?>/student/explore_roadmaps.php" class="text-sm text-purple-400 hover:text-purple-300 flex items-center">
                                Explore more
                                <i data-lucide="arrow-right" class="lucide ml-2"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Completed Videos Card -->
                    <div class="stat-card p-6 animate-fade-in delay-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-400">Completed Videos</p>
                                <p class="text-3xl font-bold text-white mt-2"><?php echo $completed_videos; ?></p>
                            </div>
                            <div class="p-3 bg-gradient-to-br from-blue-500/20 to-blue-600/20 rounded-xl">
                                <i data-lucide="play-circle" class="lucide text-blue-400"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <?php if ($enrolled_roadmaps): ?>
                                <p class="text-xs text-gray-400">Keep going! You're doing great!</p>
                            <?php else: ?>
                                <p class="text-xs text-gray-400">Start learning today!</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Certificates Card -->
                    <div class="stat-card p-6 animate-fade-in delay-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-400">Certificates</p>
                                <p class="text-3xl font-bold text-white mt-2"><?php echo $certificate_count; ?></p>
                            </div>
                            <div class="p-3 bg-gradient-to-br from-green-500/20 to-green-600/20 rounded-xl">
                                <i data-lucide="award" class="lucide text-green-400"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <?php if ($certificate_count > 0): ?>
                                <p class="text-xs text-gray-400">Great achievement!</p>
                            <?php else: ?>
                                <p class="text-xs text-gray-400">Complete a roadmap to earn</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quiz Attempts Card -->
                    <div class="stat-card p-6 animate-fade-in delay-400">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-400">Quiz Attempts</p>
                                <p class="text-3xl font-bold text-white mt-2"><?php echo $quiz_count; ?></p>
                            </div>
                            <div class="p-3 bg-gradient-to-br from-yellow-500/20 to-yellow-600/20 rounded-xl">
                                <i data-lucide="clipboard-check" class="lucide text-yellow-400"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <?php if ($quiz_count > 0): ?>
                                <p class="text-xs text-gray-400">Keep testing your knowledge!</p>
                            <?php else: ?>
                                <p class="text-xs text-gray-400">Complete videos to unlock quizzes</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Dashboard Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
            <div class="grid lg:grid-cols-3 gap-8">
                <!-- Left Column: Enrolled Roadmaps & Recent Activities -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Enrolled Roadmaps -->
                    <div class="card p-6 animate-fade-in">
                        <div class="flex items-center justify-between mb-8">
                            <h2 class="text-xl font-bold text-white flex items-center">
                                <i data-lucide="book-open" class="lucide text-purple-400 mr-3"></i>
                                My Learning Roadmaps
                            </h2>
                            <a href="<?php echo $BASE_PATH; ?>/student/explore_roadmaps.php" class="text-sm text-purple-400 hover:text-purple-300 flex items-center">
                                Browse all
                                <i data-lucide="arrow-right" class="lucide ml-2"></i>
                            </a>
                        </div>
                        
                        <div class="space-y-4">
                            <?php if (empty($enrolled_roadmaps)): ?>
                                <div class="text-center py-8">
                                    <div class="w-16 h-16 bg-gradient-to-br from-gray-800 to-gray-900 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i data-lucide="book-open" class="lucide text-gray-600"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-400 mb-2">No roadmaps yet</h3>
                                    <p class="text-gray-500 mb-4">Start your learning journey by enrolling in a roadmap</p>
                                    <a href="<?php echo $BASE_PATH; ?>/student/explore_roadmaps.php" class="btn-primary inline-flex items-center">
                                        <i data-lucide="compass" class="lucide mr-2"></i>
                                        Browse Roadmaps
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($enrolled_roadmaps as $roadmap): 
                                    $icon = getRoadmapIcon($roadmap['title']);
                                ?>
                                <div class="bg-white/5 rounded-xl p-4 hover:bg-white/10 transition-colors">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center space-x-4">
                                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500/20 to-purple-600/20 flex items-center justify-center">
                                                <i data-lucide="<?php echo $icon; ?>" class="lucide text-purple-400"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-semibold text-white"><?php echo htmlspecialchars($roadmap['title']); ?></h4>
                                                <p class="text-sm text-gray-400">By <?php echo htmlspecialchars($roadmap['instructor_name']); ?></p>
                                            </div>
                                        </div>
                                        <span class="badge badge-success px-3 py-1 text-xs">
                                            <?php echo $roadmap['progress_percent']; ?>% Complete
                                        </span>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-gray-400">Progress</span>
                                            <span class="text-white"><?php echo $roadmap['progress_percent']; ?>%</span>
                                        </div>
                                        <div class="progress-bar h-2">
                                            <div class="progress-fill" style="width: <?php echo $roadmap['progress_percent']; ?>%"></div>
                                        </div>
                                        <div class="flex justify-between text-xs text-gray-400 mt-1">
                                            <span><?php echo $roadmap['completed_videos']; ?> of <?php echo $roadmap['total_videos']; ?> videos</span>
                                            <span><?php echo $roadmap['progress_percent']; ?>%</span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-400">
                                            <i data-lucide="calendar" class="lucide inline mr-1"></i>
                                            Enrolled <?php echo date('M d, Y', strtotime($roadmap['enrollment_date'])); ?>
                                        </span>
                                        <div class="flex space-x-2">
                                            <a href="<?php echo $BASE_PATH; ?>/student/roadmap_player.php?id=<?php echo $roadmap['id']; ?>" class="text-sm bg-gradient-to-r from-blue-600 to-blue-700 text-white px-3 py-2 rounded-lg hover:opacity-90 transition-opacity">
                                                Continue
                                            </a>
                                            <a href="<?php echo $BASE_PATH; ?>/student/view_roadmap.php?id=<?php echo $roadmap['id']; ?>" class="text-sm bg-white/10 text-white px-3 py-2 rounded-lg hover:bg-white/20 transition-colors">
                                                Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Activities -->
                    <div class="card p-6 animate-fade-in delay-100">
                        <h2 class="text-xl font-bold text-white mb-8 flex items-center">
                            <i data-lucide="activity" class="lucide text-blue-400 mr-3"></i>
                            Recent Activity
                        </h2>
                        <div class="space-y-4">
                            <?php if (empty($recent_activities)): ?>
                                <div class="text-center py-8">
                                    <i data-lucide="activity" class="lucide text-gray-600 text-4xl mx-auto mb-4"></i>
                                    <p class="text-gray-400">No recent activity</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                <div class="flex items-center p-4 bg-white/5 rounded-xl">
                                    <div class="flex-shrink-0 mr-4">
                                        <?php if ($activity['type'] === 'video'): ?>
                                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-green-500/20 to-green-600/20 flex items-center justify-center">
                                                <i data-lucide="play-circle" class="lucide text-green-400"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500/20 to-blue-600/20 flex items-center justify-center">
                                                <i data-lucide="clipboard-check" class="lucide text-blue-400"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-white">
                                            <?php if ($activity['type'] === 'video'): ?>
                                                Completed "<?php echo htmlspecialchars($activity['video_title']); ?>"
                                            <?php else: ?>
                                                Quiz: <?php echo htmlspecialchars($activity['phase_title']); ?> (Score: <?php echo $activity['score']; ?>%)
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            <?php echo $activity['roadmap_title']; ?> • 
                                            <?php echo date('M d, g:i A', strtotime($activity['completed_at'] ?? $activity['attempt_date'])); ?>
                                        </p>
                                    </div>
                                    <?php if ($activity['type'] === 'quiz'): ?>
                                        <span class="badge <?php echo $activity['passed'] == 1 ? 'badge-success' : 'badge-warning'; ?> px-3 py-1 text-xs">
                                            <?php echo $activity['passed'] == 1 ? 'Passed' : 'Failed'; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Certificates & Quick Actions -->
                <div class="space-y-8">
                    <!-- Certificates -->
                    <div class="card p-6 animate-fade-in delay-200">
                        <div class="flex items-center justify-between mb-8">
                            <h2 class="text-xl font-bold text-white flex items-center">
                                <i data-lucide="award" class="lucide text-yellow-400 mr-3"></i>
                                My Certificates
                            </h2>
                            <?php if (!empty($recent_certificates)): ?>
                                <a href="#" class="text-sm text-purple-400 hover:text-purple-300 flex items-center">
                                    View all
                                    <i data-lucide="arrow-right" class="lucide ml-2"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="space-y-4">
                            <?php if (empty($recent_certificates)): ?>
                                <div class="text-center py-8">
                                    <div class="w-16 h-16 bg-gradient-to-br from-gray-800 to-gray-900 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i data-lucide="award" class="lucide text-gray-600"></i>
                                    </div>
                                    <p class="text-gray-400 mb-3">No certificates yet</p>
                                    <p class="text-sm text-gray-500">Complete roadmaps to earn certificates</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_certificates as $cert): ?>
                                <div class="bg-gradient-to-r from-purple-500/10 to-blue-500/10 rounded-xl p-4 border border-purple-500/20">
                                    <div class="flex items-center justify-between mb-3">
                                        <i data-lucide="award" class="lucide text-yellow-400"></i>
                                        <span class="text-xs text-gray-400">
                                            <?php echo date('M d, Y', strtotime($cert['issue_date'])); ?>
                                        </span>
                                    </div>
                                    <h4 class="font-semibold text-white mb-1"><?php echo htmlspecialchars($cert['roadmap_title']); ?></h4>
                                    <p class="text-sm text-gray-400 mb-3">By <?php echo htmlspecialchars($cert['instructor_name']); ?></p>
                                    <a href="<?php echo $BASE_PATH; ?>/student/certificate.php?id=<?php echo $cert['roadmap_id']; ?>" class="text-sm text-purple-400 hover:text-purple-300 flex items-center">
                                        View Certificate
                                        <i data-lucide="arrow-right" class="lucide ml-2"></i>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Progress Overview -->
                    <div class="card p-6 animate-fade-in delay-300">
                        <h2 class="text-xl font-bold text-white mb-8 flex items-center">
                            <i data-lucide="trending-up" class="lucide text-green-400 mr-3"></i>
                            Learning Progress
                        </h2>
                        <div class="space-y-6">
                            <div>
                                <div class="flex justify-between text-sm mb-2">
                                    <span class="text-gray-300">Overall Progress</span>
                                    <span class="text-gray-300"><?php echo $overall_progress; ?>%</span>
                                </div>
                                <div class="progress-bar h-3">
                                    <div class="progress-fill" style="width: <?php echo $overall_progress; ?>%"></div>
                                </div>
                            </div>
                            <div class="space-y-3 text-sm text-gray-400">
                                <div class="flex justify-between">
                                    <span>Videos Completed</span>
                                    <span class="text-white"><?php echo $total_completed_videos; ?> / <?php echo $total_enrolled_videos; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Active Roadmaps</span>
                                    <span class="text-white"><?php echo count($enrolled_roadmaps); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Average Progress</span>
                                    <span class="text-white">
                                        <?php 
                                            $avg_progress = count($enrolled_roadmaps) > 0 ? 
                                                array_sum(array_column($enrolled_roadmaps, 'progress_percent')) / count($enrolled_roadmaps) : 0;
                                            echo round($avg_progress); 
                                        ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card p-6 animate-fade-in delay-400">
                        <h2 class="text-xl font-bold text-white mb-8 flex items-center">
                            <i data-lucide="zap" class="lucide text-orange-400 mr-3"></i>
                            Quick Actions
                        </h2>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="<?php echo $BASE_PATH; ?>/student/explore_roadmaps.php" class="p-4 bg-white/5 rounded-xl hover:bg-white/10 transition-colors text-center">
                                <i data-lucide="compass" class="lucide text-blue-400 mx-auto mb-2"></i>
                                <p class="text-sm font-medium text-white">Browse</p>
                                <p class="text-xs text-gray-400">Find roadmaps</p>
                            </a>
                            <a href="#" class="p-4 bg-white/5 rounded-xl hover:bg-white/10 transition-colors text-center">
                                <i data-lucide="clipboard-check" class="lucide text-green-400 mx-auto mb-2"></i>
                                <p class="text-sm font-medium text-white">Quizzes</p>
                                <p class="text-xs text-gray-400">Test knowledge</p>
                            </a>
                            <a href="#" class="p-4 bg-white/5 rounded-xl hover:bg-white/10 transition-colors text-center">
                                <i data-lucide="message-square" class="lucide text-yellow-400 mx-auto mb-2"></i>
                                <p class="text-sm font-medium text-white">Feedback</p>
                                <p class="text-xs text-gray-400">Share feedback</p>
                            </a>
                            <button onclick="openEditProfile()" class="p-4 bg-white/5 rounded-xl hover:bg-white/10 transition-colors text-center">
                                <i data-lucide="user" class="lucide text-purple-400 mx-auto mb-2"></i>
                                <p class="text-sm font-medium text-white">Profile</p>
                                <p class="text-xs text-gray-400">Edit profile</p>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid md:grid-cols-4 gap-8">
                <!-- Brand -->
                <div class="space-y-4">
                    <a href="<?php echo $BASE_PATH; ?>/index.php" class="flex items-center space-x-3 group">
                        <div class="w-10 h-10 bg-gradient-to-br from-purple-600 to-blue-600 rounded-xl flex items-center justify-center">
                            <i data-lucide="map" class="lucide text-white"></i>
                        </div>
                        <div>
                            <span class="text-xl font-bold text-white">Your</span>
                            <span class="text-xl font-bold text-purple-400">Roadmap</span>
                        </div>
                    </a>
                    <p class="text-gray-400 text-sm">
                        Roadmaps, projects, and resources created by the community to help you grow in your career.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i data-lucide="twitter" class="lucide"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i data-lucide="linkedin" class="lucide"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i data-lucide="github" class="lucide"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i data-lucide="youtube" class="lucide"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="text-white font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-3">
                        <li><a href="<?php echo $BASE_PATH; ?>/index.php" class="text-gray-400 hover:text-white transition-colors">Home</a></li>
                        <li><a href="<?php echo $BASE_PATH; ?>/student/explore_roadmaps.php" class="text-gray-400 hover:text-white transition-colors">Browse Roadmaps</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">My Certificates</a></li>
                        <li><a href="<?php echo $BASE_PATH; ?>/student/dashboard.php" class="text-gray-400 hover:text-white transition-colors">Dashboard</a></li>
                    </ul>
                </div>

                <!-- Resources -->
                <div>
                    <h4 class="text-white font-semibold mb-4">Resources</h4>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Documentation</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Tutorials</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Blog</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Community</a></li>
                    </ul>
                </div>

                <!-- Legal -->
                <div>
                    <h4 class="text-white font-semibold mb-4">Legal</h4>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Terms of Service</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Cookie Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Contact Us</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-800 mt-8 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-400 text-sm">
                    © <?php echo date('Y'); ?> YourRoadmap. All rights reserved.
                </p>
                <p class="text-gray-400 text-sm mt-4 md:mt-0">
                    Made with <i data-lucide="heart" class="lucide text-red-500 mx-1 inline"></i> for the learning community
                </p>
            </div>
        </div>
    </footer>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Toggle user dropdown
        const userMenuButton = document.getElementById('user-menu-button');
        const userMenu = document.getElementById('user-menu');

        if (userMenuButton && userMenu) {
            userMenuButton.addEventListener('click', function(e) {
                e.stopPropagation();
                userMenu.classList.toggle('hidden');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!userMenu.contains(e.target) && !userMenuButton.contains(e.target)) {
                    userMenu.classList.add('hidden');
                }
            });
        }

        // Modal functions
        function openEditProfile() {
            document.getElementById('editProfileModal').classList.add('active');
            document.getElementById('user-menu').classList.add('hidden');
        }

        function closeModal() {
            document.getElementById('editProfileModal').classList.remove('active');
        }

        // Profile picture preview
        document.getElementById('profilePicture')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('profilePreview');
                    if (preview) {
                        preview.src = e.target.result;
                    }
                }
                reader.readAsDataURL(file);
            }
        });

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Close modal when clicking outside
        document.getElementById('editProfileModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            const animatedElements = document.querySelectorAll('.animate-fade-in');
            animatedElements.forEach((el, index) => {
                el.style.animationDelay = `${index * 100}ms`;
            });
        });

        // Image error fallback
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('img');
            images.forEach(img => {
                img.addEventListener('error', function() {
                    if (this.src.includes('uploads/profile_pictures')) {
                        const name = this.alt || 'User';
                        this.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random`;
                    }
                });
            });
        });

        // Check for URL hash to open edit profile modal
        if (window.location.hash === '#edit-profile') {
            openEditProfile();
        }
    </script>
</body>
</html>