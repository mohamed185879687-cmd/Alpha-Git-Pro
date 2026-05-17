<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alpha-Git | Card Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .generator-container {
            display: flex;
            justify-content: space-between;
            gap: 2rem;
        }
        
        @media (min-width: 992px) {
            .form-section {
                flex: 1;
            }
            
            .result-section {
                flex: 0.6;
            }
            
            .result-section textarea {
                height: 400px;
            }
        }
        
        .copy-btn {
            position: absolute;
            top: 38px;
            right: 15px;
            cursor: pointer;
            font-size: 1.2rem;
            color: #0d6efd;
            z-index: 10;
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
                        <a class="nav-link" href="checker.php">Card Checker</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="generator.php">Generator</a>
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

    <!-- Generator Content -->
    <div class="container my-5">
        <div class="generator-container">
            <div class="form-section">
                <h1 class="text-center mb-4"><b>Alpha-Git Generator</b></h1>
                <form id="generatorForm">
                    <div class="mb-3">
                        <label for="bin" class="form-label"><b>BIN</b></label>
                        <input type="text" class="form-control" id="bin" 
                               placeholder="37513874xx0591x" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="month" class="form-label"><b>MONTH</b></label>
                            <select class="form-select" id="month">
                                <option value="Random">Random</option>
                                <option value="01">01</option>
                                <option value="02">02</option>
                                <option value="03">03</option>
                                <option value="04">04</option>
                                <option value="05">05</option>
                                <option value="06">06</option>
                                <option value="07">07</option>
                                <option value="08">08</option>
                                <option value="09">09</option>
                                <option value="10">10</option>
                                <option value="11">11</option>
                                <option value="12">12</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="year" class="form-label"><b>YEAR</b></label>
                            <select class="form-select" id="year">
                                <option value="Random">Random</option>
                                <option value="2024">2024</option>
                                <option value="2025">2025</option>
                                <option value="2026">2026</option>
                                <option value="2027">2027</option>
                                <option value="2028">2028</option>
                                <option value="2029">2029</option>
                                <option value="2030">2030</option>
                                <option value="2031">2031</option>
                                <option value="2032">2032</option>
                                <option value="2033">2033</option>
                                <option value="2034">2034</option>
                                <option value="2035">2035</option>
                                <option value="2036">2036</option>
                                <option value="2037">2037</option>
                                <option value="2038">2038</option>
                                <option value="2039">2039</option>
                                <option value="2040">2040</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ccv" class="form-label"><b>CVV</b></label>
                            <input type="text" class="form-control" id="ccv" 
                                   placeholder="Random">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="count" class="form-label"><b>QUANTITY</b></label>
                            <input type="number" class="form-control" id="count" 
                                   value="20" min="1" max="1000">
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-primary" onclick="generateCards()">
                        <b>Generate Cards</b>
                    </button>
                </form>
            </div>
            
            <div class="result-section">
                <div class="mb-3 position-relative">
                    <label for="output" class="form-label"><b>GENERATED CARDS</b></label>
                    <i class="fas fa-copy copy-btn" onclick="copyToClipboard()" 
                       title="Copy to clipboard"></i>
                    <textarea class="form-control" id="output" rows="15" readonly></textarea>
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
        function generateCard(bin, length) {
            let bin2 = "";
            let card = "";
            let card1_l = [];
            let card2_l = [];
            let sum = 0;
            let mod = 0;
            let check_sum = 0;

            // Replace 'x' with random digits
            for (let i = 0; i < bin.length && bin2.length < length - 1; i++) {
                let char = bin[i].toLowerCase();
                if (char == "x") {
                    char = Math.floor(Math.random() * 10);
                }
                bin2 += char;
            }

            // Fill remaining digits
            while (bin2.length < length - 1) {
                bin2 += Math.floor(Math.random() * 10);
            }

            // Convert to arrays
            for (let i = 0; i < bin2.length; i++) {
                card1_l.push(parseInt(bin2[i]));
                card2_l.push(parseInt(bin2[i]));
            }

            // Luhn algorithm
            for (let i = card2_l.length - 1; i >= 0; i -= 2) {
                card2_l[i] *= 2;
                if (card2_l[i] > 9) {
                    card2_l[i] -= 9;
                }
            }

            // Calculate sum
            for (let i in card2_l) {
                sum += card2_l[i];
            }

            // Calculate checksum
            mod = sum % 10;
            if (mod != 0) {
                check_sum = 10 - mod;
            }

            // Add checksum
            card1_l.push(check_sum);

            // Convert to string
            for (let i in card1_l) {
                card += card1_l[i];
            }
            return card;
        }

        function generateMonth() {
            const months = ["01", "02", "03", "04", "05", "06", "07", "08", "09", "10", "11", "12"];
            return months[Math.floor(Math.random() * 12)];
        }

        function generateYear() {
            const years = ["2024", "2025", "2026", "2027", "2028", "2029", "2030", "2031", "2032", "2033", "2034", "2035", "2036", "2037", "2038", "2039", "2040"];
            return years[Math.floor(Math.random() * years.length)];
        }

        function generateCVV(bin) {
            const isAmex = /^3[47]/.test(bin);
            const cvvLength = isAmex ? 4 : 3;
            let cvv = "";
            for (let i = 0; i < cvvLength; i++) {
                cvv += Math.floor(Math.random() * 10);
            }
            return cvv;
        }

        function generateCards() {
            const bin = document.getElementById("bin").value.trim();
            const ccv = document.getElementById("ccv").value.trim();
            const month = document.getElementById("month").value;
            const year = document.getElementById("year").value;
            const count = parseInt(document.getElementById("count").value);
            const output = document.getElementById("output");

            if (!bin) {
                alert("Please enter BIN!");
                return;
            }

            const cardLength = /^3[47]/.test(bin) ? 15 : (/^3[0689]/.test(bin) ? 14 : 16);
            const countValue = isNaN(count) || count <= 0 ? 20 : Math.min(count, 1000);

            let cards = "";
            for (let i = 0; i < countValue; i++) {
                const cardNumber = generateCard(bin, cardLength);
                const cardMonth = month === "Random" ? generateMonth() : month;
                const cardYear = year === "Random" ? generateYear() : year;
                const cardCVV = (ccv === "" || ccv === "Random") ? generateCVV(bin) : ccv;
                
                cards += `${cardNumber}|${cardMonth}|${cardYear}|${cardCVV}\n`;
            }

            output.value = cards.trim();
        }

        function copyToClipboard() {
            const output = document.getElementById("output");
            if (output.value.trim() === "") {
                alert("No cards to copy!");
                return;
            }
            
            output.select();
            output.setSelectionRange(0, 99999);
            document.execCommand("copy");
            alert("Copied to clipboard!");
        }
    </script>
</body>
</html>