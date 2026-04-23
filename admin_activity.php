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

    // 1. Popular Books/Journals (Document Access Logs)
    $stmtPopular = $pdo->query("
        SELECT file_path, COUNT(*) as access_count 
        FROM document_access_logs 
        GROUP BY file_path 
        ORDER BY access_count DESC 
        LIMIT 20
    ");
    $popularData = $stmtPopular->fetchAll(PDO::FETCH_ASSOC);

    // 2. Peak Usage Times (Hourly)
    $stmtHourly = $pdo->query("
        SELECT HOUR(login_time) as hour, COUNT(*) as count 
        FROM activity_logs 
        WHERE login_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY hour 
        ORDER BY hour ASC
    ");
    $hourlyData = array_fill(0, 24, 0);
    while ($row = $stmtHourly->fetch()) {
        $hourlyData[(int)$row['hour']] = (int)$row['count'];
    }

    // 3. Active vs. Inactive Users
    $stmtStatus = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM users 
        GROUP BY status
    ");
    $statusData = $stmtStatus->fetchAll(PDO::FETCH_KEY_PAIR);
    
} catch (PDOException $e) {
    $error = "Error fetching analytics: " . $e->getMessage();
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
    <script src="assets/js/chart.min.js"></script>
    <style>
        .admin-card { background: var(--bg-card); padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 2rem; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { text-align: left; padding: 1rem; border-bottom: 1px solid var(--border); color: var(--text-main); }
        th { background-color: var(--bg-main); font-weight: 600; }
        tr:hover { background-color: var(--bg-hover); }
        .sidebar-nav li { padding: 0.875rem 1.5rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.2s; color: var(--text-muted); font-weight: 500;}
        .sidebar-nav li:hover { background-color: var(--bg-hover); color: var(--primary); }
        .sidebar-nav li.active { background-color: var(--bg-active); color: var(--primary); border-right: 3px solid var(--primary); }
        
        /* Analytics Dashboard Styles */
        .dashboard-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 1.5rem; 
            margin-bottom: 2rem; 
        }
        .chart-container.full-width {
            grid-column: span 2;
            height: 550px; /* Enhanced height for featured chart */
        }
        /* Responsive adjustment for small screens */
        @media (max-width: 900px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .chart-container.full-width { grid-column: span 1; height: 320px; }
        }
        .chart-container { 
            background: var(--bg-card); 
            padding: 2.5rem; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); 
            border: 1px solid var(--border);
            height: 380px;
            overflow: hidden; /* Prevent overflow of internal elements */
        }
        .chart-container.full-width {
            grid-column: span 2;
            height: 700px; 
        }
        .chart-scroll-wrapper {
            width: 100%;
            height: calc(100% - 40px); /* Leave space for title */
            overflow-x: auto;
            position: relative;
        }
        .chart-inner-container {
            height: 100%;
            min-width: 100%;
        }
        .chart-container h3 { 
            margin-top: 0; 
            margin-bottom: 1rem; 
            font-size: 1.1rem; 
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .toggle-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .view-toggle {
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        .view-toggle.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
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
                    <li class="<?= $activePage == 'all_users' ? 'active' : '' ?>" onclick="window.location.href='admin_users.php'">
                        <i class="fas fa-users"></i> <span>All Users</span>
                    </li>
                    <li class="<?= $activePage == 'activity' ? 'active' : '' ?>" onclick="window.location.href='admin_activity.php'">
                        <i class="fas fa-history"></i> <span>Library Analytics</span>
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
                    <h1>User Activity & Analytics</h1>
                </div>

                <div class="toggle-container">
                    <button class="view-toggle active" id="showAnalytics">
                        <i class="fas fa-chart-pie"></i> Analytics Dashboard
                    </button>
                    <button class="view-toggle" id="showLogs">
                        <i class="fas fa-list"></i> Activity Logs
                    </button>
                </div>

                <!-- Analytics Dashboard -->
                <div id="analyticsView">
                    <div class="dashboard-grid">
                        <div class="chart-container full-width">
                            <h3><i class="fas fa-book-reader"></i> Most Popular Documents</h3>
                            <div class="chart-scroll-wrapper">
                                <div class="chart-inner-container" id="popularChartContainer">
                                    <canvas id="popularChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="chart-container">
                            <h3><i class="fas fa-clock"></i> Peak Usage Times (24h)</h3>
                            <canvas id="usageChart"></canvas>
                        </div>
                        <div class="chart-container">
                                <h3><i class="fas fa-users-cog"></i> User Engagement</h3>
                            <canvas id="engagementChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Activity Logs Table -->
                <div id="logsView" style="display: none;">
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
                </div> <!-- End logsView -->
            </div> <!-- End content-view -->
        </main>
    </div>

    <script>
        // Data from PHP
        const popularLabels = <?php echo json_encode(array_map(function($item) { return basename($item['file_path']); }, $popularData)); ?>;
        const popularValues = <?php echo json_encode(array_column($popularData, 'access_count')); ?>;
        
        const hourlyLabels = <?php echo json_encode(range(0, 23)); ?>.map(h => h + ":00");
        const hourlyValues = <?php echo json_encode($hourlyData); ?>;
        
        const statusLabels = <?php echo json_encode(array_keys($statusData)); ?>;
        const statusValues = <?php echo json_encode(array_values($statusData)); ?>;

        // Common Chart Config
        Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text-main').trim() || '#334155';
        Chart.defaults.font.family = "'Inter', sans-serif";

        // 1. Popular Books Chart - Dynamic Width for Scrolling
        const popularChartContainer = document.getElementById('popularChartContainer');
        const minBarWidth = 80; // Minimum width per bar to keep it readable
        const requiredWidth = popularLabels.length * minBarWidth;
        if (requiredWidth > popularChartContainer.parentElement.clientWidth) {
            popularChartContainer.style.width = requiredWidth + 'px';
        }

        new Chart(document.getElementById('popularChart'), {
            type: 'bar',
            data: {
                labels: popularLabels,
                datasets: [{
                    label: 'Access Count',
                    data: popularValues,
                    backgroundColor: 'rgba(56, 189, 248, 0.6)',
                    borderColor: 'rgb(56, 189, 248)',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
                    x: { grid: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });

        // 2. Usage Chart
        new Chart(document.getElementById('usageChart'), {
            type: 'line',
            data: {
                labels: hourlyLabels,
                datasets: [{
                    label: 'Logins',
                    data: hourlyValues,
                    fill: true,
                    backgroundColor: 'rgba(129, 140, 248, 0.2)',
                    borderColor: 'rgb(129, 140, 248)',
                    tension: 0.4,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: { stepSize: 1, precision: 0 }
                    },
                    x: { 
                        grid: { color: 'rgba(255,255,255,0.05)' },
                        ticks: {
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 12 // Show every 2nd hour to keep it clean
                        }
                    }
                },
                plugins: { legend: { display: false } }
            }
        });

        // 3. User Engagement Chart
        new Chart(document.getElementById('engagementChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: ['rgba(34, 197, 94, 0.6)', 'rgba(239, 68, 68, 0.6)'],
                    borderColor: ['rgb(34, 197, 94)', 'rgb(239, 68, 68)'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // View Toggles
        document.getElementById('showAnalytics').addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('showLogs').classList.remove('active');
            document.getElementById('analyticsView').style.display = 'block';
            document.getElementById('logsView').style.display = 'none';
        });

        document.getElementById('showLogs').addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('showAnalytics').classList.remove('active');
            document.getElementById('logsView').style.display = 'block';
            document.getElementById('analyticsView').style.display = 'none';
        });
    </script>
</body>
</html>
