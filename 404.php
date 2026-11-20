<?php http_response_code(404); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page not found</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px}
        .box{background:#fff;padding:40px;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.1);text-align:center;max-width:460px}
        h1{color:#0b1f3a;font-size:64px;margin:0}
        a{color:#0b1f3a;font-weight:bold}
    </style>
</head>
<body>
<div class="box">
    <h1>404</h1>
    <p>The page you are looking for does not exist or you do not have access to it.</p>
    <p><a href="index.php">← Back to Home</a></p>
</div>
</body>
</html>
