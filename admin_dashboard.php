<?php
// Start the session to access session variables.
session_start();

// Check if the user is an admin. If not, set a flag and redirect later.
$is_admin = isset($_SESSION['user']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// Include the database connection file.
// The include_once must be at the very top before any HTML output.
include_once 'connect.php';

// Check if the mysqli connection object is set.
if (!isset($con) || $con->connect_error) {
    die("Error: The database connection failed. Please ensure 'connect.php' is in the same directory and the credentials are correct.");
}

// Handle AJAX requests first, before any HTML output
if (isset($_GET['action']) && $_GET['action'] === 'get_interests') {
    header('Content-Type: application/json');
    
    // Check for the listing_id parameter
    if (!isset($_GET['listing_id']) || empty($_GET['listing_id'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Listing ID not provided.'
        ]);
        exit;
    }

    $listingId = intval($_GET['listing_id']);

    if (isset($con) && $con->ping()) {
        // First check if the tables exist
        $checkTables = $con->query("SHOW TABLES LIKE 'user_interests'");
        if ($checkTables->num_rows == 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'user_interests table does not exist.'
            ]);
            exit;
        }
        
        $checkUsers = $con->query("SHOW TABLES LIKE 'users'");
        if ($checkUsers->num_rows == 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'users table does not exist.'
            ]);
            exit;
        }

        // SQL query to join the tables and get the necessary information, ordered by timestamp
        $sql = "
            SELECT 
                u.name, 
                u.email,
                u.phone,
                ui.timestamp
            FROM 
                user_interests ui
            JOIN 
                users u ON ui.user_id = u.id
            WHERE 
                ui.listing_id = ?
            ORDER BY 
                ui.timestamp ASC
        ";

        $stmt = $con->prepare($sql);
        
        if (!$stmt) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to prepare statement: ' . $con->error
            ]);
            exit;
        }

        $stmt->bind_param("i", $listingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $interests = [];
        while ($row = $result->fetch_assoc()) {
            $interests[] = $row;
        }

        echo json_encode([
            'status' => 'success',
            'data' => $interests
        ]);

        $stmt->close();
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to connect to the database.'
        ]);
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    header('Content-Type: application/json');
    
    // Get statistics
    if (isset($con) && $con->ping()) {
        $stats = [];
        
        // Total listings
        $result = $con->query("SELECT COUNT(*) as count FROM reusable_waste_listings");
        $stats['totalListings'] = $result->fetch_assoc()['count'];
        
        // Total interests
        $result = $con->query("SELECT COUNT(*) as count FROM user_interests");
        $stats['totalInterests'] = $result->fetch_assoc()['count'];
        
        // Available listings
        $result = $con->query("SELECT COUNT(*) as count FROM reusable_waste_listings WHERE status = 'available'");
        $stats['availableListings'] = $result->fetch_assoc()['count'];
        
        // Pending listings
        $result = $con->query("SELECT COUNT(*) as count FROM reusable_waste_listings WHERE status = 'pending'");
        $stats['pendingListings'] = $result->fetch_assoc()['count'];
        
        echo json_encode([
            'status' => 'success',
            'data' => $stats
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to connect to the database.'
        ]);
    }
    exit;
}

// Simple PHP-based "router" to determine the current page.
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$message = ''; // Initialize a message variable for user feedback.

// --- All PHP Logic and Redirections Go Here, Before Any HTML or ECHO ---

