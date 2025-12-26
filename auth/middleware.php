<?php
// auth/middleware.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Redirects to a specified path.
 * @param string $path The path to redirect to.
 */
function redirect(string $path): void
{
    header("Location: {$path}");
    exit();
}

/**
 * Ensures the user is authenticated and has the 'admin' role.
 * Redirects to the login page if not authorized.
 */
function requireAdmin(): void
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        // You can also set a flash message here to show on the login page
        // $_SESSION['flash_error'] = 'You must be an admin to access this page.';
        redirect('/login.php');
    }
}

/**
 * Ensures the user is authenticated and has the 'instructor' role.
 * Redirects to the login page if not authorized.
 */
function requireInstructor(): void
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
        redirect('/login.php');
    }
    
    // You might also want to handle the 'first_login' case here or in the router.
}

/**
 * Ensures the user is authenticated and has the 'student' role.
 * Redirects to the login page if not authorized.
 */
function requireStudent(): void
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        redirect('/login.php');
    }
}

/**
 * Ensures the user is authenticated, regardless of role.
 * Redirects to the login page if not.
 */
function requireAuth(): void
{
    if (!isset($_SESSION['user_id'])) {
         redirect('/login.php');
    }
}

?>
