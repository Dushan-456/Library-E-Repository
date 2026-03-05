<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("Access denied");
}

if (!isset($_GET['file'])) {
    die("No file specified");
}

$file = $_GET['file'];
$basePath = realpath('E Resources');
$fullPath = realpath($basePath . DIRECTORY_SEPARATOR . $file);

// Security Check: Ensure file is within E Resources
if ($fullPath === false || strpos($fullPath, $basePath) !== 0 || !is_file($fullPath)) {
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
