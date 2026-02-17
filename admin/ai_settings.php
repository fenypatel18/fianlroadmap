<?php
session_start();
require_once __DIR__ . '/../auth/middleware.php';
requireAdmin();

require_once __DIR__ . '/../config/openai_quiz.php';

$ai_quiz = new AIQuizManager($pdo);
$message = '';
$test_result = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_key'])) {
        // Test API key
        $test_key = trim($_POST['test_key']);
        $result = $ai_quiz->testApiKey($test_key);
        $test_result = $result['success'] 
            ? '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">✓ ' . $result['message'] . '</div>'
            : '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">✗ ' . $result['message'] . '</div>';
            
    } elseif (isset($_POST['save_key'])) {
        // Save API key
        $api_key = trim($_POST['api_key']);
        if (!empty($api_key)) {
            try {
                $ai_quiz->setApiKey($api_key);
                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">✓ API key saved successfully!</div>';
            } catch (Exception $e) {
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">✗ Error: ' . $e->getMessage() . '</div>';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Quiz Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include __DIR__ . '/admin_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-900 mb-6">AI Quiz Settings</h1>
            
            <?php echo $message . $test_result; ?>
            
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">OpenAI API Configuration</h2>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            OpenAI API Key
                        </label>
                        <input type="password" 
                               name="api_key" 
                               placeholder="sk-..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <p class="text-gray-500 text-xs mt-1">
                            Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank" class="text-blue-600">OpenAI Platform</a>
                        </p>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <button type="submit" 
                                name="save_key"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Save API Key
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Test API Key</h2>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <input type="text" 
                               name="test_key" 
                               placeholder="Enter API key to test"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        Test API Connection
                    </button>
                </form>
            </div>
            
            <div class="mt-6 text-sm text-gray-600">
                <h3 class="font-semibold mb-2">How AI Quiz Works:</h3>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Questions are generated based on roadmap content</li>
                    <li>Questions are stored in a JSON file (no database changes)</li>
                    <li>Auto-grading with detailed explanations</li>
                    <li>Fallback questions if AI fails</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>