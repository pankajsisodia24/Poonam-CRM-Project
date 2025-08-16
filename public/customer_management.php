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
$company_customer_name_header = "Your Company Name"; // Default
$company_logo_header = ""; // Default

$database->query('SELECT company_name, company_logo FROM company_profile LIMIT 1');
$header_company_profile = $database->single();

if($header_company_profile) {
    $company_customer_name_header = htmlspecialchars($header_company_profile->company_name);
    $company_logo_header = htmlspecialchars($header_company_profile->company_logo);
}

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_customer'])) {
        $customer_name = trim($_POST['customer_name']);
        $email = trim($_POST['email']);
        $mobile_no = trim($_POST['mobile_no']);
        $gst_no = trim($_POST['gst_no']);

        if (empty($customer_name) || empty($email) || empty($mobile_no)) {
            $message = "Name, Email, and Mobile No. are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
        } else {
            // Check if email already exists
            $database->query('SELECT id FROM customers WHERE email = :email');
            $database->bind(':email', $email);
            $database->execute();
            if ($database->rowCount() > 0) {
                $message = "Customer with this email already exists.";
            } else {
                $database->query('INSERT INTO customers (customer_name, email, mobile_no, gst_no) VALUES (:customer_name, :email, :mobile_no, :gst_no)');
                $database->bind(':customer_name', $customer_name);
                $database->bind(':email', $email);
                $database->bind(':mobile_no', $mobile_no);
                $database->bind(':gst_no', $gst_no);

                if ($database->execute()) {
                    $message = "Customer added successfully!";
                } else {
                    $message = "Error adding customer.";
                }
            }
        }
    } elseif (isset($_POST['edit_customer'])) {
        $id = $_POST['customer_id'];
        $customer_name = trim($_POST['customer_name']);
        $email = trim($_POST['email']);
        $mobile_no = trim($_POST['mobile_no']);
        $gst_no = trim($_POST['gst_no']);

        if (empty($customer_name) || empty($email) || empty($mobile_no)) {
            $message = "Name, Email, and Mobile No. are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
        } else {
            // Check if email already exists for another customer
            $database->query('SELECT id FROM customers WHERE email = :email AND id != :id');
            $database->bind(':email', $email);
            $database->bind(':id', $id);
            $database->execute();
            if ($database->rowCount() > 0) {
                $message = "Customer with this email already exists.";
            } else {
                $database->query('UPDATE customers SET customer_name = :customer_name, email = :email, mobile_no = :mobile_no, gst_no = :gst_no WHERE id = :id');
                $database->bind(':customer_name', $customer_name);
                $database->bind(':email', $email);
                $database->bind(':mobile_no', $mobile_no);
                $database->bind(':gst_no', $gst_no);
                $database->bind(':id', $id);

                if ($database->execute()) {
                    $message = "Customer updated successfully!";
                } else {
                    $message = "Error updating customer.";
                }
            }
        }
    } elseif (isset($_POST['delete_customer'])) {
        $id = $_POST['customer_id'];
        $database->query('DELETE FROM customers WHERE id = :id');
        $database->bind(':id', $id);
        if ($database->execute()) {
            $message = "Customer deleted successfully!";
        } else {
            $message = "Error deleting customer.";
        }
    }
}

// Search and filter parameters
$search_customer_name = $_GET['search_customer_name'] ?? '';
$search_email = $_GET['search_email'] ?? '';
$search_mobile = $_GET['search_mobile'] ?? '';

$where_clauses = [];
$bind_params = [];

