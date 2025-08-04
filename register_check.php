<?php
session_start();
include "connect.php";

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$address = $_POST['address'] ?? '';
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if ($password !== $confirm_password) {
    echo "<script>alert('Passwords do not match'); window.location='register.php';</script>";
    exit();
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Check if email exists
$check = mysqli_query($con, "SELECT * FROM users WHERE email='$email'");
if (mysqli_num_rows($check) > 0) {
    echo "<script>alert('Email already exists'); window.location='register.php';</script>";
    exit();
}

// Insert new user
$query = "INSERT INTO users (name, email, phone, address, password, role) 
          VALUES ('$name', '$email', '$phone', '$address', '$hashed_password', 'user')";

$result = mysqli_query($con, $query);

if ($result) {
    echo "<script>alert('Registration successful! You can now login.'); window.location='login.php';</script>";
} else {
    echo "<script>alert('Error: Registration failed.'); window.location='register.php';</script>";
}
?>
