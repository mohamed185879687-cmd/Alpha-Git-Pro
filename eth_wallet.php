<?php
if (session_status() == PHP_SESSION_NONE) session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$conn->query("CREATE TABLE IF NOT EXISTS user_wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    wallet_address VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wallet (user_id, wallet_address)
)");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alpha-Git Pro | ETH Wallet Generator + Batch Balance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#0a0e27;font-family:'Segoe UI',sans-serif;}
        .navbar{background:#0a0e27!important;border-bottom:1px solid #2a2f4a;}
        .navbar-brand{color:#00ff88!important;font-weight:bold;}
        .nav-link{color:#aaa!important;}
        .nav-link.active{color:#00ff88!important;background:#1a2f3a;border-radius:8px;}
        .card{background:#1a1f3a;border:none;border-radius:12px;margin-bottom:20px;}
        .card-header{background:#1a1f3a;border-bottom:1px solid #2a2f4a;color:#00ff88;font-weight:bold;}
        .form-control,.input-group-text{background:#2a2f4a;border:none;color:#fff;}
        .form-control:focus{background:#2a2f4a;color:#fff;box-shadow:none;border:1px solid #00ff88;}
        .btn-primary{background:#00ff88;border:none;color:#0a0e27;font-weight:bold;}
        .btn-primary:hover{background:#00cc66;}
        .btn-outline-primary{border-color:#00ff88;color:#00ff88;}
        .btn-outline-primary:hover{background:#00ff88;color:#0a0e27;}
        .wallet-card{border-left:3px solid #00ff88;margin-bottom:15px;}
        .wallet-private{background:#2a1f2a;border-left:4px solid #ff4466;padding:10px;border-radius:8px;margin-top:10px;}
        .wallet-mnemonic{background:#2a2a1f;border-left:4px solid #ffaa00;padding:10px;border-radius:8px;margin-top:10px;}
        .balance-display{font-weight:bold;color:#00ff88;}
        .small-balance{font-size:0.85rem;}
        .copy-btn{cursor:pointer;}
        .toast-message{position:fixed;top:80px;right:20px;z-index:9999;background:#1a1f3a;border-left:4px solid #00ff88;color:#fff;padding:10px 20px;border-radius:8px;animation:fadeOut 4s forwards;}
        @keyframes fadeOut{0%{opacity:1}70%{opacity:1}100%{opacity:0;visibility:hidden}}
        .simple-qr{width:90px;height:90px;background:#2a2f4a;border-radius:8px;display:flex;align-items:center;justify-content:center;margin:0 auto;font-family:monospace;font-size:10px;text-align:center;color:#00ff88;border:1px solid #00ff88;}
        footer{background:#0a0e27;border-top:1px solid #2a2f4a;color:#555;text-align:center;padding:15px;}
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
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="checker.php">Card Checker</a></li>
                    <li class="nav-item"><a class="nav-link" href="generator.php">Generator</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" data-bs-toggle="dropdown"><i class="fab fa-ethereum"></i> ETH Tools</a>
                        <ul class="dropdown-menu dropdown-menu-dark">
                            <li><a class="dropdown-item active" href="eth_wallet.php">Wallet Generator</a></li>
                            <li><a class="dropdown-item" href="eth_checker.php">Address Checker</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="fas fa-user"></i> <?= htmlspecialchars($username) ?></a>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
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
                    <div class="card-header"><i class="fab fa-ethereum"></i> Wallet Generator + Batch Balance Check</div>
                    <div class="card-body">
                        <div class="alert alert-danger bg-dark text-danger border-danger">
                            <i class="fas fa-exclamation-triangle"></i> Wallets generated locally. No keys stored on server.
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="generateMnemonic" checked> <label class="text-white">Generate Mnemonic (12 words)</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="showPrivateKey" checked> <label class="text-white">Show Private Key</label></div>
                            </div>
                            <div class="col-md-6">
                                <label>Number of Wallets</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="walletCount" min="1" max="20" value="1">
                                    <button class="btn btn-outline-primary" id="genMultiBtn">Generate Multiple</button>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-4">
                            <button class="btn btn-primary" id="genSingleBtn"><i class="fas fa-plus-circle"></i> Single Wallet</button>
                            <button class="btn btn-outline-warning" id="clearBtn">Clear All</button>
                            <button class="btn btn-outline-info" id="batchBalanceBtn"><i class="fas fa-chart-line"></i> Check All Balances</button>
                        </div>
                        <div id="walletsContainer">
                            <div class="text-center text-muted py-5"><i class="fas fa-wallet fa-3x mb-3"></i><p>No wallets yet.</p></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Quick Balance Check</div>
                    <div class="card-body">
                        <input type="text" class="form-control mb-2" id="singleBalanceAddress" placeholder="0x...">
                        <button class="btn btn-outline-info w-100" id="singleBalanceBtn">Check One Address</button>
                        <div id="singleBalanceResult" class="mt-3" style="display:none;"><div class="alert alert-dark"><span id="singleBalanceValue">0 ETH</span></div></div>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-header">Export Options</div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" id="exportTxtBtn">Export as TXT</button>
                            <button class="btn btn-outline-success" id="exportJsonBtn">Export as JSON</button>
                        </div>
                        <hr>
                        <ul class="small text-muted"><li>Never share private keys</li><li>Save mnemonics offline</li></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer><small>&copy; 2026 Alpha-Git Pro | Powered by ethers.js</small></footer>

    <script src="https://cdn.jsdelivr.net/npm/ethers@5.7.2/dist/ethers.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let wallets = []; // array of {address, privateKey, mnemonic, timestamp, index, balance?}

        function showMessage(msg, type='success') {
            let div = document.createElement('div');
            div.className = 'toast-message';
            div.style.borderLeftColor = type==='success'?'#00ff88':'#ff4466';
            div.innerText = msg;
            document.body.appendChild(div);
            setTimeout(()=>div.remove(),4000);
        }

        async function generateOneWallet(askConfirm=true) {
            if(askConfirm && !confirm('Generate new wallet? Save keys now!')) return null;
            let withMnemonic = document.getElementById('generateMnemonic').checked;
            let showPrivate = document.getElementById('showPrivateKey').checked;
            try {
                let wallet = ethers.Wallet.createRandom();
                let mnemonic = (withMnemonic && wallet.mnemonic) ? wallet.mnemonic.phrase : '';
                let walletData = {
                    address: wallet.address,
                    privateKey: showPrivate ? wallet.privateKey : 'HIDDEN',
                    mnemonic: mnemonic,
                    timestamp: new Date().toISOString(),
                    index: wallets.length+1,
                    balance: null
                };
                wallets.push(walletData);
                displayWallet(walletData);
                saveAddressToDB(wallet.address);
                showMessage('Wallet generated','success');
                return walletData;
            } catch(e) {
                showMessage('Error: '+e.message,'error');
                return null;
            }
        }

        function displayWallet(w){
            let container = document.getElementById('walletsContainer');
            if(container.querySelector('.text-muted')) container.innerHTML='';
            let div = document.createElement('div');
            div.className = 'card wallet-card mb-3';
            div.id = `wallet-${w.index}`;
            let balanceHtml = w.balance !== null ? `<span class="badge bg-info ms-2">${parseFloat(w.balance).toFixed(6)} ETH</span>` : '<span class="badge bg-secondary ms-2">Not checked</span>';
            div.innerHTML = `
                <div class="card-header d-flex justify-content-between">
                    <span><i class="fas fa-wallet"></i> Wallet #${w.index} ${balanceHtml}</span>
                    <div><button class="btn btn-sm btn-outline-light copy-addr" data-addr="${w.address}">Copy Address</button></div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label>Address</label><div class="input-group mb-2"><input type="text" class="form-control" value="${w.address}" readonly><span class="input-group-text copy-btn copy-addr" data-addr="${w.address}"><i class="fas fa-copy"></i></span></div>
                            ${w.privateKey !== 'HIDDEN' ? `<div class="wallet-private"><label class="text-danger">Private Key</label><div class="input-group"><input type="password" class="form-control" value="${w.privateKey}" readonly><span class="input-group-text copy-btn copy-key" data-key="${w.privateKey}"><i class="fas fa-key"></i></span></div></div>` : ''}
                        </div>
                        <div class="col-md-6">
                            ${w.mnemonic ? `<div class="wallet-mnemonic"><label>Mnemonic</label><textarea class="form-control" rows="2" readonly>${w.mnemonic}</textarea><button class="btn btn-sm btn-outline-warning mt-1 copy-mnemonic" data-mnemonic="${w.mnemonic}">Copy Phrase</button></div>` : ''}
                            <div><small>Generated: ${new Date(w.timestamp).toLocaleString()}</small></div>
                            <button class="btn btn-sm btn-outline-info mt-2 check-single-balance" data-addr="${w.address}">Check Balance</button>
                        </div>
                    </div>
                    <div class="text-center mt-2"><div class="simple-qr mx-auto">${generateQR(w.address)}</div></div>
                </div>
            `;
            container.appendChild(div);
            attachCopyEvents();
            attachSingleBalanceEvents();
        }

        function generateQR(addr){
            let short = addr.slice(0,10)+'...'+addr.slice(-8);
            return short.split('').join('<br>');
        }

        function attachCopyEvents(){
            document.querySelectorAll('.copy-addr').forEach(el=>el.addEventListener('click',()=>copyText(el.dataset.addr)));
            document.querySelectorAll('.copy-key').forEach(el=>el.addEventListener('click',()=>copyText(el.dataset.key)));
            document.querySelectorAll('.copy-mnemonic').forEach(el=>el.addEventListener('click',()=>copyText(el.dataset.mnemonic)));
        }

        function attachSingleBalanceEvents(){
            document.querySelectorAll('.check-single-balance').forEach(btn=>{
                btn.removeEventListener('click', balanceHandler);
                btn.addEventListener('click', balanceHandler);
            });
        }
        async function balanceHandler(e){
            let addr = e.currentTarget.dataset.addr;
            let balance = await fetchBalance(addr);
            if(balance !== null){
                // update wallet object and UI
                let walletObj = wallets.find(w=>w.address===addr);
                if(walletObj) walletObj.balance = balance;
                let card = e.currentTarget.closest('.wallet-card');
                let headerSpan = card.querySelector('.card-header span');
                headerSpan.innerHTML = `<i class="fas fa-wallet"></i> Wallet #${walletObj.index} <span class="badge bg-info ms-2">${parseFloat(balance).toFixed(6)} ETH</span>`;
                showMessage(`Balance: ${balance.toFixed(6)} ETH`, 'success');
            } else showMessage('Failed to fetch balance','error');
        }

        async function fetchBalance(address){
            try{
                let res = await fetch(`https://api.ethplorer.io/getAddressInfo/${address}?apiKey=freekey`);
                let data = await res.json();
                if(data.error) throw new Error(data.error.message);
                return data.ETH ? data.ETH.balance : 0;
            } catch(e){
                console.warn(e);
                return null;
            }
        }

        async function batchBalanceCheck(){
            if(wallets.length===0){ showMessage('No wallets to check','warning'); return; }
            showMessage(`Checking ${wallets.length} wallets... This may take a moment.`,'info');
            for(let w of wallets){
                let bal = await fetchBalance(w.address);
                if(bal!==null){
                    w.balance = bal;
                    let card = document.getElementById(`wallet-${w.index}`);
                    if(card){
                        let headerSpan = card.querySelector('.card-header span');
                        headerSpan.innerHTML = `<i class="fas fa-wallet"></i> Wallet #${w.index} <span class="badge bg-info ms-2">${parseFloat(bal).toFixed(6)} ETH</span>`;
                    }
                }
                await new Promise(r=>setTimeout(r,500));
            }
            showMessage('Batch check completed','success');
        }

        async function generateMultiple(count){
            for(let i=0;i<count;i++){
                let success = await generateOneWallet(false);
                if(!success) break;
                await new Promise(r=>setTimeout(r,150));
            }
        }

        function clearAllWallets(){
            if(wallets.length===0) return;
            if(!confirm('Delete all wallets from screen?')) return;
            wallets = [];
            document.getElementById('walletsContainer').innerHTML = `<div class="text-center text-muted py-5"><i class="fas fa-wallet fa-3x"></i><p>No wallets yet.</p></div>`;
            showMessage('All wallets cleared','success');
        }

        function copyText(t){
            navigator.clipboard.writeText(t);
            showMessage('Copied!','success');
        }

        function exportWallets(format){
            if(!wallets.length){ showMessage('No wallets','warning'); return; }
            let content='',filename=`eth_wallets_${new Date().toISOString().slice(0,10)}`;
            if(format==='txt'){
                content='ETHEREUM WALLETS\n'+'='.repeat(50)+'\n';
                wallets.forEach(w=>{
                    content+=`Wallet #${w.index}\nAddress: ${w.address}\nPrivate: ${w.privateKey}\nMnemonic: ${w.mnemonic||'N/A'}\nTimestamp: ${w.timestamp}\nBalance: ${w.balance!==null?w.balance:'?'} ETH\n`+'-'.repeat(40)+'\n';
                });
                filename+='.txt';
            } else {
                let safe = wallets.map(w=>({address:w.address,balance:w.balance,timestamp:w.timestamp}));
                content=JSON.stringify(safe,null,2);
                filename+='.json';
            }
            let blob=new Blob([content],{type:'text/plain'});
            let a=document.createElement('a');
            a.href=URL.createObjectURL(blob);
            a.download=filename;
            a.click();
            URL.revokeObjectURL(a.href);
            showMessage('Exported','success');
        }

        async function singleAddressBalance(){
            let addr = document.getElementById('singleBalanceAddress').value.trim();
            if(!ethers.utils.isAddress(addr)){ showMessage('Invalid address','error'); return; }
            let bal = await fetchBalance(addr);
            if(bal!==null){
                document.getElementById('singleBalanceValue').innerText = `${parseFloat(bal).toFixed(6)} ETH`;
                document.getElementById('singleBalanceResult').style.display='block';
            } else { showMessage('Failed','error'); }
        }

        function saveAddressToDB(address){
            fetch('save_wallet_address.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'address='+encodeURIComponent(address)}).catch(e=>console.warn);
        }

        // Event bindings
        document.getElementById('genSingleBtn').onclick = ()=>generateOneWallet(true);
        document.getElementById('genMultiBtn').onclick = ()=>{
            let count = parseInt(document.getElementById('walletCount').value);
            if(count<1||count>20){ showMessage('Count 1-20','warning'); return; }
            generateMultiple(count);
        };
        document.getElementById('clearBtn').onclick = clearAllWallets;
        document.getElementById('batchBalanceBtn').onclick = batchBalanceCheck;
        document.getElementById('exportTxtBtn').onclick = ()=>exportWallets('txt');
        document.getElementById('exportJsonBtn').onclick = ()=>exportWallets('json');
        document.getElementById('singleBalanceBtn').onclick = singleAddressBalance;
    </script>
</body>
</html>
