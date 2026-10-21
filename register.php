<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

// ========== CHECK IF REGISTRATION IS ALLOWED ==========
$result = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key='allow_registration'");
$row = mysqli_fetch_assoc($result);
$allow_registration = $row['setting_value'] ?? '1';

if($allow_registration == '0'){
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Registration Closed</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f0f2f5;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }
            .closed-box {
                background: white;
                padding: 50px;
                border-radius: 16px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 450px;
            }
            .closed-box .icon { font-size: 70px; margin-bottom: 20px; }
            .closed-box h1 { color: #dc3545; margin-bottom: 10px; }
            .closed-box p { color: #666; line-height: 1.8; }
            .closed-box .btn {
                display: inline-block;
                padding: 12px 30px;
                background: #0b1f3a;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                margin-top: 15px;
            }
            .closed-box .btn:hover { background: #1a3a5c; }
            .dark-mode .closed-box {
                background: #16213e;
                color: white;
            }
            .dark-mode .closed-box h1 { color: #dc3545; }
            .dark-mode .closed-box p { color: #aaa; }
        </style>
    </head>
    <body>
        <div class='closed-box'>
            <div class='icon'>🚫</div>
            <h1>Registration Closed</h1>
            <p>New registrations are currently disabled by the admin.</p>
            <p>Please check back later or contact the library for assistance.</p>
            <a href='login.php' class='btn'>← Back to Login</a>
        </div>
    </body>
    </html>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Register - Library Management System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-section {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
            padding: 20px;
        }
        .form-box {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px;
        }
        .form-box h2 {
            text-align: center;
            color: #0b1f3a;
            margin-bottom: 25px;
        }
        .form-box input {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
            box-sizing: border-box;
        }
        .form-box input:focus {
            outline: none;
            border-color: #0b1f3a;
        }
        .form-box button {
            width: 100%;
            padding: 12px;
            background: #0b1f3a;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }
        .form-box button:hover {
            background: #1a3a5c;
        }
        .form-box p {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .form-box a {
            color: #0b1f3a;
            text-decoration: none;
            font-weight: bold;
        }
        .form-box a:hover {
            text-decoration: underline;
        }
        .error-msg {
            background: #f8d7da;
            color: #721c24;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid #f5c6cb;
        }
        .success-msg {
            background: #d4edda;
            color: #155724;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid #c3e6cb;
        }
        .dark-mode .form-box {
            background: #16213e;
            color: white;
        }
        .dark-mode .form-box h2 {
            color: white;
        }
        .dark-mode .form-box input {
            background: #1a1a3a;
            color: white;
            border-color: #333;
        }
        .dark-mode .form-box p {
            color: #aaa;
        }
        .dark-mode .form-box a {
            color: #ffc72c;
        }
        .dark-mode .error-msg {
            background: #2a1a1a;
            color: #f8a5a5;
            border-color: #5a1a1a;
        }
        .dark-mode .success-msg {
            background: #1a2a1a;
            color: #a5f8a5;
            border-color: #1a5a1a;
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav>
    <div class="logo">📖 Book's Library</div>
    <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="books.php">Books</a></li>
        <li><a href="login.php">Login</a></li>
        <li><a class="active" href="register.php">Register</a></li>
        <li><button onclick="toggleDarkMode()" class="dark-btn">🌙 Dark</button></li>
    </ul>
</nav>

<section class="form-section">
    <div class="form-box">

        <h2>📝 Create Account</h2>

        <?php
        if(isset($_SESSION['register_error'])){
            echo '<div class="error-msg">' . $_SESSION['register_error'] . '</div>';
            unset($_SESSION['register_error']);
        }
        if(isset($_SESSION['register_success'])){
            echo '<div class="success-msg">' . $_SESSION['register_success'] . '</div>';
            unset($_SESSION['register_success']);
        }
        ?>

        <form method="POST" action="register_process.php">
<?php echo csrf_field(); ?>

            <input type="text" 
                   name="fullname"
                   placeholder="Enter Full Name" 
                   required>

            <input type="email" 
                   name="email"
                   placeholder="Enter Email" 
                   required>

            <input type="password" 
                   name="password"
                   placeholder="Enter Password" 
                   required>

            <input type="password" 
                   name="confirm_password"
                   placeholder="Confirm Password" 
                   required>

            <button type="submit" name="register">
                Register
            </button>

        </form>

        <p>
            Already have an account? 
            <a href="login.php">Login here</a>
        </p>

    </div>
</section>

<script>
if(localStorage.getItem("theme") === "dark"){
    document.body.classList.add("dark-mode");
}

function toggleDarkMode(){
    document.body.classList.toggle("dark-mode");
    if(document.body.classList.contains("dark-mode")){
        localStorage.setItem("theme", "dark");
    }
    else{
        localStorage.setItem("theme", "light");
    }
}
</script>

</body>
</html>