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
$user_id = $_GET['id'] ?? $_POST['id'] ?? null;

if (!$user_id) {
    header("Location: admin_users.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $speciality = trim($_POST['speciality'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $slmc_number = trim($_POST['slmc_number'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    $role = trim($_POST['role'] ?? 'User');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if ($first_name && $last_name && $email && $id_number) {
        if (!empty($new_password) && $new_password !== $confirm_password) {
            $error = "Passwords do not match!";
        } else {
            try {
                $sql = "UPDATE users SET first_name=?, last_name=?, email=?, speciality=?, id_number=?, slmc_number=?, status=?, role=?";
                $params = [$first_name, $last_name, $email, $speciality, $id_number, $slmc_number, $status, $role];
                
                if (!empty($new_password)) {
                    $sql .= ", password_hash=?";
                    $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                }
                
                $sql .= " WHERE id=?";
                $params[] = $user_id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $message = "User details updated successfully!";
                if (!empty($new_password)) {
                    $message .= " Password has been changed.";
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Error: Email or ID Number already exists for another user.";
                } else {
                    $error = "Database Error: " . $e->getMessage();
                }
            }
        }
    } else {
        $error = "Please fill in all required fields (First Name, Last Name, Email, ID Number).";
    }
}

// Fetch current user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        die("User not found.");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$activePage = 'all_users'; // Keep the sidebar selection on "All Users" instead of create
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - PGIM Digital Library</title>
    <link rel="icon" type="image/x-icon" href="./assets/img/logo without bg.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <script src="assets/js/theme.js?v=<?php echo time(); ?>"></script>
    <style>
        .admin-card { background: var(--bg-card); padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 2rem; max-width: 800px; margin-left: auto; margin-right: auto; }
        .admin-header h2 { margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border); color: var(--primary); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text-main); }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; outline: none; background: var(--bg-input); color: var(--text-main); }
        .form-group input:focus, .form-group select:focus { border-color: var(--primary); }
        .btn-submit { padding: 0.75rem 1.5rem; background: var(--primary); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 1.5rem; display: inline-block; }
        .btn-submit:hover { background: var(--primary-hover); }
        .btn-back { padding: 0.75rem 1.5rem; background: var(--bg-hover); color: var(--text-main); border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 1.5rem; display: inline-block; text-decoration: none; margin-right: 0.5rem; }
        .btn-back:hover { background: var(--border); }
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; max-width: 800px; margin-left: auto; margin-right: auto; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .sidebar-nav li { padding: 0.875rem 1.5rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.2s; color: var(--text-muted); font-weight: 500;}
        .sidebar-nav li:hover { background-color: var(--bg-hover); color: var(--primary); }
        .sidebar-nav li.active { background-color: var(--bg-active); color: var(--primary); border-right: 3px solid var(--primary); }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <img src="./assets/img/pgim logo black.png" alt="PGIM Logo">
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
                    <h1>Edit User</h1>
                </div>

                <?php if ($message): ?>
                    <div class="message success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="admin-card">
                    <div class="admin-header">
                        <h2><i class="fas fa-user-edit"></i> Edit Details (<?= htmlspecialchars($user['email']) ?>)</h2>
                    </div>
                    <form method="POST" action="admin_edit_user.php">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" required value="<?= htmlspecialchars($user['first_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" required value="<?= htmlspecialchars($user['last_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>">
                            </div>
                            <div class="form-group">
                                <label>ID Number *</label>
                                <input type="text" name="id_number" required value="<?= htmlspecialchars($user['id_number']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Speciality</label>
                                <input type="text" name="speciality" value="<?= htmlspecialchars($user['speciality']) ?>">
                            </div>
                            <div class="form-group">
                                <label>SLMC Number</label>
                                <input type="text" name="slmc_number" value="<?= htmlspecialchars($user['slmc_number']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Account Status</label>
                                <select name="status">
                                    <option value="active" <?= (isset($user['status']) && $user['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= (isset($user['status']) && $user['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>System Role</label>
                                <select name="role">
                                    <option value="User" <?= (isset($user['role']) && $user['role'] == 'User') ? 'selected' : '' ?>>User</option>
                                    <option value="Admin" <?= (isset($user['role']) && $user['role'] == 'Admin') ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>
                        </div>

                        <div class="admin-header" style="margin-top: 2rem;">
                            <h2><i class="fas fa-key"></i> Change Password (Leave blank to keep current)</h2>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" placeholder="Enter new password">
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" placeholder="Confirm new password">
                            </div>
                        </div>
                        <div style="margin-top: 1.5rem;">
                            <a href="admin_users.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Users</a>
                            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
