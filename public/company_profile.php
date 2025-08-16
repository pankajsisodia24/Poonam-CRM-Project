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

// Fetch existing company profile data
$company_data = null;
$database->query('SELECT *, company_mobile_no FROM company_profile LIMIT 1');
$company_data = $database->single();

// Fetch terms and conditions
$terms_conditions = [];
if ($company_data) {
    $database->query('SELECT * FROM terms_conditions WHERE company_id = :company_id');
    $database->bind(':company_id', $company_data->id);
    $terms_conditions = $database->resultSet();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("POST request received in company_profile.php");
    error_log("FILES array: " . print_r($_FILES, true));
    $company_name = $_POST['company_name'] ?? '';
    $company_gst_no = $_POST['company_gst_no'] ?? '';
    $company_pan_no = $_POST['company_pan_no'] ?? '';
    $company_email = $_POST['company_email'] ?? '';
    $company_address = $_POST['company_address'] ?? '';
    $state_code = $_POST['state_code'] ?? '';
    $hsn_sac_code = $_POST['hsn_sac_code'] ?? '';
    $bank_name = $_POST['bank_name'] ?? '';
    $ifsc_code = $_POST['ifsc_code'] ?? '';
    $account_no = $_POST['account_no'] ?? '';
    $company_mobile_no = $_POST['company_mobile_no'] ?? '';
    $terms = $_POST['terms'] ?? [];

    $company_logo = $company_data ? $company_data->company_logo : '';
    $company_authorised_seal = $company_data ? $company_data->company_authorised_seal : '';
    $qr_code_payment = $company_data ? $company_data->qr_code_payment : '';
    $theme_image = $company_data ? $company_data->theme_image : '';

    // Handle file uploads
    $upload_dir = __DIR__ . '/../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Function to handle file uploads with error reporting
    function handleUpload($file_input_name, $upload_dir, &$variable_to_update) {
        if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
            $file_tmp_name = $_FILES[$file_input_name]['tmp_name'];
            $file_name = uniqid() . '_' . basename($_FILES[$file_input_name]['name']);
            $destination = $upload_dir . $file_name;

            if (move_uploaded_file($file_tmp_name, $destination)) {
                $variable_to_update = 'uploads/' . $file_name;
            } else {
                error_log("Failed to move uploaded file {$file_tmp_name} to {$destination}. Error: " . error_get_last()['message']);
            }
        } elseif (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] != UPLOAD_ERR_NO_FILE) {
            error_log("File upload error for {$file_input_name}: " . $_FILES[$file_input_name]['error']);
        }
    }

    handleUpload('company_logo', $upload_dir, $company_logo);
    handleUpload('company_authorised_seal', $upload_dir, $company_authorised_seal);
    handleUpload('qr_code_payment', $upload_dir, $qr_code_payment);
    handleUpload('theme_image', $upload_dir, $theme_image);

    try {
        if ($company_data) {
            // Update existing record
            $database->query('UPDATE company_profile SET company_name = :company_name, company_logo = :company_logo, company_gst_no = :company_gst_no, company_pan_no = :company_pan_no, company_email = :company_email, company_address = :company_address, state_code = :state_code, hsn_sac_code = :hsn_sac_code, company_authorised_seal = :company_authorised_seal, qr_code_payment = :qr_code_payment, bank_name = :bank_name, ifsc_code = :ifsc_code, account_no = :account_no, theme_image = :theme_image, company_mobile_no = :company_mobile_no WHERE id = :id');
            $database->bind(':id', $company_data->id);
        } else {
            // Insert new record
            $database->query('INSERT INTO company_profile (company_name, company_logo, company_gst_no, company_pan_no, company_email, company_address, state_code, hsn_sac_code, company_authorised_seal, qr_code_payment, bank_name, ifsc_code, account_no, theme_image, company_mobile_no) VALUES (:company_name, :company_logo, :company_gst_no, :company_pan_no, :company_email, :company_address, :state_code, :hsn_sac_code, :company_authorised_seal, :qr_code_payment, :bank_name, :ifsc_code, :account_no, :theme_image, :company_mobile_no)');
        }

        $database->bind(':company_name', $company_name);
        $database->bind(':company_logo', $company_logo);
        $database->bind(':company_gst_no', $company_gst_no);
        $database->bind(':company_pan_no', $company_pan_no);
        $database->bind(':company_email', $company_email);
        $database->bind(':company_address', $company_address);
        $database->bind(':state_code', $state_code);
        $database->bind(':hsn_sac_code', $hsn_sac_code);
        $database->bind(':company_authorised_seal', $company_authorised_seal);
        $database->bind(':qr_code_payment', $qr_code_payment);
        $database->bind(':bank_name', $bank_name);
        $database->bind(':ifsc_code', $ifsc_code);
        $database->bind(':account_no', $account_no);
        $database->bind(':theme_image', $theme_image);
        $database->bind(':company_mobile_no', $company_mobile_no);

        if ($database->execute()) {
            $message = "Company profile saved successfully!";
            // Re-fetch data to display updated info
            $database->query('SELECT *, company_mobile_no FROM company_profile LIMIT 1');
            $company_data = $database->single();

            // Update terms and conditions
            if ($company_data) {
                // Delete existing terms
                $database->query('DELETE FROM terms_conditions WHERE company_id = :company_id');
                $database->bind(':company_id', $company_data->id);
                $database->execute();

                // Insert new terms
                foreach ($terms as $term_text) {
                    if (!empty(trim($term_text))) {
                        $database->query('INSERT INTO terms_conditions (company_id, term_text) VALUES (:company_id, :term_text)');
                        $database->bind(':company_id', $company_data->id);
                        $database->bind(':term_text', $term_text);
                        $database->execute();
                    }
                }
                // Re-fetch terms
                $database->query('SELECT * FROM terms_conditions WHERE company_id = :company_id');
                $database->bind(':company_id', $company_data->id);
                $terms_conditions = $database->resultSet();
            }

        } else {
            $message = "Error saving company profile.";
        }
    } catch (PDOException $e) {
        $message = "Database Error: " . $e->getMessage();
        error_log("Company Profile Save Error: " . $e->getMessage());
    }
}

