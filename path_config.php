<?php
// Define the mapping of virtual folders to physical drives/folders
$LIBRARY_MAPPINGS = [
    'Jounals' => 'D:\E - Jounals',
    'Books' => 'E:\Books',
    'PGIM Academic Publication' => 'F:\PGIM Academic Publication',
    'DEFAULT' => __DIR__ . DIRECTORY_SEPARATOR . 'E - Jounals'
];

/**
 * Maps a virtual path (e.g., 'ClinicalKey/Books/book.pdf') to an absolute physical path
 */

function getPhysicalPath($virtualPath) {
    global $LIBRARY_MAPPINGS;
    
    $virtualPath = str_replace(['/', '\\'], '/', trim($virtualPath, '/'));
    if ($virtualPath === '') {
        return $LIBRARY_MAPPINGS['DEFAULT'];
    }
    
    $parts = explode('/', $virtualPath);
    $rootName = $parts[0];
    
    if (isset($LIBRARY_MAPPINGS[$rootName])) {
        unset($parts[0]);
        $subPath = implode(DIRECTORY_SEPARATOR, $parts);
        $base = rtrim($LIBRARY_MAPPINGS[$rootName], '\\/');
        return $subPath ? $base . DIRECTORY_SEPARATOR . $subPath : $base;
    }
    
    return rtrim($LIBRARY_MAPPINGS['DEFAULT'], '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $virtualPath);
}

/**
 * Ensures the resolved physical path is securely within the allowed mapped base directory
 */
function isPathSecure($physicalPath, $virtualPath) {
    global $LIBRARY_MAPPINGS;
    $virtualPath = str_replace(['/', '\\'], '/', trim($virtualPath, '/'));
    $parts = explode('/', $virtualPath);
    $rootName = $parts[0] ?? '';
    
    $baseStr = isset($LIBRARY_MAPPINGS[$rootName]) ? $LIBRARY_MAPPINGS[$rootName] : $LIBRARY_MAPPINGS['DEFAULT'];
    $base = realpath($baseStr);
    
    $realPhysical = realpath($physicalPath);
    if ($realPhysical === false || $base === false) return false;
    
    // Check if realPhysical starts with base directory (case-insensitive for Windows)
    return stripos($realPhysical, $base) === 0;
}
?>
