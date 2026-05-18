<?php
session_start();
require_once 'config/db.php'; 

// UNAUTHORIZED ACCESS PROTECT
if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
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
            die("Error: Field content exceeds character limits.");
        }
        $product_id = $this->generate_automatic_id();
        $sql = "INSERT INTO products (product_id, name, brand, model, color, expiry_date, supplier_id, stock) 
                VALUES (:product_id, :name, :brand, :model, :color, :expiry_date, :supplier_id, :stock)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'product_id'  => $product_id, 'name' => $name, 'brand' => $brand, 
            'model' => $model, 'color' => $color, 'expiry_date' => $expiry_date, 
            'supplier_id' => $supplier_id, 'stock' => $stock
        ]);
    }

    // UPDATE PRODUCT (Notice product_id remains fixed since it is the table primary key identifier!)
    public function update($product_id, $name, $brand, $model, $color, $expiry_date, $supplier_id, $stock) {
        $sql = "UPDATE products SET name = :name, brand = :brand, model = :model, color = :color, 
                       expiry_date = :expiry_date, supplier_id = :supplier_id, stock = :stock WHERE product_id = :product_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'name' => $name, 'brand' => $brand, 'model' => $model, 'color' => $color,
            'expiry_date' => $expiry_date, 'supplier_id' => $supplier_id, 'stock' => $stock, 'product_id' => $product_id
        ]);
    }

    // DELETE PRODUCT
    public function delete($product_id) {
        $sql = "DELETE FROM products WHERE product_id = :product_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['product_id' => $product_id]);
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
            die("Error: Field content exceeds character limits.");
        }
        // CONTACT NUMBER VALIDATION
        $new_contact = preg_replace('/[^0-9+]/', '', $contact_nb);
        if (!preg_match('/^\+?\d{7,15}$/', $new_contact)) {
            die("Error: Invalid contact number format. Please enter a valid numerical phone number.");
        }
        // EMAIL VALIDATION
        if (!filter_var($email_ad, FILTER_VALIDATE_EMAIL)) {
            die("Error: Invalid email address format.");
        }
        
        $supplier_id = $this->generate_automatic_id();
        $sql = "INSERT INTO suppliers (supplier_id, name, email_address, contact_number) VALUES (:supplier_id, :name, :email_ad, :contact_nb)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['supplier_id' => $supplier_id, 'name' => $name, 'email_ad' => $email_ad, 'contact_nb' => $new_contact]);
    }

    // UPDATE SUPPLIER (Notice supplier_id remains fixed since it is the table primary key identifier!)
    public function update($supplier_id, $name, $email_ad, $contact_nb) {
        // CONTACT NUMBER VALIDATION
        $new_contact = preg_replace('/[^0-9+]/', '', $contact_nb);
        if (!preg_match('/^\+?\d{7,15}$/', $new_contact)) {
            die("Error: Invalid contact number format. Please enter a valid numerical phone number.");
        }
        // EMAIL VALIDATION
        if (!filter_var($email_ad, FILTER_VALIDATE_EMAIL)) {
            die("Error: Invalid email address format.");
        }
        $sql = "UPDATE suppliers SET name = :name, email_address = :email_ad, contact_number = :contact_nb WHERE supplier_id = :supplier_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['name' => $name, 'email_ad' => $email_ad, 'contact_nb' => $new_contact, 'supplier_id' => $supplier_id]);
    }

    // DELETE SUPPLIER
    public function delete($supplier_id) {
        // DEPENDENCY CHECK
        $check = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = :supplier_id");
        $check->execute(['supplier_id' => $supplier_id]);
        if ($check->fetchColumn() > 0) {
            die("Error: Cannot delete this supplier. Active products are currently assigned to them!");
        }
        $sql = "DELETE FROM suppliers WHERE supplier_id = :supplier_id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['supplier_id' => $supplier_id]);
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
}

class dashboard_controller {
    private $product_mgr;
    private $supplier_mgr;
    public $message = "";

    public function __construct($pdo_instance) {
        $this->product_mgr  = new product_manager($pdo_instance);
        $this->supplier_mgr = new supplier_manager($pdo_instance);
    }

    // ROUTE USER REQUEST SUBMISSIONS
    public function handle_post_requests() {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") return;

        if (isset($_POST['btn_create_product'])) {
            $this->product_mgr->create($_POST['name'], $_POST['brand'], $_POST['model'], $_POST['color'], $_POST['expiry_date'], $_POST['supplier_id'], $_POST['stock']);
            $this->message = "Product added successfully!";
        }
        if (isset($_POST['btn_update_product'])) {
            $this->product_mgr->update($_POST['product_id'], $_POST['name'], $_POST['brand'], $_POST['model'], $_POST['color'], $_POST['expiry_date'], $_POST['supplier_id'], $_POST['stock']);
            $this->message = "Product updated successfully!";
        }
        if (isset($_POST['btn_delete_product'])) {
            $this->product_mgr->delete($_POST['product_id']);
            $this->message = "Product removed.";
        }
        if (isset($_POST['btn_create_supplier'])) {
            $this->supplier_mgr->create($_POST['name'], $_POST['email_ad'], $_POST['contact_nb']);
            $this->message = "Supplier registered successfully!";
        }
        if (isset($_POST['btn_update_supplier'])) {
            $this->supplier_mgr->update($_POST['supplier_id'], $_POST['name'], $_POST['email_ad'], $_POST['contact_nb']);
            $this->message = "Supplier information updated!";
        }
        if (isset($_POST['btn_delete_supplier'])) {
            $this->supplier_mgr->delete($_POST['supplier_id']);
            $this->message = "Supplier removed.";
        }
    }
}
