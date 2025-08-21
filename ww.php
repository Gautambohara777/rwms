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
            <div class="bg-white rounded-2xl shadow-lg p-8 max-w-sm w-full text-center">
                <h2 class="text-2xl font-bold mb-4 text-gray-800">Access Denied</h2>
                <p class="text-gray-600 mb-6">You must be logged in as an admin to view this page.</p>
                <a href="login.php" class="inline-block bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-2 px-6 rounded-full transition duration-300">Go to Login</a>
            </div>
        </div>';
        exit;
    }

    // Include the database connection file.
    include 'connect.php';

    // Get the current page from the URL, or default to 'dashboard'.
    $currentPage = isset($_GET['page']) ? htmlspecialchars($_GET['page']) : 'dashboard';
    
    // Check if the database connection is successful
    if ($con->connect_error) {
        die("Connection failed: " . $con->connect_error);
    }
    ?>

    <div class="min-h-screen flex">
        <!-- Sidebar Navigation -->
        <div class="w-64 bg-gray-800 text-white flex flex-col rounded-r-2xl shadow-xl">
            <div class="p-6 border-b border-gray-700">
                <h1 class="text-2xl font-bold">Recycle Admin</h1>
            </div>
            <nav class="flex-1 p-4 space-y-2">
                <a href="?page=dashboard" class="flex items-center p-3 rounded-lg hover:bg-gray-700 transition duration-200 <?= ($currentPage == 'dashboard') ? 'bg-gray-700' : '' ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard
                </a>
                <a href="?page=users" class="flex items-center p-3 rounded-lg hover:bg-gray-700 transition duration-200 <?= ($currentPage == 'users') ? 'bg-gray-700' : '' ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a4 4 0 014-4h.5M17 14v4m-2 2h4M17 14v4m-2-2h4"></path></svg>
                    User Management
                </a>
                <a href="?page=reports" class="flex items-center p-3 rounded-lg hover:bg-gray-700 transition duration-200 <?= ($currentPage == 'reports') ? 'bg-gray-700' : '' ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19a2 2 0 002 2h1a2 2 0 002-2m-8 2a2 2 0 002-2m0-13a2 2 0 012-2m-2 2a2 2 0 012 2m7 12a2 2 0 002-2m-2 2a2 2 0 01-2-2m7-2a2 2 0 012-2m-2 2a2 2 0 00-2-2"></path></svg>
                    Reports
                </a>
                <!-- New navigation link for uploaded listings -->
                <a href="?page=listings" class="flex items-center p-3 rounded-lg hover:bg-gray-700 transition duration-200 <?= ($currentPage == 'listings') ? 'bg-gray-700' : '' ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0a2 2 0 012-2h6a2 2 0 012 2m-6 0h.01M17 17h.01"></path></svg>
                    Reusable Listings
                </a>
                <a href="logout.php" class="flex items-center p-3 rounded-lg hover:bg-gray-700 transition duration-200">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    Logout
                </a>
            </nav>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 p-8 overflow-y-auto">
            <header class="flex justify-between items-center mb-6">
                <h2 class="text-3xl font-semibold text-gray-800 capitalize"><?= $currentPage ?></h2>
            </header>
            <?php
            // Switch to determine which page content to display
            switch ($currentPage) {
                case 'dashboard':
                    // Dashboard content
                    ?>
                    <!-- Dashboard content as it was before -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <!-- Dashboard Cards -->
                        <div class="bg-white p-6 rounded-2xl shadow-md border-l-4 border-emerald-500">
                            <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Users</h3>
                            <p class="text-3xl font-bold text-gray-900">
                                <?php
                                $usersResult = $con->query("SELECT COUNT(*) as total_users FROM users");
                                if ($usersResult) {
                                    $row = $usersResult->fetch_assoc();
                                    echo $row['total_users'];
                                } else {
                                    echo "N/A";
                                }
                                ?>
                            </p>
                        </div>
                        <div class="bg-white p-6 rounded-2xl shadow-md border-l-4 border-blue-500">
                            <h3 class="text-lg font-semibold text-gray-700 mb-2">Pending Pickups</h3>
                            <p class="text-3xl font-bold text-gray-900">
                                <?php
                                $pendingResult = $con->query("SELECT COUNT(*) as pending_pickups FROM pickup_requests WHERE status = 'pending'");
                                if ($pendingResult) {
                                    $row = $pendingResult->fetch_assoc();
                                    echo $row['pending_pickups'];
                                } else {
                                    echo "N/A";
                                }
                                ?>
                            </p>
                        </div>
                        <div class="bg-white p-6 rounded-2xl shadow-md border-l-4 border-yellow-500">
                            <h3 class="text-lg font-semibold text-gray-700 mb-2">Completed Pickups</h3>
                            <p class="text-3xl font-bold text-gray-900">
                                <?php
                                $completedResult = $con->query("SELECT COUNT(*) as completed_pickups FROM pickup_requests WHERE status = 'completed'");
                                if ($completedResult) {
                                    $row = $completedResult->fetch_assoc();
                                    echo $row['completed_pickups'];
                                } else {
                                    echo "N/A";
                                }
                                ?>
                            </p>
                        </div>
                        <div class="bg-white p-6 rounded-2xl shadow-md border-l-4 border-red-500">
                            <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Waste (kg)</h3>
                            <p class="text-3xl font-bold text-gray-900">
                                <?php
                                $wasteResult = $con->query("SELECT SUM(total_weight) as total_waste FROM completed_pickups_report");
                                if ($wasteResult && $row = $wasteResult->fetch_assoc()) {
                                    echo number_format($row['total_waste'], 2);
                                } else {
                                    echo "0.00";
                                }
                                ?>
                            </p>
                        </div>
                    </div>

                    <!-- Dashboard Tables -->
                    <div class="bg-white p-6 rounded-2xl shadow-md mb-8">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Current Waste Rates</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waste Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate per kg ($)</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php
                                    $ratesResult = $con->query("SELECT waste_type, rate_per_kg FROM waste_rates");
                                    if ($ratesResult && $ratesResult->num_rows > 0) {
                                        while ($rate = $ratesResult->fetch_assoc()): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900 capitalize"><?= htmlspecialchars($rate['waste_type']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= number_format($rate['rate_per_kg'], 2); ?></td>
                                            </tr>
                                        <?php endwhile;
                                    } else { ?>
                                        <tr>
                                            <td colspan="2" class="px-6 py-4 text-center text-gray-500">No waste rates found.</td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-md">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Recent Activity</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waste Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php
                                    $recentPickupsResult = $con->query("SELECT id, user_id, waste_type, status, created_at FROM pickup_requests ORDER BY created_at DESC LIMIT 10");
                                    if ($recentPickupsResult && $recentPickupsResult->num_rows > 0) {
                                        while ($pickup = $recentPickupsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($pickup['id']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($pickup['user_id']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900 capitalize"><?= htmlspecialchars($pickup['waste_type']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    <?= ($pickup['status'] == 'pending') ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' ?>">
                                                        <?= htmlspecialchars($pickup['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-500"><?= htmlspecialchars($pickup['created_at']); ?></td>
                                            </tr>
                                        <?php endwhile;
                                    } else { ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">No recent activity found.</td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php
                    break;

                case 'users':
                    // User Management content
                    ?>
                    <div class="bg-white p-6 rounded-2xl shadow-md">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">User List</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php
                                    $userResult = $con->query("SELECT user_id, name, email, role FROM users");
                                    if ($userResult && $userResult->num_rows > 0) {
                                        while ($user = $userResult->fetch_assoc()): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($user['user_id']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900 capitalize"><?= htmlspecialchars($user['name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($user['email']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900 capitalize"><?= htmlspecialchars($user['role']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <!-- Action buttons (e.g., Edit, Delete) can go here -->
                                                    <a href="#" class="text-indigo-600 hover:text-indigo-900 mr-2">Edit</a>
                                                    <a href="#" class="text-red-600 hover:text-red-900">Delete</a>
                                                </td>
                                            </tr>
                                        <?php endwhile;
                                    } else { ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">No users found.</td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php
                    break;

                case 'reports':
                    // Reports content
                    ?>
                    <div class="bg-white p-6 rounded-2xl shadow-md">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Completed Pickups Report</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waste Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Weight (kg)</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Pickups</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php
                                    // Query the completed_pickups_report table to generate the report data.
                                    $reportData = $con->query("SELECT waste_type, total_weight, total_pickups FROM completed_pickups_report");
                                    if ($reportData && $reportData->num_rows > 0): ?>
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

                // --- NEW CASE ADDED FOR REUSABLE LISTINGS ---
                case 'listings':
                    ?>
                    <div class="bg-white p-6 rounded-2xl shadow-md">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Reusable Waste Listings</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price ($)</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php
                                    // Query the reusable_waste_listings table
                                    $listingsQuery = "SELECT * FROM reusable_waste_listings ORDER BY listing_id DESC";
                                    $listingsResult = $con->query($listingsQuery);

                                    if ($listingsResult && $listingsResult->num_rows > 0) {
                                        // Loop through the results and display each listing in a table row
                                        while ($listing = $listingsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($listing['listing_id']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <!-- Assume images are in an 'uploads/reusable/' directory -->
                                                    <img src="uploads/reusable/<?= htmlspecialchars($listing['image']); ?>" alt="<?= htmlspecialchars($listing['title']); ?>" class="w-20 h-20 object-cover rounded-md shadow">
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg font-medium text-gray-900"><?= htmlspecialchars($listing['title']); ?></td>
                                                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs overflow-hidden truncate"><?= htmlspecialchars($listing['description']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    <?= ($listing['status'] == 'available') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                        <?= htmlspecialchars($listing['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900">$<?= number_format($listing['price'], 2); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($listing['quantity']); ?></td>
                                            </tr>
                                        <?php endwhile;
                                    } else {
                                        // If no listings are found, display a message
                                        ?>
                                        <tr>
                                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">No reusable waste listings found.</td>
                                        </tr>
                                    <?php } ?>
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
