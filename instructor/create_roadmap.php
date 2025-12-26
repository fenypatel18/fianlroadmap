<?php
// instructor/create_roadmap.php
require_once __DIR__ . '/../auth/middleware.php';
requireInstructor(); // Ensures user is a logged-in instructor and first_login is 0

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Roadmap - SkillPath Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

<div class="flex h-screen">
    <!-- Sidebar -->
    <div class="w-64 bg-gray-800 text-white flex flex-col fixed h-full">
        <div class="px-8 py-6 border-b border-gray-700">
            <h2 class="text-2xl font-bold">Instructor Panel</h2>
        </div>
        <nav class="flex-1 px-4 py-4 space-y-2">
            <a href="dashboard.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-md"><span>Dashboard</span></a>
            <a href="create_roadmap.php" class="flex items-center px-4 py-2 text-white bg-gray-700 rounded-md"><span>Create Roadmap</span></a>
            <a href="#" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-md"><span>My Roadmaps</span></a>
            <a href="#" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-md"><span>Rejected / Changed Roadmaps</span></a>
            <a href="#" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-md"><span>Students</span></a>
            <a href="#" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-md"><span>Feedback</span></a>
        </nav>
        <div class="px-4 py-4 border-t border-gray-700">
            <a href="../auth/logout.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-red-700 rounded-md"><span>Logout</span></a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 ml-64 p-10 overflow-y-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Create a New Roadmap</h1>

        <form action="store_roadmap.php" method="POST" enctype="multipart/form-data" id="roadmap-form" class="space-y-8">
            
            <!-- Roadmap Details -->
            <div class="bg-white p-8 rounded-lg shadow-md">
                <h2 class="text-2xl font-semibold mb-6">Roadmap Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">Roadmap Title</label>
                        <input type="text" name="title" id="title" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                     <div>
                        <label for="duration" class="block text-sm font-medium text-gray-700">Estimated Duration (e.g., '8 Weeks')</label>
                        <input type="text" name="duration" id="duration" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" id="description" rows="4" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700">Price ($)</label>
                        <input type="number" name="price" id="price" min="0" step="0.01" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="mt-2 text-sm text-green-600 font-semibold">Note: The first 2 phases are always FREE. Remaining phases unlock after payment.</p>
                    </div>
                </div>
            </div>

            <!-- Dynamic Phases -->
            <div id="phases-container" class="space-y-6"></div>

            <button type="button" id="add-phase" class="mt-4 px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">+ Add Phase</button>
            
            <div class="flex justify-end pt-6 border-t">
                 <button type="submit" class="px-8 py-3 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700">Submit for Approval</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
    const phasesContainer = document.getElementById('phases-container');
    const addPhaseBtn = document.getElementById('add-phase');
    let phaseCounter = 0;

    function addPhase() {
        const phaseIndex = phaseCounter++;
        const phaseId = `phase-${phaseIndex}`;
        const phaseHTML = `
            <div id="${phaseId}" class="bg-white p-8 rounded-lg shadow-md border-l-4 border-indigo-500">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold">Phase ${phaseIndex + 1}</h3>
                    <button type="button" onclick="removeElement('${phaseId}')" class="px-3 py-1 bg-red-500 text-white rounded-md text-sm hover:bg-red-600">Remove Phase</button>
                </div>
                <input type="hidden" name="phases[${phaseIndex}][order]" value="${phaseIndex + 1}">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Phase Title</label>
                    <input type="text" name="phases[${phaseIndex}][title]" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <div id="videos-container-${phaseIndex}" class="mt-6 space-y-4"></div>
                <button type="button" onclick="addVideo(${phaseIndex})" class="mt-4 px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 text-sm">+ Add Video</button>
            </div>
        `;
        phasesContainer.insertAdjacentHTML('beforeend', phaseHTML);
    }

    window.addVideo = function(phaseIndex) {
        const videosContainer = document.getElementById(`videos-container-${phaseIndex}`);
        const videoIndex = videosContainer.children.length;
        const videoId = `video-${phaseIndex}-${videoIndex}`;
        const videoHTML = `
            <div id="${videoId}" class="p-4 border rounded-md bg-gray-50 flex items-center gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">Video Title</label>
                    <input type="text" name="phases[${phaseIndex}][videos][${videoIndex}][title]" required class="mt-1 block w-full px-3 py-2 border-gray-300 rounded-md text-sm">
                </div>
                 <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">Video File (MP4, etc.)</label>
                    <input type="file" name="phases[${phaseIndex}][videos][${videoIndex}][file]" required accept="video/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>
                <button type="button" onclick="removeElement('${videoId}')" class="mt-5 px-3 py-2 bg-red-500 text-white rounded-md text-xs hover:bg-red-600">Remove</button>
            </div>
        `;
        videosContainer.insertAdjacentHTML('beforeend', videoHTML);
    }

    window.removeElement = function(elementId) {
        document.getElementById(elementId).remove();
        // Note: This doesn't re-order phases, but backend should rely on the hidden order input.
    }

    addPhaseBtn.addEventListener('click', addPhase);

    // Add two initial phases
    addPhase();
    addPhase();
});
</script>

</body>
</html>
