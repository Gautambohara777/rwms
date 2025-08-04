<?php
session_start();
include "connect.php";

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($con, $_POST['name']);
    $waste_type = mysqli_real_escape_string($con, $_POST['waste_type']);
    $other_waste_type = isset($_POST['other_waste_type']) ? mysqli_real_escape_string($con, $_POST['other_waste_type']) : '';
    $weight = floatval($_POST['weight']);
    $pickup_date = mysqli_real_escape_string($con, $_POST['pickup_date']);
    $address = mysqli_real_escape_string($con, $_POST['address']);
    $latitude = floatval($_POST['latitude']);
    $longitude = floatval($_POST['longitude']);
    $rate_per_kg = floatval($_POST['rate_per_kg']);  // Coming from hidden input in form

    // If 'Other' is selected, replace waste_type with user input
    if ($waste_type === 'Other') {
        $waste_type = $other_waste_type;
        $rate_per_kg = 0;
    }

    // Insert data into pickup_requests table
    $insertQuery = "INSERT INTO pickup_requests 
        (user_id, name, waste_type, rate, weight, pickup_date, address, latitude, longitude, status)
        VALUES
        ('$user_id', '$name', '$waste_type', '$rate_per_kg', '$weight', '$pickup_date', '$address', '$latitude', '$longitude', 'Pending')";

    $insert = mysqli_query($con, $insertQuery);

    if ($insert) {
        echo "<script>alert('Pickup request submitted successfully!'); window.location.href='pickup_history.php';</script>";
    } else {
        echo "Error: " . mysqli_error($con);
    }

} else {
    header("Location: sell.php");
    exit();
}
?>
