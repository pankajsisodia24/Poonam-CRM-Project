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
$search_pay_to = $_GET['search_pay_to'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_clauses = [];
$bind_params = [];

if (!empty($search_invoice_no)) {
    $where_clauses[] = "invoice_no LIKE :search_invoice_no";
    $bind_params[':search_invoice_no'] = '%' . $search_invoice_no . '%';
}
if (!empty($search_pay_to)) {
    $where_clauses[] = "pay_to LIKE :search_pay_to";
    $bind_params[':search_pay_to'] = '%' . $search_pay_to . '%';
}
if (!empty($start_date)) {
    $where_clauses[] = "invoice_date >= :start_date";
    $bind_params[':start_date'] = $start_date;
}
if (!empty($end_date)) {
    $where_clauses[] = "invoice_date <= :end_date";
    $bind_params[':end_date'] = $end_date;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Fetch expenses
$database->query("SELECT id, invoice_no, invoice_date, total_amount, payment_mode, payment_status, pay_to, created_at FROM expenses" . $where_sql . " ORDER BY invoice_date DESC");
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$expenses = $database->resultSet();

// Export to Excel
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="expenses_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, array('ID', 'Invoice No.', 'Invoice Date', 'Total Amount', 'Payment Mode', 'Payment Status', 'Pay To', 'Created At'), ',', '"');

foreach ($expenses as $expense) {
    fputcsv($output, (array) $expense, ',', '"');
}

fclose($output);
exit();

?>