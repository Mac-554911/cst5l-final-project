<?php
session_start();
require_once 'config/db.php'; 

// UNAUTHORIZED ACCESS PROTECT
if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit();
}

$message = "";

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

class product_manager {
    private $pdo;

    public function __construct($pdo_instance) {
        $this->pdo = $pdo_instance;
    }

    // AUTOMATIC PRODUCT ID GENERATION
    public function generate_automatic_id() {
        $id_stmt = $this->pdo->query("SELECT product_id FROM products");
        $max_num = 0;
        while ($row = $id_stmt->fetch(PDO::FETCH_ASSOC)) {
            $num = (int) str_replace('PID', '', $row['product_id']);
            if ($num > $max_num) {
                $max_num = $num;
            }
        }
        return "PID" . ($max_num + 1);
    }

    // CREATE PRODUCT
    public function create($name, $brand, $model, $color, $expiry_date, $supplier_id, $stock) {
        // INPUT LENGTH VALIDATION
        if (mb_strlen($name) > 50 || mb_strlen($brand) > 50 || mb_strlen($model) > 50 || mb_strlen($color) > 50) {
            return "Error: Field content exceeds character limits.";
        }

        // TREAT EMPTY EXPIRY AS NULL
        $expiry_date = (!empty($expiry_date)) ? $expiry_date : null;
        
        try {
            $product_id = $this->generate_automatic_id();
            $sql = "INSERT INTO products (product_id, name, brand, model, color, expiry_date, supplier_id, stock) 
                    VALUES (:product_id, :name, :brand, :model, :color, :expiry_date, :supplier_id, :stock)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'product_id'  => $product_id, 'name' => $name, 'brand' => $brand, 
                'model' => $model, 'color' => $color, 'expiry_date' => $expiry_date, 
                'supplier_id' => $supplier_id, 'stock' => $stock
            ]);
            return "success:Product created successfully! Assigned ID: " . $product_id;
        } catch (PDOException $e) {
            return "error:Database Error: " . $e->getMessage();
        }
    }

    // UPDATE PRODUCT
    public function update($product_id, $name, $brand, $model, $color, $expiry_date, $supplier_id, $stock) {
        if (mb_strlen($name) > 50 || mb_strlen($brand) > 50 || mb_strlen($model) > 50 || mb_strlen($color) > 50) {
            return "error:Field content exceeds character limits.";
        }

        // TREAT EMPTY EXPIRY AS NULL
        $expiry_date = (!empty($expiry_date)) ? $expiry_date : null;

        try {
            $sql = "UPDATE products SET name=:name, brand=:brand, model=:model, color=:color, expiry_date=:expiry_date, supplier_id=:supplier_id, stock=:stock WHERE product_id=:product_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'product_id' => $product_id, 'name' => $name, 'brand' => $brand,
                'model' => $model, 'color' => $color, 'expiry_date' => $expiry_date,
                'supplier_id' => $supplier_id, 'stock' => $stock
            ]);
            return "success:Product updated successfully.";
        } catch (PDOException $e) {
            return "error:Database Error: " . $e->getMessage();
        }
    }

    // DELETE PRODUCT
    public function delete($product_id) {
        try {
            $sql = "DELETE FROM products WHERE product_id = :product_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['product_id' => $product_id]);
            return "success:Product deleted successfully.";
        } catch (PDOException $e) {
            return "error:Database Error: " . $e->getMessage();
        }
    }

    // FETCH SINGLE PRODUCT BY ID
    public function fetch_by_id($product_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE product_id = :product_id");
        $stmt->execute(['product_id' => $product_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // SEARCH ALL PRODUCT FIELDS EXCEPT TIMESTAMP
    public function fetch_all_with_filters($search = '') {
        if (!empty($search)) {
            $sql = "SELECT * FROM products WHERE product_id LIKE :s1 OR name LIKE :s2 OR brand LIKE :s3 
                    OR model LIKE :s4 OR color LIKE :s5 OR expiry_date LIKE :s6 OR supplier_id LIKE :s7 OR stock LIKE :s8 ORDER BY created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                's1' => "%$search%", 's2' => "%$search%", 's3' => "%$search%", 's4' => "%$search%",
                's5' => "%$search%", 's6' => "%$search%", 's7' => "%$search%", 's8' => "%$search%"
            ]);
            return $stmt->fetchAll();
        }
        return $this->pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();
    }

    // COUNT TOTAL PRODUCTS
    public function count_total() {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    }

    // COUNT LOW STOCK PRODUCTS (>10)
    public function count_low_stock() {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM products WHERE stock < 10")->fetchColumn();
    }

    // FETCH PRODUCTS EXPIRING WITHIN 30 DAYS (not yet expired)
    public function fetch_expiring_soon() {
        $sql = "SELECT product_id, name, expiry_date FROM products
                WHERE expiry_date IS NOT NULL
                  AND expiry_date >= CURDATE()
                  AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                ORDER BY expiry_date ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // FETCH ALREADY EXPIRED PRODUCTS
    public function fetch_expired() {
        $sql = "SELECT product_id, name, expiry_date FROM products
                WHERE expiry_date IS NOT NULL
                  AND expiry_date < CURDATE()
                ORDER BY expiry_date ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // FETCH LOW STOCK PRODUCT DETAILS (>10)
    public function fetch_low_stock_items() {
        $sql = "SELECT product_id, name, stock FROM products
                WHERE stock < 10
                ORDER BY stock ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}

class product_supply_manager {
    private $pdo;

    public function __construct($pdo_instance) {
        $this->pdo = $pdo_instance;
    }

    // AUTOMATIC SUPPLY ID GENERATION
    public function generate_automatic_id() {
        $id_stmt = $this->pdo->query("SELECT product_supply_id FROM products_supply");
        $max_num = 0;
        while ($row = $id_stmt->fetch(PDO::FETCH_ASSOC)) {
            $num = (int) str_replace('PSID', '', $row['product_supply_id']);
            if ($num > $max_num) {
                $max_num = $num;
            }
        }
        return "PSID" . ($max_num + 1);
    }

    // CREATE SUPPLY RECORD
    public function create($product_id, $supplier_id, $quantity, $supply_date) {
        if ((int)$quantity <= 0) {
            return "error:Quantity must be greater than zero.";
        }
        try {
            $supply_id = $this->generate_automatic_id();
            $sql = "INSERT INTO products_supply (product_supply_id, product_id, supplier_id, quantity, delivery_date)
                    VALUES (:product_supply_id, :product_id, :supplier_id, :quantity, :delivery_date)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'product_supply_id' => $supply_id,
                'product_id'        => $product_id,
                'supplier_id'       => $supplier_id,
                'quantity'          => (int)$quantity,
                'delivery_date'     => $supply_date
            ]);
            // UPDATE PRODUCT STOCK
            $upd = $this->pdo->prepare("UPDATE products SET stock = stock + :qty WHERE product_id = :pid");
            $upd->execute(['qty' => (int)$quantity, 'pid' => $product_id]);
            return "success:Supply record created! Assigned ID: " . $supply_id . ". Stock updated.";
        } catch (PDOException $e) {
            return "error:Database Error: " . $e->getMessage();
        }
    }

    // UPDATE SUPPLY RECORD
    public function update($supply_id, $product_id, $supplier_id, $quantity, $supply_date) {
        if ((int)$quantity <= 0) {
            return "error:Quantity must be greater than zero.";
        }
        try {
            // REVERT OLD STOCK CHANGE BEFORE APPLYING NEW VALUE
            $old = $this->pdo->prepare("SELECT product_id, quantity FROM products_supply WHERE product_supply_id = :sid");
            $old->execute(['sid' => $supply_id]);
            $old_row = $old->fetch(PDO::FETCH_ASSOC);
            if ($old_row) {
                $revert = $this->pdo->prepare("UPDATE products SET stock = stock - :qty WHERE product_id = :pid");
                $revert->execute(['qty' => $old_row['quantity'], 'pid' => $old_row['product_id']]);
            }
            $sql = "UPDATE products_supply SET product_id=:product_id, supplier_id=:supplier_id, quantity=:quantity, delivery_date=:delivery_date WHERE product_supply_id=:supply_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'supply_id'   => $supply_id,
                'product_id'  => $product_id,
                'supplier_id' => $supplier_id,
                'quantity'    => (int)$quantity,
                'delivery_date' => $supply_date
            ]);
            // APPLY NEW STOCK AMOUNT
            $upd = $this->pdo->prepare("UPDATE products SET stock = stock + :qty WHERE product_id = :pid");
            $upd->execute(['qty' => (int)$quantity, 'pid' => $product_id]);
            return "success:Supply record updated successfully.";
        } catch (PDOException $e) {
            return "error:Database Error: " . $e->getMessage();
        }
    }

    // DELETE SUPPLY RECORD
    public function delete($supply_id) {
        try {
            // REVERT STOCK ON DELETION
            $old = $this->pdo->prepare("SELECT product_id, quantity FROM products_supply WHERE product_supply_id = :sid");
            $old->execute(['sid' => $supply_id]);
            $old_row = $old->fetch(PDO::FETCH_ASSOC);
            if ($old_row) {
                $revert = $this->pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - :qty) WHERE product_id = :pid");
                $revert->execute(['qty' => $old_row['quantity'], 'pid' => $old_row['product_id']]);
            }
            $sql = "DELETE FROM products_supply WHERE product_supply_id = :product_supply_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['product_supply_id' => $supply_id]);
            return "success:Supply record deleted successfully.";
        } catch (PDOException $e) {
            return "error:Database Error: " . $e->getMessage();
        }
    }

    // FETCH ALL SUPPLY RECORDS WITH FILTERS
    public function fetch_all_with_filters($search = '') {
        if (!empty($search)) {
            $sql = "SELECT ps.*, p.name AS product_name, s.name AS supplier_name
                    FROM products_supply ps
                    LEFT JOIN products p ON ps.product_id = p.product_id
                    LEFT JOIN suppliers s ON ps.supplier_id = s.supplier_id
                    WHERE ps.product_supply_id LIKE :s1 OR ps.product_id LIKE :s2 OR ps.supplier_id LIKE :s3
                       OR p.name LIKE :s4 OR s.name LIKE :s5 OR ps.delivery_date LIKE :s6
                    ORDER BY ps.delivery_date DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                's1' => "%$search%", 's2' => "%$search%", 's3' => "%$search%",
                's4' => "%$search%", 's5' => "%$search%", 's6' => "%$search%"
            ]);
            return $stmt->fetchAll();
        }
        $sql = "SELECT ps.*, p.name AS product_name, s.name AS supplier_name
                FROM products_supply ps
                LEFT JOIN products p ON ps.product_id = p.product_id
                LEFT JOIN suppliers s ON ps.supplier_id = s.supplier_id
                ORDER BY ps.delivery_date DESC";
        return $this->pdo->query($sql)->fetchAll();
    }

    // COUNT TOTAL SUPPLY RECORDS
    public function count_total() {
        try {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM products_supply")->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
}

class supplier_manager {
    private $pdo;

    public function __construct($pdo_instance) {
        $this->pdo = $pdo_instance;
    }

    // AUTOMATIC SUPPLIER ID GENERATION
    public function generate_automatic_id() {
        $id_stmt = $this->pdo->query("SELECT supplier_id FROM suppliers");
        $max_num = 0;
        while ($row = $id_stmt->fetch(PDO::FETCH_ASSOC)) {
            $num = (int) str_replace('SID', '', $row['supplier_id']);
            if ($num > $max_num) {
                $max_num = $num;
            }
        }
        return "SID" . ($max_num + 1);
    }

    // CREATE SUPPLIER
    public function create($name, $email_ad, $contact_nb) {
        // INPUT LENGTH VALIDATION
        if (mb_strlen($name) > 50 || mb_strlen($email_ad) > 100 || mb_strlen($contact_nb) > 20) {
            return "error:Field content exceeds character limits.";
        }
        // CONTACT NUMBER VALIDATION
        $new_contact = preg_replace('/[^0-9+]/', '', $contact_nb);
        if (!preg_match('/^\+?\d{7,15}$/', $new_contact)) {
            return "error:Invalid contact number format. Please enter a valid numerical phone number.";
        }
        // EMAIL VALIDATION
        if (!filter_var($email_ad, FILTER_VALIDATE_EMAIL)) {
            return "error:Invalid email address format.";
        }
        
        try {
            $supplier_id = $this->generate_automatic_id();
            $sql = "INSERT INTO suppliers (supplier_id, name, email_address, contact_number) VALUES (:supplier_id, :name, :email_ad, :contact_nb)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['supplier_id' => $supplier_id, 'name' => $name, 'email_ad' => $email_ad, 'contact_nb' => $new_contact]);
            return "success:Supplier created successfully! Assigned ID: " . $supplier_id;
        } catch (PDOException $e) {
            return "error:Database Error: " . $e->getMessage();
        }
    }

    // UPDATE SUPPLIER
    public function update($supplier_id, $name, $email_ad, $contact_nb) {
        if (mb_strlen($name) > 50 || mb_strlen($email_ad) > 100 || mb_strlen($contact_nb) > 20) {
            return "error:Field content exceeds character limits.";
        }
        $new_contact = preg_replace('/[^0-9+]/', '', $contact_nb);
        if (!preg_match('/^\+?\d{7,15}$/', $new_contact)) {
            return "error:Invalid contact number format.";
        }
        if (!filter_var($email_ad, FILTER_VALIDATE_EMAIL)) {
            return "error:Invalid email address format.";
        }
        try {
            $sql = "UPDATE suppliers SET name=:name, email_address=:email_ad, contact_number=:contact_nb WHERE supplier_id=:supplier_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['supplier_id' => $supplier_id, 'name' => $name, 'email_ad' => $email_ad, 'contact_nb' => $new_contact]);
            return "success:Supplier updated successfully.";
        } catch (PDOException $e) {
            return "error:Database Error: " . $e->getMessage();
        }
    }

    // DELETE SUPPLIER
    public function delete($supplier_id) {
        // DEPENDENCY CHECK
        $check = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = :supplier_id");
        $check->execute(['supplier_id' => $supplier_id]);
        if ($check->fetchColumn() > 0) {
            return "error:Cannot delete this supplier. Active products are currently assigned to them!";
        }
        
        try {
            $sql = "DELETE FROM suppliers WHERE supplier_id = :supplier_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['supplier_id' => $supplier_id]);
            return "success:Supplier deleted successfully.";
        } catch (PDOException $e) {
            return "error:Database Error: " . $e->getMessage();
        }
    }

    // SEARCH ALL SUPPLIER FIELDS EXCEPT TIMESTAMP
    public function fetch_all_with_filters($search = '') {
        if (!empty($search)) {
            $sql = "SELECT * FROM suppliers WHERE supplier_id LIKE :s1 OR name LIKE :s2 OR email_address LIKE :s3 OR contact_number LIKE :s4 ORDER BY created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                's1' => "%$search%", 's2' => "%$search%", 's3' => "%$search%", 's4' => "%$search%"
            ]);
            return $stmt->fetchAll();
        }
        return $this->pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();
    }

    // COUNT TOTAL SUPPLIERS
    public function count_total() {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
    }
}

