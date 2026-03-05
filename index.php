<?php
session_start();

// Authentication Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$baseDir = 'E Resources';

function getDirectoryContents($path) {
    $fullPath = realpath($path);
    $basePath = realpath('E Resources');
    
    // Security check: ensure the path is within E Resources
    if (strpos($fullPath, $basePath) !== 0) {
        return ['error' => 'Access denied'];
    }

    $items = [];
    if (is_dir($fullPath)) {
        $files = scandir($fullPath);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;
            $relPath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $filePath);
            $relPath = str_replace(DIRECTORY_SEPARATOR, '/', $relPath);
            
            $items[] = [
                'name' => $file,
                'path' => $relPath,
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
    echo json_encode(getDirectoryContents($baseDir . DIRECTORY_SEPARATOR . $folder));
    exit;
}

// Handle AJAX request for searching
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');
    $query = strtolower($_GET['query']);
    $results = [];
    $basePath = realpath($baseDir);
    
    $it = new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS);
    foreach (new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST) as $file) {
        $filename = $file->getFilename();
        if (strpos(strtolower($filename), $query) !== false) {
            $fullPath = $file->getRealPath();
            $relPath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $fullPath);
            $relPath = str_replace(DIRECTORY_SEPARATOR, '/', $relPath);
            
            $results[] = [
                'name' => $filename,
                'path' => $relPath,
                'isDir' => $file->isDir(),
                'size' => $file->isFile() ? $file->getSize() : 0
            ];
            if (count($results) > 100) break; // Increased limit
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
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Top Global Header -->
    <header class="main-header">
        <div class="header-left">
            <img src="img/logo.png" alt="PGIM Logo">
            <h1>PGIM Digital Library</h1>
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
                <h2>Library Menu</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="active" data-folder="">
                        <i class="fas fa-home"></i> <span>Home</span>
                    </li>
                    <li data-folder="ClinicalKey/Books">
                        <i class="fas fa-book"></i> <span>Books</span>
                    </li>
                    <li data-folder="ClinicalKey/Journals">
                        <i class="fas fa-journal-whills"></i> <span>Journals</span>
                    </li>
                    <li data-folder="Thesis & Dissertation">
                        <i class="fas fa-graduation-cap"></i> <span>Theses</span>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-bar">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search resources...">
                </div>
                <div class="breadcrumb" id="breadcrumb">
                    <span>Library</span>
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
