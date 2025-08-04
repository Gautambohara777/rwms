<?php
session_start();
include "connect.php";

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Extract user ID safely from session
if (is_array($_SESSION['user'])) {
    // If session stores array, try to get 'id' or 'user_id' key
    $user_id = $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;
} else {
    $user_id = $_SESSION['user'];
}

if (!$user_id) {
    // If no user id found, force login
    header("Location: login.php");
    exit();
}

// Fetch pickup requests for this user only
$query = "SELECT * FROM pickup_requests WHERE user_id = '$user_id' ORDER BY pickup_date DESC";
$result = mysqli_query($con, $query);

if (!$result) {
    die("Database query failed: " . mysqli_error($con));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'include/header.php'; ?>
    <?php include 'include/sidebar.php'; ?>
    <meta charset="UTF-8" />
    <title>Pickup Request History</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f9f9f9;
        }
        h2 {
            text-align: center;
            color: #2e7d32;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            max-width: 900px;
            margin: 20px auto;
            background: white;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: center;
        }
        th {
            background-color: #2e7d32;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f3f3f3;
        }
        .status-pending {
            color: orange;
            font-weight: bold;
        }
        .status-completed {
            color: green;
            font-weight: bold;
        }
        .status-cancelled {
            color: red;
            font-weight: bold;
        }
        a.back-link {
            display: block;
            max-width: 900px;
            margin: 10px auto 40px;
            text-decoration: none;
            color: #2e7d32;
            font-weight: bold;
        }
        a.back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <h2>Your Pickup Request History</h2>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Waste Type</th>
                <th>Weight (kg)</th>
                <th>Rate per kg</th>
                <th>Total Price</th>
                <th>Pickup Date</th>
                <th>Address</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (mysqli_num_rows($result) > 0) {
                $count = 1;
                while ($row = mysqli_fetch_assoc($result)) {
                    $total_price = $row['rate'] * $row['weight'];
                    $status_class = '';
                    switch (strtolower($row['status'])) {
                        case 'pending':
                            $status_class = 'status-pending';
                            break;
                        case 'completed':
                            $status_class = 'status-completed';
                            break;
                        case 'cancelled':
                            $status_class = 'status-cancelled';
                            break;
                        default:
                            $status_class = '';
                    }
                    echo "<tr>
                        <td>" . $count++ . "</td>
                        <td>" . htmlspecialchars($row['name']) . "</td>
                        <td>" . htmlspecialchars($row['waste_type']) . "</td>
                        <td>" . number_format($row['weight'], 2) . "</td>
                        <td>" . number_format($row['rate'], 2) . "</td>
                        <td>" . number_format($total_price, 2) . "</td>
                        <td>" . htmlspecialchars($row['pickup_date']) . "</td>
                        <td>" . htmlspecialchars($row['address']) . "</td>
                        <td class='{$status_class}'>" . htmlspecialchars($row['status']) . "</td>
                    </tr>";
                }
            } else {
                echo "<tr><td colspan='9'>No pickup requests found.</td></tr>";
            }
            ?>
        </tbody>
    </table>

</body>
<?php include 'include/footer.php'; ?>
</html>
