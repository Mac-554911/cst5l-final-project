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

class password_reset_manager {
    private $pdo;

    public function __construct($pdo_instance) {
        $this->pdo = $pdo_instance;
    }

    // COMMIT NEW PASSWORD
    public function update_password($token, $password, $confirm_pw) {
        // PASSWORD VALIDATION
        if ($password !== $confirm_pw) {
            die("Error: Passwords do not match.");
        }
        
        // PASSWORD STRENGTH VALIDATION
        $password_pattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/';
        if (!preg_match($password_pattern, $password)) {
            die("Error: Password must be at least 10 characters long and include at least one uppercase letter, one number, and one special character.");
        }

        try {
            // FETCH TOKEN EXISTENCE AND EXPIRY TIME VALUES SECURELY
            $sql = "SELECT id, token_expiry FROM employees WHERE reset_token = :token";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['token' => $token]);
            
            // FORCE EXPLICIT ASSOCIATIVE FETCH CODES
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // VERIFY TOKEN EXISTENCE
            if (!$user) {
                die("Error: The password reset link is invalid.");
            }

            // EVALUATE EXPIRATION MANUALLY USING PHP TIME LOGIC
            $current_timestamp = time();
            $expiry_timestamp  = strtotime($user['token_expiry']);

            if ($current_timestamp > $expiry_timestamp) {
                die("Error: The password reset link has expired.");
            }

            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            // UPDATE ACCREDITED ROWS AND FLUSH TIMEOUT TOKENS
            $sql = "UPDATE employees SET password = :password, reset_token = NULL, token_expiry = NULL WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'password' => $hashed_password,
                'id'       => $user['id']
            ]);

            return [
                'status'  => true,
                'message' => "Password reset successfully! Redirecting to login portal..."
            ];

        } catch (PDOException $e) {
            die("Database Error: " . $e->getMessage());
        }
    }
}

class reset_password_controller {
    private $reset_mgr;
    public $reset_result = null;

    public function __construct($pdo_instance) {
        $this->reset_mgr = new password_reset_manager($pdo_instance);
    }

    // ROUTE USER REQUEST SUBMISSIONS
    public function handle_post_requests() {
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_reset'])) {
            $token      = trim($_POST['token']);
            $password   = $_POST['password'];
            $confirm_pw = $_POST['confirm_password'];
            
            $this->reset_result = $this->reset_mgr->update_password($token, $password, $confirm_pw);
            
            if ($this->reset_result['status'] === true) {
                header("Refresh: 3; url=login-page.php");
            }
        }
    }
}

// APP INITIALIZATION CORNER
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($token) && !isset($_POST['btn_reset'])) {
    die("Error: Missing secure transaction token identifier parameters.");
}

$controller = new reset_password_controller($pdo);
$controller->handle_post_requests();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>
<body>
    <div class="container">
        <h1>Inventory Management System</h1>
        <h3>Reset Password</h3>

        <?php if ($controller->reset_result !== null): ?>
            <div>
                <p><strong><?php echo security_helper::xss_clean($controller->reset_result['message']); ?></strong></p>
            </div>
        <?php endif; ?>

        <!-- Action file and POST method -->
        <form action="reset-password.php?token=<?php echo security_helper::xss_clean($token); ?>" method="POST">
            <input type="hidden" name="token" value="<?php echo security_helper::xss_clean($token); ?>">
            
            <div class="form-group">
                <input type="password" name="password" id="txt_password" class="form-control" placeholder="New Password" required pattern="(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}">
                <button type="button" onclick="toggle_visibility('txt_password', this)">Show</button>
                <button type="button" class="info-btn" onclick="alert('Password must have: \n• At least 10 characters \n• 1 uppercase letter \n• 1 number \n• 1 special character (e.g., !@#$)')">?</button>
            </div>
            <div class="form-group">
                <input type="password" name="confirm_password" id="txt_confirm_password" class="form-control" placeholder="Re-write Password" required>
                <button type="button" onclick="toggle_visibility('txt_confirm_password', this)">Show</button>
            </div>
            <button type="submit" name="btn_reset" class="btn">Reset Password</button>
        </form>
    </div>

    <!-- DYNAMIC VISIBILITY SCRIPT -->
    <script>
        function toggle_visibility(input_id, btn_el) {
            const input = document.getElementById(input_id);
            if (input.type === "password") {
                input.type = "text";
                btn_el.textContent = "Hide";
            } else {
                input.type = "password";
                btn_el.textContent = "Show";
            }
        }
    </script>
</body>
</html>
