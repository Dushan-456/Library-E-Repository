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

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header("Location: admin_users.php");
    exit;
}

try {
    // Fetch User Info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch();
    if (!$u) {
        die("User not found.");
    }

    // Fetch Analytics Summaries
    $stmt2 = $pdo->prepare("SELECT COUNT(*) as total_logins, SUM(total_active_time) as total_time FROM activity_logs WHERE user_id = ?");
    $stmt2->execute([$user_id]);
    $stats = $stmt2->fetch();

    // Fetch Individual Logs
    $stmt3 = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY login_time DESC LIMIT 200");
    $stmt3->execute([$user_id]);
    $activities = $stmt3->fetchAll();
    
    // Fetch Most Viewed Documents for this user
    $stmt4 = $pdo->prepare("
        SELECT file_path, COUNT(*) as view_count 
        FROM document_access_logs 
        WHERE user_id = ? 
        GROUP BY file_path 
        ORDER BY view_count DESC 
        LIMIT 10
    ");
    $stmt4->execute([$user_id]);
    $mostViewed = $stmt4->fetchAll();
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

function formatDuration($seconds) {
    if ($seconds === null || $seconds === '') return '-';
    $hours = floor($seconds / 3600);
    $mins = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    $timeStr = "";
    if ($hours > 0) $timeStr .= "{$hours}h ";
    if ($mins > 0 || $hours > 0) $timeStr .= "{$mins}m ";
    $timeStr .= "{$secs}s";
    return trim($timeStr);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Analytics - PGIM Digital Library</title>
    <link rel="icon" type="image/x-icon" href="./assets/img/logo without bg.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <script src="assets/js/theme.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/chart.min.js"></script>
    <style>
        .admin-card { background: var(--bg-card); padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 2rem; overflow-x: auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-box { background: var(--bg-main); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border); text-align: center; }
        .stat-box h3 { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-box p { font-size: 2rem; font-weight: 700; color: var(--primary); margin: 0; }
        .user-header { border-bottom: 2px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.5rem; display:flex; justify-content: space-between; align-items:flex-end; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { text-align: left; padding: 1rem; border-bottom: 1px solid var(--border); color: var(--text-main); }
        th { background-color: var(--bg-main); font-weight: 600; }
        tr:hover { background-color: var(--bg-hover); }
        .sidebar-nav li { padding: 0.875rem 1.5rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.2s; color: var(--text-muted); font-weight: 500;}
        .sidebar-nav li:hover { background-color: var(--bg-hover); color: var(--primary); }
        .sidebar-nav li.active { background-color: var(--bg-active); color: var(--primary); border-right: 3px solid var(--primary); }
        .btn-back { padding: 0.5rem 1rem; background: var(--bg-hover); color: var(--text-main); text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.85rem; }
        .btn-back:hover { background: var(--border); }

        /* User Chart Styles */
        .user-chart-section {
            margin: 2rem 0;
            background: var(--bg-main);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        .user-chart-container {
            height: 350px;
            width: 100%;
            position: relative;
        }
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
                    <li class="<?= $activePage == 'create_user' ? 'active' : '' ?>" onclick="window.location.href='admin_create_user.php'">
                        <i class="fas fa-user-plus"></i> <span>Add New User</span>
                    </li>
                    <li class="<?= $activePage == 'pending_users' ? 'active' : '' ?>" onclick="window.location.href='admin_pending_users.php'">
                        <i class="fas fa-user-clock"></i> <span>Pending Activations</span>
                        <?php if ($pending_count > 0): ?>
                            <span style="background: #ef4444; color: white; padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; margin-left: auto;"><?= $pending_count ?></span>
                        <?php endif; ?>
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
                    <h1>User Analytics</h1>
                </div>

                <div class="admin-card">
                    <div class="user-header">
                        <div>
                            <h2 style="color: var(--primary); margin-bottom: 0.25rem;">
                                <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                            </h2>
                            <p style="color: var(--text-muted); font-size: 0.9rem;">
                                <strong>Email:</strong> <?= htmlspecialchars($u['email']) ?> | 
                                <strong>ID:</strong> <?= htmlspecialchars($u['id_number']) ?> | 
                                <strong>SLMC:</strong> <?= htmlspecialchars($u['slmc_number'] ?? 'N/A') ?>
                            </p>
                        </div>
                        <a href="admin_users.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Users</a>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-box">
                            <h3>Total Logins</h3>
                            <p><?= (int)($stats['total_logins'] ?? 0) ?></p>
                        </div>
                        <div class="stat-box">
                            <h3>Total Active Time</h3>
                            <p><?= formatDuration($stats['total_time'] ?? 0) ?></p>
                        </div>
                    </div>

                    <h3 style="margin: 2rem 0 1rem; color: var(--text-main);"><i class="fas fa-book-reader"></i> Most Viewed Documents</h3>
                    <div class="user-chart-section">
                        <div class="user-chart-container">
                            <canvas id="userPopularChart"></canvas>
                        </div>
                    </div>

                    <h3 style="margin-bottom: 1rem; color: var(--text-main);">Recent Activity Logs</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Login Time</th>
                                <th>Logout Time</th>
                                <th>Active Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($activities) > 0): ?>
                                <?php foreach ($activities as $act): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($act['login_time']); ?></td>
                                        <td><?php echo htmlspecialchars($act['logout_time'] ?? '-'); ?></td>
                                        <td><?php echo formatDuration($act['total_active_time']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center;">No activity logs recorded.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <script>
        // Data from PHP
        const userPopularLabels = <?php echo json_encode(array_map(function($item) { return basename($item['file_path']); }, $mostViewed)); ?>;
        const userPopularValues = <?php echo json_encode(array_column($mostViewed, 'view_count')); ?>;
        const userPopularPaths = <?php echo json_encode(array_column($mostViewed, 'file_path')); ?>;

        // Theme-aware Chart Config
        Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text-main').trim() || '#334155';
        Chart.defaults.font.family = "'Inter', sans-serif";

        if (userPopularLabels.length > 0) {
            new Chart(document.getElementById('userPopularChart'), {
                type: 'bar',
                data: {
                    labels: userPopularLabels,
                    datasets: [{
                        label: 'Your Views',
                        data: userPopularValues,
                        backgroundColor: 'rgba(56, 189, 248, 0.6)',
                        borderColor: 'rgb(56, 189, 248)',
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onClick: (event, elements) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            window.open('viewer.php?file=' + encodeURIComponent(userPopularPaths[index]), '_blank');
                        }
                    },
                    onHover: (event, elements) => {
                        event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            grid: { color: 'rgba(255,255,255,0.05)' },
                            ticks: { precision: 0 }
                        },
                        x: { grid: { display: false } }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                afterLabel: () => '(Click to open)'
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