class profile_manager {
    private $pdo;

    public function __construct($pdo_instance) {
        $this->pdo = $pdo_instance;
    }

    // UPDATE EMPLOYEE PROFILE
    public function update_profile($user_id, $first_name, $last_name, $email_address, $contact_number, $username) {
        if (mb_strlen($first_name) > 50 || mb_strlen($last_name) > 50) {
            return "error:Name exceeds character limits.";
        }
        if (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
            return "error:Invalid email address format.";
        }
        $new_contact = preg_replace('/[^0-9+]/', '', $contact_number);
        if (!preg_match('/^\+?\d{7,15}$/', $new_contact)) {
            return "error:Invalid contact number format.";
        }
        try {
            // USERNAME AVAILABILITY CHECK (exclude current user)
            $check = $this->pdo->prepare("SELECT COUNT(*) FROM employees WHERE username = :username AND id != :user_id");
            $check->execute(['username' => $username, 'user_id' => $user_id]);
            if ($check->fetchColumn() > 0) {
                return "error:Username is already taken by another account.";
            }
            $sql = "UPDATE employees SET first_name=:first_name, last_name=:last_name, email_address=:email_address, contact_number=:contact_number, username=:username WHERE id=:user_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'first_name'     => $first_name,
                'last_name'      => $last_name,
                'email_address'  => $email_address,
                'contact_number' => $new_contact,
                'username'       => $username,
                'user_id'        => $user_id
            ]);
            // UPDATE SESSION VALUES
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name']  = $last_name;
            $_SESSION['username']   = $username;
            return "success:Profile updated successfully.";
        } catch (PDOException $e) {
            return "error:Database Error: " . $e->getMessage();
        }
    }

    // CHANGE PASSWORD
    public function change_password($user_id, $current_password, $new_password, $confirm_password) {
        if ($new_password !== $confirm_password) {
            return "error:New passwords do not match.";
        }
        $password_pattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/';
        if (!preg_match($password_pattern, $new_password)) {
            return "error:New password must be 10+ chars with 1 uppercase, 1 number, and 1 special symbol.";
        }
        try {
            $stmt = $this->pdo->prepare("SELECT password FROM employees WHERE id = :user_id");
            $stmt->execute(['user_id' => $user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || !password_verify($current_password, $user['password'])) {
                return "error:Current password is incorrect.";
            }
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $upd = $this->pdo->prepare("UPDATE employees SET password = :password WHERE id = :user_id");
            $upd->execute(['password' => $hashed, 'user_id' => $user_id]);
            return "success:Password changed successfully.";
        } catch (PDOException $e) {
            return "error:Database Error: " . $e->getMessage();
        }
    }

    // FETCH EMPLOYEE PROFILE
    public function fetch_profile($user_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM employees WHERE id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

class dashboard_controller {
    private $product_mgr;
    private $supply_mgr;
    private $supplier_mgr;
    private $profile_mgr;
    public $message = "";

    public function __construct($pdo_instance) {
        $this->product_mgr  = new product_manager($pdo_instance);
        $this->supply_mgr   = new product_supply_manager($pdo_instance);
        $this->supplier_mgr = new supplier_manager($pdo_instance);
        $this->profile_mgr  = new profile_manager($pdo_instance);
    }

    // ROUTE USER REQUEST SUBMISSIONS
    public function handle_post_requests() {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // ROUTE PRODUCT CREATION
            if (isset($_POST['btn_create_product'])) {
                $this->message = $this->product_mgr->create(
                    trim($_POST['name']), trim($_POST['brand']), trim($_POST['model']), 
                    trim($_POST['color']), $_POST['expiry_date'], $_POST['supplier_id'], $_POST['stock']
                );
            }
            // ROUTE PRODUCT UPDATE
            if (isset($_POST['btn_update_product'])) {
                $this->message = $this->product_mgr->update(
                    $_POST['product_id'], trim($_POST['name']), trim($_POST['brand']), trim($_POST['model']),
                    trim($_POST['color']), $_POST['expiry_date'], $_POST['supplier_id'], $_POST['stock']
                );
            }
            // ROUTE PRODUCT DELETION
            if (isset($_POST['btn_delete_product'])) {
                $this->message = $this->product_mgr->delete($_POST['product_id']);
            }
            // ROUTE SUPPLY CREATION
            if (isset($_POST['btn_create_supply'])) {
                $this->message = $this->supply_mgr->create(
                    $_POST['supply_product_id'], $_POST['supply_supplier_id'], $_POST['supply_quantity'], $_POST['supply_date']
                );
            }
            // ROUTE SUPPLY UPDATE
            if (isset($_POST['btn_update_supply'])) {
                $this->message = $this->supply_mgr->update(
                    $_POST['supply_id'], $_POST['supply_product_id'], $_POST['supply_supplier_id'], $_POST['supply_quantity'], $_POST['supply_date']
                );
            }
            // ROUTE SUPPLY DELETION
            if (isset($_POST['btn_delete_supply'])) {
                $this->message = $this->supply_mgr->delete($_POST['supply_id']);
            }
            // ROUTE SUPPLIER CREATION
            if (isset($_POST['btn_create_supplier'])) {
                $this->message = $this->supplier_mgr->create(
                    trim($_POST['supplier_name']), trim($_POST['supplier_email']), trim($_POST['supplier_contact'])
                );
            }
            // ROUTE SUPPLIER UPDATE
            if (isset($_POST['btn_update_supplier'])) {
                $this->message = $this->supplier_mgr->update(
                    $_POST['supplier_id'], trim($_POST['supplier_name']), trim($_POST['supplier_email']), trim($_POST['supplier_contact'])
                );
            }
            // ROUTE SUPPLIER DELETION
            if (isset($_POST['btn_delete_supplier'])) {
                $this->message = $this->supplier_mgr->delete($_POST['supplier_id']);
            }
            // ROUTE PROFILE UPDATE
            if (isset($_POST['btn_update_profile'])) {
                $this->message = $this->profile_mgr->update_profile(
                    $_SESSION['user_id'], trim($_POST['first_name']), trim($_POST['last_name']),
                    trim($_POST['email_address']), trim($_POST['contact_number']), trim($_POST['username'])
                );
            }
            // ROUTE PASSWORD CHANGE
            if (isset($_POST['btn_change_password'])) {
                $this->message = $this->profile_mgr->change_password(
                    $_SESSION['user_id'], $_POST['current_password'], $_POST['new_password'], $_POST['confirm_new_password']
                );
            }
        }
    }

    public function get_products($search = '') { return $this->product_mgr->fetch_all_with_filters($search); }
    public function get_supplies($search = '')  { return $this->supply_mgr->fetch_all_with_filters($search); }
    public function get_suppliers($search = '') { return $this->supplier_mgr->fetch_all_with_filters($search); }
    public function get_profile()               { return $this->profile_mgr->fetch_profile($_SESSION['user_id']); }
    public function get_expiring_soon()         { return $this->product_mgr->fetch_expiring_soon(); }
    public function get_expired()               { return $this->product_mgr->fetch_expired(); }
    public function get_low_stock_items()       { return $this->product_mgr->fetch_low_stock_items(); }

    // DASHBOARD STAT AGGREGATION
    public function get_stats() {
        return [
            'total_products'  => $this->product_mgr->count_total(),
            'total_suppliers' => $this->supplier_mgr->count_total(),
            'total_supplies'  => $this->supply_mgr->count_total(),
            'low_stock'       => $this->product_mgr->count_low_stock(),
        ];
    }
}

// APP INITIALIZATION CORNER
$controller = new dashboard_controller($pdo);
$controller->handle_post_requests();

$active_tab    = $_GET['tab'] ?? 'dashboard';
$search_query  = trim($_GET['search'] ?? '');

$products_list  = $controller->get_products($search_query);
$supplies_list  = $controller->get_supplies($search_query);
$suppliers_list = $controller->get_suppliers($search_query);
$profile_data   = $controller->get_profile();
$stats          = $controller->get_stats();

// FETCH EXPIRY AND LOW STOCK DATA FOR NOTIFICATIONS
$expiring_soon   = $controller->get_expiring_soon();
$expired_items   = $controller->get_expired();
$low_stock_items = $controller->get_low_stock_items();

// TOTAL NOTIFICATION COUNT FOR BELL BADGE
$notif_count = count($expiring_soon) + count($expired_items) + count($low_stock_items);

// PARSE MESSAGE TYPE (success: or error: prefix)
$message_type = 'info';
$message_text = $controller->message;
if (strpos($controller->message, 'success:') === 0) {
    $message_type = 'success';
    $message_text = substr($controller->message, 8);
} elseif (strpos($controller->message, 'error:') === 0) {
    $message_type = 'error';
    $message_text = substr($controller->message, 6);
}

// USER DISPLAY NAME AND INITIALS
$display_name = trim(($profile_data['first_name'] ?? $_SESSION['first_name'] ?? '') . ' ' . ($profile_data['last_name'] ?? $_SESSION['last_name'] ?? ''));
$initials = strtoupper(substr($profile_data['first_name'] ?? $_SESSION['first_name'] ?? 'U', 0, 1) . substr($profile_data['last_name'] ?? $_SESSION['last_name'] ?? '', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kawaii Store IMS — Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="dashboard-layout">

    <!-- SIDEBAR NAVIGATION -->
    <div class="sidebar">
        <div class="sidebar_brand">
            <div class="sidebar_brand-icon">🌸</div>
            <div>
                <div class="sidebar_brand-text">Kawaii Store</div>
                <div class="sidebar_brand-sub">IMS</div>
            </div>
        </div>

        <div class="sidebar_section-label">Menu</div>
        <nav class="sidebar_nav">
            <a href="?tab=dashboard" class="sidebar_nav-item <?php echo $active_tab === 'dashboard' ? 'is-active' : ''; ?>">
                <span class="sidebar_nav-icon">📊</span> Dashboard
            </a>
            <a href="?tab=products" class="sidebar_nav-item <?php echo $active_tab === 'products' ? 'is-active' : ''; ?>">
                <span class="sidebar_nav-icon">📦</span> Products
            </a>
            <a href="?tab=product_supply" class="sidebar_nav-item <?php echo $active_tab === 'product_supply' ? 'is-active' : ''; ?>">
                <span class="sidebar_nav-icon">🚚</span> Product Supply
            </a>
            <a href="?tab=suppliers" class="sidebar_nav-item <?php echo $active_tab === 'suppliers' ? 'is-active' : ''; ?>">
                <span class="sidebar_nav-icon">🏭</span> Suppliers
            </a>
            <a href="?tab=system_activity" class="sidebar_nav-item <?php echo $active_tab === 'system_activity' ? 'is-active' : ''; ?>">
                <span class="sidebar_nav-icon">📋</span> System Activity
            </a>
        </nav>

        <div class="sidebar_footer">
            <!-- CLICKABLE USER PROFILE (opens profile dialog) -->
            <div class="sidebar_user" onclick="document.getElementById('dlg_profile').classList.add('is-open')" title="Edit your profile">
                <div class="sidebar_user-avatar"><?php echo security_helper::xss_clean($initials); ?></div>
                <div>
                    <div class="sidebar_user-name"><?php echo security_helper::xss_clean($display_name); ?></div>
                    <div class="sidebar_user-id"><?php echo security_helper::xss_clean($_SESSION['employee_id']); ?></div>
                </div>
            </div>
            <a href="logout.php" class="btn btn--danger btn--full btn--sm">Logout</a>
        </div>
    </div>

    <!-- MAIN CONTENT AREA -->
    <div class="main-content">

        <!-- STICKY TOP BAR -->
         <div class="topbar">
            <span class="topbar_title">
                <?php
                $tab_titles = [
                    'dashboard'       => '📊 Dashboard',
                    'products'        => '📦 Products',
                    'product_supply'  => '🚚 Product Supply',
                    'suppliers'       => '🏭 Suppliers',
                    'system_activity' => '📋 System Activity',
                ];
                echo $tab_titles[$active_tab] ?? 'Dashboard';
                ?>
            </span>
            <div class="topbar_right">
                
                <form method="GET" action="" class="topbar_search" id="frm_search">
                    <input type="hidden" name="tab" value="<?php echo security_helper::xss_clean($active_tab); ?>">
                    
                    <div class="topbar_search-input-wrapper">
                        <input type="text" 
                               name="search" 
                               class="form-control" 
                               placeholder="Search..." 
                               value="<?php echo security_helper::xss_clean($search_query); ?>"
                               autocomplete="off">

                        <button type="submit" class="topbar_search-submit-btn" aria-label="Submit Search">
                            <svg viewBox="0 0 24 24">
                                <circle cx="11" cy="11" r="8"/>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                        </button>
                    </div>

                    <?php if (!empty($search_query)): ?>
                        <a href="?tab=<?php echo security_helper::xss_clean($active_tab); ?>" class="btn btn--sm btn--ghost">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>

                <!-- NOTIFICATION BELL -->
                <button class="topbar_notif-btn" onclick="document.getElementById('dlg_notifications').classList.add('is-open')" aria-label="Notifications">
                    <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <?php if ($notif_count > 0): ?>
                        <span class="topbar_notif-badge"></span>
                    <?php endif; ?>
                </button>

            </div>
        </div>

        <!-- SCROLLABLE CONTENT PADDING WRAPPER -->
        <div class="content-area">

            <?php if (!empty($message_text)): ?>
                <div class="notifications-panel">
                    <div class="alert alert--<?php echo $message_type; ?>">
                        <div class="alert_body">
                            <div class="alert_message"><?php echo security_helper::xss_clean($message_text); ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- DASHBOARD TAB -->
            <?php if ($active_tab === 'dashboard'): ?>
            <div class="page-section">
                <div class="section-header">
                    <div>
                        <div class="section-title">Welcome back, <?php echo security_helper::xss_clean($profile_data['first_name'] ?? $_SESSION['first_name'] ?? 'there'); ?>!</div>
                        <div class="section-subtitle">Here's what's happening in your store today.</div>
                    </div>
                </div>

                <!-- STAT CARDS ROW -->
                <div class="stat-cards">
                    <div class="stat-card stat-card--purple">
                        <div class="stat-card_icon">📦</div>
                        <div class="stat-card_label">Total Products</div>
                        <div class="stat-card_value"><?php echo $stats['total_products']; ?></div>
                        <div class="stat-card_sub">items in inventory</div>
                    </div>
                    <div class="stat-card stat-card--pink">
                        <div class="stat-card_icon">🚚</div>
                        <div class="stat-card_label">Supply Records</div>
                        <div class="stat-card_value"><?php echo $stats['total_supplies']; ?></div>
                        <div class="stat-card_sub">supply entries logged</div>
                    </div>
                    <div class="stat-card stat-card--blue">
                        <div class="stat-card_icon">🏭</div>
                        <div class="stat-card_label">Suppliers</div>
                        <div class="stat-card_value"><?php echo $stats['total_suppliers']; ?></div>
                        <div class="stat-card_sub">registered partners</div>
                    </div>
                    <div class="stat-card stat-card--mint">
                        <div class="stat-card_icon">⚠️</div>
                        <div class="stat-card_label">Low Stock Items</div>
                        <div class="stat-card_value"><?php echo $stats['low_stock']; ?></div>
                        <div class="stat-card_sub">below 10 units</div>
                    </div>
                </div>

                <!-- RECENT PRODUCTS PREVIEW TABLE -->
                <div class="card mb-6">
                    <div class="card_header">
                        <span class="card_title">Recent Products</span>
                        <a href="?tab=products" class="btn btn--ghost btn--sm">View All →</a>
                    </div>
                    <div class="table-wrapper" style="border:none; border-radius:0; box-shadow:none;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product ID</th>
                                    <th>Name</th>
                                    <th>Brand / Model</th>
                                    <th>Stock</th>
                                    <th>Expiry Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recent = array_slice($products_list, 0, 5);
                                if (empty($recent)): ?>
                                    <tr><td colspan="5" class="table-empty"><div class="table-empty_icon">🌸</div><div class="table-empty_text">No products yet.</div></td></tr>
                                <?php else: foreach ($recent as $prod): ?>
                                    <tr>
                                        <td><span class="mono-id"><?php echo security_helper::xss_clean($prod['product_id']); ?></span></td>
                                        <td><strong><?php echo security_helper::xss_clean($prod['name']); ?></strong></td>
                                        <td><?php echo security_helper::xss_clean($prod['brand'] . ' ' . $prod['model']); ?></td>
                                        <td>
                                            <?php $stock_badge = (int)$prod['stock'] < 10 ? 'badge--low-stock' : 'badge--ok'; ?>
                                            <span class="badge <?php echo $stock_badge; ?>"><?php echo security_helper::xss_clean($prod['stock']); ?> units</span>
                                        </td>
                                        <td><?php echo security_helper::xss_clean($prod['expiry_date']); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- RECENT SUPPLIERS PREVIEW -->
                <div class="card">
                    <div class="card_header">
                        <span class="card_title">Registered Suppliers</span>
                        <a href="?tab=suppliers" class="btn btn--ghost btn--sm">View All →</a>
                    </div>
                    <div class="table-wrapper" style="border:none; border-radius:0; box-shadow:none;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Supplier ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recent_sup = array_slice($suppliers_list, 0, 5);
                                if (empty($recent_sup)): ?>
                                    <tr><td colspan="4" class="table-empty"><div class="table-empty_icon">🏭</div><div class="table-empty_text">No suppliers yet.</div></td></tr>
                                <?php else: foreach ($recent_sup as $sup): ?>
                                    <tr>
                                        <td><span class="mono-id"><?php echo security_helper::xss_clean($sup['supplier_id']); ?></span></td>
                                        <td><strong><?php echo security_helper::xss_clean($sup['name']); ?></strong></td>
                                        <td><?php echo security_helper::xss_clean($sup['email_address']); ?></td>
                                        <td><?php echo security_helper::xss_clean($sup['contact_number']); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- PRODUCTS TAB -->
            <?php elseif ($active_tab === 'products'): ?>
            <div class="page-section">
                <div class="section-header">
                    <div>
                        <div class="section-title">Products</div>
                        <div class="section-subtitle"><?php echo count($products_list); ?> product(s) found</div>
                    </div>
                    <button class="btn btn--primary" onclick="document.getElementById('dlg_add_product').classList.add('is-open')">＋ Add Product</button>
                </div>

                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Name</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Color</th>
                                <th>Stock</th>
                                <th>Expiry Date</th>
                                <th>Supplier ID</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products_list)): ?>
                                <tr><td colspan="9" class="table-empty">
                                    <div class="table-empty_icon">📦</div>
                                    <div class="table-empty_text">No products found.</div>
                                </td></tr>
                            <?php else: foreach ($products_list as $prod): ?>
                                <tr>
                                    <td><span class="mono-id"><?php echo security_helper::xss_clean($prod['product_id']); ?></span></td>
                                    <td><strong><?php echo security_helper::xss_clean($prod['name']); ?></strong></td>
                                    <td><?php echo security_helper::xss_clean($prod['brand']); ?></td>
                                    <td><?php echo security_helper::xss_clean($prod['model']); ?></td>
                                    <td><?php echo security_helper::xss_clean($prod['color']); ?></td>
                                    <td>
                                        <?php $stock_badge = (int)$prod['stock'] < 10 ? 'badge--low-stock' : 'badge--ok'; ?>
                                        <span class="badge <?php echo $stock_badge; ?>"><?php echo security_helper::xss_clean($prod['stock']); ?> units</span>
                                    </td>
                                    <td><?php echo security_helper::xss_clean($prod['expiry_date']); ?></td>
                                    <td><span class="mono-id"><?php echo security_helper::xss_clean($prod['supplier_id']); ?></span></td>
                                    <td class="col-actions">
                                        <div class="table-actions">
                                            <!-- EDIT PRODUCT BUTTON (opens pre-filled dialog)) -->
                                            <button class="btn btn--ghost btn--sm" onclick="open_edit_product(
                                                '<?php echo security_helper::xss_clean($prod['product_id']); ?>',
                                                '<?php echo addslashes(security_helper::xss_clean($prod['name'])); ?>',
                                                '<?php echo addslashes(security_helper::xss_clean($prod['brand'])); ?>',
                                                '<?php echo addslashes(security_helper::xss_clean($prod['model'])); ?>',
                                                '<?php echo addslashes(security_helper::xss_clean($prod['color'])); ?>',
                                                '<?php echo security_helper::xss_clean($prod['expiry_date']); ?>',
                                                '<?php echo security_helper::xss_clean($prod['supplier_id']); ?>',
                                                '<?php echo security_helper::xss_clean($prod['stock']); ?>'
                                            )">✏️ Edit</button>
                                            <!-- DELETE PRODUCT BUTTON -->
                                            <form action="" method="POST" onsubmit="return confirm('Delete this product?');" style="display:inline;">
                                                <input type="hidden" name="product_id" value="<?php echo security_helper::xss_clean($prod['product_id']); ?>">
                                                <input type="hidden" name="tab" value="products">
                                                <button type="submit" name="btn_delete_product" class="btn btn--danger btn--sm">🗑 Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- PRODUCT SUPPLY TAB -->
            <?php elseif ($active_tab === 'product_supply'): ?>
            <div class="page-section">
                <div class="section-header">
                    <div>
                        <div class="section-title">Product Supply</div>
                        <div class="section-subtitle"><?php echo count($supplies_list); ?> supply record(s) found</div>
                    </div>
                    <button class="btn btn--primary" onclick="document.getElementById('dlg_add_supply').classList.add('is-open')">＋ Add Supply</button>
                </div>

                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Supply ID</th>
                                <th>Product</th>
                                <th>Supplier</th>
                                <th>Quantity</th>
                                <th>Supply Date</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($supplies_list)): ?>
                                <tr><td colspan="6" class="table-empty">
                                    <div class="table-empty_icon">🚚</div>
                                    <div class="table-empty_text">No supply records found.</div>
                                </td></tr>
                            <?php else: foreach ($supplies_list as $sup): ?>
                                <tr>
                                    <td><span class="mono-id"><?php echo security_helper::xss_clean($sup['product_supply_id']); ?></span></td>
                                    <td>
                                        <strong><?php echo security_helper::xss_clean($sup['product_name'] ?? ''); ?></strong><br>
                                        <span class="mono-id"><?php echo security_helper::xss_clean($sup['product_id']); ?></span>
                                    </td>
                                    <td>
                                        <?php echo security_helper::xss_clean($sup['supplier_name'] ?? ''); ?><br>
                                        <span class="mono-id"><?php echo security_helper::xss_clean($sup['supplier_id']); ?></span>
                                    </td>
                                    <td><span class="badge badge--ok"><?php echo security_helper::xss_clean($sup['quantity']); ?> units</span></td>
                                    <td><?php echo security_helper::xss_clean($sup['delivery_date']); ?></td>
                                    <td class="col-actions">
                                        <div class="table-actions">
                                            <button class="btn btn--ghost btn--sm" onclick="open_edit_supply(
                                                '<?php echo security_helper::xss_clean($sup['product_supply_id']); ?>',
                                                '<?php echo security_helper::xss_clean($sup['product_id']); ?>',
                                                '<?php echo security_helper::xss_clean($sup['supplier_id']); ?>',
                                                '<?php echo security_helper::xss_clean($sup['quantity']); ?>',
                                                '<?php echo security_helper::xss_clean($sup['delivery_date']); ?>'
                                            )">✏️ Edit</button>
                                            <form action="" method="POST" onsubmit="return confirm('Delete this supply record?');" style="display:inline;">
                                                <input type="hidden" name="supply_id" value="<?php echo security_helper::xss_clean($sup['product_supply_id']); ?>">
                                                <input type="hidden" name="tab" value="product_supply">
                                                <button type="submit" name="btn_delete_supply" class="btn btn--danger btn--sm">🗑 Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SUPPLIERS TAB -->
            <?php elseif ($active_tab === 'suppliers'): ?>
            <div class="page-section">
                <div class="section-header">
                    <div>
                        <div class="section-title">Suppliers</div>
                        <div class="section-subtitle"><?php echo count($suppliers_list); ?> supplier(s) found</div>
                    </div>
                    <button class="btn btn--primary" onclick="document.getElementById('dlg_add_supplier').classList.add('is-open')">＋ Add Supplier</button>
                </div>

                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Supplier ID</th>
                                <th>Name</th>
                                <th>Email Address</th>
                                <th>Contact Number</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($suppliers_list)): ?>
                                <tr><td colspan="5" class="table-empty">
                                    <div class="table-empty_icon">🏭</div>
                                    <div class="table-empty_text">No suppliers registered yet.</div>
                                </td></tr>
                            <?php else: foreach ($suppliers_list as $sup): ?>
                                <tr>
                                    <td><span class="mono-id"><?php echo security_helper::xss_clean($sup['supplier_id']); ?></span></td>
                                    <td><strong><?php echo security_helper::xss_clean($sup['name']); ?></strong></td>
                                    <td><?php echo security_helper::xss_clean($sup['email_address']); ?></td>
                                    <td><?php echo security_helper::xss_clean($sup['contact_number']); ?></td>
                                    <td class="col-actions">
                                        <div class="table-actions">
                                            <button class="btn btn--ghost btn--sm" onclick="open_edit_supplier(
                                                '<?php echo security_helper::xss_clean($sup['supplier_id']); ?>',
                                                '<?php echo addslashes(security_helper::xss_clean($sup['name'])); ?>',
                                                '<?php echo addslashes(security_helper::xss_clean($sup['email_address'])); ?>',
                                                '<?php echo security_helper::xss_clean($sup['contact_number']); ?>'
                                            )">✏️ Edit</button>
                                            <form action="" method="POST" onsubmit="return confirm('Delete this supplier?');" style="display:inline;">
                                                <input type="hidden" name="supplier_id" value="<?php echo security_helper::xss_clean($sup['supplier_id']); ?>">
                                                <input type="hidden" name="tab" value="suppliers">
                                                <button type="submit" name="btn_delete_supplier" class="btn btn--danger btn--sm">🗑 Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SYSTEM ACTIVITY TAB -->
            <?php elseif ($active_tab === 'system_activity'): ?>
            <div class="page-section">
                <div class="section-header">
                    <div>
                        <div class="section-title">System Activity</div>
                        <div class="section-subtitle">Overview of all records in the system.</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card_body">
                        <div class="activity-entry">
                            <div class="activity-entry_dot" style="background:#c084db;"></div>
                            <div class="activity-entry_meta">
                                <div class="activity-entry_type">Products</div>
                                <div class="activity-entry_name"><?php echo $stats['total_products']; ?> total product(s) in inventory</div>
                                <div class="activity-entry_time"><?php echo $stats['low_stock']; ?> item(s) below minimum stock threshold (10 units)</div>
                            </div>
                        </div>
                        <div class="activity-entry">
                            <div class="activity-entry_dot" style="background:#f472a8;"></div>
                            <div class="activity-entry_meta">
                                <div class="activity-entry_type">Product Supply</div>
                                <div class="activity-entry_name"><?php echo $stats['total_supplies']; ?> supply record(s) logged</div>
                                <div class="activity-entry_time">Stock levels updated automatically on supply creation and deletion</div>
                            </div>
                        </div>
                        <div class="activity-entry">
                            <div class="activity-entry_dot" style="background:#93c5fd;"></div>
                            <div class="activity-entry_meta">
                                <div class="activity-entry_type">Suppliers</div>
                                <div class="activity-entry_name"><?php echo $stats['total_suppliers']; ?> supplier partner(s) registered</div>
                                <div class="activity-entry_time">Suppliers with active product links cannot be deleted</div>
                            </div>
                        </div>
                        <div class="activity-entry">
                            <div class="activity-entry_dot" style="background:#6bcb9a;"></div>
                            <div class="activity-entry_meta">
                                <div class="activity-entry_type">Session</div>
                                <div class="activity-entry_name">Logged in as <?php echo security_helper::xss_clean($display_name); ?></div>
                                <div class="activity-entry_time">Employee ID: <?php echo security_helper::xss_clean($_SESSION['employee_id']); ?> · Username: <?php echo security_helper::xss_clean($_SESSION['username']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FULL SUPPLY LOG TABLE -->
                <div style="margin-top:var(--space-6);">
                    <div class="section-header" style="margin-bottom:var(--space-4);">
                        <div class="section-title" style="font-size:var(--text-lg);">Recent Supply Records</div>
                    </div>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead><tr>
                                <th>Supply ID</th><th>Product</th><th>Supplier</th><th>Quantity</th><th>Date</th>
                            </tr></thead>
                            <tbody>
                                <?php if (empty($supplies_list)): ?>
                                    <tr><td colspan="5" class="table-empty"><div class="table-empty_icon">🚚</div><div class="table-empty_text">No supply records yet.</div></td></tr>
                                <?php else: foreach (array_slice($supplies_list, 0, 10) as $s): ?>
                                    <tr>
                                        <td><span class="mono-id"><?php echo security_helper::xss_clean($s['product_supply_id']); ?></span></td>
                                        <td><?php echo security_helper::xss_clean($s['product_name'] ?? $s['product_id']); ?></td>
                                        <td><?php echo security_helper::xss_clean($s['supplier_name'] ?? $s['supplier_id']); ?></td>
                                        <td><span class="badge badge--ok"><?php echo security_helper::xss_clean($s['quantity']); ?> units</span></td>
                                        <td><?php echo security_helper::xss_clean($s['delivery_date']); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>


<!-- ADD PRODUCT DIALOG -->
<div class="dialog-overlay" id="dlg_add_product" onclick="if(event.target===this)this.classList.remove('is-open')">
    <div class="dialog">
        <div class="dialog_header">
            <span class="dialog_title">Add New Product</span>
            <button class="dialog_close" onclick="document.getElementById('dlg_add_product').classList.remove('is-open')">&times;</button>
        </div>
        <form action="?tab=products" method="POST">
            <div class="dialog_body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Plushie Bear" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Brand</label>
                        <input type="text" name="brand" class="form-control" placeholder="Brand" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Model</label>
                        <input type="text" name="model" class="form-control" placeholder="Model" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Color</label>
                        <input type="text" name="color" class="form-control" placeholder="Color" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Initial Stock</label>
                        <input type="number" name="stock" class="form-control" placeholder="0" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Supplier</label>
                    <select name="supplier_id" class="form-control" required>
                        <option value="">— Select Supplier —</option>
                        <?php foreach ($suppliers_list as $s): ?>
                            <option value="<?php echo security_helper::xss_clean($s['supplier_id']); ?>">
                                <?php echo security_helper::xss_clean($s['name'] . ' (' . $s['supplier_id'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="dialog_footer">
                <button type="button" class="btn btn--ghost" onclick="document.getElementById('dlg_add_product').classList.remove('is-open')">Cancel</button>
                <button type="submit" name="btn_create_product" class="btn btn--primary">Save Product</button>
            </div>
        </form>
        </div>
    </div>
</div>

<!-- EDIT PRODUCT DIALOG -->
<div class="dialog-overlay" id="dlg_edit_product" onclick="if(event.target===this)this.classList.remove('is-open')">
    <div class="dialog">
        <div class="dialog_header">
            <span class="dialog_title">Edit Product</span>
            <button class="dialog_close" onclick="document.getElementById('dlg_edit_product').classList.remove('is-open')">&times;</button>
        </div>
        <form action="?tab=products" method="POST">
            <input type="hidden" name="product_id" id="edit_product_id">
            <div class="dialog_body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" id="edit_product_name" class="form-control" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Brand</label>
                        <input type="text" name="brand" id="edit_product_brand" class="form-control" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Model</label>
                        <input type="text" name="model" id="edit_product_model" class="form-control" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Color</label>
                        <input type="text" name="color" id="edit_product_color" class="form-control" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock</label>
                        <input type="number" name="stock" id="edit_product_stock" class="form-control" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" id="edit_product_expiry" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Supplier</label>
                    <select name="supplier_id" id="edit_product_supplier" class="form-control" required>
                        <option value="">— Select Supplier —</option>
                        <?php foreach ($suppliers_list as $s): ?>
                            <option value="<?php echo security_helper::xss_clean($s['supplier_id']); ?>">
                                <?php echo security_helper::xss_clean($s['name'] . ' (' . $s['supplier_id'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="dialog_footer">
                <button type="button" class="btn btn--ghost" onclick="document.getElementById('dlg_edit_product').classList.remove('is-open')">Cancel</button>
                <button type="submit" name="btn_update_product" class="btn btn--primary">Update Product</button>
            </div>
        </form>
        </div>
    </div>
</div>

<!-- ADD SUPPLY DIALOG -->
<div class="dialog-overlay" id="dlg_add_supply" onclick="if(event.target===this)this.classList.remove('is-open')">
    <div class="dialog">
        <div class="dialog_header">
            <span class="dialog_title">Add Supply Record</span>
            <button class="dialog_close" onclick="document.getElementById('dlg_add_supply').classList.remove('is-open')">&times;</button>
        </div>
        <form action="?tab=product_supply" method="POST">
            <div class="dialog_body">
                <div class="form-group">
                    <label class="form-label">Product</label>
                    <select name="supply_product_id" class="form-control" required>
                        <option value="">— Select Product —</option>
                        <?php foreach ($products_list as $p): ?>
                            <option value="<?php echo security_helper::xss_clean($p['product_id']); ?>">
                                <?php echo security_helper::xss_clean($p['name'] . ' (' . $p['product_id'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Supplier</label>
                    <select name="supply_supplier_id" class="form-control" required>
                        <option value="">— Select Supplier —</option>
                        <?php foreach ($suppliers_list as $s): ?>
                            <option value="<?php echo security_helper::xss_clean($s['supplier_id']); ?>">
                                <?php echo security_helper::xss_clean($s['name'] . ' (' . $s['supplier_id'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="supply_quantity" class="form-control" placeholder="Units" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supply Date</label>
                        <input type="date" name="supply_date" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="dialog_footer">
                <button type="button" class="btn btn--ghost" onclick="document.getElementById('dlg_add_supply').classList.remove('is-open')">Cancel</button>
                <button type="submit" name="btn_create_supply" class="btn btn--primary">Save Supply</button>
            </div>
        </form>
        </div>
    </div>
</div>

<!-- EDIT SUPPLY DIALOG -->
<div class="dialog-overlay" id="dlg_edit_supply" onclick="if(event.target===this)this.classList.remove('is-open')">
    <div class="dialog">
        <div class="dialog_header">
            <span class="dialog_title">Edit Supply Record</span>
            <button class="dialog_close" onclick="document.getElementById('dlg_edit_supply').classList.remove('is-open')">&times;</button>
        </div>
        <form action="?tab=product_supply" method="POST">
            <input type="hidden" name="supply_id" id="edit_supply_id">
            <div class="dialog_body">
                <div class="form-group">
                    <label class="form-label">Product</label>
                    <select name="supply_product_id" id="edit_supply_product_id" class="form-control" required>
                        <option value="">— Select Product —</option>
                        <?php foreach ($products_list as $p): ?>
                            <option value="<?php echo security_helper::xss_clean($p['product_id']); ?>">
                                <?php echo security_helper::xss_clean($p['name'] . ' (' . $p['product_id'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Supplier</label>
                    <select name="supply_supplier_id" id="edit_supply_supplier_id" class="form-control" required>
                        <option value="">— Select Supplier —</option>
                        <?php foreach ($suppliers_list as $s): ?>
                            <option value="<?php echo security_helper::xss_clean($s['supplier_id']); ?>">
                                <?php echo security_helper::xss_clean($s['name'] . ' (' . $s['supplier_id'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="supply_quantity" id="edit_supply_quantity" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supply Date</label>
                        <input type="date" name="supply_date" id="edit_supply_date" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="dialog_footer">
                <button type="button" class="btn btn--ghost" onclick="document.getElementById('dlg_edit_supply').classList.remove('is-open')">Cancel</button>
                <button type="submit" name="btn_update_supply" class="btn btn--primary">Update Supply</button>
            </div>
        </form>
        </div>
    </div>
</div>

<!-- ADD SUPPLIER DIALOG -->
<div class="dialog-overlay" id="dlg_add_supplier" onclick="if(event.target===this)this.classList.remove('is-open')">
    <div class="dialog">
        <div class="dialog_header">
            <span class="dialog_title">Add Supplie</span>
            <button class="dialog_close" onclick="document.getElementById('dlg_add_supplier').classList.remove('is-open')">&times;</button>
        </div>
        <form action="?tab=suppliers" method="POST">
            <div class="dialog_body">
                <div class="form-group">
                    <label class="form-label">Supplier Name</label>
                    <input type="text" name="supplier_name" class="form-control" placeholder="Company name" required maxlength="50">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="supplier_email" class="form-control" placeholder="supplier@email.com" required maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="supplier_contact" class="form-control" placeholder="+63 912 345 6789" required maxlength="20">
                </div>
            </div>
            <div class="dialog_footer">
                <button type="button" class="btn btn--ghost" onclick="document.getElementById('dlg_add_supplier').classList.remove('is-open')">Cancel</button>
                <button type="submit" name="btn_create_supplier" class="btn btn--primary">Save Supplier</button>
            </div>
        </form>
        </div>
    </div>
</div>

<!-- EDIT SUPPLIER DIALOG -->
<div class="dialog-overlay" id="dlg_edit_supplier" onclick="if(event.target===this)this.classList.remove('is-open')">
    <div class="dialog">
        <div class="dialog_header">
            <span class="dialog_title">Edit Supplier</span>
            <button class="dialog_close" onclick="document.getElementById('dlg_edit_supplier').classList.remove('is-open')">&times;</button>
        </div>
        <form action="?tab=suppliers" method="POST">
            <input type="hidden" name="supplier_id" id="edit_supplier_id">
            <div class="dialog_body">
                <div class="form-group">
                    <label class="form-label">Supplier Name</label>
                    <input type="text" name="supplier_name" id="edit_supplier_name" class="form-control" required maxlength="50">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="supplier_email" id="edit_supplier_email" class="form-control" required maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="supplier_contact" id="edit_supplier_contact" class="form-control" required maxlength="20">
                </div>
            </div>
            <div class="dialog_footer">
                <button type="button" class="btn btn--ghost" onclick="document.getElementById('dlg_edit_supplier').classList.remove('is-open')">Cancel</button>
                <button type="submit" name="btn_update_supplier" class="btn btn--primary">Update Supplier</button>
            </div>
        </form>
        </div>
    </div>
</div>

<!-- PROFILE DIALOG -->
<div class="dialog-overlay" id="dlg_profile" onclick="if(event.target===this)this.classList.remove('is-open')">
    <div class="dialog" style="max-width:540px;">
        <div class="dialog_header" style="position:relative;">
            <span class="dialog_title" style="flex:1; text-align:center;">My Profile</span>
            <button class="dialog_close" style="position:absolute; right:var(--space-6); top:0;" onclick="document.getElementById('dlg_profile').classList.remove('is-open')">&times;</button>
        </div>
        <div class="dialog_body">
            <!-- PROFILE UPDATE FORM -->
            <form action="?tab=<?php echo security_helper::xss_clean($active_tab); ?>" method="POST">
                <div style="margin-bottom:var(--space-5);">
                    <div style="font-weight:var(--weight-bold); font-size:var(--text-sm); text-transform:uppercase; letter-spacing:.06em; color:var(--color-text-secondary); margin-bottom:var(--space-4); text-align:left;">Account Info</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4);">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" value="<?php echo security_helper::xss_clean($profile_data['first_name'] ?? ''); ?>" required maxlength="50">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?php echo security_helper::xss_clean($profile_data['last_name'] ?? ''); ?>" required maxlength="50">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email_address" class="form-control" value="<?php echo security_helper::xss_clean($profile_data['email_address'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" value="<?php echo security_helper::xss_clean($profile_data['contact_number'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo security_helper::xss_clean($profile_data['username'] ?? ''); ?>" required>
                    </div>
                </div>
                <button type="submit" name="btn_update_profile" class="btn btn--primary btn--full">Save Changes</button>
            </form>

            <hr style="margin: var(--space-6) 0;">

            <!-- CHANGE PASSWORD FORM -->
            <form action="?tab=<?php echo security_helper::xss_clean($active_tab); ?>" method="POST">
                <div style="font-weight:var(--weight-bold); color:var(--color-text-secondary); margin-bottom:var(--space-4); font-size:var(--text-sm); text-transform:uppercase; letter-spacing:.06em;">Change Password</div>
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="current_password" id="txt_curr_pw" class="form-control" placeholder="Enter current password" required>
                        <button type="button" class="btn--icon" onclick="toggle_visibility('txt_curr_pw', this)" aria-label="Toggle">
                            <svg id="txt_curr_pw_eye_show" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="txt_curr_pw_eye_hide" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="new_password" id="txt_new_pw" class="form-control" placeholder="Enter new password" required>
                        <button type="button" class="btn--icon" onclick="toggle_visibility('txt_new_pw', this)" aria-label="Toggle">
                            <svg id="txt_new_pw_eye_show" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="txt_new_pw_eye_hide" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="confirm_new_password" id="txt_confirm_new_pw" class="form-control" placeholder="Repeat new password" required>
                        <button type="button" class="btn--icon" onclick="toggle_visibility('txt_confirm_new_pw', this)" aria-label="Toggle">
                            <svg id="txt_confirm_new_pw_eye_show" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="txt_confirm_new_pw_eye_hide" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" name="btn_change_password" class="btn btn--ghost btn--full">Change Password</button>
            </form>
        </div>
    </div>
</div>

<!-- NOTIFICATIONS DIALOG -->
<div class="dialog-overlay" id="dlg_notifications" onclick="if(event.target===this)this.classList.remove('is-open')">
    <div class="dialog" style="max-width:460px;">
        <div class="dialog_header">
            <span class="dialog_title">Notifications
                <?php if ($notif_count > 0): ?>
                    <span style="display:inline-flex;align-items:center;justify-content:center;background:var(--color-danger);color:#fff;font-size:var(--text-xs);font-weight:var(--weight-bold);border-radius:var(--radius-full);min-width:20px;height:20px;padding:0 5px;margin-left:var(--space-2);"><?php echo $notif_count; ?></span>
                <?php endif; ?>
            </span>
            <button class="dialog_close" onclick="document.getElementById('dlg_notifications').classList.remove('is-open')">&times;</button>
        </div>
        <div class="dialog_body">

            <?php if (empty($expired_items) && empty($expiring_soon) && empty($low_stock_items)): ?>
                <div class="alert alert--success">
                    <div class="alert_body">
                        <div class="alert_title">All Good!</div>
                        <div class="alert_message">No urgent alerts. Your inventory is looking healthy.</div>
                    </div>
                </div>
            <?php else: ?>

                <?php if (!empty($expired_items)): ?>
                    <div class="alert alert--error" style="margin-bottom:var(--space-3);">
                        <div class="alert_body">
                            <div class="alert_title">Expired Products (<?php echo count($expired_items); ?>)</div>
                            <div class="alert_message">These products are past their expiry date.</div>
                        </div>
                    </div>
                    <div style="margin-bottom:var(--space-5);">
                        <?php foreach ($expired_items as $ep): ?>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:var(--space-2) var(--space-3);border-radius:var(--radius-sm);background:var(--color-error-bg);margin-bottom:var(--space-2);font-size:var(--text-sm);">
                                <div>
                                    <span style="font-weight:var(--weight-bold);color:var(--color-text-primary);"><?php echo security_helper::xss_clean($ep['name']); ?></span>
                                    <span class="mono-id" style="margin-left:var(--space-2);"><?php echo security_helper::xss_clean($ep['product_id']); ?></span>
                                </div>
                                <span class="badge badge--expired">Expired <?php echo security_helper::xss_clean($ep['expiry_date']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($expiring_soon)): ?>
                    <div class="alert alert--warning" style="margin-bottom:var(--space-3);">
                        <div class="alert_body">
                            <div class="alert_title">Expiring Within 30 Days (<?php echo count($expiring_soon); ?>)</div>
                            <div class="alert_message">Consider selling or restocking these items soon.</div>
                        </div>
                    </div>
                    <div style="margin-bottom:var(--space-5);">
                        <?php foreach ($expiring_soon as $ep): ?>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:var(--space-2) var(--space-3);border-radius:var(--radius-sm);background:var(--color-warning-bg);margin-bottom:var(--space-2);font-size:var(--text-sm);">
                                <div>
                                    <span style="font-weight:var(--weight-bold);color:var(--color-text-primary);"><?php echo security_helper::xss_clean($ep['name']); ?></span>
                                    <span class="mono-id" style="margin-left:var(--space-2);"><?php echo security_helper::xss_clean($ep['product_id']); ?></span>
                                </div>
                                <span class="badge badge--expiring">Expires <?php echo security_helper::xss_clean($ep['expiry_date']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($low_stock_items)): ?>
                    <div class="alert alert--warning" style="margin-bottom:var(--space-3);">
                        <div class="alert_body">
                            <div class="alert_title">Low Stock Items (<?php echo count($low_stock_items); ?>)</div>
                            <div class="alert_message">Products with fewer than 10 units remaining.</div>
                        </div>
                    </div>
                    <div>
                        <?php foreach ($low_stock_items as $ls): ?>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:var(--space-2) var(--space-3);border-radius:var(--radius-sm);background:var(--color-error-bg);margin-bottom:var(--space-2);font-size:var(--text-sm);">
                                <div>
                                    <span style="font-weight:var(--weight-bold);color:var(--color-text-primary);"><?php echo security_helper::xss_clean($ls['name']); ?></span>
                                    <span class="mono-id" style="margin-left:var(--space-2);"><?php echo security_helper::xss_clean($ls['product_id']); ?></span>
                                </div>
                                <span class="badge badge--low-stock"><?php echo security_helper::xss_clean($ls['stock']); ?> units left</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
        <div class="dialog_footer">
            <button class="btn btn--primary" onclick="document.getElementById('dlg_notifications').classList.remove('is-open')">Got it!</button>
        </div>
    </div>
</div>

<script>
    // EYE ICON TOGGLE (uses svg show/hide pairs)
    function toggle_visibility(input_id, btn_el) {
        const input    = document.getElementById(input_id);
        const eye_show = document.getElementById(input_id + '_eye_show');
        const eye_hide = document.getElementById(input_id + '_eye_hide');
        if (input.type === "password") {
            input.type = "text";
            if (eye_show) eye_show.style.display = "none";
            if (eye_hide) eye_hide.style.display = "block";
        } else {
            input.type = "password";
            if (eye_show) eye_show.style.display = "block";
            if (eye_hide) eye_hide.style.display = "none";
        }
    }

    // OPEN EDIT PRODUCT DIALOG (pre-fills all fields)
    function open_edit_product(pid, name, brand, model, color, expiry, supplier_id, stock) {
        document.getElementById('edit_product_id').value       = pid;
        document.getElementById('edit_product_name').value     = name;
        document.getElementById('edit_product_brand').value    = brand;
        document.getElementById('edit_product_model').value    = model;
        document.getElementById('edit_product_color').value    = color;
        document.getElementById('edit_product_expiry').value   = expiry;
        document.getElementById('edit_product_stock').value    = stock;
        const sel = document.getElementById('edit_product_supplier');
        for (let opt of sel.options) { opt.selected = opt.value === supplier_id; }
        document.getElementById('dlg_edit_product').classList.add('is-open');
    }

    // OPEN EDIT SUPPLY DIALOG (pre-fills all fields)
    function open_edit_supply(sid, product_id, supplier_id, quantity, supply_date) {
        document.getElementById('edit_supply_id').value       = sid;
        document.getElementById('edit_supply_quantity').value = quantity;
        document.getElementById('edit_supply_date').value     = supply_date;
        const psel = document.getElementById('edit_supply_product_id');
        for (let opt of psel.options) { opt.selected = opt.value === product_id; }
        const ssel = document.getElementById('edit_supply_supplier_id');
        for (let opt of ssel.options) { opt.selected = opt.value === supplier_id; }
        document.getElementById('dlg_edit_supply').classList.add('is-open');
    }

    // OPEN EDIT SUPPLIER DIALOG (pre-fills all fields)
    function open_edit_supplier(sid, name, email, contact) {
        document.getElementById('edit_supplier_id').value      = sid;
        document.getElementById('edit_supplier_name').value    = name;
        document.getElementById('edit_supplier_email').value   = email;
        document.getElementById('edit_supplier_contact').value = contact;
        document.getElementById('dlg_edit_supplier').classList.add('is-open');
    }
</script>
</body>
</html>
