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
$search_name = $_GET['search_name'] ?? '';
$search_email = $_GET['search_email'] ?? '';
$search_mobile = $_GET['search_mobile'] ?? '';

$where_clauses = [];
$bind_params = [];

if (!empty($search_name)) {
    $where_clauses[] = "name LIKE :search_name";
    $bind_params[':search_name'] = '%' . $search_name . '%';
}
if (!empty($search_email)) {
    $where_clauses[] = "email LIKE :search_email";
    $bind_params[':search_email'] = '%' . $search_email . '%';
}
if (!empty($search_mobile)) {
    $where_clauses[] = "mobile_no LIKE :search_mobile";
    $bind_params[':search_mobile'] = '%' . $search_mobile . '%';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Fetch customers
$database->query("SELECT id, name, email, mobile_no, gst_no, created_at FROM customers" . $where_sql . " ORDER BY created_at DESC");
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$customers = $database->resultSet();

// Export to Excel
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="customers_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, array('ID', 'Name', 'Email', 'Mobile No.', 'GST No.', 'Created At'));

foreach ($customers as $customer) {
    fputcsv($output, (array) $customer);
}

fclose($output);
exit();

?>