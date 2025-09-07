<?php
// Start the session to access session variables.
session_start();

// Handle AJAX requests for chart data before session check
if (isset($_GET['page']) && $_GET['page'] === 'rates' && isset($_GET['waste_type'])) {
    // Include the database connection file.
    if (!file_exists('connect.php')) {
        die("Error: The 'connect.php' file is missing. Please ensure it's in the same directory.");
    }
    include_once 'connect.php';
    
    // Check if the mysqli connection object is set.
    if (!isset($con) || $con->connect_error) {
        die("Error: The database connection failed. Please ensure 'connect.php' is in the same directory and the credentials are correct.");
    }
    
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    
    $waste_type = mysqli_real_escape_string($con, $_GET['waste_type']);
    
    // Debug: Log the request
    error_log("Chart AJAX request for waste_type: " . $waste_type);
    
    // First, get historical data
    $stmt = $con->prepare("SELECT rate_per_kg, updated_at FROM waste_rate_history WHERE waste_type = ? ORDER BY updated_at ASC");
    if ($stmt === false) {
        echo json_encode(['error' => 'Failed to prepare statement: ' . $con->error]);
        exit();
    }
    
    $stmt->bind_param("s", $waste_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $history_data = [];
    
    while ($row = $result->fetch_assoc()) {
        $history_data[] = $row;
    }
    $stmt->close();
    
    // Now get the current rate from waste_rates table
    $stmt_current = $con->prepare("SELECT rate_per_kg FROM waste_rates WHERE waste_type = ?");
    if ($stmt_current) {
        $stmt_current->bind_param("s", $waste_type);
        $stmt_current->execute();
        $result_current = $stmt_current->get_result();
        if ($row_current = $result_current->fetch_assoc()) {
            $current_rate = $row_current['rate_per_kg'];
            
            // Add current rate as the latest data point if it's different from the last historical rate
            $last_historical_rate = null;
            if (!empty($history_data)) {
                $last_historical_rate = end($history_data)['rate_per_kg'];
            }
            
            // Only add current rate if it's different from the last historical rate or if there's no history
            if ($last_historical_rate === null || $current_rate != $last_historical_rate) {
                $history_data[] = [
                    'rate_per_kg' => $current_rate,
                    'updated_at' => date('Y-m-d H:i:s') // Current timestamp
                ];
            }
        }
        $stmt_current->close();
    }
    
    // Debug: Log the result
    error_log("Chart data count for '" . $waste_type . "': " . count($history_data));
    
    echo json_encode(['history' => $history_data]);
    $con->close();
    exit();
}

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
                $name = $username; // Use the username from session
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

<?php include 'include/header.php'; ?>

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
            font-size: 0.875rem; /* text-sm */
            font-weight: 600;
            text-align: center;
            line-height: 1.2;
            margin-bottom: 0.5rem;
            max-width: 100%;
            word-wrap: break-word;
            hyphens: auto;
        }
        .count-value {
            font-size: 2rem; /* text-3xl */
            font-weight: 700;
            text-align: center;
            line-height: 1;
            margin-top: 0.25rem;
            max-width: 100%;
            word-wrap: break-word;
        }
        @media (min-width: 640px) {
            .count-text {
                font-size: 1rem; /* text-base */
            }
            .count-value {
                font-size: 2.5rem; /* text-4xl */
            }
        }
        @media (min-width: 1024px) {
            .count-text {
                font-size: 1.125rem; /* text-lg */
            }
            .count-value {
                font-size: 3rem; /* text-5xl */
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
        <h1 class="text-xl font-bold text-gray-800">Welcome</h1>
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
                    <h2 class="text-2xl font-bold">Welcome</h2>
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
                    <li>
                        <a href="?page=rates" class="flex items-center p-3 rounded-lg hover:bg-gray-700 transition-colors duration-200 <?php echo $currentPage == 'rates' ? 'bg-gray-700' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 mr-3">
                                <path d="M11.474 1.296a.75.75 0 0 1 1.052 0l7.5 7.5a.75.75 0 0 1-1.052 1.052L12 3.864 4.026 9.848a.75.75 0 0 1-1.052-1.052l7.5-7.5Z" />
                                <path fill-rule="evenodd" d="M3 13.5a.75.75 0 0 1 .75.75v6.75a.75.75 0 0 0 .75.75h1.5a.75.75 0 0 0 .75-.75v-6.75a.75.75 0 0 1 1.5 0v6.75a.75.75 0 0 0 .75.75h1.5a.75.75 0 0 0 .75-.75v-6.75a.75.75 0 0 1 1.5 0v6.75a.75.75 0 0 0 .75.75h1.5a.75.75 0 0 0 .75-.75v-6.75a.75.75 0 0 1 1.5 0v6.75c0 .414-.336.75-.75.75H17.25a.75.75 0 0 1-.75-.75v-6.75a.75.75 0 0 0-1.5 0v6.75a.75.75 0 0 0-.75.75h-1.5a.75.75 0 0 0-.75-.75v-6.75a.75.75 0 0 0-1.5 0v6.75a.75.75 0 0 0-.75.75h-1.5a.75.75 0 0 0-.75-.75v-6.75a.75.75 0 0 0-1.5 0v6.75A.75.75 0 0 1 3 21.75V13.5Z" clip-rule="evenodd" />
                            </svg>
                            <span>Rates</span>
                        </a>
                    </li>
                    <li>
                        <a href="?page=reusable" class="flex items-center p-3 rounded-lg hover:bg-gray-700 transition-colors duration-200 <?php echo $currentPage == 'reusable' ? 'bg-gray-700' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 mr-3">
                                <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM6.262 6.262a.75.75 0 0 1 1.061 0L10.5 9.44V6.75a.75.75 0 0 1 1.5 0v4.5a.75.75 0 0 1-.75.75h-4.5a.75.75 0 0 1 0-1.5h2.69L6.262 7.323a.75.75 0 0 1 0-1.061ZM17.738 17.738a.75.75 0 0 1-1.061 0L13.5 14.56v2.69a.75.75 0 0 1-1.5 0v-4.5a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 0 1.5h-2.69l2.678 2.678a.75.75 0 0 1 0 1.061Z" clip-rule="evenodd" />
                            </svg>
                            <span>Reusable Items</span>
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
                                <div class="bg-blue-500 text-white rounded-2xl shadow-lg p-4 flex flex-col items-center justify-center transform hover:scale-105 transition-transform duration-300 min-h-[160px] w-full">
                                    <div class="count-text">Total Requests</div>
                                    <div class="count-value"><?= htmlspecialchars($totalRequests) ?></div>
                                </div>
                                <!-- In Progress Card -->
                                <div class="bg-yellow-500 text-white rounded-2xl shadow-lg p-4 flex flex-col items-center justify-center transform hover:scale-105 transition-transform duration-300 min-h-[160px] w-full">
                                    <div class="count-text">In Progress</div>
                                    <div class="count-value"><?= htmlspecialchars($inProgressCount) ?></div>
                                </div>
                                <!-- Completed Card -->
                                <div class="bg-green-500 text-white rounded-2xl shadow-lg p-4 flex flex-col items-center justify-center transform hover:scale-105 transition-transform duration-300 min-h-[160px] w-full">
                                    <div class="count-text">Completed</div>
                                    <div class="count-value"><?= htmlspecialchars($completedCount) ?></div>
                                </div>
                                <!-- Refused Pickup Card -->
                                <div class="bg-red-500 text-white rounded-2xl shadow-lg p-4 flex flex-col items-center justify-center transform hover:scale-105 transition-transform duration-300 min-h-[160px] w-full">
                                    <div class="count-text">Refused Pickup</div>
                                    <div class="count-value"><?= htmlspecialchars($refusedCount) ?></div>
                                </div>
                                <!-- Total Weight Sold Card -->
                                <div class="bg-purple-500 text-white rounded-2xl shadow-lg p-4 flex flex-col items-center justify-center transform hover:scale-105 transition-transform duration-300 min-h-[160px] w-full">
                                    <div class="count-text">Weight Sold (KG)</div>
                                    <div class="count-value"><?= htmlspecialchars(number_format($totalWeightSold, 2)) ?></div>
                                </div>
                                <!-- Total Earned Card -->
                                <div class="bg-pink-500 text-white rounded-2xl shadow-lg p-4 flex flex-col items-center justify-center transform hover:scale-105 transition-transform duration-300 min-h-[160px] w-full">
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
                                
                                <!-- Recent History Box -->
                                <div class="col-span-1 sm:col-span-2 mt-6">
                                    <div class="bg-white rounded-2xl shadow-lg p-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="text-xl font-bold text-gray-800">Recent Activity</h3>
                                            <a href="?page=pickup_history" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</a>
                                        </div>
                                        <div class="space-y-3">
                                            <?php
                                            // Get recent pickup requests (last 5)
                                            if ($user_id) {
                                                $recent_stmt = $con->prepare("SELECT pr.*, wr.rate_per_kg FROM pickup_requests pr LEFT JOIN waste_rates wr ON pr.waste_type = wr.waste_type WHERE pr.user_id = ? ORDER BY pr.created_at DESC LIMIT 5");
                                                if ($recent_stmt) {
                                                    $recent_stmt->bind_param("i", $user_id);
                                                    $recent_stmt->execute();
                                                    $recent_result = $recent_stmt->get_result();
                                                    $recent_pickups = $recent_result->fetch_all(MYSQLI_ASSOC);
                                                    $recent_stmt->close();
                                                } else {
                                                    $recent_pickups = [];
                                                }
                                            } else {
                                                $recent_pickups = [];
                                            }
                                            
                                            if (!empty($recent_pickups)) :
                                                foreach ($recent_pickups as $pickup) :
                                                    $status = htmlspecialchars($pickup['status']);
                                                    $status_class = '';
                                                    $status_icon = '';
                                                    
                                                    if ($status == 'Pending') {
                                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                                        $status_icon = '⏳';
                                                    } elseif ($status == 'Approved') {
                                                        $status_class = 'bg-blue-100 text-blue-800';
                                                        $status_icon = '✅';
                                                    } elseif ($status == 'Collected' || $status == 'Completed') {
                                                        $status_class = 'bg-green-100 text-green-800';
                                                        $status_icon = '🎉';
                                                    } elseif ($status == 'Rejected') {
                                                        $status_class = 'bg-red-100 text-red-800';
                                                        $status_icon = '❌';
                                                    }
                                                    
                                                    $date = date('M j, Y', strtotime($pickup['created_at']));
                                                    $weight = htmlspecialchars($pickup['weight'] ?? 0);
                                                    $waste_type = htmlspecialchars(ucfirst($pickup['waste_type'] ?? 'Unknown'));
                                                    $earnings = floatval($weight) * floatval($pickup['rate_per_kg'] ?? 0);
                                            ?>
                                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                                        <div class="flex items-center space-x-3">
                                                            <span class="text-lg"><?= $status_icon ?></span>
                                                            <div>
                                                                <p class="font-medium text-gray-900"><?= $waste_type ?> - <?= $weight ?>kg</p>
                                                                <p class="text-sm text-gray-500"><?= $date ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="text-right">
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $status_class ?>">
                                                                <?= $status ?>
                                                            </span>
                                                            <?php if ($status == 'Collected' || $status == 'Completed') : ?>
                                                                <p class="text-sm font-medium text-green-600 mt-1">Rs. <?= number_format($earnings, 2) ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                            <?php 
                                                endforeach;
                                            else :
                                            ?>
                                                <div class="text-center py-8 text-gray-500">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-12 h-12 mx-auto mb-3 text-gray-300">
                                                        <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6a.75.75 0 0 0 .75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z" clip-rule="evenodd" />
                                                    </svg>
                                                    <p>No recent activity</p>
                                                    <p class="text-sm">Start by requesting your first pickup!</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
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

                                    <!-- Address Field (Auto-populated from map) -->
                                    <div>
                                        <label for="address" class="block text-gray-700 font-bold mb-2">Pickup Address</label>
                                        <textarea id="address" name="address" rows="3" required class="shadow appearance-none border rounded-xl w-full py-3 px-6 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-green-500 transition-all duration-200 bg-white" placeholder="Address will be automatically detected from map location..."></textarea>
                                        <div class="flex items-center justify-between mt-1">
                                            <p class="text-sm text-gray-500">Address will be automatically detected when you select a location on the map.</p>
                                            <button type="button" id="manualAddressBtn" class="text-blue-600 hover:text-blue-800 text-sm font-medium underline">
                                                Enter manually
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Map Section -->
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <label class="block text-gray-700 font-bold">Select Location on Map</label>
                                            <div class="flex space-x-2">
                                                <button type="button" id="getLocationBtn" class="text-green-600 hover:text-green-800 text-sm font-medium flex items-center bg-green-50 px-3 py-1 rounded-lg hover:bg-green-100 transition-colors">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 mr-1">
                                                        <path fill-rule="evenodd" d="M8.161 2.58a1.875 1.875 0 0 1 1.678 0l4.993 2.498c.106.052.23.052.336 0l3.869-1.935A1.875 1.875 0 0 1 21.75 4.82v12.485c0 .71-.401 1.36-1.037 1.677l-4.875 2.437a1.875 1.875 0 0 1-1.676 0l-4.994-2.497a.375.375 0 0 0-.336 0l-3.868 1.935A1.875 1.875 0 0 1 2.25 19.18V6.695c0-.71.401-1.36 1.037-1.677l4.874-2.437ZM9 6.75a.75.75 0 0 0-.75.75v6c0 .414.336.75.75.75h6a.75.75 0 0 0 .75-.75v-6a.75.75 0 0 0-.75-.75H9Z" clip-rule="evenodd" />
                                                    </svg>
                                                    Get My Location
                                                </button>
                                                <button type="button" id="expandMapBtn" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 mr-1">
                                                        <path fill-rule="evenodd" d="M3.75 3.75v4.5a.75.75 0 0 1-1.5 0v-4.5A2.25 2.25 0 0 1 4.5 1.5h4.5a.75.75 0 0 1 0 1.5H4.5a.75.75 0 0 0-.75.75ZM3.75 15.75v4.5a.75.75 0 0 0 .75.75h4.5a.75.75 0 0 0 0-1.5H4.5a.75.75 0 0 1-.75-.75v-4.5a.75.75 0 0 0-1.5 0ZM15.75 3.75h4.5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0V4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 1 0-1.5ZM15.75 15.75a.75.75 0 0 1 1.5 0v4.5a.75.75 0 0 1-.75.75h-4.5a.75.75 0 0 1 0-1.5h4.5a.75.75 0 0 0 .75-.75v-4.5Z" clip-rule="evenodd" />
                                                    </svg>
                                                    Expand Map
                                                </button>
                                            </div>
                                        </div>
                                        <div id="map" class="rounded-xl shadow-lg" style="height: 300px;"></div>
                                        <p class="text-sm text-gray-500 mt-2">Drag the marker to your precise location. Address will be automatically detected.</p>
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

                    case 'rates':
                        // Include the rate history functionality


                        // Fetch current rates from the waste_rates table
                        $current_rates = [];
                        $stmt_current = $con->prepare("SELECT waste_type, rate_per_kg FROM waste_rates ORDER BY waste_type ASC");
                        $stmt_current->execute();
                        $result_current = $stmt_current->get_result();
                        if ($result_current) {
                            while ($row = mysqli_fetch_assoc($result_current)) {
                                // Find the previous rate from history for comparison
                                $previous_rate = null;
                                $stmt_prev = $con->prepare("SELECT rate_per_kg FROM waste_rate_history WHERE waste_type = ? ORDER BY updated_at DESC LIMIT 1");
                                if ($stmt_prev) {
                                    $stmt_prev->bind_param("s", $row['waste_type']);
                                    $stmt_prev->execute();
                                    $res_prev = $stmt_prev->get_result();
                                    if ($row_prev = $res_prev->fetch_assoc()) {
                                        $previous_rate = $row_prev['rate_per_kg'];
                                    }
                                    $stmt_prev->close();
                                }
                                $row['previous_rate'] = $previous_rate;
                                $current_rates[] = $row;
                            }
                        }
                        $stmt_current->close();

                        // Fetch recent rate changes for the second table on the right
                        $recent_changes = [];
                        $result_recent = mysqli_query($con, "SELECT waste_type, rate_per_kg, updated_at FROM waste_rate_history ORDER BY updated_at DESC LIMIT 5");
                        if ($result_recent) {
                            while ($row = mysqli_fetch_assoc($result_recent)) {
                                $recent_changes[] = $row;
                            }
                        }
                ?>
                        <!-- Waste Rate History Section -->
                        <section id="waste-rates-section">
                            <h2 class="text-3xl font-bold text-gray-800 mb-6">Waste Rate History</h2>
                            
                            <!-- Main Content Grid -->
                            <div class="grid grid-cols-1 lg:grid-cols-[2fr,1fr] gap-8">
                                <!-- Left side: Chart -->
                                <div class="bg-white p-6 rounded-2xl shadow-lg">
                                    <div class="mb-6">
                                        <label for="wasteTypeFilter" class="block text-sm font-medium text-gray-700 mb-2">Select Waste Type:</label>
                                        <select id="wasteTypeFilter" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
                                            <?php foreach ($current_rates as $rate): ?>
                                                <option value="<?= htmlspecialchars($rate['waste_type']) ?>"><?= htmlspecialchars($rate['waste_type']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="chart-container">
                                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Rate Change Chart</h3>
                                        <div id="chartLoading" class="text-center py-8 text-gray-500" style="display: none;">
                                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-green-500"></div>
                                            <p class="mt-2">Loading chart data...</p>
                                        </div>
                                        <div style="position: relative; height: 400px; width: 100%;">
                                            <canvas id="rateChart"></canvas>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right side: Current Rates and Recent Changes -->
                                <div class="space-y-6">
                                    <!-- Current Rates -->
                                    <div class="bg-white p-6 rounded-2xl shadow-lg">
                                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Current Rates</h3>
                                        <?php if (count($current_rates) === 0): ?>
                                            <p class="text-gray-500 text-center py-4">No current rates found.</p>
                                        <?php else: ?>
                                            <div class="space-y-3">
                                                <?php foreach ($current_rates as $rate): ?>
                                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                                                        <div>
                                                            <h4 class="font-semibold text-gray-800 capitalize"><?= htmlspecialchars($rate['waste_type']) ?></h4>
                                                            <p class="text-lg font-bold text-green-600">Rs. <?= htmlspecialchars($rate['rate_per_kg']) ?>/kg</p>
                                                        </div>
                                                        <div class="text-right">
                                                            <?php 
                                                                $icon = '';
                                                                $status = 'Stable';
                                                                $class = 'text-gray-500';
                                                                if ($rate['previous_rate'] !== null) {
                                                                    if ($rate['rate_per_kg'] > $rate['previous_rate']) {
                                                                        $icon = '↗';
                                                                        $status = 'Increased';
                                                                        $class = 'text-green-600';
                                                                    } elseif ($rate['rate_per_kg'] < $rate['previous_rate']) {
                                                                        $icon = '↘';
                                                                        $status = 'Decreased';
                                                                        $class = 'text-red-600';
                                                                    }
                                                                } else {
                                                                    $icon = '●';
                                                                    $status = 'New';
                                                                    $class = 'text-blue-600';
                                                                }
                                                            ?>
                                                            <span class="text-2xl <?= $class ?>"><?= $icon ?></span>
                                                            <p class="text-sm <?= $class ?>"><?= $status ?></p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Recent Changes -->
                                    <div class="bg-white p-6 rounded-2xl shadow-lg">
                                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Recent Changes</h3>
                                        <?php if (count($recent_changes) === 0): ?>
                                            <p class="text-gray-500 text-center py-4">No recent changes found.</p>
                                        <?php else: ?>
                                            <div class="space-y-3">
                                                <?php foreach ($recent_changes as $change): 
                                                    $status = 'Stable';
                                                    $icon = '●';
                                                    $class = 'text-gray-500';
                                                    
                                                    // Find the rate from the history table that came immediately before this one
                                                    $stmt_prev_hist = $con->prepare("SELECT rate_per_kg FROM waste_rate_history WHERE waste_type = ? AND updated_at < ? ORDER BY updated_at DESC LIMIT 1");
                                                    $stmt_prev_hist->bind_param("ss", $change['waste_type'], $change['updated_at']);
                                                    $stmt_prev_hist->execute();
                                                    $res_prev_hist = $stmt_prev_hist->get_result();
                                                    $prev_hist_rate = $res_prev_hist->fetch_assoc()['rate_per_kg'] ?? null;
                                                    $stmt_prev_hist->close();

                                                    if ($prev_hist_rate !== null) {
                                                        if ($change['rate_per_kg'] > $prev_hist_rate) {
                                                            $status = 'Increased';
                                                            $icon = '↗';
                                                            $class = 'text-green-600';
                                                        } elseif ($change['rate_per_kg'] < $prev_hist_rate) {
                                                            $status = 'Decreased';
                                                            $icon = '↘';
                                                            $class = 'text-red-600';
                                                        }
                                                    }
                                                ?>
                                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                                                        <div>
                                                            <p class="font-medium text-gray-800 capitalize"><?= htmlspecialchars($change['waste_type']) ?></p>
                                                            <p class="text-sm text-gray-600">Rs. <?= htmlspecialchars($change['rate_per_kg']) ?>/kg</p>
                                                            <p class="text-xs text-gray-500"><?= date('M d, Y', strtotime($change['updated_at'])) ?></p>
                                                        </div>
                                                        <div class="text-right">
                                                            <span class="text-xl <?= $class ?>"><?= $icon ?></span>
                                                            <p class="text-sm <?= $class ?>"><?= $status ?></p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Chart.js Script -->
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <script>
                            // Wait for DOM to be fully loaded
                            document.addEventListener('DOMContentLoaded', function() {
                                const wasteTypeFilter = document.getElementById('wasteTypeFilter');
                                const chartCanvas = document.getElementById('rateChart');
                                let rateChart = null;

                                // Debug function to check if elements exist
                                console.log('wasteTypeFilter:', wasteTypeFilter);
                                console.log('chartCanvas:', chartCanvas);

                                async function fetchHistory(wasteType) {
                                    try {
                                        console.log('Fetching history for:', wasteType);
                                        
                                        // Show loading indicator
                                        const loadingDiv = document.getElementById('chartLoading');
                                        const chartDiv = document.querySelector('#rateChart').parentElement;
                                        if (loadingDiv && chartDiv) {
                                            loadingDiv.style.display = 'block';
                                            chartDiv.style.display = 'none';
                                        }
                                        
                                        const response = await fetch(`?page=rates&waste_type=${encodeURIComponent(wasteType)}`);
                                        console.log('Response status:', response.status);
                                        
                                        if (!response.ok) {
                                            throw new Error(`HTTP error! status: ${response.status}`);
                                        }
                                        
                                        const data = await response.json();
                                        console.log('Fetched data:', data);
                                        
                                        // Hide loading indicator
                                        if (loadingDiv && chartDiv) {
                                            loadingDiv.style.display = 'none';
                                            chartDiv.style.display = 'block';
                                        }
                                        
                                        return data.history || [];
                                    } catch (error) {
                                        console.error('Error fetching data:', error);
                                        
                                        // Hide loading indicator on error
                                        const loadingDiv = document.getElementById('chartLoading');
                                        const chartDiv = document.querySelector('#rateChart').parentElement;
                                        if (loadingDiv && chartDiv) {
                                            loadingDiv.style.display = 'none';
                                            chartDiv.style.display = 'block';
                                        }
                                        
                                        return [];
                                    }
                                }

                                function createChart(history) {
                                    console.log('Creating chart with history:', history);
                                    
                                    if (rateChart) {
                                        rateChart.destroy();
                                        rateChart = null;
                                    }
                                    
                                    if (!history || history.length === 0) {
                                        // Show message when no data
                                        if (chartCanvas && chartCanvas.parentElement) {
                                            chartCanvas.parentElement.innerHTML = '<div class="text-center py-8 text-gray-500"><p>No historical data available for this waste type.</p></div>';
                                        }
                                        return;
                                    }

                                    const labels = history.map(item => {
                                        const date = new Date(item.updated_at);
                                        return date.toLocaleDateString('en-US', { 
                                            year: 'numeric', 
                                            month: 'short', 
                                            day: 'numeric', 
                                            hour: '2-digit', 
                                            minute: '2-digit' 
                                        });
                                    });
                                    const data = history.map(item => parseFloat(item.rate_per_kg));
                                    
                                    // Determine which point is the current rate (last point)
                                    const pointBackgroundColors = history.map((item, index) => {
                                        return index === history.length - 1 ? 'rgb(239, 68, 68)' : 'rgb(34, 197, 94)'; // Red for current, green for historical
                                    });
                                    const pointBorderColors = history.map((item, index) => {
                                        return index === history.length - 1 ? 'rgb(239, 68, 68)' : 'rgb(34, 197, 94)';
                                    });
                                    const pointRadius = history.map((item, index) => {
                                        return index === history.length - 1 ? 6 : 4; // Larger point for current rate
                                    });

                                    console.log('Chart labels:', labels);
                                    console.log('Chart data:', data);

                                    if (chartCanvas) {
                                        rateChart = new Chart(chartCanvas, {
                                            type: 'line',
                                            data: {
                                                labels: labels,
                                                datasets: [{
                                                    label: 'Rate per kg (Rs.)',
                                                    data: data,
                                                    borderColor: 'rgb(34, 197, 94)',
                                                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                                    tension: 0.1,
                                                    fill: true,
                                                    pointBackgroundColor: pointBackgroundColors,
                                                    pointBorderColor: pointBorderColors,
                                                    pointRadius: pointRadius,
                                                    pointHoverRadius: 8
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                interaction: {
                                                    intersect: false,
                                                    mode: 'index'
                                                },
                                                scales: {
                                                    y: {
                                                        beginAtZero: false,
                                                        title: {
                                                            display: true,
                                                            text: 'Rate (Rs./kg)'
                                                        },
                                                        grid: {
                                                            color: 'rgba(0, 0, 0, 0.1)'
                                                        }
                                                    },
                                                    x: {
                                                        title: {
                                                            display: true,
                                                            text: 'Date and Time'
                                                        },
                                                        grid: {
                                                            color: 'rgba(0, 0, 0, 0.1)'
                                                        }
                                                    }
                                                },
                                                plugins: {
                                                    legend: {
                                                        display: true,
                                                        position: 'top',
                                                        labels: {
                                                            generateLabels: function(chart) {
                                                                const original = Chart.defaults.plugins.legend.labels.generateLabels;
                                                                const labels = original.call(this, chart);
                                                                labels.push({
                                                                    text: '● Current Rate',
                                                                    fillStyle: 'rgb(239, 68, 68)',
                                                                    strokeStyle: 'rgb(239, 68, 68)',
                                                                    lineWidth: 0,
                                                                    pointStyle: 'circle',
                                                                    hidden: false,
                                                                    index: labels.length
                                                                });
                                                                return labels;
                                                            }
                                                        }
                                                    },
                                                    tooltip: {
                                                        callbacks: {
                                                            title: (tooltipItems) => {
                                                                return tooltipItems[0].label;
                                                            },
                                                            label: (tooltipItem) => {
                                                                const isCurrent = tooltipItem.dataIndex === data.length - 1;
                                                                const label = isCurrent ? 'Current Rate' : 'Historical Rate';
                                                                return `${label}: ${tooltipItem.raw} Rs./kg`;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        });
                                        console.log('Chart created successfully');
                                    } else {
                                        console.error('Chart canvas not found');
                                    }
                                }

                                async function updateHistory() {
                                    if (!wasteTypeFilter) {
                                        console.error('Waste type filter not found');
                                        return;
                                    }
                                    
                                    const selectedWasteType = wasteTypeFilter.value;
                                    console.log('Updating history for:', selectedWasteType);
                                    
                                    if (!selectedWasteType) {
                                        console.log('No waste type selected');
                                        return;
                                    }
                                    
                                    const historyData = await fetchHistory(selectedWasteType);
                                    createChart(historyData);
                                }


                                // Initialize chart when page loads
                                if (wasteTypeFilter && wasteTypeFilter.options.length > 0) {
                                    console.log('Initializing chart...');
                                    updateHistory();
                                    wasteTypeFilter.addEventListener('change', updateHistory);
                                } else {
                                    console.error('Waste type filter not found or has no options');
                                }
                            });
                        </script>
                <?php
                        break;

                    case 'reusable':
                        // Get the listing IDs the current user has shown interest in
                        $userInterests = [];
                        $interestsQuery = "SELECT listing_id FROM user_interests WHERE user_id = ?";
                        $stmt = $con->prepare($interestsQuery);
                        if ($stmt) {
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $interestsResult = $stmt->get_result();
                            while ($row = $interestsResult->fetch_assoc()) {
                                $userInterests[] = $row['listing_id'];
                            }
                            $stmt->close();
                        }

                        // Start building the SQL query for the main listings
                        $sql = "SELECT listing_id, title, description, quantity, image, status, price FROM reusable_waste_listings";
                        $whereClauses = [];

                        // Add a WHERE clause for the search query
                        if (isset($_GET['search_query']) && $_GET['search_query'] != '') {
                            $searchQuery = $con->real_escape_string($_GET['search_query']);
                            $whereClauses[] = "title LIKE '%$searchQuery%'";
                        }

                        // Handle different filter options
                        if (isset($_GET['status']) && $_GET['status'] === 'Interested') {
                            // For interested items, we need to join with user_interests table
                            $sql = "SELECT rwl.listing_id, rwl.title, rwl.description, rwl.quantity, rwl.image, rwl.status, rwl.price 
                                    FROM reusable_waste_listings rwl 
                                    INNER JOIN user_interests ui ON rwl.listing_id = ui.listing_id 
                                    WHERE ui.user_id = ?";
                            
                            // Add search query if present
                            if (isset($_GET['search_query']) && $_GET['search_query'] != '') {
                                $searchQuery = $con->real_escape_string($_GET['search_query']);
                                $sql .= " AND rwl.title LIKE '%$searchQuery%'";
                            }
                            
                            $sql .= " ORDER BY rwl.title ASC";
                            
                            // Execute the interested items query
                            $stmt = $con->prepare($sql);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $reusableItems = [];
                            while($row = $result->fetch_assoc()) {
                                $row['isInterested'] = true; // All items in this result are interested
                                $reusableItems[] = $row;
                            }
                            $stmt->close();
                        } else {
                            // For other status filters (Available, Reserved) or All Items (empty)
                            if (isset($_GET['status']) && $_GET['status'] !== '') {
                                $status = $con->real_escape_string($_GET['status']);
                                $whereClauses[] = "status = '$status'";
                            }
                            // If status is empty (All Items), don't add any status filter
                            
                            // Combine all WHERE clauses with 'AND'
                            if (!empty($whereClauses)) {
                                $sql .= " WHERE " . implode(" AND ", $whereClauses);
                            }
                            
                            // Add the sorting clause
                            $sql .= " ORDER BY title ASC";
                            
                            // Execute the query
                            $result = $con->query($sql);
                            $reusableItems = [];
                            if ($result) {
                                while($row = $result->fetch_assoc()) {
                                    $row['isInterested'] = in_array($row['listing_id'], $userInterests);
                                    $reusableItems[] = $row;
                                }
                            }
                        }
                ?>
                        <!-- Reusable Items Section -->
                        <section id="reusable-items-section">
                            <h2 class="text-3xl font-bold text-gray-800 mb-6">Reusable Waste Marketplace</h2>
                            
                            <!-- Filter Container -->
                            <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
                                <form id="filterForm" method="get" action="">
                                    <input type="hidden" name="page" value="reusable">
                                    <div class="flex flex-col md:flex-row gap-4 items-center">
                                        <div class="flex-1">
                                            <input type="text" name="search_query" id="search-input" placeholder="Search for items..." value="<?php echo htmlspecialchars($_GET['search_query'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                                        </div>
                                        <div>
                                            <select name="status" id="status-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                                                <option value="">All Items</option>
                                                <option value="Available" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Available') ? 'selected' : ''; ?>>Available</option>
                                                <option value="Reserved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Reserved') ? 'selected' : ''; ?>>Reserved</option>
                                                <option value="Interested" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Interested') ? 'selected' : ''; ?>>Interested Items</option>
                                            </select>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Items Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                <?php if (!empty($reusableItems)) : ?>
                                    <?php foreach ($reusableItems as $item) : ?>
                                        <div class="bg-white rounded-2xl shadow-lg overflow-hidden transition-transform duration-300 hover:scale-105 hover:shadow-xl cursor-pointer" onclick='showReusableDetails(<?php echo json_encode($item); ?>)'>
                                            <div class="relative">
                                                <img src="<?php echo !empty($item["image"]) ? htmlspecialchars($item["image"]) : 'https://placehold.co/400x250/e3f2fd/1a237e?text=No+Image'; ?>" alt="<?php echo htmlspecialchars($item["title"]); ?>" class="w-full h-48 object-cover">
                                                <div class="absolute top-3 right-3">
                                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $item['status'] == 'Available' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                        <?php echo htmlspecialchars($item["status"]); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="p-4">
                                                <h3 class="text-lg font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($item["title"]); ?></h3>
                                                <p class="text-sm text-gray-600 mb-2 line-clamp-2"><?php echo htmlspecialchars($item["description"]); ?></p>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-xl font-bold text-green-600">Rs. <?php echo htmlspecialchars($item["price"]); ?></span>
                                                    <span class="text-sm text-gray-500">Qty: <?php echo htmlspecialchars($item["quantity"]); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <div class="col-span-full bg-white rounded-2xl shadow-lg p-8 text-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                                            <?php if (isset($_GET['status']) && $_GET['status'] === 'Interested'): ?>
                                                <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6a.75.75 0 0 0 .75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z" clip-rule="evenodd" />
                                            <?php else: ?>
                                                <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6a.75.75 0 0 0 .75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z" clip-rule="evenodd" />
                                            <?php endif; ?>
                                        </svg>
                                        <?php if (isset($_GET['status']) && $_GET['status'] === 'Interested'): ?>
                                            <p class="text-lg font-semibold text-gray-500 mb-2">No interested items yet</p>
                                            <p class="text-sm text-gray-400">Browse available items and show interest to see them here</p>
                                        <?php else: ?>
                                            <p class="text-lg font-semibold text-gray-500 mb-2">No reusable items found</p>
                                            <p class="text-sm text-gray-400">Try adjusting your search or filter criteria</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <!-- Modal for displaying listing details -->
                        <div id="reusableModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
                            <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                                <div class="p-6">
                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="text-2xl font-bold text-gray-800">Item Details</h3>
                                        <button onclick="closeReusableModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                                    </div>
                                    <img id="reusableModalImage" class="w-full h-64 object-cover rounded-lg mb-4" src="" alt="Item Image">
                                    <h4 id="reusableModalTitle" class="text-xl font-semibold text-gray-800 mb-2"></h4>
                                    <p class="text-gray-600 mb-2"><strong>Description:</strong> <span id="reusableModalDescription"></span></p>
                                    <p class="text-gray-600 mb-2"><strong>Quantity:</strong> <span id="reusableModalQuantity"></span></p>
                                    <p class="text-gray-600 mb-2"><strong>Price:</strong> <span id="reusableModalPrice"></span></p>
                                    <p class="text-gray-600 mb-4"><strong>Status:</strong> <span id="reusableModalStatus"></span></p>
                                    <input type="hidden" id="reusableModalListingId">
                                    <button id="reusableInterestButton" class="w-full bg-green-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-green-600 transition-colors" onclick="confirmReusablePurchase()">Show Interest</button>
                                    <p id="reusableConfirmationMessage" class="hidden mt-4 text-center"></p>
                                    <div id="reusableContactDetails" class="hidden mt-4 bg-gray-50 p-4 rounded-lg">
                                        <h5 class="font-semibold text-gray-800 mb-2">Contact Recycle Hub</h5>
                                        <p class="text-sm text-gray-600"><strong>Name:</strong> Recycle Hub</p>
                                        <p class="text-sm text-gray-600"><strong>Phone:</strong> 9876543210</p>
                                        <p class="text-sm text-gray-600"><strong>Email:</strong> recyclehub@gmail.com</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                            function showReusableDetails(item) {
                                // Store the current item globally for later updates
                                window.currentReusableItem = item;
                                
                                document.getElementById('reusableModalImage').src = item.image ? item.image : 'https://placehold.co/400x250/e3f2fd/1a237e?text=No+Image';
                                document.getElementById('reusableModalTitle').textContent = item.title;
                                document.getElementById('reusableModalDescription').textContent = item.description;
                                document.getElementById('reusableModalQuantity').textContent = item.quantity;
                                document.getElementById('reusableModalPrice').textContent = `Rs. ${item.price}`;
                                document.getElementById('reusableModalStatus').textContent = item.status;
                                document.getElementById('reusableModalListingId').value = item.listing_id;
                                
                                const interestButton = document.getElementById('reusableInterestButton');
                                const confirmationMessage = document.getElementById('reusableConfirmationMessage');
                                const contactDetails = document.getElementById('reusableContactDetails');
                                
                                if (item.status === 'Reserved' || item.isInterested) {
                                    interestButton.style.display = 'none';
                                    confirmationMessage.textContent = 'You have already shown interest in this item. Contact the seller to confirm your purchase.';
                                    confirmationMessage.className = 'block mt-4 text-center text-green-600';
                                    contactDetails.classList.remove('hidden');
                                } else {
                                    // Reset button state for available items
                                    interestButton.style.display = 'block';
                                    interestButton.disabled = false;
                                    interestButton.textContent = 'Show Interest';
                                    interestButton.className = 'w-full bg-green-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-green-600 transition-colors';
                                    confirmationMessage.classList.add('hidden');
                                    contactDetails.classList.add('hidden');
                                }
                                
                                document.getElementById('reusableModal').classList.remove('hidden');
                            }

                            function closeReusableModal() {
                                document.getElementById('reusableModal').classList.add('hidden');
                            }

                            function confirmReusablePurchase() {
                                const listingId = document.getElementById('reusableModalListingId').value;
                                const userId = "<?php echo $user_id; ?>";
                                const interestButton = document.getElementById('reusableInterestButton');
                                
                                if (!listingId || !userId) {
                                    console.error("Listing ID or User ID not found.");
                                    return;
                                }

                                // Disable button and show loading state
                                interestButton.disabled = true;
                                interestButton.textContent = 'Processing...';
                                interestButton.className = 'w-full bg-gray-400 text-white py-3 px-4 rounded-lg font-semibold cursor-not-allowed';

                                fetch('handle_interest.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                    },
                                    body: JSON.stringify({ listing_id: listingId, user_id: userId }),
                                })
                                .then(response => response.json())
                                .then(data => {
                                    const confirmationMessage = document.getElementById('reusableConfirmationMessage');
                                    const contactDetails = document.getElementById('reusableContactDetails');
                                    
                                    if (data.success) {
                                        // Update the item data structure to mark as interested
                                        window.currentReusableItem.isInterested = true;
                                        
                                        // Update the modal UI
                                        confirmationMessage.textContent = 'Informed to the seller. If you would like to confirm the buy, please contact the seller.';
                                        confirmationMessage.className = 'block mt-4 text-center text-green-600';
                                        contactDetails.classList.remove('hidden');
                                        interestButton.style.display = 'none';
                                        
                                        // Update the item card in the grid
                                        updateItemCardState(listingId, true);
                                        
                                        // Show a success message and refresh the page after a short delay
                                        // This ensures the user can see their interested items in the filter
                                        setTimeout(() => {
                                            // Check if we're currently viewing interested items
                                            const currentFilter = document.getElementById('status-filter').value;
                                            if (currentFilter === 'Interested') {
                                                // If viewing interested items, refresh to show the new item
                                                window.location.reload();
                                            } else {
                                                // If not viewing interested items, show a message about the filter
                                                const successMessage = document.createElement('div');
                                                successMessage.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
                                                successMessage.innerHTML = `
                                                    <div class="flex items-center">
                                                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                        </svg>
                                                        <span>Interest recorded! Use "Interested Items" filter to see your items.</span>
                                                    </div>
                                                `;
                                                document.body.appendChild(successMessage);
                                                
                                                // Remove the message after 5 seconds
                                                setTimeout(() => {
                                                    successMessage.remove();
                                                }, 5000);
                                            }
                                        }, 1500);
                                        
                                    } else {
                                        // Re-enable button on failure
                                        interestButton.disabled = false;
                                        interestButton.textContent = 'Show Interest';
                                        interestButton.className = 'w-full bg-green-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-green-600 transition-colors';
                                        
                                        confirmationMessage.textContent = 'Failed to record your interest. Please try again later.';
                                        confirmationMessage.className = 'block mt-4 text-center text-red-600';
                                    }
                                })
                                .catch((error) => {
                                    console.error('Error:', error);
                                    
                                    // Re-enable button on error
                                    interestButton.disabled = false;
                                    interestButton.textContent = 'Show Interest';
                                    interestButton.className = 'w-full bg-green-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-green-600 transition-colors';
                                    
                                    const confirmationMessage = document.getElementById('reusableConfirmationMessage');
                                    confirmationMessage.textContent = 'An error occurred. Please try again later.';
                                    confirmationMessage.className = 'block mt-4 text-center text-red-600';
                                });
                            }

                            function updateItemCardState(listingId, isInterested) {
                                // Find the item card in the grid and update its visual state
                                const itemCards = document.querySelectorAll('[onclick*="showReusableDetails"]');
                                itemCards.forEach(card => {
                                    const onclickAttr = card.getAttribute('onclick');
                                    if (onclickAttr && onclickAttr.includes(listingId)) {
                                        // Update the card to show interested state
                                        const statusBadge = card.querySelector('.absolute.top-3.right-3 span');
                                        if (statusBadge) {
                                            statusBadge.textContent = 'Interested';
                                            statusBadge.className = 'px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800';
                                        }
                                        
                                        // Add a visual indicator that user is interested
                                        const cardContent = card.querySelector('.p-4');
                                        if (cardContent && !cardContent.querySelector('.interested-indicator')) {
                                            const interestedIndicator = document.createElement('div');
                                            interestedIndicator.className = 'interested-indicator mt-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full text-center';
                                            interestedIndicator.textContent = '✓ You are interested';
                                            cardContent.appendChild(interestedIndicator);
                                        }
                                    }
                                });
                            }

                            // Add event listeners for live filtering
                            document.getElementById('search-input').addEventListener('input', () => {
                                document.getElementById('filterForm').submit();
                            });

                            document.getElementById('status-filter').addEventListener('change', () => {
                                document.getElementById('filterForm').submit();
                            });

                            // Close modal when clicking outside
                            document.getElementById('reusableModal').addEventListener('click', function(event) {
                                if (event.target === this) {
                                    closeReusableModal();
                                }
                            });
                        </script>
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

                // Function to get address from coordinates using reverse geocoding
                function getAddressFromCoordinates(lat, lng) {
                    // Show loading state
                    const addressField = document.getElementById("address");
                    addressField.value = "Detecting address...";
                    addressField.style.color = "#6B7280";
                    
                    // Simple approach with timeout
                    const timeout = setTimeout(() => {
                        addressField.value = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;
                        addressField.style.color = "#6B7280";
                    }, 5000); // 5 second timeout
                    
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16&addressdetails=1`)
                        .then(response => {
                            clearTimeout(timeout);
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            clearTimeout(timeout);
                            console.log('Address data received:', data); // Debug log
                            
                            if (data && data.display_name) {
                                // Use display_name as primary source
                                addressField.value = data.display_name;
                                addressField.style.color = "#374151";
                            } else if (data && data.address) {
                                // Try to build address from components
                                let detailedAddress = buildDetailedAddress(data.address);
                                if (detailedAddress && detailedAddress.trim() !== '') {
                                    addressField.value = detailedAddress;
                                    addressField.style.color = "#374151";
                                } else {
                                    addressField.value = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;
                                    addressField.style.color = "#6B7280";
                                }
                            } else {
                                addressField.value = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;
                                addressField.style.color = "#6B7280";
                            }
                        })
                        .catch(error => {
                            clearTimeout(timeout);
                            console.error('Error fetching address:', error);
                            addressField.value = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;
                            addressField.style.color = "#6B7280";
                        });
                }

                // Function to build a detailed address from address components
                function buildDetailedAddress(address) {
                    let parts = [];
                    
                    // Add house number and street if available
                    if (address.house_number && address.road) {
                        parts.push(`${address.house_number} ${address.road}`);
                    } else if (address.road) {
                        parts.push(address.road);
                    }
                    
                    // Add neighborhood or suburb
                    if (address.neighbourhood) {
                        parts.push(address.neighbourhood);
                    } else if (address.suburb) {
                        parts.push(address.suburb);
                    }
                    
                    // Add city or town
                    if (address.city) {
                        parts.push(address.city);
                    } else if (address.town) {
                        parts.push(address.town);
                    } else if (address.village) {
                        parts.push(address.village);
                    }
                    
                    // Add district or county
                    if (address.county) {
                        parts.push(address.county);
                    }
                    
                    // Add state or region
                    if (address.state) {
                        parts.push(address.state);
                    }
                    
                    // Add country
                    if (address.country) {
                        parts.push(address.country);
                    }
                    
                    // Join parts with commas and clean up
                    let detailedAddress = parts.join(', ');
                    
                    // Clean up extra spaces and commas
                    detailedAddress = detailedAddress.replace(/,\s*,/g, ',').replace(/\s+/g, ' ').trim();
                    
                    return detailedAddress;
                }

                // Manual address button functionality
                const manualAddressBtn = document.getElementById('manualAddressBtn');
                if (manualAddressBtn) {
                    manualAddressBtn.addEventListener('click', function() {
                        const addressField = document.getElementById('address');
                        const currentAddress = addressField.value;
                        
                        // If address field is empty or shows coordinates, clear it for manual entry
                        if (!currentAddress || currentAddress.includes('Lat:') || currentAddress.includes('Detecting')) {
                            addressField.value = '';
                            addressField.placeholder = 'Please enter the complete pickup address manually...';
                            addressField.focus();
                        } else {
                            // If there's already an address, just focus for editing
                            addressField.focus();
                        }
                    });
                }

                // Get My Location button functionality
                const getLocationBtn = document.getElementById('getLocationBtn');
                if (getLocationBtn) {
                    getLocationBtn.addEventListener('click', function() {
                        // Show loading state
                        getLocationBtn.innerHTML = `
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Getting Location...
                        `;
                        getLocationBtn.disabled = true;
                        
                        // Request user's current location
                        map.locate({ setView: true, maxZoom: 16, watch: false });
                    });
                }

                // Map expand functionality
                const expandBtn = document.getElementById('expandMapBtn');
                if (expandBtn) {
                    expandBtn.addEventListener('click', function() {
                        const mapDiv = document.getElementById('map');
                        const isExpanded = mapDiv.style.height === '500px';
                        
                        if (isExpanded) {
                            mapDiv.style.height = '300px';
                            mapDiv.style.position = 'relative';
                            mapDiv.style.zIndex = '1';
                            expandBtn.innerHTML = `
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 mr-1">
                                    <path fill-rule="evenodd" d="M3.75 3.75v4.5a.75.75 0 0 1-1.5 0v-4.5A2.25 2.25 0 0 1 4.5 1.5h4.5a.75.75 0 0 1 0 1.5H4.5a.75.75 0 0 0-.75.75ZM3.75 15.75v4.5a.75.75 0 0 0 .75.75h4.5a.75.75 0 0 0 0-1.5H4.5a.75.75 0 0 1-.75-.75v-4.5a.75.75 0 0 0-1.5 0ZM15.75 3.75h4.5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0V4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 1 0-1.5ZM15.75 15.75a.75.75 0 0 1 1.5 0v4.5a.75.75 0 0 1-.75.75h-4.5a.75.75 0 0 1 0-1.5h4.5a.75.75 0 0 0 .75-.75v-4.5Z" clip-rule="evenodd" />
                                </svg>
                                Expand Map
                            `;
                        } else {
                            mapDiv.style.height = '500px';
                            mapDiv.style.position = 'relative';
                            mapDiv.style.zIndex = '10';
                            expandBtn.innerHTML = `
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 mr-1">
                                    <path fill-rule="evenodd" d="M9 9a.75.75 0 0 0 0 1.5h6a.75.75 0 0 0 0-1.5H9Z" clip-rule="evenodd" />
                                </svg>
                                Collapse Map
                            `;
                        }
                        
                        // Trigger map resize after animation
                        setTimeout(() => {
                            map.invalidateSize();
                        }, 100);
                    });
                }

                // Get initial address
                getAddressFromCoordinates(defaultLat, defaultLng);

                // Function to handle successful geolocation
                function onLocationFound(e) {
                    const lat = e.latlng.lat;
                    const lng = e.latlng.lng;
                    map.setView(e.latlng, 16);
                    marker.setLatLng(e.latlng);
                    document.getElementById("latitude").value = lat;
                    document.getElementById("longitude").value = lng;
                    getAddressFromCoordinates(lat, lng);
                    
                    // Reset the Get My Location button
                    const getLocationBtn = document.getElementById('getLocationBtn');
                    if (getLocationBtn) {
                        getLocationBtn.innerHTML = `
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 mr-1">
                                <path fill-rule="evenodd" d="M8.161 2.58a1.875 1.875 0 0 1 1.678 0l4.993 2.498c.106.052.23.052.336 0l3.869-1.935A1.875 1.875 0 0 1 21.75 4.82v12.485c0 .71-.401 1.36-1.037 1.677l-4.875 2.437a1.875 1.875 0 0 1-1.676 0l-4.994-2.497a.375.375 0 0 0-.336 0l-3.868 1.935A1.875 1.875 0 0 1 2.25 19.18V6.695c0-.71.401-1.36 1.037-1.677l4.874-2.437ZM9 6.75a.75.75 0 0 0-.75.75v6c0 .414.336.75.75.75h6a.75.75 0 0 0 .75-.75v-6a.75.75 0 0 0-.75-.75H9Z" clip-rule="evenodd" />
                            </svg>
                            Get My Location
                        `;
                        getLocationBtn.disabled = false;
                    }
                }

                // Function to handle geolocation error
                function onLocationError(e) {
                    console.error("Geolocation failed:", e.message);
                    
                    // Reset the Get My Location button
                    const getLocationBtn = document.getElementById('getLocationBtn');
                    if (getLocationBtn) {
                        getLocationBtn.innerHTML = `
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 mr-1">
                                <path fill-rule="evenodd" d="M8.161 2.58a1.875 1.875 0 0 1 1.678 0l4.993 2.498c.106.052.23.052.336 0l3.869-1.935A1.875 1.875 0 0 1 21.75 4.82v12.485c0 .71-.401 1.36-1.037 1.677l-4.875 2.437a1.875 1.875 0 0 1-1.676 0l-4.994-2.497a.375.375 0 0 0-.336 0l-3.868 1.935A1.875 1.875 0 0 1 2.25 19.18V6.695c0-.71.401-1.36 1.037-1.677l4.874-2.437ZM9 6.75a.75.75 0 0 0-.75.75v6c0 .414.336.75.75.75h6a.75.75 0 0 0 .75-.75v-6a.75.75 0 0 0-.75-.75H9Z" clip-rule="evenodd" />
                            </svg>
                            Get My Location
                        `;
                        getLocationBtn.disabled = false;
                    }
                    
                    // Show user-friendly error message
                    const addressField = document.getElementById("address");
                    addressField.value = "Unable to detect location. Please select manually on the map.";
                    addressField.style.color = "#EF4444";
                }

                // Request user's current location on page load
                map.on('locationfound', onLocationFound);
                map.on('locationerror', onLocationError);
                map.locate({ setView: true, maxZoom: 16, watch: false });

                // Update coordinates and address on drag
                marker.on('dragend', function (e) {
                    const pos = marker.getLatLng();
                    document.getElementById("latitude").value = pos.lat;
                    document.getElementById("longitude").value = pos.lng;
                    getAddressFromCoordinates(pos.lat, pos.lng);
                });
            }
        });
    </script>
    
    <?php include 'include/footer.php'; ?>
</body>
</html>
<?php
if (isset($con)) {
    $con->close();
}
?>