if (!empty($search_customer_name)) {
    $where_clauses[] = "customer_name LIKE :search_customer_name";
    $bind_params[':search_customer_name'] = '%' . $search_customer_name . '%';
}
if (!empty($search_email)) {
    $where_clauses[] = "email LIKE :search_email";
    $bind_params[':search_email'] = '%' . $search_email . '%';
}
if (!empty($search_mobile)) {
    $where_clauses[] = "mobile_no LIKE :search_mobile";
    $bind_params[':search_mobile'] = '%' . $search_mobile . '%';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Pagination settings
$customers_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $customers_per_page;

// Get total number of customers for pagination
$database->query("SELECT COUNT(*) FROM customers" . $where_sql);
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$total_customers = $database->single()->{'COUNT(*)'};
$total_pages = ceil($total_customers / $customers_per_page);

// Fetch customers
$database->query("SELECT * FROM customers" . $where_sql . " ORDER BY created_at DESC LIMIT :offset, :customers_per_page");
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$database->bind(':offset', $offset, PDO::PARAM_INT);
$database->bind(':customers_per_page', $customers_per_page, PDO::PARAM_INT);
$customers = $database->resultSet();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta customer_name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - CRM App</title>
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
            color: white; /* White text color for visibility */
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
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <?php if (!empty($company_logo_header)): ?>
                <img src="../<?php echo $company_logo_header; ?>" alt="Company Logo" class="logo">
            <?php endif; ?>
            <span><?php echo $company_customer_name_header; ?></span>
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
            <h1>Customer Management</h1>

            <?php if (!empty($message)): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="table-container">
                <h2>Customer List</h2>

                <!-- Search Bar -->
                <form class="form-inline mb-4" method="GET" action="customer_management.php">
                    <input type="text" class="form-control mr-sm-2" customer_name="search_customer_name" placeholder="Search by Name" value="<?php echo htmlspecialchars($search_customer_name); ?>">
                    <input type="text" class="form-control mr-sm-2" customer_name="search_email" placeholder="Search by Email" value="<?php echo htmlspecialchars($search_email); ?>">
                    <input type="text" class="form-control mr-sm-2" customer_name="search_mobile" placeholder="Search by Mobile" value="<?php echo htmlspecialchars($search_mobile); ?>">
                    <button class="btn btn-primary my-2 my-sm-0" type="submit">Search</button>
                    <a href="export_customers_excel.php?search_customer_name=<?php echo urlencode($search_customer_name); ?>&search_email=<?php echo urlencode($search_email); ?>&search_mobile=<?php echo urlencode($search_mobile); ?>" class="btn btn-success ml-2">Download Excel</a>
                    <button type="button" class="btn btn-info ml-2" data-toggle="modal" data-target="#addCustomerModal">Add New Customer</button>
                </form>

                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile No.</th>
                            <th>GST No.</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($customers)): ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($customer->id); ?></td>
                                    <td><?php echo htmlspecialchars($customer->customer_name); ?></td>
                                    <td><?php echo htmlspecialchars($customer->email); ?></td>
                                    <td><?php echo htmlspecialchars($customer->mobile_no); ?></td>
                                    <td><?php echo htmlspecialchars($customer->gst_no); ?></td>
                                    <td><?php echo htmlspecialchars($customer->created_at); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info edit-customer-btn" 
                                                data-id="<?php echo $customer->id; ?>" 
                                                data-customer_name="<?php echo htmlspecialchars($customer->customer_name); ?>" 
                                                data-email="<?php echo htmlspecialchars($customer->email); ?>" 
                                                data-mobile="<?php echo htmlspecialchars($customer->mobile_no); ?>" 
                                                data-gst="<?php echo htmlspecialchars($customer->gst_no); ?>"
                                                data-toggle="modal" data-target="#editCustomerModal">Edit</button>
                                        <form method="POST" action="customer_management.php" style="display:inline-block;">
                                            <input type="hidden" customer_name="customer_id" value="<?php echo $customer->id; ?>">
                                            <button type="submit" customer_name="delete_customer" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this customer?');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">No customers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <nav aria-label="Customer Pagination" class="pagination-container">
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="customer_management.php?page=<?php echo $i; ?>&search_customer_name=<?php echo urlencode($search_customer_name); ?>&search_email=<?php echo urlencode($search_email); ?>&search_mobile=<?php echo urlencode($search_mobile); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>

            <!-- Add Customer Modal -->
            <div class="modal fade" id="addCustomerModal" tabindex="-1" role="dialog" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addCustomerModalLabel">Add New Customer</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form method="POST" action="customer_management.php">
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for="add_customer_name">Name</label>
                                    <input type="text" class="form-control" id="add_customer_name" customer_name="customer_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="add_email">Email</label>
                                    <input type="email" class="form-control" id="add_email" customer_name="email" required>
                                </div>
                                <div class="form-group">
                                    <label for="add_mobile_no">Mobile No.</label>
                                    <input type="text" class="form-control" id="add_mobile_no" customer_name="mobile_no" required>
                                </div>
                                <div class="form-group">
                                    <label for="add_gst_no">GST No.</label>
                                    <input type="text" class="form-control" id="add_gst_no" customer_name="gst_no">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="submit" customer_name="add_customer" class="btn btn-primary">Add Customer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Customer Modal -->
            <div class="modal fade" id="editCustomerModal" tabindex="-1" role="dialog" aria-labelledby="editCustomerModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editCustomerModalLabel">Edit Customer</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form method="POST" action="customer_management.php">
                            <div class="modal-body">
                                <input type="hidden" id="edit_customer_id" customer_name="customer_id">
                                <div class="form-group">
                                    <label for="edit_customer_name">Name</label>
                                    <input type="text" class="form-control" id="edit_customer_name" customer_name="customer_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_email">Email</label>
                                    <input type="email" class="form-control" id="edit_email" customer_name="email" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_mobile_no">Mobile No.</label>
                                    <input type="text" class="form-control" id="edit_mobile_no" customer_name="mobile_no" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_gst_no">GST No.</label>
                                    <input type="text" class="form-control" id="edit_gst_no" customer_name="gst_no">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="submit" customer_name="edit_customer" class="btn btn-primary">Save Changes</button>
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
            $('.edit-customer-btn').on('click', function() {
                var id = $(this).data('id');
                var customer_name = $(this).data('customer_name');
                var email = $(this).data('email');
                var mobile = $(this).data('mobile');
                var gst = $(this).data('gst');

                $('#edit_customer_id').val(id);
                $('#edit_customer_name').val(customer_name);
                $('#edit_email').val(email);
                $('#edit_mobile_no').val(mobile);
                $('#edit_gst_no').val(gst);
            });
        });
    </script>
</body>
</html>
