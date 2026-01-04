<?php
// admin/update_student_status.php
require_once __DIR__ . '/../auth/middleware.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (isset($_GET['action'], $_GET['id'])) {
    if ($_GET['action'] === 'enable' || $_GET['action'] === 'disable') {
        $status = $_GET['action'] === 'enable' ? 'active' : 'disabled';
        $id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

        try {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'student'");
            $stmt->execute([$status, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Student status updated successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
}