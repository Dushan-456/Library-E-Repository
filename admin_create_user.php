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

$message = "";
$error = "";

// Handle User Creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $speciality = trim($_POST['speciality'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $slmc_number = trim($_POST['slmc_number'] ?? '');
    
    if ($first_name && $last_name && $email && $id_number) {
        try {
            $raw_password = preg_replace('/V$/', 'v', $id_number);
            $password_hash = password_hash($raw_password, PASSWORD_DEFAULT);
            $role = 'User';

            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, speciality, id_number, slmc_number, role, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$first_name, $last_name, $email, $speciality, $id_number, $slmc_number, $role, $password_hash]);
            $message = "User created successfully! Their password is set to their ID Number ($raw_password).";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Error: Email or ID Number already exists.";
            } else {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    } else {
        $error = "Please fill in all required fields (First Name, Last Name, Email, ID Number).";
    }
}

// Handle CSV Upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'upload_csv') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($file_tmp, "r")) !== FALSE) {
            $successCount = 0;
            $errorCount = 0;
            $existingUsers = [];
            $row = 0;
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row++;
                if ($row === 1 && (stripos($data[0] ?? '', 'name') !== false || stripos($data[0] ?? '', 'first') !== false)) {
                    continue; // Skip header
                }
                
                $first_name = trim($data[0] ?? '');
                $last_name = trim($data[1] ?? '');
                $email = trim($data[2] ?? '');
                $id_number = trim($data[3] ?? '');
                $speciality = trim($data[4] ?? '');
                $slmc_number = trim($data[5] ?? '');
                
                if ($first_name && $last_name && $email && $id_number) {
                    try {
                        $raw_password = preg_replace('/V$/', 'v', $id_number);
                        $password_hash = password_hash($raw_password, PASSWORD_DEFAULT);
                        $role = 'User';
                        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, speciality, id_number, slmc_number, role, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$first_name, $last_name, $email, $speciality, $id_number, $slmc_number, $role, $password_hash]);
                        $successCount++;
                    } catch (PDOException $e) {
                        $errorCount++;
                        if ($e->getCode() == 23000) {
                            $existingUsers[] = $email . ' (' . $id_number . ')';
                        }
                    }
                } elseif (array_filter($data)) {
                    $errorCount++;
                }
            }
            fclose($handle);
            
            if ($successCount > 0) {
                $message = "CSV process complete! $successCount user(s) created successfully.";
            }
            if ($errorCount > 0) {
                $errorMsg = "$errorCount record(s) failed";
                if (!empty($existingUsers)) {
                    $errorMsg .= ". Already exists: " . implode(", ", $existingUsers);
                } else {
                    $errorMsg .= " (missing required fields or database error).";
                }
                $error = $error ? ($error . " | " . $errorMsg) : $errorMsg;
            }
            if ($successCount == 0 && $errorCount == 0 && !$error) {
                $error = "The uploaded CSV file was empty or valid data not found.";
            }
        } else {
            $error = "Error opening the uploaded file.";
        }
    } else {
        $error = "Please upload a valid CSV file.";
    }
}
$activePage = 'create_user';

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
    <title>Add New User - PGIM Digital Library</title>
    <link rel="icon" type="image/x-icon" href="./assets/img/logo without bg.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <script src="assets/js/theme.js?v=<?php echo time(); ?>"></script>
    <style>
        .admin-card { background: var(--bg-card); padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .forms-container { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: start; }
        .csv-instructions { background: var(--bg-main); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px dashed var(--border); font-size: 0.9rem; color: var(--text-muted); }
        .csv-instructions ul { margin-left: 1.5rem; margin-top: 0.5rem; }
        .file-upload-wrapper { position: relative; width: 100%; height: 150px; border: 2px dashed var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-direction: column; background: var(--bg-main); transition: all 0.2s; cursor: pointer; text-align: center; padding: 1rem; color: var(--text-muted); }
        .file-upload-wrapper:hover { border-color: var(--primary); background: var(--bg-active); }
        .file-upload-wrapper input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .file-upload-wrapper i { font-size: 2.5rem; color: var(--primary); margin-bottom: 0.5rem; }
        @media (max-width: 1024px) { .forms-container { grid-template-columns: 1fr; } }
        .admin-header h2 { margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border); color: var(--primary); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-main); }
        .form-group input { width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; outline: none; background: var(--bg-input); color: var(--text-main); }
        .form-group input:focus { border-color: var(--primary); }
        .btn-submit { padding: 0.75rem 1.5rem; background: var(--primary); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 1.5rem; }
        .btn-submit:hover { background: var(--primary-hover); }
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .sidebar-nav li { padding: 0.875rem 1.5rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.2s; color: var(--text-muted); font-weight: 500;}
        .sidebar-nav li:hover { background-color: var(--bg-hover); color: var(--primary); }
        .sidebar-nav li.active { background-color: var(--bg-active); color: var(--primary); border-right: 3px solid var(--primary); }
        .btn-download-sample { display: inline-block; margin-top: 0.75rem; padding: 0.5rem 1rem; background: var(--bg-hover); color: var(--text-main); text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; border: 1px solid var(--border); transition: all 0.2s; }
        .btn-download-sample:hover { background: var(--border); }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <img src="./assets/img/pgim logo black.png" alt="PGIM Logo">
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
                    <h1>Add New User</h1>
                </div>

                <?php if ($message): ?>
                    <div class="message success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="forms-container">
                    <div class="admin-card">
                        <div class="admin-header">
                            <h2><i class="fas fa-user-edit"></i> Add Single User</h2>
                        </div>
                        <form method="POST" action="admin_create_user.php">
                            <input type="hidden" name="action" value="create_user">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>First Name *</label>
                                    <input type="text" name="first_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Last Name *</label>
                                    <input type="text" name="last_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Email *</label>
                                    <input type="email" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label>ID Number *</label>
                                    <input type="text" name="id_number" required>
                                </div>
                                <div class="form-group">
                                    <label>Speciality</label>
                                    <input type="text" name="speciality">
                                </div>
                                <div class="form-group">
                                    <label>SLMC Number</label>
                                    <input type="text" name="slmc_number">
                                </div>
                            </div>
                            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Create User</button>
                        </form>
                    </div>

                    <div class="admin-card">
                        <div class="admin-header">
                            <h2><i class="fas fa-file-csv"></i> Upload Multiple Users (CSV)</h2>
                        </div>
                        <div class="csv-instructions">
                            <strong>CSV Format Required:</strong>
                            <ul>
                                <li>Column 1: First Name *</li>
                                <li>Column 2: Last Name *</li>
                                <li>Column 3: Email *</li>
                                <li>Column 4: ID Number *</li>
                                <li>Column 5: Speciality</li>
                                <li>Column 6: SLMC Number</li>
                            </ul>
                            <em>Note: The first row will be skipped if it contains headers. Password defaults to ID Number.</em>
                            <br>
                            <a href="sample_users.csv" download="sample_users.csv" class="btn-download-sample">
                                <i class="fas fa-download"></i> Download Sample CSV
                            </a>
                        </div>
                        <form method="POST" action="admin_create_user.php" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_csv">
                            <div class="file-upload-wrapper">
                                <input type="file" name="csv_file" accept=".csv" required id="csvFileInput">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span id="fileNameDisplay">Drag & Drop or Click to Upload CSV</span>
                            </div>
                            <button type="submit" class="btn-submit" style="width: 100%; margin-top: 1rem;"><i class="fas fa-upload"></i> Upload & Create Users</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        document.getElementById('csvFileInput').addEventListener('change', function(e) {
            var fileName = e.target.files[0] ? e.target.files[0].name : 'Drag & Drop or Click to Upload CSV';
            document.getElementById('fileNameDisplay').textContent = fileName;
        });
    </script>
</body>
</html>
