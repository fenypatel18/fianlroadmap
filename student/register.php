
<?php
// student/register.php

// Start session and include config
session_start();

// Define BASE_URL and url() function
define('BASE_URL', '/fianlroadmap');
if (!function_exists('url')) {
    function url($path = '') {
        $path = ltrim($path, '/');
        $url = BASE_URL;
        if (!empty($path)) {
            $url .= '/' . $path;
        }
        return $url;
    }
}

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header('Location: ' . url('student/dashboard.php'));
    exit();
}

// CSS colors from your landing page
$gradient_start = 'rgb(108, 0, 162)';
$gradient_end = 'rgb(0, 17, 82)';
$bg_dark = 'rgb(19, 20, 23)';
$bg_darker = 'rgb(4, 7, 29)';
?>
<!DOCTYPE html>
<html lang="en" class="dark" style="color-scheme: dark;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Account - YourRoadmap</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --gradient-background-start: <?php echo $gradient_start; ?>;
            --gradient-background-end: <?php echo $gradient_end; ?>;
        }
        
        body {
            background-color: <?php echo $bg_dark; ?>;
            min-height: 100vh;
        }
        
        .bg-hero-grid {
            position: fixed;
            inset: 0;
            background-color: #0a0a0f;
            background-image: 
                linear-gradient(to right, rgba(100, 149, 237, 0.1) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(100, 149, 237, 0.1) 1px, transparent 1px),
                linear-gradient(45deg, transparent 49.8%, rgba(108, 0, 162, 0.05) 50%, transparent 50.2%),
                linear-gradient(-45deg, transparent 49.8%, rgba(0, 17, 82, 0.05) 50%, transparent 50.2%),
                radial-gradient(circle at 20% 20%, rgba(108, 0, 162, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 80% 20%, rgba(0, 17, 82, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 20% 80%, rgba(108, 0, 162, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 80% 80%, rgba(0, 17, 82, 0.1) 0%, transparent 40%),
                linear-gradient(to right, rgba(138, 43, 226, 0.05), transparent 10%, transparent 90%, rgba(0, 17, 82, 0.05)),
                linear-gradient(to bottom, rgba(138, 43, 226, 0.05), transparent 10%, transparent 90%, rgba(0, 17, 82, 0.05));
            background-size: 
                50px 50px, 50px 50px, 20px 20px, 20px 20px, 100% 100%, 100% 100%, 100% 100%, 100% 100%, 100% 100%, 100% 100%;
            background-position: 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0;
            z-index: -2;
        }
        
        .bg-hero-grid::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at center, transparent 20%, <?php echo $bg_dark; ?> 70%);
            pointer-events: none;
        }
        
        .animated-button {
            border: 1px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .animated-button::before {
            content: '';
            position: absolute;
            inset: -1000%;
            background: conic-gradient(from 90deg at 50% 50%, #E2CBFF 0%, #393BB2 50%, #E2CBFF 100%);
            animation: spin 2s linear infinite;
        }
        
        .animated-button span:last-child {
            background-color: #0f172a;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .text-purple {
            color: rgb(168, 85, 247);
        }
        
        .bg-glass {
            background-color: rgba(17, 25, 40, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.125);
        }
        
        .backdrop-blur-saturate {
            backdrop-filter: blur(16px) saturate(180%);
        }
        
        .input-glow:focus {
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.3);
        }
        
        .gradient-border {
            border: double 1px transparent;
            background-image: linear-gradient(<?php echo $bg_darker; ?>, <?php echo $bg_darker; ?>), 
                              linear-gradient(45deg, var(--gradient-background-start), var(--gradient-background-end));
            background-origin: border-box;
            background-clip: padding-box, border-box;
        }
        
        .floating-animation {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        
        .pulse-animation {
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6;
            }
        }
        
        @keyframes blob {
            0% {
                transform: translate(0px, 0px) scale(1);
            }
            33% {
                transform: translate(30px, -50px) scale(1.1);
            }
            66% {
                transform: translate(-20px, 20px) scale(0.9);
            }
            100% {
                transform: translate(0px, 0px) scale(1);
            }
        }
        
        .animate-blob {
            animation: blob 7s infinite;
        }
        
        .animation-delay-2000 {
            animation-delay: 2s;
        }
        
        .animation-delay-4000 {
            animation-delay: 4s;
        }
    </style>
</head>
<body class="dark relative min-h-screen">
    <!-- Fixed Background Grid -->
    <div class="bg-hero-grid"></div>
    
    <!-- Decorative Blobs (also fixed) -->
    <div class="fixed top-10 left-10 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-xl opacity-10 animate-blob"></div>
    <div class="fixed bottom-10 right-10 w-72 h-72 bg-blue-500 rounded-full mix-blend-multiply filter blur-xl opacity-10 animate-blob animation-delay-2000"></div>
    <div class="fixed top-1/2 left-1/3 w-72 h-72 bg-pink-500 rounded-full mix-blend-multiply filter blur-xl opacity-10 animate-blob animation-delay-4000"></div>
    
    <!-- Main Content (scrollable) -->
    <div class="relative z-10">
        <div class="min-h-screen flex items-center justify-center px-4 py-12">
            <div class="max-w-6xl w-full grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                
                <!-- Left Side - Branding & Info -->
                <div class="text-center lg:text-left space-y-8">
                    <!-- Logo -->
                    <div class="flex items-center justify-center lg:justify-start space-x-3">
                        <div class="w-12 h-12 bg-gradient-to-r from-purple-600 to-blue-600 rounded-xl flex items-center justify-center">
                            <span class="text-white font-bold text-xl">YR</span>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white">Your<span class="text-purple">Roadmap</span></h1>
                            <p class="text-gray-400 text-sm">Career Development Platform</p>
                        </div>
                    </div>
                    
                    <!-- Hero Text -->
                    <div>
                        <h2 class="text-4xl lg:text-5xl font-bold text-white leading-tight">
                            Begin Your <span class="text-purple">Learning Journey</span> Today
                        </h2>
                        <p class="text-gray-300 text-lg mt-4 max-w-lg">
                            Join thousands of learners who are accelerating their careers with beginner-friendly roadmaps and curated resources.
                        </p>
                    </div>
                    
                    <!-- Features -->
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-lg bg-purple-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-purple-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <span class="text-gray-300">Beginner-friendly roadmaps</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <span class="text-gray-300">Expert-curated resources</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-lg bg-green-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <span class="text-gray-300">Track your progress & profile</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-lg bg-yellow-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <span class="text-gray-300">Earn certificates of completion</span>
                        </div>
                    </div>
                    
                    <!-- Floating Illustration -->
                    <div class="relative h-48 mt-8">
                        <div class="absolute inset-0 flex items-center justify-center floating-animation">
                            <div class="relative">
                                <div class="w-40 h-40 bg-gradient-to-br from-purple-900/20 to-blue-900/20 rounded-full border border-white/10 flex items-center justify-center">
                                    <div class="w-32 h-32 bg-gradient-to-tr from-purple-600/30 to-blue-600/30 rounded-full border border-white/10 flex items-center justify-center">
                                        <svg class="w-20 h-20 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l9-5-9-5-9 5 9 5z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/>
                                        </svg>
                                    </div>
                                </div>
                                <!-- Glowing dots -->
                                <div class="absolute -top-2 -right-2 w-6 h-6 bg-purple-500 rounded-full opacity-70 pulse-animation"></div>
                                <div class="absolute -bottom-2 -left-2 w-4 h-4 bg-blue-500 rounded-full opacity-70 pulse-animation" style="animation-delay: 1s;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side - Registration Form -->
                <div class="relative">
                    <div class="backdrop-blur-saturate bg-glass rounded-2xl p-8 lg:p-10 border border-white/10 shadow-2xl">
                        <div class="text-center mb-8">
                            <h2 class="text-3xl font-bold text-white">Create Your Account</h2>
                            <p class="text-gray-400 mt-2">Start your learning journey in minutes</p>
                        </div>
                        
                        <div id="message-container" class="mb-6"></div>
                        
                        <form id="register-form" method="POST" action="<?php echo url('auth/register_student.php'); ?>" class="space-y-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-300 mb-2">
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-purple-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                        Full Name
                                    </span>
                                </label>
                                <div class="relative">
                                    <input id="name" name="name" type="text" required 
                                           class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none input-glow transition-all duration-300"
                                           placeholder="John Doe">
                                    <div class="absolute right-3 top-3">
                                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-300 mb-2">
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-purple-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                        </svg>
                                        Email Address
                                    </span>
                                </label>
                                <div class="relative">
                                    <input id="email" name="email" type="email" autocomplete="email" required 
                                           class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none input-glow transition-all duration-300"
                                           placeholder="john@example.com">
                                    <div class="absolute right-3 top-3">
                                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-purple-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Password
                                    </span>
                                </label>
                                <div class="relative">
                                    <input id="password" name="password" type="password" required minlength="8" 
                                           class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none input-glow transition-all duration-300"
                                           placeholder="••••••••">
                                    <div class="absolute right-3 top-3">
                                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-2 flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Must be at least 8 characters long
                                </p>
                            </div>
                            
                            <!-- Terms Checkbox -->
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="terms" name="terms" type="checkbox" required
                                           class="w-4 h-4 bg-white/5 border border-white/10 rounded focus:ring-2 focus:ring-purple-500 focus:ring-offset-0">
                                </div>
                                <label for="terms" class="ml-2 text-sm text-gray-400">
                                    I agree to the <a href="#" class="text-purple-400 hover:text-purple-300">Terms of Service</a> and <a href="#" class="text-purple-400 hover:text-purple-300">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <div>
                                <button type="submit" id="submit-btn"
                                        class="relative w-full h-12 overflow-hidden rounded-lg p-[1px] focus:outline-none animated-button">
                                    <span class="absolute inset-[-1000%] animate-[spin_2s_linear_infinite] bg-[conic-gradient(from_90deg_at_50%_50%,#E2CBFF_0%,#393BB2_50%,#E2CBFF_100%)]"></span>
                                    <span class="inline-flex h-full w-full cursor-pointer items-center justify-center rounded-lg bg-slate-950 px-7 text-sm font-medium text-white backdrop-blur-3xl gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                        </svg>
                                        <span id="submit-text">Create Account</span>
                                    </span>
                                </button>
                            </div>
                        </form>
                        
                        <!-- Divider -->
                        <div class="relative my-8">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-white/10"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-4 bg-gray-900 text-gray-400">Already have an account?</span>
                            </div>
                        </div>
                        
                        <!-- Login Link -->
                        <div class="text-center">
                            <a href="<?php echo url('auth/login.php'); ?>" 
                               class="inline-flex items-center px-6 py-3 border border-white/20 text-white font-medium rounded-lg hover:bg-white/10 transition-all duration-300">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                </svg>
                                Log in to Your Account
                            </a>
                        </div>
                    </div>
                    
                    <!-- Stats at bottom -->
                    <div class="mt-8 grid grid-cols-3 gap-4 text-center">
                        <div class="p-4 bg-white/5 rounded-lg border border-white/10">
                            <div class="text-2xl font-bold text-white">10K+</div>
                            <div class="text-sm text-gray-400">Active Learners</div>
                        </div>
                        <div class="p-4 bg-white/5 rounded-lg border border-white/10">
                            <div class="text-2xl font-bold text-white">50+</div>
                            <div class="text-sm text-gray-400">Roadmaps</div>
                        </div>
                        <div class="p-4 bg-white/5 rounded-lg border border-white/10">
                            <div class="text-2xl font-bold text-white">98%</div>
                            <div class="text-sm text-gray-400">Satisfaction</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('register-form');
            const messageContainer = document.getElementById('message-container');
            const submitBtn = document.getElementById('submit-btn');
            const submitText = document.getElementById('submit-text');
            const originalBtnText = submitText.textContent;

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                
                // Clear previous messages
                messageContainer.innerHTML = '';
                messageContainer.className = '';
                
                // Check terms
                if (!document.getElementById('terms').checked) {
                    showMessage('Please agree to the Terms of Service and Privacy Policy', 'error');
                    return;
                }
                
                // Show loading state
                submitText.textContent = 'Creating Account...';
                submitBtn.disabled = true;
                
                // Get form data
                const formData = new FormData(form);
                
                // Create FormData for traditional form submission
                const formDataObject = new URLSearchParams();
                formDataObject.append('name', formData.get('name'));
                formDataObject.append('email', formData.get('email'));
                formDataObject.append('password', formData.get('password'));

                try {
                    // Send request using traditional form submission
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formDataObject
                    });

                    const result = await response.json();

                    if (response.ok && result.status === 'success') {
                        // Success
                        showMessage(result.message, 'success');
                        
                        // Add success animation
                        submitBtn.classList.add('scale-95');
                        setTimeout(() => submitBtn.classList.remove('scale-95'), 300);
                        
                        // Redirect after 2 seconds
                        setTimeout(() => {
                            if (result.redirect) {
                                window.location.href = result.redirect;
                            } else {
                                window.location.href = '<?php echo url('student/dashboard.php'); ?>';
                            }
                        }, 2000);
                        
                    } else {
                        // Error
                        showMessage(result.message || 'An unknown error occurred.', 'error');
                        
                        // Reset button
                        submitText.textContent = originalBtnText;
                        submitBtn.disabled = false;
                    }
                    
                } catch (error) {
                    // Network error - try fallback to traditional form submission
                    console.error('Fetch error:', error);
                    
                    // If fetch fails, submit the form traditionally
                    showMessage('Submitting form...', 'info');
                    form.submit();
                }
            });
            
            function showMessage(message, type) {
                messageContainer.innerHTML = `
                    <div class="p-4 rounded-lg border-l-4 ${type === 'success' ? 'bg-green-900/20 border-green-500' : type === 'error' ? 'bg-red-900/20 border-red-500' : 'bg-blue-900/20 border-blue-500'}">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-3 ${type === 'success' ? 'text-green-400' : type === 'error' ? 'text-red-400' : 'text-blue-400'}" fill="currentColor" viewBox="0 0 20 20">
                                ${type === 'success' ? 
                                    '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>' :
                                type === 'error' ?
                                    '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>' :
                                    '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>'
                                }
                            </svg>
                            <span class="${type === 'success' ? 'text-green-300' : type === 'error' ? 'text-red-300' : 'text-blue-300'} font-medium">
                                ${message}
                            </span>
                        </div>
                    </div>
                `;
            }
        });
    </script>
</body>
</html>
