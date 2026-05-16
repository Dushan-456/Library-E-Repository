<?php
session_start();
require_once __DIR__ . '/seb_check.php';
require_once __DIR__ . '/db_config.php';

// Authentication Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/session_timeout.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch User Data for Profile
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

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (password_verify($current_password, $user['password_hash'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                try {
                    $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $updateStmt->execute([$new_hash, $user_id]);
                    $message = "Password updated successfully.";
                } catch (PDOException $e) {
                    $error = "Update failed: " . $e->getMessage();
                }
            } else {
                $error = "New password must be at least 6 characters long.";
            }
        } else {
            $error = "New passwords do not match.";
        }
    } else {
        $error = "Incorrect current password.";
    }
}

$pending_count = 0;
if ($_SESSION['role'] === 'Admin') {
    try {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'inactive'");
        $pending_count = $countStmt->fetchColumn();
    } catch (PDOException $e) {
        // Ignore
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - PGIM Digital Library</title>
    <link rel="icon" type="image/x-icon" href="./assets/img/logo without bg.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <script src="assets/js/theme.js?v=<?php echo time(); ?>"></script>
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            padding: 1rem;
        }
        .profile-card {
            background: var(--bg-card);
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }
        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), #8b5cf6);
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: var(--bg-active);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--primary);
            border: 3px solid var(--border);
        }
        .profile-title h1 {
            font-size: 1.5rem;
            margin: 0;
            color: var(--text-main);
        }
        .profile-title p {
            margin: 0.25rem 0 0;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .info-grid {
            display: grid;
            gap: 1.5rem;
        }
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .info-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .info-value {
            font-size: 1rem;
            color: var(--text-main);
            font-weight: 500;
        }
        .security-form {
            display: grid;
            gap: 1.25rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .form-group label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-main);
        }
        .form-group input {
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--bg-input);
            color: var(--text-main);
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            border-color: var(--primary);
        }
        .btn-save {
            padding: 0.875rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 0.5rem;
        }
        .btn-save:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        @media (max-width: 1024px) {
            .profile-container { grid-template-columns: 1fr; }
        }
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
            <div class="user-profile">
                <i class="fas fa-user-circle"></i>
                <div class="user-info-text">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </div>
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

        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>PGIM Digital Library</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li onclick="window.location.href='index.php'">
                        <i class="fas fa-home"></i> <span>Home</span>
                    </li>
                    <?php if ($_SESSION['role'] === 'Admin'): ?>
                    <li style="margin-top: 1rem; padding-left: 1.5rem; font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; cursor: default; pointer-events: none; border: none;">
                        Admin Panel
                    </li>
                    <li onclick="window.location.href='admin_create_user.php'">
                        <i class="fas fa-user-plus"></i> <span>Add New User</span>
                    </li>
                    <li onclick="window.location.href='admin_pending_users.php'">
                        <i class="fas fa-user-clock"></i> <span>Pending Activations</span>
                        <?php if ($pending_count > 0): ?>
                            <span style="background: #ef4444; color: white; padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; margin-left: auto;"><?= $pending_count ?></span>
                        <?php endif; ?>
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
                    <li class="active" onclick="window.location.href='profile.php'">
                        <i class="fas fa-id-card"></i> <span>My Profile</span>
                    </li>
                </ul>
            </nav>
             <div class="sidebar-footer">
                <p>&copy; 2026 PGIM Library -V1.2</p>
                <p>Developed by PGIM IT Unit - Dushan</p>
            </div>
        </aside>

        <main class="main-content">
            <div class="content-view">
                <div class="view-header">
                    <h1>My Account Settings</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>

                <div class="profile-container">
                    <!-- Personal Info Card -->
                    <div class="profile-card">
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="profile-title">
                                <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                                <p><?php echo htmlspecialchars($user['role']); ?> Account</p>
                            </div>
                        </div>

                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Email Address</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Identification Number</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['id_number']); ?></span>
                            </div>
                            <?php if ($user['slmc_number']): ?>
                            <div class="info-item">
                                <span class="info-label">SLMC Number</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['slmc_number']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($user['speciality']): ?>
                            <div class="info-item">
                                <span class="info-label">Speciality</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['speciality']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-item">
                                <span class="info-label">Member Since</span>
                                <span class="info-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Security Card -->
                    <div class="profile-card">
                        <div class="profile-header">
                            <div class="profile-avatar" style="color: #ef4444; background: rgba(239, 68, 68, 0.1);">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="profile-title">
                                <h1>Security</h1>
                                <p>Update your account password</p>
                            </div>
                        </div>

                        <form method="POST" class="security-form">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" required placeholder="Enter current password">
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" required placeholder="Enter new password">
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" required placeholder="Confirm new password">
                            </div>
                            <button type="submit" class="btn-save">Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