if (!$is_admin) {
    // If not an admin, we show a page with a login link.
    // This part is placed here to handle access denial immediately without echoing headers.
} else {
    // Handling actions for different pages
    switch ($currentPage) {
        case 'users':
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
                    if (isset($stmt)) $stmt->close();
                }
            }
            break;

        case 'waste_rates':
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
                        if (isset($stmt)) $stmt->close();
                    }
                }
            }
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
            break;

        case 'new_pickups':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_collector'])) {
                $request_id = intval($_POST['request_id']);
                $collector_id = intval($_POST['collector_id']);
                if ($collector_id > 0) {
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
            break;

        case 'verify_pickups':
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pickup_details'])) {
                $request_id = intval($_POST['request_id']);
                $new_final_weight = floatval($_POST['final_weight']);
                $new_2rstatus = $_POST['2rstatus'];
                $new_status = 'Completed';
                $rateResult = $con->query("SELECT wr.rate_per_kg FROM pickup_requests pr JOIN waste_rates wr ON pr.waste_type = wr.waste_type WHERE pr.id = $request_id");
                $total_cost = 0;
                if ($rateResult && $rateRow = $rateResult->fetch_assoc()) {
                    $rate_per_kg = $rateRow['rate_per_kg'];
                    $total_cost = $new_final_weight * $rate_per_kg;
                }
                $stmt = $con->prepare("UPDATE pickup_requests SET final_weight = ?, total_cost = ?, status = ?, 2rstatus = ? WHERE id = ?");
                $stmt->bind_param("ddssi", $new_final_weight, $total_cost, $new_status, $new_2rstatus, $request_id);
                if ($stmt->execute()) {
                    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Success:</p><p class="text-sm">Pickup details updated successfully!</p></div>';
                } else {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Error:</p><p class="text-sm">Failed to update pickup details. Please try again. ' . $con->error . '</p></div>';
                }
                $stmt->close();
            }
            break;

        case 'refused_pickups':
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
            break;

        case 'reusable_waste':
            $uploadDir = "uploads/reusable/";
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_listing'])) {
                $listing_id = (int)($_POST['listing_id'] ?? 0);
                $new_status = $_POST['status'] ?? 'available';
                if ($listing_id > 0) {
                    $stmt = $con->prepare("UPDATE reusable_waste_listings SET status = ? WHERE listing_id = ?");
                    $stmt->bind_param("si", $new_status, $listing_id);
                    $stmt->execute();
                    $stmt->close();
                }
                header("Location: ?page=reusable_waste");
                exit();
            }
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['list_reusable_waste'])) {
                $title = $_POST['title'] ?? '';
                $description = $_POST['description'] ?? '';
                $quantity = (int)($_POST['quantity'] ?? 0);
                $price = (float)($_POST['price'] ?? 0);
                $status = "available";
                $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
                if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $fileName = time() . "_" . basename($_FILES["image"]["name"]);
                    $targetFilePath = $uploadDir . $fileName;
                    if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                        $sql = "INSERT INTO reusable_waste_listings (title, description, quantity, price, image, status, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $con->prepare($sql);
                        $stmt->bind_param("ssdissi", $title, $description, $quantity, $price, $targetFilePath, $status, $user_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                header("Location: ?page=reusable_waste");
                exit();
            }
            if (isset($_GET['delete_id'])) {
                $delete_id = (int)($_GET['delete_id'] ?? 0);
                if ($delete_id > 0) {
                    $stmt = $con->prepare("SELECT image FROM reusable_waste_listings WHERE listing_id = ?");
                    $stmt->bind_param("i", $delete_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        if (file_exists($row['image'])) {
                            unlink($row['image']);
                        }
                    }
                    $stmt->close();
                    $stmt = $con->prepare("DELETE FROM reusable_waste_listings WHERE listing_id = ?");
                    $stmt->bind_param("i", $delete_id);
                    $stmt->execute();
                    $stmt->close();
                }
                header("Location: ?page=reusable_waste");
                exit();
            }
            break;
    }
}

// --- Now the HTML Starts ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Custom modal overlay */
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
        }
    </style>
