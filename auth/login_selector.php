<?php
// auth/login_selector.php
session_start();

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(120deg, #f3e8ff, #eef2ff);
        }
    </style>
</head>
<body class="gradient-bg flex items-center justify-center min-h-screen font-sans">
    <div class="w-full max-w-sm p-8 space-y-6 bg-white rounded-xl shadow-md m-4">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-indigo-600">SkillPath Builder</h1>
            <h2 class="mt-2 text-3xl font-bold text-gray-900">Choose Your Role</h2>
            <p class="mt-2 text-sm text-gray-600">Please select how you'd like to sign in.</p>
        </div>
        <div class="space-y-4">
            <a href="/admin/login.php" class="group flex items-center justify-center w-full text-center py-3 px-4 border border-gray-300 rounded-lg hover:bg-red-50 transition-colors">
                <i class="fas fa-user-shield text-red-500 w-6"></i>
                <span class="ml-3 font-semibold text-gray-700 group-hover:text-red-600">Admin Login</span>
            </a>
            <a href="/instructor/login.php" class="group flex items-center justify-center w-full text-center py-3 px-4 border border-gray-300 rounded-lg hover:bg-blue-50 transition-colors">
                <i class="fas fa-chalkboard-teacher text-blue-500 w-6"></i>
                <span class="ml-3 font-semibold text-gray-700 group-hover:text-blue-600">Instructor Login</span>
            </a>
            <a href="/student/login.php" class="group flex items-center justify-center w-full text-center py-3 px-4 border border-gray-300 rounded-lg hover:bg-green-50 transition-colors">
                <i class="fas fa-user-graduate text-green-500 w-6"></i>
                <span class="ml-3 font-semibold text-gray-700 group-hover:text-green-600">Student Login</span>
            </a>
        </div>
        <div class="text-center text-sm text-gray-600">
            <p>
                Don't have an account?
                <a href="/student/register.php" class="font-semibold text-indigo-600 hover:underline">
                    Sign up here
                </a>
            </p>
             <p class="mt-4">
                <a href="/index.php" class="text-gray-500 hover:text-indigo-600 transition-colors"><i class="fas fa-arrow-left mr-1"></i> Back to Home</a>
            </p>
        </div>
    </div>
</body>
</html>
