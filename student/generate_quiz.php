<?php
// api/generate_quiz.php

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// In production, this would call an AI API
// For demo, we'll return mock questions
function generateQuizQuestions($roadmap_id, $count = 15) {
    // Fetch roadmap title for context
    global $pdo;
    $stmt = $pdo->prepare("SELECT title FROM roadmaps WHERE id = ?");
    $stmt->execute([$roadmap_id]);
    $roadmap = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $roadmap_title = $roadmap['title'] ?? 'General Knowledge';
    
    // Mock questions based on roadmap title
    // In real app, this would call OpenAI API or similar
    $questions = [
        [
            'question' => "What is the primary goal of learning {$roadmap_title}?",
            'options' => [
                'a' => "To memorize all concepts",
                'b' => "To build practical skills",
                'c' => "To pass exams only",
                'd' => "To get a certificate"
            ],
            'correct_answer' => 'b'
        ],
        [
            'question' => "Which of these is NOT a key component of {$roadmap_title}?",
            'options' => [
                'a' => "Foundational principles",
                'b' => "Advanced techniques",
                'c' => "Historical background",
                'd' => "Practical applications"
            ],
            'correct_answer' => 'c'
        ],
        // Add more questions...
    ];
    
    // Generate 15 questions
    $all_questions = [];
    for ($i = 0; $i < $count; $i++) {
        $question_num = $i + 1;
        $all_questions[$question_num] = [
            'question' => "Question {$question_num} about {$roadmap_title}: What is an important concept to remember?",
            'options' => [
                'a' => "Concept A",
                'b' => "Concept B",
                'c' => "Concept C",
                'd' => "Concept D"
            ],
            'correct_answer' => ['a', 'b', 'c', 'd'][$i % 4]
        ];
    }
    
    return $all_questions;
}

// Handle API request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $roadmap_id = filter_input(INPUT_GET, 'roadmap_id', FILTER_VALIDATE_INT);
    
    if (!$roadmap_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Roadmap ID is required']);
        exit();
    }
    
    $questions = generateQuizQuestions($roadmap_id, 15);
    
    // Store questions in session for validation
    session_start();
    $_SESSION['quiz_questions'] = $questions;
    $_SESSION['quiz_roadmap_id'] = $roadmap_id;
    $_SESSION['quiz_generated_at'] = time();
    
    echo json_encode([
        'success' => true,
        'questions' => $questions,
        'count' => count($questions),
        'instructions' => 'You need 12 correct answers (80%) to pass. Maximum 3 attempts allowed.'
    ]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>