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
$product_id = $_GET['id'] ?? null;
$product_data = null;

// Fetch company profile for header/navigation
$company_name_header = "Your Company Name"; // Default
$company_logo_header = ""; // Default

$database->query('SELECT company_name, company_logo FROM company_profile LIMIT 1');
$header_company_profile = $database->single();

if($header_company_profile) {
    $company_name_header = htmlspecialchars($header_company_profile->company_name);
    $company_logo_header = htmlspecialchars($header_company_profile->company_logo);
}

// Fetch product data if ID is provided
if ($product_id) {
    $database->query('SELECT * FROM products WHERE id = :id');
    $database->bind(':id', $product_id);
    $product_data = $database->single();

    if (!$product_data) {
        $message = "Product not found.";
        $product_id = null; // Invalidate product_id if not found
    }
}

// Handle product update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $product_id) {
    $product_name = $_POST['product_name'] ?? '';
    $product_model_no = $_POST['product_model_no'] ?? '';
    $product_category = $_POST['product_category'] ?? '';
    $product_warranty = $_POST['product_warranty'] ?? '';
    $unit = $_POST['unit'] ?? '';
    $selling_price = $_POST['selling_price'] ?? 0;
    $purchase_price = $_POST['purchase_price'] ?? 0;
    $cgst = $_POST['cgst'] ?? 0;
    $sgst = $_POST['sgst'] ?? 0;
    $available_stock = $_POST['available_stock'] ?? 0;
    $product_summary = $_POST['product_summary'] ?? '';

    $current_product_image = $product_data->product_image ?? '';

    $upload_dir = __DIR__ . '/../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
        $image_name = uniqid() . '_' . basename($_FILES['product_image']['name']);
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_dir . $image_name)) {
            $current_product_image = 'uploads/' . $image_name;
        }
    }

    $database->query('UPDATE products SET product_name = :product_name, product_model_no = :product_model_no, product_category = :product_category, product_warranty = :product_warranty, unit = :unit, selling_price = :selling_price, purchase_price = :purchase_price, product_image = :product_image, cgst = :cgst, sgst = :sgst, available_stock = :available_stock, product_summary = :product_summary WHERE id = :id');
    $database->bind(':product_name', $product_name);
    $database->bind(':product_model_no', $product_model_no);
    $database->bind(':product_category', $product_category);
    $database->bind(':product_warranty', $product_warranty);
    $database->bind(':unit', $unit);
    $database->bind(':selling_price', $selling_price);
    $database->bind(':purchase_price', $purchase_price);
    $database->bind(':product_image', $current_product_image);
    $database->bind(':cgst', $cgst);
    $database->bind(':sgst', $sgst);
    $database->bind(':available_stock', $available_stock);
    $database->bind(':product_summary', $product_summary);
    $database->bind(':id', $product_id);

    if ($database->execute()) {
        $message = "Product updated successfully!";
        // Re-fetch product data to display updated info
        $database->query('SELECT * FROM products WHERE id = :id');
        $database->bind(':id', $product_id);
        $product_data = $database->single();
    } else {
        $message = "Error updating product.";
    }
}

// Fetch existing product names for dropdown (for auto-fetch)
$database->query('SELECT DISTINCT product_name FROM products');
$existing_product_names = $database->resultSet();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - CRM App</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
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
            <h1>Edit Product</h1>
            <?php if (!empty($message)): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($product_data): ?>
            <form action="index.php?page=edit_product&id=<?php echo htmlspecialchars($product_id); ?>" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="product_name">Product Name</label>
                    <input type="text" class="form-control" id="product_name" name="product_name" value="<?php echo htmlspecialchars($product_data->product_name ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="product_model_no">Product Model No.</label>
                    <input type="text" class="form-control" id="product_model_no" name="product_model_no" value="<?php echo htmlspecialchars($product_data->product_model_no ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="product_category">Product Category</label>
                    <input type="text" class="form-control" id="product_category" name="product_category" value="<?php echo htmlspecialchars($product_data->product_category ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="product_warranty">Product Warranty</label>
                    <input type="text" class="form-control" id="product_warranty" name="product_warranty" value="<?php echo htmlspecialchars($product_data->product_warranty ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="unit">Unit</label>
                    <select class="form-control" id="unit" name="unit" required>
                        <option value="">Select Unit</option>
                        <option value="Mtr" <?php echo ($product_data->unit == 'Mtr') ? 'selected' : ''; ?>>Mtr</option>
                        <option value="Ft." <?php echo ($product_data->unit == 'Ft.') ? 'selected' : ''; ?>>Ft.</option>
                        <option value="Sqm" <?php echo ($product_data->unit == 'Sqm') ? 'selected' : ''; ?>>Sqm</option>
                        <option value="Kg" <?php echo ($product_data->unit == 'Kg') ? 'selected' : ''; ?>>Kg</option>
                        <option value="Gm" <?php echo ($product_data->unit == 'Gm') ? 'selected' : ''; ?>>Gm</option>
                        <option value="Box" <?php echo ($product_data->unit == 'Box') ? 'selected' : ''; ?>>Box</option>
                        <option value="Pec" <?php echo ($product_data->unit == 'Pec') ? 'selected' : ''; ?>>Pec</option>
                        <option value="Ltr" <?php echo ($product_data->unit == 'Ltr') ? 'selected' : ''; ?>>Ltr</option>
                        <option value="Gallon" <?php echo ($product_data->unit == 'Gallon') ? 'selected' : ''; ?>>Gallon</option>
                        <option value="Pound" <?php echo ($product_data->unit == 'Pound') ? 'selected' : ''; ?>>Pound</option>
                        <option value="cm" <?php echo ($product_data->unit == 'cm') ? 'selected' : ''; ?>>cm</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="selling_price">Selling Price</label>
                    <input type="number" step="0.01" class="form-control" id="selling_price" name="selling_price" value="<?php echo htmlspecialchars($product_data->selling_price ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="purchase_price">Purchase Price</label>
                    <input type="number" step="0.01" class="form-control" id="purchase_price" name="purchase_price" value="<?php echo htmlspecialchars($product_data->purchase_price ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="product_image">Product Image</label>
                    <input type="file" class="form-control-file" id="product_image" name="product_image">
                    <?php if (!empty($product_data->product_image)): ?>
                        <img src="../<?php echo htmlspecialchars($product_data->product_image); ?>" alt="Current Product Image" style="max-width: 150px; margin-top: 10px; display: block;">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="cgst">CGST</label>
                    <input type="number" step="0.01" class="form-control" id="cgst" name="cgst" value="<?php echo htmlspecialchars($product_data->cgst ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="sgst">SGST</label>
                    <input type="number" step="0.01" class="form-control" id="sgst" name="sgst" value="<?php echo htmlspecialchars($product_data->sgst ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="available_stock">Available Stock</label>
                    <input type="number" class="form-control" id="available_stock" name="available_stock" value="<?php echo htmlspecialchars($product_data->available_stock ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="product_summary">Product Summary</label>
                    <textarea class="form-control" id="product_summary" name="product_summary" rows="3"><?php echo htmlspecialchars($product_data->product_summary ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Update Product</button>
            </form>
            <?php else: ?>
                <p>No product selected for editing or product not found.</p>
            <?php endif; ?>
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