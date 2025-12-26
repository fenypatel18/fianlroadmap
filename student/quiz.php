<?php
// student/quiz.php

// --- 1. SETUP & SECURITY ---
session_start();
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
requireStudent();

$student_id = $_SESSION['user_id'];
$roadmap_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$error_message = '';
$is_eligible = false;

// --- 2. VALIDATION & ELIGIBILITY CHECK ---
if (!$roadmap_id) {
    header('Location: /student/dashboard.php');
    exit();
}

// Fetch roadmap details
$stmt = $pdo->prepare("SELECT title FROM roadmaps WHERE id = ? AND status = 'approved'");
$stmt->execute([$roadmap_id]);
$roadmap = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$roadmap) {
    die("Roadmap not found.");
}

// Check if student is enrolled
$stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND roadmap_id = ?");
$stmt->execute([$student_id, $roadmap_id]);
if (!$stmt->fetch()) {
    $error_message = "You are not enrolled in this roadmap, so you cannot take the quiz.";
} else {
    // Eligibility check: Compare total videos vs. completed videos.
    // Count total videos in the entire roadmap.
    $stmt = $pdo->prepare("
        SELECT COUNT(v.id) 
        FROM roadmap_videos v 
        JOIN roadmap_phases p ON v.phase_id = p.id 
        WHERE p.roadmap_id = ?
    ");
    $stmt->execute([$roadmap_id]);
    $total_videos = (int)$stmt->fetchColumn();

    // Count videos completed by the student for this roadmap.
    $stmt = $pdo->prepare("
        SELECT COUNT(id) 
        FROM progress 
        WHERE student_id = ? AND roadmap_id = ? AND completed_at IS NOT NULL
    ");
    $stmt->execute([$student_id, $roadmap_id]);
    $completed_videos = (int)$stmt->fetchColumn();

    // The student is eligible only if they have completed every video.
    if ($total_videos > 0 && $completed_videos >= $total_videos) {
        $is_eligible = true;
    } else {
        $error_message = "You have not completed all the videos in this roadmap yet. Please finish the course before taking the final quiz.";
    }
}

// --- 3. MOCK QUIZ QUESTIONS & ANSWERS ---
// NOTE: In a production app, this would come from a `quiz_questions` database table.
// The 'answer' key holds the correct option key.
$mock_questions = [
    1 => [
        'question' => 'What does PHP stand for?',
        'options' => ['a' => 'Personal Home Page', 'b' => 'PHP: Hypertext Preprocessor', 'c' => 'Private Host Protocol', 'd' => 'Programming Hypertext Protocol'],
        'answer' => 'b',
    ],
    2 => [
        'question' => 'Which of the following is used to display output in PHP?',
        'options' => ['a' => 'echo', 'b' => 'console.log', 'c' => 'print_f', 'd' => 'write'],
        'answer' => 'a',
    ],
    3 => [
        'question' => 'How do you start a session in PHP?',
        'options' => ['a' => 'start_session()', 'b' => 'new Session()', 'c' => 'session_start()', 'd' => 'session.begin()'],
        'answer' => 'c',
    ],
    4 => [
        'question' => 'What is the correct way to include the file "time.php"?',
        'options' => ['a' => '<!-- include file="time.php" -->', 'b' => 'include("time.php");', 'c' => '<?php include "time.php"; ?>', 'd' => 'All of the above'],
        'answer' => 'c',
    ],
    5 => [
        'question' => 'Which operator is used to check if two values are equal AND of the same type?',
        'options' => ['a' => '==', 'b' => '=', 'c' => '===', 'd' => '!='],
        'answer' => 'c',
    ],
];

// Store the correct answers in the session to securely validate them on the submission page.
// This prevents a user from seeing the answers in the HTML source.
$_SESSION['quiz_answers'] = array_column($mock_questions, 'answer', array_keys($mock_questions));
$_SESSION['quiz_roadmap_id'] = $roadmap_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Quiz: <?php echo htmlspecialchars($roadmap['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

    <div class="container mx-auto p-4 sm:p-8 max-w-4xl">
        <div class="bg-white rounded-lg shadow-xl p-8">
            <h1 class="text-3xl font-bold text-center text-gray-800 mb-2">Final Quiz</h1>
            <h2 class="text-xl font-semibold text-center text-indigo-600 mb-8"><?php echo htmlspecialchars($roadmap['title']); ?></h2>

            <?php if ($is_eligible): ?>
                <form action="quiz_submit.php" method="POST" class="space-y-8">
                    <input type="hidden" name="roadmap_id" value="<?php echo $roadmap_id; ?>">
                    
                    <?php foreach ($mock_questions as $id => $data): ?>
                        <fieldset class="p-6 border border-gray-200 rounded-lg">
                            <legend class="text-lg font-semibold text-gray-900 px-2">Question <?php echo $id; ?></legend>
                            <p class="mb-4 text-gray-700"><?php echo htmlspecialchars($data['question']); ?></p>
                            <div class="space-y-3">
                                <?php foreach ($data['options'] as $key => $text): ?>
                                    <label class="flex items-center p-3 border rounded-md hover:bg-gray-50 cursor-pointer">
                                        <input type="radio" name="answers[<?php echo $id; ?>]" value="<?php echo $key; ?>" required class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                        <span class="ml-3 text-gray-800"><?php echo htmlspecialchars($text); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>

                    <div class="text-center pt-4">
                        <button type="submit" class="w-full sm:w-auto px-12 py-4 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Submit Quiz
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <!-- Not Eligible State -->
                <div class="text-center p-8 bg-red-50 border-l-4 border-red-500 rounded-r-lg">
                    <h3 class="text-2xl font-bold text-red-800">Access Denied</h3>
                    <p class="mt-2 text-red-700"><?php echo htmlspecialchars($error_message); ?></p>
                    <a href="/student/roadmap_player.php?id=<?php echo $roadmap_id; ?>" class="mt-6 inline-block px-6 py-2 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700">
                        Return to Roadmap
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
