<?php
// header.php
if (session_status() === PHP_SESSION_NONE) session_start();
$userRole = $_SESSION['user_role'] ?? 'guest';
?>
<style>
    .main-header {
        background-color: #2e7d32;
        color: white;
        padding: 1px 50px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .main-header .logo {
        display: flex;
        align-items: center;
        cursor: pointer;
        text-decoration: none;
    }

    .main-header .logo img {
        height: 60px;
        margin-left: 20px;
        margin-right: 10px;
    }

    .company-name {
        font-size: 28px;
        font-weight: bold;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #ffffff;
        letter-spacing: 1px;
        background: linear-gradient(to right, #a8e063, #56ab2f);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .main-header nav a {
        color: white;
        text-decoration: none;
        margin-left: 25px;
        font-weight: 400;
        font-size: 16px;
    }

    .main-header nav a:hover {
        text-decoration: underline;
    }
</style>

<header class="main-header">
    <div style="display:flex; align-items:center;">
        <!-- Logo + Company Name -->
        <a href="home.php" class="logo" title="RecycleHub Home">
            <img src="img/logo.png" alt="RecycleHub Logo" />
            <span class="company-name">RecycleHub</span>
        </a>
    </div>

    <nav>
        <a href="home.php">Home</a>
        <a href="rates.php">Rates</a>
        <?php if (isset($_SESSION['user'])): ?>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
        <?php endif; ?>
    </nav>
</header>
