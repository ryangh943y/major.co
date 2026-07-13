<?php
// backend/security_helper.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate a cryptographically secure CSRF token if one does not exist.
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get current session CSRF token.
 */
function getCsrfToken() {
    return generateCsrfToken();
}

/**
 * Validate standard posted CSRF token.
 */
function validateCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Outputs a hidden input field containing the CSRF token.
 */
function csrfInput() {
    $token = getCsrfToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}
?>
