<?php
// Start the session to access session variables.
session_start();

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Include the database connection file.
if (!file_exists('connect.php')) {
    die("Error: The 'connect.php' file is missing. Please ensure it's in the same directory.");
}
include_once 'connect.php';

// Check if the mysqli connection object is set.
if (!isset($con) || $con->connect_error) {
    die("Error: The database connection failed. Please ensure 'connect.php' is in the same directory and the credentials are correct.");
}

// Safely get the user_id and username from the session to avoid warnings.
$user_id = $_SESSION['user'] ?? null;
$username = $_SESSION['user_name'] ?? 'User';
$message = '';
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Fetch waste types and rates from the database for the form.
$waste_options = [];
$waste_rate_query = $con->query("SELECT waste_type, rate_per_kg FROM waste_rates ORDER BY waste_type ASC");
if ($waste_rate_query) {
    while ($row = $waste_rate_query->fetch_assoc()) {
        $waste_options[] = $row;
    }
} else {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Failed to load waste rates from the database. Please check your "waste_rates" table.</p></div>';
}


// --- PHP Logic for handling user actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($currentPage) {
        case 'new_pickup':
            if (isset($_POST['request_pickup'])) {
                // Sanitize and validate input data.
                $name = trim($_POST['name']);
                $waste_type = trim($_POST['waste_type']);
                $other_waste_type = isset($_POST['other_waste_type']) ? trim($_POST['other_waste_type']) : '';
                $weight = floatval($_POST['weight']);
                $address = trim($_POST['address']);
                $latitude = floatval($_POST['latitude']);
                $longitude = floatval($_POST['longitude']);
                $rate_per_kg = floatval($_POST['rate_per_kg']);

                // If 'Other' is selected, use the custom waste type and set rate to 0
                if ($waste_type === 'Other') {
                    $waste_type = $other_waste_type;
                    $rate_per_kg = 0;
                }

                // Prepare the SQL statement to insert a new pickup request.
                // Using a prepared statement for security.
                $sql = "INSERT INTO pickup_requests (user_id, name, waste_type, rate, weight, address, latitude, longitude, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = null;
                try {
                    $stmt = $con->prepare($sql);
                    if ($stmt === false) {
                        throw new Exception("Error preparing statement: " . $con->error);
                    }

                    // Bind parameters to the prepared statement.
                    $status = 'Pending';
                    $stmt->bind_param("issdssdss", $user_id, $name, $waste_type, $rate_per_kg, $weight, $address, $latitude, $longitude, $status);

                    // Execute the statement and check for success.
                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Pickup request submitted successfully!";
                        header('Location: ?page=pickup_history');
                        exit();
                    } else {
                        throw new Exception("Error executing statement: " . $stmt->error);
                    }
                } catch (Exception $e) {
                    $message = "Database Error: " . $e->getMessage();
                } finally {
                    // Close the statement to free up resources.
                    if ($stmt) {
                        $stmt->close();
                    }
                }
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RecycleHub User Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
        .sidebar {
            transition: transform 0.3s ease-in-out;
            z-index: 50;
        }
        .count-text {
            font-size: 1.25rem; /* text-xl */
            font-weight: 600;
        }
        .count-value {
            font-size: 3rem; /* text-5xl */
            font-weight: 700;
        }
        @media (min-width: 1024px) {
            .count-value {
                font-size: 3.75rem; /* text-6xl for bigger count on larger screens */
            }
        }
        /* Custom scrollable table container */
        .scrollable-table-container {
            max-height: 400px; /* Set a fixed max-height */
            overflow-y: auto; /* Enable vertical scrolling */
            -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
        }
        #map {
            height: 300px;
            border-radius: 0.75rem; /* rounded-xl */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <!-- Mobile Header with Hamburger Menu -->
    <header class="bg-white md:hidden p-4 flex items-center justify-between shadow-md">
        <h1 class="text-xl font-bold text-gray-800">RecycleHub</h1>
        <button id="menu-button-top" class="text-gray-600 focus:outline-none">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
            </svg>
        </button>
    </header>

    <!-- Main Content Grid Area: Two columns on medium screens and up -->
    <div class="grid grid-cols-1 md:grid-cols-[256px,1fr] overflow-hidden min-h-screen">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar bg-gray-800 text-white shadow-lg fixed md:static inset-y-0 w-64 transform -translate-x-full md:translate-x-0 z-50">
            <div class="p-6">
                <div class="flex items-center space-x-2 mb-8">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-8 h-8 text-green-400">
                        <path d="M7.44 3.704a1.5 1.5 0 0 0-2.88 0l-1.704 6.816a1.5 1.5 0 0 0-.256 1.15l1.625 3.25a1.5 1.5 0 0 0 1.348.878h10.962a1.5 1.5 0 0 0 1.348-.878l1.625-3.25a1.5 1.5 0 0 0-.256-1.15L16.44 3.704a1.5 1.5 0 0 0-2.88 0l-1.704 6.816a1.5 1.5 0 0 0-2.56 0l-1.704-6.816Z" />
                    </svg>
                    <h2 class="text-2xl font-bold">RecycleHub</h2>
                </div>
                <ul class="space-y-4">
                    <li>
                        <a href="?page=dashboard" class="flex items-center p-3 rounded-lg hover:bg-gray-700 transition-colors duration-200 <?php echo $currentPage == 'dashboard' ? 'bg-gray-700' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 mr-3">
                                <path fill-rule="evenodd" d="M2.25 2.25a.75.75 0 0 0 0 1.5H3.75v16.5a.75.75 0 0 0 1.5 0V3.75h1.5a.75.75 0 0 0 0-1.5H2.25ZM9 4.5a.75.75 0 0 0-.75.75v15a.75.75 0 0 0 1.5 0v-15A.75.75 0 0 0 9 4.5ZM18.75 6a.75.75 0 0 0-.75.75v13.5a.75.75 0 0 0 1.5 0V6.75a.75.75 0 0 0-.75-.75Z" clip-rule="evenodd" />
                            </svg>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="?page=new_pickup" class="flex items-center p-3 rounded-lg hover:bg-gray-700 transition-colors duration-200 <?php echo $currentPage == 'new_pickup' ? 'bg-gray-700' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 mr-3">
                                <path fill-rule="evenodd" d="M12 5.25a.75.75 0 0 1 .75.75v5.25H18a.75.75 0 0 1 0 1.5h-5.25V18a.75.75 0 0 1-1.5 0v-5.25H6a.75.75 0 0 1 0-1.5h5.25V6a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
                            </svg>
                            <span>Request Pickup</span>
                        </a>
                    </li>
                    <li>
                        <a href="?page=pickup_history" class="flex items-center p-3 rounded-lg hover:bg-gray-700 transition-colors duration-200 <?php echo $currentPage == 'pickup_history' ? 'bg-gray-700' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 mr-3">
                                <path d="M11.5 5.25a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-.75.75h-3a.75.75 0 0 1 0-1.5h2.25V6a.75.75 0 0 1 .75-.75Zm3.36 1.794a.75.75 0 0 1 .799-.597 7.5 7.5 0 0 1 2.373 5.488.75.75 0 0 1-1.5-.098 6 6 0 0 0-1.92-4.394.75.75 0 0 1-.752-.449ZM6.96 6.554a.75.75 0 0 1 1.058.077 6 6 0 0 0 4.295 8.293.75.75 0 0 1 .632.483l.256.638a.75.75 0 0 1-.295.968 7.5 7.5 0 0 1-5.741-6.757.75.75 0 0 1 .495-.702ZM-1.87 2.05a.75.75 0 0 1 .786-1.229 7.5 7.5 0 0 1 6.521 3.23.75.75 0 0 1-1.33.722A6 6 0 0 0 6.64 8.604ZM14.498 17.067a.75.75 0 0 1 .597.799 6 6 0 0 0-4.394-1.92.75.75 0 0 1-.449-.752l.08-.265a.75.75 0 0 1 .968-.295 7.5 7.5 0 0 1 3.23 6.52.75.75 0 0 1-1.229.786 6 6 0 0 0 6.505-3.21l.266-.08a.75.75 0 0 1 .495.702Z" />
                            </svg>
                            <span>My Pickup History</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content Area -->
        <main id="main-content" class="p-4 md:p-8 md:col-start-2 overflow-y-auto w-full">
            <div class="space-y-8">
                <!-- Display messages from PHP actions -->
                <?php echo $message; ?>

                <?php
                // Display different content based on the current page
                switch ($currentPage) {
                    case 'dashboard':
                        // Initialize variables with a default of 0
                        $totalRequests = 0;
                        $inProgressCount = 0;
                        $completedCount = 0;
                        $refusedCount = 0;
                        $totalWeightSold = 0.00;
                        $totalEarned = 0.00;

                        $dashboardMessage = '';

                        // Use prepared statements to prevent SQL injection and add error handling
                        $stmt = $con->prepare("SELECT COUNT(*) as total_requests,
                            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as in_progress,
                            SUM(CASE WHEN status = 'Collected' OR status = 'Completed' THEN 1 ELSE 0 END) as completed,
                            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as refused,
                            SUM(CASE WHEN status = 'Collected' OR status = 'Completed' THEN final_weight ELSE 0 END) as total_weight_sold,
                            SUM(CASE WHEN status = 'Collected' OR status = 'Completed' THEN total_cost ELSE 0 END) as total_earned
                            FROM pickup_requests WHERE user_id = ?");

                        if ($stmt === false) {
                            $dashboardMessage = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Failed to prepare dashboard query. Please check your SQL syntax or table structure.</p></div>';
                        } else {
                            $stmt->bind_param("i", $user_id);
                            if ($stmt->execute()) {
                                $result = $stmt->get_result();
                                $row = $result->fetch_assoc();
                                if ($row) {
                                    $totalRequests = $row['total_requests'];
                                    $inProgressCount = $row['in_progress'];
                                    $completedCount = $row['completed'];
                                    $refusedCount = $row['refused'];
                                    $totalWeightSold = $row['total_weight_sold'] ?? 0.00;
                                    $totalEarned = $row['total_earned'] ?? 0.00;
                                }
                            } else {
                                $dashboardMessage = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Failed to execute dashboard query: ' . htmlspecialchars($stmt->error) . '</p></div>';
                            }
                            $stmt->close();
                        }

                        // Fetch waste rates for the new table
                        $waste_rates_query = $con->query("SELECT * FROM waste_rates ORDER BY waste_type ASC");
                        if ($waste_rates_query === false) {
                            $dashboardMessage .= '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Failed to retrieve waste rates. Check if the "waste_rates" table exists.</p></div>';
                            $waste_rates = [];
                        } else {
                            $waste_rates = $waste_rates_query->fetch_all(MYSQLI_ASSOC);
                        }
                ?>
                        <?= $dashboardMessage; ?>
                        <!-- Dashboard Container -->
                        <div class="grid grid-cols-1 lg:grid-cols-[2fr,1fr] gap-8">
                            <!-- Left side: Metric Cards and Button -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <!-- Total Requests Card -->
                                <div class="bg-blue-500 text-white rounded-2xl shadow-lg p-6 flex flex-col items-center justify-center transform hover:scale-105 transition-transform duration-300 h-40">
                                    <div class="count-text">Total Requests</div>
                                    <div class="count-value"><?= htmlspecialchars($totalRequests) ?></div>
                                </div>
                                <!-- In Progress Card -->
                                <div class="bg-yellow-500 text-white rounded-2xl shadow-lg p-6 flex flex-col items-center justify-center transform hover:scale-105 transition-transform duration-300 h-40">
                                    <div class="count-text">In Progress</div>
                                    <div class="count-value"><?= htmlspecialchars($inProgressCount) ?></div>
                                </div>
                                <!-- Completed Card -->
                                <div class="bg-green-500 text-white rounded-2xl shadow-lg p-6 flex flex-col items-center justify-center transform hover:scale-105 transition-transform duration-300 h-40">
                                    <div class="count-text">Completed</div>
                                    <div class="count-value"><?= htmlspecialchars($completedCount) ?></div>
                                </div>
                                <!-- Refused Pickup Card -->
                                <div class="bg-red-500 text-white rounded-2xl shadow-lg p-6 flex flex-col items-center justify-center transform hover:scale-105 transition-transform duration-300 h-40">
                                    <div class="count-text">Refused Pickup</div>
                                    <div class="count-value"><?= htmlspecialchars($refusedCount) ?></div>
                                </div>
                                <!-- Total Weight Sold Card -->
                                <div class="bg-purple-500 text-white rounded-2xl shadow-lg p-6 flex flex-col items-center justify-center transform hover:scale-105 transition-transform duration-300 h-40">
                                    <div class="count-text">Total Weight Sold (KG)</div>
                                    <div class="count-value"><?= htmlspecialchars(number_format($totalWeightSold, 2)) ?></div>
                                </div>
                                <!-- Total Earned Card -->
                                <div class="bg-pink-500 text-white rounded-2xl shadow-lg p-6 flex flex-col items-center justify-center transform hover:scale-105 transition-transform duration-300 h-40">
                                    <div class="count-text">Total Earned</div>
                                    <div class="count-value">Rs. <?= htmlspecialchars(number_format($totalEarned, 2)) ?></div>
                                </div>
                                <!-- Button to Request a New Pickup -->
                                <div class="col-span-1 sm:col-span-2 mt-4">
                                    <a href="?page=new_pickup" class="w-full inline-flex items-center justify-center px-8 py-4 rounded-full text-xl font-bold bg-green-500 text-white hover:bg-green-600 transition-colors duration-300 shadow-lg transform hover:scale-105">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 mr-3">
                                            <path fill-rule="evenodd" d="M12 5.25a.75.75 0 0 1 .75.75v5.25H18a.75.75 0 0 1 0 1.5h-5.25V18a.75.75 0 0 1-1.5 0v-5.25H6a.75.75 0 0 1 0-1.5h5.25V6a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
                                        </svg>
                                        Request a New Pickup
                                    </a>
                                </div>
                            </div>
                            <!-- Right side: Current Waste Rates Table -->
                            <div class="bg-white p-6 rounded-2xl shadow-lg">
                                <h2 class="text-xl font-bold text-gray-800 mb-4">Current Waste Rates</h2>
                                <div class="scrollable-table-container">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50 sticky top-0">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waste Type</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate (Rs./KG)</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php if (!empty($waste_rates)) : ?>
                                                <?php foreach ($waste_rates as $rate) : ?>
                                                    <tr class="bg-white hover:bg-gray-50">
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 capitalize"><?= htmlspecialchars($rate['waste_type']); ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars(number_format($rate['rate_per_kg'], 2)); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else : ?>
                                                <tr>
                                                    <td colspan="2" class="py-4 px-6 text-center text-gray-500">No waste rates found.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                <?php
                        break;

                    case 'new_pickup':
                ?>
                        <!-- Action Section: New Request Form -->
                        <section id="new-request-form-section">
                            <h2 class="text-3xl font-bold text-gray-800 mb-6">Create New Request</h2>
                            <div class="bg-white rounded-2xl shadow-lg p-6 md:p-8">
                                <form action="?page=new_pickup" method="POST" class="space-y-6">
                                    <!-- Hidden input for the button click -->
                                    <input type="hidden" name="request_pickup" value="1">

                                    <!-- Name Field -->
                                    <div>
                                        <label for="name" class="block text-gray-700 font-bold mb-2">Your Name</label>
                                        <input type="text" id="name" name="name" required class="shadow appearance-none border rounded-full w-full py-3 px-6 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-green-500 transition-all duration-200">
                                    </div>

                                    <!-- Waste Type Dropdown -->
                                    <div>
                                        <label for="wasteSelect" class="block text-gray-700 font-bold mb-2">Waste Type</label>
                                        <select id="wasteSelect" name="waste_type" required class="shadow appearance-none border rounded-full w-full py-3 px-6 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-green-500 transition-all duration-200">
                                            <option value="">-- Select Waste Type --</option>
                                            <?php foreach ($waste_options as $opt): ?>
                                                <option value="<?= htmlspecialchars($opt['waste_type']) ?>" data-rate="<?= htmlspecialchars($opt['rate_per_kg']) ?>"><?= htmlspecialchars($opt['waste_type']) ?></option>
                                            <?php endforeach; ?>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>

                                    <!-- Other Waste Type Input (Hidden by default) -->
                                    <div id="otherTypeBox" style="display:none;">
                                        <label for="other_waste_type" class="block text-gray-700 font-bold mb-2">Enter Other Waste Type</label>
                                        <input type="text" id="other_waste_type" name="other_waste_type" class="shadow appearance-none border rounded-full w-full py-3 px-6 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-green-500 transition-all duration-200" />
                                    </div>

                                    <!-- Dynamic Rate Display -->
                                    <div id="rateBox" class="text-lg font-semibold text-green-600"></div>

                                    <!-- Weight Field -->
                                    <div>
                                        <label for="weight" class="block text-gray-700 font-bold mb-2">Total Weight (kg)</label>
                                        <input type="number" step="0.01" id="weight" name="weight" required class="shadow appearance-none border rounded-full w-full py-3 px-6 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-green-500 transition-all duration-200">
                                    </div>

                                    <!-- Address Field -->
                                    <div>
                                        <label for="address" class="block text-gray-700 font-bold mb-2">Pickup Address</label>
                                        <textarea id="address" name="address" rows="3" required class="shadow appearance-none border rounded-xl w-full py-3 px-6 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-green-500 transition-all duration-200"></textarea>
                                    </div>
                                    
                                    <!-- Map Section -->
                                    <div>
                                        <label class="block text-gray-700 font-bold mb-2">Select Location on Map</label>
                                        <div id="map" class="rounded-xl shadow-lg"></div>
                                        <p class="text-sm text-gray-500 mt-2">Drag the marker to your precise location.</p>
                                    </div>

                                    <!-- Hidden inputs for coordinates and rate -->
                                    <input type="hidden" name="latitude" id="latitude">
                                    <input type="hidden" name="longitude" id="longitude">
                                    <input type="hidden" name="rate_per_kg" id="rate_per_kg">

                                    <!-- Submit Button -->
                                    <div class="flex items-center justify-between pt-4">
                                        <button type="submit" name="request_pickup" class="w-full inline-flex items-center justify-center px-8 py-4 rounded-full text-xl font-bold bg-green-500 text-white hover:bg-green-600 transition-colors duration-300 shadow-lg transform hover:scale-105">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 mr-3">
                                                <path fill-rule="evenodd" d="M12 5.25a.75.75 0 0 1 .75.75v5.25H18a.75.75 0 0 1 0 1.5h-5.25V18a.75.75 0 0 1-1.5 0v-5.25H6a.75.75 0 0 1 0-1.5h5.25V6a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
                                            </svg>
                                            Submit Request
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </section>
                <?php
                        break;

                    case 'pickup_history':
                        $message = $_SESSION['message'] ?? '';
                        unset($_SESSION['message']); // Clear the message after displaying it
                        if (!empty($message)) {
                            echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Success:</p><p class="text-sm">' . htmlspecialchars($message) . '</p></div>';
                        }
                        
                        $stmt = $con->prepare("SELECT pr.*, wr.rate_per_kg FROM pickup_requests pr LEFT JOIN waste_rates wr ON pr.waste_type = wr.waste_type WHERE pr.user_id = ? ORDER BY pr.created_at DESC");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $history_result = $stmt->get_result();
                        $pickups = $history_result->fetch_all(MYSQLI_ASSOC);
                        $stmt->close();
                ?>
                        <!-- My Requests History Section -->
                        <section id="my-requests-section">
                            <h2 class="text-3xl font-bold text-gray-800 mb-6">My Waste Requests</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <?php if (!empty($pickups)) : ?>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($pickups as $pickup) : ?>
                                        <?php
                                        // Determine colors and icons based on status
                                        $status = htmlspecialchars($pickup['status']);
                                        $status_class = '';
                                        $icon_svg = '';
                                        if ($status == 'Pending') {
                                            $status_class = 'bg-yellow-50 text-yellow-600 border-yellow-200';
                                            $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                                                <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6a.75.75 0 0 0 .75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z" clip-rule="evenodd" />
                                            </svg>';
                                        } elseif ($status == 'Approved') {
                                            $status_class = 'bg-blue-50 text-blue-600 border-blue-200';
                                            $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                                                <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.882l-3.484 4.474-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.25Z" clip-rule="evenodd" />
                                            </svg>';
                                        } elseif ($status == 'Collected' || $status == 'Completed') {
                                            $status_class = 'bg-green-50 text-green-600 border-green-200';
                                            $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                                                <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.882l-3.484 4.474-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.25Z" clip-rule="evenodd" />
                                            </svg>';
                                        } elseif ($status == 'Rejected') {
                                            $status_class = 'bg-red-50 text-red-600 border-red-200';
                                            $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                                                <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-2.67 2.67a.75.75 0 1 0 1.06 1.06l2.67-2.67 2.67 2.67a.75.75 0 1 0 1.06-1.06L13.06 12l2.67-2.67a.75.75 0 1 0-1.06-1.06l-2.67 2.67-2.67-2.67Z" clip-rule="evenodd" />
                                            </svg>';
                                        }
                                        ?>
                                        <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-200 transition-transform duration-300 hover:scale-105 hover:shadow-xl">
                                            <div class="flex items-center justify-between mb-4">
                                                <span class="text-xl font-bold text-gray-700">Request #<?= $counter++; ?></span>
                                                <div class="flex items-center px-3 py-1 rounded-full text-sm font-semibold border-2 <?= $status_class ?>">
                                                    <?= $icon_svg ?>
                                                    <span class="ml-2"><?= $status ?></span>
                                                </div>
                                            </div>
                                            <div class="space-y-2 text-gray-600">
                                                <p><strong>Waste Type:</strong> <span class="capitalize"><?= htmlspecialchars($pickup['waste_type']); ?></span></p>
                                                <p><strong>Estimated Weight:</strong> <?= number_format($pickup['weight'], 2); ?> kg</p>
                                                <p><strong>Requested on:</strong> <?= date('M d, Y', strtotime(htmlspecialchars($pickup['created_at']))); ?></p>
                                                <?php if ($status == 'Collected' || $status == 'Completed') : ?>
                                                    <?php
                                                        $total_cost = ($pickup['final_weight'] ?? 0) * ($pickup['rate'] ?? 0);
                                                    ?>
                                                    <div class="pt-2 mt-4 border-t border-dashed border-gray-300">
                                                        <p class="text-lg font-bold text-green-600">Total Earned: Rs. <?= number_format($total_cost, 2) ?></p>
                                                        <?php if ($pickup['final_weight'] > 0) : ?>
                                                             <p class="text-xs text-gray-500 mt-1">(Final weight: <?= number_format($pickup['final_weight'], 2); ?> kg @ Rs. <?= number_format($pickup['rate'], 2); ?>/kg)</p>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <div class="col-span-1 md:col-span-2 lg:col-span-3 bg-white p-6 rounded-2xl shadow-lg text-center text-gray-500">
                                        <p class="text-lg font-semibold mb-2">No pickup requests found.</p>
                                        <p class="text-sm">You can start a new request from the sidebar.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                <?php
                        break;
                    default:
                        echo '<p class="text-gray-500">Page not found.</p>';
                        break;
                }
                ?>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <script>
        // Sidebar toggle logic for mobile
        const menuButton = document.getElementById('menu-button-top');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const header = document.querySelector('header');

        menuButton.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            header.classList.toggle('fixed');
        });
        
        // Close sidebar when clicking outside on mobile
        mainContent.addEventListener('click', (event) => {
            if (!sidebar.classList.contains('-translate-x-full') && window.innerWidth < 768) {
                sidebar.classList.add('-translate-x-full');
                header.classList.remove('fixed');
            }
        });

        // --- New Pickup Form Logic ---
        document.addEventListener('DOMContentLoaded', function () {
            const wasteSelect = document.getElementById('wasteSelect');
            const otherTypeBox = document.getElementById('otherTypeBox');
            const rateBox = document.getElementById('rateBox');
            const rateInput = document.getElementById('rate_per_kg');
            
            // Handle rate display and other input
            if (wasteSelect) {
                wasteSelect.addEventListener("change", function () {
                    const selected = this.options[this.selectedIndex];
                    const rate = selected.getAttribute("data-rate");

                    if (this.value === "Other") {
                        otherTypeBox.style.display = "block";
                        rateBox.innerText = "Rate: To be evaluated by collector";
                        rateInput.value = 0;
                    } else {
                        otherTypeBox.style.display = "none";
                        rateBox.innerText = `Rate: Rs. ${rate} per kg`;
                        rateInput.value = rate;
                    }
                });
            }

            // Initialize Leaflet Map
            const mapContainer = document.getElementById('map');
            if (mapContainer) {
                const defaultLat = 27.7172; // Default to Kathmandu, Nepal
                const defaultLng = 85.3240;

                const map = L.map('map').setView([defaultLat, defaultLng], 13);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                const marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

                // Set initial values
                document.getElementById("latitude").value = defaultLat;
                document.getElementById("longitude").value = defaultLng;

                // Function to handle successful geolocation
                function onLocationFound(e) {
                    const lat = e.latlng.lat;
                    const lng = e.latlng.lng;
                    map.setView(e.latlng, 16);
                    marker.setLatLng(e.latlng);
                    document.getElementById("latitude").value = lat;
                    document.getElementById("longitude").value = lng;
                }

                // Function to handle geolocation error
                function onLocationError(e) {
                    console.error("Geolocation failed:", e.message);
                    // Alert replacement for better UI
                    // A message could be displayed on the page instead.
                }

                // Request user's current location on page load
                map.on('locationfound', onLocationFound);
                map.on('locationerror', onLocationError);
                map.locate({ setView: false, maxZoom: 16 });

                // Update coordinates on drag
                marker.on('dragend', function (e) {
                    const pos = marker.getLatLng();
                    document.getElementById("latitude").value = pos.lat;
                    document.getElementById("longitude").value = pos.lng;
                });
            }
        });
    </script>
</body>
</html>
<?php
if (isset($con)) {
    $con->close();
}
?>
