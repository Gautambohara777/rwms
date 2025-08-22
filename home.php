<?php
include "connect.php";

// Start the session to access user data
if (session_status() === PHP_SESSION_NONE) session_start();

// Get the user's role and check if they are logged in
$user = $_SESSION['user'] ?? null;
$userRole = $user['user_role'] ?? 'guest';

// Fetch waste rate data
$wasteRates = [];
$result = $con->query("SELECT * FROM waste_rates ORDER BY updated_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $wasteRates[] = $row;
    }
}

// Define the dashboard link based on the user's role
$dashboardLink = '';
if ($userRole === 'admin') {
    $dashboardLink = 'admin_dashboard.php';
} elseif ($userRole === 'collector') {
    $dashboardLink = 'collector_dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RecycleHub - Home</title>
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #ffffffff;
        }

        .video-section {
            position: relative;
            width: 100%;
            height: 85vh;
            overflow: hidden;
            margin: 0;
            padding: 0;
            background-color: black;
        }

        .video-section video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: top center;
            display: block;
        }

        .right-overlay {
            position: absolute;
            top: 20px;
            right: 30px;
            width: 350px;
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            max-height: 90%;
            overflow-y: auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .rate-panel h3 {
            margin-top: 0;
            color: #2e7d32;
        }

        .rate-panel table {
            width: 100%;
            border-collapse: collapse;
        }

        .rate-panel th, .rate-panel td {
            padding: 8px;
            text-align: left;
            font-size: 14px;
        }

        .rate-panel th {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .rate-panel {
            width: 100%;
        }

        .recycle-text {
            font-size: 20px;
            font-weight: bold;
            color: #2e7d32;
            text-align: center;
            margin: 20px 0 10px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        /* New dashboard button styles */
        .dashboard-button {
            background-color: #1a5223; /* A darker shade of green */
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
            text-decoration: none;
            display: inline-block;
        }

        .start-button, .option-button {
            background-color: #2e7d32;
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }

        .start-button-container {
            text-align: center;
            width: 100%;
        }

        .option-buttons {
            display: none;
            flex-direction: column;
            align-items: center;
            margin-top: 10px;
        }

        .option-buttons p {
            font-size: 16px;
            margin-bottom: 10px;
        }

        @media(max-width: 768px) {
            .right-overlay {
                position: static;
                width: 90%;
                margin: auto;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'include/header.php'; ?>

    <main>
        <?php include 'include/sidebar.php'; ?>

        <div class="video-section">
            <video autoplay muted loop playsinline>
                <source src="img/intro.mp4" type="video/mp4">
                Your browser does not support the video tag.
            </video>

            <div class="right-overlay">
                <div class="rate-panel">
                    <h3>Current Waste Rates</h3>
                    <table border="1">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Rate (NPR/KG)</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wasteRates as $rate): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rate['waste_type']); ?></td>
                                    <td><?php echo htmlspecialchars($rate['rate_per_kg']); ?></td>
                                    <td><?php echo htmlspecialchars(date("M d, Y", strtotime($rate['updated_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($wasteRates)): ?>
                                <tr><td colspan="3">No data available.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (!empty($dashboardLink)): ?>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="<?php echo $dashboardLink; ?>" class="dashboard-button">Go to Dashboard</a>
                    </div>
                <?php endif; ?>

                <div class="recycle-text">♻ Recycle Waste, Save Earth 🌍</div>
                <div class="start-button-container">
                    <button class="start-button" id="startBtn" onclick="revealOptions()">Start Now</button>
                    <div class="option-buttons" id="options">
                        <div class="recycle-text">Which one would you like to proceed with?</div>
                        <div>
                            <a href="sell.php"><button class="option-button">Sell</button></a>
                            <a href="buy.php"><button class="option-button">Buy</button></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function revealOptions() {
                document.getElementById('startBtn').style.display = 'none';
                document.getElementById('options').style.display = 'flex';
            }
        </script>
    </main>

    <?php include 'include/footer.php'; ?>
</body>
</html>