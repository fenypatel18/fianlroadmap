<?php
// student/feedback.php

// --- 1. SETUP & SECURITY ---
session_start();
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
requireStudent();

$student_id = $_SESSION['user_id'];
$roadmap_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$error_message = '';
$success_message = '';
$is_eligible = false;
$has_submitted = false;

// --- 2. VALIDATION & ELIGIBILITY CHECK ---
if (!$roadmap_id) {
    header('Location: /student/dashboard.php');
    exit();
}

// Fetch roadmap details
$stmt = $pdo->prepare("SELECT title FROM roadmaps WHERE id = ?");
$stmt->execute([$roadmap_id]);
$roadmap = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$roadmap) { die("Roadmap not found."); }

// Check if feedback has already been submitted
$stmt = $pdo->prepare("SELECT id FROM feedback WHERE student_id = ? AND roadmap_id = ?");
$stmt->execute([$student_id, $roadmap_id]);
if ($stmt->fetch()) {
    $has_submitted = true;
    $error_message = "You have already submitted feedback for this roadmap.";
} else {
    // Check if the student has passed the quiz for this roadmap
    $stmt = $pdo->prepare("SELECT id FROM quiz_attempts WHERE student_id = ? AND roadmap_id = ? AND status = 'passed'");
    $stmt->execute([$student_id, $roadmap_id]);
    if ($stmt->fetch()) {
        $is_eligible = true;
    } else {
        $error_message = "You must pass the final quiz before you can leave feedback.";
    }
}

// --- 3. HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_eligible && !$has_submitted) {
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $comment = trim(filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING));

    if ($rating >= 1 && $rating <= 5) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO feedback (student_id, roadmap_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$student_id, $roadmap_id, $rating, $comment]);
            $success_message = "Thank you! Your feedback has been submitted successfully.";
            $has_submitted = true; // Prevent re-submission
        } catch (PDOException $e) {
            $error_message = "A database error occurred. Please try again later.";
        }
    } else {
        $error_message = "Please select a star rating between 1 and 5.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Feedback - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style> /* Simple CSS for star rating */ .star-rating input[type="radio"] { display: none; } .star-rating label { font-size: 2.5rem; color: #d1d5db; cursor: pointer; transition: color 0.2s; } .star-rating input[type="radio"]:checked ~ label, .star-rating label:hover, .star-rating label:hover ~ label { color: #f59e0b; } </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="container mx-auto p-4 max-w-2xl">
        <div class="bg-white rounded-lg shadow-xl p-8">
            <h1 class="text-3xl font-bold text-center text-gray-800 mb-2">Share Your Feedback</h1>
            <h2 class="text-xl font-semibold text-center text-indigo-600 mb-8"><?php echo htmlspecialchars($roadmap['title']); ?></h2>

            <?php if ($success_message): ?>
                <div class="text-center p-8 bg-green-50 border-l-4 border-green-500 rounded-r-lg">
                    <h3 class="text-2xl font-bold text-green-800">Feedback Submitted!</h3>
                    <p class="mt-2 text-green-700"><?php echo htmlspecialchars($success_message); ?></p>
                    <a href="/student/dashboard.php" class="mt-6 inline-block px-6 py-2 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700">Return to Dashboard</a>
                </div>
            <?php elseif (!$is_eligible || $has_submitted): ?>
                <div class="text-center p-8 bg-yellow-50 border-l-4 border-yellow-500 rounded-r-lg">
                    <h3 class="text-2xl font-bold text-yellow-800">Action Not Available</h3>
                    <p class="mt-2 text-yellow-700"><?php echo htmlspecialchars($error_message); ?></p>
                    <a href="/student/dashboard.php" class="mt-6 inline-block px-6 py-2 bg-yellow-600 text-white font-semibold rounded-lg hover:bg-yellow-700">Return to Dashboard</a>
                </div>
            <?php else: ?>
                <!-- Feedback Form -->
                <form action="feedback.php?id=<?php echo $roadmap_id; ?>" method="POST" class="space-y-6">
                    <div>
                        <label class="block text-lg font-semibold text-gray-700 mb-2 text-center">Your Overall Rating*</label>
                        <div class="star-rating flex justify-center flex-row-reverse">
                            <input type="radio" id="star5" name="rating" value="5" required/><label for="star5" title="5 stars">&#9733;</label>
                            <input type="radio" id="star4" name="rating" value="4" /><label for="star4" title="4 stars">&#9733;</label>
                            <input type="radio" id="star3" name="rating" value="3" /><label for="star3" title="3 stars">&#9733;</label>
                            <input type="radio" id="star2" name="rating" value="2" /><label for="star2" title="2 stars">&#9733;</label>
                            <input type="radio" id="star1" name="rating" value="1" /><label for="star1" title="1 star">&#9733;</label>
                        </div>
                    </div>

                    <div>
                        <label for="comment" class="block text-lg font-semibold text-gray-700">Your Comments (Optional)</label>
                        <textarea id="comment" name="comment" rows="5" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>
                    
                    <div class="text-center pt-4">
                        <button type="submit" class="w-full sm:w-auto px-10 py-3 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700">Submit Feedback</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
