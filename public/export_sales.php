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

// Fetch sales data (with optional search filter)
$search_query = $_GET['search'] ?? '';
$sql = "SELECT s.invoice_no, c.name as customer_name, s.sale_date, s.eway_bill_no, s.advance_amount, s.payment_status, s.payment_mode, s.other_payment_mode_details, s.net_amount, s.total_discount, s.total_cgst, s.total_sgst, s.total_amount, s.pending_amount FROM sales s JOIN customers c ON s.customer_id = c.id";
$where_clauses = [];
$bind_params = [];

if (!empty($search_query)) {
    $where_clauses[] = "(s.invoice_no LIKE :search_query OR c.name LIKE :search_query)";
    $bind_params[':search_query'] = '%' . $search_query . '%';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY s.sale_date DESC";

$database->query($sql);
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$sales_data = $database->resultSet();

// Prepare CSV headers
$csv_headers = [
    'Invoice No.',
    'Customer Name',
    'Sale Date',
    'E-Way Bill No.',
    'Advance Amount',
    'Payment Status',
    'Payment Mode',
    'Other Payment Mode Details',
    'Net Amount',
    'Total Discount',
    'Total CGST',
    'Total SGST',
    'Total Amount',
    'Pending Amount'
];

// Output CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sales_export_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, $csv_headers);

if ($sales_data) {
    foreach ($sales_data as $row) {
        fputcsv($output, [
            $row->invoice_no,
            $row->customer_name,
            $row->sale_date,
            $row->eway_bill_no,
            $row->advance_amount,
            $row->payment_status,
            $row->payment_mode,
            $row->other_payment_mode_details,
            $row->net_amount,
            $row->total_discount,
            $row->total_cgst,
            $row->total_sgst,
            $row->total_amount,
            $row->pending_amount
        ]);
    }
}

fclose($output);
exit();

?>