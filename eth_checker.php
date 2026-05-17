<?php
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$conn->query("CREATE TABLE IF NOT EXISTS eth_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    checked_address VARCHAR(100) NOT NULL,
    balance_eth DECIMAL(20,8) DEFAULT 0,
    transactions INT DEFAULT 0,
    is_contract BOOLEAN DEFAULT FALSE,
    check_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alpha-Git Pro | ETH Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- QR Code library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0a0e27; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar { background: #0a0e27 !important; border-bottom: 1px solid #2a2f4a; }
        .navbar-brand { color: #00ff88 !important; font-weight: bold; }
        .nav-link { color: #aaa !important; }
        .nav-link.active { color: #00ff88 !important; background: #1a2f3a; border-radius: 8px; }
        .card { background: #1a1f3a; border: none; border-radius: 12px; margin-bottom: 20px; transition: transform 0.2s; }
        .card:hover { transform: translateY(-3px); }
        .card-header { background: #1a1f3a; border-bottom: 1px solid #2a2f4a; color: #00ff88; font-weight: bold; }
        .form-control, .input-group-text { background: #2a2f4a; border: none; color: #fff; }
        .form-control:focus { background: #2a2f4a; color: #fff; box-shadow: none; border: 1px solid #00ff88; }
        .btn-primary { background: #00ff88; border: none; color: #0a0e27; font-weight: bold; }
        .btn-primary:hover { background: #00cc66; }
        .btn-outline-primary { border-color: #00ff88; color: #00ff88; }
        .btn-outline-primary:hover { background: #00ff88; color: #0a0e27; }
        .btn-outline-success, .btn-outline-info, .btn-outline-warning, .btn-outline-danger {
            border-color: #2a2f4a; color: #aaa;
        }
        .btn-outline-success:hover, .btn-outline-info:hover, .btn-outline-warning:hover, .btn-outline-danger:hover {
            background: #2a2f4a; color: #fff;
        }
 h4 {
    font-size: 1.5rem;
    color: white;
  }
.text-muted {
  --bs-text-opacity: 1;
  color: rgba(255, 0, 0, 0.75) !important;
  font-size: larger;
  font-family: initial;
}

        .code { font-family: monospace; background: #2a2f4a; padding: 2px 6px; border-radius: 6px; color: #00ff88; }
        .balance-display { font-size: 1.8rem; font-weight: bold; color: #00ff88; }
        .address-box { background: #2a2f4a; border-radius: 10px; padding: 12px; word-break: break-all; font-family: monospace; color: #fff; }
        .qr-container { background: white; padding: 4px; border-radius: 10px; display: inline-block; margin-bottom: 10px; }
        footer { background: #0a0e27; border-top: 1px solid #2a2f4a; color: #555; }
        .toast-message { position: fixed; top: 80px; right: 20px; z-index: 9999; background: #1a1f3a; border-left: 4px solid #00ff88; color: #fff; padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.4); animation: fadeInOut 5s ease; }
        @keyframes fadeInOut { 0% { opacity: 0; transform: translateX(20px); } 10% { opacity: 1; transform: translateX(0); } 90% { opacity: 1; } 100% { opacity: 0; transform: translateX(20px); } }
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
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="checker.php"><i class="fas fa-credit-card"></i> Card Checker</a></li>
                    <li class="nav-item"><a class="nav-link" href="generator.php"><i class="fas fa-cogs"></i> Generator</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="ethDropdown" data-bs-toggle="dropdown"><i class="fab fa-ethereum"></i> ETH Tools</a>
                        <ul class="dropdown-menu dropdown-menu-dark">
                            <li><a class="dropdown-item" href="eth_wallet.php"><i class="fas fa-wallet"></i> Wallet Generator</a></li>
                            <li><a class="dropdown-item active" href="eth_checker.php"><i class="fas fa-search"></i> Address Checker</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?></a>
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
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><i class="fab fa-ethereum"></i> Check Ethereum Address</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Enter ETH Address</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="ethAddressInput" placeholder="0x...">
                                <button class="btn btn-primary" onclick="checkEthereumAddress()"><i class="fas fa-search"></i> Check</button>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6"><button class="btn btn-outline-primary w-100" onclick="setTestAddress()"><i class="fas fa-vial"></i> Use Test Address</button></div>
                            <div class="col-md-6"><button class="btn btn-outline-success w-100" id="generateWalletBtn"><i class="fas fa-plus-circle"></i> Generate Wallet</button></div>
                        </div>
                        <div id="checkResults"></div>
                    </div>
                </div>
                <div class="card mt-4">
                    <div class="card-header"><i class="fas fa-chart-line"></i> Live Market Data</div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4"><h6 class="text-muted">ETH Price</h6><h4 id="ethPrice">Loading...</h4></div>
                            <div class="col-md-4"><h6 class="text-muted">Market Cap</h6><h4 id="marketCap">Loading...</h4></div>
                            <div class="col-md-4"><h6 class="text-muted">24h Volume</h6><h4 id="volume24h">Loading...</h4></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Address Details</div>
                    <div class="card-body text-center">
                        <div id="addressQR" class="qr-container mx-auto mb-3" style="width:130px; height:130px;"></div>
                        <div id="addressDisplay" class="address-box mb-3"></div>
                        <div class="mb-3"><label class="form-label">Balance</label><div class="balance-display" id="balanceDisplay">0.0 ETH</div><small id="balanceUsd">$0.00</small></div>
                        <div class="mb-3"><label class="form-label">Transactions</label><div class="h4 text-white" id="txCount">0</div></div>
                        <div class="d-grid gap-2 mt-3">
                            <button class="btn btn-outline-info" onclick="refreshCurrentAddress()"><i class="fas fa-sync-alt"></i> Refresh</button>
                            <button class="btn btn-outline-warning" onclick="saveCurrentAddress()"><i class="fas fa-save"></i> Save Address</button>
                            <button class="btn btn-outline-primary" onclick="openEtherscan()"><i class="fab fa-ethereum"></i> View on Etherscan</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center py-4">
        <small>&copy; 2026 Alpha-Git Pro | Ethereum Tools powered by Ethers.js & Etherscan</small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/ethers@5.7.2/dist/ethers.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentAddress = '';
        let currentBalance = 0;
        let currentTxCount = 0;
        let currentIsContract = false;

        // Helper: show floating message
        function showMessage(msg, type = 'success') {
            const div = document.createElement('div');
            div.className = 'toast-message';
            div.innerHTML = msg;
            div.style.borderLeftColor = type === 'success' ? '#00ff88' : type === 'error' ? '#ff4466' : '#ffaa00';
            document.body.appendChild(div);
            setTimeout(() => div.remove(), 5000);
        }

        // Copy text
        window.copyText = function(text) {
            navigator.clipboard.writeText(text);
            showMessage('Copied!', 'success');
        };

        // Generate QR Code using QRCode.js
        function generateQRCode(elementId, text) {
            const element = document.getElementById(elementId);
            element.innerHTML = ''; // clear previous
            try {
                new QRCode(element, {
                    text: text,
                    width: 120,
                    height: 120,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            } catch(e) {
                element.innerHTML = `<div style="font-size:11px; color:#aaa;">QR error</div>`;
            }
        }

        // Get ETH price
        async function getETHPrice() {
            try {
                const res = await fetch('https://api.coingecko.com/api/v3/simple/price?ids=ethereum&vs_currencies=usd');
                const data = await res.json();
                return data.ethereum?.usd || 0;
            } catch(e) { return 0; }
        }

        // Fetch address info (balance, tx count)
        async function getAddressInfo(address) {
            try {
                const res = await fetch(`https://api.ethplorer.io/getAddressInfo/${address}?apiKey=freekey`);
                const data = await res.json();
                if (!data.error) {
                    const ethBalance = data.ETH?.balance || 0;
                    const txCount = data.countTxs || 0;
                    return { balance: ethBalance, transactions: txCount, isContract: !!data.contractInfo };
                }
            } catch(e) {}
            // Fallback to Etherscan
            try {
                const balanceRes = await fetch(`https://api.etherscan.io/api?module=account&action=balance&address=${address}&tag=latest`);
                const balanceData = await balanceRes.json();
                let ethBalance = 0;
                if (balanceData.status === '1') ethBalance = ethers.utils.formatEther(balanceData.result);
                const txRes = await fetch(`https://api.etherscan.io/api?module=account&action=txlist&address=${address}&startblock=0&endblock=99999999&sort=asc`);
                const txData = await txRes.json();
                let txCount = 0;
                if (txData.status === '1') txCount = txData.result.length;
                return { balance: parseFloat(ethBalance), transactions: txCount, isContract: false };
            } catch(e) {
                return { balance: 0, transactions: 0, isContract: false };
            }
        }

        // Main check function
        async function checkEthereumAddress() {
            const address = document.getElementById('ethAddressInput').value.trim();
            if (!address) return showMessage('Please enter an Ethereum address', 'warning');
            if (!ethers.utils.isAddress(address)) return showMessage('Invalid Ethereum address format', 'error');
            currentAddress = address;
            document.getElementById('checkResults').innerHTML = `<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Checking address...</p></div>`;
            try {
                const info = await getAddressInfo(address);
                const price = await getETHPrice();
                const usdValue = (info.balance * price).toFixed(2);
                currentBalance = info.balance;
                currentTxCount = info.transactions;
                currentIsContract = info.isContract;

                const resultsHtml = `
                    <div class="card mt-3">
                        <div class="card-body">
                            <div class="address-box mb-3">${address}</div>
                            <div class="row">
                                <div class="col-6"><div class="bg-dark p-2 rounded text-center"><small class="text-muted">ETH Balance</small><div class="h5 text-success">${info.balance.toFixed(6)} ETH</div><small>$${usdValue}</small></div></div>
                                <div class="col-6"><div class="bg-dark p-2 rounded text-center"><small class="text-muted">Transactions</small><div class="h5 text-white">${info.transactions}</div></div></div>
                            </div>
                            ${info.isContract ? '<div class="alert alert-info mt-3 mb-0">🔗 Smart Contract Address</div>' : ''}
                        </div>
                    </div>`;
                document.getElementById('checkResults').innerHTML = resultsHtml;
                updateSidebar(address, info.balance, info.transactions, usdValue);
                generateQRCode('addressQR', address);
                showMessage('Address checked successfully', 'success');
                saveCheckToDatabase(address, info.balance, info.transactions, info.isContract);
            } catch(err) {
                document.getElementById('checkResults').innerHTML = `<div class="alert alert-danger mt-3">Error fetching address data</div>`;
                showMessage('Failed to check address', 'error');
            }
        }

        function updateSidebar(address, balance, txCount, usdValue) {
            document.getElementById('addressDisplay').innerText = address;
            document.getElementById('balanceDisplay').innerHTML = `${balance.toFixed(4)} ETH`;
            document.getElementById('balanceUsd').innerHTML = `$${usdValue}`;
            document.getElementById('txCount').innerHTML = txCount;
        }

        async function saveCheckToDatabase(address, balance, txCount, isContract) {
            const formData = new URLSearchParams();
            formData.append('address', address);
            formData.append('balance', balance);
            formData.append('txCount', txCount);
            try {
                await fetch('save_eth_check.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData });
            } catch(e) {}
        }

        function setTestAddress() {
            const test = ['0x742d35Cc6634C0532925a3b844Bc454e4438f44e', '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045', '0x3f5CE5FBFe3E9af3971dD833D26bA9b5C936f0bE'];
            const random = test[Math.floor(Math.random() * test.length)];
            document.getElementById('ethAddressInput').value = random;
            showMessage('Test address loaded', 'info');
        }

        // Fixed wallet generation - using ethers and showing result in checkResults
        async function generateWallet() {
            if (!confirm('⚠️ Generate new wallet locally? Keep private key safe!')) return;
            try {
                if (typeof ethers === 'undefined') throw new Error('Ethers library not loaded');
                const wallet = ethers.Wallet.createRandom();
                const address = wallet.address;
                const privKey = wallet.privateKey;
                const mnemonic = wallet.mnemonic?.phrase || '';
                const resultsHtml = `
                    <div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle"></i> Save these credentials securely (never share)</div>
                    <div class="card mt-2"><div class="card-body">
                        <div class="mb-2"><label>Address</label><div class="input-group"><input class="form-control" value="${address}" readonly><button class="btn btn-outline-primary" onclick="copyText('${address}')"><i class="fas fa-copy"></i></button></div></div>
                        <div class="mb-2"><label>Private Key</label><div class="input-group"><input type="password" class="form-control" value="${privKey}" readonly><button class="btn btn-outline-danger" onclick="copyText('${privKey}')"><i class="fas fa-key"></i></button></div></div>
                        ${mnemonic ? `<div class="mb-2"><label>Recovery Phrase</label><textarea class="form-control" rows="2" readonly>${mnemonic}</textarea><button class="btn btn-outline-warning mt-1 w-100" onclick="copyText('${mnemonic}')">Copy Phrase</button></div>` : ''}
                    </div></div>`;
                document.getElementById('checkResults').innerHTML = resultsHtml;
                currentAddress = address;
                document.getElementById('ethAddressInput').value = address;
                generateQRCode('addressQR', address);
                updateSidebar(address, 0, 0, '0.00');
                showMessage('Wallet generated successfully', 'success');
            } catch (err) {
                console.error(err);
                showMessage('Generation failed: ' + err.message, 'error');
                document.getElementById('checkResults').innerHTML = `<div class="alert alert-danger mt-3">Failed to generate wallet: ${err.message}</div>`;
            }
        }

        function refreshCurrentAddress() {
            if (!currentAddress) return showMessage('No address loaded', 'warning');
            document.getElementById('ethAddressInput').value = currentAddress;
            checkEthereumAddress();
        }

        function saveCurrentAddress() {
            if (!currentAddress) return showMessage('No address to save', 'warning');
            let saved = JSON.parse(localStorage.getItem('saved_eth_addresses') || '[]');
            if (!saved.includes(currentAddress)) { saved.push(currentAddress); localStorage.setItem('saved_eth_addresses', JSON.stringify(saved)); showMessage('Address saved locally', 'success'); }
            else showMessage('Already saved', 'info');
        }

        function openEtherscan() {
            if (!currentAddress) return showMessage('No address', 'warning');
            window.open(`https://etherscan.io/address/${currentAddress}`, '_blank');
        }

        async function updateMarketData() {
            try {
                const res = await fetch('https://api.coingecko.com/api/v3/simple/price?ids=ethereum&vs_currencies=usd&include_market_cap=true&include_24hr_vol=true');
                const data = await res.json();
                if (data.ethereum) {
                    document.getElementById('ethPrice').innerHTML = `$${data.ethereum.usd.toLocaleString()}`;
                    document.getElementById('marketCap').innerHTML = `$${(data.ethereum.usd_market_cap / 1e9).toFixed(2)}B`;
                    document.getElementById('volume24h').innerHTML = `$${(data.ethereum.usd_24h_vol / 1e9).toFixed(2)}B`;
                }
            } catch(e) { console.error(e); }
        }

        // Event binding after DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            const genBtn = document.getElementById('generateWalletBtn');
            if (genBtn) genBtn.onclick = generateWallet;
            updateMarketData();
            setTestAddress();
            setInterval(updateMarketData, 60000);
        });
    </script>
</body>
</html>
