<?php
// toast_helper.php

function setToast($message, $type = 'info') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['toast_message'] = $message;
    $_SESSION['toast_type'] = $type;
}

function displayToast() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['toast_message'])) {
        $message = $_SESSION['toast_message'];
        $type = $_SESSION['toast_type'] ?? 'info';
        
        // Map PHP toast types to JS toast types
        $toast_types = [
            'success' => 'success',
            'error' => 'error',
            'warning' => 'warning',
            'info' => 'info'
        ];
        
        $js_type = $toast_types[$type] ?? 'info';
        
        echo "<script>";
        echo "Toast." . htmlspecialchars($js_type) . "('" . addslashes($message) . "');";
        echo "</script>";
        
        // Clear the toast after displaying
        unset($_SESSION['toast_message'], $_SESSION['toast_type']);
    }
}