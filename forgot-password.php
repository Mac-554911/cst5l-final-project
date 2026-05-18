<?php
session_start();
require_once 'config/db.php'; 

// AUTOMATIC LOGIN CHECK
if (isset($_SESSION['employee_id'])) {
    header("Location: dashboard.php");
    exit();
}

// XSS CLEAN UP FILTER UTILITY FUNCTION
class security_helper {
    public static function xss_clean($data) {
        if ($data === null) {
            return '';
        }
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
    }
}

class password_recovery_manager {
    private $pdo;

    public function __construct($pdo_instance) {
        $this->pdo = $pdo_instance;
    }

    // GENERATE SECURE RECOVERY TOKEN
    public function initiate_reset($email_ad) {
        // EMAIL VALIDATION
        if (!filter_var($email_ad, FILTER_VALIDATE_EMAIL)) {
            die("Error: Invalid email address format.");
        }

        try {
            // VERIFY EMAIL REGISTERED
            $sql = "SELECT COUNT(*) FROM employees WHERE email_address = :email_ad";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['email_ad' => $email_ad]);
            
            if ($stmt->fetchColumn() == 0) {
                // Security practice: do not leak if email exists or not
                return [
                    'status'  => true,
                    'message' => "If that email exists in our records, a recovery link has been generated."
                ];
            }

            // TOKEN GENERATION AND EXPIRY INITIALIZATION
            $token  = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $sql = "UPDATE employees SET reset_token = :token, token_expiry = :expiry WHERE email_address = :email_ad";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'token'    => $token,
                'expiry'   => $expiry,
                'email_ad' => $email_ad
            ]);

            // ACADEMIC BACKBONE FALLBACK simulation
            $reset_link = "reset-password.php?token=" . $token;
            
            return [
                'status'  => true,
                'message' => "Recovery link generated! Simulation Link: <a href='" . $reset_link . "'>Reset Password Link</a>"
            ];

        } catch (PDOException $e) {
            die("Database Error: " . $e->getMessage());
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
</head>
<body>
    <div class="container">
        <h1>Inventory Management System</h1>
        <h3>Forgot Password</h3>

        <?php if ($controller->recovery_result !== null): ?>
            <div>
                <p><strong><?php echo $controller->recovery_result['message']; ?></strong></p>
            </div>
        <?php endif; ?>

        <!-- Action file and POST method -->
        <form action="forgot-password.php" method="POST">
            <div class="form-group">
                <input type="email" name="email_ad" class="form-control" placeholder="Enter Registered Email" required>
            </div>
            <button type="submit" name="btn_forgot" class="btn">Request Reset Link</button>
        </form>

        <div class="form-navigation">
            <p><a href="login-page.php">Back to Login</a></p>
        </div>
    </div>
</body>
</html>