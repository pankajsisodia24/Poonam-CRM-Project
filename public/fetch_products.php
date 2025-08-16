<?php
ini_set('display_errors', 0); // Disable display errors for JSON output
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // Still log errors, but don't display them

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    $database = new Database();

    $product_id = $_GET['id'] ?? null;

    // CRITICAL: Ensure these columns are ONLY those present in the 'products' table.
    // Based on product_management.php, these are:
    // id, product_name, selling_price, product_model_no, product_category, unit
    $select_columns = "id, product_name, selling_price, product_model_no, product_category, unit";

    if ($product_id) {
        $database->query("SELECT {$select_columns} FROM products WHERE id = :id");
        $database->bind(':id', $product_id);
        $product = $database->single();
        $response = ['success' => true, 'data' => $product];
    } else {
        $database->query("SELECT {$select_columns} FROM products ORDER BY product_name ASC");
        $products = $database->resultSet();
        $response = ['success' => true, 'data' => $products];
    }
} catch (Exception $e) {
    // Catch any exceptions, including PDOException from Database constructor
    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    // Log the error for debugging, but don't display sensitive info to user
    error_log('Fetch Products Error: ' . $e->getMessage());
}

echo json_encode($response);
exit(); // Ensure nothing else is printed
?>