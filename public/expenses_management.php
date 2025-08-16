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

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_expense'])) {
        $invoice_no = trim($_POST['invoice_no']);
        $invoice_date = $_POST['invoice_date'];
        $payment_mode = $_POST['payment_mode'];
        $payment_status = $_POST['payment_status'];
        $pay_to = trim($_POST['pay_to']);
        $total_amount = 0; // Will be calculated from items

        // Insert expense
        try {
            $database->query('INSERT INTO expenses (invoice_no, invoice_date, total_amount, payment_mode, payment_status, pay_to) VALUES (:invoice_no, :invoice_date, :total_amount, :payment_mode, :payment_status, :pay_to)');
            $database->bind(':invoice_no', $invoice_no);
            $database->bind(':invoice_date', $invoice_date);
            $database->bind(':total_amount', $total_amount); // Placeholder, will update later
            $database->bind(':payment_mode', $payment_mode);
            $database->bind(':payment_status', $payment_status);
            $database->bind(':pay_to', $pay_to);

            if ($database->execute()) {
                $expense_id = $database->lastInsertId();
                $total_amount_calculated = 0;

                // Insert expense items
                foreach ($_POST['expense_name'] as $key => $expense_name) {
                    $expense_category = $_POST['expense_category'][$key];
                    $amount = $_POST['amount'][$key];

                    $total_amount_calculated += $amount;

                    $database->query('INSERT INTO expense_items (expense_id, expense_name, expense_category, amount) VALUES (:expense_id, :expense_name, :expense_category, :amount)');
                    $database->bind(':expense_id', $expense_id);
                    $database->bind(':expense_name', $expense_name);
                    $database->bind(':expense_category', $expense_category);
                    $database->bind(':amount', $amount);
                    $database->execute();
                }

                // Update total_amount in expenses table
                $database->query('UPDATE expenses SET total_amount = :total_amount WHERE id = :expense_id');
                $database->bind(':total_amount', $total_amount_calculated);
                $database->bind(':expense_id', $expense_id);
                $database->execute();

                $message = "Expense added successfully!";
            } else {
                $message = "Error adding expense.";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'invoice_no') !== false) {
                $message = "Error: Duplicate Invoice Number. Please use a unique invoice number.";
            } else {
                $message = "An unexpected database error occurred: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['edit_expense'])) {
        $id = $_POST['expense_id'];
        $invoice_no = trim($_POST['invoice_no']);
        $invoice_date = $_POST['invoice_date'];
        $payment_mode = $_POST['payment_mode'];
        $payment_status = $_POST['payment_status'];
        $pay_to = trim($_POST['pay_to']);
        $total_amount = 0; // Will be recalculated from items

        try {
            // Update expense main record
            $database->query('UPDATE expenses SET invoice_no = :invoice_no, invoice_date = :invoice_date, payment_mode = :payment_mode, payment_status = :payment_status, pay_to = :pay_to WHERE id = :id');
            $database->bind(':invoice_no', $invoice_no);
            $database->bind(':invoice_date', $invoice_date);
            $database->bind(':payment_mode', $payment_mode);
            $database->bind(':payment_status', $payment_status);
            $database->bind(':pay_to', $pay_to);
            $database->bind(':id', $id);
            $database->execute();

            // Delete existing expense items
            $database->query('DELETE FROM expense_items WHERE expense_id = :expense_id');
            $database->bind(':expense_id', $id);
            $database->execute();

            $total_amount_calculated = 0;
            // Insert updated expense items
            foreach ($_POST['expense_name'] as $key => $expense_name) {
                $expense_category = $_POST['expense_category'][$key];
                $amount = $_POST['amount'][$key];

                $total_amount_calculated += $amount;

                $database->query('INSERT INTO expense_items (expense_id, expense_name, expense_category, amount) VALUES (:expense_id, :expense_name, :expense_category, :amount)');
                $database->bind(':expense_id', $id);
                $database->bind(':expense_name', $expense_name);
                $database->bind(':expense_category', $expense_category);
                $database->bind(':amount', $amount);
                $database->execute();
            }

            // Update total_amount in expenses table
            $database->query('UPDATE expenses SET total_amount = :total_amount WHERE id = :expense_id');
            $database->bind(':total_amount', $total_amount_calculated);
            $database->bind(':expense_id', $id);
            $database->execute();

            $message = "Expense updated successfully!";
        } catch (PDOException $e) {
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'invoice_no') !== false) {
                $message = "Error: Duplicate Invoice Number. Please use a unique invoice number.";
            } else {
                $message = "An unexpected database error occurred: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_expense'])) {
        $id = $_POST['expense_id'];
        
        // Delete expense items first
        $database->query('DELETE FROM expense_items WHERE expense_id = :id');
        $database->bind(':id', $id);
        $database->execute();

        // Then delete expense
        $database->query('DELETE FROM expenses WHERE id = :id');
        $database->bind(':id', $id);
        if ($database->execute()) {
            $message = "Expense deleted successfully!";
        } else {
            $message = "Error deleting expense.";
        }
    }
}

