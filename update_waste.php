<?php
// update_waste.php
session_start();
include "../include/connect.php"; // Adjust path if needed

$success = $error = "";

// Step 1: Get ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid waste item ID.");
}

$id = (int) $_GET['id'];

// Step 2: Fetch existing record
$stmt = $conn->prepare("SELECT * FROM waste_rates WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$waste = $result->fetch_assoc();
$stmt->close();

if (!$waste) {
    die("Waste record not found.");
}

// Step 3: Update form submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $waste_type = trim($_POST['waste_type']);
    $rate_per_kg = trim($_POST['rate_per_kg']);

    if (empty($waste_type) || !is_numeric($rate_per_kg)) {
        $error = "Please enter a valid waste type and numeric rate.";
    } else {
        $stmt = $conn->prepare("UPDATE waste_rates SET waste_type = ?, rate_per_kg = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sdi", $waste_type, $rate_per_kg, $id);

        if ($stmt->execute()) {
            $success = "Waste type and rate updated successfully!";
            // Refresh the data
            $waste['waste_type'] = $waste_type;
            $waste['rate_per_kg'] = $rate_per_kg;
        } else {
            $error = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Waste Rate</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f1f8f4;
            padding: 30px;
        }
        .form-container {
            background: white;
            max-width: 500px;
            margin: auto;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #2e7d32;
        }
        label {
            display: block;
            margin-top: 15px;
        }
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
            font-size: 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        .message {
            margin-top: 10px;
            color: green;
        }
        .error {
            color: red;
        }
        .back-link {
            margin-top: 20px;
            display: inline-block;
            color: #2e7d32;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Update Waste Type & Rate</h2>

    <?php if ($success): ?>
        <p class="message"><?php echo $success; ?></p>
    <?php elseif ($error): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="POST">
        <label for="waste_type">Waste Type:</label>
        <input type="text" id="waste_type" name="waste_type" value="<?php echo htmlspecialchars($waste['waste_type']); ?>" required>

        <label for="rate_per_kg">Rate per KG (NPR):</label>
        <input type="number" step="0.01" id="rate_per_kg" name="rate_per_kg" value="<?php echo htmlspecialchars($waste['rate_per_kg']); ?>" required>

        <button type="submit">Update Waste Rate</button>
    </form>

    <a class="back-link" href="rate.php">&larr; Back to Rate Management</a>
</div>

</body>
</html>
