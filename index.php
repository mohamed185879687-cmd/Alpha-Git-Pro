<?php
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// إحصائيات البطاقات
$total_checks = 0;
$total_live = 0;
$total_die = 0;
$recent_card_activity = [];

// إحصائيات ETH
$total_eth_checks = 0;
$total_eth_balance = 0;
$recent_eth_activity = [];

// جلب إحصائيات البطاقات
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total, 
                                   SUM(CASE WHEN status = 'Live' THEN 1 ELSE 0 END) as live,
                                   SUM(CASE WHEN status = 'Die' THEN 1 ELSE 0 END) as die
                            FROM card_checks WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $total_checks = $stats['total'] ?? 0;
        $total_live = $stats['live'] ?? 0;
        $total_die = $stats['die'] ?? 0;
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Card stats error: " . $e->getMessage());
}

// جلب أحدث 5 بطاقات
try {
    $stmt = $conn->prepare("SELECT card_number, status, message, check_date FROM card_checks WHERE user_id = ? ORDER BY check_date DESC LIMIT 5");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $recent_card_activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Recent cards error: " . $e->getMessage());
}

// إحصائيات ETH (إذا كانت الجداول موجودة)
$table_check = $conn->query("SHOW TABLES LIKE 'eth_checks'");
if ($table_check && $table_check->num_rows > 0) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(balance_eth) as total_balance FROM eth_checks WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $eth_stats = $stmt->get_result()->fetch_assoc();
            $total_eth_checks = $eth_stats['total'] ?? 0;
            $total_eth_balance = $eth_stats['total_balance'] ?? 0;
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("ETH stats error: " . $e->getMessage());
    }
    
    try {
        $stmt = $conn->prepare("SELECT checked_address, balance_eth, check_date FROM eth_checks WHERE user_id = ? ORDER BY check_date DESC LIMIT 5");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $recent_eth_activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Recent ETH error: " . $e->getMessage());
    }
}

// عدد الأدوات النشطة
$active_tools = 4; // Card Checker, Generator, ETH Wallet, ETH Checker
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alpha-Git Pro | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: #0a0e27;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: #0a0e27 !important;
            border-bottom: 1px solid #2a2f4a;
        }
        .navbar-brand {
            color: #00ff88 !important;
            font-weight: bold;
        }
        .nav-link {
            color: #aaa !important;
        }
        .nav-link.active {
            color: #00ff88 !important;
            background: #1a2f3a;
            border-radius: 8px;
        }
        .card {
            background: #1a1f3a;
            border: none;
            border-radius: 12px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }
        .card-header {
            background: #1a1f3a;
            border-bottom: 1px solid #2a2f4a;
            color: #00ff88;
            font-weight: bold;
        }
        .card-title, .card-text, .display-4 {
            color: #fff;
        }
        .text-primary, .text-success, .text-info, .text-warning {
            color: #00ff88 !important;
        }
        .btn-primary {
            background: #00ff88;
            border: none;
            color: #0a0e27;
            font-weight: bold;
        }
        .btn-primary:hover {
            background: #00cc66;
        }
        .btn-outline-primary {
            border-color: #00ff88;
            color: #00ff88;
        }
        .btn-outline-primary:hover {
            background: #00ff88;
            color: #0a0e27;
        }
        .table {
            color: #fff;
        }
.fw-bold {
  font-weight: 700 !important;
  color: red;
}
        .table thead th {
            border-bottom-color: #2a2f4a;
            color: #00ff88;
        }
        .table td, .table th {
            border-color: #2a2f4a;
        }
        .badge {
            font-weight: 500;
            padding: 5px 10px;
        }
        .bg-success {
            background-color: #00ff88 !important;
            color: #0a0e27;
        }
        .bg-danger {
            background-color: #ff4466 !important;
        }
        .bg-warning {
            background-color: #ffaa00 !important;
            color: #0a0e27;
        }
        .bg-info {
            background-color: #17a2b8 !important;
        }
        footer {
            background: #0a0e27;
            border-top: 1px solid #2a2f4a;
            color: #555;
        }
        .quick-action-btn {
            transition: all 0.3s;
        }
        .quick-action-btn:hover {
            transform: scale(1.02);
        }
        .badge-online {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }
        .code {
            font-family: monospace;
            font-size: 0.85rem;
            background: #2a2f4a;
            padding: 2px 6px;
            border-radius: 6px;
            color: #00ff88;
        }
