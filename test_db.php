<?php
require 'db_config.php';
$stmt = $pdo->prepare("SELECT * FROM file_index WHERE file_name LIKE '%4568%' OR virtual_path LIKE '%4568%'");
$stmt->execute();
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
