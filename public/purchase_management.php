<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if(!isset($_SESSION['user_id'])){
    header("location: index.php");
    exit;
}

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php';

$database = new Database();
$message = '';

// Fetch company profile for header/navigation
$company_name_header = "Your Company Name"; // Default
$company_logo_header = ""; // Default

$database->query('SELECT company_name, company_logo FROM company_profile LIMIT 1');
$header_company_profile = $database->single();

if($header_company_profile) {
    $company_name_header = htmlspecialchars($header_company_profile->company_name);
    $company_logo_header = htmlspecialchars($header_company_profile->company_logo);
}

// Fetch suppliers for dropdown
$database->query('SELECT id, company_name FROM suppliers ORDER BY company_name ASC');
$suppliers = $database->resultSet();

// Fetch products for dropdown
$database->query('SELECT id, product_name, product_category, unit, available_stock, selling_price, purchase_price, cgst, sgst FROM products ORDER BY product_name ASC');
$products_list = $database->resultSet();

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_purchase'])) {
        $supplier_id = $_POST['supplier_id'];
        $invoice_no = trim($_POST['invoice_no']);
        $invoice_date = $_POST['invoice_date'];
        $total_amount = 0; // Will be calculated from items

        // Handle invoice file upload
        $invoice_file_path = NULL;
        if (isset($_FILES['invoice_file']) && $_FILES['invoice_file']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/invoices/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_name = uniqid() . '_' . basename($_FILES['invoice_file']['name']);
            if (move_uploaded_file($_FILES['invoice_file']['tmp_name'], $upload_dir . $file_name)) {
                $invoice_file_path = 'uploads/invoices/' . $file_name;
            }
        }

        // Insert purchase order
        try {
            $database->query('INSERT INTO purchase_orders (supplier_id, invoice_no, invoice_date, total_amount, invoice_file) VALUES (:supplier_id, :invoice_no, :invoice_date, :total_amount, :invoice_file)');
            $database->bind(':supplier_id', $supplier_id);
            $database->bind(':invoice_no', $invoice_no);
            $database->bind(':invoice_date', $invoice_date);
            $database->bind(':total_amount', $total_amount); // Placeholder, will update later
            $database->bind(':invoice_file', $invoice_file_path);

            if ($database->execute()) {
                $purchase_order_id = $database->lastInsertId();
                $total_amount_calculated = 0;

                // Insert purchase order items and update product stock
                foreach ($_POST['product_id'] as $key => $product_id) {
                    $quantity = $_POST['quantity'][$key];
                    $purchase_price = $_POST['purchase_price'][$key];
                    $cgst = $_POST['cgst'][$key];
                    $sgst = $_POST['sgst'][$key];

                    $subtotal = ($quantity * $purchase_price) + (($quantity * $purchase_price) * ($cgst / 100)) + (($quantity * $purchase_price) * ($sgst / 100));
                    $total_amount_calculated += $subtotal;

                    $database->query('INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, unit_price, subtotal, purchase_price, cgst, sgst) VALUES (:purchase_order_id, :product_id, :quantity, :unit_price, :subtotal, :purchase_price, :cgst, :sgst)');
                    $database->bind(':purchase_order_id', $purchase_order_id);
                    $database->bind(':product_id', $product_id);
                    $database->bind(':quantity', $quantity);
                    $database->bind(':unit_price', $purchase_price); // unit_price in items table is purchase_price
                    $database->bind(':subtotal', $subtotal);
                    $database->bind(':purchase_price', $purchase_price);
                    $database->bind(':cgst', $cgst);
                    $database->bind(':sgst', $sgst);
                    $database->execute();

                    // Update product stock
                    $database->query('UPDATE products SET available_stock = available_stock + :quantity WHERE id = :product_id');
                    $database->bind(':quantity', $quantity);
                    $database->bind(':product_id', $product_id);
                    $database->execute();
                }

                // Update total_amount in purchase_orders table
                $database->query('UPDATE purchase_orders SET total_amount = :total_amount WHERE id = :purchase_order_id');
                $database->bind(':total_amount', $total_amount_calculated);
                $database->bind(':purchase_order_id', $purchase_order_id);
                $database->execute();

                $message = "Purchase order added successfully!";
            } else {
                $message = "Error adding purchase order.";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'invoice_no') !== false) {
                $message = "Error: Duplicate Invoice Number. Please use a unique invoice number.";
            } else {
                $message = "An unexpected database error occurred: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['edit_purchase'])) {
        $id = $_POST['purchase_id'];
        $supplier_id = $_POST['supplier_id'];
        $invoice_no = trim($_POST['invoice_no']);
        $invoice_date = $_POST['invoice_date'];
        $total_amount = 0; // Will be recalculated from items

        // Handle invoice file upload
        $invoice_file_path = $_POST['current_invoice_file'] ?? NULL; // Keep existing if not new upload
        if (isset($_FILES['invoice_file']) && $_FILES['invoice_file']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/invoices/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_name = uniqid() . '_' . basename($_FILES['invoice_file']['name']);
            if (move_uploaded_file($_FILES['invoice_file']['tmp_name'], $upload_dir . $file_name)) {
                $invoice_file_path = 'uploads/invoices/' . $file_name;
            }
        }

        try {
            // Revert stock for old items before updating
            $database->query('SELECT product_id, quantity FROM purchase_order_items WHERE purchase_order_id = :purchase_order_id');
            $database->bind(':purchase_order_id', $id);
            $old_items = $database->resultSet();
            foreach($old_items as $item) {
                $database->query('UPDATE products SET available_stock = available_stock - :quantity WHERE id = :product_id');
                $database->bind(':quantity', $item->quantity);
                $database->bind(':product_id', $item->product_id);
                $database->execute();
            }

            // Delete existing purchase items
            $database->query('DELETE FROM purchase_order_items WHERE purchase_order_id = :id');
            $database->bind(':id', $id);
            $database->execute();

            // Update purchase order main record
            $database->query('UPDATE purchase_orders SET supplier_id = :supplier_id, invoice_no = :invoice_no, invoice_date = :invoice_date, total_amount = :total_amount, invoice_file = :invoice_file WHERE id = :id');
            $database->bind(':supplier_id', $supplier_id);
            $database->bind(':invoice_no', $invoice_no);
            $database->bind(':invoice_date', $invoice_date);
            $database->bind(':total_amount', $total_amount); // Placeholder, will update later
            $database->bind(':invoice_file', $invoice_file_path);
            $database->bind(':id', $id);
            $database->execute();

            $total_amount_calculated = 0;
            // Insert updated purchase order items and update product stock
            if (isset($_POST['product_id']) && is_array($_POST['product_id'])) {
                foreach ($_POST['product_id'] as $key => $product_id) {
                    $quantity = $_POST['quantity'][$key];
                    $purchase_price = $_POST['purchase_price'][$key];
                    $cgst = $_POST['cgst'][$key];
                    $sgst = $_POST['sgst'][$key];

                    $subtotal = ($quantity * $purchase_price) + (($quantity * $purchase_price) * ($cgst / 100)) + (($quantity * $purchase_price) * ($sgst / 100));
                    $total_amount_calculated += $subtotal;

                    $database->query('INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, unit_price, subtotal, purchase_price, cgst, sgst) VALUES (:purchase_order_id, :product_id, :quantity, :unit_price, :subtotal, :purchase_price, :cgst, :sgst)');
                    $database->bind(':purchase_order_id', $id);
                    $database->bind(':product_id', $product_id);
                    $database->bind(':quantity', $quantity);
                    $database->bind(':unit_price', $purchase_price); // unit_price in items table is purchase_price
                    $database->bind(':subtotal', $subtotal);
                    $database->bind(':purchase_price', $purchase_price);
                    $database->bind(':cgst', $cgst);
                    $database->bind(':sgst', $sgst);
                    $database->execute();

                    // Update product stock
                    $database->query('UPDATE products SET available_stock = available_stock + :quantity WHERE id = :product_id');
                    $database->bind(':quantity', $quantity);
                    $database->bind(':product_id', $product_id);
                    $database->execute();
                }
            }

            // Update total_amount in purchase_orders table
            $database->query('UPDATE purchase_orders SET total_amount = :total_amount WHERE id = :purchase_order_id');
            $database->bind(':total_amount', $total_amount_calculated);
            $database->bind(':purchase_order_id', $id);
            $database->execute();

            $message = "Purchase order updated successfully!";
        } catch (PDOException $e) {
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'invoice_no') !== false) {
                $message = "Error: Duplicate Invoice Number. Please use a unique invoice number.";
            } else {
                $message = "An unexpected database error occurred: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_purchase'])) {
        $id = $_POST['purchase_id'];
        
        // Before deleting purchase order, revert stock changes
        $database->query('SELECT product_id, quantity FROM purchase_order_items WHERE purchase_order_id = :purchase_order_id');
        $database->bind(':purchase_order_id', $id);
        $items_to_revert = $database->resultSet();

        foreach($items_to_revert as $item) {
            $database->query('UPDATE products SET available_stock = available_stock - :quantity WHERE id = :product_id');
            $database->bind(':quantity', $item->quantity);
            $database->bind(':product_id', $item->product_id);
            $database->execute();
        }

        // Delete purchase order items first
        $database->query('DELETE FROM purchase_order_items WHERE purchase_order_id = :id');
        $database->bind(':id', $id);
        $database->execute();

        // Then delete purchase order
        $database->query('DELETE FROM purchase_orders WHERE id = :id');
        $database->bind(':id', $id);
        if ($database->execute()) {
            $message = "Purchase order deleted successfully!";
        } else {
            $message = "Error deleting purchase order.";
        }
    }
}

