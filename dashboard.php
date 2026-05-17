<?php
session_start();
require_once 'config/db.php';

// UNAUTHORIZED ACCESS PROTECT
if (!isset($_SESSION['employee_id'])) {
    header("Location: login-page.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // CREATE PRODUCT
    if (isset($_POST['action_create_product'])) {
        $name  = trim($_POST['name']);
        $brand = trim($_POST['brand']);
        $stock = intval($_POST['stock']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO products (name, brand, stock) VALUES (:name, :brand, :stock)");
            $stmt->execute([
                'name'  => $name, 
                'brand' => $brand, 
                'stock' => $stock
            ]);
            $message = "Product added successfully!";
        } catch (PDOException $e) { 
            $message = "Error: " . $e->getMessage(); 
        }
    }

    // UPDATE PRODUCT STOCK
    if (isset($_POST['action_update_product'])) {
        $productId = trim($_POST['product_id']);
        $stock     = intval($_POST['stock']);
        
        try {
            $stmt = $pdo->prepare("UPDATE products SET stock = :stock WHERE product_id = :product_id");
            $stmt->execute([
                'stock'      => $stock, 
                'product_id' => $productId
            ]);
            $message = "Stock level updated!";
        } catch (PDOException $e) { 
            $message = "Error: " . $e->getMessage(); 
        }
    }

    // DELETE PRODUCT
    if (isset($_POST['action_delete_product'])) {
        $productId = trim($_POST['product_id']);
        
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = :product_id");
            $stmt->execute([
                'product_id' => $productId
            ]);
            $message = "Product removed.";
        } catch (PDOException $e) { 
            $message = "Error: " . $e->getMessage(); 
        }
    }

    // CREATE SUPPLIER
    if (isset($_POST['action_create_supplier'])) {
        $name    = trim($_POST['name']);
        $contact = trim($_POST['contact_number']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_number) VALUES (:name, :contact_number)");
            $stmt->execute([
                'name'           => $name, 
                'contact_number' => $contact
            ]);
            $message = "Supplier registered successfully!";
        } catch (PDOException $e) { 
            $message = "Error: " . $e->getMessage(); 
        }
    }

    // DELETE SUPPLIER
    if (isset($_POST['action_delete_supplier'])) {
        $supplierId = trim($_POST['supplier_id']);
        
        try {
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE supplier_id = :supplier_id");
            $stmt->execute([
                'supplier_id' => $supplierId
            ]);
            $message = "Supplier removed.";
        } catch (PDOException $e) { 
            $message = "Error: " . $e->getMessage(); 
        }
    }

    // LOG CONCURRENT FREIGHT SUPPLY SHIPMENT (TRIGGERS STORED PROCEDURE)
    if (isset($_POST['action_log_supply'])) {
        $supplierId = trim($_POST['supplier_id']);
        $productId  = trim($_POST['product_id']);
        $quantity   = intval($_POST['quantity']);
        $date       = $_POST['delivery_date'];
        
        try {
            $stmt = $pdo->prepare("CALL products_supply_log(:supplier_id, :product_id, :quantity, :delivery_date)");
            $stmt->execute([
                'supplier_id'   => $supplierId, 
                'product_id'    => $productId, 
                'quantity'      => $quantity, 
                'delivery_date' => $date
            ]);
            $message = "Supply shipment logged! Product stock successfully updated.";
        } catch (PDOException $e) { 
            $message = "Error: " . $e->getMessage(); 
        }
    }
}

