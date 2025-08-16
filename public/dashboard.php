<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if(!isset($_SESSION['user_id'])){
    header("location: index.php");
    exit;
}

// Include necessary files
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php';

$database = new Database();

// Fetch company profile for header/navigation
$company_name = "Your Company Name"; // Default
$company_logo = ""; // Default

$database->query('SELECT company_name, company_logo FROM company_profile LIMIT 1');
$company_profile = $database->single();

if($company_profile) {
    $company_name = htmlspecialchars($company_profile->company_name);
    $company_logo = htmlspecialchars($company_profile->company_logo);
}

$total_expenses_amount = $total_expenses_data->total_expenses_amount ?? 0;

// Date filtering logic
$filter_type = $_GET['filter_type'] ?? 'today';
$custom_start_date = $_GET['custom_start_date'] ?? '';
$custom_end_date = $_GET['custom_end_date'] ?? '';

$start_date = null;
$end_date = null;

switch ($filter_type) {
    case 'today':
        $start_date = date('Y-m-d 00:00:00');
        $end_date = date('Y-m-d 23:59:59');
        break;
    case 'yesterday':
        $start_date = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $end_date = date('Y-m-d 23:59:59', strtotime('-1 day'));
        break;
    case 'this_week':
        $start_date = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $end_date = date('Y-m-d 23:59:59', strtotime('sunday this week'));
        break;
    case 'this_month':
        $start_date = date('Y-m-01 00:00:00');
        $end_date = date('Y-m-t 23:59:59');
        break;
    case 'previous_month':
        $start_date = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $end_date = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        break;
    case 'custom_date':
        if (!empty($custom_start_date)) {
            $start_date = $custom_start_date . ' 00:00:00';
        }
        if (!empty($custom_end_date)) {
            $end_date = $custom_end_date . ' 23:59:59';
        }
        break;
    case 'all_time':
    default:
        // No date filter
        break;
}

// Helper function to apply date filters to queries
function applyDateFilter($query, $start_date, $end_date, $created_at_column = 'created_at') {
    $where_clause = '';
    $bind_params = [];

    if ($start_date && $end_date) {
        $where_clause = " WHERE {$created_at_column} BETWEEN :start_date AND :end_date";
        $bind_params[':start_date'] = $start_date;
        $bind_params[':end_date'] = $end_date;
    } elseif ($start_date) {
        $where_clause = " WHERE {$created_at_column} >= :start_date";
        $bind_params[':start_date'] = $start_date;
    } elseif ($end_date) {
        $where_clause = " WHERE {$created_at_column} <= :end_date";
        $bind_params[':end_date'] = $end_date;
    }

    return ['query' => $query . $where_clause, 'params' => $bind_params];
}

// Apply date filters to all data fetches

// Users
$user_query_data = applyDateFilter('SELECT COUNT(*) as total_users FROM users', $start_date, $end_date);
$database->query($user_query_data['query']);
foreach ($user_query_data['params'] as $key => $value) {
    $database->bind($key, $value);
}
$total_users_data = $database->single();
$total_users = $total_users_data->total_users ?? 0;
$active_users = $total_users; // Placeholder for now

// Products
$product_query_data = applyDateFilter('SELECT COUNT(*) as total_products FROM products', $start_date, $end_date);
$database->query($product_query_data['query']);
foreach ($product_query_data['params'] as $key => $value) {
    $database->bind($key, $value);
}
$total_products_data = $database->single();
$total_products = $total_products_data->total_products ?? 0;

$category_query_data = applyDateFilter('SELECT COUNT(DISTINCT product_category) as total_categories FROM products', $start_date, $end_date);
$database->query($category_query_data['query']);
foreach ($category_query_data['params'] as $key => $value) {
    $database->bind($key, $value);
}
$total_categories_data = $database->single();
$total_categories = $total_categories_data->total_categories ?? 0;

// Customers
$customer_query_data = applyDateFilter('SELECT COUNT(*) as total_customers FROM customers', $start_date, $end_date);
$database->query($customer_query_data['query']);
foreach ($customer_query_data['params'] as $key => $value) {
    $database->bind($key, $value);
}
$total_customers_data = $database->single();
$total_customers = $total_customers_data->total_customers ?? 0;

