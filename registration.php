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

            // Simple Auto ID calculation strategy
            $id_stmt = $this->pdo->query("SELECT COUNT(*) as total FROM employees");
            $count_row = $id_stmt->fetch(PDO::FETCH_ASSOC);
            $employee_id = "EID" . ($count_row['total'] + 1);

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
                trim($_POST['email']),
                trim($_POST['contact_nb']),
                trim($_POST['username']),
                $_POST['password'],
                $_POST['confirm_password']
            );

            if ($this->reg_result['status'] === true) {
                header("Refresh: 3; url=dashboard.php");
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
    <title>Registration Portal</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <div class="auth-card auth-card--wide">
        <h1 class="auth-heading">Registration</h1>
        <h3 class="auth-subheading">Create an account</h3>

        <?php if ($controller->reg_result !== null): ?>
            <div class="alert <?php echo $controller->reg_result['status'] ? 'alert--success' : 'alert--error'; ?>">
                <p><strong><?php echo security_helper::xss_clean($controller->reg_result['message']); ?></strong></p>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control" placeholder="Last Name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="Email" required>
            </div>
            <div class="form-group">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact_nb" class="form-control" placeholder="Contact Number" required>
            </div>
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Username" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="txt_password" class="form-control" placeholder="Password" required>
                    <button type="button" class="btn--icon" onclick="toggle_visibility('txt_password', this)">Show</button>
                    <button type="button" class="btn--info" onclick="alert('Must have: 10+ chars, 1 uppercase, 1 number, 1 symbol')">?</button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <div class="input-wrapper">
                    <input type="password" name="confirm_password" id="txt_confirm_password" class="form-control" placeholder="Re-write Password" required>
                    <button type="button" class="btn--icon" onclick="toggle_visibility('txt_confirm_password', this)">Show</button>
                </div>
            </div>
            <button type="submit" name="btn_register" class="btn btn--primary btn--full">Sign Up</button>
        </form>

        <div class="auth-nav">
            <p>Already have an account? <a href="login.php">Login Here</a></p>
        </div>
    </div>

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
