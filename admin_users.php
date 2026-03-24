<?php
session_start();
require_once __DIR__ . '/db_config.php';

// Authentication Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

$users = [];
$search = $_GET['search'] ?? '';

try {
    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email LIKE ? OR slms_number LIKE ? ORDER BY created_at DESC");
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    }
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$activePage = 'all_users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Users - PGIM Digital Library</title>
    <link rel="icon" type="image/x-icon" href="./assets/img/logo without bg.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        .admin-card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 2rem; overflow-x: auto; }
        .search-form { margin-bottom: 1.5rem; display: flex; gap: 1rem; }
        .search-form input { padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; outline: none; flex: 1; max-width: 400px; }
        .search-form button { padding: 0.75rem 1.5rem; background: var(--primary); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .search-form button:hover { background: var(--primary-hover); }
        .btn-view { padding: 0.4rem 0.8rem; background: #cbd5e1; color: #0f172a; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: background 0.2s; white-space: nowrap; }
        .btn-view:hover { background: #94a3b8; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { text-align: left; padding: 1rem; border-bottom: 1px solid var(--border); }
        th { background-color: #f8fafc; font-weight: 600; color: var(--text); }
        tr:hover { background-color: #f1f5f9; }
        .sidebar-nav li { padding: 0.875rem 1.5rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.2s; color: var(--text-muted); font-weight: 500;}
        .sidebar-nav li:hover { background-color: #f1f5f9; color: var(--primary); }
        .sidebar-nav li.active { background-color: #eff6ff; color: var(--primary); border-right: 3px solid var(--primary); }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <img src="./assets/img/pgim booking.png" alt="PGIM Logo">
        </div>
        <div class="header-right">
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
                    <h1>All Registered Users</h1>
                </div>

                <div class="admin-card">
                    <form method="GET" action="admin_users.php" class="search-form">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by Email or SLMC Number...">
                        <button type="submit"><i class="fas fa-search"></i> Search</button>
                    </form>

                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>ID Number</th>
                                <th>SLMC Number</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) > 0): ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td><?php echo htmlspecialchars($u['id_number']); ?></td>
                                        <td><?php echo htmlspecialchars($u['slms_number'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($u['role']); ?></td>
                                        <td>
                                            <a href="admin_user_analytics.php?id=<?= $u['id'] ?>" class="btn-view"><i class="fas fa-chart-line"></i> Analytics</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
