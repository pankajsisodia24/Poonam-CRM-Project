<?php
// Include database configuration
require_once __DIR__ . '/../app/config.php';

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $quotation_id = $_GET['id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Delete associated quotation items first
        $stmt_items = $conn->prepare("DELETE FROM quotation_items WHERE quotation_id = ?");
        $stmt_items->bind_param("i", $quotation_id);
        $stmt_items->execute();
        $stmt_items->close();

        // Delete the quotation
        $stmt_quotation = $conn->prepare("DELETE FROM quotations WHERE id = ?");
        $stmt_quotation->bind_param("i", $quotation_id);
        $stmt_quotation->execute();
        $stmt_quotation->close();

        // Commit transaction
        $conn->commit();
        header("Location: index.php?page=quotation_management&status=deleted");
        exit();

    } catch (mysqli_sql_exception $exception) {
        // Rollback transaction on error
        $conn->rollback();
        header("Location: index.php?page=quotation_management&status=error&message=" . urlencode($exception->getMessage()));
        exit();
    }
} else {
    // Invalid action or missing ID
    header("Location: index.php?page=quotation_management&status=error&message=" . urlencode("Invalid action or missing ID."));
    exit();
}

$conn->close();
?>