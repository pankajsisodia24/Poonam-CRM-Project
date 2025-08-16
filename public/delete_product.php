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

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $product_id = $_GET['id'];

    // Optional: Get product image path to delete the file from server
    $database->query('SELECT product_image FROM products WHERE id = :id');
    $database->bind(':id', $product_id);
    $product = $database->single();

    if ($product && !empty($product->product_image)) {
        $image_path = __DIR__ . '/../' . $product->product_image;
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }

    // Delete product from database
    $database->query('DELETE FROM products WHERE id = :id');
    $database->bind(':id', $product_id);

    if ($database->execute()) {
        $_SESSION['message'] = "Product deleted successfully!";
    } else {
        $_SESSION['message'] = "Error deleting product.";
    }
} else {
    $_SESSION['message'] = "Invalid product ID.";
}

header("location: index.php?page=product_management");
exit;
?>