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
        return htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
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
                session_regenerate_id(true);

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
            return [
                'status'  => false,
                'message' => "Database Error: " . $e->getMessage()
            ];
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
    <title>Kawaii Store IMS | Login</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">

        <div class="auth-logo auth-logo--centered">
    <div class="auth-logo_name">🌸 Kawaii Store IMS</div>
</div>

<h1 class="auth-heading">Welcome back!</h1>
<p class="auth-subheading">Enter your credentials to access the dashboard.</p>

        <?php if ($controller->login_result !== null): ?>
            <div class="alert <?php echo $controller->login_result['status'] ? 'alert--success' : 'alert--error'; ?>">
                <div class="alert_body">
                    <div class="alert_message"><?php echo security_helper::xss_clean($controller->login_result['message']); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="txt_password" class="form-control" placeholder="Enter your password" required>
                    <button type="button" class="btn--icon" onclick="toggle_visibility('txt_password', this)" aria-label="Toggle password visibility">
                        <!-- EYE ICON — VISIBLE STATE -->
                        <svg id="txt_password_eye_show" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <!-- EYE-OFF ICON — HIDDEN STATE -->
                        <svg id="txt_password_eye_hide" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>
            <button type="submit" name="btn_login" class="btn btn--primary btn--full">Login</button>
        </form>

        <div class="auth-nav">
            <p>Don't have an account? <a href="registration.php">Sign Up Here</a></p>
            <p><a href="forgot-password.php">Forgot Password?</a></p>
        </div>
    </div>

    <script>
        function toggle_visibility(input_id, btn_el) {
            const input = document.getElementById(input_id);
            const eye_show = document.getElementById(input_id + '_eye_show');
            const eye_hide = document.getElementById(input_id + '_eye_hide');
            if (input.type === "password") {
                input.type = "text";
                eye_show.style.display = "none";
                eye_hide.style.display = "block";
            } else {
                input.type = "password";
                eye_show.style.display = "block";
                eye_hide.style.display = "none";
            }
        }
    </script>
</body>
</html>
