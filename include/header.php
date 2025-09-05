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
        --primary-color: #2C7B43;
        --secondary-color: #B7E45E;
        --accent-color: #A0D2DB;
        --text-color-light: #ffffff;
        --text-color-dark: #333333;
        --font-family-poppins: 'Poppins', sans-serif;
    }

    .main-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, #1e5a2e 100%);
        padding: 12px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        z-index: 10;
        position: relative;
        border-bottom: 3px solid var(--accent-color);
    }

    .main-header .logo-container {
        display: flex;
        align-items: center;
        text-decoration: none;
        transition: transform 0.3s ease;
    }

    .main-header .logo-container:hover {
        transform: scale(1.05);
        filter: brightness(1.1);
    }

    .main-header .logo-img {
        height: 60px;
        margin-right: 15px;
    }

    .company-name {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-color-light);
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        letter-spacing: 0.5px;
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
        transition: all 0.3s ease;
        padding: 8px 16px;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
    }

    .main-header nav a:hover {
        color: var(--accent-color);
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .nav-username {
        font-weight: 600;
        margin-right: 15px;
        color: var(--accent-color);
        background: rgba(160, 210, 219, 0.2);
        padding: 6px 12px;
        border-radius: 20px;
        border: 1px solid rgba(160, 210, 219, 0.3);
        font-size: 0.9rem;
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .main-header {
            padding: 10px 15px;
            flex-wrap: wrap;
        }
        
        .main-header nav {
            margin-top: 10px;
            width: 100%;
            justify-content: center;
        }
        
        .main-header nav a {
            margin: 0 8px;
            font-size: 0.9rem;
            padding: 6px 12px;
        }
        
        .company-name {
            font-size: 1.5rem;
        }
        
        .main-header .logo-img {
            height: 50px;
        }
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
            <a href="<?php echo $basePath; ?>/user_dashboard.php?page=new_pickup">Request Pickup</a>
            <a href="<?php echo $basePath; ?>/user_dashboard.php?page=pickup_history">My Requests</a>
        <?php endif; ?>
        <?php if ($userRole !== 'guest'): ?>
            <a href="<?php echo $basePath; ?>/logout.php">Logout</a>
        <?php else: ?>
            <a href="<?php echo $basePath; ?>/login.php">Login</a>
        <?php endif; ?>
    </nav>
</header>