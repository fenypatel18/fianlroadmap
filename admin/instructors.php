<?php
require_once __DIR__ . '/../auth/middleware.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

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
            // Optional: Handle error, e.g., show an error message
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
</head>
<body class="bg-gray-100">

    <div class="flex">
        <!-- Sidebar -->
        <div class="w-1/5 bg-gray-800 text-white h-screen p-4 fixed">
             <h2 class="text-2xl font-bold mb-10">Admin Panel</h2>
            <ul>
                <li><a href="dashboard.php" class="block py-2 px-4 rounded hover:bg-gray-700">Dashboard</a></li>
                <li><a href="instructors.php" class="block py-2 px-4 rounded bg-gray-700">Instructors</a></li>
                 <!-- Add other links here -->
            </ul>
             <a href="../auth/logout.php" class="block py-2 px-4 rounded hover:bg-red-700 mt-auto">Logout</a>
        </div>

        <!-- Main Content -->
        <div class="w-4/5 ml-auto p-8">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Instructor Management</h1>
                 <button onclick="document.getElementById('create-modal').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">+ Create Instructor</button>
            </div>

             <!-- Instructors Table -->
            <div class="bg-white shadow-md rounded-lg overflow-x-auto">
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Name</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Roadmaps</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Joined Date</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($instructors)): ?>
                            <tr><td colspan="5" class="text-center p-4">No instructors found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($instructors as $instructor): ?>
                                <tr>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <p class="text-gray-900 whitespace-no-wrap"><?= htmlspecialchars($instructor['name']) ?></p>
                                        <p class="text-gray-600 whitespace-no-wrap text-xs"><?= htmlspecialchars($instructor['email']) ?></p>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center"><?= $instructor['roadmap_count'] ?></td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm"><?= date('M d, Y', strtotime($instructor['created_at'])) ?></td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                         <span class="relative inline-block px-3 py-1 font-semibold leading-tight <?= $instructor['status'] === 'active' ? 'text-green-900' : 'text-red-900' ?>">
                                            <span aria-hidden class="absolute inset-0 <?= $instructor['status'] === 'active' ? 'bg-green-200' : 'bg-red-200' ?> opacity-50 rounded-full"></span>
                                            <span class="relative"><?= ucfirst($instructor['status']) ?></span>
                                        </span>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <?php if ($instructor['status'] === 'active'): ?>
                                            <a href="?action=disable&id=<?= $instructor['id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to disable this instructor?')">Disable</a>
                                        <?php else: ?>
                                            <a href="?action=enable&id=<?= $instructor['id'] ?>" class="text-green-600 hover:text-green-900" onclick="return confirm('Are you sure you want to enable this instructor?')">Enable</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
    
    <!-- Create Instructor Modal -->
    <div id="create-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Create New Instructor</h3>
                <div class="mt-2 px-7 py-3">
                     <form action="instructors.php" method="POST">
                        <input type="hidden" name="create_instructor" value="1">
                        <input class="text-md text-gray-700 w-full mb-3 px-4 py-2 border rounded-md" type="text" name="name" placeholder="Full Name" required>
                        <input class="text-md text-gray-700 w-full mb-3 px-4 py-2 border rounded-md" type="email" name="email" placeholder="Email Address" required>
                        <input class="text-md text-gray-700 w-full mb-4 px-4 py-2 border rounded-md" type="password" name="password" placeholder="Temporary Password" required>
                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">Create</button>
                    </form>
                    <?php if (!empty($errors)): ?>
                        <div class="mt-4 text-left text-sm text-red-600">
                            <?php foreach ($errors as $error): ?><p><?= $error ?></p><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="items-center px-4 py-3">
                    <button onclick="document.getElementById('create-modal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <script>document.getElementById('create-modal').classList.remove('hidden');</script>
    <?php endif; ?>
</body>
</html>
