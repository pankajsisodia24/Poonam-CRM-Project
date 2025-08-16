<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php';

$database = new Database();

$quotation = null;
$quotation_items = [];
$quotation_id = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $quotation_id = $_GET['id'];

    $database->query("SELECT * FROM quotations WHERE id = :id");
    $database->bind(':id', $quotation_id);
    $quotation = $database->single();

    if ($quotation) {
        $database->query("SELECT * FROM quotation_items WHERE quotation_id = :quotation_id");
        $database->bind(':quotation_id', $quotation_id);
        $quotation_items = $database->resultSet();
    } else {
        echo "<p style='color: red;'>Quotation not found.</p>";
        $quotation_id = null;
    }
}

// --- Handle Form Submission for Updating Quotation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quotation']) && $quotation_id) {
    $quotation_date = $_POST['quotation_date'];
    $valid_until = $_POST['valid_until'];
    $customer_name = $_POST['customer_name'];
    $customer_email = $_POST['customer_email'];
    $customer_phone = $_POST['customer_phone'];
    $customer_address = $_POST['customer_address'];
    $total_amount = 0;

    $database->beginTransaction();

    try {
        $database->query("UPDATE quotations SET quotation_date = :quotation_date, valid_until = :valid_until, customer_name = :customer_name, customer_email = :customer_email, customer_phone = :customer_phone, customer_address = :customer_address, total_amount = :total_amount WHERE id = :id");
        $database->bind(':quotation_date', $quotation_date);
        $database->bind(':valid_until', $valid_until);
        $database->bind(':customer_name', $customer_name);
        $database->bind(':customer_email', $customer_email);
        $database->bind(':customer_phone', $customer_phone);
        $database->bind(':customer_address', $customer_address);
        $database->bind(':total_amount', $total_amount);
        $database->bind(':id', $quotation_id);
        $database->execute();

        $database->query("DELETE FROM quotation_items WHERE quotation_id = :quotation_id");
        $database->bind(':quotation_id', $quotation_id);
        $database->execute();

        $product_ids = $_POST['product_id'];
        $product_names = $_POST['product_name'];
        $model_nos = $_POST['model_no'];
        $categories = $_POST['category'];
        $units = $_POST['unit'];
        $quantities = $_POST['quantity'];
        $selling_prices = $_POST['selling_price'];
        $discounts = $_POST['discount'];
        $cgsts = $_POST['cgst'];
        $sgsts = $_POST['sgst'];

        $item_total_sum = 0;

        for ($i = 0; $i < count($product_ids); $i++) {
            $product_id = $product_ids[$i];
            $product_name = $product_names[$i];
            $model_no = $model_nos[$i];
            $category = $categories[$i];
            $unit = $units[$i];
            $quantity = $quantities[$i];
            $selling_price = $selling_prices[$i];
            $discount = $discounts[$i];
            $cgst = $cgsts[$i];
            $sgst = $sgsts[$i];

            $item_base_price = $quantity * $selling_price;
            $item_discount_amount = $item_base_price * ($discount / 100);
            $item_price_after_discount = $item_base_price - $item_discount_amount;
            $item_cgst_amount = $item_price_after_discount * ($cgst / 100);
            $item_sgst_amount = $item_price_after_discount * ($sgst / 100);
            $item_total = $item_price_after_discount + $item_cgst_amount + $item_sgst_amount;
            $item_total_sum += $item_total;

            $database->query("INSERT INTO quotation_items (quotation_id, product_id, product_name, model_no, category, unit, quantity, selling_price, discount, cgst, sgst, total_price) VALUES (:quotation_id, :product_id, :product_name, :model_no, :category, :unit, :quantity, :selling_price, :discount, :cgst, :sgst, :total_price)");
            $database->bind(':quotation_id', $quotation_id);
            $database->bind(':product_id', $product_id);
            $database->bind(':product_name', $product_name);
            $database->bind(':model_no', $model_no);
            $database->bind(':category', $category);
            $database->bind(':unit', $unit);
            $database->bind(':quantity', $quantity);
            $database->bind(':selling_price', $selling_price);
            $database->bind(':discount', $discount);
            $database->bind(':cgst', $cgst);
            $database->bind(':sgst', $sgst);
            $database->bind(':total_price', $item_total);
            $database->execute();
        }

        $database->query("UPDATE quotations SET total_amount = :total_amount WHERE id = :id");
        $database->bind(':total_amount', $item_total_sum);
        $database->bind(':id', $quotation_id);
        $database->execute();

        $database->commit();
        echo "<p style='color: green;'>Quotation updated successfully!</p>";

        $database->query("SELECT * FROM quotations WHERE id = :id");
        $database->bind(':id', $quotation_id);
        $quotation = $database->single();

        $database->query("SELECT * FROM quotation_items WHERE quotation_id = :quotation_id");
        $database->bind(':quotation_id', $quotation_id);
        $quotation_items = $database->resultSet();

    } catch (Exception $e) {
        $database->rollBack();
        echo "<p style='color: red;'>Error updating quotation: " . $e->getMessage() . "</p>";
    }
}

