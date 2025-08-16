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
$search_username = $_GET['search_username'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_clauses = [];
$bind_params = [];

if (!empty($search_username)) {
    $where_clauses[] = "username LIKE :search_username";
    $bind_params[':search_username'] = '%' . $search_username . '%';
}

if (!empty($start_date)) {
    $where_clauses[] = "activity_time >= :start_date";
    $bind_params[':start_date'] = $start_date . ' 00:00:00';
}

if (!empty($end_date)) {
    $where_clauses[] = "activity_time <= :end_date";
    $bind_params[':end_date'] = $end_date . ' 23:59:59';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Fetch user activity logs
$database->query("SELECT username, activity_type, activity_time FROM user_activity_log" . $where_sql . " ORDER BY activity_time DESC");
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$activity_logs = $database->resultSet();

// Export to Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="user_activity_log_' . date('Y-m-d') . '.xls"');

$output = fopen('php://output', 'w');
fputcsv($output, array('Username', 'Activity Type', 'Activity Time'));

foreach ($activity_logs as $log) {
    fputcsv($output, (array) $log);
}

fclose($output);
exit();

?>