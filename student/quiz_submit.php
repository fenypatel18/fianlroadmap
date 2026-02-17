<?php
// student/quiz_submit.php

session_start();
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/openai_quiz.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /fianlroadmap/auth/login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$roadmap_id = $_POST['roadmap_id'] ?? null;
$attempt_number = $_POST['attempt_number'] ?? 1;
$quiz_session_id = $_POST['quiz_session_id'] ?? null;
$user_answers = $_POST['answers'] ?? [];

if (!$roadmap_id || !$quiz_session_id) {
    $_SESSION['error'] = "Invalid quiz submission";
    header("Location: quiz.php?id=" . $roadmap_id);
    exit();
}

// Evaluate quiz
$ai_quiz = new AIQuizManager($pdo);

try {
    $evaluation = $ai_quiz->evaluateQuiz($quiz_session_id, $user_answers);
    
    $score = $evaluation['score'];
    $total = $evaluation['total'];
    $percentage = $evaluation['percentage'];
    $passed = $evaluation['passed'];
    $detailed_results = $evaluation['results'];
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error evaluating quiz: " . $e->getMessage();
    header("Location: quiz.php?id=" . $roadmap_id);
    exit();
}

// Save to database
try {
    $pdo->beginTransaction();
    
    // Save quiz attempt
    $stmt = $pdo->prepare("
        INSERT INTO quiz_attempts 
        (student_id, roadmap_id, score, passed, attempt_number, attempt_date)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$student_id, $roadmap_id, $score, $passed ? 1 : 0, $attempt_number]);
    $attempt_id = $pdo->lastInsertId();
    
    // Generate certificate if passed
    $certificate_id = null;
    if ($passed) {
        $certificate_code = 'CERT-' . strtoupper(uniqid());
        $certificate_url = "/certificates/{$certificate_code}.pdf";
        
        // Create certificate record
        $stmt = $pdo->prepare("
            INSERT INTO certificates 
            (student_id, roadmap_id, issue_date, certificate_url)
            VALUES (?, ?, NOW(), ?)
        ");
        $stmt->execute([$student_id, $roadmap_id, $certificate_url]);
        $certificate_id = $pdo->lastInsertId();
        
        // Create simple certificate file
        $cert_dir = __DIR__ . '/../certificates';
        if (!is_dir($cert_dir)) {
            mkdir($cert_dir, 0777, true);
        }
        
        $cert_html = "<!DOCTYPE html>
        <html>
        <head>
            <title>Certificate</title>
            <style>
                body { font-family: Arial; text-align: center; padding: 50px; }
                .certificate { border: 10px solid gold; padding: 50px; display: inline-block; }
                h1 { color: #2c3e50; }
            </style>
        </head>
        <body>
            <div class='certificate'>
                <h1>Certificate of Completion</h1>
                <p>Student ID: $student_id</p>
                <p>Course ID: $roadmap_id</p>
                <p>Score: $score/$total ($percentage%)</p>
                <p>Certificate Code: $certificate_code</p>
                <p>Issued: " . date('Y-m-d') . "</p>
            </div>
        </body>
        </html>";
        
        file_put_contents($cert_dir . '/' . $certificate_code . '.html', $cert_html);
    }
    
    $pdo->commit();
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error saving results: " . $e->getMessage();
    header("Location: quiz.php?id=" . $roadmap_id);
    exit();
}

// Store results in session
$_SESSION['quiz_results'] = [
    'passed' => $passed,
    'score' => $score,
    'total' => $total,
    'percentage' => round($percentage, 2),
    'attempt_number' => $attempt_number,
    'roadmap_id' => $roadmap_id,
    'attempt_id' => $attempt_id,
    'certificate_generated' => $passed,
    'certificate_id' => $certificate_id
];

$_SESSION['quiz_detailed_results'] = $detailed_results;

// Clear quiz session
unset($_SESSION['quiz_session_id'], $_SESSION['quiz_roadmap_id'], $_SESSION['quiz_attempt']);

header("Location: quiz_results.php");
exit();
?>