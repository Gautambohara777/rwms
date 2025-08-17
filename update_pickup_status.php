<?php
session_start();
include "connect.php";

// Only collectors can update their assigned pickups
if (!isset($_SESSION['user']) || ($_SESSION['user_role'] ?? '') !== 'collector') {
    http_response_code(403);
    exit('Forbidden');
}

$collector_id = (int)$_SESSION['user'];
$id = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';

if (!$id || !in_array($status, ['collected'], true)) {
    http_response_code(400);
    exit('Invalid data');
}

// Ensure the pickup belongs to this collector
$check = mysqli_prepare($con, "SELECT 1 FROM pickup_requests WHERE id=? AND assigned_collector_id=? LIMIT 1");
mysqli_stmt_bind_param($check, "ii", $id, $collector_id);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);
if (mysqli_stmt_num_rows($check) === 0) {
    http_response_code(403);
    exit('Not allowed');
}
mysqli_stmt_close($check);

// Update status
$stmt = mysqli_prepare($con, "UPDATE pickup_requests SET status=? WHERE id=?");
mysqli_stmt_bind_param($stmt, "si", $status, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header('Content-Type: text/plain');
echo "OK";
