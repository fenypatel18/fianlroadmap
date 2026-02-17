<?php
// student/check_quiz_status.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$student_id = $_SESSION['user_id'];
$roadmap_id = filter_input(INPUT_GET, 'roadmap_id', FILTER_VALIDATE_INT);

if (!$roadmap_id) {
    echo json_encode(['error' => 'Roadmap ID required']);
    exit();
}

// Check quiz status
$stmt = $pdo->prepare("
    SELECT 
        EXISTS(SELECT 1 FROM quiz_attempts WHERE student_id = ? AND roadmap_id = ? AND passed = 1) as has_passed,
        COUNT(*) as attempt_count,
        (SELECT certificate_id FROM quiz_attempts WHERE student_id = ? AND roadmap_id = ? AND passed = 1 ORDER BY attempt_date DESC LIMIT 1) as certificate_id
    FROM quiz_attempts 
    WHERE student_id = ? AND roadmap_id = ?
");
$stmt->execute([$student_id, $roadmap_id, $student_id, $roadmap_id, $student_id, $roadmap_id]);
$status = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'has_passed' => (bool)$status['has_passed'],
    'attempt_count' => (int)$status['attempt_count'],
    'has_certificate' => !empty($status['certificate_id'])
]);
?>
