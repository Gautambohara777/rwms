<?php
session_start();
if (isset($_SESSION['user'])) {
    header("Location: home.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Recycle Hub</title>
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
            justify-content: space-evenly;
        }

        .logo-section {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logo-section img {
    max-width: 100%;
    max-height: 800px; 
    width: 60%; 
}


        .login-section {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-box {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 380px;
        }

        .login-box h2 {
            text-align: center;
            margin-bottom: 25px;
            color: blue;
        }

        .input-group {
    margin-bottom: 30px;         /* Less vertical spacing */
    position: relative;
}

.input-group input {
    width: 90%;
    padding: 8px 35px 8px 10px;  /* Smaller padding */
    font-size: 14px;             /* Slightly smaller font */
    border: 1px solid #ccc;
    border-radius: 5px;
    outline: none;
}

.input-group i {
    position: absolute;
    right: 10px;
    top: 9px;
    font-size: 14px;             /* Icon also smaller */
    color: #666;
}


        .options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .options input[type="checkbox"] {
            margin-right: 5px;
        }

        .login-button {
            width: 100%;
            padding: 12px;
            border: none;
            background-color: blue;
            color: white;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
        }

        .login-button:hover {
            background-color: #0033cc;
        }

        .register-link {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }

        .register-link a {
            color: blue;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include 'include/header.php'; ?>
    <?php include 'include/sidebar.php'; ?>
<div class="container">
    <div class="logo-section">
        <img src="img/logo.png" alt="Recycle Hub Logo">
    </div>
    <div class="login-section">
        <form class="login-box" action="login_check.php" method="POST">
            <h2>Login</h2>

            <div class="input-group">
                <input type="email" name="email" placeholder="Email" required>
                <i class="fa fa-envelope"></i>
            </div>

            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
                <i class="fa fa-lock"></i>
            </div>

            <div class="options">
                <label><input type="checkbox" name="remember"> Remember</label>
                <a href="register.php">Register new user</a>
            </div>

            <button type="submit" class="login-button">Login</button>

            <div class="register-link">
                Don't have an account? <a href="register.php">Register here</a>
            </div>
        </form>
    </div>
</div>
<?php include 'include/footer.php'; ?>
</body>
</html>
