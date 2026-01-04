<?php
// index.php - Pixel-perfect recreation of OneRoadmap.io homepage
// For educational/internal use only

// Start session for user state management
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// CSS colors and variables from the original site
$gradient_start = 'rgb(108, 0, 162)';
$gradient_end = 'rgb(0, 17, 82)';
$bg_dark = 'rgb(19, 20, 23)';
$bg_darker = 'rgb(4, 7, 29)';
?>

<!DOCTYPE html>
<html lang="en" class="dark" style="color-scheme: dark;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>YourRoadmap - Your Career Development Platform</title>
    
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom Tailwind configuration -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'black-100': 'rgb(0, 3, 25)',
                        'purple': 'rgb(168, 85, 247)',
                        'blue-100': 'rgb(191, 219, 254)',
                    },
                    animation: {
                        'spotlight': 'spotlight 2s ease .75s 1 forwards',
                        'scroll': 'scroll var(--animation-duration, 40s) var(--animation-direction, forwards) linear infinite',
                        'flip': 'flip 1s ease forwards',
                        'rotate': 'rotate 9s linear infinite',
                        'first': 'moveVertical 30s ease infinite',
                        'second': 'moveInCircle 20s reverse infinite',
                        'third': 'moveInCircle 40s linear infinite',
                        'fourth': 'moveHorizontal 40s ease infinite',
                        'fifth': 'moveInCircle 20s ease infinite',
                    },
                    keyframes: {
                        spotlight: {
                            '0%': { opacity: 0, transform: 'translate(-72%, -62%) scale(0.5)' },
                            '100%': { opacity: 1, transform: 'translate(-50%, -40%) scale(1)' },
                        },
                        scroll: {
                            to: { transform: 'translate(calc(-50% - 0.5rem))' },
                        },
                        flip: {
                            '0%': { transform: 'rotateX(0)' },
                            '100%': { transform: 'rotateX(360deg)' },
                        },
                        rotate: {
                            '0%': { transform: 'rotate(0deg)' },
                            '50%': { transform: 'rotate(180deg)' },
                            '100%': { transform: 'rotate(360deg)' },
                        },
                        moveVertical: {
                            '0%': { transform: 'translateY(-50%)' },
                            '50%': { transform: 'translateY(50%)' },
                            '100%': { transform: 'translateY(-50%)' },
                        },
                        moveInCircle: {
                            '0%': { transform: 'rotate(0deg)' },
                            '50%': { transform: 'rotate(180deg)' },
                            '100%': { transform: 'rotate(360deg)' },
                        },
                        moveHorizontal: {
                            '0%': { transform: 'translateX(-50%) translateY(-10%)' },
                            '50%': { transform: 'translateX(50%) translateY(10%)' },
                            '100%': { transform: 'translateX(-50%) translateY(-10%)' },
                        },
                    },
                    backgroundImage: {
                        'grid-white': 'linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px), linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px)',
                        'grid-black-100': 'linear-gradient(to right, rgba(0, 0, 0, 0.1) 1px, transparent 1px), linear-gradient(to bottom, rgba(0, 0, 0, 0.1) 1px, transparent 1px)',
                    },
                },
            },
        }
    </script>
    
    <!-- Custom styles matching the original site -->
    <style>
        :root {
            --gradient-background-start: <?php echo $gradient_start; ?>;
            --gradient-background-end: <?php echo $gradient_end; ?>;
            --first-color: 18, 113, 255;
            --second-color: 221, 74, 255;
            --third-color: 100, 220, 255;
            --fourth-color: 200, 50, 50;
            --fifth-color: 180, 180, 50;
            --pointer-color: 140, 100, 255;
            --size: 80%;
            --blending-value: hard-light;
        }
        
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .heading {
            font-size: 2.25rem;
            line-height: 2.5rem;
            font-weight: 700;
            color: white;
        }
        
        @media (min-width: 768px) {
            .heading {
                font-size: 3rem;
                line-height: 1;
            }
        }
        
        @media (min-width: 1024px) {
            .heading {
                font-size: 3.75rem;
                line-height: 1;
            }
        }
        
        .text-purple {
            color: rgb(168, 85, 247);
        }
        
        .text-white-200 {
            color: rgba(255, 255, 255, 0.8);
        }
        
        /* Futuristic grid background for hero section */
        .bg-hero-grid {
            background-color: #0a0a0f;
            background-image: 
                /* Main orthogonal grid - thin lines forming check pattern */
                linear-gradient(to right, rgba(100, 149, 237, 0.1) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(100, 149, 237, 0.1) 1px, transparent 1px),
                /* Subtle cross-hatch for check pattern */
                linear-gradient(45deg, transparent 49.8%, rgba(108, 0, 162, 0.05) 50%, transparent 50.2%),
                linear-gradient(-45deg, transparent 49.8%, rgba(0, 17, 82, 0.05) 50%, transparent 50.2%),
                /* Corner glow gradients */
                radial-gradient(circle at 20% 20%, rgba(108, 0, 162, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 80% 20%, rgba(0, 17, 82, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 20% 80%, rgba(108, 0, 162, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 80% 80%, rgba(0, 17, 82, 0.1) 0%, transparent 40%),
                /* Edge glows - blue-purple blend */
                linear-gradient(to right, rgba(138, 43, 226, 0.05), transparent 10%, transparent 90%, rgba(0, 17, 82, 0.05)),
                linear-gradient(to bottom, rgba(138, 43, 226, 0.05), transparent 10%, transparent 90%, rgba(0, 17, 82, 0.05));
            background-size: 
                50px 50px, /* Grid cell size - small-to-medium square units */
                50px 50px,
                20px 20px, /* Check pattern spacing */
                20px 20px,
                100% 100%,
                100% 100%,
                100% 100%,
                100% 100%,
                100% 100%,
                100% 100%;
            background-position: 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0;
        }
        .backdrop-blur-saturate {
            backdrop-filter: blur(16px) saturate(180%);
        }
        
        .bg-glass {
            background-color: rgba(17, 25, 40, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.125);
        }
        
        .spark::before {
            content: '';
            position: absolute;
            inset: 0;
            background: conic-gradient(from 0deg, transparent 0 340deg, white 360deg);
            animation: rotate 9s linear infinite;
        }
        
        .mask-gradient {
            mask: linear-gradient(white, transparent 50%);
        }
        
        .gradients-container {
            filter: url(#blurMe) blur(40px);
        }
        
        /* Animated button styles */
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
        
        /* Scroller styles */
        .scroller {
            mask-image: linear-gradient(to right, transparent, white 20%, white 80%, transparent);
        }
        
        /* Hero text animation */
        .hero-text span {
            opacity: 0;
            animation: fadeInUp 0.5s ease forwards;
        }
        
        .hero-text span:nth-child(1) { animation-delay: 0.1s; }
        .hero-text span:nth-child(2) { animation-delay: 0.2s; }
        .hero-text span:nth-child(3) { animation-delay: 0.3s; }
        .hero-text span:nth-child(4) { animation-delay: 0.4s; }
        .hero-text span:nth-child(5) { animation-delay: 0.5s; }
        .hero-text span:nth-child(6) { animation-delay: 0.6s; }
        .hero-text span:nth-child(7) { animation-delay: 0.7s; }
        .hero-text span:nth-child(8) { animation-delay: 0.8s; }
        .hero-text span:nth-child(9) { animation-delay: 0.9s; }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Dark mode specific styles */
        .dark .bg-grid-white {
            background-image: linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                            linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
        }
        
        /* Smooth transitions */
        .transition-transform-200 {
            transition: transform 0.2s ease;
        }
        
        /* Shadow styles from original */
        .shadow-glass {
            box-shadow: 0px 2px 3px -1px rgba(0, 0, 0, 0.1),
                      0px 1px 0px 0px rgba(25, 28, 33, 0.02),
                      0px 0px 0px 1px rgba(25, 28, 33, 0.08);
        }
        
        /* Full width section styling */
        .w-screen {
            width: 100vw;
            position: relative;
            left: 50%;
            right: 50%;
            margin-left: -50vw;
            margin-right: -50vw;
        }

        /* Corrected scroll animation for seamless infinite loop */
        .animate-scroll-slow {
            animation: scroll-slow 40s linear infinite;
            display: flex;
            width: max-content; /* Important for seamless scrolling */
        }

        /* Keyframes for smooth infinite scroll with duplicate set */
        @keyframes scroll-slow {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(calc(-100% - 2rem)); /* Move by the exact width of the original set */
            }
        }

        /* Alternative: For even smoother effect with gradient edges */
        .scroll-container {
            position: relative;
            overflow: hidden;
            width: 100%;
        }

        .scroll-container::before,
        .scroll-container::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 100px;
            z-index: 10;
            pointer-events: none;
        }

        .scroll-container::before {
            left: 0;
            background: linear-gradient(to right, rgb(4, 7, 29), transparent);
        }

        .scroll-container::after {
            right: 0;
            background: linear-gradient(to left, rgb(4, 7, 29), transparent);
        }

        /* Remove any background patterns/grids */
        #workshops {
            background: rgb(0, 3, 25) !important;
        }

        #workshops::before,
        #workshops::after {
            display: none !important;
        }

        /* Optional: Add this to make sure logos are aligned properly */
        #workshops .flex {
            align-items: center;
            height: 100%;
        }

        /* For mobile responsiveness */
        @media (max-width: 768px) {
            .animate-scroll-slow {
                animation-duration: 60s; /* Slower on mobile for better readability */
            }
            
            .scroll-container::before,
            .scroll-container::after {
                width: 60px;
            }
        }

        /* Hero to Workshop transition shade */
        .hero-transition-shade {
            height: 100px;
            background: linear-gradient(to bottom, transparent, rgb(0, 3, 25));
            margin-top: -100px;
            position: relative;
            z-index: 1;
        }

        /* Section transition shade */
        .section-transition-shade {
            height: 80px;
            background: linear-gradient(to top, transparent, <?php echo $bg_darker; ?>);
            margin-bottom: -80px;
            position: relative;
            z-index: 2;
        }

        /* Testimonials specific styles */
        #testimonials {
            position: relative;
            overflow: hidden;
        }

        .scroller ul {
            padding-left: 2rem;
            padding-right: 2rem;
        }

        /* Smooth scroll animation */
        @keyframes scroll {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(calc(-50% - 1rem));
            }
        }

        .animate-scroll {
            animation: scroll var(--animation-duration, 40s) var(--animation-direction, normal) linear infinite;
        }

        .animate-scroll:hover {
            animation-play-state: paused;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .scroller ul li {
                width: 300px !important;
            }
            
            #testimonials .scroller::before,
            #testimonials .scroller::after {
                width: 60px;
            }
        }
    </style>
