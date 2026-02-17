<?php
// student/quiz.php
session_start();
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/openai_quiz.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: /fianlroadmap/auth/login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$roadmap_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$roadmap_id) {
    header('Location: dashboard.php');
    exit();
}

// Get roadmap info
$stmt = $pdo->prepare("SELECT id, title FROM roadmaps WHERE id = ? AND status = 'approved'");
$stmt->execute([$roadmap_id]);
$roadmap = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$roadmap) {
    die("Roadmap not found");
}

// Check enrollment
$stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND roadmap_id = ?");
$stmt->execute([$student_id, $roadmap_id]);
if (!$stmt->fetch()) {
    $_SESSION['error'] = "You are not enrolled in this course";
    header("Location: roadmap_player.php?id=" . $roadmap_id);
    exit();
}

// Check video completion
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_videos
    FROM roadmap_videos v
    JOIN roadmap_phases p ON v.phase_id = p.id
    WHERE p.roadmap_id = ?
");
$stmt->execute([$roadmap_id]);
$total_videos = $stmt->fetch()['total_videos'];

if ($total_videos > 0) {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT pr.video_id) as completed_videos
        FROM progress pr
        JOIN roadmap_videos v ON pr.video_id = v.id
        JOIN roadmap_phases p ON v.phase_id = p.id
        WHERE pr.student_id = ? 
        AND p.roadmap_id = ? 
        AND pr.completed = 1
    ");
    $stmt->execute([$student_id, $roadmap_id]);
    $completed_videos = $stmt->fetch()['completed_videos'];
    
    if ($completed_videos < $total_videos) {
        $_SESSION['error'] = "Complete all videos first ($completed_videos/$total_videos)";
        header("Location: roadmap_player.php?id=" . $roadmap_id);
        exit();
    }
}

// Check quiz attempts
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as attempt_count,
        EXISTS(SELECT 1 FROM quiz_attempts WHERE student_id = ? AND roadmap_id = ? AND passed = 1) as has_passed
    FROM quiz_attempts 
    WHERE student_id = ? AND roadmap_id = ?
");
$stmt->execute([$student_id, $roadmap_id, $student_id, $roadmap_id]);
$attempt_data = $stmt->fetch();

$attempt_count = (int)$attempt_data['attempt_count'];
$has_passed = (bool)$attempt_data['has_passed'];
$next_attempt = $attempt_count + 1;
$max_attempts = 3;

if ($has_passed) {
    $_SESSION['error'] = "You already passed this quiz";
    header("Location: roadmap_player.php?id=" . $roadmap_id);
    exit();
}

if ($attempt_count >= $max_attempts) {
    $_SESSION['error'] = "No attempts remaining";
    header("Location: roadmap_player.php?id=" . $roadmap_id);
    exit();
}

// Generate quiz questions
$ai_quiz = new AIQuizManager($pdo);
// In student/quiz.php, after generating quiz data:
$quiz_data = $ai_quiz->generateQuiz($roadmap_id, $student_id, $roadmap['title'], 15);

if (!$quiz_data || empty($quiz_data['questions'])) {
    $_SESSION['error'] = "Failed to generate quiz. Please try again.";
    header("Location: roadmap_player.php?id=" . $roadmap_id);
    exit();
}

// Check for duplicate questions
$questions = $quiz_data['questions'];
$question_texts = array_map(function($q) { 
    return is_array($q) ? $q['question'] : ''; 
}, $questions);
$unique_questions = array_unique($question_texts);

// If we have too many duplicates, regenerate once
if (count($unique_questions) < count($questions) * 0.7) { // If less than 70% unique
    error_log("Regenerating quiz due to duplicate questions for roadmap $roadmap_id");
    $quiz_data = $ai_quiz->generateQuiz($roadmap_id, $student_id, $roadmap['title'], 15);
    $questions = $quiz_data['questions'];
}
// Store session data
$_SESSION['quiz_session_id'] = $quiz_data['session_id'];
$_SESSION['quiz_roadmap_id'] = $roadmap_id;
$_SESSION['quiz_attempt'] = $next_attempt;

