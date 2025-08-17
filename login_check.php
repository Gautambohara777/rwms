<?php
session_start();
include "connect.php"; // ensures $con is available

// Get and sanitize input
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Escape email for safety
$email = mysqli_real_escape_string($con, $email);

// Check if email or password is empty
if (empty($email) || empty($password)) {
    echo "<script>alert('Email and password are required'); window.location='login.php';</script>";
    exit();
}

// Query user by email
$sql = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
$result = mysqli_query($con, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);

    // Verify password
    if (password_verify($password, $user['password'])) {
        // ✅ Store user info in session
        $_SESSION['user'] = $user['id'];
        $_SESSION['user_role'] = $user['role']; 
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];

        // ✅ Redirect based on role
        if ($user['role'] === 'admin') {
            header("Location: admin_dashboard.php");
        } elseif ($user['role'] === 'collector') {
            header("Location: collector_dashboard.php");
        } else {
            header("Location: home.php"); // default for normal users
        }
        exit();
    } else {
        echo "<script>alert('Incorrect password'); window.location='login.php';</script>";
    }
} else {
    echo "<script>alert('User not found'); window.location='login.php';</script>";
}
?>
