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

// Search and pagination
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Base query for certificates
$sql = "SELECT c.id, c.issue_date, c.certificate_url, u.name as student_name, r.title as roadmap_title
        FROM certificates c
        JOIN users u ON c.student_id = u.id
        JOIN roadmaps r ON c.roadmap_id = r.id";

$count_sql = "SELECT COUNT(c.id) 
              FROM certificates c
              JOIN users u ON c.student_id = u.id
              JOIN roadmaps r ON c.roadmap_id = r.id";

// Apply search filter if a search term is provided
if (!empty($search)) {
    $search_term = "%" . $search . "%";
    // Add WHERE clause for searching
    $sql .= " WHERE (u.name LIKE ? OR r.title LIKE ?)";
    $count_sql .= " WHERE (u.name LIKE ? OR r.title LIKE ?)";
}

// Add ordering, limit and offset
$sql .= " ORDER BY c.issue_date DESC LIMIT ? OFFSET ?";

// Prepare and execute the main query
$stmt = $conn->prepare($sql);

if (!empty($search)) {
    // Bind search term, limit, and offset
    $stmt->bind_param("ssii", $search_term, $search_term, $limit, $offset);
} else {
    // Bind only limit and offset
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

// Prepare and execute the count query
$count_stmt = $conn->prepare($count_sql);

if (!empty($search)) {
    // Bind search term for count
    $count_stmt->bind_param("ss", $search_term, $search_term);
}
$count_stmt->execute();
$total_results = $count_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_results / $limit);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Certificates - OneRoadmap</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* Fixed Sidebar Styles */
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
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
        }
        .main-content {
            margin-left: 16rem; /* Same as sidebar width */
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Fixed Sidebar -->
    <aside class="fixed-sidebar">
        <!-- Logo -->
        <div class="p-6">
            <a href="dashboard.php" class="text-2xl font-bold text-gray-800">OneRoadmap</a>
        </div>
        
        <!-- Navigation Links -->
        <div class="sidebar-content px-4">
            <nav class="flex flex-col space-y-2">
                <a href="dashboard.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-tachometer-alt w-6 text-center"></i>
                    <span class="ml-3">Dashboard</span>
                </a>
                <a href="roadmaps.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-road w-6 text-center"></i>
                    <span class="ml-3">Roadmaps</span>
                </a>
                <a href="instructors.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-chalkboard-teacher w-6 text-center"></i>
                    <span class="ml-3">Instructors</span>
                </a>
                <a href="students.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-user-graduate w-6 text-center"></i>
                    <span class="ml-3">Students</span>
                </a>
                <a href="feedback.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-comment-dots w-6 text-center"></i>
                    <span class="ml-3">Feedback</span>
                </a>
                <a href="certificates.php" class="flex items-center w-full px-4 py-3 text-white bg-indigo-600 rounded-lg">
                    <i class="fas fa-certificate w-6 text-center"></i>
                    <span class="ml-3">Certificates</span>
                </a>
            </nav>
        </div>
        
        <!-- Fixed Footer with Logout -->
        <div class="sidebar-footer p-4">
            <div class="mb-3 px-4 py-2 text-sm text-gray-600 bg-gray-50 rounded">
                <p class="font-medium"><?php echo htmlspecialchars($admin_name); ?></p>
                <p class="text-xs text-gray-500">Administrator</p>
            </div>
            <a href="../auth/logout.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg hover:text-red-600">
                <i class="fas fa-sign-out-alt w-6 text-center"></i>
                <span class="ml-3">Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content p-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Manage Certificates</h1>
        
        <!-- Search Bar -->
        <form method="get" class="mb-6">
            <div class="relative">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by student or roadmap title..." class="w-full pl-10 pr-4 py-2 border rounded-lg">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
        </form>

        <!-- Certificates Table -->
        <div class="bg-white shadow-md rounded-lg overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roadmap</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issue Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-700"><?php echo htmlspecialchars($row['roadmap_title']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($row['issue_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="<?php echo htmlspecialchars($row['certificate_url']); ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900">View Certificate</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">No certificates found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex justify-between items-center">
                <span class="text-sm text-gray-600">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="px-4 py-2 border rounded-md text-sm hover:bg-gray-100">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="px-4 py-2 border rounded-md text-sm hover:bg-gray-100">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>
