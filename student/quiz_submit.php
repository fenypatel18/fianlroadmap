<?php
// student/quiz_submit.php

// --- 1. SETUP & SECURITY ---
session_start();
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
requireStudent();

// --- 2. VALIDATE REQUEST & SESSION DATA ---
// Block direct access and ensure form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /student/dashboard.php');
    exit();
}

// Verify that the quiz answers and roadmap ID are in the session.
// This is crucial for security and correct processing.
if (!isset($_SESSION['quiz_answers'], $_SESSION['quiz_roadmap_id'])) {
    die("Error: Could not process quiz results. Your session may have expired.");
}

$student_id = $_SESSION['user_id'];
$correct_answers = $_SESSION['quiz_answers'];
$roadmap_id = $_SESSION['quiz_roadmap_id'];
$user_answers = $_POST['answers'] ?? [];
$total_questions = count($correct_answers);
$score = 0;

// --- 3. CALCULATE SCORE ---
// Loop through the correct answers and compare them to the user's submitted answers.
foreach ($correct_answers as $question_id => $correct_option) {
    // Check if the user answered this question and if their answer was correct.
    if (isset($user_answers[$question_id]) && $user_answers[$question_id] === $correct_option) {
        $score++;
    }
}

// Calculate the final percentage score.
$percentage_score = ($total_questions > 0) ? ($score / $total_questions) * 100 : 0;

// --- 4. DETERMINE PASS/FAIL STATUS & STORE ATTEMPT ---
$passing_score = 60; // 60% required to pass.
$status = ($percentage_score >= $passing_score) ? 'passed' : 'failed';

try {
    // Insert the quiz attempt into the database for record-keeping.
    $stmt = $pdo->prepare("
        INSERT INTO quiz_attempts (student_id, roadmap_id, score, status, submitted_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$student_id, $roadmap_id, $percentage_score, $status]);
} catch (PDOException $e) {
    // In a real application, you'd log this error.
    die("Database error: Could not save your quiz attempt.");
}

// --- 5. CLEAN UP SESSION --- 
// Unset the session variables to prevent re-submission or errors.
unset($_SESSION['quiz_answers'], $_SESSION['quiz_roadmap_id']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="w-full max-w-2xl p-8 text-center space-y-6 bg-white rounded-lg shadow-2xl m-4">
        
        <?php if ($status === 'passed'): ?>
            <!-- Passed State -->
            <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto">
                <svg class="w-16 h-16 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h1 class="text-4xl font-extrabold text-green-700">Congratulations, You Passed!</h1>
        <?php else: ?>
            <!-- Failed State -->
            <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mx-auto">
                 <svg class="w-16 h-16 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
            </div>
            <h1 class="text-4xl font-extrabold text-red-700">Don't Give Up!</h1>
        <?php endif; ?>

        <p class="text-lg text-gray-600">You have completed the quiz.</p>
        
        <div class="p-6 bg-gray-50 rounded-lg">
            <p class="text-xl text-gray-700">Your Score:</p>
            <p class="text-6xl font-bold <?php echo ($status === 'passed') ? 'text-green-600' : 'text-red-600'; ?>">
                <?php echo round($percentage_score); ?>%
            </p>
            <p class="text-md text-gray-500 mt-2">
                (You answered <?php echo $score; ?> out of <?php echo $total_questions; ?> questions correctly)
            </p>
        </div>

        <p class="text-gray-600">
            <?php if ($status === 'passed'): ?>
                You are now eligible to receive your certificate!
            <?php else: ?>
                You need a score of <?php echo $passing_score; ?>% or higher to pass. Please review the course materials and try again.
            <?php endif; ?>
        </p>

        <div class="pt-4">
            <?php if ($status === 'passed'): ?>
                <!-- In the future, this will link to the certificate generation page -->
                <a href="#" class="inline-block px-8 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">Get Certificate (Coming Soon)</a>
            <?php else: ?>
                 <a href="/student/roadmap_player.php?id=<?php echo $roadmap_id; ?>" class="inline-block px-8 py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700">
                    Return to Course
                </a>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>