h6 {
  font-size: 1rem;
  color: aliceblue;
}
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-code-branch"></i> Alpha-Git Pro</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link active" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="checker.php"><i class="fas fa-credit-card"></i> Card Checker</a></li>
                    <li class="nav-item"><a class="nav-link" href="generator.php"><i class="fas fa-cogs"></i> Generator</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="ethDropdown" data-bs-toggle="dropdown"><i class="fab fa-ethereum"></i> ETH Tools</a>
                        <ul class="dropdown-menu dropdown-menu-dark">
                            <li><a class="dropdown-item" href="eth_wallet.php"><i class="fas fa-wallet"></i> Wallet Generator</a></li>
                            <li><a class="dropdown-item" href="eth_checker.php"><i class="fas fa-search"></i> Address Checker</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" data-bs-toggle="dropdown"><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></a>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Stats Cards -->
        <div class="row g-4">
            <div class="col-md-3">
                <div class="card text-center p-3">
                    <i class="fas fa-credit-card fa-2x text-primary mb-2"></i>
                    <h3 class="display-5 fw-bold"><?php echo $total_checks; ?></h3>
                    <p class="card-text">Total Checks</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center p-3">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <h3 class="display-5 fw-bold"><?php echo $total_live; ?></h3>
                    <p class="card-text">Live Cards</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center p-3">
                    <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                    <h3 class="display-5 fw-bold"><?php echo $total_die; ?></h3>
                    <p class="card-text">Dead Cards</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center p-3">
                    <i class="fab fa-ethereum fa-2x text-info mb-2"></i>
                    <h3 class="display-5 fw-bold"><?php echo $total_eth_checks; ?></h3>
                    <p class="card-text">ETH Checks</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><i class="fas fa-rocket"></i> Quick Actions</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3 col-sm-6">
                                <a href="checker.php" class="text-decoration-none">
                                    <div class="card bg-dark text-center p-3 quick-action-btn">
                                        <i class="fas fa-credit-card fa-2x text-primary mb-2"></i>
                                        <h6>Card Checker</h6>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <a href="generator.php" class="text-decoration-none">
                                    <div class="card bg-dark text-center p-3 quick-action-btn">
                                        <i class="fas fa-cogs fa-2x text-success mb-2"></i>
                                        <h6>Generator</h6>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <a href="eth_wallet.php" class="text-decoration-none">
                                    <div class="card bg-dark text-center p-3 quick-action-btn">
                                        <i class="fas fa-wallet fa-2x text-info mb-2"></i>
                                        <h6>ETH Wallet</h6>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <a href="eth_checker.php" class="text-decoration-none">
                                    <div class="card bg-dark text-center p-3 quick-action-btn">
                                        <i class="fas fa-search fa-2x text-warning mb-2"></i>
                                        <h6>ETH Checker</h6>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-credit-card"></i> Recent Card Checks</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr><th>Time</th><th>Card</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_card_activity): ?>
                                        <?php foreach ($recent_card_activity as $row): ?>
                                            <?php 
                                                $status_class = ($row['status'] == 'Live') ? 'success' : (($row['status'] == 'Die') ? 'danger' : 'warning');
                                                $card_short = substr($row['card_number'], 0, 12) . '...';
                                            ?>
                                            <tr>
                                                <td><small><?php echo date('H:i:s', strtotime($row['check_date'])); ?></small></td>
                                                <td><code class="code"><?php echo htmlspecialchars($card_short); ?></code></td>
                                                <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo $row['status']; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center text-muted py-3">No card checks yet</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 text-end">
                            <a href="checker.php" class="btn btn-sm btn-outline-primary">View All →</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><i class="fab fa-ethereum"></i> Recent ETH Checks</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr><th>Time</th><th>Address</th><th>Balance (ETH)</th></tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_eth_activity): ?>
                                        <?php foreach ($recent_eth_activity as $row): ?>
                                            <?php 
                                                $addr_short = substr($row['checked_address'], 0, 10) . '...';
                                                $balance_val = floatval($row['balance_eth'] ?? 0);
                                                $balance_class = ($balance_val > 0) ? 'success' : 'secondary';
                                            ?>
                                            <tr>
                                                <td><small><?php echo date('H:i:s', strtotime($row['check_date'])); ?></small></td>
                                                <td><code class="code"><?php echo htmlspecialchars($addr_short); ?></code></td>
                                                <td><span class="badge bg-<?php echo $balance_class; ?>"><?php echo number_format($balance_val, 6); ?> ETH</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center text-muted py-3">No ETH checks yet</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 text-end">
                            <a href="eth_checker.php" class="btn btn-sm btn-outline-primary">View All →</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="row mt-4 mb-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><i class="fas fa-server"></i> System Status</div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <i class="fas fa-database fa-2x text-primary"></i>
                                <h6 class="mt-2">Database</h6>
                                <span class="badge bg-success">Connected</span>
                            </div>
                            <div class="col-md-4">
                                <i class="fas fa-plug fa-2x text-success"></i>
                                <h6 class="mt-2">Card API <span class="badge badge-online bg-success">Online</span></h6>
                            </div>
                            <div class="col-md-4">
                                <i class="fab fa-ethereum fa-2x text-info"></i>
                                <h6 class="mt-2">ETH APIs <span class="badge bg-success">Online</span></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center py-4">
        <small>&copy; 2026 Alpha-Git Pro | High Performance Tools</small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