// Search and filter parameters
$search_invoice_no = $_GET['search_invoice_no'] ?? '';
$search_supplier = $_GET['search_supplier'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_clauses = [];
$bind_params = [];

if (!empty($search_invoice_no)) {
    $where_clauses[] = "po.invoice_no LIKE :search_invoice_no";
    $bind_params[':search_invoice_no'] = '%' . $search_invoice_no . '%';
}
if (!empty($search_supplier)) {
    $where_clauses[] = "s.company_name LIKE :search_supplier";
    $bind_params[':search_supplier'] = '%' . $search_supplier . '%';
}
if (!empty($start_date)) {
    $where_clauses[] = "po.invoice_date >= :start_date";
    $bind_params[':start_date'] = $start_date;
}
if (!empty($end_date)) {
    $where_clauses[] = "po.invoice_date <= :end_date";
    $bind_params[':end_date'] = $end_date;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Pagination settings
$purchases_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $purchases_per_page;

// Get total number of purchase orders for pagination
$database->query("SELECT COUNT(*) FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id" . $where_sql);
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$total_purchases = $database->single()->{'COUNT(*)'};
$total_pages = ceil($total_purchases / $purchases_per_page);

// Fetch purchase orders
$database->query("SELECT po.*, s.company_name FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id" . $where_sql . " ORDER BY po.invoice_date DESC LIMIT :offset, :purchases_per_page");
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$database->bind(':offset', $offset, PDO::PARAM_INT);
$database->bind(':purchases_per_page', $purchases_per_page, PDO::PARAM_INT);
$purchase_orders = $database->resultSet();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Management - CRM App</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to bottom, #FF0000, #800080, #A7D129);
        }
        .header {
            background-color: #333;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header .logo {
            height: 40px;
            margin-right: 10px;
        }
        .header .company-info {
            display: flex;
            align-items: center;
        }
        .header .user-profile {
            display: flex;
            align-items: center;
        }
        .header .user-profile img {
            height: 30px;
            width: 30px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .navbar {
            background-color: #444;
            padding: 10px 20px;
        }
        .navbar a {
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            margin-right: 10px;
        }
        .navbar a:hover {
            background-color: #555;
            border-radius: 5px;
        }
        .wrapper {
            display: flex;
        }
        .side-navigation {
            width: 200px;
            background-color: #555;
            color: white;
            padding-top: 20px;
            min-height: calc(100vh - 110px); /* Adjust based on header/footer height */
        }
        .side-navigation a {
            display: block;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
        }
        .side-navigation a:hover {
            background-color: #666;
        }
        .content {
            flex-grow: 1;
            padding: 20px;
        }
        .footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 10px 0;
            position: relative;
            bottom: 0;
            width: 100%;
        }
        .table-container {
            background-color: rgba(0, 0, 0, 0.6);
            padding: 20px;
            border-radius: 10px;
            color: white;
        }
        .table-container h2 {
            color: white;
            margin-bottom: 20px;
        }
        .table-container .form-inline .form-control,
        .table-container .form-group .form-control {
            background-color: rgba(0, 0, 0, 0.7); /* Darker, more opaque background */
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white; /* White text color */
        }
        .table-container .form-inline .form-control::placeholder,
        .table-container .form-group .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5); /* Lighter placeholder for contrast */
        }
        .table-container .form-inline .btn,
        .table-container .btn-primary {
            background-color: #A7D129;
            border-color: #A7D129;
            color: black;
        }
        .table-container .form-inline .btn:hover,
        .table-container .btn-primary:hover {
            background-color: #8CBF20;
            border-color: #8CBF20;
        }
        .table-container .table {
            color: white;
        }
        .table-container .table thead th {
            border-bottom: 2px solid rgba(255, 255, 255, 0.5);
        }
        .table-container .table tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .table-container .table tbody tr:last-child {
            border-bottom: none;
        }
        .table-container .table tbody tr:hover {
            background-color: white; /* White background on hover */
            color: black; /* Black text on hover */
        }
        .modal-content {
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
        }
        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }
        .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.3);
        }
        .modal-title {
            color: white;
        }
        .close {
            color: white;
            opacity: 1;
        }
        .close:hover {
            color: #ddd;
        }
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .pagination .page-item .page-link {
            background-color: rgba(0, 0, 0, 0.6);
            border-color: rgba(255, 255, 255, 0.3);
            color: white;
        }
        .pagination .page-item.active .page-link {
            background-color: #A7D129;
            border-color: #A7D129;
            color: black;
        }
        .pagination .page-item .page-link:hover {
            background-color: rgba(0, 0, 0, 0.8);
            border-color: rgba(255, 255, 255, 0.5);
        }
        .item-row {
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .item-row .form-group {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <?php if (!empty($company_logo_header)): ?>
                <img src="../<?php echo $company_logo_header; ?>" alt="Company Logo" class="logo">
            <?php endif; ?>
            <span><?php echo $company_name_header; ?></span>
        </div>
        <div class="user-profile">
            <?php if (!empty($_SESSION['profile_image'])): ?>
                <img src="../<?php echo htmlspecialchars($_SESSION['profile_image']); ?>" alt="User Avatar">
            <?php else: ?>
                <img src="https://via.placeholder.com/30" alt="User Avatar">
            <?php endif; ?>
            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
    </div>

    <nav class="navbar">
        <a href="index.php?page=dashboard">Dashboard</a>
        <a href="index.php?page=company_profile">Company Profile</a>
        <a href="index.php?page=product_management">Product Management</a>
        <a href="index.php?page=customer_management">Customer Management</a>
        <a href="index.php?page=supplier_management">Supplier Management</a>
        <a href="index.php?page=purchase_management">Purchase Management</a>
        <a href="index.php?page=user_profile">User Profile</a>
        <a href="index.php?page=logout">Logout</a>
    </nav>

    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="content">
            <h1>Purchase Management</h1>

            <?php if (!empty($message)): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="table-container">
                <h2>Purchase Order List</h2>

                <!-- Search Bar -->
                <form class="form-inline mb-4" method="GET" action="purchase_management.php">
                    <input type="text" class="form-control mr-sm-2" name="search_invoice_no" placeholder="Search by Invoice No." value="<?php echo htmlspecialchars($search_invoice_no); ?>">
                    <input type="text" class="form-control mr-sm-2" name="search_supplier" placeholder="Search by Supplier Name" value="<?php echo htmlspecialchars($search_supplier); ?>">
                    <input type="date" class="form-control mr-sm-2" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    <input type="date" class="form-control mr-sm-2" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    <button class="btn btn-primary my-2 my-sm-0" type="submit">Search</button>
                    <a href="export_purchase_excel.php?search_invoice_no=<?php echo urlencode($search_invoice_no); ?>&search_supplier=<?php echo urlencode($search_supplier); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-success ml-2">Download Excel</a>
                    <button type="button" class="btn btn-info ml-2" data-toggle="modal" data-target="#addPurchaseModal">Add New Purchase</button>
                </form>

                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Supplier</th>
                            <th>Invoice No.</th>
                            <th>Invoice Date</th>
                            <th>Total Amount</th>
                            <th>Invoice File</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($purchase_orders)): ?>
                            <?php foreach ($purchase_orders as $purchase): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($purchase->id); ?></td>
                                    <td><?php echo htmlspecialchars($purchase->company_name); ?></td>
                                    <td><?php echo htmlspecialchars($purchase->invoice_no); ?></td>
                                    <td><?php echo htmlspecialchars($purchase->invoice_date); ?></td>
                                    <td>₹<?php echo htmlspecialchars(number_format($purchase->total_amount, 2)); ?></td>
                                    <td>
                                        <?php if (!empty($purchase->invoice_file)): ?>
                                            <a href="../<?php echo htmlspecialchars($purchase->invoice_file); ?>" target="_blank" class="btn btn-sm btn-secondary">View</a>
                                            <a href="../<?php echo htmlspecialchars($purchase->invoice_file); ?>" download class="btn btn-sm btn-secondary">Download</a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($purchase->created_at); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info edit-purchase-btn" 
                                                data-id="<?php echo $purchase->id; ?>" 
                                                data-supplier-id="<?php echo htmlspecialchars($purchase->supplier_id); ?>" 
                                                data-invoice-no="<?php echo htmlspecialchars($purchase->invoice_no); ?>" 
                                                data-invoice-date="<?php echo htmlspecialchars($purchase->invoice_date); ?>" 
                                                data-total-amount="<?php echo htmlspecialchars($purchase->total_amount); ?>" 
                                                data-invoice-file="<?php echo htmlspecialchars($purchase->invoice_file); ?>"
                                                data-toggle="modal" data-target="#editPurchaseModal">Edit</button>
                                        <form method="POST" action="purchase_management.php" style="display:inline-block;">
                                            <input type="hidden" name="purchase_id" value="<?php echo $purchase->id; ?>">
                                            <button type="submit" name="delete_purchase" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this purchase order? This will also revert stock changes.');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">No purchase orders found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <nav aria-label="Purchase Pagination" class="pagination-container">
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="purchase_management.php?page=<?php echo $i; ?>&search_invoice_no=<?php echo urlencode($search_invoice_no); ?>&search_supplier=<?php echo urlencode($search_supplier); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>

            <!-- Add Purchase Modal -->
            <div class="modal fade" id="addPurchaseModal" tabindex="-1" role="dialog" aria-labelledby="addPurchaseModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addPurchaseModalLabel">Add New Purchase Order</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form method="POST" action="purchase_management.php" enctype="multipart/form-data">
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for="supplier_id">Supplier Company Name</label>
                                    <select class="form-control" id="supplier_id" name="supplier_id" required>
                                        <option value="">Select Supplier</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?php echo $supplier->id; ?>"><?php echo htmlspecialchars($supplier->company_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="invoice_no">Invoice No.</label>
                                    <input type="text" class="form-control" id="invoice_no" name="invoice_no" required>
                                </div>
                                <div class="form-group">
                                    <label for="invoice_date">Invoice Date</label>
                                    <input type="date" class="form-control" id="invoice_date" name="invoice_date" required>
                                </div>
                                <div class="form-group">
                                    <label for="invoice_file">Upload Invoice (PDF/Image)</label>
                                    <input type="file" class="form-control-file" id="invoice_file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png">
                                </div>

                                <hr>
                                <h4>Products</h4>
                                <div id="product-items-container">
                                    <!-- Product items will be added here by JavaScript -->
                                    <div class="item-row">
                                        <div class="form-group">
                                            <label>Product Name</label>
                                            <select class="form-control product-select" name="product_id[]" required>
                                                <option value="">Select Product</option>
                                                <?php foreach ($products_list as $product): ?>
                                                    <option value="<?php echo $product->id; ?>" 
                                                            data-category="<?php echo htmlspecialchars($product->product_category); ?>" 
                                                            data-unit="<?php echo htmlspecialchars($product->unit); ?>" 
                                                            data-stock="<?php echo htmlspecialchars($product->available_stock); ?>"
                                                            data-purchase-price="<?php echo htmlspecialchars($product->purchase_price); ?>"
                                                            data-cgst="<?php echo htmlspecialchars($product->cgst); ?>"
                                                            data-sgst="<?php echo htmlspecialchars($product->sgst); ?>">
                                                        <?php echo htmlspecialchars($product->product_name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Product Category</label>
                                            <input type="text" class="form-control product-category" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label>Unit</label>
                                            <input type="text" class="form-control product-unit" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label>Available Stock</label>
                                            <input type="text" class="form-control product-stock" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label>Quantity</label>
                                            <input type="number" class="form-control product-quantity" name="quantity[]" min="1" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Purchase Price (per unit)</label>
                                            <input type="number" step="0.01" class="form-control product-purchase-price" name="purchase_price[]" min="0" required>
                                        </div>
                                        <div class="form-group">
                                            <label>CGST (%)</label>
                                            <input type="number" step="0.01" class="form-control product-cgst" name="cgst[]" value="0" min="0">
                                        </div>
                                        <div class="form-group">
                                            <label>SGST (%)</label>
                                            <input type="number" step="0.01" class="form-control product-sgst" name="sgst[]" value="0" min="0">
                                        </div>
                                        <div class="form-group">
                                            <label>Subtotal</label>
                                            <input type="text" class="form-control product-subtotal" readonly>
                                        </div>
                                        <button type="button" class="btn btn-danger remove-item-btn">Remove</button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary mt-3" id="add-more-item">Add More Item</button>

                                <hr>
                                <h4>Summary</h4>
                                <div class="form-group">
                                    <label>Total Amount</label>
                                    <input type="text" class="form-control" id="total_amount_display" readonly>
                                    <input type="hidden" name="total_amount" id="total_amount_hidden">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="submit" name="add_purchase" class="btn btn-primary">Submit Purchase</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Purchase Modal -->
            <div class="modal fade" id="editPurchaseModal" tabindex="-1" role="dialog" aria-labelledby="editPurchaseModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editPurchaseModalLabel">Edit Purchase Order</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form method="POST" action="purchase_management.php" enctype="multipart/form-data">
                            <div class="modal-body">
                                <input type="hidden" id="edit_purchase_id" name="purchase_id">
                                <div class="form-group">
                                    <label for="edit_supplier_id">Supplier Company Name</label>
                                    <select class="form-control" id="edit_supplier_id" name="supplier_id" required>
                                        <option value="">Select Supplier</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?php echo $supplier->id; ?>"><?php echo htmlspecialchars($supplier->company_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="edit_invoice_no">Invoice No.</label>
                                    <input type="text" class="form-control" id="edit_invoice_no" name="invoice_no" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_invoice_date">Invoice Date</label>
                                    <input type="date" class="form-control" id="edit_invoice_date" name="invoice_date" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_invoice_file">Upload Invoice (PDF/Image)</label>
                                    <input type="file" class="form-control-file" id="edit_invoice_file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png">
                                    <input type="hidden" id="current_invoice_file" name="current_invoice_file">
                                    <div id="current_invoice_file_display" class="mt-2">
                                        <!-- Current file link will be displayed here -->
                                    </div>
                                </div>

                                <hr>
                                <h4>Products</h4>
                                <div id="edit-product-items-container">
                                    <!-- Product items will be loaded here by JavaScript -->
                                </div>
                                <button type="button" class="btn btn-secondary mt-3" id="edit-add-more-item">Add More Item</button>

                                <hr>
                                <h4>Summary</h4>
                                <div class="form-group">
                                    <label>Total Amount</label>
                                    <input type="text" class="form-control" id="edit_total_amount_display" readonly>
                                    <input type="hidden" name="total_amount" id="edit_total_amount_hidden">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="submit" name="edit_purchase" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> CRM App. All rights reserved.</p>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // --- Start of Add Purchase Modal Logic ---

            // Function to calculate subtotal for an item row in the Add modal
            function calculateSubtotalAdd(itemRow) {
                var quantity = parseFloat(itemRow.find('.product-quantity').val()) || 0;
                var purchasePrice = parseFloat(itemRow.find('.product-purchase-price').val()) || 0;
                var cgst = parseFloat(itemRow.find('.product-cgst').val()) || 0;
                var sgst = parseFloat(itemRow.find('.product-sgst').val()) || 0;

                var itemTotal = quantity * purchasePrice;
                var gstAmount = itemTotal * (cgst / 100) + itemTotal * (sgst / 100);
                var subtotal = itemTotal + gstAmount;

                itemRow.find('.product-subtotal').val(subtotal.toFixed(2));
                updateTotalAmountAdd();
            }

            // Function to update total amount for the Add modal
            function updateTotalAmountAdd() {
                var totalAmount = 0;
                $('#product-items-container .item-row').each(function() {
                    totalAmount += parseFloat($(this).find('.product-subtotal').val()) || 0;
                });
                $('#total_amount_display').val(totalAmount.toFixed(2));
                $('#total_amount_hidden').val(totalAmount.toFixed(2));
            }

            // Event listener for product selection change in Add modal
            $('#product-items-container').on('change', '.product-select', function() {
                var selectedOption = $(this).find('option:selected');
                var itemRow = $(this).closest('.item-row');
                itemRow.find('.product-category').val(selectedOption.data('category'));
                itemRow.find('.product-unit').val(selectedOption.data('unit'));
                itemRow.find('.product-stock').val(selectedOption.data('stock'));
                itemRow.find('.product-purchase-price').val(selectedOption.data('purchase-price'));
                itemRow.find('.product-cgst').val(selectedOption.data('cgst'));
                itemRow.find('.product-sgst').val(selectedOption.data('sgst'));
                calculateSubtotalAdd(itemRow);
            });

            // Event listeners for input changes in Add modal
            $('#product-items-container').on('input', '.product-quantity, .product-purchase-price, .product-cgst, .product-sgst', function() {
                calculateSubtotalAdd($(this).closest('.item-row'));
            });

            // Add More Item button click in Add modal
            $('#add-more-item').on('click', function() {
                var newItemRow = $('#product-items-container .item-row').first().clone(true);
                newItemRow.find('input, select').val('');
                newItemRow.find('.product-select option:first').prop('selected', true);
                newItemRow.find('.product-cgst, .product-sgst, .product-subtotal').val('0');
                newItemRow.find('.remove-item-btn').show();
                $('#product-items-container').append(newItemRow);
                updateTotalAmountAdd();
            });

            // Remove Item button click in Add modal
            $('#product-items-container').on('click', '.remove-item-btn', function() {
                if ($('#product-items-container .item-row').length > 1) {
                    $(this).closest('.item-row').remove();
                    updateTotalAmountAdd();
                } else {
                    alert("You must have at least one product item.");
                }
            });

            // Initial setup for Add modal
            if ($('#product-items-container .item-row').length === 1) {
                $('#product-items-container .remove-item-btn').hide();
            }
            updateTotalAmountAdd();

            // --- End of Add Purchase Modal Logic ---


            // --- Start of Edit Purchase Modal Logic ---

            // Caching product options for performance
            var productsOptionsHtml = '';
            <?php foreach ($products_list as $product): ?>
                productsOptionsHtml += '<option value="<?php echo $product->id; ?>" ' +
                    'data-category="<?php echo htmlspecialchars(addslashes($product->product_category)); ?>" ' +
                    'data-unit="<?php echo htmlspecialchars(addslashes($product->unit)); ?>" ' +
                    'data-stock="<?php echo htmlspecialchars(addslashes($product->available_stock)); ?>" ' +
                    'data-purchase-price="<?php echo htmlspecialchars(addslashes($product->purchase_price)); ?>" ' +
                    'data-cgst="<?php echo htmlspecialchars(addslashes($product->cgst)); ?>" ' +
                    'data-sgst="<?php echo htmlspecialchars(addslashes($product->sgst)); ?>">' +
                    '<?php echo htmlspecialchars(addslashes($product->product_name)); ?>' +
                '</option>';
            <?php endforeach; ?>

            function getNewItemRowHtml() {
                return '<div class="item-row">' +
                           '<div class="form-group">' +
                               '<label>Product Name</label>' +
                               '<select class="form-control product-select" name="product_id[]" required>' +
                                   '<option value="">Select Product</option>' +
                                   productsOptionsHtml +
                               '</select>' +
                           '</div>' +
                           '<div class="form-group"><label>Product Category</label><input type="text" class="form-control product-category" readonly></div>' +
                           '<div class="form-group"><label>Unit</label><input type="text" class="form-control product-unit" readonly></div>' +
                           '<div class="form-group"><label>Available Stock</label><input type="text" class="form-control product-stock" readonly></div>' +
                           '<div class="form-group"><label>Quantity</label><input type="number" class="form-control product-quantity" name="quantity[]" value="1" min="1" required></div>' +
                           '<div class="form-group"><label>Purchase Price (per unit)</label><input type="number" step="0.01" class="form-control product-purchase-price" name="purchase_price[]" value="0" min="0" required></div>' +
                           '<div class="form-group"><label>CGST (%)</label><input type="number" step="0.01" class="form-control product-cgst" name="cgst[]" value="0" min="0"></div>' +
                           '<div class="form-group"><label>SGST (%)</label><input type="number" step="0.01" class="form-control product-sgst" name="sgst[]" value="0" min="0"></div>' +
                           '<div class="form-group"><label>Subtotal</label><input type="text" class="form-control product-subtotal" readonly></div>' +
                           '<button type="button" class="btn btn-danger remove-item-btn">Remove</button>' +
                       '</div>';
            }
            
            function calculateSubtotalEdit(itemRow) {
                var quantity = parseFloat(itemRow.find('.product-quantity').val()) || 0;
                var purchasePrice = parseFloat(itemRow.find('.product-purchase-price').val()) || 0;
                var cgst = parseFloat(itemRow.find('.product-cgst').val()) || 0;
                var sgst = parseFloat(itemRow.find('.product-sgst').val()) || 0;
                var itemTotal = quantity * purchasePrice;
                var gstAmount = itemTotal * (cgst / 100) + itemTotal * (sgst / 100);
                var subtotal = itemTotal + gstAmount;
                itemRow.find('.product-subtotal').val(subtotal.toFixed(2));
                updateTotalAmountEdit();
            }

            function updateTotalAmountEdit() {
                var totalAmount = 0;
                $('#edit-product-items-container .item-row').each(function() {
                    totalAmount += parseFloat($(this).find('.product-subtotal').val()) || 0;
                });
                $('#edit_total_amount_display').val(totalAmount.toFixed(2));
                $('#edit_total_amount_hidden').val(totalAmount.toFixed(2));
            }

            $('.edit-purchase-btn').on('click', function() {
                var purchaseId = $(this).data('id');
                $('#edit_purchase_id').val(purchaseId);
                $('#edit_supplier_id').val($(this).data('supplier-id'));
                $('#edit_invoice_no').val($(this).data('invoice-no'));
                $('#edit_invoice_date').val($(this).data('invoice-date'));
                
                var invoiceFile = $(this).data('invoice-file');
                var fileDisplayHtml = invoiceFile ? '<a href="../' + invoiceFile + '" target="_blank" class="btn btn-sm btn-secondary">View</a> <a href="../' + invoiceFile + '" download class="btn btn-sm btn-secondary">Download</a>' : '';
                $('#current_invoice_file_display').html(fileDisplayHtml);
                $('#current_invoice_file').val(invoiceFile);

                $.ajax({
                    url: 'fetch_purchase_items.php',
                    type: 'GET',
                    data: { purchase_id: purchaseId },
                    success: function(response) {
                        var items = JSON.parse(response);
                        var container = $('#edit-product-items-container');
                        container.empty();

                        if (items.length > 0) {
                            items.forEach(function(item) {
                                var newItemRow = $(getNewItemRowHtml());
                                newItemRow.find('.product-select').val(item.product_id);
                                newItemRow.find('.product-category').val(item.product_category);
                                newItemRow.find('.product-unit').val(item.unit);
                                newItemRow.find('.product-stock').val(item.available_stock);
                                newItemRow.find('.product-quantity').val(item.quantity);
                                newItemRow.find('.product-purchase-price').val(item.purchase_price);
                                newItemRow.find('.product-cgst').val(item.cgst);
                                newItemRow.find('.product-sgst').val(item.sgst);
                                newItemRow.find('.product-subtotal').val(item.subtotal);
                                container.append(newItemRow);
                            });
                        } else {
                            container.append(getNewItemRowHtml());
                        }

                        if (container.find('.item-row').length === 1) {
                            container.find('.remove-item-btn').hide();
                        }
                        updateTotalAmountEdit();
                    },
                    error: function() {
                        alert('Failed to fetch purchase items. Please try again.');
                    }
                });
            });

            $('#edit-add-more-item').on('click', function() {
                var newItemRow = $(getNewItemRowHtml());
                $('#edit-product-items-container').append(newItemRow);
                $('#edit-product-items-container .remove-item-btn').show();
            });

            $('#edit-product-items-container').on('click', '.remove-item-btn', function() {
                if ($('#edit-product-items-container .item-row').length > 1) {
                    $(this).closest('.item-row').remove();
                    updateTotalAmountEdit();
                } else {
                    alert("You must have at least one product item.");
                }
            });

            $('#edit-product-items-container').on('change', '.product-select', function() {
                var selectedOption = $(this).find('option:selected');
                var itemRow = $(this).closest('.item-row');
                itemRow.find('.product-category').val(selectedOption.data('category'));
                itemRow.find('.product-unit').val(selectedOption.data('unit'));
                itemRow.find('.product-stock').val(selectedOption.data('stock'));
                itemRow.find('.product-purchase-price').val(selectedOption.data('purchase-price'));
                itemRow.find('.product-cgst').val(selectedOption.data('cgst'));
                itemRow.find('.product-sgst').val(selectedOption.data('sgst'));
                calculateSubtotalEdit(itemRow);
            });

            $('#edit-product-items-container').on('input', '.product-quantity, .product-purchase-price, .product-cgst, .product-sgst', function() {
                calculateSubtotalEdit($(this).closest('.item-row'));
            });

            // --- End of Edit Purchase Modal Logic ---
        });
    </script>
</body>
</html>
