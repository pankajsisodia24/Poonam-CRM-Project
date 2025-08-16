<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php';

$database = new Database();

if (isset($_GET['product_id'])) {
    $product_id = $_GET['product_id'];
    $database->query('SELECT * FROM products WHERE id = :id');
    $database->bind(':id', $product_id);
    $product = $database->single();
    echo json_encode($product);
}