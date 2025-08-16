<?php
session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php';

$database = new Database();

$purchase_id = $_GET['purchase_id'] ?? 0;

$items = [];
if ($purchase_id > 0) {
    $database->query('SELECT 
                        poi.product_id, 
                        poi.quantity, 
                        poi.purchase_price, 
                        poi.cgst, 
                        poi.sgst, 
                        poi.subtotal, 
                        p.product_name, 
                        p.product_category, 
                        p.unit, 
                        p.available_stock 
                      FROM purchase_order_items poi
                      JOIN products p ON poi.product_id = p.id
                      WHERE poi.purchase_order_id = :purchase_id');
    $database->bind(':purchase_id', $purchase_id);
    $items = $database->resultSet();
}

header('Content-Type: application/json');
echo json_encode($items);
exit();
?>