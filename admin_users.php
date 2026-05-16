<?php
session_start();
require_once __DIR__ . '/seb_check.php';
require_once __DIR__ . '/db_config.php';

// Authentication Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/session_timeout.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delete_id = $_POST['user_id'];
    try {
        if ($delete_id != $_SESSION['user_id']) {
            $delStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $delStmt->execute([$delete_id]);
            $message = "User deleted successfully.";
        } else {
            $error = "You cannot delete your own account.";
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

$users = [];
$search = $_GET['search'] ?? '';

try {
    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email LIKE ? OR slmc_number LIKE ? ORDER BY created_at DESC");
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    }
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$activePage = 'all_users';

$pending_count = 0;
try {
    $countStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'inactive'");
    $pending_count = $countStmt->fetchColumn();
} catch (PDOException $e) {
    // Ignore
}
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
    <script src="assets/js/theme.js?v=<?php echo time(); ?>"></script>
    <style>
        .admin-card { background: var(--bg-card); padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 2rem; overflow-x: auto; }
        .search-form { margin-bottom: 1.5rem; display: flex; gap: 1rem; }
        .search-form input { padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; outline: none; flex: 1; max-width: 400px; background: var(--bg-input); color: var(--text-main); }
        .search-form button { padding: 0.75rem 1.5rem; background: var(--primary); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .search-form button:hover { background: var(--primary-hover); }
        .btn-view { padding: 0.4rem 0.8rem; background: var(--bg-hover); color: var(--text-main); border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: background 0.2s; white-space: nowrap; }
        .btn-view:hover { background: var(--border); }
        .btn-edit { padding: 0.4rem 0.8rem; background: #fbbf24; color: #fff; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: background 0.2s; white-space: nowrap; margin-left: 0.25rem; }
        .btn-edit:hover { background: #f59e0b; }
        .btn-delete { padding: 0.4rem 0.8rem; background: #ef4444; color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: background 0.2s; white-space: nowrap; margin-left: 0.25rem; }
        .btn-delete:hover { background: #dc2626; }
        .badge { padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .message-box { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .message-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .message-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
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
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                <button id="sebToggleBtn" data-enabled="<?php echo $safe_browser_only ? 'true' : 'false'; ?>" style="padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.75rem; font-weight: bold; cursor: pointer; border: none; margin-right: 15px; background: <?php echo $safe_browser_only ? '#10b981' : '#ef4444'; ?>; color: white; transition: background 0.2s;">
                    <?php echo $safe_browser_only ? 'SEB OFF' : 'SEB ON'; ?>
                </button>
            <?php endif; ?>
            <button id="themeToggle" class="theme-toggle" title="Toggle Theme">
                <div class="theme-toggle-knob"><i class="fas fa-sun"></i></div>
            </button>
            <a href="profile.php" style="text-decoration: none; color: inherit;">
                <div class="user-profile">
                    <i class="fas fa-user-circle"></i>
                    <div class="user-info-text">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                </div>
            </a>
            <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="app-container">
        <!-- Global Watermark -->
        <div class="watermark">PGIM LIBRARY
            <br>
            <p>
                <span>
                    <?php echo  htmlspecialchars($_SESSION['email']) ; ?>
                </span>
                <?php echo   "ID: " . htmlspecialchars($_SESSION['id_number']) . " <br> SLMC: " . htmlspecialchars($_SESSION['slmc_number']); ?>
            </p>
            </div>
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
                    <li class="<?= $activePage == 'pending_users' ? 'active' : '' ?>" onclick="window.location.href='admin_pending_users.php'">
                        <i class="fas fa-user-clock"></i> <span>Pending Activations</span>
                        <?php if ($pending_count > 0): ?>
                            <span style="background: #ef4444; color: white; padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; margin-left: auto;"><?= $pending_count ?></span>
                        <?php endif; ?>
                    </li>
                    <li class="<?= $activePage == 'create_user' ? 'active' : '' ?>" onclick="window.location.href='admin_create_user.php'">
                        <i class="fas fa-user-plus"></i> <span>Add New User</span>
                    </li>
                    <li class="<?= $activePage == 'all_users' ? 'active' : '' ?>" onclick="window.location.href='admin_users.php'">
                        <i class="fas fa-users"></i> <span>All Users</span>
                    </li>
                    <li class="<?= $activePage == 'activity' ? 'active' : '' ?>" onclick="window.location.href='admin_activity.php'">
                        <i class="fas fa-history"></i> <span>Library Analytics</span>
                    </li>
                    <li class="<?= $activePage == 'reindex' ? 'active' : '' ?>" onclick="window.location.href='admin_reindex.php'">
                        <i class="fas fa-database"></i> <span>Search Index</span>
                    </li>
                    <li onclick="window.location.href='profile.php'">
                        <i class="fas fa-id-card"></i> <span>My Profile</span>
                    </li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>&copy; 2026 PGIM Library -V1.2</p>
                <p>Developed by PGIM IT Unit - Dushan</p>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-view">
                <div class="view-header">
                    <h1>All Registered Users</h1>
                </div>

                <?php if ($message): ?>
                    <div class="message-box message-success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="message-box message-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

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
                                <th>Status</th>
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
                                        <td><?php echo htmlspecialchars($u['slmc_number'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($u['role']); ?></td>
                                        <td>
                                            <?php $stat = $u['status'] ?? 'active'; ?>
                                            <span class="badge badge-<?= $stat ?>"><?= htmlspecialchars($stat) ?></span>
                                        </td>
                                        <td style="display: flex; gap: 0.5rem; align-items: center;">
                                            <a href="admin_user_analytics.php?id=<?= $u['id'] ?>" class="btn-view"><i class="fas fa-chart-line"></i> Analytics</a>
                                            <a href="admin_edit_user.php?id=<?= $u['id'] ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                            <form method="POST" action="admin_users.php" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete this user? This cannot be undone.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn-delete"><i class="fas fa-trash-alt"></i> Delete</button>
                                            </form>
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