</head><?php include 'include/header.php'; ?>
<body class="bg-gray-100 font-sans">
    <?php if (!$is_admin): ?>
        <div class="bg-gray-100 min-h-screen flex items-center justify-center">
            <div class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-md text-center">
                <h2 class="text-3xl font-bold text-red-600 mb-4">Access Denied</h2>
                <p class="text-gray-600 mb-6">You must be logged in as an administrator to view this page.</p>
                <a href="login.php" class="bg-indigo-600 text-white py-2 px-4 rounded-xl hover:bg-indigo-700 transition-colors">Go to Login</a>
            </div>
        </div>
    <?php else: ?>
        <div class="min-h-screen flex">
            <div class="bg-gray-800 text-white w-64 p-4 shadow-lg rounded-r-2xl hidden md:block">
                <div class="flex flex-col items-center mb-6 mt-2">
                    <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center font-bold text-xl mb-2">
                        R
                    </div>
                    <h1 class="text-xl font-bold">Recycle Admin</h1>
                </div>
                <nav>
                    <?php
                        $navItems = [
                            'dashboard' => ['name' => 'Dashboard Overview', 'icon' => 'M12 6.5l-10 6 10 6 10-6-10-6zM12 2l10 6-10 6-10-6L12 2zM2 18l10 6 10-6'],
                            'users' => ['name' => 'User Management', 'icon' => 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'],
                            'waste_rates' => ['name' => 'Waste Rates', 'icon' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zM11 7h2v6h-2z'],
                            'new_pickups' => ['name' => 'New Pickup Requests', 'icon' => 'M14 10h-2v4h2v-4zm-2 6h-2v2h2v-2zm4-6h-2v4h2v-4zm-4-4h2v2h-2V6z'],
                            'verify_pickups' => ['name' => 'Verify Pickups', 'icon' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z'],
                            'refused_pickups' => ['name' => 'Refused Pickups', 'icon' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 13.59L15.59 15 12 11.41 8.41 15 7 13.59 10.59 10 7 6.41 8.41 5 12 8.59 15.59 5 17 6.41 13.41 10 17 13.59z'],
                            'reusable_waste' => ['name' => 'List Reusable Waste', 'icon' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15.5h2V12h-2v5.5zm1-10C10.79 7.5 9.75 8.54 9.75 9.75S10.79 12 12 12s2.25-1.04 2.25-2.25S13.21 7.5 12 7.5z'],
                            'user_interests' => ['name' => 'User Interests', 'icon' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z'],
                            'reports' => ['name' => 'Reports', 'icon' => 'M22 6h-4V4c0-1.1-.9-2-2-2h-4c-1.1 0-2 .9-2 2v2H2c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h20c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6-2h-4v2h4V4zm6 16H2V8h20v12zM9 10h2v8H9v-8zm4 0h2v8h-2v-8zm4 0h2v8h-2v-8z']
                        ];
                    ?>
                    <ul>
                        <?php foreach ($navItems as $key => $item): ?>
                            <li class="mb-2">
                                <a href="?page=<?php echo $key; ?>" class="flex items-center w-full py-3 px-4 rounded-xl text-left transition-colors <?php echo ($currentPage === $key) ? 'bg-green-600 font-semibold' : 'hover:bg-gray-700'; ?>">
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

            <div class="flex-1 p-6 overflow-y-auto">
                <?php echo $message; ?>
                <?php
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
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate (NPR/kg)</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($rates as $rate): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 capitalize"><?php echo htmlspecialchars($rate['waste_type']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">NPR <?php echo number_format($rate['rate_per_kg'], 2); ?></td>
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
                    $usersResult = $con->query("SELECT id, name, email, role FROM users");
                    $users = ($usersResult) ? $usersResult->fetch_all(MYSQLI_ASSOC) : [];
                    ?>
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-4xl font-bold text-gray-800">User Management</h2>
                    </div>
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
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($user['email']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900 capitalize"><?= htmlspecialchars($user['role']); ?></td>
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
                    $wasteData = [];
                    $result = $con->query("SELECT * FROM waste_rates ORDER BY updated_at DESC");
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $wasteData[] = $row;
                        }
                    }
                    ?>
                    <h2 class="text-4xl font-bold text-gray-800 mb-8">Waste Rates Management</h2>
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
                                                <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Rate (NPR/kg)</th>
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
                                    <label for="rate_per_kg" class="block text-lg font-medium text-gray-700">Rate per KG (NPR)</label>
                                    <input type="number" step="0.01" id="rate_per_kg" name="rate_per_kg" required class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-lg">
                                </div>
                                <div>
                                    <button type="submit" class="w-full bg-green-600 text-white py-3 px-4 rounded-xl text-lg font-semibold hover:bg-green-700 transition-colors shadow-lg">Add Waste Rate</button>
                                </div>
                            </form>
                        </div>
                    </div>
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
                    $pendingRequests = [];
                    $query = "SELECT pr.*, u.name AS requester_name, u.phone AS requester_phone FROM pickup_requests pr JOIN users u ON pr.user_id = u.id WHERE pr.status = 'Pending' ORDER BY pr.pickup_date ASC";
                    $result = $con->query($query);
                    if ($result) {
                        $pendingRequests = $result->fetch_all(MYSQLI_ASSOC);
                    }
                    $collectorsResult = $con->query("SELECT id, name FROM users WHERE role = 'collector'");
                    $collectorOptions = [];
                    if ($collectorsResult) {
                        while ($col = $collectorsResult->fetch_assoc()) {
                            $collectorOptions[$col['id']] = $col['name'];
                        }
                    }
                    ?>
                    <h2 class="text-4xl font-bold text-gray-800 mb-8">New Pickup Requests (Pending)</h2>
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
                                                <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($request['weight'] ?? $request['final_weight'] ?? 'N/A'); ?></td>
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
                                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">No new pickup requests found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php
                    break;
                case 'verify_pickups':
                    $pickupsToVerify = [];
                    $query = "
                        SELECT pr.*, u.name AS requester_name, u.phone AS requester_phone, c.name AS collector_name
                        FROM pickup_requests pr
                        JOIN users u ON pr.user_id = u.id
                        LEFT JOIN users c ON pr.assigned_collector_id = c.id
                        WHERE pr.status = 'Collected'
                        ORDER BY pr.pickup_date ASC
                    ";
                    $result = $con->query($query);
                    if ($result) {
                        $pickupsToVerify = $result->fetch_all(MYSQLI_ASSOC);
                    }
                    ?>
                    <h2 class="text-4xl font-bold text-gray-800 mb-8">Verify Pickups</h2>
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">#</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Requester</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Collector</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Final Weight (kg)</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Total Cost (NPR)</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">2R Status</th>
                                        <th class="px-6 py-3 text-left text-base font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($pickupsToVerify)): ?>
                                        <?php $count = 1; ?>
                                        <?php foreach ($pickupsToVerify as $pickup): ?>
                                            <tr>
                                                <form method="post" action="?page=verify_pickups">
                                                    <input type="hidden" name="request_id" value="<?= $pickup['id']; ?>">
                                                    <input type="hidden" name="update_pickup_details" value="1">
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= $count++; ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($pickup['requester_name']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($pickup['requester_phone']); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900"><?= htmlspecialchars($pickup['collector_name'] ?? 'N/A'); ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg">
                                                        <input type="number" step="0.01" name="final_weight" value="<?= htmlspecialchars($pickup['final_weight']); ?>" class="w-24 rounded-xl border-gray-300 shadow-sm text-sm" required>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg text-gray-900">
                                                        NPR <?= number_format($pickup['total_cost'], 2); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg">
                                                        <select name="2rstatus" class="rounded-xl border-gray-300 text-sm">
                                                            <option value="Reuse" <?= ($pickup['2Rstatus'] == 'Reuse' ? 'selected' : ''); ?>>Reuse</option>
                                                            <option value="Recycle" <?= ($pickup['2Rstatus'] == 'Recycle' ? 'selected' : ''); ?>>Recycle</option>
                                                        </select>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-lg">
                                                        <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-xl text-sm hover:bg-blue-700 transition-colors shadow-md">Update</button>
                                                    </td>
                                                </form>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">No pickups to verify.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php
                    break;
                case 'refused_pickups':
                    $refusedPickups = [];
                    $query = "
                        SELECT pr.*, u.name AS requester_name, u.phone AS requester_phone, c.name AS collector_name, c.phone AS collector_phone
                        FROM pickup_requests pr
                        JOIN users u ON pr.user_id = u.id
                        LEFT JOIN users c ON pr.assigned_collector_id = c.id
                        WHERE pr.status = 'Refused'
                        ORDER BY pr.created_at DESC
                    ";
                    $result = $con->query($query);
                    if ($result) {
                        $refusedPickups = $result->fetch_all(MYSQLI_ASSOC);
                    } else {
                        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4" role="alert"><p class="font-bold">Database Error:</p><p class="text-sm">Failed to retrieve refused pickups. Please check the query and table structure. MySQL Error: ' . htmlspecialchars($con->error) . '</p></div>';
                    }
                    $collectorsResult = $con->query("SELECT id, name FROM users WHERE role = 'collector'");
                    $collectorOptions = [];
                    if ($collectorsResult) {
                        while ($col = $collectorsResult->fetch_assoc()) {
                            $collectorOptions[$col['id']] = $col['name'];
                        }
                    }
                    ?>
                    <h2 class="text-4xl font-bold text-gray-800 mb-8">Refused Pickups</h2>
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
                case 'reusable_waste':
            // The Reusable Waste section handles adding, updating, and deleting listings.
            // It also displays all current listings for the admin to manage.
            
            // Handle Add New Listing
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_listing'])) {
                $title = $_POST['title'] ?? '';
                $description = $_POST['description'] ?? '';
                $quantity = (int)($_POST['quantity'] ?? 0);
                $price = (float)($_POST['price'] ?? 0);
                $status = "available";
                $user_id = $_SESSION['user_id'] ?? 1; // Use the actual session user ID

                if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
                    $targetDir = "uploads/reusable/";
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0777, true);
                    }

                    $fileName = time() . "_" . basename($_FILES["image"]["name"]);
                    $targetFilePath = $targetDir . $fileName;

                    if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                        $stmt = $con->prepare("INSERT INTO reusable_waste_listings (title, description, quantity, price, image, status, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssdssi", $title, $description, $quantity, $price, $targetFilePath, $status, $user_id);
                        
                        if ($stmt->execute()) {
                            $message = "✅ Listing added successfully!";
                        } else {
                            $message = "❌ Database insertion failed: " . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $message = "❌ File upload failed.";
                    }
                } else {
                    $message = "❌ No image uploaded or file error.";
                }
            }

            // Handle Delete Listing
            if (isset($_GET['delete'])) {
                $id = (int)$_GET['delete'];
                
                $stmt = $con->prepare("SELECT image FROM reusable_waste_listings WHERE listing_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    if (file_exists($row['image'])) {
                        unlink($row['image']); // Delete the image file
                    }
                }
                $stmt->close();
                
                $stmt = $con->prepare("DELETE FROM reusable_waste_listings WHERE listing_id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $message = "🗑️ Listing deleted successfully.";
                } else {
                    $message = "❌ Deletion failed.";
                }
                $stmt->close();
            }
            
            // Handle Update Listing Status
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
                $listing_id = (int)$_POST['listing_id'];
                $status_value = $_POST['status_value'];
                
                $stmt = $con->prepare("UPDATE reusable_waste_listings SET status = ? WHERE listing_id = ?");
                $stmt->bind_param("si", $status_value, $listing_id);
                
                if ($stmt->execute()) {
                    $message = "✏️ Listing status updated successfully.";
                } else {
                    $message = "❌ Status update failed.";
                }
                $stmt->close();
            }

            // Fetch all listings for display
            $result = $con->query("SELECT * FROM reusable_waste_listings ORDER BY listing_id DESC");
            ?>
            <h2 class="text-4xl font-bold text-gray-800 mb-8">Manage Reusable Waste</h2>
            
            <!-- Current Listings Section -->
            <div class="mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-semibold text-gray-800">Current Listings</h3>
                    <div class="text-sm text-gray-500">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?= $result->num_rows ?> listing<?= $result->num_rows !== 1 ? 's' : '' ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($result && $result->num_rows > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                        <!-- Image Section -->
                        <div class="relative h-48 overflow-hidden">
                            <?php if (!empty($row['image']) && file_exists($row['image'])): ?>
                                <img src="<?= htmlspecialchars($row['image']) ?>" 
                                     alt="<?= htmlspecialchars($row['title']) ?>" 
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                    <i class="fas fa-image text-4xl text-gray-400"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Status Badge -->
                            <div class="absolute top-3 right-3">
                                <?php
                                $statusClass = '';
                                $statusText = '';
                                switch($row['status']) {
                                    case 'available':
                                        $statusClass = 'bg-green-500 text-white';
                                        $statusText = 'Available';
                                        break;
                                    case 'sold':
                                        $statusClass = 'bg-gray-500 text-white';
                                        $statusText = 'Sold';
                                        break;
                                    case 'Reserved':
                                        $statusClass = 'bg-yellow-500 text-white';
                                        $statusText = 'Reserved';
                                        break;
                                    default:
                                        $statusClass = 'bg-gray-400 text-white';
                                        $statusText = ucfirst($row['status']);
                                }
                                ?>
                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $statusClass ?>">
                                    <?= $statusText ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Content Section -->
                        <div class="p-6">
                            <h4 class="text-lg font-semibold text-gray-800 mb-2 line-clamp-2">
                                <?= htmlspecialchars($row['title']) ?>
                            </h4>
                            
                            <div class="space-y-2 mb-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-500">Quantity:</span>
                                    <span class="text-sm font-medium text-gray-800"><?= $row['quantity'] ?> pcs</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-500">Price:</span>
                                    <span class="text-lg font-bold text-green-600">NPR <?= number_format($row['price'], 2) ?></span>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex gap-2">
                                <form action="admin_dashboard.php?page=reusable_waste" method="post" class="flex-1">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="listing_id" value="<?= $row['listing_id'] ?>">
                                    <select name="status_value" onchange="this.form.submit()" 
                                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="available" <?= ($row['status'] == 'available') ? 'selected' : ''; ?>>Available</option>
                                        <option value="sold" <?= ($row['status'] == 'sold') ? 'selected' : ''; ?>>Sold</option>
                                        <option value="Reserved" <?= ($row['status'] == 'Reserved') ? 'selected' : ''; ?>>Reserved</option>
                                    </select>
                                </form>
                                
                                <button onclick="confirmDelete(<?= $row['listing_id'] ?>)" 
                                        class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors text-sm font-medium">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-box-open text-3xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-500 mb-2">No listings found</h3>
                    <p class="text-gray-400">Start by adding your first reusable waste listing below.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Add New Listing Section -->
            <div class="bg-white rounded-2xl shadow-lg p-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-plus text-green-600"></i>
                    </div>
                    <h3 class="text-2xl font-semibold text-gray-800">Add New Listing</h3>
                </div>
                
                <form action="admin_dashboard.php?page=reusable_waste" method="post" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="add_listing" value="1">
                    
                    <!-- Title and Description Row -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tag mr-2 text-gray-400"></i>Title
                            </label>
                            <input type="text" id="title" name="title" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                   placeholder="Enter listing title">
                        </div>
                        
                        <div>
                            <label for="quantity" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-cubes mr-2 text-gray-400"></i>Quantity (pcs)
                            </label>
                            <input type="number" id="quantity" name="quantity" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                   placeholder="Enter quantity">
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-align-left mr-2 text-gray-400"></i>Description
                        </label>
                        <textarea id="description" name="description" rows="4" required 
                                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors resize-none"
                                  placeholder="Describe the item in detail"></textarea>
                    </div>
                    
                    <!-- Price and Image Row -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-rupee-sign mr-2 text-gray-400"></i>Price (NPR)
                            </label>
                            <input type="number" step="0.01" id="price" name="price" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                   placeholder="0.00">
                        </div>
                        
                        <div>
                            <label for="image" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-image mr-2 text-gray-400"></i>Upload Image
                            </label>
                            <div class="relative">
                                <input type="file" id="image" name="image" accept="image/*" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="pt-4">
                        <button type="submit" 
                                class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold py-4 px-6 rounded-xl hover:from-green-600 hover:to-green-700 focus:ring-4 focus:ring-green-200 transition-all duration-200 transform hover:scale-105 shadow-lg">
                            <i class="fas fa-save mr-2"></i>Save Listing
                        </button>
                    </div>
                </form>
            </div>
            </div>
            
            <script>
                function confirmDelete(listingId) {
                    if (confirm('Are you sure you want to delete this listing? This action cannot be undone.')) {
                        window.location.href = 'admin_dashboard.php?page=reusable_waste&delete=' + listingId;
                    }
                }
            </script>
            
            <style>
                .line-clamp-2 {
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }
                
                .hover\:scale-105:hover {
                    transform: scale(1.05);
                }
                
                .transform {
                    transform: translateZ(0);
                }
                
                .transition-all {
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }
                
                .duration-300 {
                    transition-duration: 300ms;
                }
                
                .duration-200 {
                    transition-duration: 200ms;
                }
                
                .hover\:-translate-y-1:hover {
                    transform: translateY(-0.25rem);
                }
                
                .hover\:shadow-xl:hover {
                    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                }
                
                .shadow-lg {
                    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                }
                
                .rounded-2xl {
                    border-radius: 1rem;
                }
                
                .rounded-xl {
                    border-radius: 0.75rem;
                }
                
                .rounded-lg {
                    border-radius: 0.5rem;
                }
                
                .focus\:ring-2:focus {
                    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
                }
                
                .focus\:ring-green-500:focus {
                    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.5);
                }
                
                .focus\:ring-4:focus {
                    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
                }
                
                .focus\:ring-green-200:focus {
                    box-shadow: 0 0 0 4px rgba(187, 247, 208, 0.5);
                }
                
                .focus\:border-green-500:focus {
                    border-color: #10b981;
                }
                
                .file\:mr-4::file-selector-button {
                    margin-right: 1rem;
                }
                
                .file\:py-2::file-selector-button {
                    padding-top: 0.5rem;
                    padding-bottom: 0.5rem;
                }
                
                .file\:px-4::file-selector-button {
                    padding-left: 1rem;
                    padding-right: 1rem;
                }
                
                .file\:rounded-lg::file-selector-button {
                    border-radius: 0.5rem;
                }
                
                .file\:border-0::file-selector-button {
                    border: 0;
                }
                
                .file\:text-sm::file-selector-button {
                    font-size: 0.875rem;
                }
                
                .file\:font-semibold::file-selector-button {
                    font-weight: 600;
                }
                
                .file\:bg-green-50::file-selector-button {
                    background-color: #f0fdf4;
                }
                
                .file\:text-green-700::file-selector-button {
                    color: #15803d;
                }
                
                .hover\:file\:bg-green-100:hover::file-selector-button {
                    background-color: #dcfce7;
                }
            </style>
            <?php
            break;
        case 'user_interests':
            // Include the user interests component
            include 'user_interests_component.php';
            break;
                case 'reports':
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
            ?>
        </div>
        </div>
    <?php endif; ?>
</body>
<?php include 'include/footer.php'; ?>
</html>
<?php
// Close the database connection at the end of the script
if (isset($con)) {
    $con->close();
}
?>