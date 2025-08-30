<?php
// PHP logic from your original file. No changes were made to the core functionality.
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

    // Fetch ALL history for the selected waste type
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
    <title>Waste Rates & History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 font-inter text-gray-800 antialiased">
    <div class="min-h-screen flex flex-col md:flex-row p-6 md:p-10 gap-8">
        <!-- Content Section -->
        <div class="flex-1 bg-white rounded-2xl shadow-xl p-8 transition-all duration-300">
            <h1 class="text-4xl font-bold text-green-700 mb-6 border-b-2 pb-4 border-gray-200 flex items-center">
                Waste Rate History
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 ml-3 text-green-500">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h11.25m-6.75-9.75 9.75-9.75M5.25 6h.75m1.5 0h.75m1.5 0h.75m1.5 0h.75M12 9v3.75m0 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0 3 0m-3 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0 3 0m-3 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0 3 0m-3 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0 3 0m-3 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0 3 0m-3 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0 3 0m-3 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0 3 0" />
                </svg>
            </h1>
            <div class="mb-8 p-6 bg-green-50 rounded-xl border border-green-200">
                <label for="wasteTypeFilter" class="block text-lg font-medium text-green-700 mb-2">Select Waste Type:</label>
                <select id="wasteTypeFilter" class="w-full md:w-1/2 p-3 rounded-lg border border-green-300 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 bg-white">
                    <?php foreach ($current_rates as $rate): ?>
                        <option value="<?= htmlspecialchars($rate['waste_type']) ?>"><?= htmlspecialchars($rate['waste_type']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="w-full h-96">
                <canvas id="rateChart"></canvas>
            </div>
        </div>
        <!-- Sidebar Section -->
        <div class="w-full md:w-1/3 flex flex-col gap-8">
            <!-- Current Rates Card -->
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h2 class="text-2xl font-semibold text-green-700 mb-4 border-b pb-2">Current Rates</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left table-auto min-w-full">
                        <thead class="bg-green-700 text-white rounded-xl">
                            <tr>
                                <th class="py-3 px-4 font-semibold rounded-tl-xl">Waste Type</th>
                                <th class="py-3 px-4 font-semibold rounded-tr-xl">Rate (Rs./kg)</th>
                            </tr>
                        </thead>
                        <tbody id="currentRatesTableBody" class="divide-y divide-gray-200">
                            <?php if (count($current_rates) === 0): ?>
                                <tr><td colspan="2" class="text-center text-gray-500 py-4">No current rates found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($current_rates as $rate): ?>
                                    <tr>
                                        <td class="py-3 px-4 border-b border-gray-200"><?= htmlspecialchars($rate['waste_type']) ?></td>
                                        <td class="py-3 px-4 border-b border-gray-200">
                                            Rs. <?= htmlspecialchars($rate['rate_per_kg']) ?>
                                            <?php if ($rate['previous_rate'] !== null): ?>
                                                <?php if ($rate['rate_per_kg'] > $rate['previous_rate']): ?>
                                                    <span class="text-green-600 font-semibold ml-2 text-xs">▲</span>
                                                <?php elseif ($rate['rate_per_kg'] < $rate['previous_rate']): ?>
                                                    <span class="text-red-600 font-semibold ml-2 text-xs">▼</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Changes Card -->
            <div class="bg-white rounded-2xl shadow-xl p-6">
                <h2 class="text-2xl font-semibold text-green-700 mb-4 border-b pb-2">Recent Changes</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left table-auto min-w-full">
                        <thead class="bg-green-700 text-white rounded-xl">
                            <tr>
                                <th class="py-3 px-4 font-semibold rounded-tl-xl">Waste Type</th>
                                <th class="py-3 px-4 font-semibold">New Rate</th>
                                <th class="py-3 px-4 font-semibold rounded-tr-xl">Status</th>
                            </tr>
                        </thead>
                        <tbody id="recentChangesTableBody" class="divide-y divide-gray-200">
                            <?php if (count($recent_changes) === 0): ?>
                                <tr><td colspan="3" class="text-center text-gray-500 py-4">No recent changes.</td></tr>
                            <?php else: ?>
                                <?php
                                $prev_rates_lookup = [];
                                foreach ($current_rates as $rate) {
                                    $prev_rates_lookup[$rate['waste_type']] = $rate['previous_rate'];
                                }
                                foreach ($recent_changes as $change):
                                    $status = 'Stable';
                                    $icon = '';
                                    $class = 'text-gray-500';
                                    if (isset($prev_rates_lookup[$change['waste_type']])) {
                                        if ($change['rate_per_kg'] > $prev_rates_lookup[$change['waste_type']]) {
                                            $status = 'Increased';
                                            $icon = '▲';
                                            $class = 'text-green-600';
                                        } elseif ($change['rate_per_kg'] < $prev_rates_lookup[$change['waste_type']]) {
                                            $status = 'Decreased';
                                            $icon = '▼';
                                            $class = 'text-red-600';
                                        }
                                    }
                                ?>
                                <tr>
                                    <td class="py-3 px-4 border-b border-gray-200"><?= htmlspecialchars($change['waste_type']) ?></td>
                                    <td class="py-3 px-4 border-b border-gray-200">Rs. <?= htmlspecialchars($change['rate_per_kg']) ?></td>
                                    <td class="py-3 px-4 border-b border-gray-200 <?= $class ?> font-medium">
                                        <span class="mr-1"><?= $icon ?></span><?= $status ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Dashboard Link -->
            <div class="mt-6 text-center">
                <a href="new.php?page=dashboard" class="inline-block w-full py-3 px-6 bg-green-500 text-white font-bold rounded-full shadow-lg hover:bg-green-600 transition-colors duration-200">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    <script>
        const wasteTypeFilter = document.getElementById('wasteTypeFilter');
        const chartCanvas = document.getElementById('rateChart');
        let rateChart;

        async function fetchHistory(wasteType) {
            try {
                const response = await fetch(`rates.php?waste_type=${encodeURIComponent(wasteType)}`);
                const data = await response.json();
                return data.history;
            } catch (error) {
                console.error('Error fetching data:', error);
                return [];
            }
        }

        function createChart(history) {
            const labels = history.map(item => new Date(item.updated_at).toLocaleString());
            const data = history.map(item => parseFloat(item.rate_per_kg));

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
                            tension: 0.1,
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            fill: true,
                            pointBackgroundColor: 'rgb(75, 192, 192)',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgb(75, 192, 192)'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: false,
                                title: {
                                    display: true,
                                    text: 'Rate (Rs./kg)',
                                    font: {
                                        size: 14,
                                        weight: 'bold'
                                    }
                                },
                                grid: {
                                    color: 'rgba(200, 200, 200, 0.2)'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Date and Time',
                                    font: {
                                        size: 14,
                                        weight: 'bold'
                                    }
                                },
                                grid: {
                                    color: 'rgba(200, 200, 200, 0.2)'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    title: (tooltipItems) => tooltipItems[0].label,
                                    label: (tooltipItem) => `Rate: ${tooltipItem.raw} Rs./kg`
                                }
                            }
                        }
                    }
                });
            } else {
                // If there's no data, clear the canvas
                const ctx = chartCanvas.getContext('2d');
                ctx.clearRect(0, 0, chartCanvas.width, chartCanvas.height);
                // Display a "no data" message (optional)
                const noData = document.createElement('div');
                noData.className = 'text-center text-gray-500 py-4';
                noData.textContent = 'No historical data found for this waste type.';
                chartCanvas.parentNode.appendChild(noData);
            }
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
