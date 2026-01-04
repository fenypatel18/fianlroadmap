<?php
// helpers/url_helper.php

/**
 * Generate absolute URL with base path
 * @param string $path The relative path
 * @return string Full URL
 */
function url($path = '') {
    // Remove leading/trailing slashes
    $path = ltrim($path, '/');
    
    // Start with base URL
    $url = BASE_URL;
    
    // Add path if not empty
    if (!empty($path)) {
        $url .= '/' . $path;
    }
    
    return $url;
}

/**
 * Redirect to a URL
 * @param string $path The path to redirect to
 */
function redirect_to($path) {
    header('Location: ' . url($path));
    exit();
}

/**
 * Get current URL
 * @return string Current URL
 */
function current_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}
?>