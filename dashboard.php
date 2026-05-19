<?php
// dashboard.php
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
            return "Error: Field content exceeds character limits.";
        }
        
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
            return "Product created successfully! Assigned ID: " . $product_id;
        } catch (PDOException $e) {
            return "Database Error: " . $e->getMessage();
        }
    }

    // DELETE PRODUCT
    public function delete($product_id) {
        try {
            $sql = "DELETE FROM products WHERE product_id = :product_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['product_id' => $product_id]);
            return "Product deleted successfully.";
        } catch (PDOException $e) {
            return "Database Error: " . $e->getMessage();
        }
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
            return "Error: Field content exceeds character limits.";
        }
        // CONTACT NUMBER VALIDATION
        $new_contact = preg_replace('/[^0-9+]/', '', $contact_nb);
        if (!preg_match('/^\+?\d{7,15}$/', $new_contact)) {
            return "Error: Invalid contact number format. Please enter a valid numerical phone number.";
        }
        // EMAIL VALIDATION
        if (!filter_var($email_ad, FILTER_VALIDATE_EMAIL)) {
            return "Error: Invalid email address format.";
        }
        
        try {
            $supplier_id = $this->generate_automatic_id();
            $sql = "INSERT INTO suppliers (supplier_id, name, email_address, contact_number) VALUES (:supplier_id, :name, :email_ad, :contact_nb)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['supplier_id' => $supplier_id, 'name' => $name, 'email_ad' => $email_ad, 'contact_nb' => $new_contact]);
            return "Supplier created successfully! Assigned ID: " . $supplier_id;
        } catch (PDOException $e) {
            return "Database Error: " . $e->getMessage();
        }
    }

    // DELETE SUPPLIER
    public function delete($supplier_id) {
        // DEPENDENCY CHECK
        $check = $this->pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = :supplier_id");
        $check->execute(['supplier_id' => $supplier_id]);
        if ($check->fetchColumn() > 0) {
            return "Error: Cannot delete this supplier. Active products are currently assigned to them!";
        }
        
        try {
            $sql = "DELETE FROM suppliers WHERE supplier_id = :supplier_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['supplier_id' => $supplier_id]);
            return "Supplier deleted successfully.";
        } catch (PDOException $e) {
            return "Database Error: " . $e->getMessage();
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
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // ROUTE PRODUCT CREATION
            if (isset($_POST['btn_create_product'])) {
                $this->message = $this->product_mgr->create(
                    trim($_POST['name']), trim($_POST['brand']), trim($_POST['model']), 
                    trim($_POST['color']), $_POST['expiry_date'], $_POST['supplier_id'], $_POST['stock']
                );
            }
            // ROUTE PRODUCT DELETION
            if (isset($_POST['btn_delete_product'])) {
                $this->message = $this->product_mgr->delete($_POST['product_id']);
            }
            // ROUTE SUPPLIER CREATION
            if (isset($_POST['btn_create_supplier'])) {
                $this->message = $this->supplier_mgr->create(
                    trim($_POST['supplier_name']), trim($_POST['supplier_email']), trim($_POST['supplier_contact'])
                );
            }
            // ROUTE SUPPLIER DELETION
            if (isset($_POST['btn_delete_supplier'])) {
                $this->message = $this->supplier_mgr->delete($_POST['supplier_id']);
            }
        }
    }

    public function get_products() {
        return $this->product_mgr->fetch_all_with_filters(trim($_GET['search'] ?? ''));
    }

    public function get_suppliers() {
        return $this->supplier_mgr->fetch_all_with_filters(trim($_GET['search'] ?? ''));
    }
}

// APP INITIALIZATION CORNER
$controller = new dashboard_controller($pdo);
$controller->handle_post_requests();