// Purchases
$purchase_query_data = applyDateFilter('SELECT COUNT(*) as total_purchases, SUM(total_amount) as total_purchase_amount FROM purchase_orders', $start_date, $end_date, 'invoice_date');
$database->query($purchase_query_data['query']);
foreach ($purchase_query_data['params'] as $key => $value) {
    $database->bind($key, $value);
}
$total_purchases_data = $database->single();
$total_purchases = $total_purchases_data->total_purchases ?? 0;
$total_purchase_amount = $total_purchases_data->total_purchase_amount ?? 0;

// Suppliers
$supplier_query_data = applyDateFilter('SELECT COUNT(*) as total_suppliers FROM suppliers', $start_date, $end_date);
$database->query($supplier_query_data['query']);
foreach ($supplier_query_data['params'] as $key => $value) {
    $database->bind($key, $value);
}
$total_suppliers_data = $database->single();
$total_suppliers = $total_suppliers_data->total_suppliers ?? 0;

// Expenses
$expense_query_data = applyDateFilter('SELECT COUNT(*) as total_expenses, SUM(total_amount) as total_expenses_amount FROM expenses', $start_date, $end_date, 'invoice_date');
$database->query($expense_query_data['query']);
foreach ($expense_query_data['params'] as $key => $value) {
    $database->bind($key, $value);
}
$total_expenses_data = $database->single();
$total_expenses = $total_expenses_data->total_expenses ?? 0;
$total_expenses_amount = $total_expenses_data->total_expenses_amount ?? 0;

// Sales (Bills)
$sales_query_data = applyDateFilter('SELECT SUM(net_amount + total_cgst + total_sgst - total_discount) as total_sales, SUM(CASE WHEN payment_status = \'Due\' THEN pending_amount ELSE 0 END) as total_pending_amount FROM bills', $start_date, $end_date, 'invoice_date');
$database->query($sales_query_data['query']);
foreach ($sales_query_data['params'] as $key => $value) {
    $database->bind($key, $value);
}
$sales_data = $database->single();
$total_sales = $sales_data->total_sales ?? 0;
$total_pending_amount = $sales_data->total_pending_amount ?? 0;

