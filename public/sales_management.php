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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id = $_POST['customer_id'] ?? null;
    $customer_name = trim($_POST['name']);
    $customer_address = trim($_POST['customer_address']);
    $customer_mobile = trim($_POST['customer_mobile']);
    $customer_gst = trim($_POST['customer_gst']);

    // Handle Customer (new or existing)
    if (empty($customer_id)) {
        // Check if customer with this name already exists
        $database->query('SELECT id FROM customers WHERE name = :name');
        $database->bind(':name', $customer_name);
        $existing_customer = $database->single();

        if ($existing_customer) {
            $customer_id = $existing_customer->id;
        } else {
            // Create new customer
            $database->query('INSERT INTO customers (name, address, mobile_no, gst_no) VALUES (:name, :address, :mobile_no, :gst_no)');
            $database->bind(':name', $customer_name);
            $database->bind(':address', $customer_address);
            $database->bind(':mobile_no', $customer_mobile);
            $database->bind(':gst_no', $customer_gst);
            if ($database->execute()) {
                $customer_id = $database->lastInsertId();
            } else {
                $message = "Error saving new customer.";
            }
        }
    }

    if (empty($message) && $customer_id) {
        // Generate Invoice Number (simple timestamp for now, improve for sequential)
        $invoice_no = 'INV-' . time();

        $sale_date = $_POST['sale_date'];
        $eway_bill_no = trim($_POST['eway_bill_no']);
        $advance_amount = floatval($_POST['advance_amount']);
        $payment_status = $_POST['payment_status'];
        $payment_mode = $_POST['payment_mode'];
        $other_payment_mode_details = ($payment_mode === 'other') ? trim($_POST['other_payment_mode_details']) : '';

        $net_amount = floatval($_POST['net_amount']);
        $total_discount = floatval($_POST['total_discount']);
        $total_cgst = floatval($_POST['total_cgst']);
        $total_sgst = floatval($_POST['total_sgst']);
        $total_amount = floatval($_POST['total_amount']);
        $pending_amount = floatval($_POST['pending_amount']);

        // Insert into sales table
        $database->query('INSERT INTO sales (invoice_no, customer_id, sale_date, eway_bill_no, advance_amount, payment_status, payment_mode, other_payment_mode_details, net_amount, total_discount, total_cgst, total_sgst, total_amount, pending_amount) VALUES (:invoice_no, :customer_id, :sale_date, :eway_bill_no, :advance_amount, :payment_status, :payment_mode, :other_payment_mode_details, :net_amount, :total_discount, :total_cgst, :total_sgst, :total_amount, :pending_amount)');
        $database->bind(':invoice_no', $invoice_no);
        $database->bind(':customer_id', $customer_id);
        $database->bind(':sale_date', $sale_date);
        $database->bind(':eway_bill_no', $eway_bill_no);
        $database->bind(':advance_amount', $advance_amount);
        $database->bind(':payment_status', $payment_status);
        $database->bind(':payment_mode', $payment_mode);
        $database->bind(':other_payment_mode_details', $other_payment_mode_details);
        $database->bind(':net_amount', $net_amount);
        $database->bind(':total_discount', $total_discount);
        $database->bind(':cgst_rate', $total_cgst);
        $database->bind(':sgst_rate', $total_sgst);
        $database->bind(':total_amount', $total_amount);
        $database->bind(':pending_amount', $pending_amount);

        if ($database->execute()) {
            $sale_id = $database->lastInsertId();

            // Insert sale items
            foreach ($_POST['items'] as $item) {
                $product_id = intval($item['product_id']);
                $quantity = intval($item['quantity']);
                $product_price = floatval($item['product_price']); // Get price from form, not DB
                $cgst = floatval($item['cgst']);
                $sgst = floatval($item['sgst']);
                $discount = floatval($item['discount']);
                $item_total_amount = floatval($item['total_amount']);

                if ($product_id) {
                    $database->query('INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, cgst_rate, sgst_rate, discount_rate, item_total_amount) VALUES (:sale_id, :product_id, :quantity, :unit_price, :cgst_rate, :sgst_rate, :discount_rate, :item_total_amount)');
                    $database->bind(':sale_id', $sale_id);
                    $database->bind(':product_id', $product_id);
                    $database->bind(':quantity', $quantity);
                    $database->bind(':unit_price', $product_price);
                    $database->bind(':cgst_rate', $cgst);
                    $database->bind(':sgst_rate', $sgst);
                    $database->bind(':discount_rate', $discount);
                    $database->bind(':item_total_amount', $item_total_amount);
                    $database->execute();
                }
            }
            $message = "Sale added successfully!";
            // Redirect to prevent form re-submission
            header("Location: sales_management.php?message=" . urlencode($message));
            exit();
        } else {
            $message = "Error adding sale.";
        }
    }
}

