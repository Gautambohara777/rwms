<?php
// sidebar.php
if (session_status() === PHP_SESSION_NONE) session_start();
$userRole = $_SESSION['user_role'] ?? 'guest';
?>
<style>
    .sidebar {
        height: 100vh;
        width: 225px;
        position: fixed;
        top: 0;
        left: -250px;
        background-color: #2e7d32;
        overflow-x: hidden;
        transition: left 0.3s ease;
        padding-top: 100px; /* space for logo */
        z-index: 999;
    }

    .sidebar-logo {
        position: fixed;
        top: 10px;
        left: 10px;
        width: 230px;
        height: 70px;
        display: flex;
        align-items: center;
        padding: 10px 15px;
        cursor: pointer;
        z-index: 1000;
        background-color: #2e7d32;
    }

    .sidebar a {
        padding: 15px 25px;
        text-decoration: none;
        font-size: 16px;
        color: #fff;
        display: block;
        transition: background-color 0.2s;
    }

    .sidebar a:hover {
        background-color: #1b5e20;
    }

    .sidebar-toggle {
        position: fixed;
        top: 20px;
        left: 20px;
        font-size: 24px;
        color: #fff;
        background-color: #2e7d32;
        border: none;
        padding: 4px 15px;
        cursor: pointer;
        z-index: 1001; /* above logo */
        border-radius: 4px;
    }

    /* Image container styling */
    .sidebar-image-container {
        padding: 15px;
        display: flex;
        justify-content: center;
    }

    .sidebar-image-container img {
        width: 180px;
        border-radius: 10px;
        display: block;
    }
</style>

<!-- Sidebar Toggle Button -->
<button class="sidebar-toggle" aria-label="Toggle Sidebar" onclick="toggleSidebar()">☰</button>

<!-- Sidebar -->
<div id="sidebar" class="sidebar">
    <?php if ($userRole === 'admin'): ?>
        <a href="admin_dashboard.php">Admin Dashboard</a>
      

    <?php elseif ($userRole === 'collector'): ?>
        <a href="pickup_schedule.php">Pickup Schedule</a>
        <a href="colldash.php">Collected Items</a>



    <?php elseif ($userRole === 'user'): ?>
        <a href="sell.php">Request Pickup</a>
        <a href="pickup_history.php">My Requests</a>


    <?php else: ?>
        <div class="sidebar-image-container">
            <img src="img/info.png" alt="Information" />
        </div>
        <a href="login.php">Login</a>
    <?php endif; ?>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById("sidebar");
        if (sidebar.style.left === "0px") {
            sidebar.style.left = "-250px";
        } else {
            sidebar.style.left = "0px";
        }
    }
</script>
