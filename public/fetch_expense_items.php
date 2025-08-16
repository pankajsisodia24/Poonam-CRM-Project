<?php
session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php';

$database = new Database();

$expense_id = $_GET['expense_id'] ?? 0;

$items = [];
if ($expense_id > 0) {
    $database->query('SELECT expense_name, expense_category, amount FROM expense_items WHERE expense_id = :expense_id');
    $database->bind(':expense_id', $expense_id);
    $items = $database->resultSet();
}

header('Content-Type: application/json');
echo json_encode($items);
exit();
?>