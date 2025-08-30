<?php
session_start();
include "connect.php"; // Assuming this file handles your database connection

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user'];

// --- Handle Form Submission (POST request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate form data
    $name = mysqli_real_escape_string($con, $_POST['name']);
    $waste_type = mysqli_real_escape_string($con, $_POST['waste_type']);
    $other_waste_type = isset($_POST['other_waste_type']) ? mysqli_real_escape_string($con, $_POST['other_waste_type']) : '';
    $weight = floatval($_POST['weight']);
    $pickup_date = mysqli_real_escape_string($con, $_POST['pickup_date']);
    $address = mysqli_real_escape_string($con, $_POST['address']);
    $latitude = floatval($_POST['latitude']);
    $longitude = floatval($_POST['longitude']);
    $rate_per_kg = floatval($_POST['rate_per_kg']);

    // If 'Other' is selected, use the custom waste type and set rate to 0
    if ($waste_type === 'Other') {
        $waste_type = $other_waste_type;
        $rate_per_kg = 0;
    }

    // Insert data into the pickup_requests table
    $insertQuery = "INSERT INTO pickup_requests
        (user_id, name, waste_type, rate, weight, pickup_date, address, latitude, longitude, status)
        VALUES
        ('$user_id', '$name', '$waste_type', '$rate_per_kg', '$weight', '$pickup_date', '$address', '$latitude', '$longitude', 'Pending')";

    $insert = mysqli_query($con, $insertQuery);

    if ($insert) {
        echo "<script>alert('Pickup request submitted successfully!'); window.location.href='pickup_history.php';</script>";
    } else {
        echo "Error: " . mysqli_error($con);
    }
} else {
    // --- Display the Form (GET request) ---
    // Fetch waste types and rates from the database
    $waste_options = [];
    $result = mysqli_query($con, "SELECT waste_type, rate_per_kg FROM waste_rates");
    while ($row = mysqli_fetch_assoc($result)) {
        $waste_options[] = $row;
    }

    include 'include/header.php';
    include 'include/sidebar.php';
?>
<title>Sell Waste - Recycle Hub</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
<style>
    form {
        width: 60%;
        margin: 30px auto;
        padding: 20px;
        background: #e8f5e9;
        border-radius: 8px;
        box-shadow: 0 0 10px #999;
    }

    label {
        display: block;
        margin-top: 15px;
        font-weight: bold;
    }

    input, select, textarea {
        width: 100%;
        padding: 10px;
        margin-top: 5px;
    }

    #map {
        height: 300px;
        margin-top: 15px;
    }

    .price-display {
        margin-top: 10px;
        font-weight: bold;
        color: green;
    }
</style>

<body>

<form action="" method="post">
    <h2>Waste Pickup Request</h2>

    <label>Your Name</label>
    <input type="text" name="name" required>

    <label>Waste Type</label>
    <select name="waste_type" id="wasteSelect" required>
        <option value="">-- Select Waste Type --</option>
        <?php foreach ($waste_options as $opt): ?>
            <option value="<?= $opt['waste_type'] ?>" data-rate="<?= $opt['rate_per_kg'] ?>"><?= $opt['waste_type'] ?></option>
        <?php endforeach; ?>
        <option value="Other">Other</option>
    </select>

    <div id="otherTypeBox" style="display:none;">
        <label>Enter Other Waste Type</label>
        <input type="text" name="other_waste_type" />
    </div>

    <div id="rateBox" class="price-display"></div>

    <label>Total Weight (kg)</label>
    <input type="number" step="0.01" name="weight" required>

    <label>Pickup Date</label>
    <input type="date" name="pickup_date" id="pickup_date" required min="<?= date('Y-m-d') ?>">

    <label>Pickup Address</label>
    <textarea name="address" required></textarea>

    <label>Select Location on Map</label>
    <div id="map"></div>

    <input type="hidden" name="latitude" id="latitude">
    <input type="hidden" name="longitude" id="longitude">
    <input type="hidden" name="rate_per_kg" id="rate_per_kg" value="0">

    <button type="submit" style="margin-top:20px;">Submit Request</button>
</form>

<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
<script>
    // Handle rate display and other input
    document.getElementById("wasteSelect").addEventListener("change", function () {
        const selected = this.options[this.selectedIndex];
        const rate = selected.getAttribute("data-rate");

        if (this.value === "Other") {
            document.getElementById("otherTypeBox").style.display = "block";
            document.getElementById("rateBox").innerText = "Rate: To be evaluated by collector";
            document.getElementById("rate_per_kg").value = 0;
        } else {
            document.getElementById("otherTypeBox").style.display = "none";
            document.getElementById("rateBox").innerText = "Rate: Rs. " + rate + " per kg";
            document.getElementById("rate_per_kg").value = rate;
        }
    });

    const map = L.map('map');
    const marker = L.marker([0, 0], { draggable: true }); // Initialize marker

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    function onLocationFound(e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        // Center the map on the user's location
        map.setView(e.latlng, 16);

        // Add the draggable marker at the current location
        marker.setLatLng(e.latlng).addTo(map);

        // Set the hidden input values
        document.getElementById("latitude").value = lat;
        document.getElementById("longitude").value = lng;
    }

    function onLocationError(e) {
        // Fallback to a default location if geolocation fails
        console.error("Geolocation failed:", e.message);
        const defaultLat = 27.7172; // Default latitude (e.g., Kathmandu)
        const defaultLng = 85.3240; // Default longitude

        map.setView([defaultLat, defaultLng], 13);
        marker.setLatLng([defaultLat, defaultLng]).addTo(map);
        document.getElementById("latitude").value = defaultLat;
        document.getElementById("longitude").value = defaultLng;
        alert("Could not get your current location. Please drag the marker to your address.");
    }

    // Request the user's current location
    map.on('locationfound', onLocationFound);
    map.on('locationerror', onLocationError);
    map.locate({ setView: false, maxZoom: 16 });

    // Update coordinates on drag
    marker.on('dragend', function (e) {
        const pos = marker.getLatLng();
        document.getElementById("latitude").value = pos.lat;
        document.getElementById("longitude").value = pos.lng;
    });
</script>

<?php
    include 'include/footer.php';
?>
</body>
</html>
<?php
}
?>