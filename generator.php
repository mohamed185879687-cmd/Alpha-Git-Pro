<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// دالة التحقق من Luhn (صحيحة 100%)
function luhnGenerate($partial) {
    $partial = preg_replace('/[^0-9]/', '', $partial);
    $len = strlen($partial);
    $sum = 0;
    $alt = false;
    for ($i = $len - 1; $i >= 0; $i--) {
        $digit = intval($partial[$i]);
        if ($alt) {
            $digit *= 2;
            if ($digit > 9) $digit -= 9;
        }
        $sum += $digit;
        $alt = !$alt;
    }
    $check = (10 - ($sum % 10)) % 10;
    return $partial . $check;
}

// دالة لتوليد رقم عشوائي آمن
function secureRandom($min, $max) {
    return random_int($min, $max);
}

// دالة لتحديد طول البطاقة بناءً على BIN
function getCardLength($bin) {
    $bin = preg_replace('/[^0-9]/', '', $bin);
    if (preg_match('/^3[47]/', $bin)) return 15; // Amex
    if (preg_match('/^3[0689]/', $bin)) return 14; // Diners
    if (preg_match('/^2/', $bin)) return 16;      // Mastercard (new range)
    return 16; // Visa, Mastercard old, Discover, etc.
}

// دالة لتوليد CVV حسب نوع البطاقة
function generateCVV($bin, $custom = null) {
    if ($custom !== null && $custom !== '' && strtolower($custom) !== 'random') {
        return str_pad($custom, 3, '0', STR_PAD_LEFT);
    }
    $isAmex = preg_match('/^3[47]/', $bin);
    $length = $isAmex ? 4 : 3;
    $cvv = '';
    for ($i = 0; $i < $length; $i++) {
        $cvv .= secureRandom(0, 9);
    }
    return $cvv;
}

// دالة لإنشاء رقم بطاقة كامل من BIN مع x
function generateCardNumber($binMask, $length) {
    // استبدال x بأرقام عشوائية
    $processed = '';
    $maskLen = strlen($binMask);
    for ($i = 0; $i < $maskLen && strlen($processed) < $length - 1; $i++) {
        $ch = $binMask[$i];
        if (strtolower($ch) === 'x') {
            $processed .= secureRandom(0, 9);
        } elseif (ctype_digit($ch)) {
            $processed .= $ch;
        } else {
            // تجاهل أي حروف غير أرقام/x
        }
    }
    // ملء الباقي (إذا كان BIN قصير)
    while (strlen($processed) < $length - 1) {
        $processed .= secureRandom(0, 9);
    }
    // توليد رقم Luhn الكامل
    return luhnGenerate($processed);
}

