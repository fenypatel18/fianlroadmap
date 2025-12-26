<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Instructor - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-center text-gray-900">Create New Instructor</h2>
        <form id="create-instructor-form" class="space-y-6">
            <div>
                <label for="name" class="text-sm font-medium text-gray-700">Full Name</label>
                <input id="name" name="name" type="text" required
                    class="block w-full px-3 py-2 mt-1 text-gray-900 placeholder-gray-500 border border-gray-300 rounded-md shadow-sm appearance-none focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="email" class="text-sm font-medium text-gray-700">Email address</label>
                <input id="email" name="email" type="email" autocomplete="email" required
                    class="block w-full px-3 py-2 mt-1 text-gray-900 placeholder-gray-500 border border-gray-300 rounded-md shadow-sm appearance-none focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="password" class="text-sm font-medium text-gray-700">Temporary Password</label>
                <input id="password" name="password" type="password" required
                    class="block w-full px-3 py-2 mt-1 text-gray-900 placeholder-gray-500 border border-gray-300 rounded-md shadow-sm appearance-none focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <button type="submit"
                    class="w-full px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Create Account
                </button>
            </div>
        </form>
        <div id="error-message" class="text-center text-red-500"></div>
        <div class="text-center">
             <a href="instructors.php" class="font-medium text-indigo-600 hover:text-indigo-500">Back to Instructors</a>
        </div>
    </div>

    <!-- Firebase SDK -->
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-auth-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.6.1/firebase-firestore-compat.js"></script>
    <script>
        const firebaseConfig = {
            apiKey: "YOUR_API_KEY",
            authDomain: "YOUR_AUTH_DOMAIN",
            projectId: "YOUR_PROJECT_ID",
            storageBucket: "YOUR_STORAGE_BUCKET",
            messagingSenderId: "YOUR_MESSAGING_SENDER_ID",
            appId: "YOUR_APP_ID"
        };
        firebase.initializeApp(firebaseConfig);
        const auth = firebase.auth();
        const db = firebase.firestore();

        // This part is tricky because Firebase Auth automatically signs in the new user.
        // A real admin backend would use the Firebase Admin SDK to prevent this.
        // For this simulation, we'll create the user and then sign out the current session.

        const createInstructorForm = document.getElementById('create-instructor-form');
        const errorMessage = document.getElementById('error-message');

        createInstructorForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = e.target.name.value;
            const email = e.target.email.value;
            const password = e.target.password.value;
            
            const adminUser = auth.currentUser; // Keep track of the currently logged-in admin

            try {
                // Create the new instructor user
                const userCredential = await auth.createUserWithEmailAndPassword(email, password);
                const newUser = userCredential.user;

                // Add instructor data to Firestore
                await db.collection('users').doc(newUser.uid).set({
                    name: name,
                    email: email,
                    role: 'instructor',
                    status: 'active',
                    firstLogin: true,
                    createdAt: firebase.firestore.FieldValue.serverTimestamp()
                });

                // Important: Sign out the newly created user session
                await auth.signOut();

                // Re-authenticate the admin user silently if possible or redirect to login
                // This part is complex without a backend. For now, we'll just redirect.
                // In a real app, you would handle session restoration more gracefully.
                alert('Instructor created successfully!');
                window.location.href = 'instructors.php';

            } catch (error) {
                errorMessage.textContent = error.message;
                // If something went wrong, ensure the admin is still logged in.
                // This is a simplified error handling for the simulation.
                if(adminUser) {
                    auth.updateCurrentUser(adminUser); // This is not a standard API, conceptual
                } else {
                     window.location.href = 'login.php'; // force re-login
                }
            }
        });
    </script>
</body>
</html>