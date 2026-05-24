<?php
// DATABASE CONNECTION
$host = $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?? 'localhost';
$port = $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT') ?? '3306';
$dbname = $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?? '';
$user = $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?? 'root';
$pass = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?? getenv('MYSQLROOT_PASSWORD') ?? '';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>