ob_start();
?>
<style>
.item-row .form-group {
    margin-bottom: 0.5rem; /* Reduce space between rows */
}

.item-row .col-md-1,
.item-row .col-md-3 {
    padding-left: 0.5rem; /* Adjust padding */
    padding-right: 0.5rem; /* Adjust padding */
}

.item-row label {
    margin-bottom: 0.2rem; /* Reduce space between label and input */
    font-size: 0.85rem; /* Smaller font for labels */
}

.item-row .form-control {
    height: calc(1.5em + .75rem + 2px); /* Standard Bootstrap input height */
    padding: .375rem .75rem; /* Standard Bootstrap input padding */
}

.item-row .remove-item {
    height: calc(1.5em + .75rem + 2px); /* Match input height */
    padding: .375rem .75rem; /* Match input padding */
}
</style>

<div class="container">
    <div class="form-container">
        <h1>Edit Quotation #<?php echo htmlspecialchars($quotation_id ?? 'N/A'); ?></h1>

        <?php if ($quotation_id && $quotation): ?>
            <form method="POST" action="">
                <input type="hidden" name="update_quotation" value="1">
                <div class="form-group">
                    <label for="quotation_date">Quotation Date:</label>
                    <input type="date" class="form-control" id="quotation_date" name="quotation_date" value="<?php echo htmlspecialchars($quotation->quotation_date); ?>" required>
                </div>
                <div class="form-group">
                    <label for="valid_until">Valid Until:</label>
                    <input type="date" class="form-control" id="valid_until" name="valid_until" value="<?php echo htmlspecialchars($quotation->valid_until); ?>" required>
                </div>
                <div class="form-group">
                    <label for="customer_name">Customer Name:</label>
                    <input type="text" class="form-control" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($quotation->customer_name); ?>" required>
                </div>
                <div class="form-group">
                    <label for="customer_email">Customer Email:</label>
                    <input type="email" class="form-control" id="customer_email" name="customer_email" value="<?php echo htmlspecialchars($quotation->customer_email); ?>">
                </div>
                <div class="form-group">
                    <label for="customer_phone">Customer Phone:</label>
                    <input type="text" class="form-control" id="customer_phone" name="customer_phone" value="<?php echo htmlspecialchars($quotation->customer_phone); ?>">
                </div>
                <div class="form-group">
                    <label for="customer_address">Customer Address:</label>
                    <textarea class="form-control" id="customer_address" name="customer_address" rows="3"><?php echo htmlspecialchars($quotation->customer_address); ?></textarea>
                </div>

                <h3>Item Details (Bill Summary)</h3>
                <div id="item-details">
                    <?php if (!empty($quotation_items)): ?>
                        <?php foreach ($quotation_items as $item): ?>
                            <div class="item-row form-group row">
                                <div class="col-md-3">
                                    <label>Product Name</label>
                                    <select class="form-control product-select" name="product_id[]" required data-selected-id="<?php echo htmlspecialchars($item->product_id); ?>">
                                        <option value="">Select Product</option>
                                    </select>
                                    <input type="hidden" name="product_name[]" value="<?php echo htmlspecialchars($item->product_name); ?>">
                                </div>
                                <div class="col-md-1">
                                    <label>Model No.</label>
                                    <input type="text" class="form-control model-no" name="model_no[]" value="<?php echo htmlspecialchars($item->model_no); ?>" readonly>
                                </div>
                                <div class="col-md-1">
                                    <label>Category</label>
                                    <input type="text" class="form-control category" name="category[]" value="<?php echo htmlspecialchars($item->category); ?>" readonly>
                                </div>
                                <div class="col-md-1">
                                    <label>Unit</label>
                                    <input type="text" class="form-control unit" name="unit[]" value="<?php echo htmlspecialchars($item->unit); ?>" readonly>
                                </div>
                                <div class="col-md-1">
                                    <label>Qty</label>
                                    <input type="number" class="form-control quantity" name="quantity[]" min="1" value="<?php echo htmlspecialchars($item->quantity); ?>" required>
                                </div>
                                <div class="col-md-1">
                                    <label>Price</label>
                                    <input type="number" class="form-control selling-price" name="selling_price[]" step="0.01" min="0" value="<?php echo htmlspecialchars($item->selling_price); ?>" required>
                                </div>
                                <div class="col-md-1">
                                    <label>Disc (%)</label>
                                    <input type="number" class="form-control discount" name="discount[]" step="0.01" min="0" value="<?php echo htmlspecialchars($item->discount); ?>">
                                </div>
                                <div class="col-md-1">
                                    <label>CGST (%)</label>
                                    <input type="number" class="form-control cgst" name="cgst[]" step="0.01" min="0" value="<?php echo htmlspecialchars($item->cgst); ?>" readonly>
                                </div>
                                <div class="col-md-1">
                                    <label>SGST (%)</label>
                                    <input type="number" class="form-control sgst" name="sgst[]" step="0.01" min="0" value="<?php echo htmlspecialchars($item->sgst); ?>" readonly>
                                </div>
                                <div class="col-md-1">
                                    <label>Total</label>
                                    <input type="text" class="form-control item-total" name="item_total[]" readonly>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-danger remove-item">Remove</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="item-row form-group row">
                            <div class="col-md-3">
                                <label>Product Name</label>
                                <select class="form-control product-select" name="product_id[]" required>
                                    <option value="">Select Product</option>
                                </select>
                                <input type="hidden" name="product_name[]">
                            </div>
                            <div class="col-md-1">
                                <label>Model No.</label>
                                <input type="text" class="form-control model-no" name="model_no[]" readonly>
                            </div>
                            <div class="col-md-1">
                                <label>Category</label>
                                <input type="text" class="form-control category" name="category[]" readonly>
                            </div>
                            <div class="col-md-1">
                                <label>Unit</label>
                                <input type="text" class="form-control unit" name="unit[]" readonly>
                            </div>
                            <div class="col-md-1">
                                <label>Qty</label>
                                <input type="number" class="form-control quantity" name="quantity[]" min="1" value="1" required>
                            </div>
                            <div class="col-md-1">
                                <label>Price</label>
                                <input type="number" class="form-control selling-price" name="selling_price[]" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-1">
                                <label>Disc (%)</label>
                                <input type="number" class="form-control discount" name="discount[]" step="0.01" min="0" value="0">
                            </div>
                            <div class="col-md-1">
                                <label>CGST (%)</label>
                                <input type="number" class="form-control cgst" name="cgst[]" step="0.01" min="0" value="0" readonly>
                            </div>
                            <div class="col-md-1">
                                <label>SGST (%)</label>
                                <input type="number" class="form-control sgst" name="sgst[]" step="0.01" min="0" value="0" readonly>
                            </div>
                            <div class="col-md-1">
                                <label>Total</label>
                                <input type="text" class="form-control item-total" name="item_total[]" readonly>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger remove-item">Remove</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-success add-item">Add Item</button>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary submit-btn">Update Quotation</button>
                </div>
            </form>
        <?php else: ?>
            <p>Please provide a valid quotation ID to edit.</p>
        <?php endif; ?>
    </div>
