<?php
$host = 'localhost';
$username = 'alpha_user';
$password = 'simple123';
$database = 'if0_40838190_ode5587';

// إنشاء الاتصال
$conn = new mysqli($host, $username, $password);

// التحقق من الاتصال
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// إنشاء قاعدة البيانات إذا لم تكن موجودة
$conn->query("CREATE DATABASE IF NOT EXISTS $database");
$conn->select_db($database);

// إنشاء جدول users
$conn->query("
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
)");

// إنشاء جدول card_checks
$conn->query("
CREATE TABLE IF NOT EXISTS card_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    card_number VARCHAR(20) NOT NULL,
    exp_month VARCHAR(2),
    exp_year VARCHAR(4),
    cvv VARCHAR(4),
    status VARCHAR(20) DEFAULT 'pending',
    message TEXT,
    check_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// إنشاء جدول eth_checks (المطلوب)
$conn->query("
CREATE TABLE IF NOT EXISTS eth_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    eth_address VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    balance VARCHAR(100) DEFAULT '0',
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// إنشاء جدول eth_wallets (المطلوب)
$conn->query("
CREATE TABLE IF NOT EXISTS eth_wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    wallet_address VARCHAR(255) UNIQUE NOT NULL,
    private_key TEXT,
    balance VARCHAR(100) DEFAULT '0',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// إنشاء جدول activity_log (تسجيل النشاطات)
$conn->query("
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// إضافة مستخدم admin إذا لم يوجد مستخدمين
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, email, password_hash, is_admin) 
                  VALUES ('admin', 'admin@localhost.com', '$admin_pass', 1)");
    
    $conn->query("INSERT INTO users (username, email, password_hash, is_admin) 
                  VALUES ('lord', 'lord@localhost.com', '$admin_pass', 1)");
}

// إرجاع الاتصال للاستخدام
?>
