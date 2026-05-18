<?php
session_start();
require_once 'config/db.php'; 

// UNAUTHORIZED ACCESS PROTECT
if (!isset($_SESSION['employee_id'])) {
    header("Location: login-page.php");
    exit();
}

$message = "";

// SEARCH FILTERS GET INITIALIZATION
$search_product  = isset($_GET['search_product']) ? trim($_GET['search_product']) : '';
$search_supplier = isset($_GET['search_supplier']) ? trim($_GET['search_supplier']) : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // CREATE PRODUCT
    if (isset($_POST['btn_create_product'])) {
        $name        = trim($_POST['name']);
        $brand       = trim($_POST['brand']);
        $model       = !empty($_POST['model']) ? trim($_POST['model']) : null;
        $color       = !empty($_POST['color']) ? trim($_POST['color']) : null;
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $supplier_id = !empty($_POST['supplier_id']) ? trim($_POST['supplier_id']) : null;
        $stock       = intval($_POST['stock']);
        
        // AUTOMATIC PRODUCT ID GENERATION
        try {
            $id_stmt = $pdo->query("SELECT product_id FROM products");
            $max_num = 0;
            while ($row = $id_stmt->fetch(PDO::FETCH_ASSOC)) {
                $num = (int) str_replace('PID', '', $row['product_id']);
                if ($num > $max_num) {
                    $max_num = $num;
                }
            }
            $next_num = $max_num + 1;
            $product_id = "PID" . $next_num;
            
            $sql = "INSERT INTO products (product_id, name, brand, model, color, expiry_date, supplier_id, stock) 
                    VALUES (:product_id, :name, :brand, :model, :color, :expiry_date, :supplier_id, :stock)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'product_id'  => $product_id,
                'name'        => $name, 
                'brand'       => $brand, 
                'model'       => $model,
                'color'       => $color,
                'expiry_date' => $expiry_date,
                'supplier_id' => $supplier_id,
                'stock'       => $stock
            ]);
            $message = "Product added successfully! Generated ID: " . $product_id;
        } catch (PDOException $e) { 
            die("Database Execution Error: " . $e->getMessage()); 
        }
    }

    // UPDATE PRODUCT (Notice product_id remains fixed since it is the table primary key identifier!)
    if (isset($_POST['btn_update_product'])) {
        $product_id  = trim($_POST['product_id']);
        $name        = trim($_POST['name']);
        $brand       = trim($_POST['brand']);
        $model       = !empty($_POST['model']) ? trim($_POST['model']) : null;
        $color       = !empty($_POST['color']) ? trim($_POST['color']) : null;
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $supplier_id = !empty($_POST['supplier_id']) ? trim($_POST['supplier_id']) : null;
        $stock       = intval($_POST['stock']);
        
        try {
            $sql = "UPDATE products SET name = :name, brand = :brand, model = :model, color = :color, 
                           expiry_date = :expiry_date, supplier_id = :supplier_id, stock = :stock WHERE product_id = :product_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'name'        => $name, 
                'brand'       => $brand, 
                'model'       => $model,
                'color'       => $color,
                'expiry_date' => $expiry_date,
                'supplier_id' => $supplier_id,
                'stock'       => $stock,
                'product_id'  => $product_id
            ]);
            $message = "Product updated successfully!";
        } catch (PDOException $e) { 
            die("Database Execution Error: " . $e->getMessage()); 
        }
    }

    // DELETE PRODUCT
    if (isset($_POST['btn_delete_product'])) {
        $product_id = trim($_POST['product_id']);
        try {
            $sql = "DELETE FROM products WHERE product_id = :product_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['product_id' => $product_id]);
            $message = "Product removed.";
        } catch (PDOException $e) { 
            die("Database Execution Error: " . $e->getMessage()); 
        }
    }

    // CREATE SUPPLIER
    if (isset($_POST['btn_create_supplier'])) {
        $name        = trim($_POST['name']);
        $email_ad    = trim($_POST['email_ad']);
        $contact_nb  = trim($_POST['contact_nb']);
        
        // CONTACT NUMBER VALIDATION
        $new_contact_nb = preg_replace('/[^0-9+]/', '', $contact_nb);
        if (!preg_match('/^\+?\d{7,15}$/', $new_contact_nb)) {
            die("Error: Invalid contact number format. Please enter a valid numerical phone number.");
        }

        // EMAIL VALIDATION
        if (!filter_var($email_ad, FILTER_VALIDATE_EMAIL)) {
            die("Error: Invalid email address format.");
        }

        // AUTOMATIC SUPPLIER ID GENERATION
        try {
            $id_stmt = $pdo->query("SELECT supplier_id FROM suppliers");
            $max_num = 0;
            while ($row = $id_stmt->fetch(PDO::FETCH_ASSOC)) {
                $num = (int) str_replace('SID', '', $row['supplier_id']);
                if ($num > $max_num) {
                    $max_num = $num;
                }
            }
            $next_num = $max_num + 1;
            $supplier_id = "SID" . $next_num;

            $sql = "INSERT INTO suppliers (supplier_id, name, email_address, contact_number) VALUES (:supplier_id, :name, :email_ad, :contact_nb)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'supplier_id' => $supplier_id, 
                'name'        => $name, 
                'email_ad'    => $email_ad, 
                'contact_nb'  => $new_contact_nb
            ]);
            $message = "Supplier registered successfully! Generated ID: " . $supplier_id;
        } catch (PDOException $e) { 
            die("Database Execution Error: " . $e->getMessage()); 
        }
    }

    // UPDATE SUPPLIER (Notice supplier_id remains fixed since it is the table primary key identifier!)
    if (isset($_POST['btn_update_supplier'])) {
        $supplier_id = trim($_POST['supplier_id']);
        $name        = trim($_POST['name']);
        $email_ad    = trim($_POST['email_ad']);
        $contact_nb  = trim($_POST['contact_nb']);
        
        // CONTACT NUMBER VALIDATION
        $new_contact_nb = preg_replace('/[^0-9+]/', '', $contact_nb);
        if (!preg_match('/^\+?\d{7,15}$/', $new_contact_nb)) {
            die("Error: Invalid contact number format. Please enter a valid numerical phone number.");
        }

        // EMAIL VALIDATION
        if (!filter_var($email_ad, FILTER_VALIDATE_EMAIL)) {
            die("Error: Invalid email address format.");
        }

        try {
            $sql = "UPDATE suppliers SET name = :name, email_address = :email_ad, contact_number = :contact_nb WHERE supplier_id = :supplier_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'name'        => $name, 
                'email_ad'    => $email_ad, 
                'contact_nb'  => $new_contact_nb, 
                'supplier_id' => $supplier_id
            ]);
            $message = "Supplier information updated!";
        } catch (PDOException $e) { 
            die("Database Execution Error: " . $e->getMessage()); 
        }
    }

    // DELETE SUPPLIER
    if (isset($_POST['btn_delete_supplier'])) {
        $supplier_id = trim($_POST['supplier_id']);
        
        // DEPENDENCY CHECK
        $dependency_check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = :supplier_id");
        $dependency_check->execute(['supplier_id' => $supplier_id]);
        
        if ($dependency_check->fetchColumn() > 0) {
            die("Error: Cannot delete this supplier. Active products are currently assigned to them!");
        }
        
        try {
            $sql = "DELETE FROM suppliers WHERE supplier_id = :supplier_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['supplier_id' => $supplier_id]);
            $message = "Supplier removed.";
        } catch (PDOException $e) { 
            die("Database Execution Error: " . $e->getMessage()); 
        }
    }
}

