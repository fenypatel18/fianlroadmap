<?php
// student/enroll.php

// --- SETUP & SECURITY ---
session_start();
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
requireStudent();

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];
$BASE_PATH = '/fianlroadmap';

// Define uploads directory
$UPLOADS_DIR = $_SERVER['DOCUMENT_ROOT'] . $BASE_PATH . '/uploads/profile_pictures/';

// --- FETCH STUDENT PROFILE DATA ---
$stmt = $pdo->prepare("SELECT id, name, email, profile_picture FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $student['profile_picture'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($student['name']) . '&background=random';

$roadmap_id = null;
$roadmap = null;
$error_message = '';

// --- 1. VALIDATE INPUT & ROADMAP ---
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: explore_roadmaps.php');
    exit();
}
$roadmap_id = $_GET['id'];

// Fetch roadmap details, ensuring it's approved and valid
$stmt = $pdo->prepare("
    SELECT r.*, u.name as instructor_name 
    FROM roadmaps r 
    JOIN users u ON r.instructor_id = u.id 
    WHERE r.id = ? AND r.status = 'approved'
");
$stmt->execute([$roadmap_id]);
$roadmap = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$roadmap) {
    die("Error: This roadmap is not available for enrollment.");
}

// Get roadmap stats
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT rp.id) as phase_count,
        COUNT(DISTINCT e.id) as enrollment_count,
        AVG(f.rating) as avg_rating
    FROM roadmaps r
    LEFT JOIN roadmap_phases rp ON r.id = rp.roadmap_id
    LEFT JOIN enrollments e ON r.id = e.roadmap_id
    LEFT JOIN feedback f ON r.id = f.roadmap_id
    WHERE r.id = ?
    GROUP BY r.id
");
$statsStmt->execute([$roadmap_id]);
$roadmap_stats = $statsStmt->fetch();

// --- 2. CHECK FOR EXISTING ENROLLMENT ---
$stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND roadmap_id = ?");
$stmt->execute([$student_id, $roadmap_id]);
if ($stmt->fetch()) {
    // If already enrolled, redirect them to the roadmap view page.
    header('Location: view_roadmap.php?id=' . $roadmap_id . '&status=enrolled');
    exit();
}

