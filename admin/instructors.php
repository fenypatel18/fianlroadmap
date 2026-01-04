<?php
require_once __DIR__ . '/../auth/middleware.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

// Check if it's fixed admin
$is_fixed_admin = isset($_SESSION['is_fixed_admin']) && $_SESSION['is_fixed_admin'] === true;
$admin_name = $_SESSION['name'] ?? 'Admin';

// Get stats for dashboard sidebar
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'instructor' AND status = 'active'");
    $stmt->execute();
    $total_instructors = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'");
    $stmt->execute();
    $total_students = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE status = 'approved'");
    $stmt->execute();
    $total_roadmaps = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roadmaps WHERE status = 'pending'");
    $stmt->execute();
    $pending_roadmaps = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $recent_feedback = $stmt->fetchColumn();
    
    // Get total revenue for stats
    try {
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE status = 'success'");
        $stmt->execute();
        $total_revenue = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $total_revenue = 0;
    }
    
} catch (PDOException $e) {
    $total_instructors = 0;
    $total_students = 0;
    $total_roadmaps = 0;
    $pending_roadmaps = 0;
    $recent_feedback = 0;
    $total_revenue = 0;
}

// Handle status updates
if (isset($_GET['action'], $_GET['id'])) {
    if ($_GET['action'] === 'enable' || $_GET['action'] === 'disable') {
        $status = $_GET['action'] === 'enable' ? 'active' : 'disabled';
        $id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

        try {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'instructor'");
            $stmt->execute([$status, $id]);
            header('Location: instructors.php');
            exit();
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    }
}

// Handle instructor creation
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_instructor'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name)) $errors[] = 'Name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (empty($password) || strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email is already in use.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status, first_login) VALUES (?, ?, ?, 'instructor', 'active', 1)");
                $stmt->execute([$name, $email, $hashedPassword]);
                header('Location: instructors.php');
                exit();
            }
        } catch (PDOException $e) {
             $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch instructors
$stmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.created_at, u.status, COUNT(r.id) as roadmap_count FROM users u LEFT JOIN roadmaps r ON u.id = r.instructor_id WHERE u.role = 'instructor' GROUP BY u.id");
$stmt->execute();
$instructors = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Instructors - Admin</title>
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
            padding-bottom: 1rem;
        }
        .sidebar-footer {
            flex-shrink: 0;
            border-top: 1px solid #e5e7eb;
            background: white;
        }
        .main-content-with-sidebar {
            margin-left: 16rem;
            min-height: 100vh;
            background-color: #f9fafb;
        }

        /* Active link styling */
        .active-link {
            background-color: #eef2ff;
            color: #4f46e5;
            font-weight: 600;
        }
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Fixed Sidebar -->
<aside class="fixed-sidebar">
    <!-- Sidebar Header -->
    <div class="px-6 py-5 border-b border-gray-200">
        <h1 class="text-xl font-bold text-indigo-600">SkillPath Builder</h1>
        <span class="text-xs text-gray-500">Admin Panel</span>
        <?php if ($is_fixed_admin): ?>
            <span class="inline-block mt-1 text-xs px-2 py-1 bg-red-100 text-red-800 rounded">Fixed Admin</span>
        <?php endif; ?>
    </div>
    
    <!-- Scrollable Navigation -->
    <div class="sidebar-content">
        <nav class="pt-4">
            <!-- Dashboard -->
            <a href="dashboard.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-tachometer-alt w-6 text-center"></i>
                <span class="ml-3">Dashboard</span>
            </a>
            
            <!-- Roadmaps -->
            <a href="roadmaps.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-road w-6 text-center"></i>
                <span class="ml-3">Roadmaps</span>
                <?php if ($pending_roadmaps > 0): ?>
                    <span class="ml-auto mr-2 bg-yellow-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $pending_roadmaps; ?> pending</span>
                <?php endif; ?>
            </a>
            
            <!-- Instructors (Active) -->
            <a href="instructors.php" class="flex items-center px-6 py-3 text-gray-700 active-link">
                <i class="fas fa-users-cog w-6 text-center"></i>
                <span class="ml-3">Instructors</span>
            </a>
            
            <!-- Students (New Option) -->
            <a href="students.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-user-graduate w-6 text-center"></i>
                <span class="ml-3">Students</span>
            </a>
            
            <!-- Feedback -->
            <a href="feedback.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fas fa-comments w-6 text-center"></i>
                <span class="ml-3">Feedback</span>
                <?php if ($recent_feedback > 0): ?>
                    <span class="ml-auto mr-2 bg-green-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $recent_feedback; ?> new</span>
                <?php endif; ?>
            </a>
            
            <!-- Certificates -->
            <a href="certificates.php" class="flex items-center px-6 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
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

