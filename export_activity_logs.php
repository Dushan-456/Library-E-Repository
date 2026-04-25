<?php
session_start();
require_once __DIR__ . '/db_config.php';

// Authentication Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    die("Access denied");
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=activity_logs_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// Output CSV Headers
fputcsv($output, array('User', 'Email', 'ID Number', 'Login Time', 'Logout Time', 'Active Duration'));

try {
    $stmt = $pdo->query("
        SELECT a.id, u.first_name, u.last_name, u.email, u.id_number, a.login_time, a.logout_time, a.total_active_time 
        FROM activity_logs a 
        JOIN users u ON a.user_id = u.id 
        ORDER BY a.login_time DESC 
    ");
    
    while ($log = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $duration = "-";
        if ($log['total_active_time'] !== null) {
            $hours = floor($log['total_active_time'] / 3600);
            $mins = floor(($log['total_active_time'] % 3600) / 60);
            $secs = $log['total_active_time'] % 60;
            $timeStr = "";
            if ($hours > 0) $timeStr .= "{$hours}h ";
            if ($mins > 0 || $hours > 0) $timeStr .= "{$mins}m ";
            $timeStr .= "{$secs}s";
            $duration = trim($timeStr);
        }
        
        fputcsv($output, array(
            $log['first_name'] . ' ' . $log['last_name'],
            $log['email'],
            $log['id_number'],
            $log['login_time'],
            $log['logout_time'] ?? '-',
            $duration
        ));
    }
} catch (PDOException $e) {
    fputcsv($output, array("Error fetching logs: " . $e->getMessage()));
}
fclose($output);
