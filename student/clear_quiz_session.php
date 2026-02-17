<?php
// student/clear_quiz_session.php
session_start();

// Clear quiz-related session data
unset(
    $_SESSION['quiz_results'],
    $_SESSION['quiz_detailed_results'],
    $_SESSION['quiz_session_key'],
    $_SESSION['quiz_questions'],
    $_SESSION['quiz_roadmap_id'],
    $_SESSION['quiz_attempt_number'],
    $_SESSION['quiz_started_at']
);

echo json_encode(['success' => true]);
?>