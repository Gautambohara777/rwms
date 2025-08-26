<?php
session_start();
include "connect.php"; // Database connection (must set $con or $conn)
if (!isset($_SESSION['user']) || ($_SESSION['user_role'] ?? '') !== 'collector') {
    header("Location: login.php");
    exit();
}

// Determine DB connection variable (support $con or $conn)
if (isset($con) && $con instanceof mysqli) {
    $db = $con;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} else {
    die("Database connection not found. Check connect.php.");
}

if (!isset($_SESSION['user']) || ($_SESSION['user_role'] ?? '') !== 'collector') {
    header("Location: login.php");
    exit();
}

$collector_id = (int) $_SESSION['user'];
$message = "";

// Get the current view from the URL, default to dashboard
$view = $_GET['view'] ?? 'dashboard';

// --- Functions to check for column existence (for robust queries)
function col_exists($db, $table, $col) {
    $q = "SHOW COLUMNS FROM `$table` LIKE '" . mysqli_real_escape_string($db, $col) . "'";
    $r = mysqli_query($db, $q);
    return ($r && mysqli_num_rows($r) > 0);
}

$assigneeCandidates = ['assigned_collector_id', 'assigned_to', 'assigned_collector'];
$assignee_col = null;
foreach ($assigneeCandidates as $c) {
    if (col_exists($db, 'pickup_requests', $c)) {
        $assignee_col = $c;
        break;
    }
}
if (!$assignee_col) {
    $assignee_col = 'assigned_collector_id';
}

$addressCandidates = ['address', 'location', 'pickup_location'];
$address_col = null;
foreach ($addressCandidates as $c) {
    if (col_exists($db, 'pickup_requests', $c)) {
        $address_col = $c;
        break;
    }
}
if (!$address_col) $address_col = 'address';

$weightCandidates = ['weight', 'quantity', 'kg'];
$weight_col = null;
foreach ($weightCandidates as $c) {
    if (col_exists($db, 'pickup_requests', $c)) {
        $weight_col = $c;
        break;
    }
}
if (!$weight_col) $weight_col = 'weight';

$pickupDateCandidates = ['pickup_date', 'date', 'scheduled_date'];
$pickup_date_col = null;
foreach ($pickupDateCandidates as $c) {
    if (col_exists($db, 'pickup_requests', $c)) {
        $pickup_date_col = $c;
        break;
    }
}
if (!$pickup_date_col) $pickup_date_col = 'pickup_date';

// --- Handle POST requests for marking pickups as "In Progress"
if ($view === 'assigned' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_selected'])) {
    $selected = $_POST['selected_ids'] ?? [];
    $ids = array_map('intval', $selected);
    $ids = array_filter($ids, function($v){ return $v > 0; });
    if (count($ids) === 0) {
        $message = "No pickups selected to start.";
    } else {
        $id_list = implode(',', $ids);
        $update_sql = "UPDATE `pickup_requests` 
                       SET `status` = 'In Progress' 
                       WHERE id IN ($id_list) 
                         AND `$assignee_col` = ?
                         AND (LOWER(COALESCE(status,'')) NOT IN ('completed','cancelled','collected','in progress','refused'))";
        $stmt = mysqli_prepare($db, $update_sql);
        mysqli_stmt_bind_param($stmt, "i", $collector_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Selected pickups marked as 'In Progress'.";
        } else {
            $message = "Database error: " . mysqli_error($db);
        }
        mysqli_stmt_close($stmt);
    }
}

