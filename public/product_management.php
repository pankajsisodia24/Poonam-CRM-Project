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

// Pagination settings
$products_per_page = 8;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $products_per_page;

// Search parameters
$search_name = $_GET['search_name'] ?? '';
$search_category = $_GET['search_category'] ?? '';

$where_clauses = [];
$bind_params = [];

if (!empty($search_name)) {
    $where_clauses[] = "product_name LIKE :search_name";
    $bind_params[':search_name'] = '%' . $search_name . '%';
}
if (!empty($search_category)) {
    $where_clauses[] = "product_category LIKE :search_category";
    $bind_params[':search_category'] = '%' . $search_category . '%';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Get total number of products for pagination
$database->query("SELECT COUNT(*) FROM products" . $where_sql);
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$total_products = $database->single()->{'COUNT(*)'};
$total_pages = ceil($total_products / $products_per_page);

// Fetch products
$database->query("SELECT * FROM products" . $where_sql . " LIMIT :offset, :products_per_page");
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$database->bind(':offset', $offset, PDO::PARAM_INT);
$database->bind(':products_per_page', $products_per_page, PDO::PARAM_INT);
$products = $database->resultSet();

// Fetch company profile for header/navigation
$company_name_header = "Your Company Name"; // Default
$company_logo_header = ""; // Default

$database->query('SELECT company_name, company_logo FROM company_profile LIMIT 1');
$header_company_profile = $database->single();

if($header_company_profile) {
    $company_name_header = htmlspecialchars($header_company_profile->company_name);
    $company_logo_header = htmlspecialchars($header_company_profile->company_logo);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - CRM App</title>
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
        .product-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .product-card img {
            max-width: 100%;
            height: 150px;
            object-fit: contain;
            margin-bottom: 10px;
        }
        .product-card h5 {
            font-size: 1.1em;
            margin-bottom: 5px;
        }
        .product-card p {
            font-size: 0.9em;
            color: #666;
        }
        .product-card .price {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
        }
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
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
        <a href="index.php?page=user_profile">User Profile</a>
        <a href="index.php?page=logout">Logout</a>
    </nav>

    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="content">
            <h1>Product Management</h1>

            <!-- Search Bar -->
            <form class="form-inline mb-4" method="GET" action="product_management.php">
                <input type="text" class="form-control mr-sm-2" name="search_name" placeholder="Search by Product Name" value="<?php echo htmlspecialchars($search_name); ?>">
                <input type="text" class="form-control mr-sm-2" name="search_category" placeholder="Search by Category" value="<?php echo htmlspecialchars($search_category); ?>">
                <button class="btn btn-outline-success my-2 my-sm-0" type="submit">Search</button>
                <a href="export_products_excel.php" class="btn btn-success ml-2">Download Excel</a>
            </form>

            <!-- Product Listing -->
            <div class="row">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="col-md-3">
                            <div class="product-card">
                                <?php if (!empty($product->product_image)): ?>
                                    <a href="../<?php echo htmlspecialchars($product->product_image); ?>" target="_blank">
                                        <img src="../<?php echo htmlspecialchars($product->product_image); ?>" alt="<?php echo htmlspecialchars($product->product_name); ?>">
                                    </a>
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/150?text=No+Image" alt="No Image">
                                <?php endif; ?>
                                <h5><?php echo htmlspecialchars($product->product_name); ?></h5>
                                <p>Category: <?php echo htmlspecialchars($product->product_category); ?></p>
                                <p class="price">Selling Price: ₹<?php echo htmlspecialchars(number_format($product->selling_price, 2)); ?></p>
                                <p>Available Stock: <?php echo htmlspecialchars($product->available_stock); ?></p>
                                <a href="edit_product.php?id=<?php echo $product->id; ?>" class="btn btn-sm btn-info">Edit</a>
                                <a href="delete_product.php?id=<?php echo $product->id; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <p>No products found.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <nav aria-label="Product Pagination" class="pagination-container">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                            <a class="page-link" href="product_management.php?page=<?php echo $i; ?>&search_name=<?php echo urlencode($search_name); ?>&search_category=<?php echo urlencode($search_category); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>

            <!-- Add Product Button (will link to a separate add_product.php or modal) -->
            <div class="text-center mt-4">
                <a href="add_product.php" class="btn btn-success">Add New Product</a>
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