<?php
// student/quiz_results.php

session_start();
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /fianlroadmap/auth/login.php');
    exit();
}

if (!isset($_SESSION['quiz_results'])) {
    header('Location: dashboard.php');
    exit();
}

$results = $_SESSION['quiz_results'];
$student_id = $_SESSION['user_id'];

// Get roadmap details
$stmt = $pdo->prepare("SELECT title FROM roadmaps WHERE id = ?");
$stmt->execute([$results['roadmap_id']]);
$roadmap = $stmt->fetch();

// Check remaining attempts
$stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM quiz_attempts WHERE student_id = ? AND roadmap_id = ?");
$stmt->execute([$student_id, $results['roadmap_id']]);
$attempt_data = $stmt->fetch();
$remaining_attempts = max(0, 3 - $attempt_data['attempts']);

// Get certificate if passed
$certificate = null;
if ($results['passed'] && isset($results['certificate_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM certificates WHERE id = ?");
    $stmt->execute([$results['certificate_id']]);
    $certificate = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results | SkillPath</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-950 flex items-center justify-center p-4">
    
    <div class="w-full max-w-2xl">
        <div class="bg-gray-900/50 backdrop-blur-xl border border-gray-800 rounded-2xl overflow-hidden">
            <div class="p-8 border-b border-gray-800 text-center">
                <h1 class="text-3xl font-bold text-white mb-2">Quiz Results</h1>
                <p class="text-gray-400"><?php echo htmlspecialchars($roadmap['title']); ?></p>
                <p class="text-sm text-gray-500 mt-2">Attempt #<?php echo $results['attempt_number']; ?></p>
            </div>
            
            <div class="p-8 border-b border-gray-800">
                <div class="flex flex-col items-center">
                    <div class="relative w-48 h-48 mb-8">
                        <svg class="w-full h-full transform -rotate-90">
                            <circle cx="96" cy="96" r="84" stroke="rgba(255,255,255,0.1)" stroke-width="12" fill="none"/>
                            <circle cx="96" cy="96" r="84" 
                                    stroke="<?php echo $results['passed'] ? 'url(#gradient-pass)' : 'url(#gradient-fail)'; ?>" 
                                    stroke-width="12" 
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-dasharray="528"
                                    stroke-dashoffset="<?php echo 528 - (528 * $results['percentage'] / 100); ?>"/>
                            
                            <defs>
                                <linearGradient id="gradient-pass" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" style="stop-color:#10b981; stop-opacity:1" />
                                    <stop offset="100%" style="stop-color:#34d399; stop-opacity:1" />
                                </linearGradient>
                                <linearGradient id="gradient-fail" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" style="stop-color:#ef4444; stop-opacity:1" />
                                    <stop offset="100%" style="stop-color:#f97316; stop-opacity:1" />
                                </linearGradient>
                            </defs>
                        </svg>
                        
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-5xl font-bold text-white"><?php echo $results['score']; ?>/<?php echo $results['total']; ?></span>
                            <span class="text-lg <?php echo $results['passed'] ? 'text-green-400' : 'text-red-400'; ?> font-semibold">
                                <?php echo round($results['percentage']); ?>%
                            </span>
                        </div>
                    </div>
                    
                    <div class="text-center mb-8">
                        <?php if ($results['passed']): ?>
                            <div class="inline-flex items-center px-6 py-3 bg-green-900/30 border border-green-700 rounded-full">
                                <i data-lucide="check-circle" class="w-6 h-6 text-green-400 mr-2"></i>
                                <span class="text-xl font-bold text-green-400">You Passed!</span>
                            </div>
                            <p class="text-gray-300 mt-3">Certificate generated successfully!</p>
                        <?php else: ?>
                            <div class="inline-flex items-center px-6 py-3 bg-red-900/30 border border-red-700 rounded-full">
                                <i data-lucide="x-circle" class="w-6 h-6 text-red-400 mr-2"></i>
                                <span class="text-xl font-bold text-red-400">Not Passed</span>
                            </div>
                            <p class="text-gray-300 mt-3">You need 80% to pass.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-6 w-full max-w-md">
                        <div class="bg-gray-800/50 p-4 rounded-xl text-center">
                            <div class="text-2xl font-bold text-white"><?php echo $results['attempt_number']; ?></div>
                            <div class="text-gray-400 text-sm">Attempt Number</div>
                        </div>
                        <div class="bg-gray-800/50 p-4 rounded-xl text-center">
                            <div class="text-2xl font-bold text-white"><?php echo $remaining_attempts; ?></div>
                            <div class="text-gray-400 text-sm">Remaining Attempts</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-8">
                <div class="space-y-4">
                    <?php if ($results['passed']): ?>
                        <?php if ($certificate): ?>
                            <a href="<?php echo $BASE_PATH; ?>/student/certificate.php?id=<?php echo $results['roadmap_id']; ?>" 
                               class="w-full flex items-center justify-center px-6 py-4 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl hover:opacity-90">
                                <i data-lucide="award" class="w-5 h-5 mr-2"></i>
                                View Certificate
                            </a>
                        <?php endif; ?>
                        
                        <a href="<?php echo $BASE_PATH; ?>/student/roadmap_player.php?id=<?php echo $results['roadmap_id']; ?>" 
                           class="w-full flex items-center justify-center px-6 py-4 border-2 border-gray-700 text-gray-300 rounded-xl hover:bg-gray-800">
                            <i data-lucide="arrow-left" class="w-5 h-5 mr-2"></i>
                            Return to Course
                        </a>
                        
                    <?php else: ?>
                        <?php if ($remaining_attempts > 0): ?>
                            <a href="<?php echo $BASE_PATH; ?>/student/quiz.php?id=<?php echo $results['roadmap_id']; ?>" 
                               class="w-full flex items-center justify-center px-6 py-4 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl hover:opacity-90">
                                <i data-lucide="refresh-cw" class="w-5 h-5 mr-2"></i>
                                Retry Quiz (<?php echo $remaining_attempts; ?> left)
                            </a>
                        <?php else: ?>
                            <div class="text-center py-4 text-red-500 border border-red-700 rounded-xl bg-red-900/20">
                                <i data-lucide="alert-triangle" class="w-6 h-6 inline mr-2"></i>
                                No attempts remaining
                            </div>
                        <?php endif; ?>
                        
                        <a href="<?php echo $BASE_PATH; ?>/student/roadmap_player.php?id=<?php echo $results['roadmap_id']; ?>" 
                           class="w-full flex items-center justify-center px-6 py-4 border-2 border-gray-700 text-gray-300 rounded-xl hover:bg-gray-800">
                            <i data-lucide="book-open" class="w-5 h-5 mr-2"></i>
                            Review Course
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
<?php
// Clear session data
unset($_SESSION['quiz_results'], $_SESSION['quiz_detailed_results']);
?>