<?php
session_start();
require_once 'config/db.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_register'])) {
    
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $contact_nb = trim($_POST['contact_nb']);
    $username   = trim($_POST['username']);
    $password   = $_POST['password'];
    $confirm_pw = $_POST['confirm_password'];

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
        $check_sm = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE username = :username");
        $check_sm->execute(['username' => $username]);
        
        if ($check_sm->fetchColumn() > 0) {
            die("Error: Username is already registered.");
        }

        // CREATE EMPLOYEE (Notice employee_id is left out because the trigger handles it!)
        $sql = "INSERT INTO employees (first_name, last_name, email_address, contact_number, username, password) 
                VALUES (:first_name, :last_name, :email_ad, :contact_nb, :username, :password)";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'email_ad'     => $email,
            'contact_nb'   => $new_contact_nb,
            'username'     => $username,
            'password'     => $hashed_password
        ]);

        // FETCH THE GENERATED IDs FOR SESSIONS
        $last_id = $pdo->lastInsertId();
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Portal</title>
</head>
<body>
    <form action="registration-page.php" method="POST">
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
                <input type="password" name="password" class="form-control" placeholder="Password" required pattern="(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}">
                <button type="button" class="info-btn" onclick="alert('Password must have: \n• At least 10 characters \n• 1 uppercase letter \n• 1 number \n• 1 special character (e.g., !@#$)')">?</button>
                </div>
            </div>
            <div class="form-group">
                <input type="password" name="confirm_password" class="form-control" placeholder="Re-write Password" required>
            </div>
            <button type="submit" name="btn_register" class="btn">Sign Up</button>
        </div>
    </form>
</body>
</html>