// Fetch company profile for header/navigation (redundant but ensures latest data)
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
    <title>Company Profile - CRM App</title>
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
        .form-group img {
            max-width: 150px;
            height: auto;
            margin-top: 10px;
            display: block;
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
            <h1>Company Profile</h1>
            <?php if (!empty($message)): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>
            <form action="index.php?page=company_profile" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="company_name">Company Name</label>
                    <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($company_data->company_name ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="company_logo">Company Logo</label>
                    <input type="file" class="form-control-file" id="company_logo" name="company_logo">
                    <?php if (!empty($company_data->company_logo)): ?>
                        <img src="../<?php echo $company_data->company_logo; ?>" alt="Current Logo">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="company_gst_no">Company GST No.</label>
                    <input type="text" class="form-control" id="company_gst_no" name="company_gst_no" value="<?php echo htmlspecialchars($company_data->company_gst_no ?? ''); ?>" placeholder="e.g., 22AAAAA0000A1Z5" pattern="^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$" title="Enter a valid 15-digit GSTIN (e.g., 22AAAAA0000A1Z5)" required>
                </div>
                <div class="form-group">
                    <label for="company_pan_no">Company PAN No.</label>
                    <input type="text" class="form-control" id="company_pan_no" name="company_pan_no" value="<?php echo htmlspecialchars($company_data->company_pan_no ?? ''); ?>" placeholder="e.g., ABCDE1234F" pattern="^[A-Z]{5}[0-9]{4}[A-Z]{1}$" title="Enter a valid 10-character PAN (e.g., ABCDE1234F)" required>
                </div>
                <div class="form-group">
                    <label for="company_email">Company Email</label>
                    <input type="email" class="form-control" id="company_email" name="company_email" value="<?php echo htmlspecialchars($company_data->company_email ?? ''); ?>" placeholder="e.g., info@example.com" required>
                </div>
                <div class="form-group">
                    <label for="company_address">Company Address</label>
                    <textarea class="form-control" id="company_address" name="company_address" rows="3"><?php echo htmlspecialchars($company_data->company_address ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="company_mobile_no">Company Mobile No.</label>
                    <input type="text" class="form-control" id="company_mobile_no" name="company_mobile_no" value="<?php echo htmlspecialchars($company_data->company_mobile_no ?? ''); ?>" placeholder="e.g., 9876543210" pattern="^[6-9][0-9]{9}$" title="Enter a valid 10-digit Indian mobile number" required>
                </div>
                <div class="form-group">
                    <label for="state_code">State Code</label>
                    <input type="text" class="form-control" id="state_code" name="state_code" value="<?php echo htmlspecialchars($company_data->state_code ?? ''); ?>" placeholder="e.g., 07 for Delhi" pattern="^[0-9]{2}$" title="Enter a 2-digit state code (e.g., 07)" required>
                </div>
                <div class="form-group">
                    <label for="hsn_sac_code">HSN/SAC Code</label>
                    <input type="text" class="form-control" id="hsn_sac_code" name="hsn_sac_code" value="<?php echo htmlspecialchars($company_data->hsn_sac_code ?? ''); ?>" placeholder="e.g., 998311 for IT Services" pattern="^[0-9]{4,8}$" title="Enter a 4-8 digit HSN/SAC code" required>
                </div>
                <div class="form-group">
                    <label for="company_authorised_seal">Company Authorised Seal</label>
                    <input type="file" class="form-control-file" id="company_authorised_seal" name="company_authorised_seal">
                    <?php if (!empty($company_data->company_authorised_seal)): ?>
                        <img src="../<?php echo $company_data->company_authorised_seal; ?>" alt="Current Seal">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="qr_code_payment">QR Code for Payment</label>
                    <input type="file" class="form-control-file" id="qr_code_payment" name="qr_code_payment">
                    <?php if (!empty($company_data->qr_code_payment)): ?>
                        <img src="../<?php echo $company_data->qr_code_payment; ?>" alt="Current QR Code">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="bank_name">Bank Name</label>
                    <input type="text" class="form-control" id="bank_name" name="bank_name" value="<?php echo htmlspecialchars($company_data->bank_name ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="ifsc_code">IFSC Code</label>
                    <input type="text" class="form-control" id="ifsc_code" name="ifsc_code" value="<?php echo htmlspecialchars($company_data->ifsc_code ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="account_no">Account No.</label>
                    <input type="text" class="form-control" id="account_no" name="account_no" value="<?php echo htmlspecialchars($company_data->account_no ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Terms and Conditions</label>
                    <div id="terms-container">
                        <?php if (!empty($terms_conditions)): ?>
                            <?php foreach ($terms_conditions as $index => $term): ?>
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" name="terms[]" value="<?php echo htmlspecialchars($term->term_text); ?>">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-danger remove-term">-</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" name="terms[]" placeholder="Enter a term or condition">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-danger remove-term">-</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-success mt-2" id="add-term">Add New Item</button>
                </div>

                <div class="form-group">
                    <label for="theme_image">Theme Upload (Image)</label>
                    <input type="file" class="form-control-file" id="theme_image" name="theme_image">
                    <?php if (!empty($company_data->theme_image)): ?>
                        <img src="../<?php echo $company_data->theme_image; ?>" alt="Current Theme Image">
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary">Save Company Profile</button>
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
            $('#add-term').click(function() {
                $('#terms-container').append(
                    '<div class="input-group mb-2">' +
                        '<input type="text" class="form-control" name="terms[]" placeholder="Enter a term or condition">' +
                        '<div class="input-group-append">' +
                            '<button type="button" class="btn btn-danger remove-term">-</button>' +
                        '</div>' +
                    '</div>'
                );
            });

            $(document).on('click', '.remove-term', function() {
                $(this).closest('.input-group').remove();
            });
        });
    </script>
</body>
</html>