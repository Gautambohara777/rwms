<?php
// update_pickup.php
session_start();
include "connect.php";

// Auth: collector only
if (!isset($_SESSION['user']) || ($_SESSION['user_role'] ?? '') !== 'collector') {
    header("Location: login.php");
    exit();
}

$id = (int)($_GET['id'] ?? 0);

// Fetch pickup details
$sql = "SELECT pr.*, u.name AS customer_name, u.phone 
        FROM pickup_requests pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.id=?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pickup = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$pickup) {
    die("Pickup not found!");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $collected_items = $_POST['collected_items'];
    $final_weight = (float)$_POST['final_weight'];
    $rate = (float)$_POST['rate'];

    // Always recalc on server (safe)
    $total_cost = $final_weight * $rate;

    $sql = "UPDATE pickup_requests 
            SET collected_items=?, final_weight=?, rate=?, total_cost=?, status='Collected'
            WHERE id=?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "sdddi", $collected_items, $final_weight, $rate, $total_cost, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("Location: collector_pickup.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Update Pickup</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f6faf6; margin: 0; padding: 20px; }
    .card { max-width: 500px; margin: auto; background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);}
    h2 { margin-bottom: 20px; color: #2e7d32; }
    label { display: block; margin: 10px 0 5px; font-weight: bold; }
    input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; }
    input[readonly] { background: #f0f0f0; }
    button { margin-top: 20px; padding: 10px; background: #2e7d32; color: white; border: none; border-radius: 6px; cursor: pointer; }
    button:hover { background: #256528; }
  </style>
</head>
<body>
  <div class="card">
    <h2>Update Pickup for <?= htmlspecialchars($pickup['customer_name']) ?> (<?= htmlspecialchars($pickup['phone']) ?>)</h2>
    <form method="POST" id="pickupForm">

      <label>Collected Items</label>
      <input type="text" name="collected_items" value="<?= htmlspecialchars($pickup['collected_items'] ?: $pickup['waste_type']) ?>" required>

      <label>Final Weight (kg)</label>
      <input type="number" step="0.01" name="final_weight" id="final_weight" value="<?= htmlspecialchars($pickup['final_weight'] ?: $pickup['weight']) ?>" required>

      <label>Rate (per kg)</label>
      <input type="number" step="0.01" name="rate" id="rate" 
             value="<?= $pickup['rate'] > 0 ? htmlspecialchars($pickup['rate']) : 0 ?>" 
             <?= $pickup['rate'] > 0 ? "readonly" : "" ?> required>

      <label>Total Cost</label>
      <!-- Display + send to server (readonly for UI, but PHP will recalc anyway) -->
      <input type="number" step="0.01" id="total_cost" name="total_cost_display" readonly>

      <button type="submit">Update & Save</button>
    </form>
  </div>

  <script>
    function calculateTotal() {
      let weight = parseFloat(document.getElementById("final_weight").value) || 0;
      let rate = parseFloat(document.getElementById("rate").value) || 0;
      let total = weight * rate;
      document.getElementById("total_cost").value = total.toFixed(2);
    }

    // Initial calculation
    calculateTotal();

    // Recalculate when weight or rate changes
    document.getElementById("final_weight").addEventListener("input", calculateTotal);
    document.getElementById("rate").addEventListener("input", calculateTotal);
  </script>
</body>
</html>
