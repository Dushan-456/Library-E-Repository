<?php
session_start();
require_once __DIR__ . '/seb_check.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("Access denied");
}

// 15-Minute Session Timeout (900 seconds)
$timeout_duration = 900;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    // Session expired
    session_unset();
    session_destroy();
    die("Session Timeout");
}
$_SESSION['last_activity'] = time(); // Update last activity time

require_once __DIR__ . '/path_config.php';

if (!isset($_GET['file'])) {
    die("No file specified");
}

$file = $_GET['file'];
$physicalPath = getPhysicalPath($file);

// Security Check: Ensure file is within mapped path
if (!isPathSecure($physicalPath, $file)) {
    die("Invalid file path");
}

$fullPath = realpath($physicalPath);
if ($fullPath === false || !is_file($fullPath)) {
    die("Invalid file path");
}

// Serve the PDF file
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('Content-Transfer-Encoding: binary');
header('Accept-Ranges: bytes');

// Disable caching to make it slightly harder to find in browser cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($fullPath);
exit;
?>
