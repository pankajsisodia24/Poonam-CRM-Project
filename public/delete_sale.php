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
$sale_id = $_GET['id'] ?? null;

if (!$sale_id) {
    header("Location: sales_management.php");
    exit;
}

// Delete associated sale items first
$database->query('DELETE FROM sale_items WHERE sale_id = :sale_id');
$database->bind(':sale_id', $sale_id);
$database->execute();

// Then delete the sale record
$database->query('DELETE FROM sales WHERE id = :id');
$database->bind(':id', $sale_id);

if ($database->execute()) {
    $message = "Sale deleted successfully!";
} else {
    $message = "Error deleting sale.";
}

header("Location: sales_management.php?message=" . urlencode($message));
exit();

?>