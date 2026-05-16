<?php
/**
 * Centralized Session Timeout Logic
 * Enforces a strict 15-minute inactivity policy.
 */

// 15-Minute Session Timeout (900 seconds)
$timeout_duration = 900;

// Only enforce timeout for non-Admin users
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'Admin') {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        // Session expired
        session_unset();
        session_destroy();
        
        header("Location: login.php?msg=timeout");
        exit;
    }
}

// Update last activity time for every new request
$_SESSION['last_activity'] = time();
?>
