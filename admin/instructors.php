
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Management - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <div class="flex md:flex-row-reverse flex-wrap">

        <!-- Main Content -->
        <div class="w-full md:w-4/5 bg-gray-100">
            <div class="container bg-white rounded-lg shadow-lg p-6 mx-4 mt-4">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold">Instructor Management</h1>
                    <a href="create_instructor.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                        Create Instructor
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-800 text-white">
                            <tr>
                                <th class="w-1/4 text-left py-3 px-4 uppercase font-semibold text-sm">Name</th>
                                <th class="w-1/4 text-left py-3 px-4 uppercase font-semibold text-sm">Email</th>
                                <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Roadmaps</th>
                                <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Joined</th>
                                <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Status</th>
                                <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700" id="instructors-table-body">
                            <!-- Rows will be inserted here by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="w-full md:w-1/5 bg-gray-900 md:bg-gray-800 px-2 text-center fixed bottom-0 md:pt-8 md:top-0 md:left-0 h-16 md:h-screen md:border-r-4 md:border-gray-600">
            <div class="md:relative mx-auto lg:float-right lg:px-6">
                <ul class="list-reset flex flex-row md:flex-col text-center md:text-left">
                    <li class="mr-3 flex-1">
                        <a href="dashboard.php" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-blue-600">
                            <i class="fas fa-chart-area pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Dashboard</span>
                        </a>
                    </li>
                    <li class="mr-3 flex-1">
                        <a href="roadmaps.php" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-pink-500">
                            <i class="fas fa-road pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Roadmaps</span>
                        </a>
                    </li>
                    <li class="mr-3 flex-1">
                        <a href="#" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-purple-500">
                            <i class="fas fa-users-cog pr-0 md:pr-3 text-purple-500"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Instructors</span>
                        </a>
                    </li>
                    <li class="mr-3 flex-1">
                        <a href="students.php" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-green-500">
                            <i class="fas fa-user-graduate pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Students</span>
                        </a>
                    </li>
                     <li class="mr-3 flex-1">
                        <a href="payments.php" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-red-500">
                            <i class="fas fa-wallet pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Payments</span>
                        </a>
                    </li>
                     <li class="mr-3 flex-1">
                        <a href="feedback.php" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-yellow-500">
                            <i class="fas fa-comment-dots pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Feedback</span>
                        </a>
                    </li>
                     <li class="mr-3 flex-1">
                        <a href="login.php" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-gray-500">
                            <i class="fas fa-sign-out-alt pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
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

        auth.onAuthStateChanged(user => {
            if (user) {
                db.collection('users').doc(user.uid).get().then(doc => {
                    if (doc.exists && doc.data().role === 'admin') {
                        loadInstructors();
                    } else {
                        window.location.href = 'login.php';
                    }
                });
            } else {
                window.location.href = 'login.php';
            }
        });

        function loadInstructors() {
            const tableBody = document.getElementById('instructors-table-body');
            db.collection('users').where('role', '==', 'instructor').onSnapshot(snapshot => {
                tableBody.innerHTML = '';
                snapshot.forEach(doc => {
                    const instructor = doc.data();
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="text-left py-3 px-4">${instructor.name}</td>
                        <td class="text-left py-3 px-4">${instructor.email}</td>
                        <td class="text-left py-3 px-4">0</td>
                        <td class="text-left py-3 px-4">${new Date(instructor.createdAt.seconds * 1000).toLocaleDateString()}</td>
                        <td class="text-left py-3 px-4">
                            <span class="relative inline-block px-3 py-1 font-semibold text-${instructor.status === 'active' ? 'green' : 'red'}-900 leading-tight">
                                <span aria-hidden class="absolute inset-0 bg-${instructor.status === 'active' ? 'green' : 'red'}-200 opacity-50 rounded-full"></span>
                                <span class="relative">${instructor.status}</span>
                            </span>
                        </td>
                        <td class="text-left py-3 px-4">
                            <button onclick="toggleStatus('${doc.id}', '${instructor.status}')" class="text-sm bg-${instructor.status === 'active' ? 'red' : 'green'}-500 hover:bg-${instructor.status === 'active' ? 'red' : 'green'}-700 text-white py-1 px-2 rounded focus:outline-none focus:shadow-outline">
                                ${instructor.status === 'active' ? 'Disable' : 'Enable'}
                            </button>
                        </td>
                    `;
                    tableBody.appendChild(tr);
                });
            });
        }
        
        function toggleStatus(uid, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'disabled' : 'active';
            db.collection('users').doc(uid).update({ status: newStatus });
        }
    </script>

</body>
</html>
