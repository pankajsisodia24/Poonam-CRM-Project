CREATE TABLE bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(255) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    payment_status VARCHAR(50) NOT NULL,
    payment_type VARCHAR(50) NOT NULL,
    other_payment_type VARCHAR(255),
    eway_bill_no VARCHAR(255),
    net_amount DECIMAL(10, 2) NOT NULL,
    total_discount DECIMAL(10, 2) NOT NULL,
    total_cgst DECIMAL(10, 2) NOT NULL,
    total_sgst DECIMAL(10, 2) NOT NULL,
    advance_amount DECIMAL(10, 2) NOT NULL,
    pending_amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE bill_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    discount DECIMAL(5, 2) NOT NULL,
    cgst DECIMAL(5, 2) NOT NULL,
    sgst DECIMAL(5, 2) NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES bills(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);
