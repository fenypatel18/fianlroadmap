<?php
// test_api.php
require_once 'config/db.php';
require_once 'config/openai_quiz.php';

$quiz = new AIQuizManager($pdo);
$result = $quiz->testAPIConnection();

echo "<h2>API Connection Test</h2>";
if ($result['success']) {
    echo "<p style='color: green;'>✓ " . $result['message'] . "</p>";
    
    // Test generating a quiz
    echo "<h3>Test Quiz Generation</h3>";
    try {
        $quiz_data = $quiz->generateQuiz(14, 9, 'Data Analytics', 3);
        if (!empty($quiz_data['questions'])) {
            echo "<p style='color: green;'>✓ Quiz generation successful!</p>";
            echo "<pre>" . print_r($quiz_data['questions'], true) . "</pre>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Generation failed: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>✗ " . $result['message'] . "</p>";
    echo "<p>Get an API key from: <a href='https://platform.openai.com/api-keys' target='_blank'>OpenAI Platform</a></p>";
}
?>