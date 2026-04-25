<?php
session_start();
require_once __DIR__ . '/seb_check.php';

// Authentication Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/session_timeout.php';


require_once __DIR__ . '/path_config.php';

function getDirectoryContents($virtualPath) {
    global $LIBRARY_MAPPINGS;
    $virtualPath = str_replace(['/', '\\'], '/', trim($virtualPath, '/'));
    $items = [];
    
    if ($virtualPath === '') {
        // Root directory: only return physical folders in default E Resources, do NOT show virtual roots
        $defaultDir = $LIBRARY_MAPPINGS['DEFAULT'];
        if (is_dir($defaultDir)) {
            $files = scandir($defaultDir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || isset($LIBRARY_MAPPINGS[$file])) continue; 
                
                $filePath = $defaultDir . DIRECTORY_SEPARATOR . $file;
                $items[] = [
                    'name' => $file,
                    'path' => $file,
                    'isDir' => is_dir($filePath),
                    'size' => is_file($filePath) ? filesize($filePath) : 0
                ];
            }
        }
        return $items;
    }

    $physicalPath = getPhysicalPath($virtualPath);
    if (!isPathSecure($physicalPath, $virtualPath)) {
        return ['error' => 'Access denied'];
    }

    $fullPath = realpath($physicalPath);
    if ($fullPath && is_dir($fullPath)) {
        $files = scandir($fullPath);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;
            $items[] = [
                'name' => $file,
                'path' => $virtualPath . '/' . $file,
                'isDir' => is_dir($filePath),
                'size' => is_file($filePath) ? filesize($filePath) : 0
            ];
        }
    }
    return $items;
}

// Handle AJAX request for folder contents
if (isset($_GET['action']) && $_GET['action'] === 'get_folder') {
    header('Content-Type: application/json');
    $folder = isset($_GET['folder']) ? $_GET['folder'] : '';
    echo json_encode(getDirectoryContents($folder));
    exit;
}

// Handle AJAX request for searching (uses pre-built file_index table)
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/db_config.php';
    $query = trim($_GET['query'] ?? '');
    $results = [];

    if (strlen($query) >= 2) {
        try {
            // Search by file/folder name and virtual path, restrict to folders and PDFs
            $stmt = $pdo->prepare("
                SELECT file_name AS name, virtual_path AS path, is_dir AS isDir, file_size AS size
                FROM file_index
                WHERE (file_name LIKE ? OR virtual_path LIKE ?)
                  AND (is_dir = 1 OR file_name LIKE '%.pdf')
                ORDER BY is_dir DESC, file_name ASC
                LIMIT 100
            ");
            $stmt->execute(["%{$query}%", "%{$query}%"]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Cast types for JSON consistency
            foreach ($results as &$r) {
                $r['isDir'] = (bool)$r['isDir'];
                $r['size'] = (int)$r['size'];
            }
        } catch (PDOException $e) {
            // Return empty results on DB error
        }
    }

    echo json_encode($results);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PGIM Digital Library</title>
    <link rel="icon" type="image/x-icon" href="./assets/img/logo without bg.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    
    <!-- Local FontAwesome for Offline Support -->
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    
    <!-- pdf.js for rendering PDFs natively (Local for offline) -->
    <script src="./assets/pdfjs/pdf.min.js"></script>
    <script src="assets/js/theme.js?v=<?php echo time(); ?>"></script>
</head>
<body>
    <!-- Top Global Header -->
    <header class="main-header">
        <div class="header-left">
            <img src="./assets/img/pgim logo black.png" alt="PGIM Logo">
            
        </div>
          <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search resources... (Press Enter)">
                    <button id="searchBtn" class="search-btn" title="Search"><i class="fas fa-search"></i></button>
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
                    <li class="active" data-folder="">
                        <i class="fas fa-home"></i> <span>Home</span>
                    </li>
                    <li data-folder="Books">
                        <i class="fas fa-book"></i> <span>E Books</span>
                    </li>
                    <li data-folder="Jounals">
                        <i class="fas fa-journal-whills"></i> <span>Journals</span>
                    </li>
                    <li data-folder="PGIM Academic Publication">
                        <i class="fas fa-graduation-cap"></i> <span>PGIM Academic Publications</span>
                    </li>
                    <li onclick="window.location.href='profile.php'">
                        <i class="fas fa-id-card"></i> <span>My Profile</span>
                    </li>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                        <li style="margin-top: 1rem; padding-left: 1.5rem; font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; cursor: default; pointer-events: none; border: none;">
                            Admin Panel
                        </li>
                        <li onclick="window.location.href='admin_create_user.php'">
                            <i class="fas fa-user-plus"></i> <span>Add New User</span>
                        </li>
                        <li onclick="window.location.href='admin_users.php'">
                            <i class="fas fa-users"></i> <span>All Users</span>
                        </li>
                        <li onclick="window.location.href='admin_activity.php'">
                            <i class="fas fa-history"></i> <span>Library Analytics</span>
                        </li>
                        <li onclick="window.location.href='admin_reindex.php'">
                            <i class="fas fa-database"></i> <span>Search Index</span>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <p>&copy; 2026 PGIM Library -V1.2</p>
                <p>Developed by PGIM IT Unit - Dushan</p>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-bar">
                <button id="backBtn" class="back-btn" style="display: none;" title="Go Back">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="breadcrumb" id="breadcrumb" style="margin-left: 1rem;">
                </div>
            </header>

            <div class="content-view">
                <div class="view-header">
                    <h1 id="currentFolderName">All Resources</h1>
                    <div class="view-controls">
                        <button id="gridViewBtn" class="active"><i class="fas fa-th-large"></i></button>
                        <button id="listViewBtn"><i class="fas fa-list"></i></button>
                    </div>
                </div>

                <div id="fileGrid">
                    <!-- Loading state or initial items will go here -->
                    <div class="loader">Loading...</div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
</body>
</html>
