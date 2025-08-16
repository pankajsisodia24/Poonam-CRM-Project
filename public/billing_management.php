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
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Handle Delete Operation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_bill'])) {
    $bill_id = $_POST['bill_id'];

    $database->beginTransaction();
    try {
        // Get bill items to restore stock
        $database->query("SELECT product_id, quantity FROM bill_items WHERE bill_id = :bill_id");
        $database->bind(':bill_id', $bill_id);
        $bill_items = $database->resultSet();

        foreach ($bill_items as $item) {
            $database->query('UPDATE products SET available_stock = available_stock + :quantity WHERE id = :id');
            $database->bind(':quantity', $item->quantity);
            $database->bind(':id', $item->product_id);
            $database->execute();
        }

        // Delete bill items
        $database->query("DELETE FROM bill_items WHERE bill_id = :bill_id");
        $database->bind(':bill_id', $bill_id);
        $database->execute();

        // Delete bill
        $database->query("DELETE FROM bills WHERE id = :id");
        $database->bind(':id', $bill_id);
        $database->execute();

        $database->commit();
        $_SESSION['message'] = "Bill deleted successfully!";
    } catch (Exception $e) {
        $database->rollBack();
        $_SESSION['error'] = "Error deleting bill: " . $e->getMessage();
    }

    header("location: billing_management.php");
    exit;
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

// Search and filter parameters
$search_customer_name = $_GET['search_customer_name'] ?? '';
$search_invoice_no = $_GET['search_invoice_no'] ?? '';

$where_clauses = [];
$bind_params = [];

if (!empty($search_customer_name)) {
    $where_clauses[] = "c.customer_name LIKE :search_customer_name";
    $bind_params[':search_customer_name'] = '%' . $search_customer_name . '%';
}
if (!empty($search_invoice_no)) {
    $where_clauses[] = "b.invoice_no LIKE :search_invoice_no";
    $bind_params[':search_invoice_no'] = '%' . $search_invoice_no . '%';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Pagination settings
$bills_per_page = 5;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $bills_per_page;

// Get total number of bills for pagination
$database->query("SELECT COUNT(*) FROM bills b JOIN customers c ON b.customer_id = c.id" . $where_sql);
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$total_bills = $database->single()->{'COUNT(*)'};
$total_pages = ceil($total_bills / $bills_per_page);

// Fetch bills
$database->query("SELECT b.*, c.customer_name FROM bills b JOIN customers c ON b.customer_id = c.id" . $where_sql . " ORDER BY b.invoice_date DESC LIMIT :offset, :bills_per_page");
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$database->bind(':offset', $offset, PDO::PARAM_INT);
$database->bind(':bills_per_page', $bills_per_page, PDO::PARAM_INT);
$bills = $database->resultSet();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Management - CRM App</title>
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
        .table-container .form-inline .form-control {
            background-color: rgba(0, 0, 0, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
        }
        .table-container .form-inline .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
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
            background-color: white;
            color: black;
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
        <a href="index.php?page=user_profile">User Profile</a>
        <a href="index.php?page=logout">Logout</a>
    </nav>

    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="content">
            <h1>Billing Management</h1>

            <?php if (!empty($message)): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="table-container">
                <h2>Sales Summary</h2>

                <!-- Search Bar -->
                <form class="form-inline mb-4" method="GET" action="billing_management.php">
                    <input type="text" class="form-control mr-sm-2" name="search_customer_name" placeholder="Search by Customer Name" value="<?php echo htmlspecialchars($search_customer_name); ?>">
                    <input type="text" class="form-control mr-sm-2" name="search_invoice_no" placeholder="Search by Invoice No" value="<?php echo htmlspecialchars($search_invoice_no); ?>">
                    <button class="btn btn-primary my-2 my-sm-0" type="submit">Search</button>
                    <a href="export_billing_excel.php" class="btn btn-success ml-2">Download Excel</a>
                </form>

                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Invoice No</th>
                            <th>Customer Name</th>
                            <th>Invoice Date</th>
                            <th>Total Amount</th>
                            <th>Payment Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($bills)): ?>
                            <?php foreach ($bills as $bill): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($bill->invoice_no); ?></td>
                                    <td><?php echo htmlspecialchars($bill->customer_name); ?></td>
                                    <td><?php echo htmlspecialchars($bill->invoice_date); ?></td>
                                    <td><?php echo htmlspecialchars($bill->net_amount - $bill->total_discount + $bill->total_cgst + $bill->total_sgst); ?></td>
                                    <td><?php echo htmlspecialchars($bill->payment_status); ?></td>
                                    <td>
                                        <a href="view_bill.php?id=<?php echo $bill->id; ?>" class="btn btn-sm btn-info">View</a>
                                        <a href="edit_bill.php?id=<?php echo $bill->id; ?>" class="btn btn-sm btn-primary">Edit</a>
                                        <form method="POST" action="billing_management.php" style="display:inline-block;">
                                            <input type="hidden" name="bill_id" value="<?php echo $bill->id; ?>">
                                            <button type="submit" name="delete_bill" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this bill?');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">No bills found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <nav aria-label="Billing Pagination" class="pagination-container">
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="billing_management.php?page=<?php echo $i; ?>&search_customer_name=<?php echo urlencode($search_customer_name); ?>&search_invoice_no=<?php echo urlencode($search_invoice_no); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>

                <a href="add_billing.php" class="btn btn-primary mt-3">Add Billing</a>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> CRM App. All rights reserved.</p>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>