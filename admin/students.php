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

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id']) && isset($_POST['status'])) {
    $student_id = (int)$_POST['student_id'];
    $status = $_POST['status'];

    if ($status === 'active' || $status === 'disabled') {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'student'");
        $stmt->bind_param("si", $status, $student_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'newStatus' => $status]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
        exit();
    }
}

// Search and pagination
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Base query
$sql = "SELECT u.id, u.name, u.email, u.status, u.created_at, COUNT(e.id) AS enrolled_roadmaps
        FROM users u
        LEFT JOIN enrollments e ON u.id = e.student_id
        WHERE u.role = 'student'";
$count_sql = "SELECT COUNT(*) FROM users WHERE role = 'student'";

// Apply search filter
if (!empty($search)) {
    $search_term = "%" . $search . "%";
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $count_sql .= " AND (name LIKE ? OR email LIKE ?)";
}

$sql .= " GROUP BY u.id, u.name, u.email, u.status, u.created_at ORDER BY u.created_at DESC LIMIT ? OFFSET ?";

// Prepare and execute the main query
$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $stmt->bind_param("ssii", $search_term, $search_term, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$students = $result->fetch_all(MYSQLI_ASSOC);

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
    <title>Manage Students - OneRoadmap</title>
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
            z-index: 40;
            display: flex;
            flex-direction: column;
        }
        .sidebar-content { flex: 1; overflow-y: auto; }
        .main-content { margin-left: 16rem; }
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
            display: none; /* Initially hidden */
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
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
                <a href="instructors.php" class="flex items-center w-full px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-chalkboard-teacher w-6 text-center"></i><span class="ml-3">Instructors</span>
                </a>
                <a href="students.php" class="flex items-center w-full px-4 py-3 text-white bg-indigo-600 rounded-lg">
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
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Manage Students</h1>
        
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($students)): ?>
                        <?php foreach ($students as $student): ?>
                            <tr id="student-row-<?php echo $student['id']; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-700"><?php echo htmlspecialchars($student['email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                        $status_class = $student['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                    ?>
                                    <span class="status-badge px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($student['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick='showStudentDetails(<?php echo json_encode($student, JSON_HEX_APOS); ?>)' class="text-indigo-600 hover:text-indigo-900 mr-4">View</button>
                                    <button onclick="toggleStatus(<?php echo $student['id']; ?>, '<?php echo $student['status']; ?>')" class="status-toggle-btn text-gray-600 hover:text-gray-900">
                                        <?php echo $student['status'] === 'active' ? 'Disable' : 'Enable'; ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">No students found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

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

    <!-- Student Details Modal -->
    <div id="studentModal" class="modal-overlay">
        <div class="modal-content">
            <h2 class="text-2xl font-bold text-gray-800 mb-4" id="modalStudentName"></h2>
            <div class="space-y-3 text-gray-700">
                <p><strong>Email:</strong> <span id="modalStudentEmail"></span></p>
                <p><strong>Joined:</strong> <span id="modalStudentJoined"></span></p>
                <p><strong>Enrolled Roadmaps:</strong> <span id="modalStudentEnrolled"></span></p>
                <p><strong>Status:</strong> <span id="modalStudentStatus"></span></p>
            </div>
            <div class="mt-6 text-right">
                <button onclick="closeModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Close</button>
            </div>
        </div>
    </div>

    <script>
        const studentModal = document.getElementById('studentModal');
        
        function showStudentDetails(student) {
            document.getElementById('modalStudentName').textContent = student.name;
            document.getElementById('modalStudentEmail').textContent = student.email;
            const joinDate = new Date(student.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            document.getElementById('modalStudentJoined').textContent = joinDate;
            document.getElementById('modalStudentEnrolled').textContent = student.enrolled_roadmaps;
            document.getElementById('modalStudentStatus').innerHTML = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${student.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${student.status}</span>`;
            
            studentModal.style.display = 'flex';
        }

        function closeModal() {
            studentModal.style.display = 'none';
        }

        // Close modal if overlay is clicked
        window.onclick = function(event) {
            if (event.target == studentModal) {
                closeModal();
            }
        }
        
        function toggleStatus(studentId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'disabled' : 'active';
            const confirmation = confirm(`Are you sure you want to ${newStatus === 'active' ? 'enable' : 'disable'} this student?`);

            if (confirmation) {
                fetch('students.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `student_id=${studentId}&status=${newStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the UI dynamically
                        const row = document.getElementById(`student-row-${studentId}`);
                        const statusBadge = row.querySelector('.status-badge');
                        const toggleBtn = row.querySelector('.status-toggle-btn');
                        
                        statusBadge.textContent = data.newStatus;
                        statusBadge.className = `status-badge px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${data.newStatus === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`;
                        
                        toggleBtn.textContent = data.newStatus === 'active' ? 'Disable' : 'Enable';
                        // Update the onclick event as well
                        toggleBtn.setAttribute('onclick', `toggleStatus(${studentId}, '${data.newStatus}')`);

                    } else {
                        alert('Failed to update status: ' + data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }
    </script>
</body>
</html>
