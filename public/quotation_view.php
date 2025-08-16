<?php
// Include database class
require_once __DIR__ . '/../app/database.php';
$database = new Database();

$quotation = null;
$quotation_items = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $quotation_id = $_GET['id'];

    // Fetch quotation details
    $database->query("SELECT * FROM quotations WHERE id = :id");
    $database->bind(':id', $quotation_id);
    $quotation = $database->single();

    if ($quotation) {
        // Fetch quotation items
        $database->query("SELECT * FROM quotation_items WHERE quotation_id = :id");
        $database->bind(':id', $quotation_id);
        $quotation_items = $database->resultSet();
    }
}

// Fetch company profile
$database->query('SELECT company_name, company_address, company_email, company_gst_no, company_pan_no, company_mobile_no, state_code, company_authorised_seal, qr_code_payment, bank_name, ifsc_code, account_no FROM company_profile LIMIT 1');
$company_profile = $database->single();

$company_name_display = $company_profile ? htmlspecialchars($company_profile->company_name) : "Your Company Name";
$company_address_display = $company_profile ? htmlspecialchars($company_profile->company_address) : "N/A";
$company_email_display = $company_profile ? htmlspecialchars($company_profile->company_email) : "N/A";
$company_tax_id_display = $company_profile ? htmlspecialchars($company_profile->company_gst_no) : "N/A";
$company_authorised_seal_display = $company_profile ? htmlspecialchars($company_profile->company_authorised_seal) : "";
$qr_code_payment_display = $company_profile ? htmlspecialchars($company_profile->qr_code_payment) : "";
$bank_name_display = $company_profile ? htmlspecialchars($company_profile->bank_name) : "N/A";
$ifsc_code_display = $company_profile ? htmlspecialchars($company_profile->ifsc_code) : "N/A";
$account_no_display = $company_profile ? htmlspecialchars($company_profile->account_no) : "N/A";
$company_pan_no_display = $company_profile ? htmlspecialchars($company_profile->company_pan_no) : "N/A";
$company_mobile_no_display = $company_profile ? htmlspecialchars($company_profile->company_mobile_no) : "N/A";


// Start capturing content for the layout
ob_start();
?>
<style>
    .item-details-grid {
        border: 1px solid #343a40; /* Dark border */
        border-radius: .25rem; /* Bootstrap default */
        overflow: hidden; /* For rounded corners */
        margin-bottom: 1rem; /* Spacing below grid */
    }

    .item-details-header {
        background-color: #343a40; /* Dark background for header */
        color: white;
        padding: .75rem 0; /* Bootstrap default padding for table headers */
        border-bottom: 1px solid #454d55; /* Lighter border for header */
    }

    .item-details-row {
        padding: .75rem 0; /* Bootstrap default padding for table rows */
        border-bottom: 1px solid #454d55; /* Lighter border between rows */
        background-color: #212529; /* Dark background for rows */
        color: white;
    }

    .item-details-row:last-child {
        border-bottom: none; /* No border for the last row */
    }

    .item-details-grid .col-md-1,
    .item-details-grid .col-md-3 {
        /* Add padding to columns to mimic table cell padding */
        padding-left: .75rem !important;
        padding-right: .75rem !important;
        /* Removed flex-shrink, white-space, overflow, text-overflow to allow content to wrap */
    }
    @media print {
        .btn.btn-primary.mb-3 { /* Hide print button */
            display: none;
        }
        .side-navigation, .navbar, .header, .footer { /* Hide navigation and footer */
            display: none;
        }
        .wrapper {
            display: block; /* Ensure content takes full width */
        }
        .content {
            padding: 0; /* Remove padding for print */
        }
        .quotation-container {
            width: 100%; /* Ensure container takes full width */
            margin: 0; /* Remove margins */
            box-shadow: none; /* Remove shadows */
            border: none; /* Remove borders */
        }
        .item-details-grid .col-md-1,
        .item-details-grid .col-md-3 {
            white-space: normal !important; /* Allow text to wrap */
            width: auto !important; /* Allow columns to expand */
            padding-left: .25rem !important; /* Adjust padding for print */
            padding-right: .25rem !important; /* Adjust padding for print */
        }
        .item-details-header, .item-details-row {
            page-break-inside: avoid; /* Prevent breaking rows across pages */
        }
    }
</style>

