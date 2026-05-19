<?php
session_start();
require_once __DIR__ . '/db_config.php';

// If already logged in, redirect to index
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: index.php");
    exit;
}

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $speciality = trim($_POST['speciality'] ?? '');
    $slmc_number = trim($_POST['slmc_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($first_name && $last_name && $email && $id_number && $password) {
        // Validate email domain
        $email_parts = explode('@', $email);
        $domain = strtolower(end($email_parts));

        if ($domain !== 'pgim.cmb.ac.lk') {
            $error = "Registration is restricted to the PGIM Emails (@pgim.cmb.ac.lk domain )";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'User';
                $status = 'inactive';

                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, speciality, id_number, slmc_number, role, status, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$first_name, $last_name, $email, $speciality, $id_number, $slmc_number, $role, $status, $password_hash]);
                $message = "Registration successful! Your account is pending activation by an administrator.Contact Library Staff to complete your account activation process.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Error: Email or ID Number already exists.";
                } else {
                    $error = "Database Error: " . $e->getMessage();
                }
            }
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PGIM Digital Library - Register</title>
    <link rel="icon" type="image/x-icon" href="./assets/img/logo without bg.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        .login-card {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 550px;
            text-align: center;
        }
        .login-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .login-header h2 {
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        .login-btn {
            display: inline-block;
            width: 100%;
            padding: 0.75rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            margin-top: 1rem;
            box-sizing: border-box;
        }
        .login-btn:hover {
            background: var(--primary-hover);
        }
        .login-body {
            background-image: url('./assets/img/bg.png') , linear-gradient( rgba(0, 0, 0, 0.68), rgba(0, 0, 0, 0.68));
            background-size: cover;
            background-position: center;
            background-blend-mode: overlay;
            display: flex;
            flex-direction: column;
            gap: 20px;
            align-items: center;
            justify-content: center;
            height: calc(100vh / var(--app-zoom, 1));
            padding: 2rem 0;
        }
        .logo {
            width: 130px;
            height: 130px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            text-align: left;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group.full-width {
            grid-column: span 2;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #475569;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            background: #f8fafc;
            box-sizing: border-box;
        }
        .form-group input:focus {
            border-color: var(--primary);
            background: white;
        }
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        .message-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .message-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        @media (max-width: 640px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-group.full-width {
                grid-column: span 1;
            }
        }

        .tabs-container {
            display: flex;
            border-bottom: 2px solid var(--border);
            margin-bottom: 1.5rem;
            justify-content: center;
            gap: 1.5rem;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 0.75rem 0.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .tab-btn:hover {
            color: var(--primary);
        }
        .tab-btn.active {
            color: var(--primary);
        }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--primary);
            border-radius: 2px;
        }
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }
        .tab-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .qr-container {
            display: flex;
            justify-content: center;
            margin: 1rem 0;
        }
        .qr-image {
            width: 260px;
            height: 260px;
            object-fit: contain;
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 0.5rem;
            background: white;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .qr-image:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .instructions {
            color: var(--text-main);
            font-size: 0.95rem;
            line-height: 1.8;
            margin-bottom: 1rem;
            text-align: left;
            background: var(--bg-main);
            padding: 1.25rem;
            border-radius: 8px;
            border: 1px dashed var(--border);
        }
    </style>
</head>
<body>
    <div class="login-body">
        <img class="logo" src="./assets/img/logo without bg.png" alt="PGIM Logo">
        <div class="login-card">
            <div class="login-header">
                <h2>Register Account</h2>
                <p style="color: #64748b; font-size: 0.875rem;">Join the PGIM Digital Library</p>
            </div>

            <?php if ($message): ?>
                <div class="message message-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message message-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!$message): ?>
            <div class="tabs-container">
                <button type="button" class="tab-btn active" id="btn-self-register" onclick="switchTab('self-register')">
                    <i class="fas fa-user-edit"></i> Self Register
                </button>
                <button type="button" class="tab-btn" id="btn-manual-method" onclick="switchTab('manual-method')">
                    <i class="fas fa-qrcode"></i> Manual Method
                </button>
            </div>

            <div id="tab-self-register" class="tab-content active">
                <form method="POST" action="register.php" onsubmit="return validateEmail()">
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
                            <input type="email" name="email" id="email" required
                                   pattern="^[a-zA-Z0-9._%+-]+@pgim\.cmb\.ac\.lk$"
                                   title="Please use a PGIM Email address">
                            <span id="email-error" style="color: #ef4444; font-size: 0.75rem; display: none; margin-top: 0.25rem;">
                                Email must be PGIM Email address.
                            </span>
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
                        <div class="form-group">
                            <label>Password *</label>
                            <input type="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password *</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                    </div>
                    <button type="submit" class="login-btn"><i class="fas fa-user-plus"></i> Register</button>
                </form>
            </div>

            <div id="tab-manual-method" class="tab-content">
                <div class="qr-container">
                    <a href="https://forms.gle/your-google-form-link" target="_blank" title="Click to open registration form in a new tab">
                        <img class="qr-image" src="./assets/img/new-qr.png" alt="Registration QR Code">
                    </a>
                </div>
                
                <div class="instructions">
                    <div><strong>1.</strong> Scan the QR code above  to fill out the form.</div>
                    <div><strong>2.</strong> Fill out the online registration form.</div>
                    <div><strong>3.</strong> Contact Library Staff to complete your account activation process.</div>
                </div>
            </div>
            <?php endif; ?>

            <a href="login.php" class="login-btn" style="background: transparent; color: var(--primary); border: 1px solid var(--primary);"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(function(content) {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(function(btn) {
                btn.classList.remove('active');
            });
            
            document.getElementById('tab-' + tabId).classList.add('active');
            document.getElementById('btn-' + tabId).classList.add('active');
        }

        function validateEmail() {
            var emailInput = document.getElementById('email');
            var emailError = document.getElementById('email-error');
            var email = emailInput.value.trim().toLowerCase();
            
            if (!email.endsWith('@pgim.cmb.ac.lk')) {
                emailError.style.display = 'block';
                emailInput.focus();
                return false;
            }
            emailError.style.display = 'none';
            return true;
        }

        document.getElementById('email').addEventListener('input', function() {
            var email = this.value.trim().toLowerCase();
            var emailError = document.getElementById('email-error');
            if (email === '' || email.endsWith('@pgim.cmb.ac.lk')) {
                emailError.style.display = 'none';
                this.setCustomValidity('');
            } else {
                emailError.style.display = 'block';
                this.setCustomValidity('Please use a @pgim.cmb.ac.lk email address');
            }
        });
    </script>
</body>
</html>

