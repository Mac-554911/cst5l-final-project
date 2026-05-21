<?php
session_start();
require_once 'config/db.php'; 

// XSS CLEAN UP FILTER UTILITY FUNCTION
class security_helper {
    public static function xss_clean($data) {
        if ($data === null) {
            return '';
        }
        return htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

class employee_manager {
    private $pdo;

    public function __construct($pdo_instance) {
        $this->pdo = $pdo_instance;
    }

    // CREATE EMPLOYEE 
    public function register_employee($first_name, $last_name, $email, $contact_nb, $username, $password, $confirm_pw) {
        // CONTACT NUMBER VALIDATION
        $new_contact_nb = preg_replace('/[^0-9+]/', '', $contact_nb);
        if (!preg_match('/^\+?\d{7,15}$/', $new_contact_nb)) {
            return ['status' => false, 'message' => "Error: Invalid contact number format."];
        }

        // PASSWORD VALIDATION
        if ($password !== $confirm_pw) {
            return ['status' => false, 'message' => "Error: Passwords do not match."];
        }
        
        // PASSWORD STRENGTH VALIDATION
        $password_pattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/';
        if (!preg_match($password_pattern, $password)) {
            return ['status' => false, 'message' => "Error: Password must be 10+ chars with 1 uppercase, 1 number, and 1 special symbol."];
        }

        // EMAIL VALIDATION
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'message' => "Error: Invalid email address format."];
        }

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        try {
            // USERNAME AVAILABILITY
            $check_sm = $this->pdo->prepare("SELECT COUNT(*) FROM employees WHERE username = :username");
            $check_sm->execute(['username' => $username]);
            
            if ($check_sm->fetchColumn() > 0) {
                return ['status' => false, 'message' => "Error: Username is already registered."];
            }

            // SIMPLE AUTO ID CALCULATION
            $id_stmt = $this->pdo->query("SELECT employee_id FROM employees");
            $max_num = 0;
            while ($row = $id_stmt->fetch(PDO::FETCH_ASSOC)) {
                $num = (int) str_replace('EID', '', $row['employee_id']);
                if ($num > $max_num) {
                    $max_num = $num;
                }
            }
            $employee_id = "EID" . ($max_num + 1);

            $sql = "INSERT INTO employees (employee_id, first_name, last_name, email_address, contact_number, username, password) 
                    VALUES (:employee_id, :first_name, :last_name, :email_ad, :contact_nb, :username, :password)";
                    
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'employee_id'  => $employee_id,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'email_ad'     => $email,
                'contact_nb'   => $new_contact_nb,
                'username'     => $username,
                'password'     => $hashed_password
            ]);

            // SESSION INITIALIZATION
            $_SESSION['user_id']     = $employee_id;
            $_SESSION['employee_id'] = $employee_id;
            $_SESSION['username']    = $username;
            $_SESSION['first_name']  = $first_name;
            $_SESSION['last_name']   = $last_name;

            return ['status' => true, 'message' => "Account created! Employee ID: " . $employee_id];

        } catch (PDOException $e) {
            return ['status' => false, 'message' => "Database Error: " . $e->getMessage()];
        }
    }
}

class registration_controller {
    private $employee_mgr;
    public $reg_result = null;

    public function __construct($pdo_instance) {
        $this->employee_mgr = new employee_manager($pdo_instance);
    }

    // ROUTE USER REQUEST SUBMISSIONS
    public function handle_post_requests() {
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_register'])) {
            $this->reg_result = $this->employee_mgr->register_employee(
                trim($_POST['first_name']),
                trim($_POST['last_name']),
                trim($_POST['email_address']),
                trim($_POST['contact_number']),
                trim($_POST['username']),
                $_POST['password'],
                $_POST['confirm_password']
            );
            if ($this->reg_result['status'] === true) {
                header("Refresh: 2; url=dashboard.php");
            }
        }
    }
}

// APP INITIALIZATION CORNER
$controller = new registration_controller($pdo);
$controller->handle_post_requests();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kawaii Store IMS — Sign Up</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page auth-page--scrollable">
    <div class="auth-card auth-card--wide">

        <div class="auth-logo auth-logo--centered">
    <div class="auth-logo_name">🌸 Kawaii Store IMS</div>
</div>

        <h1 class="auth-heading">Create an account</h1>
        <p class="auth-subheading">Fill in your details to get started.</p>

        <?php if ($controller->reg_result !== null): ?>
            <div class="alert <?php echo $controller->reg_result['status'] ? 'alert--success' : 'alert--error'; ?>">
                <div class="alert_body">
                    <div class="alert_message"><?php echo security_helper::xss_clean($controller->reg_result['message']); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4);">
                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" placeholder="First name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" placeholder="Last name" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email_address" class="form-control" placeholder="email@kawaiistore.com" required>
            </div>
            <div class="form-group">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact_number" class="form-control" placeholder="+63 912 345 6789" required>
            </div>
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Choose a username" required>
            </div>
            <div class="form-group">
                <label class="form-label" style="display:flex; align-items:center; gap:var(--space-2);">
                    Password
                    <button type="button" class="btn--info" onclick="document.getElementById('dlg_pw_requirements').classList.add('is-open')" aria-label="Password requirements">?</button>
                </label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="txt_reg_password" class="form-control" placeholder="Create a strong password" required>
                    <button type="button" class="btn--icon" onclick="toggle_visibility('txt_reg_password', this)" aria-label="Toggle password visibility">
                        <svg id="txt_reg_password_eye_show" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg id="txt_reg_password_eye_hide" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <div class="input-wrapper">
                    <input type="password" name="confirm_password" id="txt_confirm_password" class="form-control" placeholder="Repeat your password" required>
                    <button type="button" class="btn--icon" onclick="toggle_visibility('txt_confirm_password', this)" aria-label="Toggle confirm password visibility">
                        <svg id="txt_confirm_password_eye_show" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg id="txt_confirm_password_eye_hide" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>
            <button type="submit" name="btn_register" class="btn btn--primary btn--full">Create Account</button>
        </form>

        <div class="auth-nav">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>

    <!-- PASSWORD REQUIREMENTS DIALOG -->
    <div class="dialog-overlay" id="dlg_pw_requirements" onclick="if(event.target===this)this.classList.remove('is-open')">
        <div class="dialog">
            <div class="dialog_header">
                <span class="dialog_title">Password Requirements</span>
                <button class="dialog_close" onclick="document.getElementById('dlg_pw_requirements').classList.remove('is-open')">&times;</button>
            </div>
            <div class="dialog_body">
                <ul style="display:flex; flex-direction:column; gap:var(--space-3); font-size:var(--text-sm); color:var(--color-text-secondary);">
                    <li>✅ At least <strong>10 characters</strong> long</li>
                    <li>✅ At least <strong>1 uppercase letter</strong> (A–Z)</li>
                    <li>✅ At least <strong>1 number</strong> (0–9)</li>
                    <li>✅ At least <strong>1 special character</strong> (e.g. !@#$%)</li>
                </ul>
            </div>
            <div class="dialog_footer">
                <button class="btn btn--primary" onclick="document.getElementById('dlg_pw_requirements').classList.remove('is-open')">Got it!</button>
            </div>
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