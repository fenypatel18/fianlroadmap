<?php
// student/register.php

// Start the session to manage user state and flash messages.
session_start();

// If a user is already logged in, redirect them to their respective dashboard.
if (isset($_SESSION['user_id'])) {
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
    <title>Student Registration - SkillPath Builder</title>
    <!-- Include Tailwind CSS for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md m-4">
        <div class="text-center">
            <h2 class="text-3xl font-bold text-gray-900">Create Your Student Account</h2>
            <p class="mt-2 text-sm text-gray-600">Join SkillPath Builder to start your learning journey.</p>
        </div>

        <!-- This container will display success or error messages from the server -->
        <div id="message-container" role="alert"></div>

        <!-- The registration form. Submission is handled by JavaScript. -->
        <form id="register-form" method="POST" class="space-y-6">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                <input id="name" name="name" type="text" required 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                <input id="email" name="email" type="email" autocomplete="email" required 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input id="password" name="password" type="password" required minlength="8" 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <button type="submit" 
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Create Account
                </button>
            </div>
        </form>

        <p class="text-sm text-center text-gray-600">
            Already have an account? 
            <!-- Link to the student login page -->
            <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                Log in here
            </a>
        </p>
    </div>

    <script>
        // Attach an event listener to the form for when it is submitted.
        document.getElementById('register-form').addEventListener('submit', async function (event) {
            // Prevent the default browser form submission behavior.
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const messageContainer = document.getElementById('message-container');

            // Clear any previous messages and hide the container.
            messageContainer.innerHTML = '';
            messageContainer.className = '';

            try {
                // Send form data to the registration backend script using the Fetch API.
                const response = await fetch('../auth/register_student.php', {
                    method: 'POST',
                    body: formData
                });

                // Parse the JSON response from the server.
                const result = await response.json();

                // Check if the request was successful and the status is 'success'.
                if (response.ok && result.status === 'success') {
                    // Display a success message.
                    messageContainer.className = 'p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg';
                    messageContainer.textContent = 'Registration successful! Redirecting to login...';
                    
                    // Redirect the user to the student login page after a 2-second delay.
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);

                } else {
                    // If there was an error, display it to the user.
                    messageContainer.className = 'p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg';
                    messageContainer.textContent = result.message || 'An unknown error occurred.';
                }
            } catch (error) {
                // Catch network errors or issues with the fetch call itself.
                messageContainer.className = 'p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg';
                messageContainer.textContent = 'A network error occurred. Please try again.';
            }
        });
    </script>

</body>
</html>