</div>

<script>
    let allProducts = [];

    document.addEventListener('DOMContentLoaded', function() {
        const itemDetails = document.getElementById('item-details');
        const addItemBtn = document.querySelector('.add-item');

        fetch('fetch_products.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Fetched response data:', data);
                if (data.success) {
                    allProducts = data.data;
                    console.log('Products array:', allProducts);
                    itemDetails.querySelectorAll('.product-select').forEach(selectElement => {
                        const selectedProductId = selectElement.dataset.selectedId;
                        populateProductSelect(selectElement, selectedProductId);
                        calculateItemTotal(selectElement.closest('.item-row'));
                    });
                } else {
                    console.error('API Error:', data.message);
                    alert('Error fetching products: ' + data.message);
                }
            })
            .catch(error => console.error('Error fetching products:', error));

        function populateProductSelect(selectElement, selectedProductId = null) {
            selectElement.innerHTML = '<option value="">Select Product</option>';
            allProducts.forEach(product => {
                const option = document.createElement('option');
                option.value = product.id;
                option.textContent = product.product_name;
                if (selectedProductId == product.id) {
                    option.selected = true;
                }
                selectElement.appendChild(option);
            });
        }

        addItemBtn.addEventListener('click', function() {
            const newItemRow = document.createElement('div');
            newItemRow.classList.add('item-row', 'form-group', 'row');
            newItemRow.innerHTML = `
                <div class="col-md-3">
                    <label>Product Name</label>
                    <select class="form-control product-select" name="product_id[]" required>
                        <option value="">Select Product</option>
                    </select>
                    <input type="hidden" name="product_name[]">
                </div>
                <div class="col-md-1">
                    <label>Model No.</label>
                    <input type="text" class="form-control model-no" name="model_no[]" readonly>
                </div>
                <div class="col-md-1">
                    <label>Category</label>
                    <input type="text" class="form-control category" name="category[]" readonly>
                </div>
                <div class="col-md-1">
                    <label>Unit</label>
                    <input type="text" class="form-control unit" name="unit[]" readonly>
                </div>
                <div class="col-md-1">
                    <label>Qty</label>
                    <input type="number" class="form-control quantity" name="quantity[]" min="1" value="1" required>
                </div>
                <div class="col-md-1">
                    <label>Price</label>
                    <input type="number" class="form-control selling-price" name="selling_price[]" step="0.01" min="0" required>
                </div>
                <div class="col-md-1">
                    <label>Disc (%)</label>
                    <input type="number" class="form-control discount" name="discount[]" step="0.01" min="0" value="0">
                </div>
                <div class="col-md-1">
                    <label>CGST (%)</label>
                    <input type="number" class="form-control cgst" name="cgst[]" step="0.01" min="0" value="0" readonly>
                </div>
                <div class="col-md-1">
                    <label>SGST (%)</label>
                    <input type="number" class="form-control sgst" name="sgst[]" step="0.01" min="0" value="0" readonly>
                </div>
                <div class="col-md-1">
                    <label>Total</label>
                    <input type="text" class="form-control item-total" name="item_total[]" readonly>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger remove-item">Remove</button>
                </div>
            `;
            itemDetails.appendChild(newItemRow);
            populateProductSelect(newItemRow.querySelector('.product-select'));
        });

        itemDetails.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item')) {
                if (e.target.closest('.item-row').parentNode.children.length > 1) {
                    e.target.closest('.item-row').remove();
                } else {
                    alert('You must have at least one item.');
                }
            }
        });

        itemDetails.addEventListener('change', function(e) {
            if (e.target.classList.contains('product-select')) {
                const selectedProductId = e.target.value;
                const itemRow = e.target.closest('.item-row');
                const selectedProduct = allProducts.find(p => p.id == selectedProductId);

                if (selectedProduct) {
                    itemRow.querySelector('input[name="product_name[]"]').value = selectedProduct.product_name;
                    itemRow.querySelector('.model-no').value = selectedProduct.product_model_no || '';
                    itemRow.querySelector('.category').value = selectedProduct.product_category || '';
                    itemRow.querySelector('.unit').value = selectedProduct.unit || '';
                    itemRow.querySelector('.selling-price').value = selectedProduct.selling_price;
                    itemRow.querySelector('.discount').value = selectedProduct.discount || 0;
                    itemRow.querySelector('.cgst').value = selectedProduct.cgst || 0;
                    itemRow.querySelector('.sgst').value = selectedProduct.sgst || 0;
                } else {
                    itemRow.querySelector('input[name="product_name[]"]').value = '';
                    itemRow.querySelector('.model-no').value = '';
                    itemRow.querySelector('.category').value = '';
                    itemRow.querySelector('.unit').value = '';
                    itemRow.querySelector('.selling-price').value = '';
                    itemRow.querySelector('.discount').value = 0;
                    itemRow.querySelector('.cgst').value = 0;
                    itemRow.querySelector('.sgst').value = 0;
                }
                calculateItemTotal(itemRow);
            } else if (e.target.classList.contains('quantity') ||
                       e.target.classList.contains('selling-price') ||
                       e.target.classList.contains('discount')) {
                calculateItemTotal(e.target.closest('.item-row'));
            }
        });

        function calculateItemTotal(itemRow) {
            const quantity = parseFloat(itemRow.querySelector('.quantity').value) || 0;
            const sellingPrice = parseFloat(itemRow.querySelector('.selling-price').value) || 0;
            const discount = parseFloat(itemRow.querySelector('.discount').value) || 0;
            const cgst = parseFloat(itemRow.querySelector('.cgst').value) || 0;
            const sgst = parseFloat(itemRow.querySelector('.sgst').value) || 0;

            let basePrice = quantity * sellingPrice;
            let discountAmount = basePrice * (discount / 100);
            let priceAfterDiscount = basePrice - discountAmount;
            let cgstAmount = priceAfterDiscount * (cgst / 100);
            let sgstAmount = priceAfterDiscount * (sgst / 100);
            let total = priceAfterDiscount + cgstAmount + sgstAmount;

            itemRow.querySelector('.item-total').value = total.toFixed(2);
        }
    });
</script>

<?php
$page_title = "Edit Quotation";
$content = ob_get_clean();
include 'layout.php';
?>