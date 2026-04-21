<?php
session_start();
require_once __DIR__ . '/db_config.php';

// Authentication Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

$error = "";
$logs = [];

try {
    $stmt = $pdo->query("
        SELECT a.id, u.first_name, u.last_name, u.email, u.id_number, a.login_time, a.logout_time, a.total_active_time 
        FROM activity_logs a 
        JOIN users u ON a.user_id = u.id 
        ORDER BY a.login_time DESC 
        LIMIT 200
    ");
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching logs: " . $e->getMessage();
}

$activePage = 'activity';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Logs - PGIM Digital Library</title>
    <link rel="icon" type="image/x-icon" href="./assets/img/logo without bg.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <script src="assets/js/theme.js?v=<?php echo time(); ?>"></script>
    <style>
        .admin-card { background: var(--bg-card); padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 2rem; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { text-align: left; padding: 1rem; border-bottom: 1px solid var(--border); color: var(--text-main); }
        th { background-color: var(--bg-main); font-weight: 600; }
        tr:hover { background-color: var(--bg-hover); }
        .sidebar-nav li { padding: 0.875rem 1.5rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.2s; color: var(--text-muted); font-weight: 500;}
        .sidebar-nav li:hover { background-color: var(--bg-hover); color: var(--primary); }
        .sidebar-nav li.active { background-color: var(--bg-active); color: var(--primary); border-right: 3px solid var(--primary); }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <img src="./assets/img/pgim booking.png" alt="PGIM Logo">
        </div>
        <div class="header-right">
            <button id="themeToggle" class="theme-toggle" title="Toggle Theme">
                <div class="theme-toggle-knob"><i class="fas fa-sun"></i></div>
            </button>
            <div class="user-profile">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
            <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>PGIM Digital Library</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li onclick="window.location.href='index.php'">
                        <i class="fas fa-home"></i> <span>Home</span>
                    </li>
                    <li style="margin-top: 1rem; padding-left: 1.5rem; font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; cursor: default; pointer-events: none; border: none;">
                        Admin Panel
                    </li>
                    <li class="<?= $activePage == 'create_user' ? 'active' : '' ?>" onclick="window.location.href='admin_create_user.php'">
                        <i class="fas fa-user-plus"></i> <span>Add New User</span>
                    </li>
                    <li class="<?= $activePage == 'all_users' ? 'active' : '' ?>" onclick="window.location.href='admin_users.php'">
                        <i class="fas fa-users"></i> <span>All Users</span>
                    </li>
                    <li class="<?= $activePage == 'activity' ? 'active' : '' ?>" onclick="window.location.href='admin_activity.php'">
                        <i class="fas fa-history"></i> <span>User Activity Logs</span>
                    </li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>&copy; 2026 PGIM Library -V1.0</p>
                <p>Developed by PGIM IT Unit</p>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-view">
                <div class="view-header">
                    <h1>User Activity Logs</h1>
                </div>

                <div class="admin-card">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>ID Number</th>
                                <th>Login Time</th>
                                <th>Logout Time</th>
                                <th>Active Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($log['email']); ?></td>
                                    <td><?php echo htmlspecialchars($log['id_number']); ?></td>
                                    <td><?php echo htmlspecialchars($log['login_time']); ?></td>
                                    <td><?php echo htmlspecialchars($log['logout_time'] ?? '-'); ?></td>
                                    <td>
                                        <?php 
                                        if ($log['total_active_time'] !== null) {
                                            $hours = floor($log['total_active_time'] / 3600);
                                            $mins = floor(($log['total_active_time'] % 3600) / 60);
                                            $secs = $log['total_active_time'] % 60;
                                            $timeStr = "";
                                            if ($hours > 0) $timeStr .= "{$hours}h ";
                                            if ($mins > 0 || $hours > 0) $timeStr .= "{$mins}m ";
                                            $timeStr .= "{$secs}s";
                                            echo trim($timeStr);
                                        } else {
                                            echo "-";
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