$questions = $quiz_data['questions'];
$total_questions = count($questions);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz: <?php echo htmlspecialchars($roadmap['title']); ?> | SkillPath</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        .question-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .option-label {
            display: block;
            cursor: pointer;
        }
        
        .option-label input[type="radio"] {
            display: none;
        }
        
        .option-label input[type="radio"]:checked + .option-content {
            background: rgba(124, 58, 237, 0.2);
            border-color: rgba(124, 58, 237, 0.5);
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-950">
    
    <header class="sticky top-0 z-50 bg-gray-900/80 backdrop-blur-lg border-b border-gray-800">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="roadmap_player.php?id=<?php echo $roadmap_id; ?>" class="flex items-center text-gray-400 hover:text-white">
                        <i data-lucide="arrow-left" class="w-5 h-5 mr-2"></i>
                        Back
                    </a>
                    <h1 class="text-xl font-bold text-white">Quiz: <?php echo htmlspecialchars($roadmap['title']); ?></h1>
                </div>
                
                <div class="flex items-center space-x-6">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="clock" class="w-5 h-5 text-amber-500"></i>
                        <span class="text-lg font-semibold text-white" id="timer">30:00</span>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <i data-lucide="refresh-cw" class="w-5 h-5 text-blue-500"></i>
                        <span class="text-gray-300">
                            Attempt <span class="font-bold text-white"><?php echo $next_attempt; ?></span>/<?php echo $max_attempts; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <div class="h-2 bg-gray-800 rounded-full overflow-hidden">
                    <div id="progress-bar" class="h-full bg-gradient-to-r from-blue-600 to-purple-600" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </header>
    
    <main class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="mb-8 p-6 question-card rounded-2xl">
                <div class="flex items-start space-x-4">
                    <div class="p-3 bg-purple-500/10 rounded-xl">
                        <i data-lucide="info" class="w-8 h-8 text-purple-500"></i>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-white mb-2">Quiz Instructions</h2>
                        <ul class="space-y-2 text-gray-300">
                            <li class="flex items-center space-x-2">
                                <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>
                                <span><strong><?php echo $total_questions; ?> questions</strong> (AI-generated)</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>
                                <span>Need <strong>80%</strong> to pass</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>
                                <span>Time: <strong>30 minutes</strong></span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <i data-lucide="refresh-cw" class="w-4 h-4 text-blue-500"></i>
                                <span>Questions are <strong>unique each attempt</strong></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <form id="quiz-form" action="quiz_submit.php" method="POST" class="space-y-6">
                <input type="hidden" name="roadmap_id" value="<?php echo $roadmap_id; ?>">
                <input type="hidden" name="attempt_number" value="<?php echo $next_attempt; ?>">
                <input type="hidden" name="quiz_session_id" value="<?php echo $quiz_data['session_id']; ?>">
                
                <?php foreach ($questions as $index => $question): ?>
                <?php $q_num = $index + 1; ?>
                <div class="question-card rounded-2xl p-6" id="question-<?php echo $q_num; ?>" style="<?php echo $q_num > 1 ? 'display: none;' : ''; ?>">
                    <div class="flex items-start justify-between mb-6">
                        <div class="flex items-center space-x-3">
                            <span class="flex items-center justify-center w-10 h-10 bg-gradient-to-br from-purple-600 to-blue-600 rounded-lg font-bold text-white">
                                <?php echo $q_num; ?>
                            </span>
                            <h3 class="text-lg font-semibold text-white">Question <?php echo $q_num; ?></h3>
                        </div>
                        <span class="text-sm text-gray-400">Select one answer</span>
                    </div>
                    
                    <p class="text-xl text-white mb-6"><?php echo htmlspecialchars($question['question']); ?></p>
                    
                    <div class="space-y-3">
                        <?php foreach (['a', 'b', 'c', 'd'] as $letter): ?>
                        <?php if (isset($question['options'][$letter])): ?>
                        <label class="option-label">
                            <input type="radio" name="answers[<?php echo $q_num; ?>]" value="<?php echo $letter; ?>" data-question="<?php echo $q_num; ?>">
                            <div class="option-content p-4 border-2 border-gray-700 rounded-xl">
                                <div class="flex items-center space-x-4">
                                    <span class="flex items-center justify-center w-8 h-8 border-2 border-gray-600 rounded-full text-gray-300 font-medium">
                                        <?php echo strtoupper($letter); ?>
                                    </span>
                                    <span class="text-lg text-gray-200"><?php echo htmlspecialchars($question['options'][$letter]); ?></span>
                                </div>
                            </div>
                        </label>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="flex justify-between mt-8 pt-6 border-t border-gray-800">
                        <button type="button" onclick="prevQuestion()" class="px-6 py-3 border border-gray-700 text-gray-300 rounded-xl hover:bg-gray-800" <?php echo $q_num == 1 ? 'disabled' : ''; ?>>
                            Previous
                        </button>
                        
                        <?php if ($q_num < $total_questions): ?>
                        <button type="button" onclick="nextQuestion()" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl">
                            Next Question
                        </button>
                        <?php else: ?>
                        <button type="submit" class="px-8 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl">
                            Submit Quiz
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </form>
        </div>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
            
            let currentQuestion = 1;
            const totalQuestions = <?php echo $total_questions; ?>;
            let answers = {};
            let timeLeft = 30 * 60;
            
            // Timer
            const timerElement = document.getElementById('timer');
            function updateTimer() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                if (timeLeft <= 0) {
                    document.getElementById('quiz-form').submit();
                } else {
                    timeLeft--;
                }
            }
            setInterval(updateTimer, 1000);
            updateTimer();
            
            // Progress bar
            function updateProgress() {
                const progress = ((currentQuestion - 1) / totalQuestions) * 100;
                document.getElementById('progress-bar').style.width = `${progress}%`;
            }
            
            window.nextQuestion = function() {
                const questionInputs = document.querySelectorAll(`input[name="answers[${currentQuestion}]"]`);
                let answered = false;
                
                for (let input of questionInputs) {
                    if (input.checked) {
                        answered = true;
                        answers[currentQuestion] = input.value;
                        break;
                    }
                }
                
                if (!answered) {
                    alert('Please select an answer.');
                    return;
                }
                
                document.getElementById(`question-${currentQuestion}`).style.display = 'none';
                currentQuestion++;
                document.getElementById(`question-${currentQuestion}`).style.display = 'block';
                updateProgress();
            };
            
            window.prevQuestion = function() {
                if (currentQuestion > 1) {
                    document.getElementById(`question-${currentQuestion}`).style.display = 'none';
                    currentQuestion--;
                    document.getElementById(`question-${currentQuestion}`).style.display = 'block';
                    updateProgress();
                }
            };
            
            document.querySelectorAll('input[type="radio"]').forEach(input => {
                input.addEventListener('change', function() {
                    const questionNum = parseInt(this.getAttribute('data-question'));
                    answers[questionNum] = this.value;
                });
            });
            
            document.getElementById('quiz-form').addEventListener('submit', function(e) {
                let unanswered = [];
                for (let i = 1; i <= totalQuestions; i++) {
                    if (!answers[i]) {
                        unanswered.push(i);
                    }
                }
                
                if (unanswered.length > 0) {
                    e.preventDefault();
                    alert(`Please answer questions: ${unanswered.join(', ')}`);
                }
            });
            
            updateProgress();
        });
    </script>
</body>
</html>