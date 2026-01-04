<?php
// auth/middleware.php

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define BASE_URL and url() function if not defined
if (!defined('BASE_URL')) {
    // FIX THE TYPO: 'fianlroadmap' to 'finalroadmap'
    define('BASE_URL', '/fianlroadmap');
}

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

/**
 * Redirects to a specified path.
 * @param string $path The path to redirect to.
 */
function redirect(string $path): void {
    header("Location: " . url($path));
    exit();
}

/**
 * Ensures the user is authenticated and has the 'admin' role.
 * Accepts both database admins and fixed admin.
 */
function requireAdmin(): void {
    if (!isset($_SESSION['user_id'])) {
        redirect('auth/login.php');
    }
    
    // Check if user is admin (either fixed admin or database admin)
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        redirect('auth/login.php');
    }
}

/**
 * Ensures the user is authenticated and has the 'instructor' role.
 */
function requireInstructor(): void {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
        redirect('auth/login.php');
    }
}

/**
 * Ensures the user is authenticated and has the 'student' role.
 */
function requireStudent(): void {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        redirect('auth/login.php');
    }
}

/**
 * Ensures the user is authenticated, regardless of role.
 */
function requireAuth(): void {
    if (!isset($_SESSION['user_id'])) {
        redirect('auth/login.php');
    }
}

/**
 * Check if current user is the fixed admin
 */
function isFixedAdmin(): bool {
    return isset($_SESSION['is_fixed_admin']) && $_SESSION['is_fixed_admin'] === true;
}
?>