// Calculate Profit/Loss
$profit_loss = $total_sales - $total_expenses_amount;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CRM App</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .gadget-section {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .gadget-title {
            color: white;
            font-size: 1.5em;
            margin-bottom: 15px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.3);
            padding-bottom: 5px;
        }
        .gadget-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .gadget-box {
            background-color: rgba(0, 0, 0, 0.6); /* Dark transparent */
            border-radius: 10px;
            padding: 20px;
            color: white;
            text-align: center;
            width: 220px; /* Fixed width for consistent alignment */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        .gadget-box h4 {
            margin-top: 0;
            font-size: 1.2em;
            color: #A7D129; /* Green grape color */
        }
        .gadget-box p {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0 0;
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
            <?php if (!empty($company_logo)): ?>
                <img src="../<?php echo $company_logo; ?>" alt="Company Logo" class="logo">
            <?php endif; ?>
            <span><?php echo $company_name; ?></span>
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
            <h1>Welcome to your Dashboard, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <p>This is where your main dashboard content will go.</p>

            <form class="form-inline mb-4" method="GET" action="dashboard.php">
                <div class="form-group mr-3">
                    <label for="filter_type" class="mr-2 text-white">Filter by Date:</label>
                    <select class="form-control" id="filter_type" name="filter_type">
                        <option value="all_time" <?php echo ($filter_type == 'all_time') ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?php echo ($filter_type == 'today') ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo ($filter_type == 'yesterday') ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="this_week" <?php echo ($filter_type == 'this_week') ? 'selected' : ''; ?>>This Week</option>
                        <option value="this_month" <?php echo ($filter_type == 'this_month') ? 'selected' : ''; ?>>This Month</option>
                        <option value="previous_month" <?php echo ($filter_type == 'previous_month') ? 'selected' : ''; ?>>Previous Month</option>
                        <option value="custom_date" <?php echo ($filter_type == 'custom_date') ? 'selected' : ''; ?>>Custom Date Range</option>
                    </select>
                </div>
                <div class="form-group mr-3" id="custom_date_range" style="display: <?php echo ($filter_type == 'custom_date') ? 'block' : 'none'; ?>;">
                    <label for="custom_start_date" class="mr-2 text-white">From:</label>
                    <input type="date" class="form-control mr-2" id="custom_start_date" name="custom_start_date" value="<?php echo htmlspecialchars($custom_start_date); ?>">
                    <label for="custom_end_date" class="mr-2 text-white">To:</label>
                    <input type="date" class="form-control" id="custom_end_date" name="custom_end_date" value="<?php echo htmlspecialchars($custom_end_date); ?>">
                </div>
                <button type="submit" class="btn btn-primary">Apply Filter</button>
            </form>

            <!-- Dashboard content will be added here -->
            <div class="gadget-section">
                <h3 class="gadget-title">User Section</h3>
                <div class="gadget-container">
                    <div class="gadget-box">
                        <h4>Active Users</h4>
                        <p><?php echo $active_users; ?></p>
                    </div>
                    <div class="gadget-box">
                        <h4>Total Users</h4>
                        <p><?php echo $total_users; ?></p>
                    </div>
                </div>
            </div>

            <div class="gadget-section">
                <h3 class="gadget-title">Product Section</h3>
                <div class="gadget-container">
                    <div class="gadget-box">
                        <h4>Total Items</h4>
                        <p><?php echo $total_products; ?></p>
                    </div>
                    <div class="gadget-box">
                        <h4>Total Categories</h4>
                        <p><?php echo $total_categories; ?></p>
                    </div>
                </div>
            </div>

            <div class="gadget-section">
                <h3 class="gadget-title">Customer Details</h3>
                <div class="gadget-container">
                    <div class="gadget-box">
                        <h4>Total Registered Customers</h4>
                        <p><?php echo $total_customers; ?></p>
                    </div>
                </div>
            </div>

            <div class="gadget-section">
                <h3 class="gadget-title">Purchase Details</h3>
                <div class="gadget-container">
                    <div class="gadget-box">
                        <h4>Total Purchases</h4>
                        <p><?php echo $total_purchases; ?></p>
                    </div>
                    <div class="gadget-box">
                        <h4>Total Purchase Amount</h4>
                        <p>₹<?php echo htmlspecialchars(number_format($total_purchase_amount)); ?></p>
                    </div>
                </div>
            </div>

            <div class="gadget-section">
                <h3 class="gadget-title">Supplier Details</h3>
                <div class="gadget-container">
                    <div class="gadget-box">
                        <h4>Total Suppliers</h4>
                        <p><?php echo $total_suppliers; ?></p>
                    </div>
                </div>
            </div>

            <div class="gadget-section">
                <h3 class="gadget-title">Expenses Details</h3>
                <div class="gadget-container">
                    <div class="gadget-box">
                        <h4>Total Expenses</h4>
                        <p><?php echo $total_expenses; ?></p>
                    </div>
                    <div class="gadget-box">
                        <h4>Total Expenses Amount</h4>
                        <p>₹<?php echo htmlspecialchars(number_format($total_expenses_amount, 2)); ?></p>
                    </div>
                </div>
            </div>

            <div class="gadget-section">
                <h3 class="gadget-title">Bill Summary</h3>
                <div class="gadget-container">
                    <div class="gadget-box">
                        <h4>Total Sales</h4>
                        <p>₹<?php echo htmlspecialchars(number_format($total_sales, 2)); ?></p>
                    </div>
                    <div class="gadget-box">
                        <h4>Sundry Debtors (Pending Amount)</h4>
                        <p>₹<?php echo htmlspecialchars(number_format($total_pending_amount, 2)); ?></p>
                    </div>
                </div>
            </div>

            <div class="gadget-section">
                <h3 class="gadget-title">Profit and Loss</h3>
                <div class="gadget-container">
                    <div class="gadget-box" style="width: 400px; height: 300px;">
                        <canvas id="profitLossChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> CRM App. All rights reserved.</p>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#filter_type').change(function() {
                if ($(this).val() === 'custom_date') {
                    $('#custom_date_range').show();
                } else {
                    $('#custom_date_range').hide();
                }
            });

            // Profit and Loss Chart
            var profitLoss = <?php echo json_encode($profit_loss); ?>;
            var chartColor = profitLoss >= 0 ? '#A7D129' : '#FF0000'; // Green for profit, Red for loss

            var ctx = document.getElementById('profitLossChart').getContext('2d');
            var profitLossChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Profit/Loss'],
                    datasets: [{
                        label: 'Amount',
                        data: [profitLoss],
                        backgroundColor: [chartColor],
                        borderColor: [chartColor],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: 'white' // Y-axis labels color
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.2)' // Y-axis grid lines color
                            }
                        },
                        x: {
                            ticks: {
                                color: 'white' // X-axis labels color
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.2)' // X-axis grid lines color
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: 'white' // Legend text color
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>