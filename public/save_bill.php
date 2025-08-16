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
        // Customer details
        $customer_id = $_POST['customer_id'];
        if ($customer_id == 'new_customer') {
            $database->query('INSERT INTO customers (customer_name, mobile_no, address, gst_no) VALUES (:customer_name, :mobile_no, :address, :gst_no)');
            $database->bind(':customer_name', $_POST['new_customer_name']);
            $database->bind(':mobile_no', $_POST['new_customer_mobile']);
            $database->bind(':address', $_POST['new_customer_address']);
            $database->bind(':gst_no', $_POST['new_customer_gst']);
            $database->execute();
            $customer_id = $database->lastInsertId();
        }

        // Invoice details
        $invoice_date = $_POST['invoice_date'];
        $payment_status = $_POST['payment_status'];
        $payment_type = $_POST['payment_type'];
        $other_payment_type = ($payment_type == 'Other') ? $_POST['other_payment_type'] : null;
        $eway_bill_no = $_POST['eway_bill_no'];
        $advance_amount = $_POST['advance_amount'];

        // Generate invoice number
        $database->query("SELECT invoice_no FROM bills ORDER BY id DESC LIMIT 1");
        $last_bill = $database->single();
        $last_invoice_no = $last_bill ? $last_bill->invoice_no : '00000/2025-26';
        $parts = explode('/', $last_invoice_no);
        $new_invoice_num = intval($parts[0]) + 1;
        $invoice_no = str_pad($new_invoice_num, 5, '0', STR_PAD_LEFT) . '/2025-26';

        // Calculate totals
        $net_amount = 0;
        $total_discount = 0;
        $total_cgst = 0;
        $total_sgst = 0;

        $product_ids = $_POST['product_id'];
        $quantities = $_POST['quantity'];
        $discounts = $_POST['discount'];

        for ($i = 0; $i < count($product_ids); $i++) {
            $database->query("SELECT selling_price FROM products WHERE id = :id");
            $database->bind(':id', $product_ids[$i]);
            $product = $database->single();
            $selling_price = $product->selling_price;

            $net_amount += $selling_price * $quantities[$i];
            $total_discount += $selling_price * $quantities[$i] * ($discounts[$i] / 100);
            // Assuming CGST and SGST are 9% each for this example
            $total_cgst += ($selling_price * $quantities[$i] * (1 - $discounts[$i] / 100)) * 0.09;
            $total_sgst += ($selling_price * $quantities[$i] * (1 - $discounts[$i] / 100)) * 0.09;
        }

        $pending_amount = $net_amount - $total_discount + $total_cgst + $total_sgst - $advance_amount;

        // Insert into bills table
        $database->query('INSERT INTO bills (invoice_no, customer_id, invoice_date, payment_status, payment_type, other_payment_type, eway_bill_no, net_amount, total_discount, total_cgst, total_sgst, advance_amount, pending_amount) VALUES (:invoice_no, :customer_id, :invoice_date, :payment_status, :payment_type, :other_payment_type, :eway_bill_no, :net_amount, :total_discount, :total_cgst, :total_sgst, :advance_amount, :pending_amount)');
        $database->bind(':invoice_no', $invoice_no);
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
        $database->execute();
        $bill_id = $database->lastInsertId();

        // Insert into bill_items table and update product stock
        for ($i = 0; $i < count($product_ids); $i++) {
            $database->query("SELECT selling_price FROM products WHERE id = :id");
            $database->bind(':id', $product_ids[$i]);
            $product = $database->single();
            $selling_price = $product->selling_price;

            $total_item_amount = $selling_price * $quantities[$i] * (1 - $discounts[$i] / 100);
            $cgst = $total_item_amount * 0.09;
            $sgst = $total_item_amount * 0.09;

            $database->query('INSERT INTO bill_items (bill_id, product_id, quantity, discount, cgst, sgst, total_amount) VALUES (:bill_id, :product_id, :quantity, :discount, :cgst, :sgst, :total_amount)');
            $database->bind(':bill_id', $bill_id);
            $database->bind(':product_id', $product_ids[$i]);
            $database->bind(':quantity', $quantities[$i]);
            $database->bind(':discount', $discounts[$i]);
            $database->bind(':cgst', $cgst);
            $database->bind(':sgst', $sgst);
            $database->bind(':total_amount', $total_item_amount);
            $database->execute();

            // Update product stock
            $database->query('UPDATE products SET available_stock = available_stock - :quantity WHERE id = :id');
            $database->bind(':quantity', $quantities[$i]);
            $database->bind(':id', $product_ids[$i]);
            $database->execute();
        }

        $database->commit();
        echo json_encode(['success' => true, 'message' => 'Bill added successfully!']);
    } catch (Exception $e) {
        $database->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error adding bill: ' . $e->getMessage()]);
    }
}
