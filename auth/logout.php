<?php
// auth/logout.php

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Store logout message in a temporary variable
$logout_message = "You have been successfully logged out.";

// Clear all session variables
$_SESSION = array();

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Start a new session to show logout message
session_start();
$_SESSION['logout_message'] = $logout_message;

// Clear any other cookies
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

// Redirect to login page
header('Location: /fianlroadmap/auth/login.php');
exit();
?>