$products_list = $controller->get_products();
$suppliers_list = $controller->get_suppliers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="dashboard-layout">
        
        <!-- FIXED SIDEBAR NAVIGATION -->
        <div class="sidebar">
            <div class="sidebar_brand">
                <span class="sidebar_brand-text">IMS Dashboard</span>
            </div>
            
            <div class="sidebar_section-label">Navigation Menu</div>
            <div class="sidebar_nav">
                <a href="dashboard.php" class="sidebar_nav-item is-active">Overview Monitor</a>
            </div>

            <div class="sidebar_footer">
                <div class="sidebar_user">
                    <div>
                        <div class="sidebar_user-name"><?php echo security_helper::xss_clean($_SESSION['username']); ?></div>
                        <div class="sidebar_user-id"><?php echo security_helper::xss_clean($_SESSION['employee_id']); ?></div>
                    </div>
                </div>
                <a href="logout.php" class="btn btn--danger btn--full">Logout Engine</a>
            </div>
        </div>

        <!-- MAIN CONTENT AREA -->
        <div class="main-content">
            
            <!-- STICKY TOP BAR -->
            <div class="topbar">
                <span class="topbar_title">Inventory Tracker Portal</span>
                
                <form method="GET" action="" class="search-row">
                    <input type="text" name="search" class="form-control" placeholder="Search parameters..." value="<?php echo security_helper::xss_clean($_GET['search'] ?? ''); ?>">
                    <button type="submit" class="btn btn--primary">Filter</button>
                    <?php if (!empty($_GET['search'])): ?>
                        <a href="dashboard.php" class="btn btn--ghost">Reset Grid</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- SCROLLABLE CONTENT PADDING WRAPPER -->
            <div class="content-area">
                
                <?php if (!empty($controller->message)): ?>
                    <div class="notifications-panel">
                        <div class="alert alert--info">
                            <div class="alert_body">
                                <div class="alert_message"><?php echo security_helper::xss_clean($controller->message); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="page-section">
                    
                    <!-- PRODUCT PROVISIONS PANEL -->
                    <div class="card mb-6">
                        <div class="card_header"><span class="card_title">Record Asset Unit</span></div>
                        <div class="card_body">
                            <form action="" method="POST">
                                <div class="form-group">
                                    <input type="text" name="name" class="form-control" placeholder="Product Name" required>
                                </div>
                                <div class="form-group">
                                    <input type="text" name="brand" class="form-control" placeholder="Brand" required>
                                </div>
                                <div class="form-group">
                                    <input type="text" name="model" class="form-control" placeholder="Model" required>
                                </div>
                                <div class="form-group">
                                    <input type="text" name="color" class="form-control" placeholder="Color" required>
                                </div>
                                <div class="form-group">
                                    <input type="date" name="expiry_date" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <input type="number" name="stock" class="form-control" placeholder="Initial Stock Allocation" min="0" required>
                                </div>
                                <div class="form-group">
                                    <select name="supplier_id" class="form-control" required>
                                        <option value="">-- Select Linked Supplier --</option>
                                        <?php foreach ($suppliers_list as $sup): ?>
                                            <option value="<?php echo security_helper::xss_clean($sup['supplier_id']); ?>">
                                                <?php echo security_helper::xss_clean($sup['name'] . " (" . $sup['supplier_id'] . ")"); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="btn_create_product" class="btn btn--primary">Add Product Record</button>
                            </form>
                        </div>
                    </div>

                    <!-- SUPPLIER PANEL -->
                    <div class="card mb-6">
                        <div class="card_header"><span class="card_title">Register Supplier Partner</span></div>
                        <div class="card_body">
                            <form action="" method="POST">
                                <div class="form-group">
                                    <input type="text" name="supplier_name" class="form-control" placeholder="Supplier Company Name" required>
                                </div>
                                <div class="form-group">
                                    <input type="email" name="supplier_email" class="form-control" placeholder="Corporate Email Address" required>
                                </div>
                                <div class="form-group">
                                    <input type="text" name="supplier_contact" class="form-control" placeholder="Contact Phone Number" required>
                                </div>
                                <button type="submit" name="btn_create_supplier" class="btn btn--primary">Save Supplier Record</button>
                            </form>
                        </div>
                    </div>

                    <!-- PRODUCTS REGISTRY TABLE -->
                    <h3>Active Inventory Assets Table Registry</h3>
                    <div class="table-wrapper mb-6">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product ID</th>
                                    <th>Name</th>
                                    <th>Brand / Model</th>
                                    <th>Color</th>
                                    <th>Stock Level</th>
                                    <th>Expiration Date</th>
                                    <th>Supplier Code</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products_list)): ?>
                                    <tr><td colspan="8" class="table-empty">No products match current metrics guidelines.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($products_list as $prod): ?>
                                        <tr>
                                            <td><span class="mono-id"><?php echo security_helper::xss_clean($prod['product_id']); ?></span></td>
                                            <td><strong><?php echo security_helper::xss_clean($prod['name']); ?></strong></td>
                                            <td><?php echo security_helper::xss_clean($prod['brand'] . " " . $prod['model']); ?></td>
                                            <td><?php echo security_helper::xss_clean($prod['color']); ?></td>
                                            <td><span class="badge badge--ok"><?php echo security_helper::xss_clean($prod['stock']); ?> units</span></td>
                                            <td><?php echo security_helper::xss_clean($prod['expiry_date']); ?></td>
                                            <td><span class="mono-id"><?php echo security_helper::xss_clean($prod['supplier_id']); ?></span></td>
                                            <td>
                                                <form action="" method="POST" onsubmit="return confirm('Purge inventory asset entry?');">
                                                    <input type="hidden" name="product_id" value="<?php echo security_helper::xss_clean($prod['product_id']); ?>">
                                                    <button type="submit" name="btn_delete_product" class="btn btn--danger btn--sm">Purge</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- SUPPLIERS DIRECTORY TABLE -->
                    <h3>Registered Supplier Partner Network Directory</h3>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Supplier ID</th>
                                    <th>Vendor Company Name</th>
                                    <th>Email Address Line</th>
                                    <th>Contact Channel Line</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($suppliers_list)): ?>
                                    <tr><td colspan="5" class="table-empty">No supplier partners registered matching query.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($suppliers_list as $sup): ?>
                                        <tr>
                                            <td><span class="mono-id"><?php echo security_helper::xss_clean($sup['supplier_id']); ?></span></td>
                                            <td><strong><?php echo security_helper::xss_clean($sup['name']); ?></strong></td>
                                            <td><?php echo security_helper::xss_clean($sup['email_address']); ?></td>
                                            <td><?php echo security_helper::xss_clean($sup['contact_number']); ?></td>
                                            <td>
                                                <form action="" method="POST" onsubmit="return confirm('Purge supplier profile record?');">
                                                    <input type="hidden" name="supplier_id" value="<?php echo security_helper::xss_clean($sup['supplier_id']); ?>">
                                                    <button type="submit" name="btn_delete_supplier" class="btn btn--danger btn--sm">Drop Profile</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</body>
</html>