<?php
session_start();
include "connect.php";

// Only admin allowed
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle Role Change Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']);
    $new_role = mysqli_real_escape_string($con, $_POST['role']);

    // Update role in database
    $updateQuery = "UPDATE users SET role = '$new_role' WHERE id = $user_id";
    mysqli_query($con, $updateQuery);
}

// Fetch all users
$users = mysqli_query($con, "SELECT id, name, email, role FROM users ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'include/header.php'; ?>
    <title>Admin - Manage User Roles</title>
    <style>
        .main-content {
            margin-left: 1px; /* Adjust based on sidebar width */
            padding: 20px;
            min-height: calc(100vh - 60px); /* Adjust if header/footer height changes */
            background: #f5f5f5;
        }
        table { border-collapse: collapse; width: 100%; background: #fff; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: center; }
        th { background: #2e7d32; color: #fff; }
        select, button { padding: 5px; }
    </style>
</head>
<body>
    <?php include 'include/sidebar.php'; ?>

    <div class="main-content">
        <h2>Manage User Roles</h2>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Current Role</th>
                    <th>Change Role</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (mysqli_num_rows($users) > 0) {
                    $count = 1;
                    while ($user = mysqli_fetch_assoc($users)) {
                        echo "<tr>
                            <td>{$count}</td>
                            <td>" . htmlspecialchars($user['name']) . "</td>
                            <td>" . htmlspecialchars($user['email']) . "</td>
                            <td>" . htmlspecialchars($user['role']) . "</td>
                            <td>
                                <form method='post'>
                                    <input type='hidden' name='user_id' value='{$user['id']}'>
                                    <select name='role'>
                                        <option value='user' " . ($user['role'] === 'user' ? 'selected' : '') . ">User</option>
                                        <option value='collector' " . ($user['role'] === 'collector' ? 'selected' : '') . ">Collector</option>
                                        
                                    </select>
                                    <button type='submit'>Update</button>
                                </form>
                            </td>
                        </tr>";
                        $count++;
                    }
                } else {
                    echo "<tr><td colspan='5'>No users found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <?php include 'include/footer.php'; ?>
</body>
</html>
