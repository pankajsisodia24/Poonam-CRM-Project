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

// Fetch company profile for header/navigation
$company_name_header = "Your Company Name"; // Default
$company_logo_header = ""; // Default

$database->query('SELECT company_name, company_logo FROM company_profile LIMIT 1');
$header_company_profile = $database->single();

if($header_company_profile) {
    $company_name_header = htmlspecialchars($header_company_profile->company_name);
    $company_logo_header = htmlspecialchars($header_company_profile->company_logo);
}

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
$database->query("SELECT * FROM user_activity_log" . $where_sql . " ORDER BY activity_time DESC");
foreach ($bind_params as $key => $value) {
    $database->bind($key, $value);
}
$activity_logs = $database->resultSet();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Tracking - CRM App</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to bottom, #FF0000, #800080, #A7D129);
        }
        .header {
            background-color: #333;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header .logo {
            height: 40px;
            margin-right: 10px;
        }
        .header .company-info {
            display: flex;
            align-items: center;
        }
        .header .user-profile {
            display: flex;
            align-items: center;
        }
        .header .user-profile img {
            height: 30px;
            width: 30px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .navbar {
            background-color: #444;
            padding: 10px 20px;
        }
        .navbar a {
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            margin-right: 10px;
        }
        .navbar a:hover {
            background-color: #555;
            border-radius: 5px;
        }
        .wrapper {
            display: flex;
        }
        .side-navigation {
            width: 200px;
            background-color: #555;
            color: white;
            padding-top: 20px;
            min-height: calc(100vh - 110px); /* Adjust based on header/footer height */
        }
        .side-navigation a {
            display: block;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
        }
        .side-navigation a:hover {
            background-color: #666;
        }
        .content {
            flex-grow: 1;
            padding: 20px;
        }
        .footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 10px 0;
            position: relative;
            bottom: 0;
            width: 100%;
        }
        .table-container {
            background-color: rgba(0, 0, 0, 0.6);
            padding: 20px;
            border-radius: 10px;
            color: white;
        }
        .table-container h2 {
            color: white;
            margin-bottom: 20px;
        }
        .table-container .form-inline .form-control {
            background-color: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
        }
        .table-container .form-inline .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .table-container .form-inline .btn {
            background-color: #A7D129;
            border-color: #A7D129;
            color: black;
        }
        .table-container .form-inline .btn:hover {
            background-color: #8CBF20;
            border-color: #8CBF20;
        }
        .table-container .table {
            color: white;
        }
        .table-container .table thead th {
            border-bottom: 2px solid rgba(255, 255, 255, 0.5);
        }
        .table-container .table tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .table-container .table tbody tr:last-child {
            border-bottom: none;
        }
        .table-container .table tbody tr:hover {
            background-color: white; /* White background on hover */
            color: black; /* Black text on hover */
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <?php if (!empty($company_logo_header)): ?>
                <img src="../<?php echo $company_logo_header; ?>" alt="Company Logo" class="logo">
            <?php endif; ?>
            <span><?php echo $company_name_header; ?></span>
        </div>
        <div class="user-profile">
            <?php if (!empty($_SESSION['profile_image'])): ?>
                <img src="../<?php echo htmlspecialchars($_SESSION['profile_image']); ?>" alt="User Avatar">
            <?php else: ?>
                <img src="https://via.placeholder.com/30" alt="User Avatar">
            <?php endif; ?>
            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
    </div>

    <nav class="navbar">
        <a href="index.php?page=dashboard">Dashboard</a>
        <a href="index.php?page=company_profile">Company Profile</a>
        <a href="index.php?page=product_management">Product Management</a>
        <a href="index.php?page=user_profile">User Profile</a>
        <a href="index.php?page=logout">Logout</a>
    </nav>

    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="content">
            <h1>User Activity Log</h1>

            <div class="table-container">
                <form class="form-inline mb-4" method="GET" action="user_tracking.php">
                    <input type="text" class="form-control mr-sm-2" name="search_username" placeholder="Search by Username" value="<?php echo htmlspecialchars($search_username); ?>">
                    <input type="date" class="form-control mr-sm-2" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    <input type="date" class="form-control mr-sm-2" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    <button class="btn btn-primary my-2 my-sm-0" type="submit">Search</button>
                    <a href="export_user_activity.php?search_username=<?php echo urlencode($search_username); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-success ml-2">Download Excel</a>
                </form>

                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Activity Type</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($activity_logs)): ?>
                            <?php foreach ($activity_logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log->username); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($log->activity_type)); ?></td>
                                    <td><?php echo htmlspecialchars($log->activity_time); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3">No activity logs found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> CRM App. All rights reserved.</p>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>