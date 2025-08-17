<?php
// add_waste_rate.php
session_start();

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
include "include/connect.php";

$success = $error = "";

// Handle deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM waste_rates WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $success = "Waste type deleted successfully.";
    } else {
        $error = "Error deleting record.";
    }
    $stmt->close();
}

// Handle addition
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $waste_type = trim($_POST['waste_type']);
    $rate_per_kg = trim($_POST['rate_per_kg']);

    if (empty($waste_type) || !is_numeric($rate_per_kg)) {
        $error = "Please enter a valid waste type and numeric rate.";
    } else {
        // Check for duplicates
        $check = $conn->prepare("SELECT id FROM waste_rates WHERE waste_type = ?");
        $check->bind_param("s", $waste_type);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "This waste type already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO waste_rates (waste_type, rate_per_kg) VALUES (?, ?)");
            $stmt->bind_param("sd", $waste_type, $rate_per_kg);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Waste type and rate added successfully!";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            } else {
                $error = "Database error: " . $conn->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// Load session success message
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Fetch data
$wasteData = [];
$result = $conn->query("SELECT * FROM waste_rates ORDER BY updated_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $wasteData[] = $row;
    }
}
?>
<?php include "include/header.php"; ?>
<?php include "include/sidebar.php"; ?>

<style>
html, body {
    height: 100%;
    margin: 0;
}
body {
    font-family: 'Poppins', sans-serif;
    background: #f1f8f4;
    display: flex;
    flex-direction: column;
}
.main-content {
    flex: 1;
    padding: 30px;
}
.container {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
}
.table-container, .form-container {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.table-container { flex: 1 1 60%; }
.form-container { flex: 1 1 35%; }
h2 { color: #2e7d32; }
label { display: block; margin-top: 15px; }
input[type="text"], input[type="number"] {
    width: 100%;
    padding: 10px;
    margin-top: 5px;
    border: 1px solid #ccc;
    border-radius: 5px;
}
button {
    margin-top: 20px;
    background-color: #2e7d32;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
}
.message { color: green; margin-top: 10px; }
.error { color: red; }
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
th, td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
}
th { background-color: #e8f5e9; color: #2e7d32; }
.update-btn, .delete-btn {
    padding: 6px 10px;
    border-radius: 4px;
    color: white;
    text-decoration: none;
}
.update-btn { background-color: #388e3c; }
.delete-btn { background-color: #d32f2f; }
</style>

<div class="main-content">
    <div class="container">
        <!-- Left: Table -->
        <div class="table-container">
            <h2>Current Waste Rates</h2>
            <?php if ($success): ?><p class="message"><?= htmlspecialchars($success) ?></p><?php endif; ?>
            <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>Waste Type</th>
                        <th>Rate (NPR/KG)</th>
                        <th>Last Updated</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($wasteData)): ?>
                    <?php foreach ($wasteData as $waste): ?>
                        <tr>
                            <td><?= htmlspecialchars($waste['waste_type']); ?></td>
                            <td><?= htmlspecialchars($waste['rate_per_kg']); ?></td>
                            <td><?= htmlspecialchars($waste['updated_at']); ?></td>
                            <td>
                                <a href="update_waste.php?id=<?= $waste['id']; ?>" class="update-btn">Update</a>
                                <a href="?delete_id=<?= $waste['id']; ?>" class="delete-btn" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">No data available.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Right: Form -->
        <div class="form-container">
            <h2>Add Waste Type & Rate</h2>
            <form method="POST">
                <label for="waste_type">Waste Type:</label>
                <input type="text" id="waste_type" name="waste_type" required>

                <label for="rate_per_kg">Rate per KG (NPR):</label>
                <input type="number" step="0.01" id="rate_per_kg" name="rate_per_kg" required>

                <button type="submit">Add Waste Rate</button>
            </form>
        </div>
    </div>
</div>

<?php include "include/footer.php"; ?>
