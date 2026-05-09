<?php
require 'db_config.php';
require 'path_config.php';

echo "<pre>";
echo "Starting mini-index test for Books...\n";

$physicalBase = $LIBRARY_MAPPINGS['Books'];
$realBase = realpath($physicalBase);

if (!$realBase || !is_dir($realBase)) {
    die("Books path not found or not a directory\n");
}

try {
    $dirIt = new RecursiveDirectoryIterator($realBase, RecursiveDirectoryIterator::SKIP_DOTS);
    $it = new RecursiveIteratorIterator($dirIt, RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);
    
    $count = 0;
    foreach ($it as $file) {
        $count++;
        if ($count > 100) break; // Just check if it can read the first 100 files
    }
    echo "SUCCESS! Was able to read the first $count files from Books without crashing using the exact method the indexer uses.\n";
} catch (Exception $e) {
    echo "CRASHED! Error: " . $e->getMessage() . "\n";
}
echo "Done.</pre>";
?>