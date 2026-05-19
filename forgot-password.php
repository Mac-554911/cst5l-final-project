<?php
session_start();
require_once 'config/db.php'; 

// AUTOMATIC LOGIN CHECK
if (isset($_SESSION['employee_id'])) {
    header("Location: dashboard.php");
    exit();
}

class password_recovery_manager {
    private $pdo;

    public function __construct($pdo_instance) {
        $this->pdo = $pdo_instance;
    }

    // GENERATE SECURE RECOVERY TOKEN
    public function initiate_reset($email_ad) {
        if (!filter_var($email_ad, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'message' => "Error: Invalid email address format."];
        }

        try {
            $sql = "SELECT COUNT(*) FROM employees WHERE email_address = :email_ad";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['email_ad' => $email_ad]);
            
            if ($stmt->fetchColumn() == 0) {
                return ['status' => true, 'message' => "If that email matches, a recovery link has been generated."];
            }

            $token  = bin2hex(random_bytes(16));
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $sql = "UPDATE employees SET reset_token = :token, token_expiry = :expiry WHERE email_address = :email_ad";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['token' => $token, 'expiry' => $expiry, 'email_ad' => $email_ad]);

            return [
                'status'  => true,
                'message' => "Recovery link generated! Simulation Link: <a href='reset-password.php?token=" . $token . "'>Reset Password Link</a>"
            ];

        } catch (PDOException $e) {
            return ['status' => false, 'message' => "Database Error: " . $e->getMessage()];
        }
    }
}

class forgot_password_controller {
    private $recovery_mgr;
    public $recovery_result = null;

    public function __construct($pdo_instance) {
        $this->recovery_mgr = new password_recovery_manager($pdo_instance);
    }

    // ROUTE USER REQUEST SUBMISSIONS
    public function handle_post_requests() {
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_forgot'])) {
            $email_ad = trim($_POST['email_ad']);
            $this->recovery_result = $this->recovery_mgr->initiate_reset($email_ad);
        }
    }
}

// APP INITIALIZATION CORNER
$controller = new forgot_password_controller($pdo);
$controller->handle_post_requests();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <h1 class="auth-heading">Forgot Password</h1>
        <h3 class="auth-subheading">Request a reset link</h3>

        <?php if ($controller->recovery_result !== null): ?>
            <div class="alert <?php echo $controller->recovery_result['status'] ? 'alert--success' : 'alert--error'; ?>">
                <p><strong><?php echo $controller->recovery_result['message']; ?></strong></p>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label class="form-label">Registered Email</label>
                <input type="email" name="email_ad" class="form-control" placeholder="Enter Registered Email" required>
            </div>
            <button type="submit" name="btn_forgot" class="btn btn--primary btn--full">Request Reset Link</button>
        </form>

        <div class="auth-nav">
            <p><a href="login.php">Back to Login</a></p>
        </div>
    </div>
</body>
</html>
