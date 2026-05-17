<?php
// navbar.php - الإصدار المصحح

// فقط ابدأ الجلسة إذا لم تكن بدأت بالفعل
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? '';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-code-branch"></i> Alpha-Git
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if($is_logged_in): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="checker.php">
                            <i class="fas fa-credit-card"></i> Card Checker
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="generator.php">
                            <i class="fas fa-cogs"></i> Generator
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="ethereumDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fab fa-ethereum"></i> Ethereum Tools
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="eth_wallet.php">
                                <i class="fas fa-wallet"></i> Wallet Generator
                            </a></li>
                            <li><a class="dropdown-item" href="eth_checker.php">
                                <i class="fas fa-search"></i> Address Checker
                            </a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if($is_logged_in): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user-circle"></i> Profile
                            </a></li>
                            <li><a class="dropdown-item" href="eth_wallet.php">
                                <i class="fab fa-ethereum"></i> Ethereum Tools
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>