<?php
session_start();
require_once 'config/db.php'; 

// AUTOMATIC LOGIN CHECK
if (isset($_SESSION['employee_id'])) {
    header("Location: dashboard.php");
    exit();
}

class password_reset_manager {
    private $pdo;

    public function __construct($pdo_instance) {
        $this->pdo = $pdo_instance;
    }

    // COMMIT NEW PASSWORD
    public function update_password($token, $password, $confirm_pw) {
        if ($password !== $confirm_pw) {
            return ['status' => false, 'message' => "Error: Passwords do not match."];
        }
        
        $password_pattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/';
        if (!preg_match($password_pattern, $password)) {
            return ['status' => false, 'message' => "Error: Password fails complexity standard."];
        }

        try {
            $sql = "SELECT id, token_expiry FROM employees WHERE reset_token = :token";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['token' => $token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return ['status' => false, 'message' => "Error: Link invalid."];
            }

            if (time() > strtotime($user['token_expiry'])) {
                return ['status' => false, 'message' => "Error: Link expired."];
            }

            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            $sql = "UPDATE employees SET password = :password, reset_token = NULL, token_expiry = NULL WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['password' => $hashed_password, 'id' => $user['id']]);

            return ['status' => true, 'message' => "Password changed successfully!"];

        } catch (PDOException $e) {
            return ['status' => false, 'message' => "Database Error: " . $e->getMessage()];
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
    public function handle_post_requests($token) {
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_reset'])) {
            $password   = $_POST['password'];
            $confirm_pw = $_POST['confirm_password'];
            
            $this->reset_result = $this->reset_mgr->update_password($token, $password, $confirm_pw);
            
            if ($this->reset_result['status'] === true) {
                header("Refresh: 3; url=index.php");
            }
        }
    }
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($token)) {
    die("Error: Missing verification token context parameters.");
}

$controller = new reset_password_controller($pdo);
$controller->handle_post_requests($token);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <h1 class="auth-heading">Reset Password</h1>
        <h3 class="auth-subheading">Create new password</h3>

        <?php if ($controller->reset_result !== null): ?>
            <div class="alert <?php echo $controller->reset_result['status'] ? 'alert--success' : 'alert--error'; ?>">
                <p><strong><?php echo $controller->reset_result['message']; ?></strong></p>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label class="form-label">New Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="txt_new_password" class="form-control" required>
                    <button type="button" class="btn--icon" onclick="toggle_visibility('txt_new_password', this)" aria-label="Toggle password visibility">
                        <svg id="txt_new_password_eye_show" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg id="txt_new_password_eye_hide" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <div class="input-wrapper">
                    <input type="password" name="confirm_password" id="txt_confirm_password" class="form-control" required>
                    <button type="button" class="btn--icon" onclick="toggle_visibility('txt_confirm_password', this)" aria-label="Toggle confirm password visibility">
                        <svg id="txt_confirm_password_eye_show" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg id="txt_confirm_password_eye_hide" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>
            <button type="submit" name="btn_reset" class="btn btn--primary btn--full">Reset Password</button>
        </form>
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