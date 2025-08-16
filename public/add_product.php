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

// Handle product addition
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_name = $_POST['product_name_hidden'] ?? $_POST['product_name'] ?? '';
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

    $product_image = '';
    $upload_dir = __DIR__ . '/../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
        $image_name = uniqid() . '_' . basename($_FILES['product_image']['name']);
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_dir . $image_name)) {
            $product_image = 'uploads/' . $image_name;
        }
    }

    $database->query('INSERT INTO products (product_name, product_model_no, product_category, product_warranty, unit, selling_price, purchase_price, product_image, cgst, sgst, available_stock, product_summary) VALUES (:product_name, :product_model_no, :product_category, :product_warranty, :unit, :selling_price, :purchase_price, :product_image, :cgst, :sgst, :available_stock, :product_summary)');
    $database->bind(':product_name', $product_name);
    $database->bind(':product_model_no', $product_model_no);
    $database->bind(':product_category', $product_category);
    $database->bind(':product_warranty', $product_warranty);
    $database->bind(':unit', $unit);
    $database->bind(':selling_price', $selling_price);
    $database->bind(':purchase_price', $purchase_price);
    $database->bind(':product_image', $product_image);
    $database->bind(':cgst', $cgst);
    $database->bind(':sgst', $sgst);
    $database->bind(':available_stock', $available_stock);
    $database->bind(':product_summary', $product_summary);

    if ($database->execute()) {
        $message = "Product added successfully!";
        // Optionally redirect or clear form
        header("Location: index.php?page=product_management");
        exit();
    } else {
        $message = "Error adding product.";
    }
}

// Fetch existing product names for dropdown
$database->query('SELECT id, product_name FROM products GROUP BY product_name');
$existing_product_names = $database->resultSet();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - CRM App</title>
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
            <h1>Add New Product</h1>
            <?php if (!empty($message)): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>
            <form action="index.php?page=add_product" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="product_name">Product Name</label>
                    <input type="hidden" id="product_name_hidden" name="product_name_hidden">
                    <select class="form-control" id="product_name" name="product_name">
                        <option value="">Select or type a product name</option>
                        <?php foreach ($existing_product_names as $product): ?>
                            <option value="<?php echo htmlspecialchars($product->id); ?>"><?php echo htmlspecialchars($product->product_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" class="form-control mt-2" id="product_name_new" placeholder="Or enter a new product name">
                </div>
                <div class="form-group">
                    <label for="product_model_no">Product Model No.</label>
                    <input type="text" class="form-control" id="product_model_no" name="product_model_no" placeholder="Auto-fetched or enter new">
                </div>
                <div class="form-group">
                    <label for="product_category">Product Category</label>
                    <input type="text" class="form-control" id="product_category" name="product_category" placeholder="Auto-fetched or enter new">
                </div>
                <div class="form-group">
                    <label for="product_warranty">Product Warranty</label>
                    <input type="text" class="form-control" id="product_warranty" name="product_warranty" placeholder="Auto-fetched or enter new">
                </div>
                <div class="form-group">
                    <label for="unit">Unit</label>
                    <select class="form-control" id="unit" name="unit" required>
                        <option value="">Select Unit</option>
                        <option value="Mtr">Mtr</option>
                        <option value="Ft.">Ft.</option>
                        <option value="Sqm">Sqm</option>
                        <option value="Kg">Kg</option>
                        <option value="Gm">Gm</option>
                        <option value="Box">Box</option>
                        <option value="Pec">Pec</option>
                        <option value="Ltr">Ltr</option>
                        <option value="Gallon">Gallon</option>
                        <option value="Pound">Pound</option>
                        <option value="cm">cm</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="selling_price">Selling Price</label>
                    <input type="number" step="0.01" class="form-control" id="selling_price" name="selling_price" placeholder="Auto-fetched or enter new" required>
                </div>
                <div class="form-group">
                    <label for="purchase_price">Purchase Price</label>
                    <input type="number" step="0.01" class="form-control" id="purchase_price" name="purchase_price" placeholder="Auto-fetched or enter new" required>
                </div>
                <div class="form-group">
                    <label for="product_image">Product Image</label>
                    <input type="file" class="form-control-file" id="product_image" name="product_image">
                    <img id="current_product_image" src="" alt="Product Image" style="max-width: 150px; margin-top: 10px; display: none;">
                </div>
                <div class="form-group">
                    <label for="cgst">CGST</label>
                    <input type="number" step="0.01" class="form-control" id="cgst" name="cgst" placeholder="Auto-fetched or enter new">
                </div>
                <div class="form-group">
                    <label for="sgst">SGST</label>
                    <input type="number" step="0.01" class="form-control" id="sgst" name="sgst" placeholder="Auto-fetched or enter new">
                </div>
                <div class="form-group">
                    <label for="available_stock">Available Stock</label>
                    <input type="number" class="form-control" id="available_stock" name="available_stock" required>
                </div>
                <div class="form-group">
                    <label for="product_summary">Product Summary</label>
                    <textarea class="form-control" id="product_summary" name="product_summary" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Add Product</button>
            </form>
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
            // Handle product name selection/input
            $('#product_name').change(function() {
                var selectedProductId = $(this).val();
                var selectedProductName = $(this).find('option:selected').text();
                $('#product_name_hidden').val(selectedProductName);
                if (selectedProductId) {
                    // If an existing product name is selected, fetch its details
                    $.ajax({
                        url: 'fetch_product_details.php',
                        type: 'GET',
                        data: { product_id: selectedProductId },
                        dataType: 'json',
                        success: function(data) {
                            if (data) {
                                $('#product_model_no').val(data.product_model_no);
                                $('#product_category').val(data.product_category);
                                $('#product_warranty').val(data.product_warranty);
                                $('#unit').val(data.unit);
                                $('#selling_price').val(data.selling_price);
                                $('#purchase_price').val(data.purchase_price);
                                $('#cgst').val(data.cgst);
                                $('#sgst').val(data.sgst);
                                $('#product_summary').val(data.product_summary);
                                if (data.product_image) {
                                    $('#current_product_image').attr('src', data.product_image).show();
                                } else {
                                    $('#current_product_image').hide();
                                }
                            } else {
                                // Clear fields if no data found for selected product
                                $('#product_model_no, #product_category, #product_warranty, #selling_price, #purchase_price, #cgst, #sgst, #product_summary').val('');
                                $('#unit').val('');
                                $('#current_product_image').hide();
                            }
                        }
                    });
                    $('#product_name_new').val('').hide(); // Hide new product name input
                } else {
                    // If "Select or type" is chosen, show new product name input and clear fields
                    $('#product_name_new').show().val('');
                    $('#product_model_no, #product_category, #product_warranty, #selling_price, #purchase_price, #cgst, #sgst, #product_summary').val('');
                    $('#unit').val('');
                    $('#current_product_image').hide();
                }
            });

            // Use the new product name input if something is typed there
            $('#product_name_new').on('input', function() {
                if ($(this).val() !== '') {
                    $('#product_name').val(''); // Clear dropdown selection
                }
            });

            // Form submission logic to use the correct product name
            $('form').submit(function() {
                if ($('#product_name_new').is(':visible') && $('#product_name_new').val() !== '') {
                    $('#product_name_hidden').val($('#product_name_new').val());
                } else {
                    $('#product_name_hidden').val($('#product_name option:selected').text());
                }
                $('#product_name').val($('#product_name_hidden').val());
            });
        });
    </script>
</body>
</html>