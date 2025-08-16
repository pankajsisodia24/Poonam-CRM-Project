ALTER TABLE quotation_items
CHANGE COLUMN unit_price selling_price DECIMAL(10, 2) NOT NULL;

ALTER TABLE quotation_items
ADD COLUMN product_id INT AFTER quotation_id,
ADD COLUMN model_no VARCHAR(255) AFTER product_id,
ADD COLUMN category VARCHAR(255) AFTER model_no,
ADD COLUMN unit VARCHAR(50) AFTER category,
ADD COLUMN discount DECIMAL(5, 2) NOT NULL DEFAULT 0.00 AFTER selling_price,
ADD COLUMN cgst DECIMAL(5, 2) NOT NULL DEFAULT 0.00 AFTER discount,
ADD COLUMN sgst DECIMAL(5, 2) NOT NULL DEFAULT 0.00 AFTER cgst;

ALTER TABLE quotation_items
CHANGE COLUMN item_name product_name VARCHAR(255) NOT NULL;