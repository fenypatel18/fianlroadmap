<?php
session_start();
include '../config/db.php';
include '../config/authload.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['name'];

// Handle status change
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $instructor_id = (int)$_GET['id'];

    if ($action === 'disable') {
        $stmt = $conn->prepare("UPDATE users SET status = 'disabled' WHERE id = ? AND role = 'instructor'");
        $stmt->bind_param("i", $instructor_id);
        $stmt->execute();
    } elseif ($action === 'enable') {
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'instructor'");
        $stmt->bind_param("i", $instructor_id);
        $stmt->execute();
    }

    header("Location: instructors.php");
    exit();
}

// Search and pagination
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Base query
$sql = "SELECT id, name, email, status, created_at FROM users WHERE role = 'instructor'";
$count_sql = "SELECT COUNT(*) FROM users WHERE role = 'instructor'";

// Apply search filter
if (!empty($search)) {
    $search_term = "%" . $search . "%";
    $sql .= " AND (name LIKE ? OR email LIKE ?)";
    $count_sql .= " AND (name LIKE ? OR email LIKE ?)";
}

$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

// Prepare and execute the main query
$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $stmt->bind_param("ssii", $search_term, $search_term, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

// Prepare and execute the count query
$count_stmt = $conn->prepare($count_sql);
if (!empty($search)) {
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
    <title>Manage Instructors - OneRoadmap</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .fixed-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 16rem;
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
                <a href="dashboard.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-tachometer-alt w-6 text-center"></i><span class="ml-3">Dashboard</span>
                </a>
                <a href="roadmaps.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-road w-6 text-center"></i><span class="ml-3">Roadmaps</span>
                </a>
                <a href="instructors.php" class="flex items-center w-full px-4 py-3 text-white bg-indigo-600 rounded-lg">
                    <i class="fas fa-chalkboard-teacher w-6 text-center"></i><span class="ml-3">Instructors</span>
                </a>
                <a href="students.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-user-graduate w-6 text-center"></i><span class="ml-3">Students</span>
                </a>
                <a href="feedback.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-comment-dots w-6 text-center"></i><span class="ml-3">Feedback</span>
                </a>
                <a href="certificates.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-certificate w-6 text-center"></i><span class="ml-3">Certificates</span>
                </a>
            </nav>
        </div>
        <div class="sidebar-footer p-4">
            <div class="mb-3 px-4 py-2 text-sm text-gray-600 bg-gray-50 rounded">
                <p class="font-medium"><?php echo htmlspecialchars($admin_name); ?></p>
                <p class="text-xs text-gray-500">Administrator</p>
            </div>
            <a href="../auth/logout.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg hover:text-red-600">
                <i class="fas fa-sign-out-alt w-6 text-center"></i><span class="ml-3">Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content p-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Manage Instructors</h1>
            <a href="create_instructor.php" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                <i class="fas fa-plus mr-2"></i>Create Instructor
            </a>
        </div>
        
        <!-- Search Bar -->
        <form method="get" class="mb-6">
            <div class="relative">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or email..." class="w-full pl-10 pr-4 py-2 border rounded-lg">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
        </form>

        <div class="bg-white shadow-md rounded-lg overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined On</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-700"><?php echo htmlspecialchars($row['email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if ($row['status'] === 'active'): ?>
                                        <a href="?action=disable&id=<?php echo $row['id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to disable this instructor?')">Disable</a>
                                    <?php else: ?>
                                        <a href="?action=enable&id=<?php echo $row['id']; ?>" class="text-green-600 hover:text-green-900" onclick="return confirm('Are you sure you want to enable this instructor?')">Enable</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">No instructors found.</td>
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
