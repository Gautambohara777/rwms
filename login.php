<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Register | Recycle Hub</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        /* Custom styles for the background gradient */
        .bg-gradient-custom {
            background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 100%);
        }
        .form-container {
            width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        .form-wrapper {
            width: 200%;
            display: flex;
            transition: transform 0.5s ease-in-out;
        }
        .form-section {
            width: 50%;
            flex-shrink: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        /* Fixed CSS selectors to target the parent container */
        .login-active .form-wrapper {
            transform: translateX(0);
        }
        .register-active .form-wrapper {
            transform: translateX(-50%);
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex flex-col justify-between">
    <?php
    session_start();
    include "connect.php";
    // Check if the form has been submitted
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $action = $_POST['action'] ?? '';

        if ($action === 'login') {
            // LOGIN LOGIC
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $email = mysqli_real_escape_string($con, $email);

            if (empty($email) || empty($password)) {
                echo "<script>alert('Email and password are required');</script>";
            } else {
                $sql = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
                $result = mysqli_query($con, $sql);
                if ($result && mysqli_num_rows($result) > 0) {
                    $user = mysqli_fetch_assoc($result);
                    if (password_verify($password, $user['password'])) {
                        $_SESSION['user'] = $user['id'];
                        $_SESSION['user_role'] = $user['role']; 
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];

                        if ($user['role'] === 'admin') {
                            header("Location: admin_dashboard.php");
                        } elseif ($user['role'] === 'collector') {
                            header("Location: collector_dashboard.php");
                        } else {
                            header("Location: home.php");
                        }
                        exit();
                    } else {
                        echo "<script>alert('Incorrect password');</script>";
                    }
                } else {
                    echo "<script>alert('User not found');</script>";
                }
            }
        } elseif ($action === 'register') {
            // REGISTRATION LOGIC
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($password) || empty($confirm_password)) {
                echo "<script>alert('All fields are required for registration.');</script>";
            } elseif ($password !== $confirm_password) {
                echo "<script>alert('Passwords do not match.');</script>";
            } else {
                $check_email_sql = "SELECT id FROM users WHERE email = '$email' LIMIT 1";
                $check_result = mysqli_query($con, $check_email_sql);
                if (mysqli_num_rows($check_result) > 0) {
                    echo "<script>alert('Email already registered.');</script>";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $insert_sql = "INSERT INTO users (name, email, phone, address, password, role) VALUES ('$name', '$email', '$phone', '$address', '$hashed_password', 'user')";
                    if (mysqli_query($con, $insert_sql)) {
                        echo "<script>alert('Registration successful! You can now log in.');</script>";
                    } else {
                        echo "<script>alert('Registration failed. Please try again.');</script>";
                    }
                }
            }
        }
    }
    ?>
    
    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center p-4 bg-gradient-custom">
        <div class="flex flex-col md:flex-row w-full max-w-6xl bg-white rounded-2xl shadow-2xl overflow-hidden">
            
            <!-- Left Section (Image/Logo) -->
            <div class="w-full md:w-1/2 p-8 flex items-center justify-center bg-gray-100 hidden md:flex">
                <a href="home.php">
                    <img src="img/e.png" alt="Recycle Hub Logo" class="rounded-lg shadow-md max-h-full w-full object-contain">
            </a>
        </div>

            <!-- Right Section (Form Container) -->
            <div class="w-full md:w-1/2 p-8 md:p-12 flex items-center justify-center">
                <div id="form-container" class="form-container">
                    <div class="form-wrapper">

                        <!-- Login Form -->
                        <div id="login-form-section" class="form-section">
                            <div class="w-full max-w-sm">
                                <div class="text-center mb-8">
                                    <a href="home.php">
                                    <img src="img/logo.png" alt="Recycle Hub Logo" class="mx-auto w-32 h-32 object-contain">
                                    </a>
                                </div>
                                <p class="text-center text-gray-600 mb-8">Login to your account</p>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                                    <input type="hidden" name="action" value="login">
                                    <!-- Email Input -->
                                    <div class="mb-6 relative">
                                        <label for="login-email" class="sr-only">Email</label>
                                        <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-green-500 transition duration-300">
                                            <i class="fa fa-envelope text-gray-400 p-3"></i>
                                            <input type="email" id="login-email" name="email" placeholder="Email" required class="w-full p-2 text-gray-700 outline-none">
                                        </div>
                                    </div>
                                    <!-- Password Input -->
                                    <div class="mb-4 relative">
                                        <label for="login-password" class="sr-only">Password</label>
                                        <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-green-500 transition duration-300">
                                            <i class="fa fa-lock text-gray-400 p-3"></i>
                                            <input type="password" id="login-password" name="password" placeholder="Password" required class="w-full p-2 text-gray-700 outline-none">
                                        </div>
                                    </div>
                                    <!-- Options and Forgot Password -->
                                    <div class="flex justify-between items-center text-sm mb-6 text-gray-600">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="remember" class="form-checkbox text-green-600 rounded-sm mr-2">
                                            <span>Remember me</span>
                                        </label>
                                        <a href="#" class="text-green-600 hover:underline transition duration-300">Forgot password?</a>
                                    </div>
                                    <!-- Login Button -->
                                    <button type="submit" class="w-full bg-green-600 text-white font-semibold py-3 px-4 rounded-lg hover:bg-green-700 transition duration-300 shadow-md">
                                        Login
                                    </button>
                                    <!-- Switch to Register -->
                                    <div class="text-center text-gray-600 text-sm mt-6">
                                        Don't have an account? <a href="#" id="show-register" class="text-green-600 font-semibold hover:underline">Register here</a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Registration Form -->
                        <div id="register-form-section" class="form-section">
                            <div class="w-full max-w-sm">
                                <h2 class="text-3xl font-bold text-center text-green-700 mb-2">Register</h2>
                                <p class="text-center text-gray-600 mb-8">Create your new account</p>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                                    <input type="hidden" name="action" value="register">
                                    <!-- Name Input -->
                                    <div class="mb-4 relative">
                                        <label for="reg-name" class="sr-only">Full Name</label>
                                        <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-green-500 transition duration-300">
                                            <i class="fa fa-user text-gray-400 p-3"></i>
                                            <input type="text" id="reg-name" name="name" placeholder="Full Name" required class="w-full p-2 text-gray-700 outline-none">
                                        </div>
                                    </div>
                                    <!-- Email Input -->
                                    <div class="mb-4 relative">
                                        <label for="reg-email" class="sr-only">Email</label>
                                        <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-green-500 transition duration-300">
                                            <i class="fa fa-envelope text-gray-400 p-3"></i>
                                            <input type="email" id="reg-email" name="email" placeholder="Email" required class="w-full p-2 text-gray-700 outline-none">
                                        </div>
                                    </div>
                                    <!-- Phone Input -->
                                    <div class="mb-4 relative">
                                        <label for="reg-phone" class="sr-only">Phone Number</label>
                                        <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-green-500 transition duration-300">
                                            <i class="fa fa-phone text-gray-400 p-3"></i>
                                            <input type="text" id="reg-phone" name="phone" placeholder="Phone Number" required class="w-full p-2 text-gray-700 outline-none">
                                        </div>
                                    </div>
                                    <!-- Address Textarea -->
                                    <div class="mb-4 relative">
                                        <label for="reg-address" class="sr-only">Address</label>
                                        <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-green-500 transition duration-300">
                                            <i class="fa fa-map-marker-alt text-gray-400 p-3 self-start"></i>
                                            <textarea id="reg-address" name="address" placeholder="Address" rows="2" required class="w-full p-2 text-gray-700 outline-none resize-none"></textarea>
                                        </div>
                                    </div>
                                    <!-- Password Input -->
                                    <div class="mb-4 relative">
                                        <label for="reg-password" class="sr-only">Password</label>
                                        <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-green-500 transition duration-300">
                                            <i class="fa fa-lock text-gray-400 p-3"></i>
                                            <input type="password" id="reg-password" name="password" placeholder="Password" required class="w-full p-2 text-gray-700 outline-none">
                                        </div>
                                    </div>
                                    <!-- Confirm Password Input -->
                                    <div class="mb-6 relative">
                                        <label for="reg-confirm-password" class="sr-only">Confirm Password</label>
                                        <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-green-500 transition duration-300">
                                            <i class="fa fa-lock text-gray-400 p-3"></i>
                                            <input type="password" id="reg-confirm-password" name="confirm_password" placeholder="Confirm Password" required class="w-full p-2 text-gray-700 outline-none">
                                        </div>
                                    </div>
                                    <!-- Register Button -->
                                    <button type="submit" class="w-full bg-green-600 text-white font-semibold py-3 px-4 rounded-lg hover:bg-green-700 transition duration-300 shadow-md">
                                        Register
                                    </button>
                                    <!-- Switch to Login -->
                                    <div class="text-center text-gray-600 text-sm mt-6">
                                        Already have an account? <a href="#" id="show-login" class="text-green-600 font-semibold hover:underline">Login here</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

     

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const formContainer = document.getElementById('form-container');
            const showRegisterBtn = document.getElementById('show-register');
            const showLoginBtn = document.getElementById('show-login');

            // Set initial state based on URL hash (if any)
            if (window.location.hash === '#register') {
                formContainer.classList.add('register-active');
            } else {
                formContainer.classList.add('login-active');
            }

            // Handle switching to registration form
            showRegisterBtn.addEventListener('click', (e) => {
                e.preventDefault();
                formContainer.classList.remove('login-active');
                formContainer.classList.add('register-active');
                history.pushState(null, null, '#register');
            });

            // Handle switching to login form
            showLoginBtn.addEventListener('click', (e) => {
                e.preventDefault();
                formContainer.classList.remove('register-active');
                formContainer.classList.add('login-active');
                history.pushState(null, null, '#login');
            });
        });
    </script>
</body>
</html>
