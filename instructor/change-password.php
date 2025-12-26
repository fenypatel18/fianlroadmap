<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-center text-gray-900">Set Your New Password</h2>
        <p class="text-center text-gray-600">As a new instructor, you must change your temporary password.</p>
        <form id="change-password-form" class="space-y-6">
            <div>
                <label for="new-password" class="text-sm font-medium text-gray-700">New Password</label>
                <input id="new-password" name="new-password" type="password" required
                    class="block w-full px-3 py-2 mt-1 text-gray-900 placeholder-gray-500 border border-gray-300 rounded-md shadow-sm appearance-none focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="confirm-password" class="text-sm font-medium text-gray-700">Confirm New Password</label>
                <input id="confirm-password" name="confirm-password" type="password" required
                    class="block w-full px-3 py-2 mt-1 text-gray-900 placeholder-gray-500 border border-gray-300 rounded-md shadow-sm appearance-none focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <button type="submit"
                    class="w-full px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Update Password & Continue
                </button>
            </div>
        </form>
        <div id="error-message" class="text-center text-red-500"></div>
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

        const changePasswordForm = document.getElementById('change-password-form');
        const errorMessage = document.getElementById('error-message');

        let currentUser = null;

        auth.onAuthStateChanged(async (user) => {
            if (user) {
                currentUser = user;
                const userDoc = await db.collection('users').doc(user.uid).get();
                if (!userDoc.exists || userDoc.data().role !== 'instructor' || userDoc.data().status !== 'active') {
                    window.location.href = 'login.php';
                } else if (!userDoc.data().firstLogin) {
                    // Already changed password, redirect to dashboard
                    window.location.href = 'dashboard.php';
                }
            } else {
                window.location.href = 'login.php';
            }
        });

        changePasswordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorMessage.textContent = '';
            const newPassword = e.target['new-password'].value;
            const confirmPassword = e.target['confirm-password'].value;

            if (newPassword !== confirmPassword) {
                errorMessage.textContent = 'Passwords do not match.';
                return;
            }
            if (newPassword.length < 6) {
                 errorMessage.textContent = 'Password should be at least 6 characters.';
                return;
            }

            try {
                await currentUser.updatePassword(newPassword);
                await db.collection('users').doc(currentUser.uid).update({ firstLogin: false });
                window.location.href = 'dashboard.php';
            } catch (error) {
                errorMessage.textContent = error.message;
            }
        });
    </script>
</body>
</html>
