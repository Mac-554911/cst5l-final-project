<?php
session_start();
require_once 'config/db.php';

// AUTOMATIC LOGIN
if (isset($_SESSION['employee_id'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    try {
        // USER PROFILE SEARCH
        $sql = "SELECT * FROM employees WHERE username = :username";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        // USER AND PASSWORD VERIFICATION
        if ($user && password_verify($password, $user['password'])) {
            
            // INITIALIZE ACTIVE SESSIONS
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['employee_id'] = $user['employee_id'];
            $_SESSION['username']    = $user['username'];
            $_SESSION['first_name']  = $user['first_name'];
            $_SESSION['last_name']   = $user['last_name'];

            echo "<h3 style='color: green; text-align: center;'>Login successful! Redirecting...</h3>";
            header("Refresh: 2; url=dashboard.php");
            exit();
        } else {
            echo "<h3 style='color: red; text-align: center;'>Error: Invalid username or password.</h3>";
        }
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
</head>
<body>
    <div class="container">
        <h1>Inventory Management System</h1>
        <h3>Login</h3>
        <!-- Action file and POST method -->
        <form action="" method="POST">
            <div class="form-group">
                <input type="text" name="username" class="form-control" placeholder="Username" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" name="btn_login" class="btn">Login</button>
        </form>
    </div>
</body>
</html>
