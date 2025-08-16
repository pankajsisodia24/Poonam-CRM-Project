<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if(!isset($_SESSION['user_id'])){
    header("location: index.php");
    exit;
}

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php';

// Include PhpSpreadsheet autoloader
require_once __DIR__ . '/../vendor/autoload.php'; // Assuming Composer autoloader

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$database = new Database();

// Fetch all products
$database->query('SELECT * FROM products');
$products = $database->resultSet();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers
$sheet->setCellValue('A1', 'Product Name');
$sheet->setCellValue('B1', 'Model No.');
$sheet->setCellValue('C1', 'Category');
$sheet->setCellValue('D1', 'Warranty');
$sheet->setCellValue('E1', 'Unit');
$sheet->setCellValue('F1', 'Selling Price');
$sheet->setCellValue('G1', 'Purchase Price');
$sheet->setCellValue('H1', 'CGST');
$sheet->setCellValue('I1', 'SGST');
$sheet->setCellValue('J1', 'Available Stock');
$sheet->setCellValue('K1', 'Product Summary');

// Populate data
$row = 2;
foreach ($products as $product) {
    $sheet->setCellValue('A' . $row, $product->product_name);
    $sheet->setCellValue('B' . $row, $product->product_model_no);
    $sheet->setCellValue('C' . $row, $product->product_category);
    $sheet->setCellValue('D' . $row, $product->product_warranty);
    $sheet->setCellValue('E' . $row, $product->unit);
    $sheet->setCellValue('F' . $row, $product->selling_price);
    $sheet->setCellValue('G' . $row, $product->purchase_price);
    $sheet->setCellValue('H' . $row, $product->cgst);
    $sheet->setCellValue('I' . $row, $product->sgst);
    $sheet->setCellValue('J' . $row, $product->available_stock);
    $sheet->setCellValue('K' . $row, $product->product_summary);
    $row++;
}

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="products.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>