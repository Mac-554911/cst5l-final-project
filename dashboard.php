<?php
session_start();
require_once 'config/db.php'; 

// UNAUTHORIZED ACCESS PROTECT
if (!isset($_SESSION['employee_id'])) {
    header("Location: login-page.php");
    exit();
}

$message = "";

// XSS CLEAN UP FILTER UTILITY FUNCTION
class SecurityHelper {
    public static function xssClean($data) {
        if ($data === null) {
            return '';
        }
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
    }
}

class ProductManager {
    private $pdo;

    public function __construct($pdo_instance) {
        $this->pdo = $pdo_instance;
    }

    // AUTOMATIC PRODUCT ID GENERATION
    public function generateAutomaticId() {
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
        $product_id = $this->generateAutomaticId();
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
    public function fetchAllWithFilters($search = '') {
        if (!empty($search)) {
            $sql = "SELECT * FROM products WHERE product_id LIKE :s1 OR name LIKE :s2 OR brand LIKE :s3 
                    OR model LIKE :s4 OR color LIKE :s5 OR expiry_date LIKE :s6 OR supplier_id LIKE :s7 OR stock LIKE :s8 ORDER BY created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_fill_keys(['s1','s2','s3','s4','s5','s6','s7','s8'], "%$search%"));
            return $stmt->fetchAll();
        }
        return $this->pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();
    }
}

class SupplierManager {
    private $pdo;

    public function __construct($pdo_instance) {
        $this->pdo = $pdo_instance;
    }

    // AUTOMATIC SUPPLIER ID GENERATION
    public function generateAutomaticId() {
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
        
        $supplier_id = $this->generateAutomaticId();
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
    public function fetchAllWithFilters($search = '') {
        if (!empty($search)) {
            $sql = "SELECT * FROM suppliers WHERE supplier_id LIKE :s1 OR name LIKE :s2 OR email_address LIKE :s3 OR contact_number LIKE :s4 ORDER BY created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_fill_keys(['s1','s2','s3','s4'], "%$search%"));
            return $stmt->fetchAll();
        }
        return $this->pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();
    }
}

class DashboardController {
    private $product_mgr;
    private $supplier_mgr;
    public $message = "";

    public function __construct($pdo_instance) {
        $this->product_mgr  = new ProductManager($pdo_instance);
        $this->supplier_mgr = new SupplierManager($pdo_instance);
    }

