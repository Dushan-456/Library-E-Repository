<?php
require 'path_config.php';

$results = [];
foreach ($LIBRARY_MAPPINGS as $name => $path) {
    $real = realpath($path);
    $canRead = false;
    $error = '';
    
    if ($real && is_dir($real)) {
        try {
            $files = @scandir($real);
            if ($files !== false) {
                $canRead = true;
                $error = count($files) . ' files/folders found';
            } else {
                $error = 'scandir() returned false (Permission Denied)';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = 'Not a valid directory';
    }

    $results[$name] = [
        'path' => $path,
        'can_read' => $canRead ? 'YES' : 'NO',
        'details' => $error
    ];
}

echo "<pre>";
echo "<h3>Directory Read Permission Test:</h3>\n";
print_r($results);
echo "</pre>";
?>


<!-- http://localhost/Library-E-Repository/test_db.php -->