// --- Handle POST requests for in-progress pickups (MOVE BACK or REFUSED)
if ($view === 'inprogress' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_ids'])) {
    $selected = $_POST['selected_ids'] ?? [];
    $ids = array_map('intval', $selected);
    $ids = array_filter($ids, function($v){ return $v > 0; });
    
    if (count($ids) === 0) {
        $message = "No pickups selected.";
    } else {
        $id_list = implode(',', $ids);
        
        if (isset($_POST['move_back_selected'])) { // New action: move back to assigned
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

// --- Fetch Data for Dashboard Counts
$counts = [
    'assigned' => 0,
    'inprogress' => 0,
    'awaiting' => 0,
    'completed' => 0
];
$queries = [
    'assigned' => "AND LOWER(status) NOT IN ('complete', 'in progress', 'collected', 'refused')",
    'inprogress' => "AND LOWER(status) LIKE 'in progress%'",
    'awaiting' => "AND LOWER(status) = 'collected'",
    'completed' => "AND LOWER(status) LIKE 'complete%'"
];

foreach ($counts as $key => $val) {
    $sql = "SELECT COUNT(*) FROM pickup_requests WHERE assigned_collector_id = ? " . $queries[$key];
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "i", $collector_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    $counts[$key] = (int) ($count ?? 0);
    mysqli_stmt_close($stmt);
}

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
            margin-top: 80px;
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 90%;
            text-align: center;
        }

        .modal-content h3 {
            margin-top: 0;
        }
        
        .modal-content .modal-buttons {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .weight-input {
            width: 100px;
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ccc;
            text-align: center;
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
        <li><a href="?view=awaiting" class="<?= $view === 'awaiting' ? 'active' : '' ?>"><i class="fas fa-hourglass-half"></i>Awaiting Approval</a></li>
        <li><a href="?view=history" class="<?= $view === 'history' ? 'active' : '' ?>"><i class="fas fa-history"></i>Completed History</a></li>
        <li><a href="?view=rates" class="<?= $view === 'rates' ? 'active' : '' ?>"><i class="fas fa-dollar-sign"></i>Waste Rates</a></li>
    </ul>
    <button class="logout-btn" onclick="location.href='logout.php'"><i class="fas fa-sign-out-alt"></i>Log Out</button>
</div>

<div class="dashboard-container">
    <div class="main-content">
        <?php if ($view === 'dashboard'): ?>
            <!-- Dashboard Content -->
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
                </div>
                <button class="start-btn" onclick="location.href='?view=assigned'">Start a Pickup Route</button>
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

                <h3>Your Current Location</h3>
                <iframe id="mapFrame" title="Your current location"></iframe>
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
            <!-- Assigned Pickups Content -->
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
            // Fetch in progress pickups for display
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
                  AND LOWER(COALESCE(pr.status,'')) LIKE 'in progress%'
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
            <!-- In Progress Content -->
            <div class="content-body">
                <h1>In Progress Pickups</h1>
                <?php if ($message): ?>
                    <div class="msg"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <div class="table-container">
                    <?php if (count($rows) === 0): ?>
                        <div class="no-data">You have no active pickups.</div>
                    <?php else: ?>
                        <form method="post" id="inprogressForm">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width:38px; text-align:center;"><input type="checkbox" id="select_all_inprogress" title="Select all" /></th>
                                        <th>Customer Name</th>
                                        <th>Waste Type</th>
                                        <th>Estimated Weight (kg)</th>
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
                                            <td><?= htmlspecialchars($r['weight'] ?? '-'); ?></td>
                                            <td><?= htmlspecialchars($r['location'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($r['status'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="actions">
                                <button type="button" class="btn secondary" id="moveBackBtn">Move back to assigned pickup</button>
                                <button type="button" class="btn refuse" id="refuseBtn">Refuse Pickup</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
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
            <!-- Awaiting Approval Content (Modified) -->
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
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($view === 'history'):
            // Fetch completed history using the user's provided logic
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
                  AND (LOWER(COALESCE(pr.status,'')) NOT IN ('collected','cancelled','Approved','In Progress','Refused'))
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
            <!-- Completed History Content (Modified) -->
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
                <div class="actions">
                    <a href="collector_dashboard.php" class="btn secondary">Back to Dashboard</a>
                </div>
            </div>

        <?php elseif ($view === 'rates'): ?>
            <!-- Waste Rates Content -->
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

<!-- Custom Modal for Alerts/Confirms -->
<div id="customModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle"></h3>
        <p id="modalMessage"></p>
        <div class="modal-buttons">
            <button id="modalConfirmBtn" class="btn">OK</button>
            <button id="modalCancelBtn" class="btn secondary" style="display:none;">Cancel</button>
        </div>
    </div>
</div>

<script>
    // General JavaScript to make the new sections interactive
    const customModal = document.getElementById('customModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalConfirmBtn = document.getElementById('modalConfirmBtn');
    const modalCancelBtn = document.getElementById('modalCancelBtn');

    function showModal(title, message, isConfirm = false) {
        return new Promise(resolve => {
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            customModal.style.display = 'flex';
            modalCancelBtn.style.display = isConfirm ? 'inline-block' : 'none';

            const handleConfirm = () => {
                customModal.style.display = 'none';
                resolve(true);
                modalConfirmBtn.removeEventListener('click', handleConfirm);
                modalCancelBtn.removeEventListener('click', handleCancel);
            };

            const handleCancel = () => {
                customModal.style.display = 'none';
                resolve(false);
                modalConfirmBtn.removeEventListener('click', handleConfirm);
                modalCancelBtn.removeEventListener('click', handleCancel);
            };

            modalConfirmBtn.addEventListener('click', handleConfirm);
            if (isConfirm) {
                modalCancelBtn.addEventListener('click', handleCancel);
            }
        });
    }

    // Assign "Mark as In Progress" button functionality
    const startBtn = document.getElementById('startBtn');
    if (startBtn) {
        startBtn.addEventListener('click', async function() {
            const checked = document.querySelectorAll('input[name="selected_ids[]"]:checked').length;
            if (checked === 0) {
                await showModal('No Pickups Selected', 'Please select at least one pickup to start.');
                return;
            }
            const confirmed = await showModal('Confirm Action', `Mark ${checked} pickup(s) as 'In Progress'?`, true);
            if (confirmed) {
                document.getElementById('pickupForm').submit();
            }
        });
    }

    // Assign "Move back to assigned pickup" and "Refuse Pickup" buttons for In-Progress view
    const inprogressForm = document.getElementById('inprogressForm');
    const moveBackBtn = document.getElementById('moveBackBtn'); // Changed from collectBtn
    const refuseBtn = document.getElementById('refuseBtn');

    if (moveBackBtn) {
        moveBackBtn.addEventListener('click', async function() {
            const checkedCheckboxes = document.querySelectorAll('#inprogressForm input[name="selected_ids[]"]:checked');
            if (checkedCheckboxes.length === 0) {
                await showModal('No Pickups Selected', 'Please select at least one pickup.');
                return;
            }
            const confirmed = await showModal('Confirm Action', `Move ${checkedCheckboxes.length} pickup(s) back to 'Assigned'?`, true);
            if (confirmed) {
                const form = document.getElementById('inprogressForm');
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'move_back_selected'; // Changed from collect_selected
                hiddenInput.value = '1';
                form.appendChild(hiddenInput);
                form.submit();
            }
        });
    }

    if (refuseBtn) {
        refuseBtn.addEventListener('click', async function() {
            const checked = document.querySelectorAll('#inprogressForm input[name="selected_ids[]"]:checked').length;
            if (checked === 0) {
                await showModal('No Pickups Selected', 'Please select at least one pickup.');
                return;
            }
            const confirmed = await showModal('Confirm Refusal', `Mark ${checked} pickup(s) as 'Refused'?`, true);
            if (confirmed) {
                const form = document.getElementById('inprogressForm');
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'refuse_selected';
                hiddenInput.value = '1';
                form.appendChild(hiddenInput);
                form.submit();
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

    // Map script
    const mapFrame = document.getElementById('mapFrame');
    if (mapFrame) {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                var lat = position.coords.latitude;
                var lon = position.coords.longitude;
                mapFrame.src =
                    "https://maps.google.com/maps?q=" + lat + "," + lon + "&z=15&output=embed";
            }, function() {
                mapFrame.src =
                    "https://maps.google.com/maps?q=Kathmandu&z=12&output=embed";
            });
        } else {
            mapFrame.src =
                "https://maps.google.com/maps?q=Kathmandu&z=12&output=embed";
        }
    }
</script>

</body>
</html>
