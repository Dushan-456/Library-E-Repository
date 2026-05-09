<?php
session_start();
require_once __DIR__ . '/seb_check.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/path_config.php';

// Admin-only access
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Access denied']);
    exit;
}

require_once __DIR__ . '/session_timeout.php';

header('Content-Type: application/json');

// Ensure the file_index table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS file_index (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_name VARCHAR(500) NOT NULL,
        virtual_path VARCHAR(1000) NOT NULL,
        is_dir TINYINT(1) DEFAULT 0,
        file_size BIGINT DEFAULT 0,
        indexed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_filename (file_name),
        FULLTEXT INDEX idx_fulltext (file_name, virtual_path)
    ) ENGINE=InnoDB");
} catch (PDOException $e) {
    // Table likely already exists
}

$action = $_GET['action'] ?? '';

// Return paginated list of indexed files
if ($action === 'list') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;
    $search = trim($_GET['search'] ?? '');

    $countSql = "SELECT COUNT(*) FROM file_index";
    $listSql = "SELECT id, file_name, virtual_path, is_dir, file_size, indexed_at FROM file_index";

    if ($search !== '') {
        $where = " WHERE file_name LIKE :search OR virtual_path LIKE :search";
        $countSql .= $where;
        $listSql .= $where;
    }

    $listSql .= " ORDER BY is_dir DESC, file_name ASC LIMIT :limit OFFSET :offset";

    // Count
    $countStmt = $pdo->prepare($countSql);
    if ($search !== '') {
        $countStmt->bindValue(':search', "%{$search}%");
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    // List
    $listStmt = $pdo->prepare($listSql);
    if ($search !== '') {
        $listStmt->bindValue(':search', "%{$search}%");
    }
    $listStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $listStmt->execute();
    $items = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'totalPages' => ceil($total / $limit),
        'limit' => $limit
    ]);
    exit;
}

// Run the re-index process
if ($action === 'run') {
    set_time_limit(0); // No time limit (0) because 147,000+ files take a long time
    ini_set('memory_limit', '2048M'); // Increase memory limit to 2GB
    $startTime = microtime(true);
    $count = 0;

    try {
        $pdo->exec("TRUNCATE TABLE file_index");

        $insertStmt = $pdo->prepare("INSERT INTO file_index (file_name, virtual_path, is_dir, file_size) VALUES (?, ?, ?, ?)");
        $batch = [];
        $batchSize = 500;

        foreach ($LIBRARY_MAPPINGS as $rootName => $physicalBase) {
            $realBase = realpath($physicalBase);
            if (!$realBase || !is_dir($realBase)) continue;

            try {
                $dirIt = new RecursiveDirectoryIterator($realBase, RecursiveDirectoryIterator::SKIP_DOTS);
                $it = new RecursiveIteratorIterator($dirIt, RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);
                
                foreach ($it as $file) {
                    try {
                        $filename = $file->getFilename();
                        $fullPath = $file->getRealPath();
                        
                        // If path couldn't be resolved (e.g. permissions), skip this specific item
                        if ($fullPath === false) continue;
                        
                        $relPath = str_replace($realBase . DIRECTORY_SEPARATOR, '', $fullPath);
                        $relPath = str_replace(DIRECTORY_SEPARATOR, '/', $relPath);

                        $virtualPath = ($rootName === 'DEFAULT') ? $relPath : $rootName . '/' . $relPath;
                        $isDir = $file->isDir() ? 1 : 0;
                        $fileSize = $file->isFile() ? $file->getSize() : 0;

                        $batch[] = [$filename, $virtualPath, $isDir, $fileSize];
                        $count++;

                        if (count($batch) >= $batchSize) {
                            foreach ($batch as $row) {
                                $insertStmt->execute($row);
                            }
                            $batch = [];
                        }
                    } catch (Exception $e) {
                        // Skip individual problematic files
                        continue;
                    }
                }
            } catch (Exception $e) {
                // Skip if the root directory itself is completely unreadable
            }
        }

        // Insert remaining batch
        foreach ($batch as $row) {
            $insertStmt->execute($row);
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        echo json_encode([
            'success' => true,
            'count' => $count,
            'elapsed' => $elapsed,
            'message' => "Indexed {$count} files/folders in {$elapsed}s"
        ]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action. Use ?action=run or ?action=list']);
