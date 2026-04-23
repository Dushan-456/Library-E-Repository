<?php
session_start();
// If already logged in, redirect to index
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: index.php");
    exit;
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
            max-width: 450px;
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
            margin-top: 1.5rem;
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
            height: 100vh;
        }
        .logo {
            width: 130px;
            height: 130px;
        }
        .qr-container {
            display: flex;
            justify-content: center;
            margin: 1rem 0;
        }
        .qr-image {
            width: 300px;
            height: 300px;
            object-fit: contain;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.5rem;
            background: white;
        }
        .instructions {
            color: #334155;
            font-size: 0.95rem;
            line-height: 1.8;
            margin-bottom: 1rem;
            text-align: left;
            background: var(--bg-main);
            padding: 1.25rem;
            border-radius: 8px;
            border: 1px dashed var(--border);
        }
        .instructions strong {
            color: var(--primary);
            margin-right: 0.5rem;
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
            
            <div class="qr-container">
                <img class="qr-image" src="./assets/img/qr.png" alt="Registration QR Code">
            </div>
            
            <div class="instructions">
                <div><strong>1.</strong> Scan the QR code above.</div>
                <div><strong>2.</strong> Fill out the online registration form.</div>
                <div><strong>3.</strong> Contact Library Staff to complete your account activation process.</div>
            </div>

            <a href="login.php" class="login-btn"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</body>
</html>
