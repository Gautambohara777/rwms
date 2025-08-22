<?php
// header.php
if (session_status() === PHP_SESSION_NONE) session_start();

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// Detect role & username safely
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    // Case 1: user stored as array
    $userRole = $_SESSION['user']['user_role'] ?? 'guest';
    $userName = $_SESSION['user']['username'] ?? '';
} else {
    // Case 2: stored separately
    $userRole = $_SESSION['user_role'] ?? 'guest';
    $userName = $_SESSION['username'] ?? '';
}

// Determine home link
switch ($userRole) {
    case 'admin':
        $homeLink = $basePath . '/admin_dashboard.php';
        break;
    case 'collector':
        $homeLink = $basePath . '/collector_dashboard.php';
        break;
    case 'user':
        $homeLink = $basePath . '/home.php';
        break;
    default:
        $homeLink = $basePath . '/home.php';
        break;
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');

    :root {
        --primary-color: #2e7d32;
        --secondary-color: #4CAF50;
        --accent-color: #a8e063;
        --text-color-light: #ffffff;
        --text-color-dark: #333;
        --font-family-poppins: 'Poppins', sans-serif;
    }

    body {
        font-family: var(--font-family-poppins);
        margin: 0;
        padding: 0;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .main-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: var(--text-color-light);
        padding: 10px 50px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        flex-shrink: 0;
    }

    .main-header .logo-container {
        display: flex;
        align-items: center;
        text-decoration: none;
        transition: transform 0.3s ease;
    }

    .main-header .logo-container:hover {
        transform: scale(1.05);
    }

    .main-header .logo-img {
        height: 60px;
        margin-right: 15px;
    }

    .company-name {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-color-light);
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
    }

    .main-header nav {
        display: flex;
        align-items: center;
    }

    .main-header nav a {
        color: var(--text-color-light);
        text-decoration: none;
        margin-left: 25px;
        font-weight: 600;
        font-size: 1rem;
        position: relative;
        transition: color 0.3s ease;
    }

    .main-header nav a::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: -5px;
        width: 100%;
        height: 2px;
        background-color: var(--accent-color);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }

    .main-header nav a:hover {
        color: var(--accent-color);
    }

    .main-header nav a:hover::after {
        transform: scaleX(1);
    }

    .nav-username {
        font-weight: 600;
        margin-right: 15px;
        color: var(--accent-color);
    }

    .content {
        flex: 1;
        padding: 20px;
    }

    footer {
        background: var(--primary-color);
        color: var(--text-color-light);
        text-align: center;
        padding: 15px;
        flex-shrink: 0;
    }
</style>

<header class="main-header">
    <div class="logo-container">
        <a href="<?php echo $homeLink; ?>" title="RecycleHub Home" class="logo-link" style="display: flex; align-items: center;">
            <img src="<?php echo $basePath; ?>/img/logo.png" alt="RecycleHub Logo" class="logo-img" />
            <span class="company-name">RecycleHub</span>
        </a>
    </div>

    <nav>
        <?php if ($userName): ?>
            <span class="nav-username">Hello, <?php echo htmlspecialchars($userName); ?>!</span>
        <?php endif; ?>

        <a href="<?php echo $homeLink; ?>">Home</a>

        <?php if ($userRole === 'user'): ?>
            <a href="<?php echo $basePath; ?>/sell.php">Request Pickup</a>
            <a href="<?php echo $basePath; ?>/pickup_history.php">My Requests</a>
            
        <?php endif; ?>

        <?php if ($userRole !== 'guest'): ?>
            <a href="<?php echo $basePath; ?>/logout.php">Logout</a>
        <?php else: ?>
            <a href="<?php echo $basePath; ?>/login.php">Login</a>
        <?php endif; ?>
    </nav>
</header>
