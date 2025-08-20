<?php
session_start();
include "connect.php"; // DB connection (must set $con)

if (!isset($_SESSION['user']) || $_SESSION['user_role'] !== 'collector') {
    header("Location: login.php");
    exit();
}

$collector_id = (int) $_SESSION['user'];

// Assigned pickups (exclude refused as well)
$sql_assigned = "SELECT COUNT(*) 
                 FROM pickup_requests 
                 WHERE assigned_collector_id = ? 
                   AND LOWER(status) NOT LIKE 'complete%' 
                   AND LOWER(status) NOT LIKE 'in progress%' 
                   AND LOWER(status) <> 'collected'
                   AND LOWER(status) <> 'refused'";
$stmt = mysqli_prepare($con, $sql_assigned);
mysqli_stmt_bind_param($stmt, "i", $collector_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $assigned_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
$assigned_count = (int) ($assigned_count ?? 0);


// In Progress
$sql_inprogress = "SELECT COUNT(*) 
                   FROM pickup_requests 
                   WHERE assigned_collector_id = ? 
                     AND LOWER(status) LIKE 'in progress%'";
$stmt = mysqli_prepare($con, $sql_inprogress);
mysqli_stmt_bind_param($stmt, "i", $collector_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $inprogress_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
$inprogress_count = (int) ($inprogress_count ?? 0);

// Awaiting Approval
$sql_awaiting = "SELECT COUNT(*) 
                 FROM pickup_requests 
                 WHERE assigned_collector_id = ? 
                   AND LOWER(status) = 'collected'";
$stmt = mysqli_prepare($con, $sql_awaiting);
mysqli_stmt_bind_param($stmt, "i", $collector_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $awaiting_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
$awaiting_count = (int) ($awaiting_count ?? 0);

// Completed
$sql_completed = "SELECT COUNT(*) 
                  FROM pickup_requests 
                  WHERE assigned_collector_id = ? 
                    AND LOWER(status) LIKE 'complete%'";
$stmt = mysqli_prepare($con, $sql_completed);
mysqli_stmt_bind_param($stmt, "i", $collector_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $completed_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);
$completed_count = (int) ($completed_count ?? 0);

// Fetch Waste Rates
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
<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f9f5;
        margin: 0;
        padding: 0;
        height: 100vh;
        display: flex;
        flex-direction: column;
    }
    header.page-header {
        background-color: #000;
        padding: 15px;
        color: white;
        text-align: center;
        font-size: 24px;
        font-weight: bold;
    }
    .dashboard {
        flex: 1;
        display: flex;
    }
    /* Left side */
    .left-side {
        flex: 2;
        display: flex;
        flex-direction: column;
        padding: 20px;
        gap: 20px;
    }
    .counts {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        flex: 1;
    }
    .card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.08);
        text-align: center;
        cursor: pointer;
        transition: transform 0.2s;
    }
    .card:hover {
        transform: scale(1.05);
    }
    .card h3 {
        margin-bottom: 10px;
        font-size: 20px;
        color: #333;
    }
    .card p {
        font-size: 50px;
        font-weight: 700;
        color: #2e7d32;
        margin: 0;
    }
    .start-btn {
        background: #2e7d32;
        color: white;
        border: none;
        border-radius: 12px;
        padding: 20px;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
        text-align: center;
        transition: background 0.3s;
        width: 100%;
    }
    .start-btn:hover {
        background-color: #256428;
    }
    /* Right side map and rates */
    .map-box {
        flex: 1;
        background: white;
        padding: 20px;
        border-radius: 50px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.08);
        display: flex;
        flex-direction: column;
    }
    .rate-list {
      
        max-height: 200px;
        overflow-y: auto;
        margin-bottom: 15px;
        border: 3px solid #000000ff;
        border-radius: 8px;
        padding: 10px;
    }
    .rate-item {
        padding: 8px;
        border-bottom: 1px solid #000000ff;
        font-size: 17px;
    }
    .rate-item:last-child {
        border-bottom: none;
    }
    .rate-item strong {
        color: #2e7d32;
    }
    .search-box {
        margin-bottom: 10px;
    }
    .search-box input {
        width: 100%;
        padding: 8px;
        border-radius: 8px;
        border: 1px solid #ccc;
    }
    #mapFrame {
        flex: 1;
        width: 100%;
        border: 0;
        border-radius: 12px;
        min-height: 200px; /* Smaller map */
    }
    @media (max-width: 900px) {
        .dashboard {
            flex-direction: column;
        }
        .counts {
            grid-template-columns: 1fr;
        }
    }
</style>
</head>
<body>

<?php include 'include/header.php'; ?>

<header class="page-header">Welcome Collector</header>

<div class="dashboard">
    <!-- Left side -->
    <div class="left-side">
        <div class="counts">
            <div class="card" onclick="location.href='collectorassigned.php'">
                <h3>Assigned Pickups</h3>
                <p><?php echo $assigned_count; ?></p>
            </div>
            <div class="card" onclick="location.href='collectorinprogress.php'">
                <h3>In Progress</h3>
                <p><?php echo $inprogress_count; ?></p>
            </div>
            <div class="card" onclick="location.href='collectorwatingapproval.php'">
                <h3>Awaiting Approval</h3>
                <p><?php echo $awaiting_count; ?></p>
            </div>
            <div class="card" onclick="location.href='collectorhistory.php'">
                <h3>Completed Pickups</h3>
                <p><?php echo $completed_count; ?></p>
            </div>
        </div>
        <button class="start-btn" onclick="location.href='collector_pickup.php'">Start</button>
    </div>

    <!-- Right side (rates + map) -->
    <div class="map-box">
        <h3>Current Waste Rates</h3>
        <div class="search-box">
            <input type="text" id="rateSearch" placeholder="Search waste type...">
        </div>
        <div class="rate-list" id="rateList">
            <?php foreach ($rates as $rate): ?>
                <div class="rate-item">
                    <strong><?php echo htmlspecialchars($rate['waste_type']); ?></strong> - 
                    Rs.<?php echo htmlspecialchars($rate['rate_per_kg']); ?>/kg 
                    <small>(Updated: <?php echo htmlspecialchars($rate['updated_at']); ?>)</small>
                </div>
            <?php endforeach; ?>
        </div>

        <h3>Your Current Location</h3>
        <iframe id="mapFrame" title="Your current location"></iframe>
    </div>
</div>

<script>
// Map
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
        var lat = position.coords.latitude;
        var lon = position.coords.longitude;
        document.getElementById('mapFrame').src =
            "https://maps.google.com/maps?q=" + lat + "," + lon + "&z=15&output=embed";
    }, function() {
        document.getElementById('mapFrame').src =
            "https://maps.google.com/maps?q=Kathmandu&z=12&output=embed";
    });
} else {
    document.getElementById('mapFrame').src =
        "https://maps.google.com/maps?q=Kathmandu&z=12&output=embed";
}

// Search filter for rates
document.getElementById('rateSearch').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let items = document.querySelectorAll('#rateList .rate-item');
    items.forEach(item => {
        let text = item.textContent.toLowerCase();
        item.style.display = text.includes(filter) ? "" : "none";
    });
});
</script>

<?php include 'include/footer.php'; ?>
</body>
</html>
