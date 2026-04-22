<?php
/**
 * Centralized Session Timeout Logic
 * Enforces a strict 15-minute inactivity policy.
 */

// 15-Minute Session Timeout (900 seconds)
$timeout_duration = 900;

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    // Session expired
    session_unset();
    session_destroy();
    
    // Clear the active session log in database if applicable
    // (Optional: You could also mark the logout_time in activity_logs here if you have the login_log_id)
    
    header("Location: login.php?msg=timeout");
    exit;
}

// Update last activity time for every new request
$_SESSION['last_activity'] = time();
?>
