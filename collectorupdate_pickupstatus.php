<?php
session_start();
include "connect.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $status = $_POST['status'] ?? 'In Progress';
    $collected_items = $_POST['collected_items'] ?? null;
    $final_weight = $_POST['final_weight'] ?? null;
    $total_cost = $_POST['total_cost'] ?? null;

    $sql = "UPDATE pickup_requests 
            SET status=?, collected_items=?, final_weight=?, total_cost=? 
            WHERE id=?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "ssddi", $status, $collected_items, $final_weight, $total_cost, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo "success";
}
?>
