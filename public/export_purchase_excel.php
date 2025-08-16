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

// Search and filter parameters
$search_invoice_no = $_GET['search_invoice_no'] ?? '';
$search_supplier = $_GET['search_supplier'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_clauses = [];
$bind_params = [];

if (!empty($search_invoice_no)) {
    $where_clauses[] = "po.invoice_no LIKE :search_invoice_no";
    $bind_params[':search_invoice_no'] = '%' . $search_invoice_no . '%';
}
if (!empty($search_supplier)) {
    $where_clauses[] = "s.company_name LIKE :search_supplier";
    $bind_params[':search_supplier'] = '%' . $search_supplier . '%';
}
if (!empty($start_date)) {
    $where_clauses[] = "po.invoice_date >= :start_date";
    $bind_params[':start_date'] = $start_date;
}
if (!empty($end_date)) {
    $where_clauses[] = "po.invoice_date <= :end_date";
    $bind_params[':end_date'] = $end_date;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Fetch purchase orders
$database->query("SELECT po.id, s.company_name, po.invoice_no, po.invoice_date, po.total_amount, po.invoice_file, po.created_at FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id" . $where_sql . " ORDER BY po.invoice_date DESC");
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$purchase_orders = $database->resultSet();

// Export to Excel
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="purchase_orders_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, array('ID', 'Supplier Company', 'Invoice No.', 'Invoice Date', 'Total Amount', 'Invoice File', 'Created At'));

foreach ($purchase_orders as $purchase) {
    fputcsv($output, (array) $purchase);
}

fclose($output);
exit();

?>