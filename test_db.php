<?php
require 'path_config.php';

$results = [];
foreach ($LIBRARY_MAPPINGS as $name => $path) {
    $real = realpath($path);
    $isDir = is_dir($path);
    $results[$name] = [
        'configured_path' => $path,
        'realpath_result' => $real !== false ? $real : 'FALSE (Path not found by PHP)',
        'is_dir_result' => $isDir ? 'TRUE' : 'FALSE',
        'exists' => file_exists($path) ? 'TRUE' : 'FALSE'
    ];
}

echo "<pre>";
print_r($results);
echo "</pre>";
?>


<!-- http://localhost/Library-E-Repository/test_db.php -->