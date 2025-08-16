<?php
// This file will contain the common layout for the CRM app.
// It expects variables like $page_title, $content, $company_name_header, $company_logo_header, $_SESSION['username'], $_SESSION['profile_image'] to be set.

// Ensure session is started if not already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in, if not then redirect to login page
if(!isset($_SESSION['user_id'])){
    header("location: index.php");
    exit;
}

// Include necessary files for database and config
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php'; // Assuming database.php exists and is used for fetching company profile

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'CRM App'; ?></title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to bottom, #FF0000, #800080, #A7D129); /* Consistent background */
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
        /* Styles for content within the main area */
        .table-container, .form-container {
            background-color: rgba(0, 0, 0, 0.6); /* Dark transparent */
            padding: 20px;
            border-radius: 10px;
            color: white;
            margin-bottom: 20px;
        }
        .table-container h2, .form-container h2 {
            color: white;
            margin-bottom: 20px;
        }
        .form-group label {
            color: white;
        }
        .form-control {
            background-color: rgba(0, 0, 0, 0.7) !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
            color: white !important;
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5) !important;
        }
        .btn-primary, .btn-success, .btn-info, .btn-danger {
            background-color: #A7D129 !important; /* Green grape color */
            border-color: #A7D129 !important;
            color: black !important;
        }
        .btn-primary:hover, .btn-success:hover, .btn-info:hover, .btn-danger:hover {
            background-color: #8CBF20 !important;
            border-color: #8CBF20 !important;
        }
        table {
            color: white;
        }
        table thead th {
            border-bottom: 2px solid rgba(255, 255, 255, 0.5) !important;
        }
        table tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2) !important;
        }
        table tbody tr:last-child {
            border-bottom: none !important;
        }
        table tbody tr:hover {
            background-color: white !important;
            color: black !important;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <?php if (!empty($company_logo_header)): ?>
                <img src="../<?php echo $company_logo_header; ?>" alt="Company Logo" class="logo">
            <?php else: ?>
                <!-- Placeholder for logo if not set -->
                <img src="https://via.placeholder.com/40x40?text=Logo" alt="Company Logo" class="logo">
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
        <a href="index.php?page=user_profile">User Profile</a>
        <a href="index.php?page=logout">Logout</a>
    </nav>

    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="content">
            <?php echo $content; // This is where the page-specific content will be injected ?>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> CRM App. All rights reserved.</p>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script> <!-- Changed to full jQuery for AJAX -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>