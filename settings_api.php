<?php
session_start();
require_once __DIR__ . '/db_config.php';

// Authentication Check - Only Admins can change settings
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] === 'toggle_seb') {
        $state = isset($data['state']) && $data['state'] === true ? '1' : '0';
        
        try {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'safe_browser_only'");
            $stmt->execute([$state]);
            
            echo json_encode(['success' => true, 'state' => $state === '1']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error']);
            exit;
        }
    }
}

echo json_encode(['error' => 'Invalid request']);