</head>
<body class="__className_e8ce0c dark" style="--gradient-background-start: <?php echo $gradient_start; ?>; --gradient-background-end: <?php echo $gradient_end; ?>; --first-color: 18, 113, 255; --second-color: 221, 74, 255; --third-color: 100, 220, 255; --fourth-color: 200, 50, 50; --fifth-color: 180, 180, 50; --pointer-color: 140, 100, 255; --size: 80%; --blending-value: hard-light;">
    
    <!-- Theme script from original -->
    <script>
        !function() {
            try {
                var d = document.documentElement;
                var c = d.classList;
                c.remove('light', 'dark');
                var e = localStorage.getItem('theme');
                if (e === 'system' || (!e && false)) {
                    var t = '(prefers-color-scheme: dark)';
                    var m = window.matchMedia(t);
                    if (m.media !== t || m.matches) {
                        d.style.colorScheme = 'dark';
                        c.add('dark');
                    } else {
                        d.style.colorScheme = 'light';
                        c.add('light');
                    }
                } else if (e) {
                    c.add(e || '');
                } else {
                    c.add('dark');
                }
                if (e === 'light' || e === 'dark' || !e) {
                    d.style.colorScheme = e || 'dark';
                }
            } catch (e) {}
        }()
    </script>
    
    <div class="min-h-screen bg-background flex flex-col">
        <main style="background-color: <?php echo $bg_dark; ?>">
            <div class="flex-1 w-full">
                <main class="relative bg-black-100 flex justify-center items-center flex-col overflow-hidden mx-auto sm:px-10 px-5">
                    <div class="max-w-7xl w-full">
                        
                        <!-- Navigation Bar -->
                        <div class="flex max-w-fit md:min-w-[70vw] lg:min-w-fit fixed z-[5000] top-10 inset-x-0 mx-auto px-10 py-5 rounded-lg border border-black/.1 shadow-glass items-center justify-center space-x-4 backdrop-blur-saturate bg-glass" style="opacity: 1; transform: none;">
                            <a href="/index.php" class="relative dark:text-neutral-50 items-center flex space-x-1 text-neutral-600 dark:hover:text-neutral-300 hover:text-neutral-500">
                                <span class="block sm:hidden"></span>
                                <span class="text-sm !cursor-pointer">Home</span>
                            </a>
                            <a href="<?php echo $isLoggedIn ? 'student/explore_roadmaps.php' : 'auth/login.php'; ?>" class="relative dark:text-neutral-50 items-center flex space-x-1 text-neutral-600 dark:hover:text-neutral-300 hover:text-neutral-500">
                                <span class="block sm:hidden"></span>
                                <span class="text-sm !cursor-pointer">Roadmaps</span>
                            </a>
                            <a href="<?php echo $isLoggedIn ? 'student/dashboard.php    ' : 'auth/login.php'; ?>" class="relative dark:text-neutral-50 items-center flex space-x-1 text-neutral-600 dark:hover:text-neutral-300 hover:text-neutral-500">
                                <span class="block sm:hidden"></span>
                                <span class="text-sm !cursor-pointer">Profile</span>
                            </a>
                            
                            <?php if($isLoggedIn): ?>
                                <a href="/logout" class="relative dark:text-neutral-50 items-center flex space-x-1 text-neutral-600 dark:hover:text-neutral-300 hover:text-neutral-500">
                                    <span class="block sm:hidden"></span>
                                    <span class="text-sm !cursor-pointer">Logout</span>
                                </a>
                            <?php else: ?>
                                <a href="auth/login.php" class="relative dark:text-neutral-50 items-center flex space-x-1 text-neutral-600 dark:hover:text-neutral-300 hover:text-neutral-500">
                                    <span class="block sm:hidden"></span>
                                    <span class="text-sm !cursor-pointer">Login</span>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Hero Section -->
                        <div class="pb-10 pt-36">
                            <!-- Spotlight effects -->
                            <div>
                                <svg class="animate-spotlight pointer-events-none absolute z-[1] w-[138%] lg:w-[84%] opacity-0 -top-40 -left-10 md:-left-32 md:-top-20 h-screen" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3787 2842" fill="none">
                                    <g filter="url(#filter)">
                                        <ellipse cx="1924.71" cy="273.501" rx="1924.71" ry="273.501" transform="matrix(-0.822377 -0.568943 -0.568943 0.822377 3631.88 2291.09)" fill="white" fill-opacity="0.21"></ellipse>
                                    </g>
                                    <defs>
                                        <filter id="filter" x="0.860352" y="0.838989" width="3785.16" height="2840.26" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                                            <feFlood flood-opacity="0" result="BackgroundImageFix"></feFlood>
                                            <feBlend mode="normal" in="SourceGraphic" in2="BackgroundImageFix" result="shape"></feBlend>
                                            <feGaussianBlur stdDeviation="151" result="effect1_foregroundBlur_1065_8"></feGaussianBlur>
                                        </filter>
                                    </defs>
                                </svg>
                                
                                <svg class="animate-spotlight pointer-events-none absolute z-[1] lg:w-[84%] opacity-0 h-[80vh] w-[50vw] top-10 left-full" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3787 2842" fill="none">
                                    <g filter="url(#filter)">
                                        <ellipse cx="1924.71" cy="273.501" rx="1924.71" ry="273.501" transform="matrix(-0.822377 -0.568943 -0.568943 0.822377 3631.88 2291.09)" fill="purple" fill-opacity="0.21"></ellipse>
                                    </g>
                                    <defs>
                                        <filter id="filter" x="0.860352" y="0.838989" width="3785.16" height="2840.26" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                                            <feFlood flood-opacity="0" result="BackgroundImageFix"></feFlood>
                                            <feBlend mode="normal" in="SourceGraphic" in2="BackgroundImageFix" result="shape"></feBlend>
                                            <feGaussianBlur stdDeviation="151" result="effect1_foregroundBlur_1065_8"></feGaussianBlur>
                                        </filter>
                                    </defs>
                                </svg>
                                
                                <svg class="animate-spotlight pointer-events-none absolute z-[1] lg:w-[84%] opacity-0 left-80 top-28 h-[80vh] w-[50vw]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3787 2842" fill="none">
                                    <g filter="url(#filter)">
                                        <ellipse cx="1924.71" cy="273.501" rx="1924.71" ry="273.501" transform="matrix(-0.822377 -0.568943 -0.568943 0.822377 3631.88 2291.09)" fill="blue" fill-opacity="0.21"></ellipse>
                                    </g>
                                    <defs>
                                        <filter id="filter" x="0.860352" y="0.838989" width="3785.16" height="2840.26" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                                            <feFlood flood-opacity="0" result="BackgroundImageFix"></feFlood>
                                            <feBlend mode="normal" in="SourceGraphic" in2="BackgroundImageFix" result="shape"></feBlend>
                                            <feGaussianBlur stdDeviation="151" result="effect1_foregroundBlur_1065_8"></feGaussianBlur>
                                        </filter>
                                    </defs>
                                </svg>
                            </div>
                            
                           
                            <!-- Grid background -->
                            <div class="h-screen w-full bg-hero-grid absolute top-0 left-0 flex items-center justify-center">
                                <div class="absolute pointer-events-none inset-0 flex items-center justify-center bg-black-100 [mask-image:radial-gradient(ellipse_at_center,transparent_20%,black)]"></div>
                            </div>
                            
                            <!-- Hero content -->
                            <div class="flex justify-center relative my-20 z-10">
                                <div class="max-w-[89vw] md:max-w-2xl lg:max-w-[60vw] flex flex-col items-center justify-center">
                                    <!-- Announcement badge -->
                                    <a href="https://theperfectresume.ai/" class="group relative grid overflow-hidden rounded-full px-4 py-1 shadow-[0_1000px_0_0_hsl(0_0%_20%)_inset] transition-colors duration-200 mb-8" style="background-color: rgba(255, 255, 255, 0.2);">
                                        <span>
                                            <span class="spark mask-gradient absolute inset-0 h-[100%] w-[100%] animate-flip overflow-hidden rounded-full [mask:linear-gradient(white,_transparent_50%)] before:absolute before:aspect-square before:w-[200%] before:rotate-[-90deg] before:animate-rotate before:bg-[conic-gradient(from_0deg,transparent_0_340deg,white_360deg)] before:content-[''] before:[inset:0_auto_auto_50%] before:[translate:-50%_-15%]"></span>
                                        </span>
                                        <span class="backdrop absolute inset-[1px] rounded-full bg-neutral-950 transition-colors duration-200 group-hover:bg-neutral-900"></span>
                                        <span class="h-full w-full blur-md absolute bottom-0 inset-x-0 bg-gradient-to-tr from-primary/40"></span>
                                        <span class="z-10 py-0.5 text-sm text-neutral-100 flex items-center justify-center gap-1.5">
                                            ✨ Introducing The Perfect Resume AI
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right w-4 h-4">
                                                <path d="m9 18 6-6-6-6"></path>
                                            </svg>
                                        </span>
                                    </a>
                                    
                                    <!-- Hero text -->
                                    <p class="uppercase tracking-widest text-s text-center text-blue-100 max-w-80 pt-5">One stop destination to</p>
                                    <div class="font-bold text-center text-[40px] md:text-5xl lg:text-6xl">
                                        <div class="my-4">
                                            <div class="dark:text-white text-black leading-snug tracking-wide">
                                                <div class="hero-text">
                                                    <span class="dark:text-white text-black opacity-0" style="opacity: 1;">Beginner </span>
                                                    <span class="dark:text-white text-black opacity-0" style="opacity: 1;">Friendly </span>
                                                    <span class="dark:text-white text-black opacity-0" style="opacity: 1;">Roadmaps </span>
                                                    <span class="dark:text-white text-black opacity-0" style="opacity: 1;">&amp; </span>
                                                    <span class="text-purple opacity-0" style="opacity: 1;">Resources </span>
                                                    <span class="text-purple opacity-0" style="opacity: 1;">You </span>
                                                    <span class="text-purple opacity-0" style="opacity: 1;">Should </span>
                                                    <span class="text-purple opacity-0" style="opacity: 1;">Start </span>
                                                    <span class="text-purple opacity-0" style="opacity: 1;">With </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- CTA Button -->
                                    <a href="<?php echo $isLoggedIn ? '/roadmap' : '/register'; ?>">
                                        <button class="relative inline-flex h-12 w-full md:mt-10 overflow-hidden rounded-lg p-[1px] focus:outline-none animated-button">
                                            <span class="absolute inset-[-1000%] animate-[spin_2s_linear_infinite] bg-[conic-gradient(from_90deg_at_50%_50%,#E2CBFF_0%,#393BB2_50%,#E2CBFF_100%)]"></span>
                                            <span class="inline-flex h-full w-full cursor-pointer items-center justify-center rounded-lg bg-slate-950 px-7 text-sm font-medium text-white backdrop-blur-3xl gap-2">
                                                Browse Roadmaps and Resources
                                                <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 448 512" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M429.6 92.1c4.9-11.9 2.1-25.6-7-34.7s-22.8-11.9-34.7-7l-352 144c-14.2 5.8-22.2 20.8-19.3 35.8s16.1 25.8 31.4 25.8l176 0 0 176c0 15.3 10.8 28.4 25.8 31.4s30-5.1 35.8-19.3l144-352z"></path>
                                                </svg>
                                            </span>
                                        </button>
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Hero to Workshop transition shade -->
                            <div class="hero-transition-shade"></div>
                        </div>
                        
                        <!-- Workshops Section - Full width -->
                        <section id="workshops" class="py-10 w-screen relative left-1/2 right-1/2 -mx-[50vw]">
                            <div class="w-full px-4">
                                <p class="text-center text-blue-100 text-sm md:text-base lg:text-lg mb-10 tracking-wide font-medium">
                                    We've hosted workshops at
                                </p>
                                
                                <!-- Full width logo strip container -->
                                <div class="relative w-full overflow-hidden scroll-container">
                                    <!-- Logo container - full width scroll with duplicate set -->
                                    <div class="flex items-center space-x-16 md:space-x-24 lg:space-x-32 animate-scroll-slow hover:[animation-play-state:paused] px-4">
                                        <?php
                                        $logos = [
                                            ['src' => 'path/masters_union.png', 'alt' => "Masters' Union"],
                                            ['src' => 'path/nas.png', 'alt' => 'NASSUMMIT'],
                                            ['src' => 'path/newtonschool.png', 'alt' => 'Newton School'],
                                            ['src' => 'path/rotaract.png', 'alt' => 'Rotaract'],
                                            ['src' => 'path/iitd.png', 'alt' => 'IIT Delhi'],
                                            ['src' => 'path/srcc.png', 'alt' => 'SRCC'],
                                            ['src' => 'path/iit_ropar.png', 'alt' => 'IIT Ropar'],
                                            ['src' => 'path/masai.png', 'alt' => 'Masai School'],
                                            ['src' => 'path/iim-kashipur.png', 'alt' => 'IIM Kashipur'],
                                            ['src' => 'path/galgotias_university.png', 'alt' => 'Galgotias University'],
                                            ['src' => 'path/gl_bajaj.png', 'alt' => 'GL Bajaj'],
                                        ];
                                        
                                        // Display logos twice for seamless loop
                                        for ($i = 0; $i < 2; $i++) {
                                            foreach ($logos as $logo) {
                                                echo '
                                                <div class="flex-shrink-0 h-14 md:h-16 lg:h-20 w-auto">
                                                    <img src="' . $logo['src'] . '" 
                                                        alt="' . htmlspecialchars($logo['alt']) . '" 
                                                        class="h-full w-auto object-contain filter brightness-0 invert opacity-90 hover:opacity-100 transition-opacity duration-300"
                                                        loading="lazy">
                                                </div>';
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </section>
                        
                        <!-- About/Features Section -->
                        <section id="about">
                            <div class="grid grid-cols-1 md:grid-cols-6 lg:grid-cols-5 md:grid-row-7 gap-4 lg:gap-8 mx-auto w-full pt-20">
                                
                                <!-- Feature 1 -->
                                <div class="row-span-1 relative overflow-hidden rounded-3xl border border-white/[0.1] group/bento hover:shadow-xl transition duration-200 shadow-input dark:shadow-none justify-between flex flex-col space-y-4 lg:col-span-3 md:col-span-6 md:row-span-4 lg:min-h-[100vh] md:min-h-[80vh] min-h-[60vh]" style="background: <?php echo $bg_darker; ?>;">
                                    <div class="false h-full">
                                        <div class="w-full h-full absolute">
                                            <div class="w-full h-full bg-gradient-to-br from-purple-900/20 to-blue-900/20 rounded-3xl">
                                                <img src = "path/road.png" >
                                            </div>
                                        </div>
                                        <div class="absolute right-0 -bottom-5 false "></div>
                                        <div class="justify-end group-hover/bento:translate-x-2 transition duration-200 relative md:h-full min-h-40 flex flex-col p-5 lg:p-10">
                                            <div class="font-sans font-extralight md:max-w-32 md:text-xs lg:text-base text-sm text-[#C1C2D3] z-10">Our Roadmaps</div>
                                            <div class="font-sans text-lg lg:text-3xl max-w-96 font-bold z-10">Get end-to-end roadmaps to get Interview Calls faster</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Feature 2 -->
                                <div class="row-span-1 relative overflow-hidden rounded-3xl border border-white/[0.1] group/bento hover:shadow-xl transition duration-200 shadow-input dark:shadow-none justify-between flex flex-col space-y-4 lg:col-span-2 md:col-span-3 md:row-span-2" style="background: <?php echo $bg_darker; ?>;">
                                    <div class="false h-full">
                                        <div class="w-full h-full absolute"></div>
                                        <div class="absolute right-0 -bottom-5 false "></div>
                                        <div class="justify-start md:text-center group-hover/bento:translate-x-2 transition duration-200 relative md:h-full min-h-40 flex flex-col p-5 lg:p-10">
                                            <div class="font-sans font-extralight md:max-w-32 md:text-xs lg:text-base text-sm text-[#C1C2D3] z-10"></div>
                                            <div class="font-sans text-lg lg:text-3xl max-w-96 font-bold z-10">Experts form world wide.</div>
                                            <div class="flex items-center justify-center absolute -left-5 top-36 md:top-40 w-full h-full">
                                                <div class="max-w-7xl mx-auto w-full relative overflow-hidden h-96 px-4">
                                                    <div class="absolute w-full bottom-0 inset-x-0 h-40 bg-gradient-to-b pointer-events-none select-none from-transparent dark:to-black to-white z-40"></div>
                                                    <div class="absolute w-full h-72 md:h-full z-10">
                                                        <div style="position: relative; width: 100%; height: 100%; overflow: hidden; pointer-events: auto; touch-action: none;">
                                                            <div style="width: 100%; height: 100%;">
                                                                <!-- Placeholder for 3D animation -->
                                                                <div class="w-full h-full bg-gradient-to-br from-purple-900/30 to-blue-900/30 rounded-xl flex items-center justify-center">
                                                                    <div class="text-center">
                                                                        <div class="text-4xl mb-4">🌍</div>
                                                                        <p class="text-gray-300">Global Expert Network</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Feature 3 -->
                                <div class="row-span-1 relative overflow-hidden rounded-3xl border border-white/[0.1] group/bento hover:shadow-xl transition duration-200 shadow-input dark:shadow-none justify-between flex flex-col space-y-4 lg:col-span-2 md:col-span-3 md:row-span-2" style="background: <?php echo $bg_darker; ?>;">
                                    <div class="false h-full">
                                        <div class="w-full h-full absolute">
                                            <div class="w-full h-full bg-gradient-to-br from-green-900/20 to-emerald-900/20 rounded-3xl"></div>
                                        </div>
                                        <div class="absolute right-0 -bottom-5 false "></div>
                                        <div class="justify-end group-hover/bento:translate-x-2 transition duration-200 relative md:h-full min-h-40 flex flex-col p-5 lg:p-10">
                                            <div class="font-sans font-extralight md:max-w-32 md:text-xs lg:text-base text-sm text-[#C1C2D3] z-10">Profile Hub</div>
                                            <div class="font-sans text-lg lg:text-3xl max-w-96 font-bold z-10">Track Your Progress & Profile</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Feature 4 -->
                                <div class="row-span-1 relative overflow-hidden rounded-3xl border border-white/[0.1] group/bento hover:shadow-xl transition duration-200 shadow-input dark:shadow-none justify-between flex flex-col space-y-4 md:col-span-3" style="background: <?php echo $bg_darker; ?>;">
                                    
                                <div class="false h-full">
                                        
                                        <div class="w-full h-full absolute">
                                            <div class="w-full h-full bg-gradient-to-br from-orange-900/20 to-red-900/20 rounded-3xl"></div>
                                        </div>
                                        <div class="absolute right-0 -bottom-5 w-full opacity-80 ">
                                            <img src = "path/link.png" >
                                        </div>
                                        <div class="justify-center md:justify-start lg:justify-center group-hover/bento:translate-x-2 transition duration-200 relative md:h-full min-h-40 flex flex-col p-5 lg:p-10">
                                            
                                            <div class="font-sans font-extralight md:max-w-32 md:text-xs lg:text-base text-sm text-[#C1C2D3] z-10">Upcoming Live Session</div>
                                            <div class="font-sans text-lg lg:text-3xl max-w-96 font-bold z-10">Linkedin Secret To Get Interview Refferals!</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Feature 5 - Animated Gradient -->
                                <div class="row-span-1 relative overflow-hidden rounded-3xl border border-white/[0.1] group/bento hover:shadow-xl transition duration-200 shadow-input dark:shadow-none justify-between flex flex-col space-y-4 lg:col-span-2 md:col-span-3 md:row-span-1" style="background: <?php echo $bg_darker; ?>;">
                                    <div class="flex justify-center h-full">
                                        <div class="w-full h-full absolute"></div>
                                        <div class="absolute right-0 -bottom-5 false "></div>
                                        <div class="w-full h-full absolute overflow-hidden top-0 left-0 bg-[linear-gradient(40deg,var(--gradient-background-start),var(--gradient-background-end))]">
                                            <svg class="hidden">
                                                <defs>
                                                    <filter id="blurMe">
                                                        <feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur"></feGaussianBlur>
                                                        <feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 18 -8" result="goo"></feColorMatrix>
                                                        <feBlend in="SourceGraphic" in2="goo"></feBlend>
                                                    </filter>
                                                </defs>
                                            </svg>
                                            <div class="">
                                                <div class="absolute z-50 inset-0 flex items-center justify-center text-white font-bold px-4 pointer-events-none text-3xl text-center md:text-4xl lg:text-7xl"></div>
                                            </div>
                                            <div class="gradients-container h-full w-full blur-lg">
                                                <div class="absolute [background:radial-gradient(circle_at_center,_var(--first-color)_0,_var(--first-color)_50%)_no-repeat] [mix-blend-mode:var(--blending-value)] w-[var(--size)] h-[var(--size)] top-[calc(50%-var(--size)/2)] left-[calc(50%-var(--size)/2)] [transform-origin:center_center] animate-first opacity-100"></div>
                                                <div class="absolute [background:radial-gradient(circle_at_center,_rgba(var(--second-color),_0.8)_0,_rgba(var(--second-color),_0)_50%)_no-repeat] [mix-blend-mode:var(--blending-value)] w-[var(--size)] h-[var(--size)] top-[calc(50%-var(--size)/2)] left-[calc(50%-var(--size)/2)] [transform-origin:calc(50%-400px)] animate-second opacity-100"></div>
                                                <div class="absolute [background:radial-gradient(circle_at_center,_rgba(var(--third-color),_0.8)_0,_rgba(var(--third-color),_0)_50%)_no-repeat] [mix-blend-mode:var(--blending-value)] w-[var(--size)] h-[var(--size)] top-[calc(50%-var(--size)/2)] left-[calc(50%-var(--size)/2)] [transform-origin:calc(50%+400px)] animate-third opacity-100"></div>
                                                <div class="absolute [background:radial-gradient(circle_at_center,_rgba(var(--fourth-color),_0.8)_0,_rgba(var(--fourth-color),_0)_50%)_no-repeat] [mix-blend-mode:var(--blending-value)] w-[var(--size)] h-[var(--size)] top-[calc(50%-var(--size)/2)] left-[calc(50%-var(--size)/2)] [transform-origin:calc(50%-200px)] animate-fourth opacity-70"></div>
                                                <div class="absolute [background:radial-gradient(circle_at_center,_rgba(var(--fifth-color),_0.8)_0,_rgba(var(--fifth-color),_0)_50%)_no-repeat] [mix-blend-mode:var(--blending-value)] w-[var(--size)] h-[var(--size)] top-[calc(50%-var(--size)/2)] left-[calc(50%-var(--size)/2)] [transform-origin:calc(50%-800px)_calc(50%+800px)] animate-fifth opacity-100"></div>
                                                <div class="absolute [background:radial-gradient(circle_at_center,_rgba(var(--pointer-color),_0.8)_0,_rgba(var(--pointer-color),_0)_50%)_no-repeat] [mix-blend-mode:var(--blending-value)] w-full h-full -top-1/2 -left-1/2 opacity-70" style="transform: translate(0px, 0px);"></div>
                                            </div>
                                        </div>
                                        <div class="justify-center md:max-w-full max-w-90 text-center group-hover/bento:translate-x-2 transition duration-200 relative md:h-full min-h-40 flex flex-col p-5 lg:p-10">
                                            <div class="font-sans font-extralight md:max-w-32 md:text-xs lg:text-base text-sm text-[#C1C2D3] z-10"></div>
                                            <div class="font-sans text-lg lg:text-3xl max-w-96 font-bold z-10">Do you want to Join the exclusive YourRoadmap Community?</div>
                                            <div class="mt-5 relative">
                                                <div class="absolute -bottom-5 right-0 block">
                                                    <!-- Placeholder for animation -->
                                                    <div title="" role="button" aria-label="animation" tabindex="0" style="width: 400px; height: 200px; overflow: hidden; margin: 0px auto; outline: none;">
                                                        <div class="w-full h-full flex items-center justify-center">
                                                            <div class="text-6xl">👥</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <a href="<?php echo $isLoggedIn ? '/community' : '/register'; ?>">
                                                    <button class="relative inline-flex h-12 w-full md:mt-10 overflow-hidden rounded-lg p-[1px] focus:outline-none animated-button">
                                                        <span class="absolute inset-[-1000%] animate-[spin_2s_linear_infinite] bg-[conic-gradient(from_90deg_at_50%_50%,#E2CBFF_0%,#393BB2_50%,#E2CBFF_100%)]"></span>
                                                        <span class="inline-flex h-full w-full cursor-pointer items-center justify-center rounded-lg bg-slate-950 px-7 text-sm font-medium text-white backdrop-blur-3xl gap-2 !bg-[#161A31]">
                                                            <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 496 512" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M248 104c-53 0-96 43-96 96s43 96 96 96 96-43 96-96-43-96-96-96zm0 144c-26.5 0-48-21.5-48-48s21.5-48 48-48 48 21.5 48 48-21.5 48-48 48zm0-240C111 8 0 119 0 256s111 248 248 248 248-111 248-248S385 8 248 8zm0 448c-49.7 0-95.1-18.3-130.1-48.4 14.9-23 40.4-38.6 69.6-39.5 20.8 6.4 40.6 9.6 60.5 9.6s39.7-3.1 60.5-9.6c29.2 1 54.7 16.5 69.6 39.5-35 30.1-80.4 48.4-130.1 48.4zm162.7-84.1c-24.4-31.4-62.1-51.9-105.1-51.9-10.2 0-26 9.6-57.6 9.6-31.5 0-47.4-9.6-57.6-9.6-42.9 0-80.6 20.5-105.1 51.9C61.9 339.2 48 299.2 48 256c0-110.3 89.7-200 200-200s200 89.7 200 200c0 43.2-13.9 83.2-37.3 115.9z"></path>
                                                            </svg>
                                                            Join now
                                                        </span>
                                                    </button>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                        
                        <!-- Testimonials Section -->
                        <section id="testimonials" class="py-20">
                            <div class="flex flex-col items-center max-lg:mt-10">
                                <h1 class="heading text-center mb-10">What Our <span class="text-purple">Community</span> Says</h1>
                                
                                <div class="w-full relative">
                                    <!-- Left fade gradient -->
                                    <div class="absolute left-0 top-0 bottom-0 w-32 bg-gradient-to-r from-[#0f172a] to-transparent z-30 pointer-events-none"></div>
                                    
                                    <!-- Right fade gradient -->
                                    <div class="absolute right-0 top-0 bottom-0 w-32 bg-gradient-to-l from-[#0f172a] to-transparent z-30 pointer-events-none"></div>
                                    
                                    <!-- Scroller container -->
                                    <div class="scroller relative z-20 w-full overflow-hidden">
                                        <ul class="flex min-w-full shrink-0 gap-6 py-4 w-max flex-nowrap animate-scroll hover:[animation-play-state:paused]" style="--animation-duration: 40s; --animation-direction: normal;">
                                            <?php
                                            $testimonials = [
                                                [
                                                    'name' => 'Geetha Reddy',
                                                    'text' => 'Earlier I was in a confusion to update my resume I tried ai tools but now I got proper directions by Mr. Gaurav ghai. Thank you for organising such workshops.'
                                                ],
                                                [
                                                    'name' => 'Razika',
                                                    'text' => 'The session is so practical & much connected with me. Thank you for providing the roadmap to start the trading journey. Appreciate all the efforts you put in & sharing the valuable knowledge with us.'
                                                ],
                                                [
                                                    'name' => 'Abhipsa Sahu',
                                                    'text' => 'Gaurav has deep insight in what he speaks and how he instills awareness in folks reaching out to him or via posts or content that he creates. He is definitely on top of the trend.'
                                                ],
                                                [
                                                    'name' => 'Shankarling Shahapeti',
                                                    'text' => 'It was insightful session. As a fresher or started recently IT journey having lot of questions in mind , Gaurav answered all questions ,that made a sense to me.'
                                                ],
                                                [
                                                    'name' => 'Himanshu Sharma',
                                                    'text' => 'He is a great listener. He patiently listened to my question and addressed each question according to my expectation. The whole aura of the call was helpful and insightful'
                                                ]
                                            ];
                                            
                                            // Display each testimonial twice for infinite scroll
                                            for ($i = 0; $i < 2; $i++) {
                                                foreach ($testimonials as $testimonial) {
                                                    echo '<li class="w-[350px] max-w-[350px] relative rounded-2xl border border-slate-800 p-6 flex-shrink-0" style="background: ' . $bg_darker . ';">
                                                        <div class="flex flex-col h-full">
                                                            <div class="mb-4">
                                                                <svg class="w-8 h-8 text-purple-500 mb-2" fill="currentColor" viewBox="0 0 24 24">
                                                                    <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/>
                                                                </svg>
                                                            </div>
                                                            <p class="text-gray-300 text-sm md:text-md leading-relaxed flex-grow mb-6">' . $testimonial['text'] . '</p>
                                                            <div class="mt-auto pt-4 border-t border-slate-800">
                                                                <span class="text-lg font-bold text-white">' . $testimonial['name'] . '</span>
                                                            </div>
                                                        </div>
                                                    </li>';
                                                }
                                            }
                                            ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </section>
                        
                        <!-- Gallery Section -->
                        <section id="about" style="position: relative; z-index: 10;">
                            <h1 class="heading text-center">Pictures from our <span class="text-purple">offline Meetups</span></h1>
                            
                            <!-- Section transition shade -->
                            <div class="section-transition-shade"></div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-6 lg:grid-cols-5 md:grid-row-7 gap-4 lg:gap-8 mx-auto w-full py-20">
                                
                                <!-- Gallery items with same structure as original -->
                                <?php
                                $gallery_items = [
                                    ['col_span' => 'lg:col-span-3 md:col-span-6 md:row-span-4 lg:min-h-[60vh]', 'color' => 'from-purple-900/30 to-pink-900/30'],
                                    ['col_span' => 'lg:col-span-2 md:col-span-3 md:row-span-2', 'color' => 'from-blue-900/30 to-cyan-900/30'],
                                    ['col_span' => 'lg:col-span-2 md:col-span-3 md:row-span-2', 'color' => 'from-green-900/30 to-emerald-900/30'],
                                    ['col_span' => 'lg:col-span-2 md:col-span-3 md:row-span-1', 'color' => 'from-orange-900/30 to-amber-900/30'],
                                    ['col_span' => 'md:col-span-3 md:row-span-2', 'color' => 'from-red-900/30 to-rose-900/30'],
                                ];
                                
                                foreach ($gallery_items as $index => $item) {
                                    echo '<div class="row-span-1 relative overflow-hidden rounded-3xl border border-white/[0.1] group/bento hover:shadow-xl transition duration-200 shadow-input dark:shadow-none justify-between flex flex-col space-y-1 ' . $item['col_span'] . '" style="background: ' . $bg_darker . ';">
                                        <div class="false h-full">
                                            <div class="w-full h-full absolute">
                                                <div class="w-full h-full bg-gradient-to-br ' . $item['color'] . ' rounded-3xl flex items-center justify-center">
                                                    <div class="text-6xl text-white/50">
                                                        📸
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="justify-end group-hover/bento:translate-x-2 transition duration-200 relative md:h-full min-h-40 flex flex-col p-5 lg:p-10">
                                                <div class="font-sans font-extralight md:max-w-42 md:text-xs lg:text-base text-sm text-[#C1C2D3] z-10"></div>
                                                <div class="font-sans text-lg lg:text-3xl max-w-96 font-bold z-10"></div>
                                            </div>
                                        </div>
                                    </div>';
                                }
                                ?>
                                
                                <!-- Community CTA -->
                                <div class="row-span-1 relative overflow-hidden rounded-3xl border border-white/[0.1] group/bento hover:shadow-xl transition duration-200 shadow-input dark:shadow-none justify-between flex flex-col space-y-1 lg:col-span-2 md:col-span-3 md:row-span-1" style="background: <?php echo $bg_darker; ?>;">
                                    <div class="flex justify-center h-full">
                                        <div class="w-full h-full absolute"></div>
                                        <div class="w-full h-full absolute overflow-hidden top-0 left-0 bg-[linear-gradient(40deg,var(--gradient-background-start),var(--gradient-background-end))]">
                                            <svg class="hidden">
                                                <defs>
                                                    <filter id="blurMe">
                                                        <feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur"></feGaussianBlur>
                                                        <feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 18 -8" result="goo"></feColorMatrix>
                                                        <feBlend in="SourceGraphic" in2="goo"></feBlend>
                                                    </filter>
                                                </defs>
                                            </svg>
                                            <div class="gradients-container h-full w-full blur-lg">
                                                <div class="absolute [background:radial-gradient(circle_at_center,_var(--first-color)_0,_var(--first-color)_50%)_no-repeat] [mix-blend-mode:var(--blending-value)] w-[var(--size)] h-[var(--size)] top-[calc(50%-var(--size)/2)] left-[calc(50%-var(--size)/2)] [transform-origin:center_center] animate-first opacity-100"></div>
                                                <div class="absolute [background:radial-gradient(circle_at_center,_rgba(var(--second-color),_0.8)_0,_rgba(var(--second-color),_0)_50%)_no-repeat] [mix-blend-mode:var(--blending-value)] w-[var(--size)] h-[var(--size)] top-[calc(50%-var(--size)/2)] left-[calc(50%-var(--size)/2)] [transform-origin:calc(50%-400px)] animate-second opacity-100"></div>
                                                <div class="absolute [background:radial-gradient(circle_at_center,_rgba(var(--third-color),_0.8)_0,_rgba(var(--third-color),_0)_50%)_no-repeat] [mix-blend-mode:var(--blending-value)] w-[var(--size)] h-[var(--size)] top-[calc(50%-var(--size)/2)] left-[calc(50%-var(--size)/2)] [transform-origin:calc(50%+400px)] animate-third opacity-100"></div>
                                            </div>
                                        </div>
                                        <div class="justify-center md:max-w-full max-w-60 text-center group-hover/bento:translate-x-2 transition duration-200 relative md:h-full min-h-40 flex flex-col p-5 lg:p-10">
                                            <div class="font-sans font-extralight md:max-w-42 md:text-xs lg:text-base text-sm text-[#C1C2D3] z-10"></div>
                                            <div class="font-sans text-lg lg:text-3xl max-w-96 font-bold z-10">Do you want us to conduct Next workshop/session at your college?</div>
                                            <div class="mt-5 relative">
                                                <div class="absolute -bottom-5 right-0 block">
                                                    <!-- Animation placeholder -->
                                                    <div title="" role="button" aria-label="animation" tabindex="0" style="width: 400px; height: 200px; overflow: hidden; margin: 0px auto; outline: none;">
                                                        <div class="w-full h-full flex items-center justify-center">
                                                            <div class="text-6xl">🏫</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button onclick="copyEmail()" class="relative inline-flex h-12 w-full md:mt-10 overflow-hidden rounded-lg p-[1px] focus:outline-none animated-button">
                                                    <span class="absolute inset-[-1000%] animate-[spin_2s_linear_infinite] bg-[conic-gradient(from_90deg_at_50%_50%,#E2CBFF_0%,#393BB2_50%,#E2CBFF_100%)]"></span>
                                                    <span class="inline-flex h-full w-full cursor-pointer items-center justify-center rounded-lg bg-slate-950 px-7 text-sm font-medium text-white backdrop-blur-3xl gap-2 !bg-[#161A31]">
                                                        <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg">
                                                            <rect width="336" height="336" x="128" y="128" fill="none" stroke-linejoin="round" stroke-width="32" rx="57" ry="57"></rect>
                                                            <path fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="m383.5 128 .5-24a56.16 56.16 0 0 0-56-56H112a64.19 64.19 0 0 0-64 64v216a56.16 56.16 0 0 0 56 56h24"></path>
                                                        </svg>
                                                        Copy my email address
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                        
                    </div>
                </main>
            </div>
        </main>
                        <!-- Footer Section -->
        <footer class="w-full pt-20 pb-10 relative overflow-hidden" id="contact">
                            <!-- Check pattern background -->
                <div class="absolute inset-0 bg-hero-grid"></div>
                        <div class="absolute pointer-events-none inset-0 flex items-center justify-center bg-black-100 [mask-image:radial-gradient(ellipse_at_center,transparent_20%,black)]"></div>
                            
                            <div class="relative z-10 max-w-4xl mx-auto px-4">
                                <!-- Main content -->
                                <div class="text-center mb-12">
                            <div class="flex items-center justify-center space-x-2 mb-6">
                        <div class="w-10 h-10 bg-gradient-to-r from-purple-600 to-blue-600 rounded-xl"></div>
                    <span class="text-2xl font-bold text-white">YourRoadmap</span>
                </div>
                                    
                <h2 class="text-3xl font-bold text-white mb-4">Your Career Development Partner</h2>
                <p class="text-gray-300 mb-8 max-w-2xl mx-auto">
                    Beginner-friendly roadmaps, curated resources, and career guidance to help you succeed.
                </p>
                                    
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="<?php echo $isLoggedIn ? '/roadmap' : '/register'; ?>">
                            <button class="px-8 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold rounded-lg hover:opacity-90 transition-opacity duration-200 shadow-lg">
                                Start Learning Now
                            </button>
                        </a>
                        <?php if(!$isLoggedIn): ?>
                        <a href="/register.php">
                        <button class="px-8 py-3 border border-white/20 text-white font-semibold rounded-lg hover:bg-white/10 transition-colors duration-200">
                            Login to Your Profile
                        </button>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                                
                                <!-- Links and Copyright -->
                <div class="pt-8 border-t border-white/10">
                    <div class="flex flex-col md:flex-row justify-between items-center">
                        <div class="mb-4 md:mb-0">
                            <div class="flex space-x-6">
                                <a href="/" class="text-gray-400 hover:text-white transition-colors">Home</a>
                                <a href="<?php echo $isLoggedIn ? '/roadmap' : '/register'; ?>" class="text-gray-400 hover:text-white transition-colors">Roadmaps</a>
                                <a href="<?php echo $isLoggedIn ? '/profile' : '/register'; ?>" class="text-gray-400 hover:text-white transition-colors">Profile</a>
                                <a href="<?php echo $isLoggedIn ? '/sessions' : '/register'; ?>" class="text-gray-400 hover:text-white transition-colors">Sessions</a>
                            </div>
                        </div>
                                        
                        <div class="text-gray-500 text-sm">
                            &copy; <?php echo date('Y'); ?> YourRoadmap. All rights reserved.
                        </div>
                    </div>
                </div>
            </div>
        </footer>
                            
                
    </div>
    
    <!-- JavaScript for interactivity -->
    <script>
        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate hero text
            const heroTextSpans = document.querySelectorAll('.hero-text span');
            heroTextSpans.forEach((span, index) => {
                span.style.animationDelay = `${0.1 * (index + 1)}s`;
                span.style.opacity = '1';
            });
            
            // Handle navigation hover effects
            const navLinks = document.querySelectorAll('nav a');
            navLinks.forEach(link => {
                link.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                link.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Handle bento grid hover effects
            const bentoCards = document.querySelectorAll('.group\\/bento');
            bentoCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Handle animated buttons
            const animatedButtons = document.querySelectorAll('.animated-button');
            animatedButtons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
            
            // Infinite scroll pause on hover
            const scrollers = document.querySelectorAll('.scroller');
            scrollers.forEach(scroller => {
                const ul = scroller.querySelector('ul');
                scroller.addEventListener('mouseenter', function() {
                    ul.style.animationPlayState = 'paused';
                });
                scroller.addEventListener('mouseleave', function() {
                    ul.style.animationPlayState = 'running';
                });
            });
        });
        
        // Theme toggle (simplified from original)
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = localStorage.getItem('theme') || 'dark';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.classList.remove(currentTheme);
            html.classList.add(newTheme);
            html.style.colorScheme = newTheme;
            localStorage.setItem('theme', newTheme);
        }
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return;
                
                e.preventDefault();
                const targetElement = document.querySelector(href);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 100,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Copy email functionality
        function copyEmail() {
            const email = 'yourroadmap@gmail.com';
            navigator.clipboard.writeText(email).then(() => {
                // Show notification
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50 animate-fade-in';
                notification.textContent = 'Email copied to clipboard: ' + email;
                document.body.appendChild(notification);
                
                // Remove notification after 3 seconds
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }).catch(err => {
                console.error('Failed to copy email: ', err);
                alert('Failed to copy email. Please copy manually: ' + email);
            });
        }
        
        // Add fade-in animation for notification
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fade-in {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .animate-fade-in {
                animation: fade-in 0.3s ease-out;
            }
        `;
        document.head.appendChild(style);
        
        // Login check for protected links
        document.addEventListener('click', function(e) {
            // Check if clicked element is a protected link
            const protectedLinks = document.querySelectorAll('a[href*="/roadmap"], a[href*="/profile"], a[href*="/sessions"], a[href*="/community"]');
            const target = e.target.closest('a');
            
            if (target && Array.from(protectedLinks).includes(target)) {
                <?php if(!$isLoggedIn): ?>
                e.preventDefault();
                const href = target.getAttribute('href');
                // Redirect to register page
                window.location.href = '/register';
                <?php endif; ?>
            }
        });
    </script>
</body>
</html>