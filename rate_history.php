<!-- Change Detection Algorithm(Current Rates no:266-271, Recent Changes no: 301-317) -->
<?php
session_start();
include "connect.php";
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Determine DB connection variable
if (isset($con) && $con instanceof mysqli) {
    $db = $con;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} else {
    die("Database connection not found. Check connect.php.");
}

// Check if a specific waste type is requested via GET and handle the AJAX request
if (isset($_GET['waste_type'])) {
    header('Content-Type: application/json');
    $waste_type = mysqli_real_escape_string($db, $_GET['waste_type']);

    // Fetch history for the selected waste type, sorted by oldest first for chart
    $stmt = $db->prepare("SELECT rate_per_kg, updated_at FROM waste_rate_history WHERE waste_type = ? ORDER BY updated_at ASC");
    $stmt->bind_param("s", $waste_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $history_data = [];
    while ($row = $result->fetch_assoc()) {
        $history_data[] = $row;
    }

    echo json_encode(['history' => $history_data]);
    exit(); // Stop script execution after sending JSON
}

// Fetch all current waste rates for the right sidebar with previous rates for comparison
$current_rates = [];
$result = mysqli_query($db, "SELECT waste_type, rate_per_kg FROM waste_rates ORDER BY waste_type ASC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $previous_rate = null;
        $stmt_prev = $db->prepare("SELECT rate_per_kg FROM waste_rate_history WHERE waste_type = ? ORDER BY updated_at DESC LIMIT 1, 1");
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

// Fetch recent rate changes for the second table on the right
$recent_changes = [];
$result_recent = mysqli_query($db, "SELECT waste_type, rate_per_kg, updated_at FROM waste_rate_history ORDER BY updated_at DESC LIMIT 5");
if ($result_recent) {
    while ($row = mysqli_fetch_assoc($result_recent)) {
        $recent_changes[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Waste Rate History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2e7d32;
            --secondary-color: #4CAF50;
            --accent-color: #a8e063;
            --text-color-dark: #333;
            --font-family-poppins: 'Poppins', sans-serif;
        }

        body {
            font-family: var(--font-family-poppins);
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
            color: var(--text-color-dark);
        }

        .main-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* New layout: left side is content, right is sidebar */
        .content {
            flex-grow: 1;
            padding: 20px;
            width: 70%;
        }

        .sidebar {
            width: 30%;
            background-color: #fff;
            padding: 20px;
            box-shadow: -2px 0 6px rgba(0,0,0,0.1); /* Shadow on left side */
            position: sticky;
            top: 0;
            overflow-y: auto;
        }

        h1, h2 {
            font-size: 2rem;
            color: var(--primary-color);
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
            margin-top: 0;
        }

        h2 {
            font-size: 1.5rem;
        }

        .table-container, .chart-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        /* Specific container for the right sidebar's content */
        .sidebar-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
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
            color: white;
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
        }
        
        .btn {
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .btn:hover {
            background-color: var(--secondary-color);
        }

        .rate-increase {
            color: #28a745; /* green */
            font-weight: 600;
        }

        .rate-decrease {
            color: #dc3545; /* red */
            font-weight: 600;
        }
        
        .rate-stable {
            color: #6c757d; /* grey */
        }
        
        .rate-change-icon {
            font-weight: bold;
            font-size: 0.9em;
            margin-left: 5px;
        }

        .filter-section {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="main-container">

    <div class="content">
        <h1>Waste Rate Change History</h1>
        
        <div class="filter-section">
            <label for="wasteTypeFilter">Select Waste Type:</label>
            <select id="wasteTypeFilter" onchange="updateHistory()">
                <?php foreach ($current_rates as $rate): ?>
                    <option value="<?= htmlspecialchars($rate['waste_type']) ?>"><?= htmlspecialchars($rate['waste_type']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="chart-container">
            <h2>Rate Change Chart</h2>
            <canvas id="rateChart"></canvas>
        </div>
    </div>

    <div class="sidebar">
        <div class="sidebar-content">
            <div class="current-rates-section">
                <h2>Current Rates</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Waste Type</th>
                            <th>Rate (Rs./kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($current_rates) === 0): ?>
                            <tr><td colspan="2" class="no-data">No current rates found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($current_rates as $rate): ?>
                                <tr>
                                    <td><?= htmlspecialchars($rate['waste_type']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($rate['rate_per_kg']) ?>
                                        <?php if ($rate['previous_rate'] !== null): ?>
                                            <?php if ($rate['rate_per_kg'] > $rate['previous_rate']): ?>
                                                <span class="rate-change-icon rate-increase">&#x25B2;</span>
                                            <?php elseif ($rate['rate_per_kg'] < $rate['previous_rate']): ?>
                                                <span class="rate-change-icon rate-decrease">&#x25BC;</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="recent-changes-section">
                <h2>Recent Changes</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Waste Type</th>
                            <th>New Rate</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_changes) === 0): ?>
                            <tr><td colspan="3" class="no-data">No recent changes.</td></tr>
                        <?php else: ?>
                            <?php 
                            $prev_rates_lookup = [];
                            foreach ($current_rates as $rate) {
                                $prev_rates_lookup[$rate['waste_type']] = $rate['previous_rate'];
                            }
                            
                            foreach ($recent_changes as $change): 
                                $status = 'Stable';
                                $icon = '&#x25C9;'; // circle
                                $class = 'rate-stable';

                                if (isset($prev_rates_lookup[$change['waste_type']])) {
                                    if ($change['rate_per_kg'] > $prev_rates_lookup[$change['waste_type']]) {
                                        $status = 'Increased';
                                        $icon = '&#x25B2;'; // up arrow
                                        $class = 'rate-increase';
                                    } elseif ($change['rate_per_kg'] < $prev_rates_lookup[$change['waste_type']]) {
                                        $status = 'Decreased';
                                        $icon = '&#x25BC;'; // down arrow
                                        $class = 'rate-decrease';
                                    }
                                }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($change['waste_type']) ?></td>
                                <td><?= htmlspecialchars($change['rate_per_kg']) ?></td>
                                <td class="<?= $class ?>">
                                    <span class="rate-change-icon"><?= $icon ?></span>
                                    <?= $status ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="actions">
                <a href="newupdate.php?view=dashboard" class="btn">Back to Dashboard</a>
            </div>
        </div>
    </div>
</div>

<script>
    const wasteTypeFilter = document.getElementById('wasteTypeFilter');
    const chartCanvas = document.getElementById('rateChart');
    let rateChart;

    const currentRates = <?= json_encode(array_column($current_rates, 'rate_per_kg', 'waste_type')) ?>;

    async function fetchHistory(wasteType) {
        try {
            const response = await fetch(`rate_history.php?waste_type=${encodeURIComponent(wasteType)}`);
            const data = await response.json();
            return data.history;
        } catch (error) {
            console.error('Error fetching data:', error);
            return [];
        }
    }

    function createChart(history) {
        const labels = history.map(item => {
            const date = new Date(item.updated_at);
            return date.toLocaleDateString();
        });
        const data = history.map(item => item.rate_per_kg);

        if (rateChart) {
            rateChart.destroy();
        }

        rateChart = new Chart(chartCanvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Rate per kg (Rs.)',
                    data: data,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Rate (Rs./kg)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });
    }

    async function updateHistory() {
        const selectedWasteType = wasteTypeFilter.value;
        const historyData = await fetchHistory(selectedWasteType);
        createChart(historyData);
    }

    // Initial load
    if (wasteTypeFilter.options.length > 0) {
        updateHistory();
    }
</script>
</body>
</html>