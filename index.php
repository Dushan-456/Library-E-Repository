<?php
session_start();
require_once __DIR__ . '/seb_check.php';

// Authentication Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// 15-Minute Session Timeout (900 seconds)
$timeout_duration = 900;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: login.php?msg=timeout");
    exit;
}
$_SESSION['last_activity'] = time(); // Update last activity time


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

// Handle AJAX request for searching
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');
    $query = strtolower($_GET['query']);
    $results = [];
    
    global $LIBRARY_MAPPINGS;
    foreach ($LIBRARY_MAPPINGS as $rootName => $physicalBase) {
        $realBase = realpath($physicalBase);
        if (!$realBase || !is_dir($realBase)) continue;
        
        try {
            $it = new RecursiveDirectoryIterator($realBase, RecursiveDirectoryIterator::SKIP_DOTS);
            foreach (new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST) as $file) {
                $filename = $file->getFilename();
                if (strpos(strtolower($filename), $query) !== false) {
                    $fullPath = $file->getRealPath();
                    $relPath = str_replace($realBase . DIRECTORY_SEPARATOR, '', $fullPath);
                    $relPath = str_replace(DIRECTORY_SEPARATOR, '/', $relPath);
                    
                    $virtualPath = ($rootName === 'DEFAULT') ? $relPath : $rootName . '/' . $relPath;
                    
                    $results[] = [
                        'name' => $filename,
                        'path' => $virtualPath,
                        'isDir' => $file->isDir(),
                        'size' => $file->isFile() ? $file->getSize() : 0
                    ];
                    if (count($results) > 100) break 2; // Increased limit
                }
            }
        } catch (Exception $e) {
            // Ignore unreadable directories
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
</head>
<body>
    <!-- Top Global Header -->
    <header class="main-header">
        <div class="header-left">
            <img src="./assets/img/pgim booking.png" alt="PGIM Logo">
            
        </div>
          <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search resources...">
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
        <!-- Global Watermark -->
        <div class="watermark">PGIM LIBRARY</div>
        
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
                    <li data-folder="E Books & Jounals/Books">
                        <i class="fas fa-book"></i> <span>Books</span>
                    </li>
                    <li data-folder="E Books & Jounals/Journals">
                        <i class="fas fa-journal-whills"></i> <span>Journals</span>
                    </li>
                    <li data-folder="PGIM Academic Publication">
                        <i class="fas fa-graduation-cap"></i> <span>PGIM Academic Publications</span>
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