    // ROUTE USER REQUEST SUBMISSIONS
    public function handlePostRequests() {
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

// APP INITIALIZATION CORNER
$search_product  = isset($_GET['search_product']) ? trim($_GET['search_product']) : '';
$search_supplier = isset($_GET['search_supplier']) ? trim($_GET['search_supplier']) : '';

$controller = new DashboardController($pdo);
$controller->handlePostRequests();

$product_worker  = new ProductManager($pdo);
$supplier_worker = new SupplierManager($pdo);

$suppliers_list_global   = $supplier_worker->fetchAllWithFilters();
$products_list           = $product_worker->fetchAllWithFilters($search_product);
$suppliers_list_filtered = $supplier_worker->fetchAllWithFilters($search_supplier);

// ALERTS AND NOTIFICATIONS INITIALIZATION
$low_stock_alerts = [];
$expiry_alerts    = [];
$current_date     = date('Y-m-d');
$danger_date      = date('Y-m-d', strtotime('+30 days'));

// QUERY ACTIVE INVENTORY ALERT METRICS
$alert_stmt = $pdo->query("SELECT product_id, name, stock, expiry_date FROM products");
while ($row = $alert_stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['stock'] <= 5) $low_stock_alerts[] = $row;
    if (!empty($row['expiry_date'])) {
        if ($row['expiry_date'] <= $current_date) $expiry_alerts[] = ['data' => $row, 'status' => 'EXPIRED'];
        elseif ($row['expiry_date'] <= $danger_date) $expiry_alerts[] = ['data' => $row, 'status' => 'EXPIRING SOON'];
    }
}

// FETCH HISTORICAL SUPPLY RECORDS
$supply_history_list = $pdo->query("SELECT * FROM products_supply ORDER BY record_at DESC")->fetchAll();

// CONSOLIDATE SYSTEM CREATION LOGS INTO CHRONOLOGICAL TIMELINE
$activity_logs_list = [];
try {
    $product_logs  = $pdo->query("SELECT product_id AS log_id, name AS log_name, 'Product Registered' AS log_type, created_at AS log_time FROM products")->fetchAll(PDO::FETCH_ASSOC);
    $supplier_logs = $pdo->query("SELECT supplier_id AS log_id, name AS log_name, 'Supplier Registered' AS log_type, created_at AS log_time FROM suppliers")->fetchAll(PDO::FETCH_ASSOC);
    
    $activity_logs_list = array_merge($product_logs, $supplier_logs);
    usort($activity_logs_list, function($a, $b) { return strcmp($b['log_time'], $a['log_time']); });
} catch (PDOException $e) {
    $activity_logs_list = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS Management Dashboard</title>
</head>
<body>
    <!-- Action file and GET/POST methods -->
    <div>
        <h1>Inventory Management System Dashboard</h1>
        <p>Welcome: <strong><?php echo SecurityHelper::xssClean($_SESSION['username'] ?? 'User'); ?></strong> (ID: <?php echo SecurityHelper::xssClean($_SESSION['employee_id']); ?>)</p>
        <p><a href="logout.php">Logout</a></p>
    </div>

    <?php if (!empty($controller->message)): ?>
        <p><mark><strong>SYSTEM STATUS: <?php echo SecurityHelper::xssClean($controller->message); ?></strong></mark></p>
    <?php endif; ?>

    <!-- NOTIFICATION SYSTEM BLOCKS -->
    <div class="system-alerts">
        <h3>⚠️ System Notifications</h3>
        <?php if (empty($low_stock_alerts) && empty($expiry_alerts)): ?>
            <p><strong>All clear! No low stock or expiry notices at this time.</strong></p>
        <?php endif; ?>

        <?php if (!empty($low_stock_alerts)): ?>
            <div class="low-stock-group">
                <strong>Low Stock Warning (5 units or less):</strong>
                <ul>
                    <?php foreach ($low_stock_alerts as $alert): ?>
                        <li>Product: <strong><?php echo SecurityHelper::xssClean($alert['name']); ?></strong> (<?php echo SecurityHelper::xssClean($alert['product_id']); ?>) — Current Stock: <span><?php echo SecurityHelper::xssClean($alert['stock']); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($expiry_alerts)): ?>
            <div class="expiry-group">
                <strong>Product Expiry Notices:</strong>
                <ul>
                    <?php foreach ($expiry_alerts as $alert): ?>
                        <li>Product: <strong><?php echo SecurityHelper::xssClean($alert['data']['name']); ?></strong> (<?php echo SecurityHelper::xssClean($alert['data']['product_id']); ?>) — Date: <strong><?php echo SecurityHelper::xssClean($alert['data']['expiry_date']); ?></strong> [<span><?php echo SecurityHelper::xssClean($alert['status']); ?></span>]</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <hr>