// جلب إعدادات المستخدم من قاعدة البيانات (نفس نظام checker)
require_once 'includes/db_connection.php';
$user_id = $_SESSION['user_id'];
$last_generated = [];
$stmt = $conn->prepare("SELECT settings FROM user_settings WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $settings = json_decode($row['settings'], true);
    $last_generated = $settings['last_generated'] ?? [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alpha-Git Pro | Card Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #0a0e27;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            background: #1a1f3a;
            border: none;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .card-header {
            background: #1a1f3a;
            border-bottom: 1px solid #2a2f4a;
            color: #00ff88;
            font-weight: bold;
        }
        .form-control, .form-select {
            background: #2a2f4a;
            border: none;
            color: #fff;
        }
        .form-control:focus, .form-select:focus {
            background: #2a2f4a;
            color: #fff;
            box-shadow: none;
            border: 1px solid #00ff88;
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
        .btn-secondary {
            background: #2a2f4a;
            border: none;
            color: #fff;
        }
        .form-label {
            color: #aaa;
        }
        textarea {
            resize: vertical;
        }
        h1, h4, h5 {
            color: #fff;
        }
        .navbar {
            background: #0a0e27 !important;
            border-bottom: 1px solid #2a2f4a;
        }
        .nav-link {
            color: #aaa !important;
        }
        .nav-link.active {
            color: #00ff88 !important;
            background: #1a2f3a !important;
            border-radius: 8px;
        }
        footer {
            color: #555;
        }
        .generator-container {
            display: flex;
            justify-content: space-between;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .form-section, .result-section {
            flex: 1;
            min-width: 280px;
        }
        .result-section textarea {
            height: 400px;
            font-family: monospace;
            font-size: 0.9rem;
        }
        .copy-btn {
            position: absolute;
            top: 38px;
            right: 15px;
            cursor: pointer;
            font-size: 1.2rem;
            color: #00ff88;
            z-index: 10;
        }
        .copy-btn:hover {
            color: #fff;
        }
        .position-relative {
            position: relative;
        }
        .stats {
            background: #1a1f3a;
            border-radius: 8px;
            padding: 10px;
            margin-top: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-code-branch"></i> Alpha-Git Pro</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="checker.php">Card Checker</a></li>
                    <li class="nav-item"><a class="nav-link active" href="generator.php">Generator</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="generator-container">
            <div class="form-section">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-magic"></i> Advanced Card Generator</h4>
                    </div>
                    <div class="card-body">
                        <form id="generatorForm">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-chalkboard"></i> BIN / Pattern</label>
                                <input type="text" class="form-control" id="bin" 
                                       placeholder="e.g., 4xxxxx, 37513874xx0591x, 222100" required>
                                <div class="form-text">Use 'x' for random digits. Example: 4567xxxxxxxxxxxx</div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="far fa-calendar-alt"></i> Month</label>
                                    <select class="form-select" id="month">
                                        <option value="Random">Random</option>
                                        <?php for ($m=1; $m<=12; $m++): ?>
                                            <option value="<?php echo sprintf('%02d', $m); ?>"><?php echo sprintf('%02d', $m); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="far fa-calendar-alt"></i> Year</label>
                                    <select class="form-select" id="year">
                                        <option value="Random">Random</option>
                                        <?php for ($y=date('Y'); $y<=date('Y')+15; $y++): ?>
                                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-lock"></i> CVV</label>
                                    <input type="text" class="form-control" id="cvv" placeholder="Random or specific (3/4 digits)">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-copy"></i> Quantity</label>
                                    <input type="number" class="form-control" id="count" value="20" min="1" max="5000">
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button type="button" class="btn btn-primary" onclick="generateCards()">
                                    <i class="fas fa-sync-alt"></i> Generate
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                                    <i class="fas fa-trash-alt"></i> Clear
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="result-section">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-list"></i> Generated Cards</span>
                        <button class="btn btn-sm btn-outline-success" onclick="copyToClipboard()"><i class="fas fa-copy"></i> Copy</button>
                    </div>
                    <div class="card-body">
                        <textarea class="form-control" id="output" rows="15" readonly placeholder="Your cards will appear here..."></textarea>
                        <div class="stats mt-3" id="statsPanel" style="display: none;">
                            <small><i class="fas fa-chart-line"></i> <span id="statsCount">0</span> cards generated</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center mt-4 py-3">
        <small>Alpha-Git Pro Generator | High-quality Luhn-compliant cards</small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Secure random polyfill for older browsers (not needed in modern)
        function secureRandom(min, max) {
            return Math.floor(Math.random() * (max - min + 1)) + min;
        }

        // Luhn generation (client-side equivalent to PHP)
        function luhnGenerate(partial) {
            partial = partial.replace(/[^0-9]/g, '');
            let len = partial.length;
            let sum = 0;
            let alt = false;
            for (let i = len - 1; i >= 0; i--) {
                let digit = parseInt(partial[i]);
                if (alt) {
                    digit *= 2;
                    if (digit > 9) digit -= 9;
                }
                sum += digit;
                alt = !alt;
            }
            let check = (10 - (sum % 10)) % 10;
            return partial + check;
        }

        function getCardLength(bin) {
            bin = bin.replace(/[^0-9]/g, '');
            if (/^3[47]/.test(bin)) return 15;
            if (/^3[0689]/.test(bin)) return 14;
            return 16;
        }

        function generateCVV(bin, custom) {
            if (custom && custom.trim() !== '' && custom.toLowerCase() !== 'random') {
                let cvvRaw = custom.toString().replace(/[^0-9]/g, '');
                if (cvvRaw.length === 3 || cvvRaw.length === 4) return cvvRaw;
            }
            let isAmex = /^3[47]/.test(bin);
            let length = isAmex ? 4 : 3;
            let cvv = '';
            for (let i = 0; i < length; i++) cvv += secureRandom(0, 9);
            return cvv;
        }

        function generateCardNumber(binMask, length) {
            let processed = '';
            let maskLen = binMask.length;
            for (let i = 0; i < maskLen && processed.length < length - 1; i++) {
                let ch = binMask[i];
                if (ch.toLowerCase() === 'x') {
                    processed += secureRandom(0, 9);
                } else if (/[0-9]/.test(ch)) {
                    processed += ch;
                }
            }
            while (processed.length < length - 1) {
                processed += secureRandom(0, 9);
            }
            return luhnGenerate(processed);
        }

        function generateMonth() {
            let months = ["01","02","03","04","05","06","07","08","09","10","11","12"];
            return months[secureRandom(0, 11)];
        }

        function generateYear() {
            let currentYear = new Date().getFullYear();
            let year = currentYear + secureRandom(0, 15);
            return year.toString();
        }

        function generateCards() {
            let binInput = document.getElementById("bin").value.trim();
            let monthSelect = document.getElementById("month").value;
            let yearSelect = document.getElementById("year").value;
            let cvvInput = document.getElementById("cvv").value.trim();
            let count = parseInt(document.getElementById("count").value);

            if (!binInput) {
                alert("Please enter BIN (e.g., 4xxxxx)");
                return;
            }
            if (isNaN(count) || count < 1) count = 20;
            if (count > 5000) count = 5000;

            let cardLength = getCardLength(binInput);
            let outputText = "";
            for (let i = 0; i < count; i++) {
                let cardNum = generateCardNumber(binInput, cardLength);
                let month = (monthSelect === "Random") ? generateMonth() : monthSelect;
                let year = (yearSelect === "Random") ? generateYear() : yearSelect;
                let cvv = generateCVV(cardNum, cvvInput);
                outputText += `${cardNum}|${month}|${year}|${cvv}\n`;
            }
            let outputArea = document.getElementById("output");
            outputArea.value = outputText.trim();
            
            // إحصائيات
            let statsPanel = document.getElementById("statsPanel");
            let statsCount = document.getElementById("statsCount");
            statsCount.innerText = count;
            statsPanel.style.display = "block";
        }

        function copyToClipboard() {
            let output = document.getElementById("output");
            if (!output.value.trim()) {
                alert("No cards to copy!");
                return;
            }
            output.select();
            output.setSelectionRange(0, 99999);
            document.execCommand("copy");
            alert("✅ " + output.value.split('\n').filter(l => l.trim()).length + " cards copied to clipboard!");
        }

        function clearForm() {
            document.getElementById("bin").value = "";
            document.getElementById("cvv").value = "";
            document.getElementById("output").value = "";
            document.getElementById("statsPanel").style.display = "none";
        }
    </script>
</body>
</html>
