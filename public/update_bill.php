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

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database->beginTransaction();

    try {
        $bill_id = $_POST['bill_id'];
        $customer_id = $_POST['customer_id'];
        $invoice_date = $_POST['invoice_date'];
        $invoice_no = $_POST['invoice_no']; // Invoice number is readonly, so it comes from the form
        $payment_status = $_POST['payment_status'];
        $payment_type = $_POST['payment_type'];
        $other_payment_type = ($payment_type == 'Other') ? $_POST['other_payment_type'] : null;
        $eway_bill_no = $_POST['eway_bill_no'] ?? null;
        $advance_amount = $_POST['advance_amount'];

        // Calculate totals (re-calculate based on submitted items, or use hidden fields if already calculated on client-side)
        // For now, let's assume these come from the form or are recalculated here.
        // In a real application, you'd re-calculate these server-side for security and accuracy.
        $net_amount = 0;
        $total_discount = 0;
        $total_cgst = 0;
        $total_sgst = 0;

        $product_ids = $_POST['product_id'];
        $quantities = $_POST['quantity'];
        $discounts = $_POST['discount'];
        // Assuming CGST and SGST are fixed or fetched from product details

        for ($i = 0; $i < count($product_ids); $i++) {
            $database->query("SELECT selling_price, cgst, sgst FROM products WHERE id = :id");
            $database->bind(':id', $product_ids[$i]);
            $product = $database->single();
            $selling_price = $product->selling_price;
            $product_cgst = $product->cgst;
            $product_sgst = $product->sgst;

            $item_net_amount = $selling_price * $quantities[$i];
            $item_discount_amount = $item_net_amount * ($discounts[$i] / 100);
            $item_amount_after_discount = $item_net_amount - $item_discount_amount;
            $item_cgst_amount = $item_amount_after_discount * ($product_cgst / 100);
            $item_sgst_amount = $item_amount_after_discount * ($product_sgst / 100);

            $net_amount += $item_net_amount;
            $total_discount += $item_discount_amount;
            $total_cgst += $item_cgst_amount;
            $total_sgst += $item_sgst_amount;
        }

        $pending_amount = $net_amount - $total_discount + $total_cgst + $total_sgst - $advance_amount;

        // Update bills table
        $database->query('UPDATE bills SET customer_id = :customer_id, invoice_date = :invoice_date, payment_status = :payment_status, payment_type = :payment_type, other_payment_type = :other_payment_type, eway_bill_no = :eway_bill_no, net_amount = :net_amount, total_discount = :total_discount, total_cgst = :total_cgst, total_sgst = :total_sgst, advance_amount = :advance_amount, pending_amount = :pending_amount WHERE id = :id');
        $database->bind(':customer_id', $customer_id);
        $database->bind(':invoice_date', $invoice_date);
        $database->bind(':payment_status', $payment_status);
        $database->bind(':payment_type', $payment_type);
        $database->bind(':other_payment_type', $other_payment_type);
        $database->bind(':eway_bill_no', $eway_bill_no);
        $database->bind(':net_amount', $net_amount);
        $database->bind(':total_discount', $total_discount);
        $database->bind(':total_cgst', $total_cgst);
        $database->bind(':total_sgst', $total_sgst);
        $database->bind(':advance_amount', $advance_amount);
        $database->bind(':pending_amount', $pending_amount);
        $database->bind(':id', $bill_id);
        $database->execute();

        // --- Handle bill_items and product stock updates ---
        // Placeholder for now. This will involve comparing existing items with submitted items.
        // For each item:
        // 1. If new: insert into bill_items, decrease product stock.
        // 2. If removed: delete from bill_items, increase product stock.
        // 3. If quantity changed: update bill_items, adjust product stock accordingly.

        // Fetch existing bill items to compare
        $database->query("SELECT product_id, quantity FROM bill_items WHERE bill_id = :bill_id");
        $database->bind(':bill_id', $bill_id);
        $existing_items = $database->resultSet();

        $existing_item_map = [];
        foreach ($existing_items as $item) {
            $existing_item_map[$item->product_id] = $item->quantity;
        }

        $submitted_item_map = [];
        for ($i = 0; $i < count($product_ids); $i++) {
            $submitted_item_map[$product_ids[$i]] = $quantities[$i];
        }

        // Process submitted items
        for ($i = 0; $i < count($product_ids); $i++) {
            $product_id = $product_ids[$i];
            $quantity = $quantities[$i];
            $discount = $discounts[$i];

            $database->query("SELECT selling_price, cgst, sgst FROM products WHERE id = :id");
            $database->bind(':id', $product_id);
            $product = $database->single();
            $selling_price = $product->selling_price;
            $product_cgst = $product->cgst;
            $product_sgst = $product->sgst;

            $total_item_amount = $selling_price * $quantity * (1 - $discount / 100);
            $cgst_amount = $total_item_amount * ($product_cgst / 100);
            $sgst_amount = $total_item_amount * ($product_sgst / 100);

            if (isset($existing_item_map[$product_id])) {
                // Item exists, check for quantity change
                $old_quantity = $existing_item_map[$product_id];
                if ($old_quantity != $quantity) {
                    // Update bill_item
                    $database->query('UPDATE bill_items SET quantity = :quantity, discount = :discount, cgst = :cgst, sgst = :sgst, total_amount = :total_amount WHERE bill_id = :bill_id AND product_id = :product_id');
                    $database->bind(':quantity', $quantity);
                    $database->bind(':discount', $discount);
                    $database->bind(':cgst', $cgst_amount);
                    $database->bind(':sgst', $sgst_amount);
                    $database->bind(':total_amount', $total_item_amount);
                    $database->bind(':bill_id', $bill_id);
                    $database->bind(':product_id', $product_id);
                    $database->execute();

                    // Adjust stock
                    $stock_change = $quantity - $old_quantity;
                    $database->query('UPDATE products SET available_stock = available_stock - :stock_change WHERE id = :id');
                    $database->bind(':stock_change', $stock_change);
                    $database->bind(':id', $product_id);
                    $database->execute();
                }
                // Remove from existing_item_map as it's been processed
                unset($existing_item_map[$product_id]);
            } else {
                // New item, insert into bill_items
                $database->query('INSERT INTO bill_items (bill_id, product_id, quantity, discount, cgst, sgst, total_amount) VALUES (:bill_id, :product_id, :quantity, :discount, :cgst, :sgst, :total_amount)');
                $database->bind(':bill_id', $bill_id);
                $database->bind(':product_id', $product_id);
                $database->bind(':quantity', $quantity);
                $database->bind(':discount', $discount);
                $database->bind(':cgst', $cgst_amount);
                $database->bind(':sgst', $sgst_amount);
                $database->bind(':total_amount', $total_item_amount);
                $database->execute();

                // Decrease stock
                $database->query('UPDATE products SET available_stock = available_stock - :quantity WHERE id = :id');
                $database->bind(':quantity', $quantity);
                $database->bind(':id', $product_id);
                $database->execute();
            }
        }

        // Process removed items (remaining in existing_item_map)
        foreach ($existing_item_map as $product_id => $quantity) {
            // Delete from bill_items
            $database->query('DELETE FROM bill_items WHERE bill_id = :bill_id AND product_id = :product_id');
            $database->bind(':bill_id', $bill_id);
            $database->bind(':product_id', $product_id);
            $database->execute();

            // Increase stock
            $database->query('UPDATE products SET available_stock = available_stock + :quantity WHERE id = :id');
            $database->bind(':quantity', $quantity);
            $database->bind(':id', $product_id);
            $database->execute();
        }

        $database->commit();
        echo json_encode(['success' => true, 'message' => 'Bill updated successfully!']);
    } catch (Exception $e) {
        $database->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error updating bill: ' . $e->getMessage()]);
    }
}
