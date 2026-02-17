<?php
session_start();
include '../config/db.php';
include '../config/authload.php';

// Ensure the user is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['name'];

// Function to fetch a single value from the database
function fetch_single_value($conn, $sql) {
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return reset($row); // Return the first value in the row
    }
    return 0; // Return 0 if no result
}

// Fetch stats
try {
    // Total roadmaps
    $total_roadmaps_sql = "SELECT COUNT(*) FROM roadmaps";
    $total_roadmaps = fetch_single_value($conn, $total_roadmaps_sql);

    // Pending roadmaps for approval
    $pending_roadmaps_sql = "SELECT COUNT(*) FROM roadmaps WHERE status = 'pending'";
    $pending_roadmaps = fetch_single_value($conn, $pending_roadmaps_sql);

    // Total certificates issued
    $total_certificates_sql = "SELECT COUNT(*) FROM certificates";
    $total_certificates = fetch_single_value($conn, $total_certificates_sql);

    // Recent feedback count (last 7 days)
    $recent_feedback_sql = "SELECT COUNT(*) FROM feedback WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $recent_feedback = fetch_single_value($conn, $recent_feedback_sql);

} catch (Exception $e) {
    // Handle potential database errors gracefully
    $error_message = "Error fetching dashboard data: " . $e->getMessage();
    $total_roadmaps = 0;
    $pending_roadmaps = 0;
    $total_certificates = 0;
    $recent_feedback = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - OneRoadmap</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .fixed-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 16rem; /* 256px */
            background-color: white;
            border-right: 1px solid #e5e7eb;
            z-index: 50;
            display: flex;
            flex-direction: column;
        }
        .sidebar-content { flex: 1; overflow-y: auto; }
        .main-content { margin-left: 16rem; }
    </style>
</head>
<body class="bg-gray-50">

    <aside class="fixed-sidebar">
        <div class="p-6">
            <a href="dashboard.php" class="text-2xl font-bold text-gray-800">OneRoadmap</a>
        </div>
        <div class="sidebar-content px-4">
            <nav class="flex flex-col space-y-2">
                <a href="dashboard.php" class="flex items-center w-full px-4 py-3 text-white bg-indigo-600 rounded-lg"><i class="fas fa-tachometer-alt w-6 text-center"></i><span class="ml-3">Dashboard</span></a>
                <a href="roadmaps.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg"><i class="fas fa-road w-6 text-center"></i><span class="ml-3">Roadmaps</span></a>
                <a href="instructors.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg"><i class="fas fa-chalkboard-teacher w-6 text-center"></i><span class="ml-3">Instructors</span></a>
                <a href="students.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg"><i class="fas fa-user-graduate w-6 text-center"></i><span class="ml-3">Students</span></a>
                <a href="feedback.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg"><i class="fas fa-comment-dots w-6 text-center"></i><span class="ml-3">Feedback</span></a>
                <a href="certificates.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg"><i class="fas fa-certificate w-6 text-center"></i><span class="ml-3">Certificates</span></a>
            </nav>
        </div>
        <div class="sidebar-footer p-4">
            <div class="mb-3 px-4 py-2 text-sm text-gray-600 bg-gray-50 rounded"><p class="font-medium"><?php echo htmlspecialchars($admin_name); ?></p><p class="text-xs text-gray-500">Administrator</p></div>
            <a href="../auth/logout.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg hover:text-red-600"><i class="fas fa-sign-out-alt w-6 text-center"></i><span class="ml-3">Logout</span></a>
        </div>
    </aside>

    <main class="main-content p-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Admin Dashboard</h1>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Pending Approvals</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $pending_roadmaps; ?></p>
                </div>
                <div class="bg-yellow-100 text-yellow-600 rounded-full p-3"><i class="fas fa-hourglass-half fa-lg"></i></div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Roadmaps</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $total_roadmaps; ?></p>
                </div>
                <div class="bg-purple-100 text-purple-600 rounded-full p-3"><i class="fas fa-road fa-lg"></i></div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Certificates Issued</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $total_certificates; ?></p>
                </div>
                <div class="bg-blue-100 text-blue-600 rounded-full p-3"><i class="fas fa-certificate fa-lg"></i></div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Recent Feedback (7d)</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $recent_feedback; ?></p>
                </div>
                <div class="bg-pink-100 text-pink-600 rounded-full p-3"><i class="fas fa-comment-dots fa-lg"></i></div>
            </div>
        </div>
        <div class="mt-12 bg-white p-8 rounded-lg shadow-md">
            <h2 class="text-xl font-bold text-gray-700 mb-4">System Overview</h2>
            <p class="text-gray-600">Welcome to the OneRoadmap administration panel. From here, you can manage all aspects of the platform. Use the sidebar to navigate between managing roadmaps, users, and viewing feedback.</p>
            <div class="mt-6 border-t pt-6">
                <h3 class="text-lg font-semibold text-gray-700">Quick Actions</h3>
                <div class="mt-4 flex space-x-4">
                    <a href="roadmaps.php" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Manage Roadmaps</a>
                    <a href="instructors.php" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">View Instructors</a>
                </div>
            </div>
        </div>
    </main>

</body>
</html>
