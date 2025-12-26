<?php
// student/login.php

// Start the session to manage user state.
session_start();

// If a user is already logged in, redirect them to the appropriate dashboard.
if (isset($_SESSION['user_id'])) {
    // Default to student dashboard if role is not set for some reason
    $role = $_SESSION['role'] ?? 'student'; 
    $dashboard_path = '/' . $role . '/dashboard.php';
    header('Location: ' . $dashboard_path);
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - SkillPath Builder</title>
    <!-- Include Tailwind CSS for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md m-4">
        <div class="text-center">
            <h2 class="text-3xl font-bold text-gray-900">Welcome Back, Student!</h2>
            <p class="mt-2 text-sm text-gray-600">Log in to continue your learning journey.</p>
        </div>

        <!-- This container will display success or error messages from the server -->
        <div id="message-container" role="alert"></div>

        <!-- The login form. Submission is handled via JavaScript (AJAX). -->
        <form id="login-form" method="POST" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                <input id="email" name="email" type="email" autocomplete="email" required 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <button type="submit" 
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Log In
                </button>
            </div>
        </form>

        <p class="text-sm text-center text-gray-600">
            Don't have an account?
            <!-- Link to the student registration page -->
            <a href="register.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                Sign up here
            </a>
        </p>
    </div>

    <script>
        // Attach a submit event listener to the login form.
        document.getElementById('login-form').addEventListener('submit', async function (event) {
            // Prevent the browser's default form submission.
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            // This hidden input is crucial to specify that we are attempting a 'student' login.
            formData.append('role', 'student');
            const messageContainer = document.getElementById('message-container');

            // Clear previous messages.
            messageContainer.innerHTML = '';
            messageContainer.className = '';

            try {
                // Send the form data to the general login authentication script.
                const response = await fetch('../auth/login.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                // Check for a successful response from the server.
                if (response.ok && result.status === 'success') {
                    // Specifically check if the logged-in user has the 'student' role.
                    if (result.role === 'student') {
                        messageContainer.className = 'p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg';
                        messageContainer.textContent = 'Login successful! Redirecting to your dashboard...';
                        
                        // Redirect to the student dashboard after a short delay.
                        setTimeout(() => {
                            window.location.href = 'dashboard.php';
                        }, 1500);
                    } else {
                        // Handle cases where a non-student user (e.g., admin) tries to log in here.
                        messageContainer.className = 'p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg';
                        messageContainer.textContent = 'Access denied. This login is for students only.';
                    }
                } else {
                    // Display any error messages sent from the server (e.g., invalid credentials).
                    messageContainer.className = 'p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg';
                    messageContainer.textContent = result.message || 'An unknown error occurred.';
                }
            } catch (error) {
                // Handle network errors or problems with the fetch request itself.
                messageContainer.className = 'p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg';
                messageContainer.textContent = 'A network error occurred. Please try again.';
            }
        });
    </script>

</body>
</html>
