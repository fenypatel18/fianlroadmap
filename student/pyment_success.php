<?php
// student/enroll.php

// --- SETUP & SECURITY ---
session_start();
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
requireStudent();

$student_id = $_SESSION['user_id'];
$roadmap_id = null;
$roadmap = null;
$error_message = '';

// --- 1. VALIDATE INPUT & ROADMAP ---
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: explore_roadmaps.php');
    exit();
}
$roadmap_id = $_GET['id'];

// Fetch roadmap details, ensuring it's approved and valid
$stmt = $pdo->prepare("SELECT id, title, price FROM roadmaps WHERE id = ? AND status = 'approved'");
$stmt->execute([$roadmap_id]);
$roadmap = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$roadmap) {
    die("Error: This roadmap is not available for enrollment.");
}

// --- 2. CHECK FOR EXISTING ENROLLMENT ---
$stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND roadmap_id = ?");
$stmt->execute([$student_id, $roadmap_id]);
if ($stmt->fetch()) {
    // If already enrolled, redirect them to the roadmap view page.
    // A query parameter indicates they are already enrolled.
    header('Location: view_roadmap.php?id=' . $roadmap_id . '&status=enrolled');
    exit();
}

// --- 3. HANDLE MOCK PAYMENT & ENROLLMENT (POST REQUEST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure the post request corresponds to the roadmap ID in the URL
    if (isset($_POST['roadmap_id']) && $_POST['roadmap_id'] == $roadmap_id) {
        
        $pdo->beginTransaction();
        try {
            // Step 3a: Insert a record into the 'payments' table to simulate payment
            $payment_stmt = $pdo->prepare(
                "INSERT INTO payments (student_id, roadmap_id, amount, status, created_at) VALUES (?, ?, ?, 'success', NOW())"
            );
            $payment_stmt->execute([$student_id, $roadmap_id, $roadmap['price']]);

            // Step 3b: Insert a record into the 'enrollments' table
            $enrollment_stmt = $pdo->prepare(
                "INSERT INTO enrollments (student_id, roadmap_id, enrolled_at) VALUES (?, ?, NOW())"
            );
            $enrollment_stmt->execute([$student_id, $roadmap_id]);

            // If both inserts succeed, commit the transaction
            $pdo->commit();
            
            // Step 3c: Store details in session for the success page
            $_SESSION['payment_success_details'] = [
                'roadmap_id' => $roadmap_id,
                'roadmap_title' => $roadmap['title']
            ];

            // Step 3d: Redirect to the payment success page
            header('Location: payment_success.php');
            exit();

        } catch (PDOException $e) {
            // If any database error occurs, roll back the entire transaction
            $pdo->rollBack();
            $error_message = "Enrollment failed. Please try again. Error: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid request. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enroll in Roadmap - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-lg p-8 space-y-6 bg-white rounded-lg shadow-md m-4">
        <div class="text-center">
            <h2 class="text-3xl font-bold text-gray-900">Enrollment Confirmation</h2>
            <p class="mt-2 text-sm text-gray-600">You are about to enroll in the following roadmap.</p>
        </div>

        <?php if ($error_message): ?>
            <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="border rounded-lg p-6 space-y-4">
            <h3 class="text-2xl font-semibold text-gray-800"><?php echo htmlspecialchars($roadmap['title']); ?></h3>
            <p class="text-gray-600">To unlock all phases and gain full access to the course content, please complete the mock payment process.</p>
            <div class="flex justify-between items-center bg-gray-50 p-4 rounded-md">
                <span class="text-lg font-medium text-gray-700">Total Price:</span>
                <span class="text-3xl font-bold text-indigo-600">$<?php echo htmlspecialchars(number_format($roadmap['price'], 2)); ?></span>
            </div>
        </div>
        
        <!-- Form to trigger the POST request for enrollment -->
        <form method="POST" action="enroll.php?id=<?php echo $roadmap_id; ?>">
            <input type="hidden" name="roadmap_id" value="<?php echo $roadmap_id; ?>">
            <button type="submit" 
                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-lg font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Proceed to Mock Payment
            </button>
        </form>

        <p class="text-sm text-center text-gray-600">
            <a href="view_roadmap.php?id=<?php echo $roadmap_id; ?>" class="font-medium text-indigo-600 hover:text-indigo-500">
                Cancel and return to roadmap
            </a>
        </p>
    </div>
</body>
</html>
