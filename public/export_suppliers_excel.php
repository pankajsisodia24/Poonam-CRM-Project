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
$search_company_name = $_GET['search_company_name'] ?? '';
$search_email = $_GET['search_email'] ?? '';
$search_mobile = $_GET['search_mobile'] ?? '';

$where_clauses = [];
$bind_params = [];

if (!empty($search_company_name)) {
    $where_clauses[] = "company_name LIKE :search_company_name";
    $bind_params[':search_company_name'] = '%' . $search_company_name . '%';
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

// Fetch suppliers
$database->query("SELECT id, company_name, contact_person_name, email, mobile_no, gst_no, created_at FROM suppliers" . $where_sql . " ORDER BY created_at DESC");
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$suppliers = $database->resultSet();

// Export to Excel
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="suppliers_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, array('ID', 'Company Name', 'Contact Person Name', 'Email', 'Mobile No.', 'GST No.', 'Created At'));

foreach ($suppliers as $supplier) {
    fputcsv($output, (array) $supplier);
}

fclose($output);
exit();

?>