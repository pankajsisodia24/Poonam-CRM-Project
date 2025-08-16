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

$bill_id = $_GET['id'] ?? null;

if (!$bill_id) {
    $_SESSION['error'] = "No bill ID provided.";
    header("location: billing_management.php");
    exit;
}

// Fetch bill details
$database->query("SELECT b.*, c.customer_name, c.mobile_no, c.address, c.gst_no FROM bills b JOIN customers c ON b.customer_id = c.id WHERE b.id = :id");
$database->bind(':id', $bill_id);
$bill = $database->single();

if (!$bill) {
    $_SESSION['error'] = "Bill not found.";
    header("location: billing_management.php");
    exit;
}

// Fetch bill items
$database->query("SELECT bi.*, p.product_name as name, p.product_model_no as model_no, p.product_category as category, p.unit, p.selling_price as sales_price, p.cgst as product_cgst, p.sgst as product_sgst FROM bill_items bi JOIN products p ON bi.product_id = p.id WHERE bi.bill_id = :bill_id");
$database->bind(':bill_id', $bill_id);
$bill_items = $database->resultSet();

// Fetch company profile for header/navigation
$company_name_header = "Your Company Name"; // Default
$company_logo_header = ""; // Default

    $database->query('SELECT company_name, company_logo, company_address, company_email, company_gst_no, state_code, company_authorised_seal, qr_code_payment, bank_name, ifsc_code, account_no, company_pan_no, company_mobile_no FROM company_profile LIMIT 1');
    $company_profile = $database->single();

    if($company_profile) {
        $company_name_header = htmlspecialchars($company_profile->company_name);
        $company_logo_header = htmlspecialchars($company_profile->company_logo);
        $company_address = htmlspecialchars($company_profile->company_address);
        $company_email = htmlspecialchars($company_profile->company_email);
        $company_gst_no = htmlspecialchars($company_profile->company_gst_no);
        $company_state_code = htmlspecialchars($company_profile->state_code);
        $company_authorised_seal = htmlspecialchars($company_profile->company_authorised_seal);
        $qr_code_payment = htmlspecialchars($company_profile->qr_code_payment);
        $bank_name = htmlspecialchars($company_profile->bank_name);
        $ifsc_code = htmlspecialchars($company_profile->ifsc_code);
        $account_no = htmlspecialchars($company_profile->account_no);
        $company_pan_no = htmlspecialchars($company_profile->company_pan_no);
        $company_mobile_no = htmlspecialchars($company_profile->company_mobile_no);
    } else {
        // Default values if company profile not found
        $company_address = "N/A";
        $company_email = "N/A";
        $company_gst_no = "N/A";
        $company_state_code = "N/A";
        $company_authorised_seal = "";
        $qr_code_payment = "";
        $bank_name = "N/A";
        $ifsc_code = "N/A";
        $account_no = "N/A";
        $company_pan_no = "N/A";
        $company_mobile_no = "N/A";
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bill - CRM App</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to bottom, #FF0000, #800080, #A7D129);
        }
        .header, .footer {
            background-color: #333;
            color: white;
            padding: 10px 20px;
        }
        .wrapper {
            display: flex;
        }
        .side-navigation {
            width: 200px;
            background-color: #555;
            color: white;
            padding-top: 20px;
            min-height: calc(100vh - 110px);
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
        .form-container {
            background-color: rgba(0, 0, 0, 0.6);
            padding: 20px;
            border-radius: 10px;
            color: white;
        }
        .quantity {
            text-align: right;
        }
        .detail-row {
            margin-bottom: 10px;
        }
        .detail-label {
            font-weight: bold;
        }

        @media print {
            @page {
                size: auto;
                margin: 0mm;
            }
            body {
                background: none !important;
                color: black !important;
                margin: 0 !important;
            }
            .side-navigation, .header, .footer, .btn, .back-button {
                display: none !important;
            }
            .wrapper {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .content {
                flex-grow: 1;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            .form-container {
                background-color: white !important;
                color: black !important;
                padding: 15px !important; /* Add some padding for print */
                border-radius: 0 !important;
                box-shadow: none !important;
            }
            .row {
                display: flex;
                flex-wrap: wrap;
            }
            .col-md-6 {
                flex: 0 0 50%;
                max-width: 50%;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            table, th, td {
                border: 1px solid black !important;
                color: black !important;
                padding: 8px;
            }
            .table-bordered {
                border: 1px solid black !important;
            }
            .table-dark thead th {
                background-color: #f2f2f2 !important;
                color: black !important;
            }
            .table-dark tbody tr {
                background-color: white !important;
                color: black !important;
            }
            .table-dark tbody tr:hover {
                background-color: white !important;
                color: black !important;
            }
            .detail-row div {
                color: black !important;
            }
            h1, h2, h4, h5 {
                color: black !important;
            }
            h1 {
                display: none !important;
            }
            .print-center-content {
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                height: 100%; /* Ensure it takes full height of its parent */
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <!-- Header content -->
    </div>

    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="content">
            <h1>View Bill Details</h1>
            <div class="form-container">
                <h2 class="text-center mb-4">TAX INVOICE</h2>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Company Details</h5>
                        <p><strong>Name:</strong> <?php echo $company_name_header; ?></p>
                        <p><strong>Address:</strong> <?php echo $company_address; ?></p>
                        <p><strong>Email:</strong> <?php echo $company_email; ?></p>
                        <p><strong>GST No:</strong> <?php echo $company_gst_no; ?></p>
                        <p><strong>PAN No:</strong> <?php echo $company_pan_no; ?></p>
                        <p><strong>Mobile No:</strong> <?php echo $company_mobile_no; ?></p>
                        <p><strong>State Code:</strong> <?php echo $company_state_code; ?></p>
                    </div>
                    <div class="col-md-6">
                        <h5>Customer Details</h5>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($bill->customer_name); ?></p>
                        <p><strong>Mobile No:</strong> <?php echo htmlspecialchars($bill->mobile_no); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($bill->address); ?></p>
                        <p><strong>GST No:</strong> <?php echo htmlspecialchars($bill->gst_no); ?></p>
                    </div>
                </div>
                <hr style="border-top: 1px solid rgba(255, 255, 255, 0.5);">
                <div class="row mt-4">
                    <div class="col-md-6">
                        <?php if (!empty($bill->eway_bill_no)): ?>
                            <p><strong>E-Way Bill No:</strong> <?php echo htmlspecialchars($bill->eway_bill_no); ?></p>
                        <?php endif; ?>
                        <p><strong>Payment Status:</strong> <?php echo htmlspecialchars($bill->payment_status); ?></p>
                        <p><strong>Payment Mode:</strong> <?php echo htmlspecialchars($bill->payment_type); ?>
                            <?php if ($bill->payment_type == 'Other'): ?>
                                (<?php echo htmlspecialchars($bill->other_payment_type); ?>)
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-6 text-right">
                        <p><strong>Invoice Date:</strong> <?php echo htmlspecialchars($bill->invoice_date); ?></p>
                        <p><strong>Invoice No:</strong> <?php echo htmlspecialchars($bill->invoice_no); ?></p>
                    </div>
                </div>

                <h4 class="mt-4">Bill Items</h4>
                <table class="table table-bordered table-dark">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Model No.</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Sales Price</th>
                            <th>Quantity</th>
                            <th>Discount (%)</th>
                            <th>CGST (%)</th>
                            <th>SGST (%)</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bill_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item->name); ?></td>
                                <td><?php echo htmlspecialchars($item->model_no); ?></td>
                                <td><?php echo htmlspecialchars($item->category); ?></td>
                                <td><?php echo htmlspecialchars($item->unit); ?></td>
                                <td><?php echo htmlspecialchars($item->sales_price); ?></td>
                                <td><?php echo htmlspecialchars($item->quantity); ?></td>
                                <td><?php echo htmlspecialchars($item->discount); ?></td>
                                <td><?php echo htmlspecialchars($item->product_cgst); ?></td>
                                <td><?php echo htmlspecialchars($item->product_sgst); ?></td>
                                <td><?php echo htmlspecialchars($item->total_amount); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <h4 class="mt-4">Total Summary</h4>
                        <div class="row detail-row">
                            <div class="col-md-12"><span class="detail-label">Net Amount:</span> <?php echo htmlspecialchars($bill->net_amount); ?></div>
                        </div>
                        <div class="row detail-row">
                            <div class="col-md-12"><span class="detail-label">Total Discount:</span> <?php echo htmlspecialchars($bill->total_discount); ?></div>
                        </div>
                        <div class="row detail-row">
                            <div class="col-md-12"><span class="detail-label">Total CGST:</span> <?php echo htmlspecialchars($bill->total_cgst); ?></div>
                        </div>
                        <div class="row detail-row">
                            <div class="col-md-12"><span class="detail-label">Total SGST:</span> <?php echo htmlspecialchars($bill->total_sgst); ?></div>
                        </div>
                        <div class="row detail-row">
                            <div class="col-md-12"><span class="detail-label">Advance Amount:</span> <?php echo htmlspecialchars($bill->advance_amount); ?></div>
                        </div>
                        <div class="row detail-row">
                            <div class="col-md-12"><span class="detail-label">Pending Amount:</span> <?php echo htmlspecialchars($bill->pending_amount); ?></div>
                        </div>

                        <h5 class="mt-4">Bank Details</h5>
                        <p><strong>Bank Name:</strong> <?php echo $bank_name; ?></p>
                        <p><strong>IFSC Code:</strong> <?php echo $ifsc_code; ?></p>
                        <p><strong>Account No:</strong> <?php echo $account_no; ?></p>
                    </div>
                    <div class="col-md-6 text-center print-center-content">
                        <?php if (!empty($company_authorised_seal)): ?>
                            <h5 class="mt-4">Company Authorised Seal</h5>
                            <img src="../<?php echo $company_authorised_seal; ?>" alt="Company Authorised Seal" style="max-width: 150px; margin-top: 10px;">
                        <?php endif; ?>

                        <?php if (!empty($qr_code_payment)): ?>
                            <h5 class="mt-4">Scan for Payment</h5>
                            <img src="../<?php echo $qr_code_payment; ?>" alt="QR Code" style="max-width: 150px; margin-top: 10px;">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4 text-center">
                    <button class="btn btn-primary" onclick="window.print()">Print Bill</button>
                    <a href="billing_management.php" class="btn btn-secondary">Back to Billing Management</a>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <!-- Footer content -->
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>