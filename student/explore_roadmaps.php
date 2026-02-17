<?php
// student/explore_roadmaps.php

// --- SETUP & SECURITY ---
session_start();
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
requireStudent();

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];
$BASE_PATH = '/fianlroadmap';

// --- FETCH STUDENT PROFILE DATA ---
$stmt = $pdo->prepare("SELECT id, name, email, profile_picture FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $student['profile_picture'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($student['name']) . '&background=random';

// --- FETCH ROADMAPS DATA ---
$availableRoadmaps = [];
$enrolledRoadmaps = [];
$recommendedRoadmaps = [];

try {
    // Fetch all approved roadmaps with proper counts
    $stmt = $pdo->prepare("
        SELECT 
            r.*, 
            u.name as instructor_name,
            COALESCE(phase_counts.phase_count, 0) as phase_count,
            COALESCE(enrollment_counts.enrollment_count, 0) as enrollment_count,
            COALESCE(avg_ratings.avg_rating, 0) as avg_rating
        FROM roadmaps r 
        JOIN users u ON r.instructor_id = u.id 
        LEFT JOIN (
            SELECT roadmap_id, COUNT(*) as phase_count 
            FROM roadmap_phases 
            GROUP BY roadmap_id
        ) as phase_counts ON r.id = phase_counts.roadmap_id
        LEFT JOIN (
            SELECT roadmap_id, COUNT(*) as enrollment_count 
            FROM enrollments 
            GROUP BY roadmap_id
        ) as enrollment_counts ON r.id = enrollment_counts.roadmap_id
        LEFT JOIN (
            SELECT roadmap_id, AVG(rating) as avg_rating 
            FROM feedback 
            GROUP BY roadmap_id
        ) as avg_ratings ON r.id = avg_ratings.roadmap_id
        WHERE r.status = 'approved'
        ORDER BY enrollment_counts.enrollment_count DESC, r.created_at DESC
    ");
    $stmt->execute();
    $allRoadmaps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user's enrolled roadmap IDs
    $enrolledStmt = $pdo->prepare("
        SELECT roadmap_id 
        FROM enrollments 
        WHERE student_id = ?
    ");
    $enrolledStmt->execute([$student_id]);
    $enrolledIds = $enrolledStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // Separate available and enrolled roadmaps
    foreach ($allRoadmaps as $roadmap) {
        if (in_array($roadmap['id'], $enrolledIds)) {
            $enrolledRoadmaps[] = $roadmap;
        } else {
            $availableRoadmaps[] = $roadmap;
        }
    }
    
    // Get recommended roadmaps (based on similar enrollments)
    if (!empty($enrolledIds)) {
        $placeholders = str_repeat('?,', count($enrolledIds) - 1) . '?';
        $recommendedStmt = $pdo->prepare("
            SELECT DISTINCT r.*, u.name as instructor_name,
                   COALESCE(phase_counts.phase_count, 0) as phase_count,
                   COALESCE(enrollment_counts.enrollment_count, 0) as enrollment_count,
                   COALESCE(avg_ratings.avg_rating, 0) as avg_rating,
                   COUNT(DISTINCT e2.student_id) as similar_enrollments
            FROM roadmaps r
            JOIN users u ON r.instructor_id = u.id
            LEFT JOIN (
                SELECT roadmap_id, COUNT(*) as phase_count 
                FROM roadmap_phases 
                GROUP BY roadmap_id
            ) as phase_counts ON r.id = phase_counts.roadmap_id
            LEFT JOIN (
                SELECT roadmap_id, COUNT(*) as enrollment_count 
                FROM enrollments 
                GROUP BY roadmap_id
            ) as enrollment_counts ON r.id = enrollment_counts.roadmap_id
            LEFT JOIN (
                SELECT roadmap_id, AVG(rating) as avg_rating 
                FROM feedback 
                GROUP BY roadmap_id
            ) as avg_ratings ON r.id = avg_ratings.roadmap_id
            JOIN enrollments e1 ON r.id = e1.roadmap_id
            JOIN enrollments e2 ON e1.student_id = e2.student_id
            WHERE e2.roadmap_id IN ($placeholders)
            AND r.id NOT IN ($placeholders)
            AND r.status = 'approved'
            GROUP BY r.id
            ORDER BY similar_enrollments DESC
            LIMIT 3
        ");
        
        // Bind parameters twice (for both IN clauses)
        $params = array_merge($enrolledIds, $enrolledIds);
        $recommendedStmt->execute($params);
        $recommendedRoadmaps = $recommendedStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Count statistics - FIXED QUERY
    $statsStmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM roadmaps WHERE status = 'approved') as total_roadmaps,
            (SELECT COUNT(DISTINCT rv.id) 
             FROM roadmap_videos rv
             JOIN roadmap_phases rp ON rv.phase_id = rp.id
             JOIN roadmaps r ON rp.roadmap_id = r.id
             WHERE r.status = 'approved') as total_videos,
            (SELECT COUNT(*) FROM enrollments) as total_enrollments,
            (SELECT COUNT(DISTINCT student_id) FROM enrollments) as total_students
    ");
    $statsStmt->execute();
    $stats = $statsStmt->fetch();
    
    // Get student's progress if logged in
    $studentProgress = [];
    if (!empty($enrolledRoadmaps)) {
        foreach ($enrolledRoadmaps as $roadmap) {
            $progressStmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT p.video_id) as completed_videos,
                    COUNT(DISTINCT rv.id) as total_videos
                FROM roadmap_videos rv
                JOIN roadmap_phases rp ON rv.phase_id = rp.id
                LEFT JOIN progress p ON rv.id = p.video_id AND p.student_id = ?
                WHERE rp.roadmap_id = ?
            ");
            $progressStmt->execute([$student_id, $roadmap['id']]);
            $progress = $progressStmt->fetch();
            
            if ($progress['total_videos'] > 0) {
                $percentage = ($progress['completed_videos'] / $progress['total_videos']) * 100;
            } else {
                $percentage = 0;
            }
            
            $studentProgress[$roadmap['id']] = [
                'completed' => $progress['completed_videos'],
                'total' => $progress['total_videos'],
                'percentage' => $percentage
            ];
        }
    }
    
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "Failed to load roadmaps. Please try again later.";
}

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
?>

<!DOCTYPE html>
<html lang="en" class="dark" style="color-scheme: dark;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Roadmaps | YourRoadmap</title>
    
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
        
        /* Hero section */
        .hero-gradient {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.15) 0%, rgba(124, 58, 237, 0.15) 100%);
            position: relative;
            overflow: hidden;
        }
        
        .hero-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.1) 0%, transparent 70%);
            animation: pulse 15s infinite alternate;
        }
        
        @keyframes pulse {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, 30px) scale(1.1); }
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
        
        /* Search input */
        .search-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 14px 20px;
            padding-left: 48px;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: rgba(124, 58, 237, 0.5);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
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
        
        .badge-free {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        
        /* Footer */
        .footer {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(30, 41, 59, 0.95) 100%);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
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
        
        /* User dropdown */
        .user-dropdown {
            background: linear-gradient(145deg, rgba(30, 31, 35, 0.95) 0%, rgba(30, 41, 59, 0.95) 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-section {
                padding: 40px 0;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Line clamp */
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Search match styling */
        .search-match {
            animation: highlight 1s ease;
        }
        
        @keyframes highlight {
            0% { box-shadow: 0 0 0 0 rgba(124, 58, 237, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(124, 58, 237, 0); }
            100% { box-shadow: 0 0 0 0 rgba(124, 58, 237, 0); }
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
    </style>
</head>
<body class="min-h-screen flex flex-col">
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
                    <a href="<?php echo $BASE_PATH; ?>/student/explore_roadmaps.php" class="nav-link active px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-purple-600/20 to-blue-600/20 rounded-lg">
                        <i data-lucide="compass" class="lucide mr-2"></i>
                        Browse Roadmaps
                    </a>
                    <a href="#" class="nav-link px-4 py-2 text-sm font-medium text-gray-300 hover:text-white transition-colors">
                        <i data-lucide="award" class="lucide mr-2"></i>
                        My Certificates
                    </a>
                    <a href="<?php echo $BASE_PATH; ?>/student/dashboard.php" class="nav-link px-4 py-2 text-sm font-medium text-gray-300 hover:text-white transition-colors">
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
        <section class="hero-gradient relative overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-20">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div class="space-y-8 animate-fade-in">
                        <div class="inline-flex items-center px-4 py-2 rounded-full bg-gradient-to-r from-purple-600/20 to-blue-600/20 border border-purple-500/30">
                            <i data-lucide="graduation-cap" class="lucide text-purple-400 mr-2"></i>
                            <span class="text-sm font-medium text-purple-300">Discover Your Path</span>
                        </div>
                        
                        <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold leading-tight">
                            Start Your
                            <span class="bg-gradient-to-r from-purple-400 to-blue-400 bg-clip-text text-transparent">Learning Journey</span>
                            Today
                        </h1>
                        
                        <p class="text-xl text-gray-300 max-w-2xl">
                            Master new skills with expertly crafted roadmaps. Join thousands of students who have transformed their careers with our structured learning paths.
                        </p>
                        
                        <div class="flex flex-wrap gap-4">
                            <a href="#featured" class="btn-primary">
                                <i data-lucide="star" class="lucide mr-2"></i>
                                Featured Roadmaps
                            </a>
                            <a href="<?php echo $BASE_PATH; ?>/student/dashboard.php" class="btn-secondary">
                                <i data-lucide="play-circle" class="lucide mr-2"></i>
                                Continue Learning
                            </a>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 animate-fade-in delay-200">
                        <div class="stat-card p-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-green-500/20 to-green-600/20 flex items-center justify-center">
                                    <i data-lucide="book-open" class="lucide text-green-400"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-white"><?php echo $stats['total_roadmaps'] ?? '0'; ?></p>
                                    <p class="text-sm text-gray-400">Roadmaps</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card p-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-purple-500/20 to-purple-600/20 flex items-center justify-center">
                                    <i data-lucide="users" class="lucide text-purple-400"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-white"><?php echo $stats['total_students'] ?? '0'; ?></p>
                                    <p class="text-sm text-gray-400">Active Students</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card p-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-blue-500/20 to-blue-600/20 flex items-center justify-center">
                                    <i data-lucide="video" class="lucide text-blue-400"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-white"><?php echo $stats['total_videos'] ?? '0'; ?></p>
                                    <p class="text-sm text-gray-400">Video Lessons</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card p-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-orange-500/20 to-orange-600/20 flex items-center justify-center">
                                    <i data-lucide="trophy" class="lucide text-orange-400"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-white"><?php echo $stats['total_enrollments'] ?? '0'; ?></p>
                                    <p class="text-sm text-gray-400">Enrollments</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Search Section -->
        <section class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl mx-auto">
                    <div class="relative">
                        <i data-lucide="search" class="lucide absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" 
                               id="search-input"
                               placeholder="Search roadmaps by title, instructor, or keyword..." 
                               class="search-input w-full pl-12">
                        <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                            <kbd class="px-2 py-1 text-xs bg-gray-800 text-gray-400 rounded">⌘K</kbd>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Content Section -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="space-y-12">
                <?php if(!empty($enrolledRoadmaps)): ?>
                <!-- My Enrolled Roadmaps -->
                <section id="my-roadmaps" class="animate-fade-in delay-100">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h2 class="text-2xl font-bold text-white flex items-center">
                                <i data-lucide="bookmark" class="lucide text-green-400 mr-3"></i>
                                My Enrolled Roadmaps
                            </h2>
                            <p class="text-gray-400 mt-2">Continue where you left off</p>
                        </div>
                        <a href="<?php echo $BASE_PATH; ?>/student/dashboard.php" class="text-sm text-blue-400 hover:text-blue-300 transition-colors flex items-center">
                            View All
                            <i data-lucide="arrow-right" class="lucide ml-2"></i>
                        </a>
                    </div>
                    
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($enrolledRoadmaps as $roadmap): 
                            $progress = $studentProgress[$roadmap['id']] ?? ['percentage' => 0, 'completed' => 0, 'total' => 0];
                            $icon = getRoadmapIcon($roadmap['title']);
                        ?>
                        <div class="card p-6 roadmap-card"
                             data-roadmap-title="<?php echo htmlspecialchars($roadmap['title']); ?>"
                             data-roadmap-description="<?php echo htmlspecialchars($roadmap['description']); ?>"
                             data-instructor="<?php echo htmlspecialchars($roadmap['instructor_name']); ?>"
                             data-category="enrolled"
                             data-roadmap-id="<?php echo $roadmap['id']; ?>">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500/20 to-green-600/20 flex items-center justify-center">
                                        <i data-lucide="<?php echo $icon; ?>" class="lucide text-green-400"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-white truncate"><?php echo htmlspecialchars($roadmap['title']); ?></h3>
                                        <p class="text-xs text-gray-400">By <?php echo htmlspecialchars($roadmap['instructor_name']); ?></p>
                                    </div>
                                </div>
                                <span class="badge badge-enrolled">
                                    <i data-lucide="check-circle" class="lucide mr-1"></i>
                                    Enrolled
                                </span>
                            </div>
                            
                            <!-- Progress -->
                            <div class="mb-6">
                                <div class="flex justify-between text-sm mb-2">
                                    <span class="text-gray-400">Progress</span>
                                    <span class="font-medium text-white"><?php echo round($progress['percentage']); ?>%</span>
                                </div>
                                <div class="progress-bar h-2">
                                    <div class="progress-fill" style="width: <?php echo $progress['percentage']; ?>%"></div>
                                </div>
                                <p class="text-xs text-gray-400 mt-2">
                                    <?php echo $progress['completed']; ?> of <?php echo $progress['total']; ?> lessons completed
                                </p>
                            </div>
                            
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center space-x-4 text-gray-400">
                                    <div class="flex items-center">
                                        <i data-lucide="layers" class="lucide mr-1"></i>
                                        <span><?php echo $roadmap['phase_count']; ?> modules</span>
                                    </div>
                                    <?php if($roadmap['avg_rating'] > 0): ?>
                                    <div class="flex items-center">
                                        <i data-lucide="star" class="lucide text-yellow-500 mr-1"></i>
                                        <span><?php echo number_format($roadmap['avg_rating'], 1); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <a href="<?php echo $BASE_PATH; ?>/student/roadmap_player.php?id=<?php echo $roadmap['id']; ?>" 
                                   class="text-blue-400 hover:text-blue-300 transition-colors font-medium flex items-center">
                                    Continue
                                    <i data-lucide="arrow-right" class="lucide ml-2"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if(!empty($recommendedRoadmaps)): ?>
                <!-- Recommended Roadmaps -->
                <section id="featured" class="animate-fade-in delay-200">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h2 class="text-2xl font-bold text-white flex items-center">
                                <i data-lucide="star" class="lucide text-yellow-400 mr-3"></i>
                                Recommended For You
                            </h2>
                            <p class="text-gray-400 mt-2">Based on your interests and enrollments</p>
                        </div>
                    </div>
                    
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($recommendedRoadmaps as $roadmap): 
                            $icon = getRoadmapIcon($roadmap['title']);
                        ?>
                        <div class="card p-6 roadmap-card"
                             data-roadmap-title="<?php echo htmlspecialchars($roadmap['title']); ?>"
                             data-roadmap-description="<?php echo htmlspecialchars($roadmap['description']); ?>"
                             data-instructor="<?php echo htmlspecialchars($roadmap['instructor_name']); ?>"
                             data-category="recommended"
                             data-roadmap-id="<?php echo $roadmap['id']; ?>">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-yellow-500/20 to-yellow-600/20 flex items-center justify-center">
                                        <i data-lucide="<?php echo $icon; ?>" class="lucide text-yellow-400"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-white truncate"><?php echo htmlspecialchars($roadmap['title']); ?></h3>
                                        <p class="text-xs text-gray-400">By <?php echo htmlspecialchars($roadmap['instructor_name']); ?></p>
                                    </div>
                                </div>
                                <span class="badge badge-featured">
                                    <i data-lucide="star" class="lucide mr-1"></i>
                                    Recommended
                                </span>
                            </div>
                            
                            <p class="text-gray-300 text-sm mb-6 line-clamp-2">
                                <?php echo htmlspecialchars(substr($roadmap['description'], 0, 100)) . '...'; ?>
                            </p>
                            
                            <div class="flex items-center justify-between text-sm mb-4">
                                <div class="flex items-center space-x-4 text-gray-400">
                                    <div class="flex items-center">
                                        <i data-lucide="users" class="lucide mr-1"></i>
                                        <span><?php echo $roadmap['enrollment_count']; ?> enrolled</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i data-lucide="clock" class="lucide mr-1"></i>
                                        <span>3-6 months</span>
                                    </div>
                                </div>
                                <?php if($roadmap['price'] > 0): ?>
                                <span class="font-bold text-white">
                                    $<?php echo number_format($roadmap['price'], 2); ?>
                                </span>
                                <?php else: ?>
                                <span class="badge badge-free">
                                    FREE
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <a href="<?php echo $BASE_PATH; ?>/student/enroll.php?id=<?php echo $roadmap['id']; ?>" 
                               class="btn-primary w-full text-center">
                                <i data-lucide="shopping-cart" class="lucide mr-2"></i>
                                Enroll Now
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- All Available Roadmaps -->
                <section class="animate-fade-in delay-300">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h2 class="text-2xl font-bold text-white flex items-center">
                                <i data-lucide="compass" class="lucide text-blue-400 mr-3"></i>
                                All Available Roadmaps
                            </h2>
                            <p class="text-gray-400 mt-2"><?php echo count($availableRoadmaps); ?> roadmaps available</p>
                        </div>
                    </div>
                    
                    <?php if (!empty($availableRoadmaps)): ?>
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($availableRoadmaps as $roadmap): 
                            $icon = getRoadmapIcon($roadmap['title']);
                        ?>
                        <div class="card p-6 roadmap-card"
                             data-roadmap-title="<?php echo htmlspecialchars($roadmap['title']); ?>"
                             data-roadmap-description="<?php echo htmlspecialchars($roadmap['description']); ?>"
                             data-instructor="<?php echo htmlspecialchars($roadmap['instructor_name']); ?>"
                             data-category="available"
                             data-roadmap-id="<?php echo $roadmap['id']; ?>">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/20 to-blue-600/20 flex items-center justify-center">
                                        <i data-lucide="<?php echo $icon; ?>" class="lucide text-blue-400"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-white truncate"><?php echo htmlspecialchars($roadmap['title']); ?></h3>
                                        <p class="text-xs text-gray-400">By <?php echo htmlspecialchars($roadmap['instructor_name']); ?></p>
                                    </div>
                                </div>
                                <?php if($roadmap['avg_rating'] >= 4.5): ?>
                                <span class="badge badge-featured">
                                    <i data-lucide="crown" class="lucide mr-1"></i>
                                    Top Rated
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <p class="text-gray-300 text-sm mb-6 line-clamp-2">
                                <?php echo htmlspecialchars(substr($roadmap['description'], 0, 100)) . '...'; ?>
                            </p>
                            
                            <div class="flex items-center justify-between text-sm mb-4">
                                <div class="flex items-center space-x-4 text-gray-400">
                                    <div class="flex items-center">
                                        <i data-lucide="layers" class="lucide mr-1"></i>
                                        <span><?php echo $roadmap['phase_count']; ?> modules</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i data-lucide="users" class="lucide mr-1"></i>
                                        <span><?php echo $roadmap['enrollment_count']; ?> enrolled</span>
                                    </div>
                                </div>
                                <?php if($roadmap['avg_rating'] > 0): ?>
                                <div class="flex items-center text-yellow-500">
                                    <i data-lucide="star" class="lucide mr-1"></i>
                                    <span><?php echo number_format($roadmap['avg_rating'], 1); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex items-center justify-between pt-4 border-t border-gray-800">
                                <?php if($roadmap['price'] > 0): ?>
                                <div class="text-lg font-bold text-white">
                                    $<?php echo number_format($roadmap['price'], 2); ?>
                                </div>
                                <?php else: ?>
                                <div class="text-green-400 font-bold flex items-center">
                                    <i data-lucide="gift" class="lucide mr-2"></i>
                                    FREE
                                </div>
                                <?php endif; ?>
                                
                                <div class="flex space-x-2">
                                    <a href="<?php echo $BASE_PATH; ?>/student/view_roadmap.php?id=<?php echo $roadmap['id']; ?>" 
                                       class="btn-secondary px-3 py-2 text-sm">
                                        <i data-lucide="eye" class="lucide mr-1"></i>
                                        View
                                    </a>
                                    <a href="<?php echo $BASE_PATH; ?>/student/enroll.php?id=<?php echo $roadmap['id']; ?>" 
                                       class="btn-primary px-4 py-2 text-sm">
                                        <i data-lucide="shopping-cart" class="lucide mr-1"></i>
                                        Enroll
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12 border-2 border-dashed border-gray-800 rounded-2xl">
                        <div class="w-16 h-16 bg-gradient-to-br from-gray-800 to-gray-900 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i data-lucide="book-open" class="lucide text-gray-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-400 mb-2">No roadmaps available</h3>
                        <p class="text-gray-500">Check back later for new learning paths!</p>
                    </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer mt-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
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

        // Search functionality
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            let searchTimeout;
            let lastSearchTerm = '';
            
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.trim().toLowerCase();
                
                clearTimeout(searchTimeout);
                
                if (searchTerm === lastSearchTerm) {
                    return;
                }
                
                lastSearchTerm = searchTerm;
                
                searchTimeout = setTimeout(() => {
                    const cards = document.querySelectorAll('.roadmap-card');
                    let foundCount = 0;
                    
                    // Remove all previous search match styling
                    cards.forEach(card => {
                        card.classList.remove('search-match', 'highlight-card');
                        card.style.display = 'block';
                    });
                    
                    // Search through cards and mark matches
                    if (searchTerm.length >= 2) {
                        cards.forEach(card => {
                            const title = card.getAttribute('data-roadmap-title').toLowerCase();
                            const description = card.getAttribute('data-roadmap-description').toLowerCase();
                            const instructor = card.getAttribute('data-instructor').toLowerCase();
                            
                            if (title.includes(searchTerm) || description.includes(searchTerm) || instructor.includes(searchTerm)) {
                                // Highlight matching card
                                card.classList.add('search-match');
                                foundCount++;
                            } else {
                                // Hide non-matching cards
                                card.style.display = 'none';
                            }
                        });
                        
                        // Show notification
                        if (foundCount > 0) {
                            showNotification(`Found ${foundCount} roadmap${foundCount > 1 ? 's' : ''}`, 'success');
                        } else {
                            showNotification('No roadmaps found', 'warning');
                        }
                    } else if (searchTerm.length === 0) {
                        // Show all cards when search is cleared
                        cards.forEach(card => {
                            card.style.display = 'block';
                        });
                    }
                }, 500);
            });
            
            // Clear search on escape key
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    this.value = '';
                    lastSearchTerm = '';
                    
                    // Show all cards
                    const cards = document.querySelectorAll('.roadmap-card');
                    cards.forEach(card => {
                        card.style.display = 'block';
                        card.classList.remove('search-match', 'highlight-card');
                    });
                }
            });
        }

        // Notification function
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300 ${
                type === 'success' ? 'bg-green-900/90 text-green-100 border border-green-800' :
                type === 'warning' ? 'bg-yellow-900/90 text-yellow-100 border border-yellow-800' :
                'bg-blue-900/90 text-blue-100 border border-blue-800'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i data-lucide="${type === 'success' ? 'check-circle' : type === 'warning' ? 'alert-triangle' : 'info'}" class="lucide mr-3"></i>
                    <span>${message}</span>
                </div>
            `;
            
            // Add to body
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
            
            // Update icons
            lucide.createIcons();
        }

        // Open edit profile modal
        function openEditProfile() {
            // Redirect to dashboard edit profile
            window.location.href = '<?php echo $BASE_PATH; ?>/student/dashboard.php#edit-profile';
        }

        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            const animatedElements = document.querySelectorAll('.animate-fade-in');
            animatedElements.forEach((el, index) => {
                el.style.animationDelay = `${index * 100}ms`;
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.getElementById('search-input');
                if (searchInput) {
                    searchInput.focus();
                }
            }
            
            // Escape to close dropdowns
            if (e.key === 'Escape') {
                const userMenu = document.getElementById('user-menu');
                if (userMenu && !userMenu.classList.contains('hidden')) {
                    userMenu.classList.add('hidden');
                }
            }
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
    </script>
</body>
</html>