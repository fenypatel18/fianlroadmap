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

// Handle roadmap status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['roadmap_id']) && isset($_POST['status'])) {
    $roadmap_id = (int)$_POST['roadmap_id'];
    $status = $_POST['status'];
    
    // Validate status
    if (in_array($status, ['approved', 'rejected'])) {
        $stmt = $conn->prepare("UPDATE roadmaps SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $roadmap_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'newStatus' => $status]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    }
    exit(); // Terminate script after AJAX request
}

// Search, filter and pagination
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Base query
$sql = "SELECT r.id, r.title, r.price, r.status, r.created_at, u.name as instructor_name
        FROM roadmaps r
        JOIN users u ON r.instructor_id = u.id";
$count_sql = "SELECT COUNT(*) FROM roadmaps r JOIN users u ON r.instructor_id = u.id";

// Build WHERE conditions
$conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $conditions[] = "(r.title LIKE ? OR u.name LIKE ?)";
    $search_term = "%" . $search . "%";
    $params[] = &$search_term;
    $params[] = &$search_term;
    $types .= 'ss';
}

if (!empty($status_filter)) {
    $conditions[] = "r.status = ?";
    $params[] = &$status_filter;
    $types .= 's';
}

if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
    $count_sql .= " WHERE " . implode(' AND ', $conditions);
}

$sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
$params[] = &$limit;
$params[] = &$offset;
$types .= 'ii';

// Prepare and execute the main query
$stmt = $conn->prepare($sql);
if (!empty($types)) {
    call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $params));
}
$stmt->execute();
$result = $stmt->get_result();

// Prepare and execute the count query
$count_stmt = $conn->prepare($count_sql);
$count_params = array_slice($params, 0, -2); // Remove limit and offset
$count_types = substr($types, 0, -2);
if (!empty($count_types)) {
    call_user_func_array([$count_stmt, 'bind_param'], array_merge([$count_types], $count_params));
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
    <title>Manage Roadmaps - OneRoadmap</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .fixed-sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 16rem; background-color: white; border-right: 1px solid #e5e7eb; z-index: 50; display: flex; flex-direction: column; }
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
                <a href="dashboard.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg"><i class="fas fa-tachometer-alt w-6 text-center"></i><span class="ml-3">Dashboard</span></a>
                <a href="roadmaps.php" class="flex items-center w-full px-4 py-3 text-white bg-indigo-600 rounded-lg"><i class="fas fa-road w-6 text-center"></i><span class="ml-3">Roadmaps</span></a>
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
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Manage Roadmaps</h1>
        
        <form method="get" class="mb-6 flex space-x-4">
            <div class="relative flex-grow">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by title or instructor..." class="w-full pl-10 pr-4 py-2 border rounded-lg">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
            <select name="status" class="border rounded-lg py-2 px-4">
                <option value="">All Statuses</option>
                <option value="pending" <?php if ($status_filter === 'pending') echo 'selected'; ?>>Pending</option>
                <option value="approved" <?php if ($status_filter === 'approved') echo 'selected'; ?>>Approved</option>
                <option value="rejected" <?php if ($status_filter === 'rejected') echo 'selected'; ?>>Rejected</option>
            </select>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Filter</button>
        </form>

        <div class="bg-white shadow-md rounded-lg overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Instructor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr id="roadmap-row-<?php echo $row['id']; ?>">
                                <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['title']); ?></div></td>
                                <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-700"><?php echo htmlspecialchars($row['instructor_name']); ?></div></td>
                                <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-700">₹<?php echo htmlspecialchars(number_format($row['price'], 2)); ?></div></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                        $status_classes = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                            'changed' => 'bg-blue-100 text-blue-800'
                                        ];
                                        $status_class = $status_classes[$row['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="status-badge px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($row['status'])); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="roadmap_view.php?id=<?php echo $row['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-4">View</a>
                                    <?php if ($row['status'] == 'pending' || $row['status'] == 'rejected'): ?>
                                        <button onclick="updateStatus(<?php echo $row['id']; ?>, 'approved')" class="text-green-600 hover:text-green-900 mr-4">Approve</button>
                                    <?php endif; ?>
                                    <?php if ($row['status'] == 'pending' || $row['status'] == 'approved'): ?>
                                        <button onclick="updateStatus(<?php echo $row['id']; ?>, 'rejected')" class="text-red-600 hover:text-red-900">Reject</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No roadmaps found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex justify-between items-center">
                <span class="text-sm text-gray-600">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="px-4 py-2 border rounded-md text-sm hover:bg-gray-100">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="px-4 py-2 border rounded-md text-sm hover:bg-gray-100">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
    function updateStatus(roadmapId, newStatus) {
        if (!confirm(`Are you sure you want to ${newStatus} this roadmap?`)) return;

        fetch('roadmaps.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `roadmap_id=${roadmapId}&status=${newStatus}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // For simplicity, we just reload the page to see the changes.
                // A more advanced implementation would update the row dynamically.
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    }
    </script>
</body>
</html>
