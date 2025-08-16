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
$sale_id = $_GET['id'] ?? null;

if (!$sale_id) {
    header("Location: sales_management.php");
    exit;
}

// Fetch company details
$company_details = null;
$database->query('SELECT company_name, address, email, gst_no, state_code, hsn_sac_code FROM company_profile LIMIT 1');
$company_details = $database->single();

// Fetch sale details
$database->query('SELECT s.*, c.name as customer_name, c.address as customer_address, c.mobile_no as customer_mobile, c.gst_no as customer_gst FROM sales s JOIN customers c ON s.customer_id = c.id WHERE s.id = :id');
$database->bind(':id', $sale_id);
$sale = $database->single();

if (!$sale) {
    header("Location: sales_management.php");
    exit;
}

// Fetch sale items
$database->query('SELECT si.*, p.product_name, p.category, p.unit FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = :sale_id');
$database->bind(':sale_id', $sale_id);
$sale_items = $database->resultSet();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo htmlspecialchars($sale->invoice_no); ?></title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .invoice-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ccc;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .invoice-header h1 {
            font-size: 2.5em;
            color: #333;
        }
        .company-details, .customer-details {
            margin-bottom: 20px;
        }
        .company-details p, .customer-details p {
            margin-bottom: 5px;
        }
        .invoice-details table {
            width: 100%;
            margin-bottom: 20px;
        }
        .invoice-details th, .invoice-details td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .item-table th, .item-table td {
            padding: 8px;
            border: 1px solid #ccc;
        }
        .item-table th {
            background-color: #f2f2f2;
        }
        .total-summary-table th, .total-summary-table td {
            padding: 8px;
            text-align: right;
        }
        .print-button-container {
            text-align: center;
            margin-top: 20px;
        }
        @media print {
            .print-button-container {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <h1>TAX INVOICE</h1>
            <h3>#<?php echo htmlspecialchars($sale->invoice_no); ?></h3>
        </div>

        <div class="row">
            <div class="col-md-6 company-details">
                <h4>Company Details</h4>
                <?php if ($company_details): ?>
                    <p><strong>Company Name:</strong> <?php echo htmlspecialchars($company_details->company_name); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($company_details->address); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($company_details->email); ?></p>
                    <p><strong>GST No:</strong> <?php echo htmlspecialchars($company_details->gst_no); ?></p>
                    <p><strong>State Code:</strong> <?php echo htmlspecialchars($company_details->state_code); ?></p>
                    <p><strong>HSN/SAC Code:</strong> <?php echo htmlspecialchars($company_details->hsn_sac_code); ?></p>
                <?php else: ?>
                    <p>Company details not configured.</p>
                <?php endif; ?>
            </div>
            <div class="col-md-6 customer-details">
                <h4>Customer Details</h4>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($sale->customer_name); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($sale->customer_address); ?></p>
                <p><strong>Mobile No.:</strong> <?php echo htmlspecialchars($sale->customer_mobile); ?></p>
                <p><strong>GST No:</strong> <?php echo htmlspecialchars($sale->customer_gst); ?></p>
            </div>
        </div>

        <hr>

        <div class="invoice-details">
            <table class="table table-borderless">
                <tr>
                    <th>Invoice Date:</th>
                    <td><?php echo htmlspecialchars($sale->sale_date); ?></td>
                    <th>E-Way Bill No.:</th>
                    <td><?php echo htmlspecialchars($sale->eway_bill_no); ?></td>
                </tr>
                <tr>
                    <th>Payment Status:</th>
                    <td><?php echo htmlspecialchars(ucfirst($sale->payment_status)); ?></td>
                    <th>Payment Mode:</th>
                    <td><?php echo htmlspecialchars(ucfirst($sale->payment_mode)); ?>
                        <?php if ($sale->payment_mode === 'other' && !empty($sale->other_payment_mode_details)): ?>
                            (<?php echo htmlspecialchars($sale->other_payment_mode_details); ?>)
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <hr>

        <h4>Item Details</h4>
        <table class="table table-bordered item-table">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>CGST (%)</th>
                    <th>SGST (%)</th>
                    <th>Discount (%)</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sale_items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item->product_name); ?></td>
                        <td><?php echo htmlspecialchars($item->category); ?></td>
                        <td><?php echo htmlspecialchars($item->unit); ?></td>
                        <td><?php echo htmlspecialchars($item->quantity); ?></td>
                        <td><?php echo htmlspecialchars(number_format($item->unit_price, 2)); ?></td>
                        <td><?php echo htmlspecialchars(number_format($item->cgst_rate, 2)); ?></td>
                        <td><?php echo htmlspecialchars(number_format($item->sgst_rate, 2)); ?></td>
                        <td><?php echo htmlspecialchars(number_format($item->discount_rate, 2)); ?></td>
                        <td><?php echo htmlspecialchars(number_format($item->item_total_amount, 2)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="row">
            <div class="col-md-6 offset-md-6">
                <table class="table table-bordered total-summary-table">
                    <tbody>
                        <tr>
                            <th>Net Amount:</th>
                            <td><?php echo htmlspecialchars(number_format($sale->net_amount, 2)); ?></td>
                        </tr>
                        <tr>
                            <th>Total Discount:</th>
                            <td><?php echo htmlspecialchars(number_format($sale->total_discount, 2)); ?></td>
                        </tr>
                        <tr>
                            <th>Total CGST:</th>
                            <td><?php echo htmlspecialchars(number_format($sale->total_cgst, 2)); ?></td>
                        </tr>
                        <tr>
                            <th>Total SGST:</th>
                            <td><?php echo htmlspecialchars(number_format($sale->total_sgst, 2)); ?></td>
                        </tr>
                        <tr>
                            <th>Advance Amount:</th>
                            <td><?php echo htmlspecialchars(number_format($sale->advance_amount, 2)); ?></td>
                        </tr>
                        <tr>
                            <th>Total Amount:</th>
                            <td><?php echo htmlspecialchars(number_format($sale->total_amount, 2)); ?></td>
                        </tr>
                        <tr>
                            <th>Pending Amount:</th>
                            <td><?php echo htmlspecialchars(number_format($sale->pending_amount, 2)); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="print-button-container">
            <button class="btn btn-primary" onclick="window.print();">Print Invoice</button>
        </div>
    </div>
</body>
</html>