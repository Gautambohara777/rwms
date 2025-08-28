<!--Algorith used( havershine line 493-497, greedy algorithm 500-506) -->
<?php
// newupdate.php
session_start();
include "connect.php"; // Database connection

// Auth: collector only
if (!isset($_SESSION['user']) || ($_SESSION['user_role'] ?? '') !== 'collector') {
    header("Location: login.php");
    exit();
}

// Determine DB connection variable
$db = isset($con) && $con instanceof mysqli ? $con : (isset($conn) && $conn instanceof mysqli ? $conn : null);
if (!$db) {
    die("Database connection not found. Check connect.php.");
}

$collector_id = (int) $_SESSION['user'];
$message = "";

// Get the current view from the URL, default to dashboard
$view = $_GET['view'] ?? 'dashboard';

// Helper function to check if a column exists
function col_exists($db, $table, $col) {
    $q = "SHOW COLUMNS FROM `$table` LIKE '" . mysqli_real_escape_string($db, $col) . "'";
    $r = mysqli_query($db, $q);
    return ($r && mysqli_num_rows($r) > 0);
}

// Find appropriate column names to ensure compatibility with different database schemas
$assignee_col = col_exists($db, 'pickup_requests', 'assigned_collector_id') ? 'assigned_collector_id' : 'assigned_to';
$address_col = col_exists($db, 'pickup_requests', 'address') ? 'address' : 'location';
$weight_col = col_exists($db, 'pickup_requests', 'weight') ? 'weight' : 'quantity';
$pickup_date_col = col_exists($db, 'pickup_requests', 'pickup_date') ? 'pickup_date' : 'scheduled_date';

