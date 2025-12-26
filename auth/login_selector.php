<?php
// auth/login_selector.php
session_start();

// If a user is already logged in, redirect them to their dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    header('Location: /' . $_SESSION['role'] . '/dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Login - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-8 space-y-8 bg-white rounded-lg shadow-md m-4">
        <div class="text-center">
            <h2 class="text-3xl font-bold text-gray-900">Choose Your Role</h2>
            <p class="mt-2 text-sm text-gray-600">Please select how you'd like to log in.</p>
        </div>
        <div class="space-y-4">
            <a href="/admin/login.php" class="block w-full text-center py-3 px-4 border border-transparent rounded-md shadow-sm text-lg font-medium text-white bg-red-600 hover:bg-red-700">
                Admin Login
            </a>
            <a href="/instructor/login.php" class="block w-full text-center py-3 px-4 border border-transparent rounded-md shadow-sm text-lg font-medium text-white bg-blue-600 hover:bg-blue-700">
                Instructor Login
            </a>
            <a href="/student/login.php" class="block w-full text-center py-3 px-4 border border-transparent rounded-md shadow-sm text-lg font-medium text-white bg-green-600 hover:bg-green-700">
                Student Login
            </a>
        </div>
        <p class="text-sm text-center text-gray-600">
            Don't have an account? 
            <a href="/student/register.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                Sign up as a student
            </a>
        </p>
    </div>
</body>
</html>
