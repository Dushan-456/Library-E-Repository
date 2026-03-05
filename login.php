<?php
session_start();
require_once __DIR__ . '/seb_check.php';

// Hard-coded credentials
$valid_username = "admin";
$valid_password = "password123";

$error = "";
$message = "";

// Check for timeout message
if (isset($_GET['msg']) && $_GET['msg'] === 'timeout') {
    $message = "Your session has expired due to 15 minutes of inactivity. Please login again.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === $valid_username && $password === $valid_password) {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['last_activity'] = time(); // Initialize activity time
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PGIM Digital Library - Login</title>
    <link rel="icon" type="image/x-icon" href="./assets/img/logo without bg.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
    
        .login-card {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h2 {
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
        }
        .form-group input:focus {
            border-color: var(--primary);
        }
        .login-btn {
            width: 100%;
            padding: 0.75rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .login-btn:hover {
            background: var(--primary-hover);
        }
        .error-msg {
            color: #ef4444;
            font-size: 0.875rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        .login-body{
            background-image: url('./assets/img/bg.png') , linear-gradient( rgba(0, 0, 0, 0.68), rgba(0, 0, 0, 0.68));
            background-size: cover;
            background-position: center;
            background-blend-mode: overlay;
            display: flex;
            flex-direction: column;
            gap: 20px;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .logo{
            width: 130px;
            height: 130px;
        }
    </style>
</head>
<body >
    <div class="login-body">

        <img class="logo" src="./assets/img/logo without bg.png" alt="PGIM Logo">
        <div class="login-card">
            <div class="login-header">
                <h2>PGIM E-Library</h2>
                <p style="color: #64748b; font-size: 0.875rem;">Please login to access the library Resources</p>
            </div>
            <?php if ($error): ?>
                <div class="error-msg"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="error-msg" style="color: #eab308;"><?php echo $message; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required placeholder="Username">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Password">
                </div>
                <button type="submit" class="login-btn">Login</button>
            </form>
        </div>
    </div>
</body>
</html>
