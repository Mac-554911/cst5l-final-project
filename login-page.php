<?php
session_start();
require_once 'config/db.php'; 

// AUTOMATIC LOGIN
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

class authentication_manager {
    private $pdo;

    public function __construct($pdo_instance) {
        $this->pdo = $pdo_instance;
    }

    // USER PROFILE SEARCH
    public function authenticate_user($username, $password) {
        try {
            $sql = "SELECT * FROM employees WHERE username = :username";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['username' => $username]);
            
            // FORCE EXPLICIT ASSOCIATIVE FETCH CODES
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // USER AND PASSWORD VERIFICATION
            if ($user && password_verify($password, $user['password'])) {
                
                // INITIALIZE ACTIVE SESSIONS
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['employee_id'] = $user['employee_id'];
                $_SESSION['username']    = $user['username'];
                $_SESSION['first_name']  = $user['first_name'];
                $_SESSION['last_name']   = $user['last_name'];

                return [
                    'status'  => true,
                    'message' => "Login successful! Redirecting..."
                ];
            } else {
                return [
                    'status'  => false,
                    'message' => "Error: Invalid username or password."
                ];
            }
        } catch (PDOException $e) {
            die("Database Error: " . $e->getMessage());
        }
    }
}

class login_controller {
    private $auth_mgr;
    public $login_result = null;

    public function __construct($pdo_instance) {
        $this->auth_mgr = new authentication_manager($pdo_instance);
    }

    // ROUTE USER REQUEST SUBMISSIONS
    public function handle_post_requests() {
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_login'])) {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            
            $this->login_result = $this->auth_mgr->authenticate_user($username, $password);
            
            if ($this->login_result['status'] === true) {
                header("Refresh: 2; url=dashboard.php");
            }
        }
    }
}

// APP INITIALIZATION CORNER
$controller = new login_controller($pdo);
$controller->handle_post_requests();
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

        <?php if ($controller->login_result !== null): ?>
            <div>
                <p><strong><?php echo security_helper::xss_clean($controller->login_result['message']); ?></strong></p>
            </div>
        <?php endif; ?>

        <!-- Action file and POST method -->
        <!-- Leave action empty to submit to its own filename self-reference safely -->
        <form action="" method="POST">
            <div class="form-group">
                <input type="text" name="username" class="form-control" placeholder="Username" required>
            </div>
            <div class="form-group">
                <!-- PASSWORD TOGGLE WRAPPER -->
                <input type="password" name="password" id="txt_password" class="form-control" placeholder="Password" required>
                <button type="button" onclick="toggle_visibility('txt_password', this)">Show</button>
            </div>
            <button type="submit" name="btn_login" class="btn">Login</button>
        </form>

        <!-- REDIRECT LINK TO OTHER PORTALS -->
        <div class="form-navigation">
            <p>Don't have an account? <a href="registration-page.php">Sign Up Here</a></p>
            <p><a href="forgot-password.php">Forgot Password?</a></p>
        </div>
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
