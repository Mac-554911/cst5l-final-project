<?php
session_start();
require_once 'config/db.php'; 

class employee_manager {
    private $pdo;

    public function __construct($pdo_instance) {
        $this->pdo = $pdo_instance;
    }

    // CREATE EMPLOYEE (Notice employee_id is left out because the trigger handles it!)
    public function register_employee($first_name, $last_name, $email, $contact_nb, $username, $password, $confirm_pw) {
        // CONTACT NUMBER VALIDATION
        $new_contact_nb = preg_replace('/[^0-9+]/', '', $contact_nb);
        if (!preg_match('/^\+?\d{7,15}$/', $new_contact_nb)) {
            die("Error: Invalid contact number format. Please enter a valid numerical phone number.");
        }

        // PASSWORD VALIDATION
        if ($password !== $confirm_pw) {
            die("Error: Passwords do not match.");
        }
        
        // PASSWORD STRENGTH VALIDATION
        $password_pattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/';
        if (!preg_match($password_pattern, $password)) {
            die("Error: Password must be at least 10 characters long and include at least one uppercase letter, one number, and one special character.");
        }

        // EMAIL VALIDATION
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            die("Error: Invalid email address format.");
        }

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        try {
            // USERNAME AVAILABILITY
            $check_sm = $this->pdo->prepare("SELECT COUNT(*) FROM employees WHERE username = :username");
            $check_sm->execute(['username' => $username]);
            
            if ($check_sm->fetchColumn() > 0) {
                die("Error: Username is already registered.");
            }

            $sql = "INSERT INTO employees (first_name, last_name, email_address, contact_number, username, password) 
                    VALUES (:first_name, :last_name, :email_ad, :contact_nb, :username, :password)";
                    
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'email_ad'     => $email,
                'contact_nb'   => $new_contact_nb,
                'username'     => $username,
                'password'     => $hashed_password
            ]);

            // FETCH THE GENERATED IDs FOR SESSIONS
            $last_id = $this->pdo->lastInsertId();
            $employee_id = "EID" . $last_id; 

            // SESSION INITIALIZATION
            $_SESSION['user_id']     = $last_id;
            $_SESSION['employee_id'] = $employee_id;
            $_SESSION['username']    = $username;

            echo "Account created successfully! Your Employee ID is: **" . $employee_id . "**";
            header("Refresh: 3; url=dashboard.php"); 
            exit();

        } catch (PDOException $e) {
            die("Database Execution Error: " . $e->getMessage());
        }
    }
}

class registration_controller {
    private $employee_mgr;

    public function __construct($pdo_instance) {
        $this->employee_mgr = new employee_manager($pdo_instance);
    }

    // ROUTE USER REQUEST SUBMISSIONS
    public function handle_post_requests() {
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_register'])) {
            $this->employee_mgr->register_employee(
                trim($_POST['first_name']),
                trim($_POST['last_name']),
                trim($_POST['email']),
                trim($_POST['contact_nb']),
                trim($_POST['username']),
                $_POST['password'],
                $_POST['confirm_password']
            );
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
    <title>Registration Portal</title>
</head>
<body>
    <form action="registration.php" method="POST">
    <!-- Action file and POST method -->
        <!-- REGISTRATION FORM -->
        <div class="personal-information">
            <h3>Register</h3>
            <div class="form-group">
                <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
            </div>
            <div class="form-group">
                <input type="text" name="last_name" class="form-control" placeholder="Last Name" required>
            </div>
            <div class="form-group">
                <input type="email" name="email" class="form-control" placeholder="Email" required>
            </div>
            <div class="form-group">
                <input type="text" name="contact_nb" class="form-control" placeholder="Contact Number" required>
            </div>
            <button type="submit" name="btn_register" class="btn">Next</button>
        </div>
        <div class="credentials">
            <div class="form-group">
                <input type="text" name="username" class="form-control" placeholder="Username" required>
            </div>
            <div class="form-group">
                <div class="password-wrapper">
                <!-- VISIBILITY COMPLIANT INDEPENDENT ELEMENT FIELDS -->
                <input type="password" name="password" id="txt_password" class="form-control" placeholder="Password" required pattern="(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}">
                <button type="button" onclick="toggle_visibility('txt_password', this)">Show</button>
                <button type="button" class="info-btn" onclick="alert('Password must have: \n• At least 10 characters \n• 1 uppercase letter \n• 1 number \n• 1 special character (e.g., !@#$)')">?</button>
                </div>
            </div>
            <div class="form-group">
                <input type="password" name="confirm_password" id="txt_confirm_password" class="form-control" placeholder="Re-write Password" required>
                <button type="button" onclick="toggle_visibility('txt_confirm_password', this)">Show</button>
            </div>
            <button type="submit" name="btn_register" class="btn">Sign Up</button>
        </div>

        <!-- NAVIGATION PORTAL REDIRECT -->
        <div class="form-navigation">
            <p>Already have an account? <a href="login-page.php">Login Here</a></p>
        </div>
    </form>

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
