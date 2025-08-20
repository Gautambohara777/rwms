<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Admin Dashboard</title>
    <!-- Include Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Use a custom font from Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Custom modal overlay */
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <?php
    // Start the session to access session variables.
    session_start();

    // Check if the user is an admin. If not, redirect to the login page.
    if (!isset($_SESSION['user']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        echo '
        <div class="bg-gray-100 min-h-screen flex items-center justify-center">
            <div class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-md text-center">
                <h2 class="text-3xl font-bold text-red-600 mb-4">Access Denied</h2>
                <p class="text-gray-600 mb-6">You must be logged in as an administrator to view this page.</p>
                <a href="login.php" class="bg-indigo-600 text-white py-2 px-4 rounded-xl hover:bg-indigo-700 transition-colors">Go to Login</a>
            </div>
        </div>
        ';
        // Stop script execution to prevent the dashboard from loading.
        exit();
    }

    // Include the header and database connection files.
    include_once 'include/header.php';
    include_once 'connect.php';

    // Check if the mysqli connection object is set.
    if (!isset($con) || $con->connect_error) {
        die("Error: The database connection failed. Please ensure 'connect.php' is in the same directory and the credentials are correct.");
    }

    // Simple PHP-based "router" to determine the current page.
    $currentPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

    // Define navigation items with their display names and corresponding page keys.
    $navItems = [
        'dashboard' => ['name' => 'Dashboard Overview', 'icon' => 'M12 6.5l-10 6 10 6 10-6-10-6zM12 2l10 6-10 6-10-6L12 2zM2 18l10 6 10-6'],
        'users' => ['name' => 'User Management', 'icon' => 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'],
        'waste_rates' => ['name' => 'Waste Rates', 'icon' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zM11 7h2v6h-2z'],
        'new_pickups' => ['name' => 'New Pickup Requests', 'icon' => 'M14 10h-2v4h2v-4zm-2 6h-2v2h2v-2zm4-6h-2v4h2v-4zm-4-4h2v2h-2V6z'],
        'verify_pickups' => ['name' => 'Verify Pickups', 'icon' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z'],
        'refused_pickups' => ['name' => 'Refused Pickups', 'icon' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 13.59L15.59 15 12 11.41 8.41 15 7 13.59 10.59 10 7 6.41 8.41 5 12 8.59 15.59 5 17 6.41 13.41 10 17 13.59z'],
        'reports' => ['name' => 'Reports', 'icon' => 'M22 6h-4V4c0-1.1-.9-2-2-2h-4c-1.1 0-2 .9-2 2v2H2c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h20c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6-2h-4v2h4V4zm6 16H2V8h20v12zM9 10h2v8H9v-8zm4 0h2v8h-2v-8zm4 0h2v8h-2v-8z']
    ];

    // Fetch all collectors for use in forms
    $collectorsResult = $con->query("SELECT id, name FROM users WHERE role = 'collector'");
    $collectorOptions = [];
    if ($collectorsResult) {
        while ($col = $collectorsResult->fetch_assoc()) {
            $collectorOptions[$col['id']] = $col['name'];
        }
    }
    ?>

    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-gray-800 text-white w-64 p-4 shadow-lg rounded-r-2xl hidden md:block">
            <div class="flex flex-col items-center mb-6 mt-2">
                <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center font-bold text-xl mb-2">
                    R
                </div>
                <h1 class="text-xl font-bold">Recycle Admin</h1>
            </div>
            <nav>
                <ul>
                    <?php foreach ($navItems as $key => $item): ?>
                        <li class="mb-2">
                            <a href="?page=<?php echo $key; ?>" class="flex items-center w-full py-3 px-4 rounded-xl text-left transition-colors <?php echo ($currentPage === $key) ? 'bg-green-600 font-semibold' : 'hover:bg-gray-700'; ?>">
                                <!-- Icon using SVG for simplicity -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="mr-3" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                  <path d="<?php echo $item['icon']; ?>" />
                                </svg>
                                <span><?php echo $item['name']; ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 p-6 overflow-y-auto">
            <?php
            // Use a switch statement to include the content for the selected page.
            switch ($currentPage) {
                case 'dashboard':
                    // --- Dashboard Overview content ---
                    $totalUsersResult = $con->query("SELECT COUNT(*) AS total_users FROM users WHERE role = 'user'");
                    $totalUsers = ($totalUsersResult && $totalUsersResult->num_rows > 0) ? $totalUsersResult->fetch_assoc()['total_users'] : 0;

                    $totalCollectorsResult = $con->query("SELECT COUNT(*) AS total_collectors FROM users WHERE role = 'collector'");
                    $totalCollectors = ($totalCollectorsResult && $totalCollectorsResult->num_rows > 0) ? $totalCollectorsResult->fetch_assoc()['total_collectors'] : 0;

                    $expectedWasteResult = $con->query("SELECT SUM(final_weight) AS total_expected FROM pickup_requests WHERE status = 'Collected'");
                    $expectedWaste = ($expectedWasteResult && $expectedWasteResult->num_rows > 0) ? $expectedWasteResult->fetch_assoc()['total_expected'] : 0;

                    $verifiedWasteResult = $con->query("SELECT SUM(final_weight) AS total_verified FROM pickup_requests WHERE status = 'Completed'");
                    $verifiedWaste = ($verifiedWasteResult && $verifiedWasteResult->num_rows > 0) ? $verifiedWasteResult->fetch_assoc()['total_verified'] : 0;

                    $newRequestsResult = $con->query("SELECT COUNT(*) AS new_requests FROM pickup_requests WHERE status = 'Pending' AND assigned_collector_id IS NULL");
                    $newRequests = ($newRequestsResult && $newRequestsResult->num_rows > 0) ? $newRequestsResult->fetch_assoc()['new_requests'] : 0;

                    $assignedPickupsResult = $con->query("SELECT COUNT(*) AS assigned_pickups FROM pickup_requests WHERE status = 'Approved' AND assigned_collector_id IS NOT NULL");
                    $assignedPickups = ($assignedPickupsResult && $assignedPickupsResult->num_rows > 0) ? $assignedPickupsResult->fetch_assoc()['assigned_pickups'] : 0;

                    $completedPickupsResult = $con->query("SELECT COUNT(*) AS completed_pickups FROM pickup_requests WHERE status = 'Completed'");
                    $completedPickups = ($completedPickupsResult && $completedPickupsResult->num_rows > 0) ? $completedPickupsResult->fetch_assoc()['completed_pickups'] : 0;

                    $refusedPickupsResult = $con->query("SELECT COUNT(*) AS refused_pickups FROM pickup_requests WHERE status = 'Refused'");
                    $refusedPickups = ($refusedPickupsResult && $refusedPickupsResult->num_rows > 0) ? $refusedPickupsResult->fetch_assoc()['refused_pickups'] : 0;
                    
                    $fullyCancelledPickupsResult = $con->query("SELECT COUNT(*) AS cancelled_pickups FROM pickup_requests WHERE status = 'Fully Cancelled'");
                    $fullyCancelledPickups = ($fullyCancelledPickupsResult && $fullyCancelledPickupsResult->num_rows > 0) ? $fullyCancelledPickupsResult->fetch_assoc()['cancelled_pickups'] : 0;

                    $stats = [
                        ['name' => 'Total Expected Waste (kg)', 'value' => number_format($expectedWaste, 2), 'color' => 'bg-green-500'],
                        ['name' => 'Total Verified Waste (kg)', 'value' => number_format($verifiedWaste, 2), 'color' => 'bg-blue-500'],
                        ['name' => 'Total Users', 'value' => $totalUsers, 'color' => 'bg-yellow-500'],
                        ['name' => 'Total Collectors', 'value' => $totalCollectors, 'color' => 'bg-indigo-500'],
                        ['name' => 'New Requests', 'value' => $newRequests, 'color' => 'bg-orange-500'],
                        ['name' => 'Assigned Pickups', 'value' => $assignedPickups, 'color' => 'bg-purple-500'],
                        ['name' => 'Completed Pickups', 'value' => $completedPickups, 'color' => 'bg-teal-500'],
                        ['name' => 'Refused Pickups', 'value' => $refusedPickups, 'color' => 'bg-red-500']
                    ];

                    $ratesResult = $con->query("SELECT waste_type, rate_per_kg FROM waste_rates");

                    if ($ratesResult) {
                        $rates = $ratesResult->fetch_all(MYSQLI_ASSOC);
                    } else {
                        $rates = [];
                        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">The waste_rates table could not be found or has different column names. Please verify your database schema.</p></div>';
                    }

                    $recentPickupsResult = $con->query("SELECT * FROM pickup_requests ORDER BY created_at DESC LIMIT 5");

                    if ($recentPickupsResult) {
                        $recentPickups = $recentPickupsResult->fetch_all(MYSQLI_ASSOC);
                    } else {
                        $recentPickups = [];
                        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">The pickup_requests table could not be found.</p></div>';
                    }
                    ?>

                    <h2 class="text-4xl font-bold text-gray-800 mb-8">Dashboard Overview</h2>
                    <div class="flex flex-col lg:flex-row gap-8">
                        <div class="w-full lg:w-2/3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                                <?php foreach ($stats as $stat): ?>
                                    <div class="rounded-2xl shadow-lg p-6 text-white <?php echo $stat['color']; ?>">
                                        <p class="text-base uppercase font-semibold"><?php echo $stat['name']; ?></p>
                                        <p class="text-5xl font-bold mt-2"><?php echo $stat['value']; ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="w-full lg:w-1/3">
                            <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
                                <h3 class="text-2xl font-semibold text-gray-800 mb-4">Current Waste Rates</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waste Type</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate ($/kg)</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($rates as $rate): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 capitalize"><?php echo htmlspecialchars($rate['waste_type']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?php echo number_format($rate['rate_per_kg'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-lg p-6 mt-6">
                        <h3 class="text-2xl font-semibold text-gray-800 mb-4">Recent Activity</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waste Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($recentPickups as $pickup): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $pickup['id']; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $pickup['user_id']; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $pickup['waste_type']; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($pickup['status'] === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                                    <?php echo $pickup['status']; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('Y-m-d', strtotime($pickup['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php
                    break;
                case 'users':
                    // --- User Management content ---
                    $message = '';
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_all_roles'])) {
                        $con->begin_transaction();
                        $success = true;

                        try {
                            $stmt = $con->prepare("UPDATE users SET role = ? WHERE id = ?");
                            foreach ($_POST['roles'] as $userId => $newRole) {
                                $stmt->bind_param("si", $newRole, $userId);
                                if (!$stmt->execute()) {
                                    $success = false;
                                    break;
                                }
                            }
                            if ($success) {
                                $con->commit();
                                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Success:</p><p class="text-sm">User roles updated successfully!</p></div>';
                            } else {
                                $con->rollback();
                                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Failed to update one or more user roles. All changes were rolled back.</p></div>';
                            }
                        } catch (Exception $e) {
                            $con->rollback();
                            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">An unexpected error occurred. All changes were rolled back.</p></div>';
                        } finally {
                            if (isset($stmt)) {
                                $stmt->close();
                            }
                        }
                    }

                    $usersResult = $con->query("SELECT id, name, email, role FROM users");
                    $users = ($usersResult) ? $usersResult->fetch_all(MYSQLI_ASSOC) : [];
                    ?>
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-4xl font-bold text-gray-800">User Management</h2>
                    </div>
                    <?php echo $message; ?>
                    <form method="post" action="?page=users">
                        <input type="hidden" name="update_all_roles" value="1">
                        <div class="bg-white rounded-2xl shadow-lg p-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                            <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                            <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Current Role</th>
                                            <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Change Role</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (count($users) > 0): ?>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?php echo $user['id']; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?php echo htmlspecialchars($user['name']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900 capitalize"><?php echo htmlspecialchars($user['role']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg font-medium">
                                                        <select name="roles[<?php echo $user['id']; ?>]" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-lg">
                                                            <option value="user" <?php echo ($user['role'] === 'user' ? 'selected' : ''); ?>>User</option>
                                                            <option value="collector" <?php echo ($user['role'] === 'collector' ? 'selected' : ''); ?>>Collector</option>
                                                            <option value="admin" <?php echo ($user['role'] === 'admin' ? 'selected' : ''); ?>>Admin</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No users found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-8 text-right">
                                <button type="submit" class="bg-indigo-600 text-white py-3 px-8 rounded-xl text-lg font-semibold hover:bg-indigo-700 transition-colors shadow-lg">Update All Roles</button>
                            </div>
                        </div>
                    </form>
                    <?php
                    break;
                case 'waste_rates':
                    // --- Waste Rates Management content ---
                    $message = '';

                    if (isset($_GET['delete_id'])) {
                        $delete_id = intval($_GET['delete_id']);
                        $stmt = $con->prepare("DELETE FROM waste_rates WHERE id = ?");
                        $stmt->bind_param("i", $delete_id);
                        if ($stmt->execute()) {
                            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Success:</p><p class="text-sm">Waste type deleted successfully.</p></div>';
                        } else {
                            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Error deleting record.</p></div>';
                        }
                        $stmt->close();
                    }

                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        if (isset($_POST['add_waste_rate'])) {
                            $waste_type = trim($_POST['waste_type']);
                            $rate_per_kg = trim($_POST['rate_per_kg']);

                            if (empty($waste_type) || !is_numeric($rate_per_kg)) {
                                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Please enter a valid waste type and numeric rate.</p></div>';
                            } else {
                                $check = $con->prepare("SELECT id FROM waste_rates WHERE waste_type = ?");
                                $check->bind_param("s", $waste_type);
                                $check->execute();
                                $check->store_result();

                                if ($check->num_rows > 0) {
                                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">This waste type already exists.</p></div>';
                                } else {
                                    $stmt = $con->prepare("INSERT INTO waste_rates (waste_type, rate_per_kg) VALUES (?, ?)");
                                    $stmt->bind_param("sd", $waste_type, $rate_per_kg);
                                    if ($stmt->execute()) {
                                        $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Success:</p><p class="text-sm">Waste type and rate added successfully!</p></div>';
                                    } else {
                                        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Database error: ' . $con->error . '</p></div>';
                                    }
                                    $stmt->close();
                                }
                                $check->close();
                            }
                        } elseif (isset($_POST['update_all_rates'])) {
                            $con->begin_transaction();
                            $success = true;
                            try {
                                $stmt = $con->prepare("UPDATE waste_rates SET rate_per_kg = ?, updated_at = NOW() WHERE id = ?");
                                foreach ($_POST['rates'] as $rateId => $newRate) {
                                    if (!is_numeric($newRate)) {
                                        $success = false;
                                        break;
                                    }
                                    $stmt->bind_param("di", $newRate, $rateId);
                                    if (!$stmt->execute()) {
                                        $success = false;
                                        break;
                                    }
                                }
                                if ($success) {
                                    $con->commit();
                                    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Success:</p><p class="text-sm">Waste rates updated successfully!</p></div>';
                                } else {
                                    $con->rollback();
                                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Failed to update one or more rates. All changes were rolled back.</p></div>';
                                }
                            } catch (Exception $e) {
                                $con->rollback();
                                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">An unexpected error occurred. All changes were rolled back.</p></div>';
                            } finally {
                                if (isset($stmt)) {
                                    $stmt->close();
                                }
                            }
                        }
                    }

                    $wasteData = [];
                    $result = $con->query("SELECT * FROM waste_rates ORDER BY updated_at DESC");
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $wasteData[] = $row;
                        }
                    }
                    ?>
                    <h2 class="text-4xl font-bold text-gray-800 mb-8">Waste Rates Management</h2>
                    <?php echo $message; ?>
                    <div class="flex flex-col lg:flex-row gap-8">
                        <div class="w-full lg:w-2/3 bg-white rounded-2xl shadow-lg p-6">
                            <h3 class="text-2xl font-semibold text-gray-800 mb-4">Current Waste Rates</h3>
                            <form method="post" action="?page=waste_rates">
                                <input type="hidden" name="update_all_rates" value="1">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Waste Type</th>
                                                <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Rate ($/kg)</th>
                                                <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                                                <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (!empty($wasteData)): ?>
                                            <?php foreach ($wasteData as $waste): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900 capitalize"><?= htmlspecialchars($waste['waste_type']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900">
                                                        <input type="number" step="0.01" name="rates[<?= $waste['id']; ?>]" value="<?= htmlspecialchars($waste['rate_per_kg']); ?>" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-lg">
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-500"><?= htmlspecialchars($waste['updated_at']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg font-medium">
                                                        <button type="button" onclick="showModal(<?= $waste['id']; ?>)" class="bg-red-600 text-white py-2 px-4 rounded-xl hover:bg-red-700 transition-colors shadow-md">Delete</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No data available.</td></tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-8 text-right">
                                    <button type="submit" class="bg-indigo-600 text-white py-3 px-8 rounded-xl text-lg font-semibold hover:bg-indigo-700 transition-colors shadow-lg">Update All Rates</button>
                                </div>
                            </form>
                        </div>

                        <div class="w-full lg:w-1/3 bg-white rounded-2xl shadow-lg p-6">
                            <h3 class="text-2xl font-semibold text-gray-800 mb-4">Add New Waste Type & Rate</h3>
                            <form method="POST" action="?page=waste_rates">
                                <input type="hidden" name="add_waste_rate" value="1">
                                <div class="mb-4">
                                    <label for="waste_type" class="block text-lg font-medium text-gray-700">Waste Type</label>
                                    <input type="text" id="waste_type" name="waste_type" required class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-lg">
                                </div>
                                <div class="mb-4">
                                    <label for="rate_per_kg" class="block text-lg font-medium text-gray-700">Rate per KG ($)</label>
                                    <input type="number" step="0.01" id="rate_per_kg" name="rate_per_kg" required class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-lg">
                                </div>
                                <div>
                                    <button type="submit" class="w-full bg-green-600 text-white py-3 px-4 rounded-xl text-lg font-semibold hover:bg-green-700 transition-colors shadow-lg">Add Waste Rate</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Delete Confirmation Modal for Waste Rates -->
                    <div id="deleteModal" class="hidden fixed inset-0 z-50 overflow-auto modal-overlay flex items-center justify-center">
                        <div class="bg-white rounded-2xl p-8 max-w-sm mx-auto shadow-2xl transform transition-all duration-300">
                            <div class="text-center">
                                <h3 class="text-2xl font-semibold text-gray-800 mb-4">Confirm Deletion</h3>
                                <p class="text-gray-600 mb-6">Are you sure you want to delete this waste rate? This action cannot be undone.</p>
                                <div class="flex justify-center space-x-4">
                                    <button type="button" onclick="hideModal()" class="bg-gray-300 text-gray-800 py-2 px-6 rounded-xl hover:bg-gray-400 transition-colors shadow-sm">Cancel</button>
                                    <a id="confirmDeleteButton" href="#" class="bg-red-600 text-white py-2 px-6 rounded-xl hover:bg-red-700 transition-colors shadow-sm">Delete</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        // Functions for the waste rates modal
                        function showModal(id) {
                            const modal = document.getElementById('deleteModal');
                            const confirmButton = document.getElementById('confirmDeleteButton');
                            confirmButton.href = `?page=waste_rates&delete_id=${id}`;
                            modal.classList.remove('hidden');
                        }
                        function hideModal() {
                            const modal = document.getElementById('deleteModal');
                            modal.classList.add('hidden');
                        }
                    </script>
                    <?php
                    break;
                case 'new_pickups':
                    // --- Handle Assign & Approve Action ---
                    $message = '';
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_collector'])) {
                        $request_id = intval($_POST['request_id']);
                        $collector_id = intval($_POST['collector_id']);

                        if ($collector_id > 0) {
                            // Update status to 'Approved' and assign collector
                            $stmt = $con->prepare("UPDATE pickup_requests SET assigned_collector_id = ?, status = 'Approved' WHERE id = ? AND status = 'Pending'");
                            $stmt->bind_param("ii", $collector_id, $request_id);
                            if ($stmt->execute()) {
                                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Success:</p><p class="text-sm">Collector assigned and request approved successfully!</p></div>';
                            } else {
                                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Failed to assign collector. Please try again.</p></div>';
                            }
                            $stmt->close();
                        } else {
                            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Please select a valid collector to assign.</p></div>';
                        }
                    }

                    // --- Fetch Pending Pickup Requests ---
                    $pendingRequests = [];
                    // Corrected query to fetch the user's phone number
                    $query = "SELECT pr.*, u.name AS requester_name, u.phone AS requester_phone FROM pickup_requests pr JOIN users u ON pr.user_id = u.id WHERE pr.status = 'Pending' ORDER BY pr.pickup_date ASC";
                    $result = $con->query($query);
                    if ($result) {
                        $pendingRequests = $result->fetch_all(MYSQLI_ASSOC);
                    }
                    ?>
                    <h2 class="text-4xl font-bold text-gray-800 mb-8">New Pickup Requests (Pending)</h2>
                    <?php echo $message; ?>
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">#</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Requester</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Waste Type</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Weight (kg)</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Pickup Date</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Assign Collector</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($pendingRequests)): ?>
                                        <?php $count = 1; ?>
                                        <?php foreach ($pendingRequests as $request): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= $count++; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($request['requester_name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($request['requester_phone']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($request['waste_type']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($request['final_weight']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= date('Y-m-d', strtotime($request['pickup_date'])); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg">
                                                    <form method="post" action="?page=new_pickups">
                                                        <input type="hidden" name="request_id" value="<?= $request['id']; ?>">
                                                        <div class="flex items-center gap-2">
                                                            <select name="collector_id" required class="rounded-xl border-gray-300 text-sm">
                                                                <option value="">Select Collector</option>
                                                                <?php foreach ($collectorOptions as $id => $name): ?>
                                                                    <option value="<?= $id; ?>"><?= htmlspecialchars($name); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button type="submit" name="assign_collector" class="bg-green-600 text-white py-2 px-4 rounded-xl text-sm hover:bg-green-700 transition-colors shadow-md">Assign</button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">No new pickup requests found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php
                    break;
                case 'verify_pickups':
                    // --- Handle Complete Action ---
                    $message = '';
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_pickup'])) {
                        $request_id = intval($_POST['request_id']);
                        $status = 'Completed';

                        $stmt = $con->prepare("UPDATE pickup_requests SET status = ? WHERE id = ? AND status = 'Approved'");
                        $stmt->bind_param("si", $status, $request_id);
                        if ($stmt->execute()) {
                            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Success:</p><p class="text-sm">Pickup status updated successfully!</p></div>';
                        } else {
                            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Failed to update pickup status. Please try again.</p></div>';
                        }
                        $stmt->close();
                    }

                    // --- Fetch Approved Pickups ---
                    $approvedPickups = [];
                    // Corrected query to get both requester and collector names, and requester phone
                    $query = "
                        SELECT pr.*, u.name AS requester_name, u.phone AS requester_phone, c.name AS collector_name
                        FROM pickup_requests pr
                        JOIN users u ON pr.user_id = u.id
                        LEFT JOIN users c ON pr.assigned_collector_id = c.id
                        WHERE pr.status = 'Approved'
                        ORDER BY pr.pickup_date ASC
                    ";
                    $result = $con->query($query);
                    if ($result) {
                        $approvedPickups = $result->fetch_all(MYSQLI_ASSOC);
                    }
                    ?>
                    <h2 class="text-4xl font-bold text-gray-800 mb-8">Verify Pickups (Approved)</h2>
                    <?php echo $message; ?>
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">#</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Requester</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Collector</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Est. Weight (kg)</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($approvedPickups)): ?>
                                        <?php $count = 1; ?>
                                        <?php foreach ($approvedPickups as $pickup): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= $count++; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($pickup['requester_name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($pickup['requester_phone']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($pickup['collector_name'] ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($pickup['final_weight']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg">
                                                    <form method="post" action="?page=verify_pickups" class="inline-block">
                                                        <input type="hidden" name="request_id" value="<?= $pickup['id']; ?>">
                                                        <button type="submit" name="complete_pickup" class="bg-blue-600 text-white py-2 px-4 rounded-xl text-sm hover:bg-blue-700 transition-colors shadow-md">Complete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">No approved pickups to verify.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php
                    break;
                case 'refused_pickups':
                    // --- Refused Pickups content ---
                    $message = '';

                    // Handle Reassign and Cancel actions
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $request_id = intval($_POST['request_id']);

                        if (isset($_POST['reassign_pickup'])) {
                            $new_collector_id = intval($_POST['new_collector_id']);
                            if ($new_collector_id > 0) {
                                $stmt = $con->prepare("UPDATE pickup_requests SET status = 'Approved', assigned_collector_id = ? WHERE id = ?");
                                $stmt->bind_param("ii", $new_collector_id, $request_id);
                                if ($stmt->execute()) {
                                    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Success:</p><p class="text-sm">Pickup re-assigned successfully!</p></div>';
                                } else {
                                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Failed to re-assign pickup. ' . $con->error . '</p></div>';
                                }
                                $stmt->close();
                            } else {
                                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Please select a collector to re-assign the pickup.</p></div>';
                            }
                        } elseif (isset($_POST['cancel_pickup'])) {
                            $stmt = $con->prepare("UPDATE pickup_requests SET status = 'Fully Cancelled' WHERE id = ?");
                            $stmt->bind_param("i", $request_id);
                            if ($stmt->execute()) {
                                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Success:</p><p class="text-sm">Pickup fully cancelled successfully!</p></div>';
                            } else {
                                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Failed to cancel pickup. ' . $con->error . '</p></div>';
                            }
                            $stmt->close();
                        }
                    }

                    $refusedPickups = [];
                    // The SQL query to retrieve all refused pickups, including requester name, phone, and collector name.
                    $query = "
                        SELECT pr.*, u.name AS requester_name, u.phone AS requester_phone, c.name AS collector_name, c.phone AS collector_phone
                        FROM pickup_requests pr
                        JOIN users u ON pr.user_id = u.id
                        LEFT JOIN users c ON pr.assigned_collector_id = c.id
                        WHERE pr.status = 'Refused'
                        ORDER BY pr.created_at DESC
                    ";
                    
                    // Attempt to execute the query. If it fails, display the exact database error.
                    $result = $con->query($query);
                    if ($result) {
                        $refusedPickups = $result->fetch_all(MYSQLI_ASSOC);
                    } else {
                        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Database Error:</p><p class="text-sm">Failed to retrieve refused pickups. Please check the query and table structure. MySQL Error: ' . htmlspecialchars($con->error) . '</p></div>';
                    }
                    ?>
                    <h2 class="text-4xl font-bold text-gray-800 mb-8">Refused Pickups</h2>
                    <?php echo $message; ?>
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">#</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Requester</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Requester Contact</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Collector</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Collector Contact</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Date Refused</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($refusedPickups)): ?>
                                        <?php $count = 1; ?>
                                        <?php foreach ($refusedPickups as $pickup): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= $count++; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($pickup['requester_name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($pickup['requester_phone']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($pickup['collector_name'] ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($pickup['collector_phone'] ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4 whitespace-normal text-lg text-gray-900"><?= htmlspecialchars($pickup['refusal_reason'] ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-500"><?= date('Y-m-d', strtotime($pickup['created_at'])); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg">
                                                    <div class="flex items-center space-x-2">
                                                        <!-- Reassign Section -->
                                                        <form method="post" action="?page=refused_pickups" class="inline-block flex items-center space-x-2">
                                                            <input type="hidden" name="request_id" value="<?= $pickup['id']; ?>">
                                                            <select name="new_collector_id" required class="rounded-xl border-gray-300 text-sm">
                                                                <option value="">Reassign</option>
                                                                <?php foreach ($collectorOptions as $id => $name): ?>
                                                                    <option value="<?= $id; ?>"><?= htmlspecialchars($name); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button type="submit" name="reassign_pickup" class="bg-blue-600 text-white py-2 px-4 rounded-xl text-sm hover:bg-blue-700 transition-colors shadow-md">Go</button>
                                                        </form>
                                                        
                                                        <!-- Cancel Button -->
                                                        <button type="button" onclick="showCancelModal(<?= $pickup['id']; ?>)" class="bg-red-600 text-white py-2 px-4 rounded-xl text-sm hover:bg-red-700 transition-colors shadow-md">Cancel</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">No refused pickups found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Cancel Confirmation Modal -->
                    <div id="cancelModal" class="hidden fixed inset-0 z-50 overflow-auto modal-overlay flex items-center justify-center">
                        <div class="bg-white rounded-2xl p-8 max-w-sm mx-auto shadow-2xl transform transition-all duration-300">
                            <div class="text-center">
                                <h3 class="text-2xl font-semibold text-gray-800 mb-4">Confirm Cancellation</h3>
                                <p class="text-gray-600 mb-6">Are you sure you want to fully cancel this pickup? This will remove it from the system and cannot be undone.</p>
                                <div class="flex justify-center space-x-4">
                                    <button type="button" onclick="hideCancelModal()" class="bg-gray-300 text-gray-800 py-2 px-6 rounded-xl hover:bg-gray-400 transition-colors shadow-sm">Cancel</button>
                                    <form id="cancelForm" method="post" action="?page=refused_pickups" class="inline-block">
                                        <input type="hidden" name="request_id" id="cancelRequestId">
                                        <button type="submit" name="cancel_pickup" class="bg-red-600 text-white py-2 px-6 rounded-xl hover:bg-red-700 transition-colors shadow-sm">Confirm</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                        // Functions for the cancel pickup modal
                        function showCancelModal(id) {
                            const modal = document.getElementById('cancelModal');
                            const requestIdInput = document.getElementById('cancelRequestId');
                            requestIdInput.value = id;
                            modal.classList.remove('hidden');
                        }
                        function hideCancelModal() {
                            const modal = document.getElementById('cancelModal');
                            modal.classList.add('hidden');
                        }
                    </script>
                    <?php
                    break;
                case 'reports':
                    // --- Reports content ---
                    // This section is a placeholder for future report generation logic.
                    // For example, you could show charts of total waste collected by month or collector performance.
                    $reportData = [];
                    $query = "SELECT pr.waste_type, SUM(pr.final_weight) as total_weight, COUNT(*) as total_pickups FROM pickup_requests pr WHERE pr.status = 'Completed' GROUP BY pr.waste_type";
                    $result = $con->query($query);
                    if ($result) {
                        $reportData = $result->fetch_all(MYSQLI_ASSOC);
                    }
                    ?>
                    <h2 class="text-4xl font-bold text-gray-800 mb-8">Reports</h2>
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <h3 class="text-2xl font-semibold text-gray-800 mb-4">Completed Pickups by Waste Type</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Waste Type</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Total Weight (kg)</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Total Pickups</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($reportData)): ?>
                                        <?php foreach ($reportData as $data): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900 capitalize"><?= htmlspecialchars($data['waste_type']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= number_format($data['total_weight'], 2); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= $data['total_pickups']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="px-6 py-4 text-center text-gray-500">No completed pickups found to generate a report.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php
                    break;
                default:
                    echo '<p class="text-gray-500">Page not found.</p>';
            }

            // Close the database connection at the end of the script
            $con->close();
            ?>
        </div>
    </div>
</body>
</html>
