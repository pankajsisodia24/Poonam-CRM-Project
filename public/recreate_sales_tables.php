<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php';

$database = new Database();

$sql_sales = "CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(255) NOT NULL,
    customer_id INT NOT NULL,
    sale_date DATE NOT NULL,
    eway_bill_no VARCHAR(255),
    advance_amount DECIMAL(10, 2) DEFAULT 0.00,
    payment_status VARCHAR(50) NOT NULL,
    payment_mode VARCHAR(50),
    other_payment_mode_details VARCHAR(255),
    net_amount DECIMAL(10, 2) NOT NULL,
    total_discount DECIMAL(10, 2) NOT NULL,
    total_cgst DECIMAL(10, 2) NOT NULL,
    total_sgst DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    pending_amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
)";

$sql_sale_items = "CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    cgst_rate DECIMAL(5, 2) NOT NULL,
    sgst_rate DECIMAL(5, 2) NOT NULL,
    discount_rate DECIMAL(5, 2) NOT NULL,
    item_total_amount DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
)";

$database->query($sql_sales);
$database->execute();

$database->query($sql_sale_items);
$database->execute();

echo 'Sales tables created successfully.';