// Fetch company profile for header/navigation
$company_name_header = "Your Company Name"; // Default
$company_logo_header = ""; // Default

$database->query('SELECT company_name, company_logo FROM company_profile LIMIT 1');
$header_company_profile = $database->single();

if($header_company_profile) {
    $company_name_header = htmlspecialchars($header_company_profile->company_name);
    $company_logo_header = htmlspecialchars($header_company_profile->company_logo);
}

// Fetch company details for invoice
$company_details = null;
$database->query('SELECT company_name, address, email, gst_no, state_code, hsn_sac_code FROM company_profile LIMIT 1');
$company_details = $database->single();

// Fetch customers for dropdown
$customers = $database->resultSet('SELECT id, name, address, mobile_no, gst_no FROM customers');

// Fetch products for dropdown
$products = $database->resultSet('SELECT id, product_name, category, unit, price FROM products');

// Display message if redirected with one
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Management - CRM App</title>
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
        .form-row .col, .form-row [class^="col-"] {
            padding-right: 5px;
            padding-left: 5px;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .invoice-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .company-details, .customer-details {
            border: 1px solid #ccc;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .company-details p, .customer-details p {
            margin-bottom: 5px;
        }
        .item-details-table th, .item-details-table td {
            vertical-align: middle;
        }
        .total-summary-table th, .total-summary-table td {
            text-align: right;
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
        <a href="dashboard.php">Dashboard</a>
        <a href="company_profile.php">Company Profile</a>
        <a href="product_management.php">Product Management</a>
        <a href="sales_management.php">Sales</a>
        <a href="user_profile.php">User Profile</a>
        <a href="logout.php">Logout</a>
    </nav>

    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="content">
            <h1 class="invoice-header">TAX INVOICE</h1>

            <form action="" method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="company-details">
                            <h4>Company Details</h4>
                            <?php if ($company_details): ?>
                                <p><strong>Company Name:</strong> <?php echo htmlspecialchars($company_details->company_name); ?></p>
                                <p><strong>Address:</strong> <?php echo htmlspecialchars($company_details->address); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($company_details->email); ?></p>
                                <p><strong>GST No:</strong> <?php echo htmlspecialchars($company_details->gst_no); ?></p>
                                <p><strong>State Code:</strong> <?php echo htmlspecialchars($company_details->state_code); ?></p>
                                <p><strong>HSN/SAC Code:</strong> <?php echo htmlspecialchars($company_details->hsn_sac_code); ?></p>
                            <?php else: ?>
                                <p>Company details not configured. Please update in Company Profile.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="customer-details">
                            <h4>Customer Details</h4>
                            <div class="form-group">
                                <label for="customer_select">Select Customer:</label>
                                <select class="form-control" id="customer_select" name="customer_id">
                                    <option value="">-- Select Existing Customer --</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer->id; ?>"
                                                data-address="<?php echo htmlspecialchars($customer->address); ?>"
                                                data-mobile="<?php echo htmlspecialchars($customer->mobile_no); ?>"
                                                data-gst="<?php echo htmlspecialchars($customer->gst_no); ?>">
                                            <?php echo htmlspecialchars($customer->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="customer_name">Customer Name:</label>
                                <input type="text" class="form-control" id="customer_name" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="customer_address">Address:</label>
                                <input type="text" class="form-control" id="customer_address" name="customer_address">
                            </div>
                            <div class="form-group">
                                <label for="customer_mobile">Mobile No.:</label>
                                <input type="text" class="form-control" id="customer_mobile" name="customer_mobile">
                            </div>
                            <div class="form-group">
                                <label for="customer_gst">GST No.:</label>
                                <input type="text" class="form-control" id="customer_gst" name="customer_gst">
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <!-- Invoice Data -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="invoice_no">Invoice No.:</label>
                            <input type="text" class="form-control" id="invoice_no" name="invoice_no" value="<?php echo 'INV-' . time(); ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="sale_date">Date:</label>
                            <input type="date" class="form-control" id="sale_date" name="sale_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="eway_bill_no">E-Way Bill No.:</label>
                            <input type="text" class="form-control" id="eway_bill_no" name="eway_bill_no">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="advance_amount">Advance Amount:</label>
                            <input type="number" step="0.01" class="form-control" id="advance_amount" name="advance_amount" value="0.00">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="payment_status">Payment Status:</label>
                            <select class="form-control" id="payment_status" name="payment_status">
                                <option value="due">Due</option>
                                <option value="received">Received</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="payment_mode">Payment Mode:</label>
                            <select class="form-control" id="payment_mode" name="payment_mode">
                                <option value="cash">Cash</option>
                                <option value="upi">UPI</option>
                                <option value="netbanking">Netbanking</option>
                                <option value="debit_card">Debit Card</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group" id="other_payment_mode_details_group" style="display: none;">
                            <label for="other_payment_mode_details">Other Payment Mode Details:</label>
                            <input type="text" class="form-control" id="other_payment_mode_details" name="other_payment_mode_details">
                        </div>
                    </div>
                </div>

                <hr>

                <!-- Item Details -->
                <h4>Item Details</h4>
                <div id="item_details_container">
                    <div class="item-row row mb-3" data-item-id="1">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Product Name</label>
                                <select class="form-control product-name" name="items[1][product_id]" required>
                                    <option value="">-- Select Product --</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product->id; ?>"
                                                data-category="<?php echo htmlspecialchars($product->category); ?>"
                                                data-unit="<?php echo htmlspecialchars($product->unit); ?>"
                                                data-price="<?php echo htmlspecialchars($product->price); ?>">
                                            <?php echo htmlspecialchars($product->product_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>Price</label>
                                <input type="number" step="0.01" class="form-control product-price" name="items[1][product_price]" value="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>Category</label>
                                <input type="text" class="form-control product-category" name="items[1][product_category]">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>Unit</label>
                                <input type="text" class="form-control product-unit" name="items[1][unit]">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>Quantity</label>
                                <input type="number" step="1" class="form-control quantity" name="items[1][quantity]" value="1" required>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>CGST (%)</label>
                                <input type="number" step="0.01" class="form-control cgst" name="items[1][cgst]" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>SGST (%)</label>
                                <input type="number" step="0.01" class="form-control sgst" name="items[1][sgst]" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>Discount (%)</label>
                                <input type="number" step="0.01" class="form-control discount" name="items[1][discount]" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Total Amount</label>
                                <input type="text" class="form-control item-total-amount" name="items[1][total_amount]" value="0.00" readonly>
                            </div>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-danger remove-item-btn">Remove</button>
                        </div>
                </div>
                <button type="button" class="btn btn-success mb-3" id="add_new_item_btn">Add New Item</button>

                <hr>

                <!-- Total Summary -->
                <h4>Total Summary</h4>
                <div class="row">
                    <div class="col-md-6 offset-md-6">
                        <table class="table table-bordered total-summary-table">
                            <tbody>
                                <tr>
                                    <th>Net Amount:</th>
                                    <td><input type="text" class="form-control" id="net_amount" name="net_amount" value="0.00" readonly></td>
                                </tr>
                                <tr>
                                    <th>Total Discount:</th>
                                    <td><input type="text" class="form-control" id="total_discount" name="total_discount" value="0.00" readonly></td>
                                </tr>
                                <tr>
                                    <th>Total CGST:</th>
                                    <td><input type="text" class="form-control" id="total_cgst" name="total_cgst" value="0.00" readonly></td>
                                </tr>
                                <tr>
                                    <th>Total SGST:</th>
                                    <td><input type="text" class="form-control" id="total_sgst" name="total_sgst" value="0.00" readonly></td>
                                </tr>
                                <tr>
                                    <th>Advance Amount:</th>
                                    <td><input type="text" class="form-control" id="summary_advance_amount" name="summary_advance_amount" value="0.00" readonly></td>
                                </tr>
                                <tr>
                                    <th>Total Amount:</th>
                                    <td><input type="text" class="form-control" id="total_amount" name="total_amount" value="0.00" readonly></td>
                                </tr>
                                <tr>
                                    <th>Pending Amount:</th>
                                    <td><input type="text" class="form-control" id="pending_amount" name="pending_amount" value="0.00" readonly></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <hr>

                <button type="submit" class="btn btn-primary btn-lg btn-block">Add Sales</button>
            </form>

            <hr class="my-5">

            <h2>Sales List</h2>
            <div class="row mb-3">
                <div class="col-md-6">
                    <form action="" method="GET" class="form-inline">
                        <div class="form-group mr-2">
                            <input type="text" class="form-control" name="search" placeholder="Search by Invoice No. or Customer Name" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                </div>
                <div class="col-md-6 text-right">
                    <a href="export_sales.php" class="btn btn-success">Export to Excel</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Invoice No.</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Total Amount</th>
                            <th>Payment Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $search_query = $_GET['search'] ?? '';
                        $sql = "SELECT s.id, s.invoice_no, c.name as customer_name, s.sale_date, s.total_amount, s.payment_status FROM sales s JOIN customers c ON s.customer_id = c.id";
                        if (!empty($search_query)) {
                            $sql .= " WHERE s.invoice_no LIKE :search OR c.name LIKE :search";
                        }
                        $sql .= " ORDER BY s.id DESC";
                        $database->query($sql);
                        if (!empty($search_query)) {
                            $database->bind(':search', '%' . $search_query . '%');
                        }
                        $sales = $database->resultSet();

                        if ($sales):
                            foreach ($sales as $sale):
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sale->invoice_no); ?></td>
                                <td><?php echo htmlspecialchars($sale->customer_name); ?></td>
                                <td><?php echo htmlspecialchars($sale->sale_date); ?></td>
                                <td><?php echo htmlspecialchars($sale->total_amount); ?></td>
                                <td><?php echo htmlspecialchars($sale->payment_status); ?></td>
                                <td>
                                    <a href="view_invoice.php?id=<?php echo $sale->id; ?>" class="btn btn-info btn-sm">View</a>
                                    <a href="edit_sale.php?id=<?php echo $sale->id; ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <a href="delete_sale.php?id=<?php echo $sale->id; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this sale?');">Delete</a>
                                </td>
                            </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                            <tr>
                                <td colspan="6" class="text-center">No sales found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
            let itemCounter = 1;

            // Function to calculate item total
            function calculateItemTotal(itemRow) {
                const quantity = parseFloat(itemRow.find('.quantity').val()) || 0;
                const price = parseFloat(itemRow.find('.product-price').val()) || 0; // Assuming a price input will be added
                const cgstRate = parseFloat(itemRow.find('.cgst').val()) || 0;
                const sgstRate = parseFloat(itemRow.find('.sgst').val()) || 0;
                const discountRate = parseFloat(itemRow.find('.discount').val()) || 0;

                let itemTotal = quantity * price;
                let discountAmount = itemTotal * (discountRate / 100);
                itemTotal -= discountAmount;

                let cgstAmount = itemTotal * (cgstRate / 100);
                let sgstAmount = itemTotal * (sgstRate / 100);

                itemTotal += cgstAmount + sgstAmount;
                itemRow.find('.item-total-amount').val(itemTotal.toFixed(2));
                calculateOverallTotals();
            }

            // Function to calculate overall totals
            function calculateOverallTotals() {
                let netAmount = 0;
                let totalDiscount = 0;
                let totalCgst = 0;
                let totalSgst = 0;
                let totalAmount = 0;

                $('.item-row').each(function() {
                    const quantity = parseFloat($(this).find('.quantity').val()) || 0;
                    const price = parseFloat($(this).find('.product-price').val()) || 0; // Assuming a price input will be added
                    const cgstRate = parseFloat($(this).find('.cgst').val()) || 0;
                    const sgstRate = parseFloat($(this).find('.sgst').val()) || 0;
                    const discountRate = parseFloat($(this).find('.discount').val()) || 0;

                    let itemBaseTotal = quantity * price;
                    let itemDiscountAmount = itemBaseTotal * (discountRate / 100);
                    let itemNet = itemBaseTotal - itemDiscountAmount;

                    let itemCgstAmount = itemNet * (cgstRate / 100);
                    let itemSgstAmount = itemNet * (sgstRate / 100);

                    netAmount += itemNet;
                    totalDiscount += itemDiscountAmount;
                    totalCgst += itemCgstAmount;
                    totalSgst += itemSgstAmount;
                    totalAmount += (itemNet + itemCgstAmount + itemSgstAmount);
                });

                const advanceAmount = parseFloat($('#advance_amount').val()) || 0;
                const pendingAmount = totalAmount - advanceAmount;

                $('#net_amount').val(netAmount.toFixed(2));
                $('#total_discount').val(totalDiscount.toFixed(2));
                $('#total_cgst').val(totalCgst.toFixed(2));
                $('#total_sgst').val(totalSgst.toFixed(2));
                $('#summary_advance_amount').val(advanceAmount.toFixed(2));
                $('#total_amount').val(totalAmount.toFixed(2));
                $('#pending_amount').val(pendingAmount.toFixed(2));
            }

            // Add New Item button click
            $('#add_new_item_btn').on('click', function() {
                itemCounter++;
                const newItemRow = `
                    <div class="item-row row mb-3" data-item-id="${itemCounter}">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Product Name</label>
                                <select class="form-control product-name" name="items[${itemCounter}][product_id]" required>
                                    <option value="">-- Select Product --</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product->id; ?>"
                                                data-category="<?php echo htmlspecialchars($product->category); ?>"
                                                data-unit="<?php echo htmlspecialchars($product->unit); ?>"
                                                data-price="<?php echo htmlspecialchars($product->price); ?>">
                                            <?php echo htmlspecialchars($product->product_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>Price</label>
                                <input type="number" step="0.01" class="form-control product-price" name="items[${itemCounter}][product_price]" value="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>Category</label>
                                <input type="text" class="form-control product-category" name="items[${itemCounter}][product_category]">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>Unit</label>
                                <input type="text" class="form-control product-unit" name="items[${itemCounter}][unit]">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>Quantity</label>
                                <input type="number" step="1" class="form-control quantity" name="items[${itemCounter}][quantity]" value="1" required>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>CGST (%)</label>
                                <input type="number" step="0.01" class="form-control cgst" name="items[${itemCounter}][cgst]" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>SGST (%)</label>
                                <input type="number" step="0.01" class="form-control sgst" name="items[${itemCounter}][sgst]" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>Discount (%)</label>
                                <input type="number" step="0.01" class="form-control discount" name="items[${itemCounter}][discount]" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Total Amount</label>
                                <input type="text" class="form-control item-total-amount" name="items[${itemCounter}][total_amount]" value="0.00" readonly>
                            </div>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-danger remove-item-btn">Remove</button>
                        </div>
                    </div>
                `;
                $('#item_details_container').append(newItemRow);
            });

            // Remove Item button click
            $(document).on('click', '.remove-item-btn', function() {
                if ($('.item-row').length > 1) {
                    $(this).closest('.item-row').remove();
                    calculateOverallTotals();
                } else {
                    alert("You must have at least one item.");
                }
            });

            // Customer dropdown change event
            $('#customer_select').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                if (selectedOption.val() !== '') {
                    $('#customer_name').val(selectedOption.text().trim());
                    $('#customer_address').val(selectedOption.data('address'));
                    $('#customer_mobile').val(selectedOption.data('mobile'));
                    $('#customer_gst').val(selectedOption.data('gst'));
                } else {
                    $('#customer_name').val('');
                    $('#customer_address').val('');
                    $('#customer_mobile').val('');
                    $('#customer_gst').val('');
                }
            });

            // Payment mode change event
            $('#payment_mode').on('change', function() {
                if ($(this).val() === 'other') {
                    $('#other_payment_mode_details_group').show();
                } else {
                    $('#other_payment_mode_details_group').hide();
                    $('#other_payment_mode_details').val('');
                }
            });

            // Recalculate totals on input change
            $(document).on('input', '.quantity, .product-price, .cgst, .sgst, .discount, #advance_amount', function() {
                const itemRow = $(this).closest('.item-row');
                calculateItemTotal(itemRow);
            });

            // Product dropdown change event
            $(document).on('change', '.product-name', function() {
                const selectedOption = $(this).find('option:selected');
                const itemRow = $(this).closest('.item-row');
                if (selectedOption.val() !== '') {
                    itemRow.find('.product-category').val(selectedOption.data('category'));
                    itemRow.find('.product-unit').val(selectedOption.data('unit'));
                    itemRow.find('.product-price').val(selectedOption.data('price'));
                } else {
                    itemRow.find('.product-category').val('');
                    itemRow.find('.product-unit').val('');
                    itemRow.find('.product-price').val('0.00');
                }
                calculateItemTotal(itemRow);
            });

            // Initial calculation
            calculateOverallTotals();
        });
    </script>
</body>
</html>