// Handle POST requests to update pickup status
if ($view === 'assigned' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_selected'])) {
    $selected = $_POST['selected_ids'] ?? [];
    $ids = array_map('intval', $selected);
    $ids = array_filter($ids, function($v){ return $v > 0; });
    if (count($ids) > 0) {
        $id_list = implode(',', $ids);
        $update_sql = "UPDATE `pickup_requests` SET `status` = 'In Progress' WHERE id IN ($id_list) AND `$assignee_col` = ? AND (LOWER(COALESCE(status,'')) NOT IN ('completed','cancelled','collected','in progress','refused'))";
        $stmt = mysqli_prepare($db, $update_sql);
        mysqli_stmt_bind_param($stmt, "i", $collector_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Selected pickups marked as 'In Progress'.";
        } else {
            $message = "Database error: " . mysqli_error($db);
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = "No pickups selected to start.";
    }
}

// Handle POST requests for in-progress pickups (MOVE BACK or REFUSED)
if ($view === 'inprogress' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_ids'])) {
    $selected = $_POST['selected_ids'] ?? [];
    $ids = array_map('intval', $selected);
    $ids = array_filter($ids, function($v){ return $v > 0; });
    
    if (count($ids) === 0) {
        $message = "No pickups selected.";
    } else {
        $id_list = implode(',', $ids);
        
        if (isset($_POST['move_back_selected'])) {
            $update_sql = "UPDATE `pickup_requests` 
                           SET `status` = 'Approved' 
                           WHERE id IN ($id_list) AND `$assignee_col` = ?";
            $stmt = $db->prepare($update_sql);
            $stmt->bind_param("i", $collector_id);
            if ($stmt->execute()) {
                $message = "Selected pickups moved back to 'Assigned'.";
            } else {
                $message = "Database error: " . $stmt->error;
            }
            $stmt->close();
        } elseif (isset($_POST['refuse_selected'])) {
            $update_sql = "UPDATE `pickup_requests` 
                           SET `status` = 'Refused' 
                           WHERE id IN ($id_list) AND `$assignee_col` = ?";
            $stmt = $db->prepare($update_sql);
            $stmt->bind_param("i", $collector_id);
            if ($stmt->execute()) {
                $message = "Selected pickups marked as 'Refused'.";
            } else {
                $message = "Database error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Handle POST request for waste collection update from the Live Route form
if ($view === 'live-route' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_collected_waste'])) {
    $id = (int)($_POST['pickup_id'] ?? 0);
    $collected_items = $_POST['collected_items'];
    $final_weight = (float)$_POST['final_weight'];
    $rate = (float)$_POST['rate'];
    $total_cost = $final_weight * $rate;

    $sql = "UPDATE pickup_requests SET collected_items=?, final_weight=?, rate=?, total_cost=?, status='Collected' WHERE id=? AND `$assignee_col` = ?";
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, "sddiii", $collected_items, $final_weight, $rate, $total_cost, $id, $collector_id);
    if (mysqli_stmt_execute($stmt)) {
        $message = "Pickup #$id has been updated and marked as 'Collected'.";
    } else {
        $message = "Error updating pickup: " . mysqli_error($db);
    }
    mysqli_stmt_close($stmt);
}

// --- Fetch Data for Dashboard Counts
$counts = [ 'assigned' => 0, 'inprogress' => 0, 'awaiting' => 0, 'completed' => 0, 'refused' => 0, 'total_collected_weight' => 0 ];
$queries = [
    'assigned' => "AND LOWER(status) NOT IN ('complete', 'in progress', 'collected', 'refused')",
    'inprogress' => "AND LOWER(status) LIKE 'in progress%'",
    'awaiting' => "AND LOWER(status) = 'collected'",
    'completed' => "AND LOWER(status) LIKE 'complete%'",
    'refused' => "AND LOWER(status) = 'refused'"
];

foreach ($queries as $key => $val) {
    $sql = "SELECT COUNT(*) FROM pickup_requests WHERE assigned_collector_id = ? " . $val;
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "i", $collector_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    $counts[$key] = (int) ($count ?? 0);
    mysqli_stmt_close($stmt);
}

// New query to get total collected weight
$weight_sql = "SELECT SUM(final_weight) FROM pickup_requests WHERE assigned_collector_id = ? AND (LOWER(status) = 'collected' OR LOWER(status) = 'completed')";
$stmt = mysqli_prepare($con, $weight_sql);
mysqli_stmt_bind_param($stmt, "i", $collector_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $total_weight);
mysqli_stmt_fetch($stmt);
$counts['total_collected_weight'] = (float) ($total_weight ?? 0);
mysqli_stmt_close($stmt);


// --- Fetch Waste Rates
$rates = [];
$result = mysqli_query($con, "SELECT waste_type, rate_per_kg, updated_at FROM waste_rates ORDER BY waste_type ASC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $rates[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Collector Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.css" />
    <style>
        :root {
            --primary-color: #2e7d32;
            --secondary-color: #4CAF50;
            --accent-color: #a8e063;
            --refuse-color: #d9534f;
            --text-color-light: #ffffff;
            --text-color-dark: #333;
            --font-family-poppins: 'Poppins', sans-serif;
            --sidebar-width: 250px;
        }

        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: var(--font-family-poppins);
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
            height: 100vh;
            color: var(--text-color-dark);
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background-color: #2c3e50;
            color: white;
            display: flex;
            flex-direction: column;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            transition: transform 0.3s ease-in-out;
            position: fixed;
            height: 100%;
            left: 0;
            top: 80px;
        }

        .sidebar h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.5rem;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }

        .sidebar li {
            margin-bottom: 10px;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            font-size: 1.1rem;
            padding: 15px;
            display: block;
            border-radius: 8px;
            transition: background-color 0.2s, color 0.2s;
            display: flex;
            align-items: center;
        }

        .sidebar a i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
        }
        
        .sidebar a:hover,
        .sidebar a.active {
            background-color: #34495e;
            color: var(--accent-color);
        }
        
        .logout-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1rem;
            padding: 15px;
            width: 100%;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: background-color 0.2s;
            border-radius: 8px;
        }

        .logout-btn:hover {
            background-color: #e74c3c;
        }
        
        /* Page Content */
        .dashboard-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            margin-top: 30px;
            padding: 20px;
        }
        
        .main-content {
            display: flex;
            flex: 1;
            gap: 20px;
            flex-wrap: wrap;
        }

        .content-body {
            flex: 2;
            display: flex;
            flex-direction: column;
            gap: 20px;
            min-width: 500px;
        }
        
        .dashboard-body {
            flex: 2;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .counts {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .card {
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
            text-align: center;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: transform 0.2s, box-shadow 0.2s;
            min-height: 150px;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }

        /* Vibrant Colors for Cards */
        .card-assigned { background-color: #3498db; }
        .card-inprogress { background-color: #e67e22; }
        .card-awaiting { background-color: #9b59b6; }
        .card-completed { background-color: #2ecc71; }
        .card-refused { background-color: #e74c3c; }
        .card-total { background-color: #f39c12; }
        
        .card h3 {
            margin-bottom: 10px;
            font-size: 1.25rem;
            color: white;
        }
        
        .card p {
            font-size: 3rem;
            font-weight: 700;
            color: white;
            margin: 0;
        }
        
        .start-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 20px;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            text-align: center;
            transition: background 0.3s, transform 0.2s;
            width: 100%;
        }
        
        .start-btn:hover {
            background-color: var(--accent-color);
            transform: translateY(-2px);
        }
        
        .map-rates-container {
            flex: 1;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }
        
        .map-rates-container h3 {
            font-size: 1.5rem;
            margin-top: 0;
        }
        
        .rate-list {
            max-height: 250px;
            overflow-y: auto;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
        }
        
        .rate-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .rate-item:last-child {
            border-bottom: none;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 15px;
        }
        
        #mapFrame {
            flex: 1;
            width: 100%;
            border: 0;
            border-radius: 12px;
            min-height: 300px;
        }

        /* New table styles */
        .table-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            padding: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        thead th {
            background-color: var(--primary-color);
            color: var(--text-color-light);
            font-weight: 600;
        }
        
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        tbody tr:hover {
            background-color: #f1f1f1;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #777;
            font-size: 1.2rem;
        }
        
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .btn {
            background: var(--primary-color);
            color: var(--text-color-light);
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.2s, transform 0.2s;
        }
        
        .btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn.refuse {
            background-color: var(--refuse-color);
        }
        .btn.refuse:hover {
            background-color: #c9302c;
        }
        
        .btn.secondary {
            background-color: #6c757d;
        }
        
        .btn.secondary:hover {
            background-color: #5a6268;
        }
        
        .btn.move-back {
            background-color: #9b59b6;
        }
        
        .btn.move-back:hover {
            background-color: #8e44ad;
        }
        
        /* New Styles for Map and Pickup List in In-Progress View */
        .live-route-container {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 16px;
        }
        #map {
            height: 520px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
        }
        .pickup-list-card {
            background: #fff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 6px rgba(0,0,0,.06);
        }
        .pickup-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .pickup-item:last-child {
            border-bottom: none;
        }
        .pickup-item .btn {
            background: var(--primary-color);
            color: #fff;
            padding: 6px 10px;
            margin: 4px 0;
            border: 0;
            border-radius: 6px;
            cursor: pointer;
        }
        .pickup-item .btn:hover {
            background: var(--secondary-color);
        }
        .direction-steps {
            margin-bottom: 12px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        /* New on-page form styles */
        .update-form {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            z-index: 1000;
            width: 90%;
            max-width: 500px;
        }
        .update-form input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        .update-form .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }


        /* Responsive Design */
        @media (max-width: 900px) {
            .main-content {
                flex-direction: column;
            }
            .sidebar {
                transform: translateX(-100%);
                z-index: 1000;
            }
            .dashboard-container {
                margin-left: 0;
                width: 100%;
            }
            .live-route-container {
                grid-template-columns: 1fr;
            }
            .counts {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include "include/header.php"; ?>

<div class="sidebar">
    <h2>Collector Panel</h2>
    <ul>
        <li><a href="?view=dashboard" class="<?= $view === 'dashboard' ? 'active' : '' ?>"><i class="fas fa-home"></i>Dashboard</a></li>
        <li><a href="?view=assigned" class="<?= $view === 'assigned' ? 'active' : '' ?>"><i class="fas fa-list-ul"></i>Assigned Pickups</a></li>
        <li><a href="?view=inprogress" class="<?= $view === 'inprogress' ? 'active' : '' ?>"><i class="fas fa-truck-pickup"></i>In Progress</a></li>
        <li><a href="?view=live-route" class="<?= $view === 'live-route' ? 'active' : '' ?>"><i class="fas fa-map-marked-alt"></i>Live Route</a></li>
        <li><a href="?view=awaiting" class="<?= $view === 'awaiting' ? 'active' : '' ?>"><i class="fas fa-hourglass-half"></i>Awaiting Approval</a></li>
        <li><a href="?view=history" class="<?= $view === 'history' ? 'active' : '' ?>"><i class="fas fa-history"></i>Completed Pickups</a></li>
        <li><a href="?view=refused" class="<?= $view === 'refused' ? 'active' : '' ?>"><i class="fas fa-ban"></i>Refused Pickups</a></li>
        <li><a href="?view=rates" class="<?= $view === 'rates' ? 'active' : '' ?>"><i class="fas fa-dollar-sign"></i>Waste Rates</a></li>
    </ul>
    <button class="logout-btn" onclick="location.href='logout.php'"><i class="fas fa-sign-out-alt"></i>Log Out</button>
</div>

<div class="dashboard-container">
    <div class="main-content">
        <?php if ($view === 'dashboard'): ?>
            <div class="dashboard-body">
                <h1 class="page-title">Welcome, Collector!</h1>
                <div class="counts">
                    <div class="card card-assigned">
                        <h3>Assigned Pickups</h3>
                        <p><?php echo $counts['assigned']; ?></p>
                    </div>
                    <div class="card card-inprogress">
                        <h3>In Progress</h3>
                        <p><?php echo $counts['inprogress']; ?></p>
                    </div>
                    <div class="card card-awaiting">
                        <h3>Awaiting Approval</h3>
                        <p><?php echo $counts['awaiting']; ?></p>
                    </div>
                    <div class="card card-completed">
                        <h3>Completed Pickups</h3>
                        <p><?php echo $counts['completed']; ?></p>
                    </div>
                    <div class="card card-refused">
                        <h3>Refused Pickups</h3>
                        <p><?php echo $counts['refused']; ?></p>
                    </div>
                    <div class="card card-total">
                        <h3>Total Waste Collected</h3>
                        <p><?php echo number_format($counts['total_collected_weight'], 2); ?> kg</p>
                    </div>
                </div>
                <button class="start-btn" onclick="location.href='?view=inprogress'">Start a Pickup Route</button>
            </div>
            
            <div class="map-rates-container">
                <h3>Current Waste Rates</h3>
                <div class="search-box">
                    <input type="text" id="rateSearch" placeholder="Search waste type...">
                </div>
                <div class="rate-list" id="rateList">
                    <?php foreach ($rates as $rate): ?>
                        <div class="rate-item">
                            <strong><?php echo htmlspecialchars($rate['waste_type']); ?></strong>
                            <span>Rs.<?php echo htmlspecialchars($rate['rate_per_kg']); ?>/kg</span>
                            <small>(Updated: <?php echo htmlspecialchars($rate['updated_at']); ?>)</small>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
            
        <?php elseif ($view === 'assigned'):
            // Fetch assigned pickups for display
            $select_sql = "
                SELECT pr.id, pr.user_id, u.name AS customer_name,
                       pr.waste_type,
                       pr.`{$weight_col}` AS weight,
                       pr.`{$address_col}` AS location,
                       pr.`{$pickup_date_col}` AS pickup_date,
                       pr.status
                FROM pickup_requests pr
                LEFT JOIN users u ON pr.user_id = u.id
                WHERE pr.`{$assignee_col}` = ?
                  AND (LOWER(COALESCE(pr.status,'')) NOT IN ('completed','cancelled','collected','in progress','refused'))
                ORDER BY pr.`{$pickup_date_col}` ASC, pr.id ASC
            ";
            $stmt = mysqli_prepare($db, $select_sql);
            if (!$stmt) die("Prepare failed: " . mysqli_error($db));
            mysqli_stmt_bind_param($stmt, "i", $collector_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $rows = [];
            while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
            mysqli_stmt_close($stmt);
        ?>
            <div class="content-body">
                <h1>Assigned Pickups</h1>
                <?php if ($message): ?>
                    <div class="msg"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <div class="table-container">
                    <?php if (count($rows) === 0): ?>
                        <div class="no-data">No pickups currently assigned to you.</div>
                    <?php else: ?>
                        <form method="post" id="pickupForm">
                            <input type="hidden" name="start_selected" value="1" />
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width:38px; text-align:center;"><input type="checkbox" id="select_all" title="Select all" /></th>
                                        <th>Customer Name</th>
                                        <th>Waste Type</th>
                                        <th>Estimated Weight (kg)</th>
                                        <th>Location</th>
                                        <th>Pickup Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $r): ?>
                                        <tr>
                                            <td style="text-align:center;">
                                                <input class="checkbox" type="checkbox" name="selected_ids[]" value="<?= (int)$r['id'] ?>" />
                                            </td>
                                            <td><?= htmlspecialchars($r['customer_name'] ?? 'Unknown') ?></td>
                                            <td><?= htmlspecialchars($r['waste_type'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['weight'] ?? '-'); ?></td>
                                            <td><?= htmlspecialchars($r['location'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['pickup_date'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['status'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <div class="actions">
                                <button type="button" class="btn" id="startBtn">Mark as In Progress</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($view === 'inprogress'):
            // Fetch in progress pickups for display, including latitude/longitude
            $select_sql = "
                SELECT pr.*, u.name AS customer_name, u.phone,
                       pr.latitude, pr.longitude, pr.rate
                FROM pickup_requests pr
                LEFT JOIN users u ON pr.user_id = u.id
                WHERE pr.`{$assignee_col}` = ?
                  AND LOWER(COALESCE(pr.status,'')) LIKE 'in progress%'
                ORDER BY pr.id ASC
            ";
            $stmt = mysqli_prepare($db, $select_sql);
            if (!$stmt) die("Prepare failed: " . mysqli_error($db));
            mysqli_stmt_bind_param($stmt, "i", $collector_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $rows = [];
            while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
            mysqli_stmt_close($stmt);
        ?>
            <div class="content-body">
                <h1>In Progress Pickups</h1>
                <div class="table-container">
                    <?php if (count($rows) === 0): ?>
                        <div class="no-data">No pickups are currently in progress.</div>
                    <?php else: ?>
                        <form method="post" id="inprogressForm">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width:38px; text-align:center;"><input type="checkbox" id="select_all_inprogress" title="Select all" /></th>
                                        <th>Customer Name</th>
                                        <th>Waste Type</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $r): ?>
                                        <tr>
                                            <td style="text-align:center;">
                                                <input class="checkbox" type="checkbox" name="selected_ids[]" value="<?= (int)$r['id'] ?>" />
                                            </td>
                                            <td><?= htmlspecialchars($r['customer_name'] ?? 'Unknown') ?></td>
                                            <td><?= htmlspecialchars($r['waste_type'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['address'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['status'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="actions">
                                <button type="button" class="btn secondary" id="moveBackBtn">Move back to Assigned</button>
                                <button type="button" class="btn refuse" id="refuseBtn">Refuse Pickup</button>
                                <a href="?view=live-route" class="btn">View Live Route</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($view === 'live-route'):
            // Fetch in progress pickups for map display
            $select_sql = "
                SELECT pr.*, u.name AS customer_name, u.phone,
                       pr.latitude, pr.longitude, pr.rate
                FROM pickup_requests pr
                LEFT JOIN users u ON pr.user_id = u.id
                WHERE pr.`{$assignee_col}` = ?
                  AND LOWER(COALESCE(pr.status,'')) LIKE 'in progress%'
                ORDER BY pr.id ASC
            ";
            $stmt = mysqli_prepare($db, $select_sql);
            if (!$stmt) die("Prepare failed: " . mysqli_error($db));
            mysqli_stmt_bind_param($stmt, "i", $collector_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $pickups = [];
            while ($r = mysqli_fetch_assoc($res)) {
                $pickups[] = [
                    'id'            => (int)$r['id'],
                    'customer_name' => $r['customer_name'],
                    'phone'         => $r['phone'],
                    'waste_type'    => $r['waste_type'],
                    'weight'        => (float)$r['weight'],
                    'rate'          => (float)$r['rate'],
                    'address'       => $r['address'],
                    'lat'           => (float)$r['latitude'],
                    'lng'           => (float)$r['longitude']
                ];
            }
            mysqli_stmt_close($stmt);
        ?>
            <div class="content-body">
                <h1>Live Optimized Pickup Route</h1>
                <a href="?view=inprogress" class="btn secondary">Back to In Progress List</a>
                <div class="live-route-container" style="margin-top: 20px;">
                    <div id="map"></div>
                    <div class="pickup-list-card">
                        <h3>Direction to Current Pickup</h3>
                        <div id="steps" class="direction-steps"></div>
                        <h3>Pickup List</h3>
                        <div id="list"></div>
                    </div>
                </div>
            </div>
            
            <div class="overlay" onclick="hideForm()"></div>
            <div class="update-form" id="updateForm">
                <span class="close-btn" onclick="hideForm()">&times;</span>
                <h2 id="updateFormTitle">Update Pickup</h2>
                <form method="POST">
                    <input type="hidden" name="update_collected_waste" value="1">
                    <input type="hidden" name="pickup_id" id="form-pickup-id">
                    
                    <label>Collected Items</label>
                    <input type="text" name="collected_items" id="form-collected-items" required>
                    
                    <label>Final Weight (kg)</label>
                    <input type="number" step="0.01" name="final_weight" id="form-final-weight" required>
                    
                    <label>Rate (per kg)</label>
                    <input type="number" step="0.01" name="rate" id="form-rate" required>
                    
                    <label>Total Cost</label>
                    <input type="number" step="0.01" id="form-total-cost" name="total_cost_display" readonly>
                    
                    <button type="submit" class="btn">Update & Save</button>
                </form>
            </div>
            

        <?php elseif ($view === 'awaiting'):
            // Fetch awaiting approval pickups
            $select_sql = "
                SELECT pr.id, pr.user_id, u.name AS customer_name,
                       pr.waste_type,
                       pr.`{$weight_col}` AS weight,
                       pr.`{$address_col}` AS location,
                       pr.`{$pickup_date_col}` AS pickup_date,
                       pr.status
                FROM pickup_requests pr
                LEFT JOIN users u ON pr.user_id = u.id
                WHERE pr.`{$assignee_col}` = ?
                  AND LOWER(COALESCE(pr.status,'')) = 'collected'
                ORDER BY pr.`{$pickup_date_col}` ASC, pr.id ASC
            ";
            $stmt = mysqli_prepare($db, $select_sql);
            if (!$stmt) die("Prepare failed: " . mysqli_error($db));
            mysqli_stmt_bind_param($stmt, "i", $collector_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $rows = [];
            while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
            mysqli_stmt_close($stmt);
        ?>
            <div class="content-body">
                <h1>Awaiting Approval</h1>
                <?php if ($message): ?>
                    <div class="msg"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <div class="table-container">
                    <?php if (count($rows) === 0): ?>
                        <div class="no-data">No pickups awaiting approval.</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer Name</th>
                                    <th>Waste Type</th>
                                    <th>Weight (kg)</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['customer_name'] ?? 'Unknown') ?></td>
                                        <td><?= htmlspecialchars($r['waste_type'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($r['weight'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($r['location'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($r['status'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($view === 'history'):
            // Fetch completed history
            $select_sql = "
                SELECT pr.id, pr.user_id, u.name AS customer_name,
                       pr.waste_type,
                       pr.`{$weight_col}` AS weight,
                       pr.`{$address_col}` AS location,
                       pr.`{$pickup_date_col}` AS pickup_date,
                       pr.status
                FROM pickup_requests pr
                LEFT JOIN users u ON pr.user_id = u.id
                WHERE pr.`{$assignee_col}` = ?
                  AND LOWER(COALESCE(pr.status,'')) = 'completed'
                ORDER BY pr.`{$pickup_date_col}` ASC, pr.id ASC
            ";
            $stmt = mysqli_prepare($db, $select_sql);
            if (!$stmt) die("Prepare failed: " . mysqli_error($db));
            mysqli_stmt_bind_param($stmt, "i", $collector_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $rows = [];
            while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
            mysqli_stmt_close($stmt);
        ?>
            <div class="content-body">
                <h1>Completed Pickups</h1>
                <?php if (count($rows) === 0): ?>
                    <div class="no-data">No completed pickups found.</div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer Name</th>
                                    <th>Waste Type</th>
                                    <th>Weight (kg)</th>
                                    <th>Location</th>
                                    <th>Pickup Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['customer_name'] ?? 'Unknown') ?></td>
                                        <td><?= htmlspecialchars($r['waste_type'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($r['weight'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($r['location'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($r['pickup_date'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($r['status'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        
        <?php elseif ($view === 'refused'):
            // Fetch refused pickups
            $select_sql = "
                SELECT pr.id, pr.user_id, u.name AS customer_name,
                       pr.waste_type,
                       pr.`{$weight_col}` AS weight,
                       pr.`{$address_col}` AS location,
                       pr.`{$pickup_date_col}` AS pickup_date,
                       pr.status
                FROM pickup_requests pr
                LEFT JOIN users u ON pr.user_id = u.id
                WHERE pr.`{$assignee_col}` = ?
                  AND LOWER(COALESCE(pr.status,'')) = 'refused'
                ORDER BY pr.`{$pickup_date_col}` ASC, pr.id ASC
            ";
            $stmt = mysqli_prepare($db, $select_sql);
            if (!$stmt) die("Prepare failed: " . mysqli_error($db));
            mysqli_stmt_bind_param($stmt, "i", $collector_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $rows = [];
            while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
            mysqli_stmt_close($stmt);
        ?>
            <div class="content-body">
                <h1>Refused Pickups</h1>
                <?php if (count($rows) === 0): ?>
                    <div class="no-data">No refused pickups found.</div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer Name</th>
                                    <th>Waste Type</th>
                                    <th>Weight (kg)</th>
                                    <th>Location</th>
                                    <th>Pickup Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['customer_name'] ?? 'Unknown') ?></td>
                                        <td><?= htmlspecialchars($r['waste_type'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($r['weight'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($r['location'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($r['pickup_date'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($r['status'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($view === 'rates'): ?>
            <div class="content-body">
                <h1>Waste Rates</h1>
                <div class="map-rates-container" style="flex: auto; padding: 20px;">
                    <div class="search-box">
                        <input type="text" id="rateSearch" placeholder="Search waste type...">
                    </div>
                    <div class="rate-list" id="rateList" style="max-height: 500px;">
                        <?php foreach ($rates as $rate): ?>
                            <div class="rate-item">
                                <strong><?php echo htmlspecialchars($rate['waste_type']); ?></strong>
                                <span>Rs.<?php echo htmlspecialchars($rate['rate_per_kg']); ?>/kg</span>
                                <small>(Updated: <?php echo htmlspecialchars($rate['updated_at']); ?>)</small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.js"></script>
<script>
    // General JavaScript to make the new sections interactive
    const startBtn = document.getElementById('startBtn');
    if (startBtn) {
        startBtn.addEventListener('click', function() {
            const checked = document.querySelectorAll('input[name="selected_ids[]"]:checked').length;
            if (checked === 0) {
                alert('No Pickups Selected', 'Please select at least one pickup to start.');
                return;
            }
            if (confirm(`Mark ${checked} pickup(s) as 'In Progress'?`)) {
                document.getElementById('pickupForm').submit();
            }
        });
    }

    const inprogressForm = document.getElementById('inprogressForm');
    const moveBackBtn = document.getElementById('moveBackBtn');
    const refuseBtn = document.getElementById('refuseBtn');

    if (moveBackBtn) {
        moveBackBtn.addEventListener('click', function() {
            const checkedCheckboxes = document.querySelectorAll('#inprogressForm input[name="selected_ids[]"]:checked');
            if (checkedCheckboxes.length === 0) {
                alert('No Pickups Selected', 'Please select at least one pickup.');
                return;
            }
            if (confirm(`Move ${checkedCheckboxes.length} pickup(s) back to 'Assigned'?`)) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'move_back_selected';
                hiddenInput.value = '1';
                inprogressForm.appendChild(hiddenInput);
                inprogressForm.submit();
            }
        });
    }

    if (refuseBtn) {
        refuseBtn.addEventListener('click', function() {
            const checked = document.querySelectorAll('#inprogressForm input[name="selected_ids[]"]:checked').length;
            if (checked === 0) {
                alert('No Pickups Selected', 'Please select at least one pickup.');
                return;
            }
            if (confirm(`Mark ${checked} pickup(s) as 'Refused'?`)) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'refuse_selected';
                hiddenInput.value = '1';
                inprogressForm.appendChild(hiddenInput);
                inprogressForm.submit();
            }
        });
    }
    
    // Simple select all toggle for assigned pickups
    document.getElementById('select_all')?.addEventListener('change', function(e){
        const checked = e.target.checked;
        document.querySelectorAll('input[name="selected_ids[]"]').forEach(cb => cb.checked = checked);
    });
    
    // Simple select all toggle for in-progress pickups
    document.getElementById('select_all_inprogress')?.addEventListener('change', function(e){
        const checked = e.target.checked;
        document.querySelectorAll('#inprogressForm input[name="selected_ids[]"]').forEach(cb => cb.checked = checked);
    });
    
    // Search filter for rates
    document.getElementById('rateSearch')?.addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let items = document.querySelectorAll('#rateList .rate-item');
        items.forEach(item => {
            let text = item.textContent.toLowerCase();
            item.style.display = text.includes(filter) ? "flex" : "none";
        });
    });

    // Map script for Live Route view
    const pickups = <?php echo json_encode($pickups ?? []); ?>;
    let map;
    let collectorMarker, routingControl, otherMarkers=[];

    function haversine(lat1, lon1, lat2, lon2){
        const R=6371, dLat=(lat2-lat1)*Math.PI/180, dLon=(lon2-lon1)*Math.PI/180;
        const a=Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
        return R*2*Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    function greedyOrder(start, stops){
        let order=[], current={...start}, remaining=[...stops];
        while(remaining.length){
            let nearest=remaining.reduce((a,b)=>haversine(current.lat,current.lng,a.lat,a.lng)<haversine(current.lat,current.lng,b.lat,b.lng)?a:b);
            order.push(nearest);
            current=nearest;
            remaining=remaining.filter(p=>p.id!==nearest.id);
        }
        return order;
    }

    function renderRoute(start, ordered){
        if(map) map.remove();
        map = L.map('map').setView([start.lat, start.lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
            attribution:'© OSM contributors'
        }).addTo(map);

        if(routingControl) map.removeControl(routingControl);
        otherMarkers.forEach(m=>map.removeLayer(m));
        otherMarkers=[];

        if(!ordered.length) {
            document.getElementById('steps').innerHTML = "<p>No pending pickups.</p>";
            return;
        }

        const currentPickup = ordered[0];
        const waypoints = [L.latLng(start.lat,start.lng), L.latLng(currentPickup.lat,currentPickup.lng)];

        routingControl = L.Routing.control({
            waypoints: waypoints,
            lineOptions: {styles: [{color: 'blue', weight: 5}]},
            createMarker: function(i, wp, nWps) {
                if(i===0){
                    return L.marker(wp.latLng, {
                        icon: L.icon({iconUrl:"https://cdn-icons-png.flaticon.com/512/64/64113.png",iconSize:[25,25]})
                    }).bindPopup("You (Collector)");
                } else {
                    return L.marker(wp.latLng, {
                        icon: L.icon({iconUrl:"https://cdn-icons-png.flaticon.com/512/190/190411.png",iconSize:[25,25]})
                    }).bindPopup("Next Pickup: "+currentPickup.customer_name);
                }
            },
            addWaypoints: false,
            routeWhileDragging: false,
            show: false
        }).addTo(map);

        // step-by-step directions
        routingControl.on('routesfound', function(e){
            let html = '<div class="direction-steps"><strong>To: '+currentPickup.customer_name+'</strong><br>';
            e.routes[0].instructions.forEach(step=>{
                html += "• " + step.text + "<br>";
            });
            html += '</div>';
            document.getElementById('steps').innerHTML = html;
        });

        // Add markers for other pickups
        ordered.slice(1).forEach(p=>{
            let m = L.marker([p.lat,p.lng],{
                icon: L.icon({iconUrl:"https://cdn-icons-png.flaticon.com/512/64/64572.png",iconSize:[22,22],iconAnchor:[11,11]})
            }).bindPopup("Other Pickup: "+p.customer_name);
            m.addTo(map);
            otherMarkers.push(m);
        });

        // Pickup list
        let html='';
        ordered.forEach((p,i)=>{
            let dist = haversine(start.lat,start.lng,p.lat,p.lng).toFixed(2);
            html+=`<div class="pickup-item" data-id="${p.id}" data-items="${p.waste_type}" data-weight="${p.weight}" data-rate="${p.rate}">
                <strong>${i+1}. ${p.customer_name} (${p.phone})</strong><br>
                ${p.waste_type} • ${p.weight}kg • Rate: ${p.rate} • ${dist} km<br>
                <button class="btn" onclick="markCollected(${p.id})">Mark as Collected</button>
                <button class="btn secondary" onclick="showUpdateForm(${p.id})">Update Pickup</button>
            </div>`;
        });
        document.getElementById('list').innerHTML=html;
    }

    if (window.location.search.includes('view=live-route')) {
        if (navigator.geolocation) {
            navigator.geolocation.watchPosition(pos=>{
                let start={lat:pos.coords.latitude,lng:pos.coords.longitude};
                if(pickups.length){
                    let ordered=greedyOrder(start,pickups);
                    renderRoute(start,ordered);
                } else {
                    const noDataHtml = '<div class="no-data">No active pickups.</div>';
                    document.getElementById('map').innerHTML = noDataHtml;
                    document.getElementById('steps').innerHTML = noDataHtml;
                    document.getElementById('list').innerHTML = noDataHtml;
                }
            },()=>alert("Location access denied. Enable GPS."),{enableHighAccuracy:true});
        } else {
            document.getElementById('map').innerHTML = '<div class="no-data">Geolocation is not supported by your browser.</div>';
        }
    }

    // New on-page update form logic
    function showUpdateForm(id) {
        const item = document.querySelector(`.pickup-item[data-id='${id}']`);
        if (!item) return;

        const wasteType = item.getAttribute('data-items');
        const weight = item.getAttribute('data-weight');
        const rate = item.getAttribute('data-rate');

        document.getElementById('updateFormTitle').textContent = `Update Pickup #${id}`;
        document.getElementById('form-pickup-id').value = id;
        document.getElementById('form-collected-items').value = wasteType;
        document.getElementById('form-final-weight').value = weight;
        document.getElementById('form-rate').value = rate;
        
        calculateTotal();

        document.getElementById('updateForm').style.display = 'block';
        document.querySelector('.overlay').style.display = 'block';
    }

    function hideForm() {
        document.getElementById('updateForm').style.display = 'none';
        document.querySelector('.overlay').style.display = 'none';
    }
    
    function calculateTotal() {
        let weight = parseFloat(document.getElementById("form-final-weight").value) || 0;
        let rate = parseFloat(document.getElementById("form-rate").value) || 0;
        let total = weight * rate;
        document.getElementById("form-total-cost").value = total.toFixed(2);
    }
    
    document.getElementById("form-final-weight")?.addEventListener("input", calculateTotal);
    document.getElementById("form-rate")?.addEventListener("input", calculateTotal);

    // Function to mark as collected with a simple confirmation
    async function markCollected(id){
        if (!confirm(`Are you sure you want to mark pickup #${id} as 'Collected'?`)) return;
        
        try{
            const item = document.querySelector(`.pickup-item[data-id='${id}']`);
            const collectedItems = item.getAttribute('data-items');
            const finalWeight = item.getAttribute('data-weight');
            const rate = item.getAttribute('data-rate');

            const res = await fetch('newupdate.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    view: 'live-route',
                    update_collected_waste: '1',
                    pickup_id: id,
                    collected_items: collectedItems,
                    final_weight: finalWeight,
                    rate: rate
                })
            });
            if (res.ok) {
                location.reload();
            } else {
                alert('Failed to mark as collected. Please try again.');
            }
        } catch(e) { 
            alert('Failed to connect to the server.');
        }
    }
</script>
</body>
</html>