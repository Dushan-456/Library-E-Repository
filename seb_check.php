<?php
// Set to true to ONLY allow access via Safe Exam Browser (SEB)
// Set to false to allow any browser
$safe_browser_only = false;

if ($safe_browser_only) {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    // SEB typically includes "SEB" or "SafeExamBrowser" in its user agent
    if (stripos($userAgent, 'SEB') === false && stripos($userAgent, 'SafeExamBrowser') === false) {
        die("<!DOCTYPE html>
<html>
<head>
    <title>Access Denied</title>
    <link rel='icon' type='image/x-icon' href='./assets/img/logo without bg.png'>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8d7da; color: #721c24; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .message-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: 1px solid #f5c6cb; text-align: center; max-width: 500px; }
        h1 { margin-top: 0; color: #721c24; }
    </style>
</head>
<body>
    <div class='message-box'>
    <img style='width: 100px; height: 100px;' src='./assets/img/denied.png' alt='Access Denied'>
        <h1>Access Restricted</h1>
        <p>This website can only be opened using the <strong>Safe Exam Browser</strong>.</p>
        <p>Please launch the Safe Exam Browser to access the digital library.</p>
        <a href='https://safeexambrowser.org/download_en.html'>Download Safe Exam Browser</a>
    </div>
</body>
</html>");
    }
}
?>