<div class="quotation-container form-container">
    <?php if ($quotation): ?>
        <div class="header" style="text-align: center; margin-bottom: 30px; background: none; color: white; padding: 0;">
            <h1>QUOTATION</h1>
        </div>

        <div class="details-section" style="display: flex; justify-content: space-between; margin-bottom: 30px;">
            <div class="company-details" style="width: 48%;">
                <h3>From:</h3>
                <p><strong><?php echo $company_name_display; ?></strong></p>
                <p><?php echo nl2br($company_address_display); ?></p>
                <p>Phone: <?php echo $company_mobile_no_display; ?></p>
                <p>Email: <?php echo $company_email_display; ?></p>
                <p>GST No: <?php echo $company_tax_id_display; ?></p>
                <p>PAN No: <?php echo $company_pan_no_display; ?></p>
            </div>

            <div class="quotation-info" style="width: 48%;">
                <h3>To:</h3>
                <p><strong><?php echo htmlspecialchars($quotation->customer_name); ?></strong></p>
                <p>Email: <?php echo htmlspecialchars($quotation->customer_email); ?></p>
                <p>Phone: <?php echo htmlspecialchars($quotation->customer_phone); ?></p>
                <p>Address: <?php echo nl2br(htmlspecialchars($quotation->customer_address)); ?></p>
                <br>
                <p><strong>Quotation Date:</strong> <?php echo htmlspecialchars($quotation->quotation_date); ?></p>
                <p><strong>Valid Until:</strong> <?php echo htmlspecialchars($quotation->valid_until); ?></p>
                <p><strong>Quotation ID:</strong> #<?php echo htmlspecialchars($quotation->id); ?></p>
            </div>
        </div>

        <h3>Item Details:</h3>
        <button onclick="window.print()" class="btn btn-primary mb-3">Print Quotation</button>
        <div class="item-details-grid">
            <!-- Header Row -->
            <div class="row item-details-header">
                                                <div class="col-md-1"><strong>Product</strong></div>
                <div class="col-md-1"><strong>Model No.</strong></div>
                <div class="col-md-1"><strong>Category</strong></div>
                <div class="col-md-1"><strong>Unit</strong></div>
                <div class="col-md-1 text-right"><strong>Qty</strong></div>
                <div class="col-md-1 text-right"><strong>Price</strong></div>
                <div class="col-md-1 text-right"><strong>Disc (%)</strong></div>
                <div class="col-md-2 text-right"><strong>GST (%)</strong></div>
                <div class="col-md-2 text-right"><strong>Total</strong></div>
            </div>

            <!-- Item Rows -->
            <?php $i = 1; foreach ($quotation_items as $item): 
                $item_base_price = $item->quantity * $item->selling_price;
                $item_discount_amount = $item_base_price * ($item->discount / 100);
                $item_price_after_discount = $item_base_price - $item_discount_amount;
                $item_cgst_amount = $item_price_after_discount * ($item->cgst / 100);
                $item_sgst_amount = $item_price_after_discount * ($item->sgst / 100);
                $calculated_item_total = $item_price_after_discount + $item_cgst_amount + $item_sgst_amount;
            ?>
                <div class="row item-details-row">
                    <div class="col-md-1"><?php echo $i++; ?></div>
                    <div class="col-md-2"><?php echo htmlspecialchars($item->product_name); ?></div>
                    <div class="col-md-1"><?php echo htmlspecialchars($item->model_no); ?></div>
                    <div class="col-md-1"><?php echo htmlspecialchars($item->category); ?></div>
                    <div class="col-md-1 text-right"><?php echo htmlspecialchars($item->unit); ?></div>
                    <div class="col-md-1 text-right"><?php echo htmlspecialchars(number_format($item->quantity, 2)); ?></div>
                    <div class="col-md-1 text-right"><?php echo htmlspecialchars(number_format($item->selling_price, 2)); ?></div>
                    <div class="col-md-1 text-right"><?php echo htmlspecialchars(number_format($item->discount, 2)); ?></div>
                    <div class="col-md-2 text-right"><?php echo htmlspecialchars(number_format($item->cgst + $item->sgst, 2)); ?></div>
                    <div class="col-md-2 text-right"><?php echo htmlspecialchars(number_format($calculated_item_total, 2)); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="total-summary" style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 30px;">
            <div class="total-summary-left" style="width: 48%;">
                <!-- Additional notes or terms can go here -->
                <p><strong>Notes:</strong></p>
                <p>Thank you for your business!</p>
            </div>
            <div class="total-summary-right" style="width: 48%; text-align: right;">
                <p>Subtotal: <?php echo htmlspecialchars(number_format($quotation->total_amount, 2)); ?></p>
                <p>Tax (Calculated): <?php
                    $total_tax = 0;
                    foreach ($quotation_items as $item) {
                        $item_base_price = $item->quantity * $item->selling_price;
                        $item_discount_amount = $item_base_price * ($item->discount / 100);
                        $item_price_after_discount = $item_base_price - $item_discount_amount;
                        $total_tax += ($item_price_after_discount * ($item->cgst / 100)) + ($item_price_after_discount * ($item->sgst / 100));
                    }
                    echo htmlspecialchars(number_format($total_tax, 2));
                ?></p>
                <p class="grand-total" style="font-weight: bold; font-size: 1.3em; color: #A7D129;">Grand Total: <?php echo htmlspecialchars(number_format($quotation->total_amount, 2)); ?></p>
                <div class="authorized-seal" style="margin-top: 20px;">
                    <?php if (!empty($company_authorised_seal_display)): ?>
                        <img src="../<?php echo $company_authorised_seal_display; ?>" alt="Authorized Seal" style="max-width: 150px; margin-bottom: 10px;">
                    <?php endif; ?>
                    <p style="margin: 0; padding-top: 20px; border-top: 1px solid #ccc; display: inline-block;">Authorized Signature</p>
                </div>
            </div>
        </div>

    <?php else: ?>
        <p>Quotation not found or invalid ID.</p>
    <?php endif; ?>
</div>

<?php
$page_title = "View Quotation";
$content = ob_get_clean();
include 'layout.php';
?>