<!-- Main Content Area -->
<div class="main-content-with-sidebar">
    <div class="p-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-4xl font-bold text-gray-800">Instructor Management</h1>
                <p class="text-gray-600 mt-2">Manage all instructors in the SkillPath Builder platform.</p>
            </div>
            <div class="text-sm text-gray-500">
                Last updated: <?php echo date('M j, Y g:i A'); ?>
            </div>
        </div>

        <!-- Dashboard Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Stat Card: Total Instructors -->
            <div class="stat-card bg-white p-6 rounded-xl shadow-sm border-l-4 border-indigo-500">
                <div class="flex items-center">
                    <div class="p-3 bg-indigo-100 rounded-lg">
                        <i class="fas fa-chalkboard-teacher text-indigo-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Instructors</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $total_instructors; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Active teaching staff</p>
                    </div>
                </div>
            </div>
            
            <!-- Stat Card: Total Students -->
            <div class="stat-card bg-white p-6 rounded-xl shadow-sm border-l-4 border-green-500">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-user-graduate text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Students</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $total_students; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Active learners</p>
                    </div>
                </div>
            </div>
            
            <!-- Stat Card: Total Roadmaps -->
            <div class="stat-card bg-white p-6 rounded-xl shadow-sm border-l-4 border-blue-500">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-road text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Roadmaps</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $total_roadmaps; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Approved courses</p>
                    </div>
                </div>
            </div>
            
            <!-- Stat Card: Total Revenue -->
            <div class="stat-card bg-white p-6 rounded-xl shadow-sm border-l-4 border-yellow-500">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-lg">
                        <i class="fas fa-wallet text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Revenue</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1">$<?php echo number_format($total_revenue, 2); ?></p>
                        <p class="text-xs text-gray-500 mt-1">From all payments</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-6 flex justify-between items-center">
            <div>
                <button onclick="document.getElementById('create-modal').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded flex items-center">
                    <i class="fas fa-plus mr-2"></i> Create Instructor
                </button>
            </div>
            <div class="text-sm text-gray-500">
                Showing <?php echo count($instructors); ?> instructor(s)
            </div>
        </div>

        <!-- Instructors Table -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roadmaps</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined Date</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($instructors)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-users text-3xl text-gray-300 mb-2"></i>
                                    <p class="text-lg">No instructors found</p>
                                    <p class="text-sm mt-1">Create your first instructor using the button above</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($instructors as $instructor): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-user text-indigo-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($instructor['name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($instructor['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-center">
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?= $instructor['roadmap_count'] ?> roadmaps
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M d, Y', strtotime($instructor['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?= $instructor['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <span class="w-2 h-2 mr-2 rounded-full <?= $instructor['status'] === 'active' ? 'bg-green-400' : 'bg-red-400' ?>"></span>
                                            <?= ucfirst($instructor['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if ($instructor['status'] === 'active'): ?>
                                            <a href="?action=disable&id=<?= $instructor['id'] ?>" class="text-red-600 hover:text-red-900 inline-flex items-center mr-4" onclick="return confirm('Are you sure you want to disable this instructor?')">
                                                <i class="fas fa-ban mr-1"></i> Disable
                                            </a>
                                        <?php else: ?>
                                            <a href="?action=enable&id=<?= $instructor['id'] ?>" class="text-green-600 hover:text-green-900 inline-flex items-center mr-4" onclick="return confirm('Are you sure you want to enable this instructor?')">
                                                <i class="fas fa-check mr-1"></i> Enable
                                            </a>
                                        <?php endif; ?>
                                        <a href="instructor_view.php?id=<?= $instructor['id'] ?>" class="text-indigo-600 hover:text-indigo-900 inline-flex items-center">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Additional Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-chalkboard-teacher text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium opacity-90">Total Instructors</h3>
                        <p class="text-3xl font-bold mt-1"><?php echo count($instructors); ?></p>
                        <p class="text-xs opacity-80 mt-1">Active teaching staff</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-road text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium opacity-90">Total Roadmaps</h3>
                        <p class="text-3xl font-bold mt-1">
                            <?php 
                                $total = 0;
                                foreach ($instructors as $inst) {
                                    $total += $inst['roadmap_count'];
                                }
                                echo $total;
                            ?>
                        </p>
                        <p class="text-xs opacity-80 mt-1">Created by instructors</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-yellow-500 to-orange-600 text-white p-6 rounded-xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 bg-white/20 rounded-lg">
                        <i class="fas fa-chart-line text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium opacity-90">Active Instructors</h3>
                        <p class="text-3xl font-bold mt-1">
                            <?php 
                                $active_count = 0;
                                foreach ($instructors as $inst) {
                                    if ($inst['status'] === 'active') {
                                        $active_count++;
                                    }
                                }
                                echo $active_count;
                            ?>
                        </p>
                        <p class="text-xs opacity-80 mt-1">Currently teaching</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Instructor Modal -->
<div id="create-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Create New Instructor</h3>
            <div class="mt-2 px-7 py-3">
                 <form action="instructors.php" method="POST">
                    <input type="hidden" name="create_instructor" value="1">
                    <div class="mb-4">
                        <label class="block text-left text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" type="text" name="name" placeholder="John Doe" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-left text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" type="email" name="email" placeholder="instructor@example.com" required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-left text-sm font-medium text-gray-700 mb-1">Temporary Password</label>
                        <input class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" type="password" name="password" placeholder="At least 8 characters" required>
                        <p class="text-xs text-gray-500 mt-1">Instructor will be prompted to change password on first login</p>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded flex items-center justify-center">
                        <i class="fas fa-user-plus mr-2"></i> Create Instructor
                    </button>
                </form>
                <?php if (!empty($errors)): ?>
                    <div class="mt-4 text-left text-sm text-red-600 bg-red-50 p-3 rounded">
                        <?php foreach ($errors as $error): ?>
                            <p class="flex items-center"><i class="fas fa-exclamation-circle mr-2"></i><?= $error ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="items-center px-4 py-3">
                <button onclick="document.getElementById('create-modal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 w-full">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <script>document.getElementById('create-modal').classList.remove('hidden');</script>
<?php endif; ?>

<script>
    // Close modal when clicking outside
    document.getElementById('create-modal').addEventListener('click', function(e) {
        if (e.target.id === 'create-modal') {
            document.getElementById('create-modal').classList.add('hidden');
        }
    });
</script>
</body>
</html>