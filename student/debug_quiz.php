<?php
// debug_quiz.php
require_once 'config/db.php';
require_once 'config/openai_quiz.php';

echo "<h2>Quiz Debugger</h2>";

$quiz = new AIQuizManager($pdo);

// Test API connection
echo "<h3>API Connection Test</h3>";
$api_test = $quiz->testAPIConnection();
if ($api_test['success']) {
    echo "<p style='color: green;'>✓ " . $api_test['message'] . "</p>";
} else {
    echo "<p style='color: red;'>✗ " . $api_test['message'] . "</p>";
    echo "<p>Note: Without a valid API key, you'll get fallback questions.</p>";
}

// Generate test quiz
echo "<h3>Test Quiz Generation</h3>";
try {
    $quiz_data = $quiz->generateQuiz(14, 9, 'Data Analytics', 5);
    
    if (!empty($quiz_data['questions'])) {
        echo "<p style='color: green;'>✓ Generated " . count($quiz_data['questions']) . " questions</p>";
        
        foreach ($quiz_data['questions'] as $index => $question) {
            $q_num = $index + 1;
            echo "<div style='border: 1px solid #ccc; padding: 15px; margin: 10px 0;'>";
            echo "<h4>Question {$q_num}: " . htmlspecialchars($question['question']) . "</h4>";
            
            echo "<p><strong>Options:</strong></p>";
            echo "<ul>";
            foreach (['a', 'b', 'c', 'd'] as $letter) {
                if (isset($question['options'][$letter])) {
                    $style = ($letter == $question['correct_answer']) ? "color: green; font-weight: bold;" : "";
                    echo "<li style='{$style}'>" . strtoupper($letter) . ") " . htmlspecialchars($question['options'][$letter]) . "</li>";
                }
            }
            echo "</ul>";
            
            echo "<p><strong>Correct Answer:</strong> " . strtoupper($question['correct_answer']) . "</p>";
            echo "<p><strong>Explanation:</strong> " . htmlspecialchars($question['explanation'] ?? 'N/A') . "</p>";
            echo "</div>";
        }
    } else {
        echo "<p style='color: red;'>✗ No questions generated</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

// Test course content extraction
echo "<h3>Course Content Extraction Test</h3>";
try {
    $content = $quiz->getCourseContent(14);
    echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow: auto;'>";
    echo htmlspecialchars($content);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}
?>