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

// Check if a specific waste type is requested via GET and handle the AJAX request for the chart
if (isset($_GET['waste_type'])) {
    header('Content-Type: application/json');
    $waste_type = mysqli_real_escape_string($db, $_GET['waste_type']);
    $stmt = $db->prepare("SELECT rate_per_kg, updated_at FROM waste_rate_history WHERE waste_type = ? ORDER BY updated_at ASC");
    $stmt->bind_param("s", $waste_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $history_data = [];
    while ($row = $result->fetch_assoc()) {
        $history_data[] = $row;
    }
    echo json_encode(['history' => $history_data]);
    exit();
}

// Fetch the most recent rate for each waste type from the waste_rate_history table for "Current Rates"
$current_rates = [];
$stmt_current = $db->prepare("SELECT waste_type, rate_per_kg FROM waste_rate_history WHERE (waste_type, updated_at) IN (SELECT waste_type, MAX(updated_at) FROM waste_rate_history GROUP BY waste_type) ORDER BY waste_type ASC");
$stmt_current->execute();
$result_current = $stmt_current->get_result();
if ($result_current) {
    while ($row = mysqli_fetch_assoc($result_current)) {
        // Now find the previous rate for comparison
        $previous_rate = null;
        $stmt_prev = $db->prepare("SELECT rate_per_kg FROM waste_rate_history WHERE waste_type = ? AND updated_at < (SELECT MAX(updated_at) FROM waste_rate_history WHERE waste_type = ?) ORDER BY updated_at DESC LIMIT 1");
        if ($stmt_prev) {
            $stmt_prev->bind_param("ss", $row['waste_type'], $row['waste_type']);
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

        .content {
            flex-grow: 1;
            padding: 20px;
            width: 70%;
        }

        .sidebar {
            width: 30%;
            background-color: #fff;
            padding: 20px;
            box-shadow: -2px 0 6px rgba(0,0,0,0.1);
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

        .table-container, .chart-container, .card-list-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            padding: 20px;
            margin-bottom: 20px;
        }

        .sidebar-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* TABS/TOGGLE STYLES */
        .sidebar-tabs {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
            background-color: #e9e9e9;
            border-radius: 10px;
            padding: 5px;
        }

        .tab-button {
            flex: 1;
            padding: 10px;
            border: none;
            background-color: transparent;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s, color 0.3s;
            border-radius: 8px;
            color: var(--text-color-dark);
        }

        .tab-button.active {
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* CARD LIST STYLES */
        .rate-card-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .rate-card {
            background-color: #f8f9fa;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .rate-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .rate-card-details h4 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--primary-color);
        }

        .rate-card-details p {
            margin: 5px 0 0;
            font-size: 0.9rem;
            color: #777;
        }
        
        .rate-card-details strong {
            font-size: 1.2rem;
            color: var(--text-color-dark);
        }

        .rate-change-indicator {
            display: flex;
            align-items: center;
            font-weight: 600;
        }

        .rate-change-icon {
            font-size: 1.2rem;
            margin-right: 5px;
        }
        
        .rate-increase {
            color: #28a745; /* green */
        }

        .rate-decrease {
            color: #dc3545; /* red */
        }

        .rate-stable {
            color: #6c757d; /* grey */
        }
        
        .recent-changes-table {
            width: 100%;
            border-collapse: collapse;
        }

        .recent-changes-table th, .recent-changes-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .recent-changes-table thead th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
        }

        .recent-changes-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .recent-changes-table tbody tr:hover {
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
            text-align: center;
        }

        .btn {
            background: var(--primary-color);
            color: white;
            padding: 12px 24px;
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
    </style>
</head>
<body>
<div class="main-container">

    <div class="content">
        <h1>Waste Rate Change History</h1>
        
        <div class="table-container">
            <div class="filter-section">
                <label for="wasteTypeFilter">Select Waste Type:</label>
                <select id="wasteTypeFilter">
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
    </div>

    <div class="sidebar">
        <div class="sidebar-content">
            <div class="sidebar-tabs">
                <button class="tab-button active" onclick="showTab('current-rates')">Current Rates</button>
                <button class="tab-button" onclick="showTab('recent-changes')">Recent Changes</button>
            </div>
            
            <div id="current-rates" class="tab-content active">
                <h2>Current Rates</h2>
                <?php if (count($current_rates) === 0): ?>
                    <p class="no-data">No current rates found.</p>
                <?php else: ?>
                    <ul class="rate-card-list">
                        <?php foreach ($current_rates as $rate): ?>
                            <li class="rate-card">
                                <div class="rate-card-details">
                                    <h4><?= htmlspecialchars($rate['waste_type']) ?></h4>
                                    <strong>Rs. <?= htmlspecialchars($rate['rate_per_kg']) ?>/kg</strong>
                                </div>
                                <div class="rate-change-indicator">
                                    <?php 
                                        $icon = '';
                                        $status = 'Stable';
                                        $class = 'rate-stable';
                                        if ($rate['previous_rate'] !== null) {
                                            if ($rate['rate_per_kg'] > $rate['previous_rate']) {
                                                $icon = '<i class="fas fa-arrow-up"></i>';
                                                $status = 'Increased';
                                                $class = 'rate-increase';
                                            } elseif ($rate['rate_per_kg'] < $rate['previous_rate']) {
                                                $icon = '<i class="fas fa-arrow-down"></i>';
                                                $status = 'Decreased';
                                                $class = 'rate-decrease';
                                            }
                                        } else {
                                            $icon = '<i class="fas fa-circle"></i>';
                                            $status = 'New';
                                        }
                                    ?>
                                    <span class="rate-change-icon <?= $class ?>"><?= $icon ?></span>
                                    <span><?= $status ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div id="recent-changes" class="tab-content">
                <h2>Recent Changes</h2>
                <?php if (count($recent_changes) === 0): ?>
                    <p class="no-data">No recent changes found.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="recent-changes-table">
                            <thead>
                                <tr>
                                    <th>Waste Type</th>
                                    <th>New Rate</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_changes as $change): 
                                    $status = 'Stable';
                                    $icon = '<i class="fas fa-circle"></i>';
                                    $class = 'rate-stable';
                                    
                                    // Find the rate from the history table that came immediately before this one
                                    $stmt_prev_hist = $db->prepare("SELECT rate_per_kg FROM waste_rate_history WHERE waste_type = ? AND updated_at < ? ORDER BY updated_at DESC LIMIT 1");
                                    $stmt_prev_hist->bind_param("ss", $change['waste_type'], $change['updated_at']);
                                    $stmt_prev_hist->execute();
                                    $res_prev_hist = $stmt_prev_hist->get_result();
                                    $prev_hist_rate = $res_prev_hist->fetch_assoc()['rate_per_kg'] ?? null;
                                    $stmt_prev_hist->close();

                                    if ($prev_hist_rate !== null) {
                                        if ($change['rate_per_kg'] > $prev_hist_rate) {
                                            $status = 'Increased';
                                            $icon = '<i class="fas fa-arrow-up"></i>';
                                            $class = 'rate-increase';
                                        } elseif ($change['rate_per_kg'] < $prev_hist_rate) {
                                            $status = 'Decreased';
                                            $icon = '<i class="fas fa-arrow-down"></i>';
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
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        });
        const data = history.map(item => item.rate_per_kg);

        if (rateChart) {
            rateChart.destroy();
        }
        
        if (labels.length > 0) {
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
                                text: 'Date and Time'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                title: (tooltipItems) => {
                                    return tooltipItems[0].label;
                                },
                                label: (tooltipItem) => {
                                    return `Rate: ${tooltipItem.raw} Rs./kg`;
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    async function updateHistory() {
        const selectedWasteType = wasteTypeFilter.value;
        const historyData = await fetchHistory(selectedWasteType);
        createChart(historyData);
    }

    function showTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });

        document.getElementById(tabId).classList.add('active');
        event.currentTarget.classList.add('active');
    }

    // Initial load
    document.addEventListener('DOMContentLoaded', () => {
        if (wasteTypeFilter.options.length > 0) {
            updateHistory();
        }
        wasteTypeFilter.addEventListener('change', updateHistory);
    });
</script>
</body>
</html>