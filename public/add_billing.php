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

// Fetch customers for the dropdown
$database->query("SELECT id, customer_name FROM customers ORDER BY customer_name ASC");
$customers = $database->resultSet();

// Fetch products for the dropdown
$database->query("SELECT id, product_name as name, product_model_no as model_no, product_category as category, unit, selling_price as sales_price, available_stock as stock_quantity, cgst, sgst FROM products ORDER BY name ASC");
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
    <title>Add Billing - CRM App</title>
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
        #items-table th:nth-child(6),
        #items-table td:nth-child(6) {
            min-width: 120px; /* Adjust as needed */
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
            <h1>Add New Bill</h1>
            <div class="form-container">
                <form id="add-bill-form" method="POST" action="save_bill.php">
                    <!-- Customer Details -->
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="customer_id">Customer</label>
                            <select id="customer_id" name="customer_id" class="form-control">
                                <option value="">Select Existing Customer</option>
                                <option value="new_customer">-- Add New Customer --</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer->id; ?>"><?php echo htmlspecialchars($customer->customer_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div id="new-customer-fields" style="display: none;">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="new_customer_name">Customer Name</label>
                                <input type="text" class="form-control" id="new_customer_name" name="new_customer_name">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="new_customer_mobile">Mobile No.</label>
                                <input type="text" class="form-control" id="new_customer_mobile" name="new_customer_mobile">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="new_customer_address">Address</label>
                                <input type="text" class="form-control" id="new_customer_address" name="new_customer_address">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="new_customer_gst">GST No.</label>
                                <input type="text" class="form-control" id="new_customer_gst" name="new_customer_gst">
                            </div>
                        </div>
                    </div>

                    <!-- Invoice Details -->
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="invoice_date">Invoice Date</label>
                            <input type="date" class="form-control" id="invoice_date" name="invoice_date" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="invoice_no">Invoice No.</label>
                            <input type="text" class="form-control" id="invoice_no" name="invoice_no" readonly>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="payment_status">Payment Status</label>
                            <select id="payment_status" name="payment_status" class="form-control">
                                <option value="Received">Received</option>
                                <option value="Due">Due</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="payment_type">Payment Type</label>
                            <select id="payment_type" name="payment_type" class="form-control">
                                <option value="Cash">Cash</option>
                                <option value="UPI">UPI</option>
                                <option value="Debit Card">Debit Card</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Net Banking">Net Banking</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6" id="other-payment-type-field" style="display: none;">
                            <label for="other_payment_type">Please Specify</label>
                            <input type="text" class="form-control" id="other_payment_type" name="other_payment_type">
                        </div>
                    </div>
                    <div class="form-row" id="eway-bill-field" style="display: none;">
                        <div class="form-group col-md-12">
                            <label for="eway_bill_no">E-Way Bill No.</label>
                            <input type="text" class="form-control" id="eway_bill_no" name="eway_bill_no">
                        </div>
                    </div>

                    <!-- Items Table -->
                    <table class="table table-bordered table-dark" id="items-table">
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
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Item rows will be added here dynamically -->
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-primary" id="add-item-btn">Add Item</button>

                    <!-- Total Summary -->
                    <div class="total-summary mt-4">
                        <h4>Total Summary</h4>
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Net Amount</label>
                                <input type="text" class="form-control" id="net_amount" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Total Discount</label>
                                <input type="text" class="form-control" id="total_discount" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Total CGST</label>
                                <input type="text" class="form-control" id="total_cgst" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Total SGST</label>
                                <input type="text" class="form-control" id="total_sgst" readonly>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="advance_amount">Advance Amount</label>
                                <input type="number" class="form-control" id="advance_amount" name="advance_amount" value="0">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Pending Amount</label>
                                <input type="text" class="form-control" id="pending_amount" readonly>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success">Submit</button>
                </form>
            </div>
        </div>
    </div>

    <div class="footer">
        <!-- Footer content -->
    </div>

    <div class="modal fade" id="responseModal" tabindex="-1" role="dialog" aria-labelledby="responseModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="responseModalLabel">Message</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="responseMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#add-bill-form').submit(function(event) {
                event.preventDefault(); // Prevent default form submission

                var formData = $(this).serialize();

                $.ajax({
                    type: "POST",
                    url: "save_bill.php",
                    data: formData,
                    dataType: "json", // Expect JSON response
                    success: function(response) {
                        if (response.success) {
                            $('#responseMessage').text(response.message);
                            $('#responseModal').modal('show');
                            // Optionally clear the form or redirect after a delay
                            // setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            $('#responseMessage').text(response.message);
                            $('#responseModal').modal('show');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#responseMessage').text("An error occurred: " + xhr.responseText);
                        $('#responseModal').modal('show');
                    }
                });
            });
            // Show/hide new customer fields
            $('#customer_id').change(function() {
                if ($(this).val() == 'new_customer') {
                    $('#new-customer-fields').show();
                } else {
                    $('#new-customer-fields').hide();
                }
            });

            // Show/hide other payment type field
            $('#payment_type').change(function() {
                if ($(this).val() == 'Other') {
                    $('#other-payment-type-field').show();
                } else {
                    $('#other-payment-type-field').hide();
                }
            });

            // Add item to table
            var products = <?php echo json_encode($products); ?>;
            $('#add-item-btn').click(function() {
                var row = '<tr>';
                row += '<td><select class="form-control product-select" name="product_id[]"><option value="">Select Product</option>';
                products.forEach(function(product) {
                    row += '<option value="' + product.id + '">' + product.name + '</option>';
                });
                row += '</select></td>';
                row += '<td><input type="text" class="form-control" name="model_no[]" readonly></td>';
                row += '<td><input type="text" class="form-control" name="category[]" readonly></td>';
                row += '<td><input type="text" class="form-control" name="unit[]" readonly></td>';
                row += '<td><input type="text" class="form-control" name="sales_price[]" readonly></td>';
                row += '<td><input type="number" class="form-control quantity" name="quantity[]" placeholder="Available: 0"></td>';
                row += '<td><input type="number" class="form-control discount" name="discount[]" value="0"></td>';
                row += '<td><input type="text" class="form-control cgst" name="cgst[]" readonly></td>';
                row += '<td><input type="text" class="form-control sgst" name="sgst[]" readonly></td>';
                row += '<td><input type="text" class="form-control total-amount" name="total_amount[]" readonly></td>';
                row += '<td><button type="button" class="btn btn-danger remove-item-btn">Remove</button></td>';
                row += '</tr>';
                $('#items-table tbody').append(row);
            });

            // Remove item from table
            $(document).on('click', '.remove-item-btn', function() {
                $(this).closest('tr').remove();
                updateTotalSummary();
            });

            // Update product details on product selection
            $(document).on('change', '.product-select', function() {
                var productId = $(this).val();
                var row = $(this).closest('tr');
                var selectedProduct = products.find(p => p.id == productId);

                if (selectedProduct) {
                    row.find('input[name="model_no[]"]').val(selectedProduct.model_no);
                    row.find('input[name="category[]"]').val(selectedProduct.category);
                    row.find('input[name="unit[]"]').val(selectedProduct.unit);
                    row.find('input[name="sales_price[]"]').val(selectedProduct.sales_price);
                    
                    // Calculate available stock after considering quantities already added in this bill
                    var quantityAlreadyAdded = getQuantityAlreadyAdded(productId);
                    var effectiveStock = selectedProduct.stock_quantity - quantityAlreadyAdded;

                    row.find('.quantity').attr('placeholder', 'Avi.Stk: ' + effectiveStock);
                    row.find('.cgst').val(selectedProduct.cgst);
                    row.find('.sgst').val(selectedProduct.sgst);
                }
            });

            // Helper function to get quantity already added for a product in the current bill
            function getQuantityAlreadyAdded(productId) {
                var totalAdded = 0;
                $('#items-table tbody tr').each(function() {
                    var currentProductId = $(this).find('.product-select').val();
                    if (currentProductId == productId) {
                        var quantityInput = $(this).find('.quantity');
                        // Only sum up if the quantity input has a value and is not the current row being edited
                        if (quantityInput.val() && quantityInput[0] !== document.activeElement) {
                            totalAdded += parseFloat(quantityInput.val());
                        }
                    }
                });
                return totalAdded;
            }

            // Calculate total amount on quantity/discount change
            $(document).on('input', '.quantity, .discount', function() {
                var row = $(this).closest('tr');
                var salesPrice = parseFloat(row.find('input[name="sales_price[]"]').val()) || 0;
                var quantity = parseFloat(row.find('.quantity').val()) || 0;
                var discount = parseFloat(row.find('.discount').val()) || 0;
                var cgst = parseFloat(row.find('.cgst').val()) || 0;
                var sgst = parseFloat(row.find('.sgst').val()) || 0;

                var total = salesPrice * quantity * (1 - discount / 100);
                var totalWithGst = total * (1 + (cgst + sgst) / 100);
                row.find('.total-amount').val(totalWithGst.toFixed(2));
                updateTotalSummary();
            });

            // Update total summary
            function updateTotalSummary() {
                var netAmount = 0;
                var totalDiscount = 0;
                var totalCgst = 0;
                var totalSgst = 0;

                $('#items-table tbody tr').each(function() {
                    var row = $(this);
                    var salesPrice = parseFloat(row.find('input[name="sales_price[]"]').val()) || 0;
                    var quantity = parseFloat(row.find('.quantity').val()) || 0;
                    var discount = parseFloat(row.find('.discount').val()) || 0;
                    var cgst = parseFloat(row.find('.cgst').val()) || 0;
                    var sgst = parseFloat(row.find('.sgst').val()) || 0;

                    netAmount += salesPrice * quantity;
                    totalDiscount += salesPrice * quantity * (discount / 100);
                    totalCgst += (salesPrice * quantity * (1 - discount / 100)) * (cgst / 100);
                    totalSgst += (salesPrice * quantity * (1 - discount / 100)) * (sgst / 100);
                });

                $('#net_amount').val(netAmount.toFixed(2));
                $('#total_discount').val(totalDiscount.toFixed(2));
                $('#total_cgst').val(totalCgst.toFixed(2));
                $('#total_sgst').val(totalSgst.toFixed(2));

                // Update pending amount
                var advanceAmount = parseFloat($('#advance_amount').val()) || 0;
                var pendingAmount = netAmount - totalDiscount + totalCgst + totalSgst - advanceAmount;
                $('#pending_amount').val(pendingAmount.toFixed(2));

                // Show/hide eway bill field
                if ((netAmount - totalDiscount) > 100000) {
                    $('#eway-bill-field').show();
                } else {
                    $('#eway-bill-field').hide();
                }
            }

            // Update pending amount on advance amount change
            $('#advance_amount').on('input', function() {
                updateTotalSummary();
            });

            // Set invoice date to today and generate invoice number
            var today = new Date().toISOString().split('T')[0];
            $('#invoice_date').val(today);

            // You will need an AJAX call to get the last invoice number and generate the new one.
            // For now, we'll just put a placeholder.
            var year = new Date().getFullYear();
            var nextYear = (year + 1).toString().slice(-2);
            $('#invoice_no').val('00001/' + year + '-' + nextYear);
        });
    </script>
</body>
</html>