    <!-- PRODUCTS MANAGEMENT FORM -->
    <div class="products-management">
        <h2>Products Management</h2>
        <h3>Add New Product</h3>
        <form action="dashboard.php" method="POST">
            <input type="text" name="name" placeholder="Name" maxlength="50" required>
            <input type="text" name="brand" placeholder="Brand" maxlength="50" required>
            <input type="text" name="model" placeholder="Model" maxlength="50">
            <input type="text" name="color" placeholder="Color" maxlength="50">
            <input type="date" name="expiry_date">
            <select name="supplier_id">
                <option value="">-- Assign Supplier --</option>
                <?php foreach ($suppliers_list_global as $sup): ?>
                    <option value="<?php echo SecurityHelper::xssClean($sup['supplier_id']); ?>"><?php echo SecurityHelper::xssClean($sup['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="stock" placeholder="Stock" min="0" value="0" required>
            <button type="submit" name="btn_create_product">Add Product</button>
        </form>
        <br>

        <h3>Search Products</h3>
        <form action="dashboard.php" method="GET">
            <input type="text" name="search_product" value="<?php echo SecurityHelper::xssClean($search_product); ?>" placeholder="Search any product attribute...">
            <button type="submit">Search</button>
            <?php if(!empty($search_product)): ?> <a href="dashboard.php">Clear</a> <?php endif; ?>
        </form>
        <br>

        <table border="1">
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Name</th>
                    <th>Brand</th>
                    <th>Model</th>
                    <th>Color</th>
                    <th>Expiry Date</th>
                    <th>Supplier</th>
                    <th>Stock Level</th>
                    <th>Actions</th>
                    <th>Removal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products_list as $product): ?>
                    <tr>
                        <form action="dashboard.php" method="POST">
                            <td>
                                <strong><?php echo SecurityHelper::xssClean($product['product_id']); ?></strong>
                                <input type="hidden" name="product_id" value="<?php echo SecurityHelper::xssClean($product['product_id']); ?>">
                            </td>
                            <?php 
                            $product_fields = [
                                'name'   => ['val' => $product['name'], 'max' => 50, 'type' => 'text'],
                                'brand'  => ['val' => $product['brand'], 'max' => 50, 'type' => 'text'],
                                'model'  => ['val' => $product['model'] ?? '', 'max' => 50, 'type' => 'text'],
                                'color'  => ['val' => $product['color'] ?? '', 'max' => 50, 'type' => 'text'],
                                'expiry_date' => ['val' => $product['expiry_date'], 'max' => null, 'type' => 'date']
                            ];
                            foreach ($product_fields as $field_key => $field_cfg): 
                            ?>
                                <td>
                                    <input type="<?php echo $field_cfg['type']; ?>" name="<?php echo $field_key; ?>" value="<?php echo SecurityHelper::xssClean($field_cfg['val']); ?>" <?php echo $field_cfg['max'] ? "maxlength='{$field_cfg['max']}'" : ""; ?> <?php echo ($field_key === 'name' || $field_key === 'brand') ? 'required' : ''; ?>>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <select name="supplier_id">
                                    <option value="">-- None --</option>
                                    <?php foreach ($suppliers_list_global as $sup): ?>
                                        <option value="<?php echo SecurityHelper::xssClean($sup['supplier_id']); ?>" <?php echo ($product['supplier_id'] == $sup['supplier_id']) ? 'selected' : ''; ?>><?php echo SecurityHelper::xssClean($sup['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="stock" value="<?php echo SecurityHelper::xssClean($product['stock']); ?>" min="0" required></td>
                            <td><button type="submit" name="btn_update_product">Save</button></td>
                        </form>
                        <td>
                            <form action="dashboard.php" method="POST" onsubmit="return confirm('Delete record permanently?');">
                                <input type="hidden" name="product_id" value="<?php echo SecurityHelper::xssClean($product['product_id']); ?>">
                                <button type="submit" name="btn_delete_product">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <hr>

    <!-- SUPPLIERS DIRECTORY FORM -->
    <div class="suppliers-directory">
        <h2>Suppliers Directory</h2>
        <h3>Register New Supplier</h3>
        <form action="dashboard.php" method="POST">
            <input type="text" name="name" placeholder="Supplier Name" maxlength="50" required>
            <input type="email" name="email_ad" placeholder="Email Address" maxlength="100" required>
            <input type="text" name="contact_nb" placeholder="Contact Number" maxlength="20" required>
            <button type="submit" name="btn_create_supplier">Register Supplier</button>
        </form>
        <br>

        <h3>Search Suppliers</h3>
        <form action="dashboard.php" method="GET">
            <input type="text" name="search_supplier" value="<?php echo SecurityHelper::xssClean($search_supplier); ?>" placeholder="Search any supplier attribute...">
            <button type="submit">Search</button>
            <?php if(!empty($search_supplier)): ?> <a href="dashboard.php">Clear</a> <?php endif; ?>
        </form>
        <br>

        <table border="1">
            <thead>
                <tr>
                    <th>Supplier ID</th>
                    <th>Name</th>
                    <th>Email Address</th>
                    <th>Contact Number</th>
                    <th>Actions</th>
                    <th>Removal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers_list_filtered as $supplier): ?>
                    <tr>
                        <form action="dashboard.php" method="POST">
                            <td>
                                <strong><?php echo SecurityHelper::xssClean($supplier['supplier_id']); ?></strong>
                                <input type="hidden" name="supplier_id" value="<?php echo SecurityHelper::xssClean($supplier['supplier_id']); ?>">
                            </td>
                            <?php 
                            $supplier_fields = [
                                'name'       => ['val' => $supplier['name'], 'max' => 50, 'type' => 'text'],
                                'email_ad'   => ['val' => $supplier['email_address'], 'max' => 100, 'type' => 'email'],
                                'contact_nb' => ['val' => $supplier['contact_number'], 'max' => 20, 'type' => 'text']
                            ];
                            foreach ($supplier_fields as $field_key => $field_cfg): 
                            ?>
                                <td>
                                    <input type="<?php echo $field_cfg['type']; ?>" name="<?php echo $field_key; ?>" value="<?php echo SecurityHelper::xssClean($field_cfg['val']); ?>" maxlength="<?php echo $field_cfg['max']; ?>" required>
                                </td>
                            <?php endforeach; ?>
                            <td><button type="submit" name="btn_update_supplier">Save</button></td>
                        </form>
                        <td>
                            <form action="dashboard.php" method="POST" onsubmit="return confirm('Delete permanently?');">
                                <input type="hidden" name="supplier_id" value="<?php echo SecurityHelper::xssClean($supplier['supplier_id']); ?>">
                                <button type="submit" name="btn_delete_supplier">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <hr>

    <!-- SUPPLY HISTORY FORM -->
    <div class="supply-history">
        <h2>Supply History</h2>
        <table border="1">
            <thead>
                <tr>
                    <th>Supply ID</th>
                    <th>Supplier ID</th>
                    <th>Product ID</th>
                    <th>Quantity Received</th>
                    <th>Delivery Date</th>
                    <th>Log Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($supply_history_list)): ?>
                    <tr><td colspan="6">No freight supply logs found in database records.</td></tr>
                <?php else: ?>
                    <?php foreach ($supply_history_list as $log): ?>
                        <tr>
                            <?php 
                            $log_fields = [
                                $log['supply_id'] ?? $log['id'] ?? 'N/A', $log['supplier_id'], $log['product_id'],
                                $log['quantity'] ?? $log['quantity_received'] ?? '0', $log['delivery_date'] ?? 'N/A', $log['record_at'] ?? 'N/A'
                            ];
                            foreach ($log_fields as $cell_data): 
                            ?>
                                <td><?php echo SecurityHelper::xssClean($cell_data); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <hr>

    <!-- SYSTEM ACTIVITY FORM -->
    <div class="system-activity">
        <h2>System Activity</h2>
        <table border="1">
            <thead>
                <tr>
                    <th>Action Timestamp</th>
                    <th>Activity Classification</th>
                    <th>Assigned Object ID Reference</th>
                    <th>Registered Element Name</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activity_logs_list)): ?>
                    <tr><td colspan="4">No activity tracking snapshots available.</td></tr>
                <?php else: ?>
                    <?php foreach ($activity_logs_list as $action_item): ?>
                        <tr>
                            <?php 
                            $activity_fields = [$action_item['log_time'], $action_item['log_type'], $action_item['log_id'], $action_item['log_name']];
                            foreach ($activity_fields as $cell_data): 
                            ?>
                                <td><?php echo SecurityHelper::xssClean($cell_data); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>
