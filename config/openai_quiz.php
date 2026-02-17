<?php
// config/openai_quiz.php

class AIQuizManager {
    private $pdo;
    private $api_key;
    private $model = 'gpt-3.5-turbo';
    private $storage_file;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->storage_file = __DIR__ . '/../storage/quiz_data.json';
        // REPLACE WITH YOUR ACTUAL OPENAI API KEY
        $this->api_key = 'sk-your-actual-api-key-here'; 
        
        // Create storage directory
        $dir = dirname($this->storage_file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    
    public function generateQuiz($roadmap_id, $student_id, $roadmap_title, $num_questions = 15) {
        $session_id = 'quiz_' . $roadmap_id . '_' . $student_id . '_' . time();
        
        try {
            // Try AI generation first
            $questions = $this->generateWithAI($roadmap_title, $num_questions);
            
            if (empty($questions)) {
                throw new Exception("AI failed to generate questions");
            }
            
        } catch (Exception $e) {
            error_log("AI Quiz Generation Error: " . $e->getMessage());
            
            // Use advanced pattern-based generation
            $questions = $this->generatePatternBasedQuestions($roadmap_title, $num_questions);
        }
        
        // Ensure uniqueness
        $questions = $this->ensureQuestionUniqueness($questions);
        
        // Save questions
        $this->saveQuizData($session_id, [
            'questions' => $questions,
            'roadmap_id' => $roadmap_id,
            'student_id' => $student_id,
            'created_at' => time(),
            'expires_at' => time() + 3600
        ]);
        
        return [
            'session_id' => $session_id,
            'questions' => $questions
        ];
    }
    
    private function generateWithAI($roadmap_title, $num_questions) {
        if (empty($this->api_key) || strpos($this->api_key, 'your-actual-api-key') !== false) {
            throw new Exception("Please configure a valid OpenAI API key");
        }
        
        $prompt = "Generate EXACTLY {$num_questions} COMPLETELY DIFFERENT multiple-choice questions about '{$roadmap_title}'.\n\n";
        $prompt .= "RULES:\n";
        $prompt .= "1. Each question must be UNIQUE - no similar wording or concepts\n";
        $prompt .= "2. Questions should cover DIFFERENT aspects of {$roadmap_title}\n";
        $prompt .= "3. Include various question types: definition, application, analysis, comparison, best practices\n";
        $prompt .= "4. Make questions progressively more challenging\n";
        $prompt .= "5. Ensure answers are logically consistent with questions\n\n";
        $prompt .= "FORMAT:\n";
        $prompt .= "Q1: [Unique question 1]\n";
        $prompt .= "A) [Correct]\nB) [Wrong]\nC) [Wrong]\nD) [Wrong]\n";
        $prompt .= "Correct: A\nExplanation: [Why A is correct]\n\n";
        $prompt .= "Q2: [Completely different question 2]\n";
        $prompt .= "A) [Correct]\nB) [Wrong]\nC) [Wrong]\nD) [Wrong]\n";
        $prompt .= "Correct: A\nExplanation: [Why A is correct]\n\n";
        $prompt .= "Continue with Q3, Q4, etc. ALL questions must be DIFFERENT.";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 40,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system', 
                        'content' => 'You generate completely unique quiz questions. No two questions should be similar. You are creative and varied.'
                    ],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.9,
                'max_tokens' => 4000
            ])
        ]);
        
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception("CURL Error: " . curl_error($ch));
        }
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            throw new Exception("OpenAI Error: " . $data['error']['message']);
        }
        
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception("No response content from AI");
        }
        
        $content = $data['choices'][0]['message']['content'] ?? '';
        return $this->parseAIContent($content, $num_questions);
    }
    
    private function parseAIContent($content, $expected_count) {
        $questions = [];
        $lines = explode("\n", $content);
        
        $current_q = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (preg_match('/^Q(\d+):\s*(.+)$/i', $line, $matches)) {
                if ($current_q && !empty($current_q['question']) && count($current_q['options']) >= 4) {
                    $questions[] = $current_q;
                }
                
                $current_q = [
                    'question' => trim($matches[2]),
                    'options' => [],
                    'correct_answer' => '',
                    'explanation' => ''
                ];
            }
            elseif (preg_match('/^A\)\s*(.+)$/i', $line, $matches) && $current_q) {
                $current_q['options']['a'] = trim($matches[1]);
            }
            elseif (preg_match('/^B\)\s*(.+)$/i', $line, $matches) && $current_q) {
                $current_q['options']['b'] = trim($matches[1]);
            }
            elseif (preg_match('/^C\)\s*(.+)$/i', $line, $matches) && $current_q) {
                $current_q['options']['c'] = trim($matches[1]);
            }
            elseif (preg_match('/^D\)\s*(.+)$/i', $line, $matches) && $current_q) {
                $current_q['options']['d'] = trim($matches[1]);
            }
            elseif (preg_match('/^Correct:\s*([A-D])$/i', $line, $matches) && $current_q) {
                $current_q['correct_answer'] = strtolower($matches[1]);
            }
            elseif (preg_match('/^Explanation:\s*(.+)$/i', $line, $matches) && $current_q) {
                $current_q['explanation'] = trim($matches[1]);
            }
        }
        
        if ($current_q && !empty($current_q['question']) && count($current_q['options']) >= 4) {
            $questions[] = $current_q;
        }
        
        // Fill missing questions
        while (count($questions) < $expected_count) {
            $questions[] = $this->createUniquePatternQuestion($roadmap_title, count($questions) + 1);
        }
        
        return array_slice($questions, 0, $expected_count);
    }
    
    private function generatePatternBasedQuestions($roadmap_title, $num_questions) {
        $questions = [];
        $subject_type = $this->determineSubjectType($roadmap_title);
        
        // Generate completely unique questions using multiple patterns
        for ($i = 1; $i <= $num_questions; $i++) {
            $questions[] = $this->createUniquePatternQuestion($roadmap_title, $i, $subject_type);
        }
        
        return $questions;
    }
    
    private function createUniquePatternQuestion($topic, $question_num, $subject_type = 'general') {
        // Multiple independent patterns to ensure uniqueness
        $patterns = $this->getQuestionPatterns();
        $answer_patterns = $this->getAnswerPatterns();
        
        // Use question number to select different pattern combinations
        $pattern_index = ($question_num - 1) % count($patterns);
        $answer_index = (($question_num * 3) - 1) % count($answer_patterns);
        
        // Get base pattern and customize it
        $pattern = $patterns[$pattern_index];
        $answer_set = $answer_patterns[$answer_index];
        
        // Customize based on subject type
        $customized = $this->customizeForSubject($pattern, $answer_set, $topic, $subject_type, $question_num);
        
        return $customized;
    }
    
    private function getQuestionPatterns() {
        // 25 completely different question patterns
        return [
            // Pattern 1: Definition questions
            [
                'template' => "What is the primary objective of {topic} in modern organizations?",
                'type' => 'definition'
            ],
            // Pattern 2: Methodology questions
            [
                'template' => "Which methodology is most effective for implementing {topic} successfully?",
                'type' => 'methodology'
            ],
            // Pattern 3: Tool/Technology questions
            [
                'template' => "What tool or technology has revolutionized the practice of {topic} in recent years?",
                'type' => 'technology'
            ],
            // Pattern 4: Skill questions
            [
                'template' => "Which skill is considered most critical for professionals specializing in {topic}?",
                'type' => 'skill'
            ],
            // Pattern 5: Process questions
            [
                'template' => "What is the first step in a standard {topic} workflow?",
                'type' => 'process'
            ],
            // Pattern 6: Benefit questions
            [
                'template' => "How does effective {topic} contribute to organizational decision-making?",
                'type' => 'benefit'
            ],
            // Pattern 7: Challenge questions
            [
                'template' => "What is the most common challenge faced during {topic} implementation?",
                'type' => 'challenge'
            ],
            // Pattern 8: Quality questions
            [
                'template' => "Which factor most significantly impacts the quality of {topic} outcomes?",
                'type' => 'quality'
            ],
            // Pattern 9: Comparison questions
            [
                'template' => "How does {topic} differ from traditional approaches in the same field?",
                'type' => 'comparison'
            ],
            // Pattern 10: Future trend questions
            [
                'template' => "What emerging trend is shaping the future of {topic}?",
                'type' => 'trend'
            ],
            // Pattern 11: Measurement questions
            [
                'template' => "Which metric is most appropriate for measuring {topic} success?",
                'type' => 'measurement'
            ],
            // Pattern 12: Ethical questions
            [
                'template' => "What ethical consideration is paramount in {topic} practices?",
                'type' => 'ethics'
            ],
            // Pattern 13: Integration questions
            [
                'template' => "How does {topic} integrate with other business functions?",
                'type' => 'integration'
            ],
            // Pattern 14: Risk questions
            [
                'template' => "What risk is most commonly associated with {topic} projects?",
                'type' => 'risk'
            ],
            // Pattern 15: Best practice questions
            [
                'template' => "Which practice significantly improves efficiency in {topic}?",
                'type' => 'practice'
            ],
            // Pattern 16: Data questions
            [
                'template' => "What type of data is most valuable for {topic} analysis?",
                'type' => 'data'
            ],
            // Pattern 17: Communication questions
            [
                'template' => "How should {topic} findings be communicated to non-technical stakeholders?",
                'type' => 'communication'
            ],
            // Pattern 18: Validation questions
            [
                'template' => "What method is most reliable for validating {topic} results?",
                'type' => 'validation'
            ],
            // Pattern 19: Cost questions
            [
                'template' => "Which factor has the greatest impact on {topic} implementation costs?",
                'type' => 'cost'
            ],
            // Pattern 20: Team questions
            [
                'template' => "What team composition is ideal for {topic} projects?",
                'type' => 'team'
            ],
            // Pattern 21: Time questions
            [
                'template' => "What timeframe is typically required for meaningful {topic} results?",
                'type' => 'time'
            ],
            // Pattern 22: Standard questions
            [
                'template' => "Which industry standard most influences {topic} practices?",
                'type' => 'standard'
            ],
            // Pattern 23: Innovation questions
            [
                'template' => "How has technology innovation transformed {topic} methodologies?",
                'type' => 'innovation'
            ],
            // Pattern 24: Training questions
            [
                'template' => "What training approach is most effective for {topic} skill development?",
                'type' => 'training'
            ],
            // Pattern 25: ROI questions
            [
                'template' => "How is return on investment typically calculated for {topic} initiatives?",
                'type' => 'roi'
            ]
        ];
    }
    
    private function getAnswerPatterns() {
        // 20 different answer patterns
        return [
            // Pattern 1
            [
                'options' => [
                    'a' => "Applying systematic methodologies with clear objectives",
                    'b' => "Relying on intuitive approaches without structure",
                    'c' => "Focusing only on theoretical concepts",
                    'd' => "Avoiding established frameworks and guidelines"
                ],
                'correct' => 'a',
                'explanation' => "Systematic approaches ensure consistency and measurable outcomes."
            ],
            // Pattern 2
            [
                'options' => [
                    'a' => "Understanding core principles before implementation",
                    'b' => "Jumping directly to advanced techniques",
                    'c' => "Memorizing procedures without comprehension",
                    'd' => "Ignoring foundational knowledge"
                ],
                'correct' => 'a',
                'explanation' => "Solid understanding of fundamentals enables effective application."
            ],
            // Pattern 3
            [
                'options' => [
                    'a' => "Combining quantitative analysis with qualitative insights",
                    'b' => "Relying solely on numerical data",
                    'c' => "Using only anecdotal evidence",
                    'd' => "Making decisions without any data"
                ],
                'correct' => 'a',
                'explanation' => "Integrated approaches provide comprehensive understanding."
            ],
            // Pattern 4
            [
                'options' => [
                    'a' => "Continuous iteration based on feedback and results",
                    'b' => "Sticking rigidly to initial plans",
                    'c' => "Frequently changing direction without reason",
                    'd' => "Avoiding any adjustments during implementation"
                ],
                'correct' => 'a',
                'explanation' => "Iterative improvement leads to optimal outcomes."
            ],
            // Pattern 5
            [
                'options' => [
                    'a' => "Clear documentation and knowledge sharing",
                    'b' => "Keeping information within small teams",
                    'c' => "Relying on verbal communication only",
                    'd' => "Avoiding written records"
                ],
                'correct' => 'a',
                'explanation' => "Documentation ensures consistency and facilitates collaboration."
            ],
            // Pattern 6
            [
                'options' => [
                    'a' => "Evidence-based decision making with validation",
                    'b' => "Following trends without verification",
                    'c' => "Relying on assumptions and intuition",
                    'd' => "Copying others without adaptation"
                ],
                'correct' => 'a',
                'explanation' => "Evidence ensures decisions are grounded in reality."
            ],
            // Pattern 7
            [
                'options' => [
                    'a' => "Strategic alignment with organizational goals",
                    'b' => "Pursuing activities without clear purpose",
                    'c' => "Focusing only on technical excellence",
                    'd' => "Ignoring business context"
                ],
                'correct' => 'a',
                'explanation' => "Alignment ensures activities create business value."
            ],
            // Pattern 8
            [
                'options' => [
                    'a' => "Balancing innovation with practical constraints",
                    'b' => "Pursuing novelty without consideration of feasibility",
                    'c' => "Sticking only to traditional methods",
                    'd' => "Avoiding any new approaches"
                ],
                'correct' => 'a',
                'explanation' => "Balance enables progress while managing risks."
            ],
            // Pattern 9
            [
                'options' => [
                    'a' => "Proactive risk identification and mitigation",
                    'b' => "Ignoring potential problems until they occur",
                    'c' => "Being overly cautious without action",
                    'd' => "Taking unnecessary risks"
                ],
                'correct' => 'a',
                'explanation' => "Proactive risk management prevents major issues."
            ],
            // Pattern 10
            [
                'options' => [
                    'a' => "Collaborative approaches involving stakeholders",
                    'b' => "Working in isolation from others",
                    'c' => "Making decisions unilaterally",
                    'd' => "Avoiding input from experts"
                ],
                'correct' => 'a',
                'explanation' => "Collaboration ensures diverse perspectives and buy-in."
            ],
            // Pattern 11
            [
                'options' => [
                    'a' => "Data quality assurance and validation processes",
                    'b' => "Using any available data without checks",
                    'c' => "Focusing only on data quantity",
                    'd' => "Ignoring data source reliability"
                ],
                'correct' => 'a',
                'explanation' => "Quality data is essential for reliable outcomes."
            ],
            // Pattern 12
            [
                'options' => [
                    'a' => "Measurable outcomes with clear success criteria",
                    'b' => "Vague objectives without metrics",
                    'c' => "Focusing only on activity, not results",
                    'd' => "Avoiding performance measurement"
                ],
                'correct' => 'a',
                'explanation' => "Measurable criteria enable progress tracking and evaluation."
            ],
            // Pattern 13
            [
                'options' => [
                    'a' => "Adaptive methodologies that respond to change",
                    'b' => "Rigid processes that resist modification",
                    'c' => "Constantly changing without reason",
                    'd' => "Avoiding any process structure"
                ],
                'correct' => 'a',
                'explanation' => "Adaptability ensures relevance in changing environments."
            ],
            // Pattern 14
            [
                'options' => [
                    'a' => "Ethical considerations and responsible practices",
                    'b' => "Focusing only on results regardless of means",
                    'c' => "Ignoring social and environmental impacts",
                    'd' => "Avoiding ethical discussions"
                ],
                'correct' => 'a',
                'explanation' => "Ethical practices ensure long-term sustainability."
            ],
            // Pattern 15
            [
                'options' => [
                    'a' => "Integration with existing systems and processes",
                    'b' => "Creating isolated solutions",
                    'c' => "Replacing everything without consideration",
                    'd' => "Ignoring compatibility requirements"
                ],
                'correct' => 'a',
                'explanation' => "Integration maximizes efficiency and adoption."
            ],
            // Pattern 16
            [
                'options' => [
                    'a' => "Scalable solutions that accommodate growth",
                    'b' => "Designing only for current needs",
                    'c' => "Over-engineering without justification",
                    'd' => "Avoiding future planning"
                ],
                'correct' => 'a',
                'explanation' => "Scalability ensures long-term viability."
            ],
            // Pattern 17
            [
                'options' => [
                    'a' => "User-centered design and experience focus",
                    'b' => "Technical implementation without user consideration",
                    'c' => "Following personal preferences only",
                    'd' => "Ignoring usability aspects"
                ],
                'correct' => 'a',
                'explanation' => "User focus ensures solutions meet actual needs."
            ],
            // Pattern 18
            [
                'options' => [
                    'a' => "Cost-benefit analysis and resource optimization",
                    'b' => "Spending without budget consideration",
                    'c' => "Being overly frugal at expense of quality",
                    'd' => "Ignoring financial implications"
                ],
                'correct' => 'a',
                'explanation' => "Financial prudence ensures sustainable implementation."
            ],
            // Pattern 19
            [
                'options' => [
                    'a' => "Continuous learning and skill development",
                    'b' => "Relying only on existing knowledge",
                    'c' => "Learning without application",
                    'd' => "Avoiding professional development"
                ],
                'correct' => 'a',
                'explanation' => "Continuous learning maintains relevance and expertise."
            ],
            // Pattern 20
            [
                'options' => [
                    'a' => "Transparent processes with clear accountability",
                    'b' => "Opaque decision-making processes",
                    'c' => "Avoiding responsibility assignment",
                    'd' => "Keeping processes hidden"
                ],
                'correct' => 'a',
                'explanation' => "Transparency builds trust and enables improvement."
            ]
        ];
    }
    
    private function customizeForSubject($pattern, $answer_set, $topic, $subject_type, $question_num) {
        $question_text = str_replace('{topic}', $topic, $pattern['template']);
        
        // Add subject-specific variations
        $variations = $this->getSubjectVariations($subject_type, $pattern['type'], $question_num);
        
        if (!empty($variations['question'])) {
            $question_text = $variations['question'];
            $question_text = str_replace('{topic}', $topic, $question_text);
        }
        
        if (!empty($variations['answers'])) {
            $answer_set['options'] = $variations['answers'];
        }
        
        if (!empty($variations['explanation'])) {
            $answer_set['explanation'] = $variations['explanation'];
        }
        
        return [
            'question' => $question_text,
            'options' => $answer_set['options'],
            'correct_answer' => $answer_set['correct'],
            'explanation' => $answer_set['explanation']
        ];
    }
    
    private function getSubjectVariations($subject_type, $question_type, $question_num) {
        $variations = [];
        
        $subject_keywords = [
            'data' => ['data analysis', 'statistical methods', 'data visualization', 'predictive modeling', 'data quality'],
            'tech' => ['software development', 'system design', 'coding practices', 'technical architecture', 'debugging'],
            'business' => ['strategic planning', 'market analysis', 'financial metrics', 'stakeholder management', 'ROI calculation'],
            'creative' => ['design principles', 'creative process', 'visual communication', 'user experience', 'aesthetic judgment']
        ];
        
        $keywords = $subject_keywords[$subject_type] ?? ['methodology', 'implementation', 'best practices', 'quality assurance'];
        
        // Select keyword based on question number for variety
        $keyword = $keywords[$question_num % count($keywords)];
        
        switch ($question_type) {
            case 'definition':
                $variations['question'] = "What characterizes effective " . $keyword . " in {topic}?";
                break;
            case 'methodology':
                $variations['question'] = "Which " . $keyword . " approach yields the most reliable results in {topic}?";
                break;
            case 'technology':
                $variations['question'] = "How has " . $keyword . " technology influenced {topic} practices?";
                break;
        }
        
        return $variations;
    }
    
    private function determineSubjectType($title) {
        $title_lower = strtolower($title);
        
        if (strpos($title_lower, 'data') !== false || strpos($title_lower, 'analytics') !== false) {
            return 'data';
        }
        elseif (strpos($title_lower, 'web') !== false || strpos($title_lower, 'programming') !== false) {
            return 'tech';
        }
        elseif (strpos($title_lower, 'business') !== false || strpos($title_lower, 'market') !== false) {
            return 'business';
        }
        elseif (strpos($title_lower, 'design') !== false || strpos($title_lower, 'creative') !== false) {
            return 'creative';
        }
        
        return 'general';
    }
    
    private function ensureQuestionUniqueness($questions) {
        $unique_questions = [];
        $seen_hashes = [];
        
        foreach ($questions as $q) {
            // Create a hash based on question and options
            $question_hash = md5($q['question'] . implode('', $q['options']));
            
            if (!in_array($question_hash, $seen_hashes)) {
                $unique_questions[] = $q;
                $seen_hashes[] = $question_hash;
            } else {
                // Replace duplicate with a new unique question
                $new_q = $this->createUniquePatternQuestion(
                    $this->extractTopicFromQuestion($q['question']), 
                    count($unique_questions) + 1
                );
                $unique_questions[] = $new_q;
                $seen_hashes[] = md5($new_q['question'] . implode('', $new_q['options']));
            }
        }
        
        return $unique_questions;
    }
    
    private function extractTopicFromQuestion($question) {
        // Simple extraction of topic from question
        if (preg_match('/about\s+(.+?)\??$/i', $question, $matches)) {
            return trim($matches[1]);
        }
        if (preg_match('/of\s+(.+?)\??$/i', $question, $matches)) {
            return trim($matches[1]);
        }
        return "the subject";
    }
    
    private function saveQuizData($session_id, $data) {
        $all_data = [];
        
        if (file_exists($this->storage_file)) {
            $all_data = json_decode(file_get_contents($this->storage_file), true) ?: [];
        }
        
        // Clean up old sessions
        foreach ($all_data as $key => $session) {
            if (isset($session['expires_at']) && $session['expires_at'] < time()) {
                unset($all_data[$key]);
            }
        }
        
        $all_data[$session_id] = $data;
        file_put_contents($this->storage_file, json_encode($all_data, JSON_PRETTY_PRINT));
    }
    
    public function getQuizData($session_id) {
        if (!file_exists($this->storage_file)) {
            return null;
        }
        
        $all_data = json_decode(file_get_contents($this->storage_file), true);
        
        if (isset($all_data[$session_id]) && isset($all_data[$session_id]['expires_at']) && 
            $all_data[$session_id]['expires_at'] > time()) {
            return $all_data[$session_id];
        }
        
        if (isset($all_data[$session_id])) {
            unset($all_data[$session_id]);
            file_put_contents($this->storage_file, json_encode($all_data, JSON_PRETTY_PRINT));
        }
        
        return null;
    }
    
    public function evaluateQuiz($session_id, $user_answers) {
        $data = $this->getQuizData($session_id);
        
        if (!$data) {
            throw new Exception("Quiz session expired or not found");
        }
        
        $questions = $data['questions'];
        $score = 0;
        $results = [];
        
        foreach ($questions as $index => $q) {
            $q_num = $index + 1;
            $user_answer = strtolower($user_answers[$q_num] ?? '');
            $is_correct = ($user_answer === $q['correct_answer']);
            
            if ($is_correct) $score++;
            
            $results[] = [
                'question_num' => $q_num,
                'question' => $q['question'],
                'user_answer' => $user_answer ?: 'Not answered',
                'correct_answer' => $q['correct_answer'],
                'is_correct' => $is_correct,
                'explanation' => $q['explanation'] ?? 'No explanation provided.'
            ];
        }
        
        $total = count($questions);
        $percentage = ($score / $total) * 100;
        $passed = $percentage >= 80;
        
        return [
            'score' => $score,
            'total' => $total,
            'percentage' => $percentage,
            'passed' => $passed,
            'results' => $results,
            'roadmap_id' => $data['roadmap_id'],
            'student_id' => $data['student_id']
        ];
    }
    
    public function testUniqueness() {
        echo "<h3>Testing Question Uniqueness</h3>";
        
        $topics = ["Data Analytics", "Web Development", "Business Strategy"];
        
        foreach ($topics as $topic) {
            echo "<h4>Topic: {$topic}</h4>";
            
            // Generate 15 questions
            $questions = $this->generatePatternBasedQuestions($topic, 15);
            
            // Check for duplicates
            $question_texts = array_map(function($q) { return $q['question']; }, $questions);
            $unique_count = count(array_unique($question_texts));
            $total_count = count($questions);
            
            echo "Generated: {$total_count} questions<br>";
            echo "Unique: {$unique_count} questions<br>";
            
            if ($unique_count == $total_count) {
                echo "<span style='color:green;'>✓ SUCCESS: All questions are unique!</span><br>";
            } else {
                echo "<span style='color:red;'>✗ FAILURE: Found " . ($total_count - $unique_count) . " duplicates</span><br>";
                
                // Show duplicates
                $counts = array_count_values($question_texts);
                $duplicate_num = 1;
                foreach ($counts as $question => $count) {
                    if ($count > 1) {
                        echo "Duplicate #{$duplicate_num}: '{$question}' (repeats {$count} times)<br>";
                        $duplicate_num++;
                    }
                }
            }
            
            // Show all questions
            echo "<h5>All Questions:</h5>";
            echo "<ol>";
            foreach ($questions as $i => $q) {
                echo "<li>" . htmlspecialchars($q['question']) . "</li>";
            }
            echo "</ol>";
            echo "<hr>";
        }
    }
}
?>x