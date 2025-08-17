<?php
// start_pickup.php
session_start();
include "connect.php"; // your DB connect file

// determine DB connection variable (support $con or $conn)
if (isset($con) && $con instanceof mysqli) {
    $db = $con;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} else {
    die("Database connection not found. Check connect.php.");
}

// Auth: only collectors
if (!isset($_SESSION['user']) || ($_SESSION['user_role'] ?? '') !== 'collector') {
    header("Location: login.php");
    exit();
}
$collector_id = (int) $_SESSION['user'];

// helper to check column existence
function col_exists($db, $table, $col) {
    $q = "SHOW COLUMNS FROM `$table` LIKE '" . mysqli_real_escape_string($db, $col) . "'";
    $r = mysqli_query($db, $q);
    return ($r && mysqli_num_rows($r) > 0);
}

// determine which assignee column to use
$assigneeCandidates = ['assigned_collector_id', 'assigned_to', 'assigned_collector'];
$assignee_col = null;
foreach ($assigneeCandidates as $c) {
    if (col_exists($db, 'pickup_requests', $c)) {
        $assignee_col = $c;
        break;
    }
}
if (!$assignee_col) {
    // fallback (best guess)
    $assignee_col = 'assigned_collector_id';
}

// determine address/location column
$addressCandidates = ['address', 'location', 'pickup_location'];
$address_col = null;
foreach ($addressCandidates as $c) {
    if (col_exists($db, 'pickup_requests', $c)) {
        $address_col = $c;
        break;
    }
}
if (!$address_col) $address_col = 'address';

// determine weight column
$weightCandidates = ['weight', 'quantity', 'kg'];
$weight_col = null;
foreach ($weightCandidates as $c) {
    if (col_exists($db, 'pickup_requests', $c)) {
        $weight_col = $c;
        break;
    }
}
if (!$weight_col) $weight_col = 'weight';

// determine pickup_date column
$pickupDateCandidates = ['pickup_date', 'date', 'scheduled_date'];
$pickup_date_col = null;
foreach ($pickupDateCandidates as $c) {
    if (col_exists($db, 'pickup_requests', $c)) {
        $pickup_date_col = $c;
        break;
    }
}
if (!$pickup_date_col) $pickup_date_col = 'pickup_date';

// handle "Start Now" POST - update selected pickups to "In Progress"
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_selected'])) {
    $selected = $_POST['selected_ids'] ?? [];
    // sanitize ids
    $ids = array_map('intval', $selected);
    $ids = array_filter($ids, function($v){ return $v > 0; });
    if (count($ids) === 0) {
        $message = "No pickups selected to start.";
    } else {
        $id_list = implode(',', $ids);
        // Update only rows that belong to this collector (safety)
        $update_sql = "UPDATE `pickup_requests` 
                       SET `status` = 'In Progress' 
                       WHERE id IN ($id_list) 
                         AND `$assignee_col` = $collector_id
                         AND (LOWER(COALESCE(status,'')) NOT IN ('completed','cancelled','collected','In Progress'))";
        if (mysqli_query($db, $update_sql)) {
            $message = "Selected pickups marked as 'In Progress'.";
        } else {
            $message = "Database error: " . mysqli_error($db);
        }
    }
}

// fetch assigned pickups (not completed/cancelled)
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
      AND (LOWER(COALESCE(pr.status,'')) NOT IN ('completed','cancelled','collected','In Progress','Refused'))
    ORDER BY pr.`{$pickup_date_col}` ASC, pr.id ASC
";
$stmt = mysqli_prepare($db, $select_sql);
if (!$stmt) {
    die("Prepare failed: " . mysqli_error($db));
}
mysqli_stmt_bind_param($stmt, "i", $collector_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res === false) {
    die("Query error: " . mysqli_error($db));
}
$rows = [];
while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Start Pickups</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<style>
    body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; padding:0; }
    .page { max-width:1100px; margin:28px auto; padding:18px; }
    h1 { color:#2e7d32; margin-bottom:14px; }
    .msg { padding:10px; background:#e8f8ee; border:1px solid #c7eed2; color:#175d2f; margin-bottom:14px; border-radius:6px; }
    .error { background:#fff0f0; border:1px solid #f0c2c2; color:#a33; }
    table { width:100%; border-collapse:collapse; background:#fff; border-radius:6px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.06); }
    th, td { padding:12px 10px; border-bottom:1px solid #eee; text-align:left; font-size:14px; }
    th { background:#2e7d32; color:white; font-weight:600; }
    tr:last-child td { border-bottom:none; }
    .center { text-align:center; }
    .actions { margin-top:14px; display:flex; gap:12px; align-items:center; }
    .btn { background:#2e7d32; color:#fff; padding:10px 16px; text-decoration:none; border-radius:6px; border:none; cursor:pointer; font-weight:600; }
    .btn.secondary { background:#6c757d; }
    .checkbox { width:18px; height:18px; }
    .no-data { padding:18px; background:#fff; border-radius:6px; border:1px solid #eee; color:#666; text-align:center; }
</style>
</head>
<body>

<?php include 'include/header.php'; ?>
<?php include 'include/sidebar.php'; ?>

<div class="page">
    <h1>Assigned Pickups</h1>

    <?php if ($message): ?>
        <div class="msg"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (count($rows) === 0): ?>
        <div class="no-data">No pickups currently assigned to you.</div>
    <?php else: ?>
        <form method="post" onsubmit="return confirmStart();">
            <input type="hidden" name="start_selected" value="1" />
            <table>
                <thead>
                    <tr>
                        <th style="width:38px;" class="center"><input type="checkbox" id="select_all" title="Select all" /></th>
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
                            <td class="center">
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
                <button type="submit" class="btn">Mark as In Progress</button>
                
            </div>
        </form>
    <?php endif; ?>
     <div class="actions">
    <a href="collector_dashboard.php" class="btn secondary">Back to Dashboard</a>
     </div>
</div>

<?php include 'include/footer.php'; ?>

<script>
    // simple select all toggle
    document.getElementById('select_all')?.addEventListener('change', function(e){
        const checked = e.target.checked;
        document.querySelectorAll('input[name="selected_ids[]"]').forEach(cb => cb.checked = checked);
    });

    function confirmStart() {
        const checked = document.querySelectorAll('input[name="selected_ids[]"]:checked').length;
        if (!checked) {
            alert('Please select at least one pickup to start.');
            return false;
        }
        return confirm('Mark ' + checked + ' pickup(s) as In Progress?');
    }
</script>
</body>
</html>
