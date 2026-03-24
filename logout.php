<?php
session_start();
require_once __DIR__ . '/db_config.php';

if (isset($_SESSION['login_log_id'])) {
    $log_id = $_SESSION['login_log_id'];
    $stmt = $pdo->prepare("UPDATE activity_logs SET logout_time = NOW(), total_active_time = TIMESTAMPDIFF(SECOND, login_time, NOW()) WHERE id = ?");
    $stmt->execute([$log_id]);
}

session_unset();
session_destroy();
header("Location: login.php");
exit;
?>
