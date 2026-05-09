<?php
require 'db_config.php';

$stmt = $pdo->prepare("
    SELECT 
        SUBSTRING_INDEX(virtual_path, '/', 1) as root_folder, 
        COUNT(*) as total_files 
    FROM file_index 
    GROUP BY root_folder
");
$stmt->execute();
$counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
echo "<h3>Total Indexed Files IN THE DATABASE per Root Folder:</h3>\n";
print_r($counts);
echo "</pre>";
?>


<!-- http://localhost/Library-E-Repository/test_db.php -->