// READ & MULTI-FIELD SEARCH LOGIC
$suppliers_list_global = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();

// SEARCH ALL PRODUCT FIELDS EXCEPT TIMESTAMP
if (!empty($search_product)) {
    $sql = "SELECT * FROM products WHERE product_id LIKE :search_id OR name LIKE :search_name OR brand LIKE :search_brand 
            OR model LIKE :search_model OR color LIKE :search_color OR expiry_date LIKE :search_expiry OR supplier_id LIKE :search_supplier_id OR stock LIKE :search_stock ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'search_id'          => "%$search_product%", 
        'search_name'        => "%$search_product%", 
        'search_brand'       => "%$search_product%",
        'search_model'       => "%$search_product%", 
        'search_color'       => "%$search_product%", 
        'search_expiry'      => "%$search_product%",
        'search_supplier_id' => "%$search_product%", 
        'search_stock'       => "%$search_product%"
    ]);
    $products_list = $stmt->fetchAll();
} else {
    $products_list = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();
}

// SEARCH ALL SUPPLIER FIELDS EXCEPT TIMESTAMP
if (!empty($search_supplier)) {
    $sql = "SELECT * FROM suppliers WHERE supplier_id LIKE :search_id OR name LIKE :search_name 
            OR email_address LIKE :search_email OR contact_number LIKE :search_contact ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'search_id'      => "%$search_supplier%", 
        'search_name'    => "%$search_supplier%",
        'search_email'   => "%$search_supplier%", 
        'search_contact' => "%$search_supplier%"
    ]);
    $suppliers_list_filtered = $stmt->fetchAll();
} else {
    $suppliers_list_filtered = $suppliers_list_global;
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
        <p>Welcome: <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></strong> (ID: <?php echo htmlspecialchars($_SESSION['employee_id']); ?>)</p>
        <p><a href="logout.php">Logout</a></p>
    </div>

    <?php if (!empty($message)): ?>
        <p><mark><strong>SYSTEM STATUS: <?php echo htmlspecialchars($message); ?></strong></mark></p>
    <?php endif; ?>

    <hr>

    <!-- PRODUCTS MANAGEMENT FORM -->
    <div class="products-management">
        <h2>Products Management</h2>
        
        <h3>Add New Product</h3>
        <form action="dashboard.php" method="POST">
            <!-- Product ID field removed for automatic calculation -->
            <input type="text" name="name" placeholder="Name" required>
            <input type="text" name="brand" placeholder="Brand" required>
            <input type="text" name="model" placeholder="Model">
            <input type="text" name="color" placeholder="Color">
            <input type="date" name="expiry_date">
            <select name="supplier_id">
                <option value="">-- Assign Supplier --</option>
                <?php foreach ($suppliers_list_global as $sup): ?>
                    <option value="<?php echo $sup['supplier_id']; ?>"><?php echo htmlspecialchars($sup['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="stock" placeholder="Stock" min="0" value="0" required>
            <button type="submit" name="btn_create_product">Add Product</button>
        </form>
        <br>

        <h3>Search Products</h3>
        <form action="dashboard.php" method="GET">
            <input type="text" name="search_product" value="<?php echo htmlspecialchars($search_product); ?>" placeholder="Search any product attribute...">
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
                                <!-- Rendered out as flat secure text value -->
                                <strong><?php echo htmlspecialchars($product['product_id']); ?></strong>
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                            </td>
                            <td><input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required></td>
                            <td><input type="text" name="brand" value="<?php echo htmlspecialchars($product['brand']); ?>" required></td>
                            <td><input type="text" name="model" value="<?php echo htmlspecialchars($product['model'] ?? ''); ?>"></td>
                            <td><input type="text" name="color" value="<?php echo htmlspecialchars($product['color'] ?? ''); ?>"></td>
                            <td><input type="date" name="expiry_date" value="<?php echo $product['expiry_date']; ?>"></td>
                            <td>
                                <select name="supplier_id">
                                    <option value="">-- None --</option>
                                    <?php foreach ($suppliers_list_global as $sup): ?>
                                        <option value="<?php echo $sup['supplier_id']; ?>" <?php echo ($product['supplier_id'] == $sup['supplier_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sup['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="stock" value="<?php echo $product['stock']; ?>" min="0" required style="width:60px;"></td>
                            <td><button type="submit" name="btn_update_product">Save</button></td>
                        </form>
                        <td>
                            <form action="dashboard.php" method="POST" onsubmit="return confirm('Delete record permanently?');">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
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
            <!-- Supplier ID field removed for automatic calculation -->
            <input type="text" name="name" placeholder="Supplier Name" required>
            <input type="email" name="email_ad" placeholder="Email Address" required>
            <input type="text" name="contact_nb" placeholder="Contact Number" required>
            <button type="submit" name="btn_create_supplier">Register Supplier</button>
        </form>
        <br>

        <h3>Search Suppliers</h3>
        <form action="dashboard.php" method="GET">
            <input type="text" name="search_supplier" value="<?php echo htmlspecialchars($search_supplier); ?>" placeholder="Search any supplier attribute...">
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
                                <!-- Rendered out as flat secure text value -->
                                <strong><?php echo htmlspecialchars($supplier['supplier_id']); ?></strong>
                                <input type="hidden" name="supplier_id" value="<?php echo $supplier['supplier_id']; ?>">
                            </td>
                            <td><input type="text" name="name" value="<?php echo htmlspecialchars($supplier['name']); ?>" required></td>
                            <td><input type="email" name="email_ad" value="<?php echo htmlspecialchars($supplier['email_address']); ?>" required></td>
                            <td><input type="text" name="contact_nb" value="<?php echo htmlspecialchars($supplier['contact_number']); ?>" required></td>
                            <td><button type="submit" name="btn_update_supplier">Save</button></td>
                        </form>
                        <td>
                            <form action="dashboard.php" method="POST" onsubmit="return confirm('Delete permanently?');">
                                <input type="hidden" name="supplier_id" value="<?php echo $supplier['supplier_id']; ?>">
                                <button type="submit" name="btn_delete_supplier">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</body>
</html>