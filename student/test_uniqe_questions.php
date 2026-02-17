<?php
// test_final_uniqueness.php
require_once 'config/db.php';
require_once 'config/openai_quiz.php';

echo "<h2>Final Unique Question Generation Test</h2>";

$quiz = new AIQuizManager($pdo);

// Run uniqueness test
$quiz->testUniqueness();

// Also test with actual quiz generation
echo "<h3>Actual Quiz Generation Test</h3>";

try {
    $quiz_data = $quiz->generateQuiz(14, 9, 'Data Analytics', 15);
    
    if (!empty($quiz_data['questions'])) {
        echo "<p>✅ Generated " . count($quiz_data['questions']) . " questions</p>";
        
        // Check uniqueness
        $question_texts = [];
        $duplicates = [];
        
        foreach ($quiz_data['questions'] as $i => $q) {
            $question_texts[] = $q['question'];
        }
        
        $unique_count = count(array_unique($question_texts));
        
        if ($unique_count == 15) {
            echo "<p style='color:green; font-weight:bold;'>🎉 PERFECT! All 15 questions are completely unique!</p>";
        } else {
            echo "<p style='color:red;'>Found " . (15 - $unique_count) . " duplicate questions</p>";
        }
        
        // Display questions
        echo "<h4>Generated Questions:</h4>";
        echo "<div style='max-height:400px; overflow-y:auto; border:1px solid #ccc; padding:10px;'>";
        foreach ($quiz_data['questions'] as $i => $q) {
            echo "<div style='margin-bottom:15px; padding:10px; border-bottom:1px solid #eee;'>";
            echo "<strong>Q" . ($i + 1) . ":</strong> " . htmlspecialchars($q['question']) . "<br>";
            echo "<strong>Correct Answer:</strong> " . strtoupper($q['correct_answer']) . ") " . 
                 htmlspecialchars($q['options'][$q['correct_answer']]) . "<br>";
            echo "</div>";
        }
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>