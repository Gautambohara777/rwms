<?php
session_start();
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | Recycle Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #edf2f7;
        }

        .container {
            display: flex;
            height: 100vh;
            align-items: center;
            justify-content: center;
        }

        .logo-section {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logo-section img {
            max-width: 100%;
            max-height: 600px;
        }

        .register-section {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .register-box {
            background-color: white;
            padding: 60px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 380px;
        }

        .register-box h2 {
            text-align: center;
            margin-bottom: 25px;
            color: blue;
        }

        .input-group {
            margin-bottom: 20px;
            position: relative;
        }

        .input-group input, .input-group textarea {
            width: 100%;
            padding: 12px 40px 12px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            outline: none;
        }

        .input-group i {
            position: absolute;
            right: 12px;
            top: 12px;
            color: #666;
        }

        .register-button {
            width: 100%;
            padding: 12px;
            border: none;
            background-color: blue;
            color: white;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
        }

        .register-button:hover {
            background-color: #0033cc;
        }

        .login-link {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }

        .login-link a {
            color: blue;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="logo-section">
        <img src="img/logo.png" alt="Recycle Hub Logo">
    </div>
    <div class="register-section">
        <form class="register-box" method="POST" action="register_check.php">
            <h2>Register</h2>

            <div class="input-group">
                <input type="text" name="name" placeholder="Full Name" required>
                <i class="fa fa-user"></i>
            </div>

            <div class="input-group">
                <input type="email" name="email" placeholder="Email" required>
                <i class="fa fa-envelope"></i>
            </div>

            <div class="input-group">
                <input type="text" name="phone" placeholder="Phone Number" required>
                <i class="fa fa-phone"></i>
            </div>

            <div class="input-group">
                <textarea name="address" placeholder="Address" rows="2" required></textarea>
                <i class="fa fa-map-marker-alt"></i>
            </div>

            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
                <i class="fa fa-lock"></i>
            </div>

            <div class="input-group">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <i class="fa fa-lock"></i>
            </div>

            <button type="submit" class="register-button">Register</button>

            <div class="login-link">
                Already have an account? <a href="login.php">Login</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
