<?php
// 1. Read variables using both $_ENV and getenv fallbacks
$host = $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?? 'localhost';
$port = $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT') ?? '3306';
$dbname = $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?? '';
$user = $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?? 'root';

// The crucial password fix
$pass = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?? getenv('MYSQLROOT_PASSWORD') ?? '';

try {
    // 2. Build the PDO connection string
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    
    // Set error mode to exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    // This is what is printing out on your screen right now!
    die("Database Connection Error: " . $e->getMessage());
}
?>
