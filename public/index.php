<?php
require_once __DIR__ . '/../app/config.php';

// Basic routing for now
$request_page = $_GET['page'] ?? '';

if ($request_page == '' || $request_page == 'login') {
    include __DIR__ . '/login.php';
} elseif ($request_page == 'register') {
    include __DIR__ . '/register.php';
} elseif ($request_page == 'forgot_password') {
    include __DIR__ . '/forgot_password.php';
} elseif ($request_page == 'dashboard') {
    include __DIR__ . '/dashboard.php';
} elseif ($request_page == 'company_profile') {
    include __DIR__ . '/company_profile.php';
} elseif ($request_page == 'product_management') {
    include __DIR__ . '/product_management.php';
} elseif ($request_page == 'add_product') {
    include __DIR__ . '/add_product.php';
} elseif ($request_page == 'edit_product') {
    include __DIR__ . '/edit_product.php';
} elseif ($request_page == 'delete_product') {
    include __DIR__ . '/delete_product.php';
} elseif ($request_page == 'fetch_product_details') {
    include __DIR__ . '/fetch_product_details.php';
} elseif ($request_page == 'export_products_excel') {
    include __DIR__ . '/export_products_excel.php';
} elseif ($request_page == 'user_profile') {
    include __DIR__ . '/user_profile.php';
} elseif ($request_page == 'quotation_management') {
    include __DIR__ . '/quotation_management.php';
} elseif ($request_page == 'quotation_view') {
    include __DIR__ . '/quotation_view.php';
} elseif ($request_page == 'quotation_edit') {
    include __DIR__ . '/quotation_edit.php';
} elseif ($request_page == 'quotation_process') {
    include __DIR__ . '/quotation_process.php';
} elseif ($request_page == 'logout') {
    include __DIR__ . '/logout.php';
} else {
    // 404 Not Found
    http_response_code(404);
    echo "<h1>404 Not Found</h1>";
}
?>