$productsList  = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();
$suppliersList = $pdo->query("SELECT * FROM suppliers ORDER BY created_at DESC")->fetchAll();
$supplyHistory = $pdo->query("SELECT * FROM products_supply ORDER BY record_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS Management Dashboard</title>
</head>
<body>

    <div>
        <h1>Inventory Management System Dashboard</h1>
        <p>Welcome, Account User: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> (Employee ID: <?php echo htmlspecialchars($_SESSION['employee_id']); ?>)</p>
        <p><a href="logout.php">Logout from System</a></p>
    </div>

    <?php if (!empty($message)): ?>
        <p><strong>SYSTEM STATUS: <?php echo htmlspecialchars($message); ?></strong></p>
    <?php endif; ?>

    <hr>

    <div>
        <h2>Products Management</h2>
        <form action="dashboard.php" method="POST">
            <input type="text" name="name" placeholder="Product Name" required>
            <input type="text" name="brand" placeholder="Brand Name" required>
            <input type="number" name="stock" placeholder="Initial Stock" min="0" required>
            <button type="submit" name="action_create_product">Add Product</button>
        </form>
        <br>
        <table border="1">
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Name</th>
                    <th>Brand</th>
                    <th>Current Stock Level</th>
                    <th>Removal Control</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productsList as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['brand']); ?></td>
                        <td>
                            <form action="dashboard.php" method="POST">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                <input type="number" name="stock" value="<?php echo $product['stock']; ?>" min="0">
                                <button type="submit" name="action_update_product">Save Stock</button>
                            </form>
                        </td>
                        <td>
                            <form action="dashboard.php" method="POST" onsubmit="return confirm('Delete record permanently?');">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                <button type="submit" name="action_delete_product">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <hr>

    <div>
        <h2>Suppliers Directory</h2>
        <form action="dashboard.php" method="POST">
            <input type="text" name="name" placeholder="Supplier Name" required>
            <input type="text" name="contact_number" placeholder="Contact Information" required>
            <button type="submit" name="action_create_supplier">Register Supplier</button>
        </form>
        <br>
        <table border="1">
            <thead>
                <tr>
                    <th>Supplier ID</th>
                    <th>Name</th>
                    <th>Contact Info String</th>
                    <th>Removal Control</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliersList as $supplier): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($supplier['supplier_id']); ?></td>
                        <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                        <td><?php echo htmlspecialchars($supplier['contact_number']); ?></td>
                        <td>
                            <form action="dashboard.php" method="POST" onsubmit="return confirm('Delete supplier profile record?');">
                                <input type="hidden" name="supplier_id" value="<?php echo $supplier['supplier_id']; ?>">
                                <button type="submit" name="action_delete_supplier">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <hr>

    <div>
        <h2>Process Incoming Shipment Freight</h2>
        <form action="dashboard.php" method="POST">
            <select name="supplier_id" required>
                <option value="">-- Choose Origin Supplier --</option>
                <?php foreach ($suppliersList as $supp): ?>
                    <option value="<?php echo $supp['supplier_id']; ?>"><?php echo htmlspecialchars($supp['name']); ?> (<?php echo $supp['supplier_id']; ?>)</option>
                <?php endforeach; ?>
            </select>

            <select name="product_id" required>
                <option value="">-- Choose Target Product Item --</option>
                <?php foreach ($productsList as $prod): ?>
                    <option value="<?php echo $prod['product_id']; ?>"><?php echo htmlspecialchars($prod['name']); ?> (<?php echo $prod['product_id']; ?>)</option>
                <?php endforeach; ?>
            </select>

            <input type="number" name="quantity" placeholder="Quantity Count Received" min="1" required>
            <input type="date" name="delivery_date" value="<?php echo date('Y-m-d'); ?>" required>
            <button type="submit" name="action_log_supply">Log Freight Shipment</button>
        </form>
    </div>

    <hr>

    <div>
        <h2>Historical Audit Supply Log Ledger (Read-Only)</h2>
        <table border="1">
            <thead>
                <tr>
                    <th>Log Reference ID</th>
                    <th>Supplier Reference</th>
                    <th>Product Reference</th>
                    <th>Quantity Count Manifested</th>
                    <th>Delivery Day</th>
                    <th>Auto Timestamp Record</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($supplyHistory)): ?>
                    <tr><td colspan="6">No historical shipments populated in database log tables yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($supplyHistory as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['products_supply_id'] ?? $log['supply_product_id'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($log['supplier_id']); ?></td>
                            <td><?php echo htmlspecialchars($log['product_id']); ?></td>
                            <td>+<?php echo htmlspecialchars($log['quantity']); ?> items</td>
                            <td><?php echo htmlspecialchars($log['delivery_date']); ?></td>
                            <td><?php echo htmlspecialchars($log['record_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>