// --- 3. HANDLE MOCK PAYMENT & ENROLLMENT (POST REQUEST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure the post request corresponds to the roadmap ID in the URL
    if (isset($_POST['roadmap_id']) && $_POST['roadmap_id'] == $roadmap_id) {
        
        $pdo->beginTransaction();
        try {
            // Step 3a: Insert a record into the 'payments' table to simulate payment
            $transaction_id = 'MOCK_' . time() . '_' . rand(1000, 9999);
            $payment_stmt = $pdo->prepare(
                "INSERT INTO payments (student_id, roadmap_id, amount, transaction_id, payment_date) VALUES (?, ?, ?, ?, NOW())"
            );
            $payment_stmt->execute([$student_id, $roadmap_id, $roadmap['price'], $transaction_id]);

            // Step 3b: Insert a record into the 'enrollments' table
            $enrollment_stmt = $pdo->prepare(
                "INSERT INTO enrollments (student_id, roadmap_id, enrollment_date) VALUES (?, ?, NOW())"
            );
            $enrollment_stmt->execute([$student_id, $roadmap_id]);

            // If both inserts succeed, commit the transaction
            $pdo->commit();
            
            // Step 3c: Store details in session for the success page
            $_SESSION['payment_success_details'] = [
                'roadmap_id' => $roadmap_id,
                'roadmap_title' => $roadmap['title'],
                'transaction_id' => $transaction_id,
                'amount' => $roadmap['price'],
                'date' => date('Y-m-d H:i:s')
            ];

            // Step 3d: Redirect to the payment success page
            header('Location: payment_success.php');
            exit();

        } catch (PDOException $e) {
            // If any database error occurs, roll back the entire transaction
            $pdo->rollBack();
            $error_message = "Enrollment failed. Please try again. Error: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid request. Please try again.";
    }
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
    <title>Enroll in <?php echo htmlspecialchars($roadmap['title']); ?> | YourRoadmap</title>
    
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
        
        .text-purple {
            color: rgb(168, 85, 247);
        }
        
        .bg-glass {
            background-color: rgba(17, 25, 40, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.125);
        }
        
        .backdrop-blur-saturate {
            backdrop-filter: blur(16px) saturate(180%);
        }
        
        /* Toast notification styles */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            transform: translateX(120%);
            transition: transform 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-left: 4px solid #059669;
        }
        
        .toast.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border-left: 4px solid #d97706;
        }
        
        .toast.error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border-left: 4px solid #dc2626;
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
        
        /* Card Hover Effects */
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        /* Payment Card Styles */
        .payment-card {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .enroll-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }
        
        .enroll-button:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .free-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .paid-badge {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
    </style>
</head>
<body class="dark min-h-screen flex flex-col">
    <!-- Background Grid -->
    <div class="bg-hero-grid"></div>
    
    <!-- Toast Notification Container -->
    <div id="toast-container"></div>

    <!-- Navigation Header - BLACK BACKGROUND -->
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
                    
                    <!-- Desktop Navigation -->
                    <div class="hidden md:flex md:items-center md:ml-10 md:space-x-4">
                        <a href="<?php echo $BASE_PATH; ?>/index.php" class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-white hover:bg-gray-800 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon mr-2">
                                <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
                                <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            </svg>
                            Home
                        </a>
                        <a href="<?php echo $BASE_PATH; ?>/student/explore_roadmaps.php" class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-white bg-purple-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon mr-2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>
                            </svg>
                            Browse Roadmaps
                        </a>
                        <a href="#" class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-white hover:bg-gray-800 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon mr-2">
                                <path d="m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526"></path>
                                <circle cx="12" cy="8" r="6"></circle>
                            </svg>
                            My Certificates
                        </a>
                        <a href="<?php echo $BASE_PATH; ?>/student/dashboard.php" class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-white hover:bg-gray-800 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon mr-2">
                                <rect width="18" height="18" x="3" y="3" rx="2"></rect>
                                <path d="M3 9h18"></path>
                                <path d="M9 21V9"></path>
                            </svg>
                            Dashboard
                        </a>
                        <a href="#" class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-white hover:bg-gray-800 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon mr-2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                            Feedback
                        </a>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    <!-- User Profile Dropdown -->
                    <div class="relative">
                        <button class="flex items-center space-x-3 focus:outline-none" id="user-menu-button">
                            <img class="h-8 w-8 rounded-full border-2 border-purple-500/50" src="<?php echo htmlspecialchars($profile_picture); ?>" alt="<?php echo htmlspecialchars($student['name']); ?>" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($student['name']); ?>&background=random';">
                            <span class="hidden md:block text-sm font-medium text-white"><?php echo htmlspecialchars($student['name']); ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon text-gray-400">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div class="hidden absolute right-0 mt-2 w-48 bg-black border border-gray-700 rounded-lg shadow-lg py-1 z-50" id="user-menu">
                            <a href="<?php echo $BASE_PATH; ?>/student/dashboard.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon mr-2 inline">
                                    <rect width="18" height="18" x="3" y="3" rx="2"></rect>
                                    <path d="M3 9h18"></path>
                                    <path d="M9 21V9"></path>
                                </svg>
                                Dashboard
                            </a>
                            <a href="<?php echo $BASE_PATH; ?>/student/dashboard.php" class="block px-4 py-2 text-sm text-white hover:bg-gray-800 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon mr-2 inline">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                Edit Profile
                            </a>
                            <a href="/auth/logout.php" class="block px-4 py-2 text-sm text-red-400 hover:bg-gray-800 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon mr-2 inline">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                    <polyline points="16 17 21 12 16 7"></polyline>
                                    <line x1="21" y1="12" x2="9" y2="12"></line>
                                </svg>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1" style="background-color:rgb(19, 20, 23)">
        <div class="w-full">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="space-y-8">
                    <!-- Back Button -->
                    <div class="mb-6">
                        <a href="<?php echo $BASE_PATH; ?>/student/view_roadmap.php?id=<?php echo $roadmap_id; ?>" class="inline-flex items-center text-gray-400 hover:text-white transition-colors">
                            <i data-lucide="arrow-left" class="skill-icon mr-2"></i>
                            Back to Roadmap
                        </a>
                    </div>

                    <!-- Hero Section -->
                    <div class="relative mb-8">
                        <div class="bg-gradient-to-r from-blue-900/20 to-indigo-900/20 rounded-xl p-8">
                            <div class="flex items-center gap-4 mb-6">
                                <div class="h-16 w-16 rounded-lg bg-blue-900/30 flex items-center justify-center">
                                    <i data-lucide="<?php echo $roadmap_icon; ?>" class="skill-icon h-8 w-8 text-blue-400"></i>
                                </div>
                                <div>
                                    <h1 class="text-3xl font-bold text-white">Enroll in <?php echo htmlspecialchars($roadmap['title']); ?></h1>
                                    <p class="text-gray-300">Complete your enrollment to start learning</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="flex items-center gap-3">
                                    <i data-lucide="user" class="skill-icon text-blue-400"></i>
                                    <div>
                                        <p class="text-sm text-gray-400">Instructor</p>
                                        <p class="text-white font-medium"><?php echo htmlspecialchars($roadmap['instructor_name']); ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <i data-lucide="users" class="skill-icon text-purple-400"></i>
                                    <div>
                                        <p class="text-sm text-gray-400">Students Enrolled</p>
                                        <p class="text-white font-medium"><?php echo $roadmap_stats['enrollment_count'] ?? 0; ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <i data-lucide="layers" class="skill-icon text-green-400"></i>
                                    <div>
                                        <p class="text-sm text-gray-400">Learning Phases</p>
                                        <p class="text-white font-medium"><?php echo $roadmap_stats['phase_count'] ?? 0; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error Message -->
                    <?php if ($error_message): ?>
                        <div class="p-4 mb-6 text-sm text-red-400 bg-red-900/30 border border-red-800 rounded-lg" role="alert">
                            <div class="flex items-center gap-2">
                                <i data-lucide="alert-circle" class="skill-icon"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Payment Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- Left Column: Order Summary -->
                        <div class="lg:col-span-2 space-y-6">
                            <div class="payment-card p-6 card-hover">
                                <h2 class="text-2xl font-bold text-white mb-6">Order Summary</h2>
                                
                                <div class="space-y-4">
                                    <div class="flex justify-between items-center p-4 bg-gray-800/50 rounded-lg">
                                        <div>
                                            <h3 class="font-medium text-white"><?php echo htmlspecialchars($roadmap['title']); ?></h3>
                                            <p class="text-sm text-gray-400">Complete learning path</p>
                                        </div>
                                        <?php if ($roadmap['price'] > 0): ?>
                                            <span class="text-xl font-bold text-white">$<?php echo htmlspecialchars(number_format($roadmap['price'], 2)); ?></span>
                                        <?php else: ?>
                                            <span class="free-badge text-sm font-bold text-white px-3 py-1 rounded">FREE</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Benefits List -->
                                    <div class="space-y-3 mt-6">
                                        <h4 class="font-medium text-white">What's included:</h4>
                                        <ul class="space-y-2">
                                            <li class="flex items-start gap-3">
                                                <i data-lucide="check-circle" class="skill-icon text-green-400 mt-0.5 flex-shrink-0"></i>
                                                <span class="text-gray-300">Full access to all <?php echo $roadmap_stats['phase_count'] ?? 0; ?> learning phases</span>
                                            </li>
                                            <li class="flex items-start gap-3">
                                                <i data-lucide="check-circle" class="skill-icon text-green-400 mt-0.5 flex-shrink-0"></i>
                                                <span class="text-gray-300">Lifetime access to course materials</span>
                                            </li>
                                            <li class="flex items-start gap-3">
                                                <i data-lucide="check-circle" class="skill-icon text-green-400 mt-0.5 flex-shrink-0"></i>
                                                <span class="text-gray-300">Certificate of completion</span>
                                            </li>
                                            <li class="flex items-start gap-3">
                                                <i data-lucide="check-circle" class="skill-icon text-green-400 mt-0.5 flex-shrink-0"></i>
                                                <span class="text-gray-300">Access to community discussions</span>
                                            </li>
                                            <li class="flex items-start gap-3">
                                                <i data-lucide="check-circle" class="skill-icon text-green-400 mt-0.5 flex-shrink-0"></i>
                                                <span class="text-gray-300">Regular content updates</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Method -->
                            <div class="payment-card p-6 card-hover">
                                <h2 class="text-2xl font-bold text-white mb-6">Payment Method</h2>
                                <div class="space-y-4">
                                    <div class="p-4 bg-gray-800/50 rounded-lg border-2 border-blue-500/50">
                                        <div class="flex items-center gap-3">
                                            <div class="h-12 w-12 rounded-lg bg-blue-900/30 flex items-center justify-center">
                                                <i data-lucide="credit-card" class="skill-icon text-blue-400"></i>
                                            </div>
                                            <div class="flex-1">
                                                <p class="font-medium text-white">Mock Payment Gateway</p>
                                                <p class="text-sm text-gray-400">This is a demonstration payment system</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="p-4 bg-gray-800/30 rounded-lg">
                                        <p class="text-sm text-gray-400 mb-2">
                                            <i data-lucide="info" class="skill-icon inline-block mr-2 text-blue-400"></i>
                                            This is a mock payment system for demonstration purposes only.
                                        </p>
                                        <p class="text-sm text-gray-400">
                                            No real money will be charged. Click "Complete Enrollment" to simulate payment.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column: Payment Summary -->
                        <div class="space-y-6">
                            <div class="payment-card p-6 card-hover">
                                <h2 class="text-2xl font-bold text-white mb-6">Payment Summary</h2>
                                
                                <div class="space-y-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-400">Course Price</span>
                                        <span class="text-white font-medium">
                                            <?php if ($roadmap['price'] > 0): ?>
                                                $<?php echo htmlspecialchars(number_format($roadmap['price'], 2)); ?>
                                            <?php else: ?>
                                                FREE
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-400">Platform Fee</span>
                                        <span class="text-green-400">$0.00</span>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-400">Tax</span>
                                        <span class="text-green-400">$0.00</span>
                                    </div>
                                    
                                    <div class="border-t border-gray-700 pt-4">
                                        <div class="flex justify-between items-center">
                                            <span class="text-lg font-bold text-white">Total Amount</span>
                                            <span class="text-2xl font-bold text-white">
                                                <?php if ($roadmap['price'] > 0): ?>
                                                    $<?php echo htmlspecialchars(number_format($roadmap['price'], 2)); ?>
                                                <?php else: ?>
                                                    FREE
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Enrollment Form -->
                                    <form method="POST" action="enroll.php?id=<?php echo $roadmap_id; ?>">
                                        <input type="hidden" name="roadmap_id" value="<?php echo $roadmap_id; ?>">
                                        
                                        <!-- Terms Agreement -->
                                        <div class="mt-6 mb-4">
                                            <label class="flex items-start gap-3 cursor-pointer">
                                                <input type="checkbox" class="mt-1" required>
                                                <span class="text-sm text-gray-300">
                                                    I agree to the 
                                                    <a href="#" class="text-blue-400 hover:text-blue-300">Terms of Service</a> 
                                                    and 
                                                    <a href="#" class="text-blue-400 hover:text-blue-300">Privacy Policy</a>. 
                                                    I understand this is a mock payment for demonstration.
                                                </span>
                                            </label>
                                        </div>
                                        
                                        <!-- Submit Button -->
                                        <button type="submit" class="w-full enroll-button text-white font-medium py-3 px-4 rounded-md text-lg">
                                            <?php if ($roadmap['price'] > 0): ?>
                                                <i data-lucide="credit-card" class="skill-icon inline-block mr-2"></i>
                                                Complete Enrollment
                                            <?php else: ?>
                                                <i data-lucide="unlock" class="skill-icon inline-block mr-2"></i>
                                                Enroll for Free
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                    
                                    <!-- Security Note -->
                                    <div class="mt-4 p-3 bg-green-900/20 border border-green-800/50 rounded-lg">
                                        <div class="flex items-start gap-2">
                                            <i data-lucide="shield-check" class="skill-icon text-green-400 mt-0.5 flex-shrink-0"></i>
                                            <p class="text-sm text-green-300">
                                                <span class="font-medium">Secure Enrollment</span><br>
                                                Your enrollment is protected by our satisfaction guarantee.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Help Section -->
                            <div class="payment-card p-6">
                                <h3 class="font-medium text-white mb-3">
                                    <i data-lucide="help-circle" class="skill-icon inline-block mr-2 text-blue-400"></i>
                                    Need Help?
                                </h3>
                                <ul class="space-y-2 text-sm text-gray-400">
                                    <li>
                                        <a href="#" class="hover:text-white transition-colors">
                                            <i data-lucide="mail" class="skill-icon inline-block mr-2 h-4 w-4"></i>
                                            Contact Support
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" class="hover:text-white transition-colors">
                                            <i data-lucide="message-circle" class="skill-icon inline-block mr-2 h-4 w-4"></i>
                                            FAQ
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" class="hover:text-white transition-colors">
                                            <i data-lucide="file-text" class="skill-icon inline-block mr-2 h-4 w-4"></i>
                                            Refund Policy
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Cancel Option -->
                    <div class="text-center">
                        <a href="<?php echo $BASE_PATH; ?>/student/view_roadmap.php?id=<?php echo $roadmap_id; ?>" 
                           class="inline-flex items-center text-gray-400 hover:text-white transition-colors">
                            <i data-lucide="x" class="skill-icon mr-2"></i>
                            Cancel and return to roadmap
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer - SAME AS OTHER PAGES -->
    <div class="py-6 pb-10 text-white sm:py-16" style="background:rgb(0 3 25 )">
        <div class="container m-auto">
            <div class="flex flex-col justify-between items-center text-center">
                <div class="max-w-[525px]">
                    <p class="text-md flex items-center justify-center">
                        <a class="inline-flex items-center text-lg font-medium text-white transition-colors hover:text-gray-400" href="/">
                            <span class="text-white font-bold text-lg">YR</span>
                            <span class="ml-2">YourRoadmap</span>
                        </a>                       
                    </p>
                    <p class="my-4 text-slate-300/60">
                        Roadmaps, best practices, projects, articles, resources, and journeys have been created by the community to help you in choosing your path and growing in your career.
                    </p>
                    <div class="text-sm text-gray-400">
                        <div class="flex">
                            <div>
                                Ghai Technologies Pvt Ltd
                                <span class="mx-1.5">·</span>
                                <a href="/legal/terms" class="hover:text-white">Terms</a>
                                <span class="mx-1.5">·</span>
                                <a href="/legal/privacy" class="hover:text-white">Privacy</a>
                                <span class="mx-1.5">·</span>
                                <a href="/legal/refunds" class="hover:text-white">Refunds</a>
                                <span class="mx-1.5">·</span>
                            </div>
                            <div class="flex items-center">
                                <a aria-label="Write us an email" href="#" class="hover:text-white">
                                    <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 448 512" style="font-size:1.2em" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7-29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z"></path>
                                    </svg>
                                </a>
                                <a aria-label="Subscribe to YouTube channel" href="#" target="_blank" class="ml-2 hover:text-white">
                                    <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 576 512" style="font-size:1.2em" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M549.655 124.083c-6.281-23.65-24.787-42.276-48.284-48.597C458.781 64 288 64 288 64S117.22 64 74.629 75.486c-23.497 6.322-42.003 24.947-48.284 48.597-11.412 42.867-11.412 132.305-11.412 132.305s0 89.438 11.412 132.305c6.281 23.65 24.787 41.5 48.284 47.821C117.22 448 288 448 288 448s170.78 0 213.371-11.486c23.497-6.321 42.003-24.171 48.284-47.821 11.412-42.867 11.412-132.305 11.412-132.305s0-89.438-11.412-132.305zm-317.51 213.508V175.185l142.739 81.205-142.739 81.201z"></path>
                                    </svg>
                                </a>
                                <a aria-label="Follow on Twitter" href="mailto:yourroadmap@gmail.com" target="_blank" class="ml-2 hover:text-white">
                                    <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512" style="font-size:1.2em" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M424 80H88a56.06 56.06 0 0 0-56 56v240a56.06 56.06 0 0 0 56 56h336a56.06 56.06 0 0 0 56-56V136a56.06 56.06 0 0 0-56-56zm-14.18 92.63-144 112a16 16 0 0 1-19.64 0l-144-112a16 16 0 1 1 19.64-25.26L256 251.73l134.18-104.36a16 16 0 0 1 19.64 25.26z"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            lucide.createIcons();
            
            // Toggle user dropdown
            const userMenuButton = document.getElementById('user-menu-button');
            const userMenu = document.getElementById('user-menu');
            
            if (userMenuButton && userMenu) {
                userMenuButton.addEventListener('click', function() {
                    userMenu.classList.toggle('hidden');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                        userMenu.classList.add('hidden');
                    }
                });
            }
            
            // Toast notification function
            function showToast(message, type = 'success') {
                const toastContainer = document.getElementById('toast-container');
                
                // Create toast element
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.innerHTML = `
                    <div class="flex items-center">
                        <i data-lucide="${type === 'success' ? 'check-circle' : type === 'warning' ? 'alert-circle' : 'x-circle'}" class="skill-icon mr-2"></i>
                        <span>${message}</span>
                    </div>
                `;
                
                // Add to container
                toastContainer.appendChild(toast);
                
                // Trigger animation
                setTimeout(() => {
                    toast.classList.add('show');
                }, 10);
                
                // Remove after 4 seconds
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        if (toast.parentNode === toastContainer) {
                            toastContainer.removeChild(toast);
                        }
                    }, 300);
                }, 4000);
                
                // Update icons
                lucide.createIcons();
            }
            
            // Fallback for broken images
            const images = document.querySelectorAll('img');
            images.forEach(img => {
                img.addEventListener('error', function() {
                    if (this.src.includes('uploads/profile_pictures')) {
                        const name = this.alt || 'User';
                        this.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random`;
                    }
                });
            });
            
            // Form submission handling
            const enrollmentForm = document.querySelector('form');
            if (enrollmentForm) {
                enrollmentForm.addEventListener('submit', function(e) {
                    const checkbox = this.querySelector('input[type="checkbox"]');
                    if (!checkbox.checked) {
                        e.preventDefault();
                        showToast('Please agree to the terms and conditions', 'warning');
                        return false;
                    }
                    
                    const price = <?php echo $roadmap['price']; ?>;
                    if (price > 0) {
                        if (!confirm('Are you ready to complete the mock payment for $' + price.toFixed(2) + '?')) {
                            e.preventDefault();
                            return false;
                        }
                    } else {
                        if (!confirm('Are you ready to enroll in this free roadmap?')) {
                            e.preventDefault();
                            return false;
                        }
                    }
                    
                    // Show loading state
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i data-lucide="loader" class="skill-icon animate-spin inline-block mr-2"></i>Processing...';
                    submitBtn.disabled = true;
                    
                    // Re-enable after 2 seconds if form doesn't submit
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        lucide.createIcons();
                    }, 2000);
                });
            }
        });
    </script>
</body>
</html>