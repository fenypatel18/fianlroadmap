<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roadmap Management - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <div class="flex md:flex-row-reverse flex-wrap">

        <!-- Main Content -->
        <div class="w-full md:w-4/5 bg-gray-100">
            <div class="container bg-white rounded-lg shadow-lg p-6 mx-4 mt-4">
                <h1 class="text-2xl font-bold mb-6">Roadmap Management</h1>

                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-800 text-white">
                            <tr>
                                <th class="w-1/3 text-left py-3 px-4 uppercase font-semibold text-sm">Title</th>
                                <th class="w-1/4 text-left py-3 px-4 uppercase font-semibold text-sm">Instructor</th>
                                <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Price</th>
                                <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Status</th>
                                <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Created</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700" id="roadmaps-table-body">
                            <!-- JS will populate this -->
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
                        <a href="#" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-pink-500">
                            <i class="fas fa-road pr-0 md:pr-3 text-pink-500"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Roadmaps</span>
                        </a>
                    </li>
                    <li class="mr-3 flex-1">
                        <a href="instructors.php" class="block py-1 md:py-3 pl-1 align-middle text-white no-underline hover:text-white border-b-2 border-gray-800 hover:border-purple-500">
                            <i class="fas fa-users-cog pr-0 md:pr-3"></i><span class="pb-1 md:pb-0 text-xs md:text-base text-gray-400 md:text-gray-200 block md:inline-block">Instructors</span>
                        </a>
                    </li>
                   <!-- Other links -->
                </ul>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="roadmap-modal" class="modal fixed w-full h-full top-0 left-0 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-lg shadow-lg w-11/12 md:w-1/2 max-h-screen overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-start">
                    <h2 class="text-2xl font-bold mb-4" id="modal-title">Roadmap Details</h2>
                    <button id="close-modal" class="text-black text-2xl font-bold">&times;</button>
                </div>
                <div id="modal-content" class="space-y-4">
                    <!-- JS will populate this -->
                </div>
                <div id="modal-actions" class="mt-6 flex justify-end space-x-4">
                    <!-- JS will populate this -->
                </div>
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

        // Auth guard
        auth.onAuthStateChanged(user => {
            if (!user) {
                window.location.href = 'login.php';
            } else {
                 db.collection('users').doc(user.uid).get().then(doc => {
                    if (!doc.exists || doc.data().role !== 'admin') {
                         window.location.href = 'login.php';
                    } else {
                        loadRoadmaps();
                    }
                });
            }
        });

        const tableBody = document.getElementById('roadmaps-table-body');
        const modal = document.getElementById('roadmap-modal');
        const modalTitle = document.getElementById('modal-title');
        const modalContent = document.getElementById('modal-content');
        const modalActions = document.getElementById('modal-actions');
        const closeModal = document.getElementById('close-modal');

        closeModal.onclick = () => modal.classList.remove('active');

        async function loadRoadmaps() {
            db.collection('roadmaps').onSnapshot(async snapshot => {
                tableBody.innerHTML = '';
                for (const doc of snapshot.docs) {
                    const roadmap = doc.data();
                    const instructorDoc = await db.collection('users').doc(roadmap.instructorId).get();
                    const instructorName = instructorDoc.exists ? instructorDoc.data().name : 'N/A';

                    const tr = document.createElement('tr');
                    tr.className = 'cursor-pointer hover:bg-gray-200';
                    tr.innerHTML = `
                        <td class="text-left py-3 px-4">${roadmap.title}</td>
                        <td class="text-left py-3 px-4">${instructorName}</td>
                        <td class="text-left py-3 px-4">$${roadmap.price}</td>
                        <td class="text-left py-3 px-4">${roadmap.status}</td>
                        <td class="text-left py-3 px-4">${new Date(roadmap.createdAt.seconds * 1000).toLocaleDateString()}</td>
                    `;
                    tr.onclick = () => openModal(doc.id);
                    tableBody.appendChild(tr);
                }
            });
        }
        
        async function openModal(roadmapId) {
            const roadmapDoc = await db.collection('roadmaps').doc(roadmapId).get();
            const roadmap = roadmapDoc.data();
            const instructorDoc = await db.collection('users').doc(roadmap.instructorId).get();
            const instructorName = instructorDoc.data().name;
            const phasesSnapshot = await db.collection('roadmaps').doc(roadmapId).collection('phases').get();
            
            modalTitle.textContent = roadmap.title;
            
            let phasesHtml = '<div class="space-y-2">';
            phasesSnapshot.docs.forEach((phaseDoc, index) => {
                const phase = phaseDoc.data();
                const isFree = index < 2 ? '<span class="text-xs font-semibold text-green-600">(Free)</span>' : '';
                phasesHtml += `<div class="p-2 border rounded"><strong>Phase ${index + 1}:</strong> ${phase.title} ${isFree} - ${phase.videoCount || 0} videos</div>`;
            });
            phasesHtml += '</div>';

            modalContent.innerHTML = `
                <p><strong>Instructor:</strong> ${instructorName}</p>
                <p><strong>Description:</strong> ${roadmap.description}</p>
                <p><strong>Price:</strong> $${roadmap.price}</p>
                <p><strong>Phases:</strong></p>
                ${phasesHtml}
            `;

            modalActions.innerHTML = `
                <button onclick="updateStatus('${roadmapId}', 'approved')" class="bg-green-500 text-white px-4 py-2 rounded">Approve</button>
                <button onclick="updateStatus('${roadmapId}', 'rejected')" class="bg-red-500 text-white px-4 py-2 rounded">Reject</button>
                <button onclick="updateStatus('${roadmapId}', 'changed')" class="bg-yellow-500 text-white px-4 py-2 rounded">Request Edit</button>
            `;

            modal.classList.add('active');
        }

        function updateStatus(roadmapId, status) {
            db.collection('roadmaps').doc(roadmapId).update({ status: status })
            .then(() => {
                modal.classList.remove('active');
                alert(\`Roadmap has been \${status}.\`);
            })
            .catch(error => console.error("Error updating status: ", error));
        }

    </script>
</body>
</html>