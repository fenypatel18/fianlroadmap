<?php
// student/certificate.php

// --- 1. SETUP & SECURITY ---
session_start();
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
requireStudent();

$student_id = $_SESSION['user_id'];
$roadmap_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$error_message = '';
$is_eligible = false;
$certificate_data = null;

// --- 2. VALIDATION & ELIGIBILITY CHECK ---
if (!$roadmap_id) {
    header('Location: /student/dashboard.php');
    exit();
}

// Fetch basic roadmap info first
$stmt = $pdo->prepare("SELECT title FROM roadmaps WHERE id = ?");
$stmt->execute([$roadmap_id]);
$roadmap = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$roadmap) { die("Roadmap not found."); }

// Step 2a: Verify the student passed the quiz for this roadmap.
$quiz_stmt = $pdo->prepare("SELECT id FROM quiz_attempts WHERE student_id = ? AND roadmap_id = ? AND status = 'passed'");
$quiz_stmt->execute([$student_id, $roadmap_id]);
$passed_quiz = $quiz_stmt->fetch();

// Step 2b: Verify the student has submitted feedback for this roadmap.
$feedback_stmt = $pdo->prepare("SELECT id FROM feedback WHERE student_id = ? AND roadmap_id = ?");
$feedback_stmt->execute([$student_id, $roadmap_id]);
$has_feedback = $feedback_stmt->fetch();

if ($passed_quiz && $has_feedback) {
    $is_eligible = true;
} else {
    $error_message = "You are not eligible for a certificate. You must pass the final quiz and submit feedback first.";
}

// --- 3. CERTIFICATE ISSUANCE & FETCHING ---
if ($is_eligible) {
    // Check if a certificate has already been issued to prevent duplicates.
    $cert_stmt = $pdo->prepare("SELECT id, issued_at FROM certificates WHERE student_id = ? AND roadmap_id = ?");
    $cert_stmt->execute([$student_id, $roadmap_id]);
    $certificate = $cert_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$certificate) {
        // Step 3a: If no certificate exists, INSERT a new record. This is the "issuance" step.
        try {
            $insert_stmt = $pdo->prepare("INSERT INTO certificates (student_id, roadmap_id, issued_at) VALUES (?, ?, NOW())");
            $insert_stmt->execute([$student_id, $roadmap_id]);
            // Get the newly created certificate ID
            $certificate_id = $pdo->lastInsertId();
            // Re-fetch the certificate data to ensure consistency
            $cert_stmt->execute([$student_id, $roadmap_id]);
            $certificate = $cert_stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error_message = "Error issuing certificate. Please try again.";
            $is_eligible = false;
        }
    }

    // Step 3b: If eligible (and certificate exists or was just created), fetch all data for display.
    if ($certificate) {
        $data_stmt = $pdo->prepare("
            SELECT 
                s.name AS student_name, 
                r.title AS roadmap_title, 
                i.name AS instructor_name,
                c.issued_at
            FROM certificates c
            JOIN users s ON c.student_id = s.id
            JOIN roadmaps r ON c.roadmap_id = r.id
            JOIN users i ON r.instructor_id = i.id
            WHERE c.id = ?
        ");
        $data_stmt->execute([$certificate['id']]);
        $certificate_data = $data_stmt->fetch(PDO::FETCH_ASSOC);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tangerine:wght@700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        .font-tangerine { font-family: 'Tangerine', cursive; }
        .font-playfair { font-family: 'Playfair Display', serif; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none; }
        }
    </style>
</head>
<body class="bg-gray-200">
    <div class="container mx-auto p-4 sm:p-10">
        <?php if ($certificate_data): ?>
            <!-- Certificate HTML -->
            <div id="certificate" class="bg-white max-w-5xl mx-auto p-8 sm:p-12 border-8 border-blue-900 shadow-2xl">
                <div class="text-center border-b-2 border-gray-300 pb-8">
                    <h1 class="font-playfair text-4xl sm:text-5xl font-bold text-blue-900">Certificate of Completion</h1>
                    <p class="text-lg text-gray-600 mt-2">SkillPath Builder proudly presents this certificate to</p>
                </div>
                <div class="text-center py-16">
                    <p class="font-tangerine text-7xl sm:text-8xl text-blue-800"><?php echo htmlspecialchars($certificate_data['student_name']); ?></p>
                </div>
                <div class="text-center">
                    <p class="text-lg text-gray-600">for successfully completing the roadmap</p>
                    <h2 class="font-playfair text-3xl sm:text-4xl font-bold text-blue-900 mt-2">"<?php echo htmlspecialchars($certificate_data['roadmap_title']); ?>"</h2>
                    <div class="flex justify-around items-center mt-12 pt-8 border-t-2 border-gray-300">
                        <div class="text-center">
                            <p class="font-semibold text-lg"><?php echo htmlspecialchars($certificate_data['instructor_name']); ?></p>
                            <p class="border-t border-gray-400 mt-1 pt-1 text-sm text-gray-500">Instructor</p>
                        </div>
                        <div class="text-center">
                            <p class="font-semibold text-lg"><?php echo date('F j, Y', strtotime($certificate_data['issued_at'])); ?></p>
                            <p class="border-t border-gray-400 mt-1 pt-1 text-sm text-gray-500">Date Issued</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Action Buttons -->
            <div class="no-print text-center mt-8">
                <button onclick="window.print()" class="px-8 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700">
                    Print / Download Certificate
                </button>
                <a href="/student/dashboard.php" class="ml-4 px-8 py-3 bg-gray-600 text-white font-bold rounded-lg hover:bg-gray-700">
                    Back to Dashboard
                </a>
            </div>
        <?php else: ?>
            <!-- Error/Ineligible State -->
            <div class="max-w-2xl mx-auto text-center p-10 bg-white shadow-lg rounded-lg">
                <h2 class="text-2xl font-bold text-red-700">Certificate Not Available</h2>
                <p class="mt-4 text-gray-600"><?php echo htmlspecialchars($error_message); ?></p>
                <a href="/student/dashboard.php" class="mt-6 inline-block px-6 py-2 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700">
                    Return to Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
