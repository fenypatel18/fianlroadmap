<?php
// student/certificate.php

session_start();
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /fianlroadmap/auth/login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$roadmap_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$roadmap_id) {
    header('Location: dashboard.php');
    exit();
}

// Get certificate
$stmt = $pdo->prepare("
    SELECT c.*, r.title as roadmap_title, u.name as student_name
    FROM certificates c
    JOIN roadmaps r ON c.roadmap_id = r.id
    JOIN users u ON c.student_id = u.id
    WHERE c.student_id = ? AND c.roadmap_id = ?
    ORDER BY c.issue_date DESC
    LIMIT 1
");
$stmt->execute([$student_id, $roadmap_id]);
$certificate = $stmt->fetch();

if (!$certificate) {
    $_SESSION['error'] = "Certificate not found";
    header("Location: roadmap_player.php?id=" . $roadmap_id);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate | <?php echo htmlspecialchars($certificate['roadmap_title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    
    <div class="max-w-4xl w-full">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-gray-800">Certificate of Completion</h1>
            <a href="roadmap_player.php?id=<?php echo $roadmap_id; ?>" class="text-blue-600 hover:underline mt-4 inline-block">
                ← Back to Course
            </a>
        </div>
        
        <div class="bg-white border-8 border-amber-500 rounded-3xl p-12 shadow-2xl">
            <div class="text-center">
                <div class="mb-8">
                    <h2 class="text-4xl font-bold text-gray-900 mb-2">Certificate of Achievement</h2>
                    <p class="text-gray-600 text-xl">This certifies that</p>
                </div>
                
                <div class="my-12">
                    <h3 class="text-5xl font-bold text-gray-900 mb-6"><?php echo htmlspecialchars($certificate['student_name']); ?></h3>
                    <div class="h-2 w-64 mx-auto bg-gradient-to-r from-amber-400 to-yellow-500 rounded-full"></div>
                </div>
                
                <div class="mb-12">
                    <p class="text-gray-700 text-2xl mb-4">has successfully completed the course</p>
                    <h4 class="text-4xl font-bold text-gray-900"><?php echo htmlspecialchars($certificate['roadmap_title']); ?></h4>
                </div>
                
                <div class="grid grid-cols-3 gap-8 max-w-2xl mx-auto mt-16">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-gray-900"><?php echo date('d', strtotime($certificate['issue_date'])); ?></div>
                        <div class="text-gray-600">Day</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-gray-900"><?php echo date('M', strtotime($certificate['issue_date'])); ?></div>
                        <div class="text-gray-600">Month</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-gray-900"><?php echo date('Y', strtotime($certificate['issue_date'])); ?></div>
                        <div class="text-gray-600">Year</div>
                    </div>
                </div>
                
                <div class="mt-16 pt-8 border-t-2 border-gray-300">
                    <div class="flex justify-between">
                        <div class="text-left">
                            <div class="text-sm text-gray-500">Certificate ID</div>
                            <div class="font-mono font-bold"><?php echo basename($certificate['certificate_url'], '.pdf'); ?></div>
                        </div>
                        
                        <div class="text-center">
                            <div class="text-xl font-bold text-gray-900">SkillPath Academy</div>
                            <div class="text-gray-600">Online Learning Platform</div>
                        </div>
                        
                        <div class="text-right">
                            <div class="text-sm text-gray-500">Issued On</div>
                            <div class="font-bold"><?php echo date('F j, Y', strtotime($certificate['issue_date'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-8 text-center">
            <button onclick="window.print()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Print Certificate
            </button>
        </div>
    </div>
    
    <script>
        lucide.createIcons();
    </script>
</body>
</html>