// Search and filter parameters
$search_invoice_no = $_GET['search_invoice_no'] ?? '';
$search_pay_to = $_GET['search_pay_to'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_clauses = [];
$bind_params = [];

if (!empty($search_invoice_no)) {
    $where_clauses[] = "invoice_no LIKE :search_invoice_no";
    $bind_params[':search_invoice_no'] = '%' . $search_invoice_no . '%';
}
if (!empty($search_pay_to)) {
    $where_clauses[] = "pay_to LIKE :search_pay_to";
    $bind_params[':search_pay_to'] = '%' . $search_pay_to . '%';
}
if (!empty($start_date)) {
    $where_clauses[] = "invoice_date >= :start_date";
    $bind_params[':start_date'] = $start_date;
}
if (!empty($end_date)) {
    $where_clauses[] = "invoice_date <= :end_date";
    $bind_params[':end_date'] = $end_date;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Pagination settings
$expenses_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $expenses_per_page;

// Get total number of expenses for pagination
$database->query("SELECT COUNT(*) FROM expenses" . $where_sql);
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$total_expenses = $database->single()->{'COUNT(*)'};
$total_pages = ceil($total_expenses / $expenses_per_page);

// Fetch expenses
$database->query("SELECT * FROM expenses" . $where_sql . " ORDER BY invoice_date DESC LIMIT :offset, :expenses_per_page");
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$database->bind(':offset', $offset, PDO::PARAM_INT);
$database->bind(':expenses_per_page', $expenses_per_page, PDO::PARAM_INT);
$expenses = $database->resultSet();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses Management - CRM App</title>
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
            <h1>Expenses Management</h1>

            <?php if (!empty($message)): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="table-container">
                <h2>Expense List</h2>

                <!-- Search Bar -->
                <form class="form-inline mb-4" method="GET" action="expenses_management.php">
                    <input type="text" class="form-control mr-sm-2" name="search_invoice_no" placeholder="Search by Invoice No." value="<?php echo htmlspecialchars($search_invoice_no); ?>">
                    <input type="text" class="form-control mr-sm-2" name="search_pay_to" placeholder="Search by Pay To" value="<?php echo htmlspecialchars($search_pay_to); ?>">
                    <input type="date" class="form-control mr-sm-2" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    <input type="date" class="form-control mr-sm-2" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    <button class="btn btn-primary my-2 my-sm-0" type="submit">Search</button>
                    <a href="export_expenses_excel.php?search_invoice_no=<?php echo urlencode($search_invoice_no); ?>&search_pay_to=<?php echo urlencode($search_pay_to); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-success ml-2">Download Excel</a>
                    <button type="button" class="btn btn-info ml-2" data-toggle="modal" data-target="#addExpenseModal">Add New Expense</button>
                </form>

                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Invoice No.</th>
                            <th>Invoice Date</th>
                            <th>Total Amount</th>
                            <th>Payment Mode</th>
                            <th>Payment Status</th>
                            <th>Pay To</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($expenses)): ?>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($expense->id); ?></td>
                                    <td><?php echo htmlspecialchars($expense->invoice_no); ?></td>
                                    <td><?php echo htmlspecialchars($expense->invoice_date); ?></td>
                                    <td>₹<?php echo htmlspecialchars(number_format($expense->total_amount, 2)); ?></td>
                                    <td><?php echo htmlspecialchars($expense->payment_mode); ?></td>
                                    <td><?php echo htmlspecialchars($expense->payment_status); ?></td>
                                    <td><?php echo htmlspecialchars($expense->pay_to); ?></td>
                                    <td><?php echo htmlspecialchars($expense->created_at); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info edit-expense-btn" 
                                                data-id="<?php echo $expense->id; ?>" 
                                                data-invoice-no="<?php echo htmlspecialchars($expense->invoice_no); ?>" 
                                                data-invoice-date="<?php echo htmlspecialchars($expense->invoice_date); ?>" 
                                                data-payment-mode="<?php echo htmlspecialchars($expense->payment_mode); ?>" 
                                                data-payment-status="<?php echo htmlspecialchars($expense->payment_status); ?>" 
                                                data-pay-to="<?php echo htmlspecialchars($expense->pay_to); ?>"
                                                data-toggle="modal" data-target="#editExpenseModal">Edit</button>
                                        <form method="POST" action="expenses_management.php" style="display:inline-block;">
                                            <input type="hidden" name="expense_id" value="<?php echo $expense->id; ?>">
                                            <button type="submit" name="delete_expense" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this expense?');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">No expenses found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <nav aria-label="Expense Pagination" class="pagination-container">
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="expenses_management.php?page=<?php echo $i; ?>&search_invoice_no=<?php echo urlencode($search_invoice_no); ?>&search_pay_to=<?php echo urlencode($search_pay_to); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>

            <!-- Add Expense Modal -->
            <div class="modal fade" id="addExpenseModal" tabindex="-1" role="dialog" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addExpenseModalLabel">Add New Expense</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form method="POST" action="expenses_management.php">
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for="invoice_no">Invoice No.</label>
                                    <input type="text" class="form-control" id="invoice_no" name="invoice_no" required>
                                </div>
                                <div class="form-group">
                                    <label for="invoice_date">Invoice Date</label>
                                    <input type="date" class="form-control" id="invoice_date" name="invoice_date" required>
                                </div>
                                <div class="form-group">
                                    <label for="payment_mode">Payment Mode</label>
                                    <select class="form-control" id="payment_mode" name="payment_mode" required>
                                        <option value="">Select Payment Mode</option>
                                        <option value="Cash">Cash</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                        <option value="Credit Card">Credit Card</option>
                                        <option value="UPI">UPI</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="payment_status">Payment Status</label>
                                    <select class="form-control" id="payment_status" name="payment_status" required>
                                        <option value="Pending">Pending</option>
                                        <option value="Paid">Paid</option>
                                        <option value="Partial">Partial</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="pay_to">Pay To</label>
                                    <input type="text" class="form-control" id="pay_to" name="pay_to" required>
                                </div>

                                <hr>
                                <h4>Expense Items</h4>
                                <div id="expense-items-container">
                                    <!-- Expense items will be added here by JavaScript -->
                                    <div class="item-row">
                                        <div class="form-group">
                                            <label>Expense Name</label>
                                            <input type="text" class="form-control" name="expense_name[]" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Expense Category</label>
                                            <input type="text" class="form-control" name="expense_category[]">
                                        </div>
                                        <div class="form-group">
                                            <label>Amount</label>
                                            <input type="number" step="0.01" class="form-control expense-amount" name="amount[]" min="0" required>
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
                                <button type="submit" name="add_expense" class="btn btn-primary">Submit Expense</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Expense Modal -->
            <div class="modal fade" id="editExpenseModal" tabindex="-1" role="dialog" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editExpenseModalLabel">Edit Expense</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form method="POST" action="expenses_management.php">
                            <div class="modal-body">
                                <input type="hidden" id="edit_expense_id" name="expense_id">
                                <div class="form-group">
                                    <label for="edit_invoice_no">Invoice No.</label>
                                    <input type="text" class="form-control" id="edit_invoice_no" name="invoice_no" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_invoice_date">Invoice Date</label>
                                    <input type="date" class="form-control" id="edit_invoice_date" name="invoice_date" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_payment_mode">Payment Mode</label>
                                    <select class="form-control" id="edit_payment_mode" name="payment_mode" required>
                                        <option value="">Select Payment Mode</option>
                                        <option value="Cash">Cash</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                        <option value="Credit Card">Credit Card</option>
                                        <option value="UPI">UPI</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="edit_payment_status">Payment Status</label>
                                    <select class="form-control" id="edit_payment_status" name="payment_status" required>
                                        <option value="Pending">Pending</option>
                                        <option value="Paid">Paid</option>
                                        <option value="Partial">Partial</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="edit_pay_to">Pay To</label>
                                    <input type="text" class="form-control" id="edit_pay_to" name="pay_to" required>
                                </div>

                                <hr>
                                <h4>Expense Items</h4>
                                <div id="edit-expense-items-container">
                                    <!-- Existing expense items will be loaded here by JavaScript -->
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
                                <button type="submit" name="edit_expense" class="btn btn-primary">Save Changes</button>
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

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Function to calculate total amount for add expense modal
            function calculateTotalAmountAdd() {
                var totalAmount = 0;
                $('#expense-items-container .item-row').each(function() {
                    totalAmount += parseFloat($(this).find('.expense-amount').val()) || 0;
                });
                $('#total_amount_display').val(totalAmount.toFixed(2));
                $('#total_amount_hidden').val(totalAmount.toFixed(2));
            }

            // Function to calculate total amount for edit expense modal
            function calculateTotalAmountEdit() {
                var totalAmount = 0;
                $('#edit-expense-items-container .item-row').each(function() {
                    totalAmount += parseFloat($(this).find('.expense-amount').val()) || 0;
                });
                $('#edit_total_amount_display').val(totalAmount.toFixed(2));
                $('#edit_total_amount_hidden').val(totalAmount.toFixed(2));
            }

            // Add More Item button click for Add Expense Modal
            $('#add-more-item').on('click', function() {
                var newItemRow = $('.item-row').first().clone();
                newItemRow.find('input').val(''); // Clear values
                newItemRow.find('.remove-item-btn').show(); // Show remove button for new items
                $('#expense-items-container').append(newItemRow);
                calculateTotalAmountAdd();
            });

            // Remove Item button click for Add Expense Modal
            $('#expense-items-container').on('click', '.remove-item-btn', function() {
                if ($('#expense-items-container .item-row').length > 1) {
                    $(this).closest('.item-row').remove();
                    calculateTotalAmountAdd();
                } else {
                    alert("You must have at least one expense item.");
                }
            });

            // Event listener for amount changes in Add Expense Modal
            $('#expense-items-container').on('input', '.expense-amount', function() {
                calculateTotalAmountAdd();
            });

            // Initial hide remove button if only one item row in Add Expense Modal
            if ($('#expense-items-container .item-row').length === 1) {
                $('#expense-items-container .remove-item-btn').hide();
            }

            // Edit Expense Modal - Populate data and handle dynamic items
            $('.edit-expense-btn').on('click', function() {
                var expenseId = $(this).data('id');
                var invoiceNo = $(this).data('invoice-no');
                var invoiceDate = $(this).data('invoice-date');
                var paymentMode = $(this).data('payment-mode');
                var paymentStatus = $(this).data('payment-status');
                var payTo = $(this).data('pay-to');

                $('#edit_expense_id').val(expenseId);
                $('#edit_invoice_no').val(invoiceNo);
                $('#edit_invoice_date').val(invoiceDate);
                $('#edit_payment_mode').val(paymentMode);
                $('#edit_payment_status').val(paymentStatus);
                $('#edit_pay_to').val(payTo);

                // Fetch expense items for editing
                $.ajax({
                    url: 'fetch_expense_items.php', // Create this file to fetch items
                    type: 'GET',
                    data: { expense_id: expenseId },
                    success: function(response) {
                        var items = JSON.parse(response);
                        $('#edit-expense-items-container').empty();
                        if (items.length > 0) {
                            items.forEach(function(item) {
                                var itemHtml = 
                                    '<div class="item-row">' +
                                        '<div class="form-group">' +
                                            '<label>Expense Name</label>' +
                                            '<input type="text" class="form-control" name="expense_name[]" value="' + item.expense_name + '" required>' +
                                        '</div>' +
                                        '<div class="form-group">' +
                                            '<label>Expense Category</label>' +
                                            '<input type="text" class="form-control" name="expense_category[]" value="' + item.expense_category + '">' +
                                        '</div>' +
                                        '<div class="form-group">' +
                                            '<label>Amount</label>' +
                                            '<input type="number" step="0.01" class="form-control expense-amount" name="amount[]" value="' + item.amount + '" min="0" required>' +
                                        '</div>' +
                                        '<button type="button" class="btn btn-danger remove-item-btn">Remove</button>' +
                                    '</div>';
                                $('#edit-expense-items-container').append(itemHtml);
                            });
                        } else {
                            // Add a default empty item row if no items are found
                            var defaultItemHtml = 
                                '<div class="item-row">' +
                                    '<div class="form-group">' +
                                        '<label>Expense Name</label>' +
                                        '<input type="text" class="form-control" name="expense_name[]" required>' +
                                    '</div>' +
                                    '<div class="form-group">' +
                                        '<label>Expense Category</label>' +
                                        '<input type="text" class="form-control" name="expense_category[]">
                                    </div>' +
                                    '<div class="form-group">' +
                                        '<label>Amount</label>' +
                                        '<input type="number" step="0.01" class="form-control expense-amount" name="amount[]" min="0" required>' +
                                    '</div>' +
                                    '<button type="button" class="btn btn-danger remove-item-btn">Remove</button>' +
                                '</div>';
                            $('#edit-expense-items-container').append(defaultItemHtml);
                        }
                        // Hide remove button if only one item row after loading
                        if ($('#edit-expense-items-container .item-row').length === 1) {
                            $('#edit-expense-items-container .remove-item-btn').hide();
                        } else {
                            $('#edit-expense-items-container .remove-item-btn').show();
                        }
                        calculateTotalAmountEdit();
                    }
                });
            });

            // Add More Item button click for Edit Expense Modal
            $('#edit-add-more-item').on('click', function() {
                var newItemRow = $('.item-row').first().clone();
                newItemRow.find('input').val(''); // Clear values
                newItemRow.find('.remove-item-btn').show(); // Show remove button for new items
                $('#edit-expense-items-container').append(newItemRow);
                calculateTotalAmountEdit();
            });

            // Remove Item button click for Edit Expense Modal
            $('#edit-expense-items-container').on('click', '.remove-item-btn', function() {
                if ($('#edit-expense-items-container .item-row').length > 1) {
                    $(this).closest('.item-row').remove();
                    calculateTotalAmountEdit();
                } else {
                    alert("You must have at least one expense item.");
                }
            });

            // Event listener for amount changes in Edit Expense Modal
            $('#edit-expense-items-container').on('input', '.expense-amount', function() {
                calculateTotalAmountEdit();
            });
        });
    </script>
</body>
</html>