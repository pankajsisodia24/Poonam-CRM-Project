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
    if (isset($_POST['add_supplier'])) {
        $company_name = trim($_POST['company_name']);
        $contact_person_name = trim($_POST['contact_person_name']);
        $email = trim($_POST['email']);
        $mobile_no = trim($_POST['mobile_no']);
        $gst_no = trim($_POST['gst_no']);

        if (empty($company_name) || empty($email) || empty($mobile_no)) {
            $message = "Company Name, Email, and Mobile No. are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
        } else {
            // Check if email already exists
            $database->query('SELECT id FROM suppliers WHERE email = :email');
            $database->bind(':email', $email);
            $database->execute();
            if ($database->rowCount() > 0) {
                $message = "Supplier with this email already exists.";
            } else {
                $database->query('INSERT INTO suppliers (company_name, contact_person_name, email, mobile_no, gst_no) VALUES (:company_name, :contact_person_name, :email, :mobile_no, :gst_no)');
                $database->bind(':company_name', $company_name);
                $database->bind(':contact_person_name', $contact_person_name);
                $database->bind(':email', $email);
                $database->bind(':mobile_no', $mobile_no);
                $database->bind(':gst_no', $gst_no);

                if ($database->execute()) {
                    $message = "Supplier added successfully!";
                } else {
                    $message = "Error adding supplier.";
                }
            }
        }
    } elseif (isset($_POST['edit_supplier'])) {
        $id = $_POST['supplier_id'];
        $company_name = trim($_POST['company_name']);
        $contact_person_name = trim($_POST['contact_person_name']);
        $email = trim($_POST['email']);
        $mobile_no = trim($_POST['mobile_no']);
        $gst_no = trim($_POST['gst_no']);

        if (empty($company_name) || empty($email) || empty($mobile_no)) {
            $message = "Company Name, Email, and Mobile No. are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
        } else {
            // Check if email already exists for another supplier
            $database->query('SELECT id FROM suppliers WHERE email = :email AND id != :id');
            $database->bind(':email', $email);
            $database->bind(':id', $id);
            $database->execute();
            if ($database->rowCount() > 0) {
                $message = "Supplier with this email already exists.";
            } else {
                $database->query('UPDATE suppliers SET company_name = :company_name, contact_person_name = :contact_person_name, email = :email, mobile_no = :mobile_no, gst_no = :gst_no WHERE id = :id');
                $database->bind(':company_name', $company_name);
                $database->bind(':contact_person_name', $contact_person_name);
                $database->bind(':email', $email);
                $database->bind(':mobile_no', $mobile_no);
                $database->bind(':gst_no', $gst_no);
                $database->bind(':id', $id);

                if ($database->execute()) {
                    $message = "Supplier updated successfully!";
                } else {
                    $message = "Error updating supplier.";
                }
            }
        }
    } elseif (isset($_POST['delete_supplier'])) {
        $id = $_POST['supplier_id'];
        $database->query('DELETE FROM suppliers WHERE id = :id');
        $database->bind(':id', $id);
        if ($database->execute()) {
            $message = "Supplier deleted successfully!";
        } else {
            $message = "Error deleting supplier.";
        }
    }
}

// Search and filter parameters
$search_company_name = $_GET['search_company_name'] ?? '';
$search_email = $_GET['search_email'] ?? '';
$search_mobile = $_GET['search_mobile'] ?? '';

$where_clauses = [];
$bind_params = [];

