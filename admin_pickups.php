<?php
session_start();
include "connect.php";

// Check if admin
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle form submission for updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = intval($_POST['request_id']);
    $new_status = mysqli_real_escape_string($con, $_POST['status']);
    $collector_id = intval($_POST['collector_id']);

    $updateQuery = "UPDATE pickup_requests SET status = '$new_status', assigned_collector_id = $collector_id WHERE id = $request_id";
    mysqli_query($con, $updateQuery);
}

// Fetch all pickup requests
$query = "SELECT pr.*, u.name as requester_name, c.name as collector_name FROM pickup_requests pr
          LEFT JOIN users u ON pr.user_id = u.id
          LEFT JOIN users c ON pr.assigned_collector_id = c.id
          ORDER BY pr.pickup_date DESC";
$result = mysqli_query($con, $query);

// Fetch all collectors
$collectors = mysqli_query($con, "SELECT id, name FROM users WHERE role = 'collector'");
$collectorOptions = [];
while ($col = mysqli_fetch_assoc($collectors)) {
    $collectorOptions[$col['id']] = $col['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Manage Pickup Requests</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        table { border-collapse: collapse; width: 100%; background: #fff; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: center; }
        th { background: #2e7d32; color: #fff; }
        form { display: flex; flex-direction: column; gap: 5px; }
        select, button { padding: 5px; }
    </style>
</head>
<body>

<h2>Manage Pickup Requests</h2>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Requester</th>
            <th>Waste Type</th>
            <th>Weight</th>
            <th>Pickup Date</th>
            <th>Status</th>
            <th>Assigned Collector</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if (mysqli_num_rows($result) > 0) {
            $count = 1;
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>
                    <td>{$count}</td>
                    <td>" . htmlspecialchars($row['requester_name']) . "</td>
                    <td>" . htmlspecialchars($row['waste_type']) . "</td>
                    <td>" . htmlspecialchars($row['weight']) . " kg</td>
                    <td>" . htmlspecialchars($row['pickup_date']) . "</td>
                    <td>
                        <form method='post'>
                            <input type='hidden' name='request_id' value='{$row['id']}'>
                            <select name='status'>
                                <option value='Pending' " . ($row['status'] === 'Pending' ? 'selected' : '') . ">Pending</option>
                                <option value='Approved' " . ($row['status'] === 'Approved' ? 'selected' : '') . ">Approved</option>
                                <option value='Completed' " . ($row['status'] === 'Completed' ? 'selected' : '') . ">Completed</option>
                                <option value='Cancelled' " . ($row['status'] === 'Cancelled' ? 'selected' : '') . ">Cancelled</option>
                            </select>
                    </td>
                    <td>
                            <select name='collector_id'>
                                <option value='0'>-- None --</option>";
                                foreach ($collectorOptions as $id => $name) {
                                    $selected = ($row['assigned_collector_id'] == $id) ? 'selected' : '';
                                    echo "<option value='$id' $selected>$name</option>";
                                }
                echo       "</select>
                    </td>
                    <td>
                            <button type='submit'>Update</button>
                        </form>
                    </td>
                </tr>";
                $count++;
            }
        } else {
            echo "<tr><td colspan='8'>No pickup requests found.</td></tr>";
        }
        ?>
    </tbody>
</table>

</body>
</html>
