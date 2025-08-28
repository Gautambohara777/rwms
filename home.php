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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RecycleHub - Home</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            color: #333;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .video-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }

        .video-background video {
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            object-fit: cover;
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 0;
        }

        .rates-card {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            max-width: 300px;
            width: 70%;
            text-align: center;
            position: absolute;
            top: 50%;
            right: 3%;
            transform: translateY(-50%);
            z-index: 1;
        }

        .rates-card h3 {
            color: white;
            font-size: 1.5em;
            font-weight: 600;
            margin-top: 0;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }

        .rates-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            color: white;
        }

        .rates-table th, .rates-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }

        .rates-table th {
            background-color: rgba(26, 82, 35, 0.4);
            font-weight: 600;
        }

        .rates-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .cta-container {
            position: absolute;
            bottom: 5vh;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
            z-index: 10;
        }
        
        .cta-button {
            padding: 15px 30px;
            font-size: 1.1em;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            transition: transform 0.3s ease, background-color 0.3s ease;
            cursor: pointer;
            border: none;
            display: inline-block;
            margin: 0 10px;
        }

        .cta-button.primary {
            background-color: #2e7d32;
            color: white;
        }

        .cta-button.secondary {
            background-color: #f1f8e9;
            color: #1a5223;
            border: 2px solid #2e7d32;
        }
        
        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        #options {
            display: none;
            gap: 20px;
        }

        .page-title {
            position: absolute;
            top: 85%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            text-shadow: 1px 1px 5px rgba(0,0,0,0.5);
            font-size: 2.5em;
            font-weight: 700;
            z-index: 1;
        }
    </style>
</head>
<body>
    <div class="video-background">
        <video autoplay muted loop playsinline>
            <source src="img/intro.mp4" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>
    
    <div class="overlay"></div>

    <?php include 'include/header.php'; ?>
    
    <main class="main-content">
        <div class="page-title">
            Recycle Waste, Save Earth 🌍
        </div>

        <div class="rates-card">
            <h3>Current Waste Rates</h3>
            <table class="rates-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Rate (NPR/KG)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wasteRates as $rate): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($rate['waste_type']); ?></td>
                            <td><?php echo htmlspecialchars($rate['rate_per_kg']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($wasteRates)): ?>
                        <tr><td colspan="2">No data available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="cta-container">
        <a href="#" class="cta-button primary" id="startBtn" onclick="revealOptions(event)">Start Now</a>
        <div id="options">
            <a href="sell.php" class="cta-button primary">Sell Your Waste</a>
            <a href="buy.php" class="cta-button secondary">Buy Recycled Products</a>
        </div>
        <?php if (!empty($dashboardLink)): ?>
            <a href="<?php echo $dashboardLink; ?>" class="cta-button secondary" style="margin-top: 10px;">Go to Dashboard</a>
        <?php endif; ?>
    </div>

    <script>
        function revealOptions(event) {
            event.preventDefault();
            document.getElementById('startBtn').style.display = 'none';
            document.getElementById('options').style.display = 'flex';
        }
    </script>

    <?php include 'include/footer.php'; ?>
</body>
</html>