if (!empty($search_company_name)) {
    $where_clauses[] = "company_name LIKE :search_company_name";
    $bind_params[':search_company_name'] = '%' . $search_company_name . '%';
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
$suppliers_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $suppliers_per_page;

// Get total number of suppliers for pagination
$database->query("SELECT COUNT(*) FROM suppliers" . $where_sql);
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$total_suppliers = $database->single()->{'COUNT(*)'};
$total_pages = ceil($total_suppliers / $suppliers_per_page);

// Fetch suppliers
$database->query("SELECT * FROM suppliers" . $where_sql . " ORDER BY created_at DESC LIMIT :offset, :suppliers_per_page");
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$database->bind(':offset', $offset, PDO::PARAM_INT);
$database->bind(':suppliers_per_page', $suppliers_per_page, PDO::PARAM_INT);
$suppliers = $database->resultSet();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management - CRM App</title>
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
        <a href="index.php?page=user_profile">User Profile</a>
        <a href="index.php?page=logout">Logout</a>
    </nav>

    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="content">
            <h1>Supplier Management</h1>

            <?php if (!empty($message)): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="table-container">
                <h2>Supplier List</h2>

                <!-- Search Bar -->
                <form class="form-inline mb-4" method="GET" action="supplier_management.php">
                    <input type="text" class="form-control mr-sm-2" name="search_company_name" placeholder="Search by Company Name" value="<?php echo htmlspecialchars($search_company_name); ?>">
                    <input type="text" class="form-control mr-sm-2" name="search_email" placeholder="Search by Email" value="<?php echo htmlspecialchars($search_email); ?>">
                    <input type="text" class="form-control mr-sm-2" name="search_mobile" placeholder="Search by Mobile" value="<?php echo htmlspecialchars($search_mobile); ?>">
                    <button class="btn btn-primary my-2 my-sm-0" type="submit">Search</button>
                    <a href="export_suppliers_excel.php?search_company_name=<?php echo urlencode($search_company_name); ?>&search_email=<?php echo urlencode($search_email); ?>&search_mobile=<?php echo urlencode($search_mobile); ?>" class="btn btn-success ml-2">Download Excel</a>
                    <button type="button" class="btn btn-info ml-2" data-toggle="modal" data-target="#addSupplierModal">Add New Supplier</button>
                </form>

                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Company Name</th>
                            <th>Contact Person</th>
                            <th>Email</th>
                            <th>Mobile No.</th>
                            <th>GST No.</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($suppliers)): ?>
                            <?php foreach ($suppliers as $supplier): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($supplier->id); ?></td>
                                    <td><?php echo htmlspecialchars($supplier->company_name); ?></td>
                                    <td><?php echo htmlspecialchars($supplier->contact_person_name); ?></td>
                                    <td><?php echo htmlspecialchars($supplier->email); ?></td>
                                    <td><?php echo htmlspecialchars($supplier->mobile_no); ?></td>
                                    <td><?php echo htmlspecialchars($supplier->gst_no); ?></td>
                                    <td><?php echo htmlspecialchars($supplier->created_at); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info edit-supplier-btn" 
                                                data-id="<?php echo $supplier->id; ?>" 
                                                data-company="<?php echo htmlspecialchars($supplier->company_name); ?>" 
                                                data-contact="<?php echo htmlspecialchars($supplier->contact_person_name); ?>" 
                                                data-email="<?php echo htmlspecialchars($supplier->email); ?>" 
                                                data-mobile="<?php echo htmlspecialchars($supplier->mobile_no); ?>" 
                                                data-gst="<?php echo htmlspecialchars($supplier->gst_no); ?>"
                                                data-toggle="modal" data-target="#editSupplierModal">Edit</button>
                                        <form method="POST" action="supplier_management.php" style="display:inline-block;">
                                            <input type="hidden" name="supplier_id" value="<?php echo $supplier->id; ?>">
                                            <button type="submit" name="delete_supplier" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this supplier?');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">No suppliers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <nav aria-label="Supplier Pagination" class="pagination-container">
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="supplier_management.php?page=<?php echo $i; ?>&search_company_name=<?php echo urlencode($search_company_name); ?>&search_email=<?php echo urlencode($search_email); ?>&search_mobile=<?php echo urlencode($search_mobile); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>

            <!-- Add Supplier Modal -->
            <div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addSupplierModalLabel">Add New Supplier</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form method="POST" action="supplier_management.php">
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for="add_company_name">Company Name</label>
                                    <input type="text" class="form-control" id="add_company_name" name="company_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="add_contact_person_name">Contact Person Name</label>
                                    <input type="text" class="form-control" id="add_contact_person_name" name="contact_person_name">
                                </div>
                                <div class="form-group">
                                    <label for="add_email">Email</label>
                                    <input type="email" class="form-control" id="add_email" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label for="add_mobile_no">Mobile No.</label>
                                    <input type="text" class="form-control" id="add_mobile_no" name="mobile_no" required>
                                </div>
                                <div class="form-group">
                                    <label for="add_gst_no">GST No.</label>
                                    <input type="text" class="form-control" id="add_gst_no" name="gst_no">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="submit" name="add_supplier" class="btn btn-primary">Add Supplier</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Supplier Modal -->
            <div class="modal fade" id="editSupplierModal" tabindex="-1" role="dialog" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editSupplierModalLabel">Edit Supplier</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form method="POST" action="supplier_management.php">
                            <div class="modal-body">
                                <input type="hidden" id="edit_supplier_id" name="supplier_id">
                                <div class="form-group">
                                    <label for="edit_company_name">Company Name</label>
                                    <input type="text" class="form-control" id="edit_company_name" name="company_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_contact_person_name">Contact Person Name</label>
                                    <input type="text" class="form-control" id="edit_contact_person_name" name="contact_person_name">
                                </div>
                                <div class="form-group">
                                    <label for="edit_email">Email</label>
                                    <input type="email" class="form-control" id="edit_email" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_mobile_no">Mobile No.</label>
                                    <input type="text" class="form-control" id="edit_mobile_no" name="mobile_no" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_gst_no">GST No.</label>
                                    <input type="text" class="form-control" id="edit_gst_no" name="gst_no">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="submit" name="edit_supplier" class="btn btn-primary">Save Changes</button>
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
            $('.edit-supplier-btn').on('click', function() {
                var id = $(this).data('id');
                var company = $(this).data('company');
                var contact = $(this).data('contact');
                var email = $(this).data('email');
                var mobile = $(this).data('mobile');
                var gst = $(this).data('gst');

                $('#edit_supplier_id').val(id);
                $('#edit_company_name').val(company);
                $('#edit_contact_person_name').val(contact);
                $('#edit_email').val(email);
                $('#edit_mobile_no').val(mobile);
                $('#edit_gst_no').val(gst);
            });
        });
    </script>
</body>
</html>