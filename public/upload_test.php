<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$upload_dir = __DIR__ . '/../uploads/';

if (!is_dir($upload_dir)) {
    echo "<p style=\"color: red;\">Upload directory does not exist: " . htmlspecialchars($upload_dir) . "</p>";
    mkdir($upload_dir, 0777, true);
    echo "<p style=\"color: blue;\">Attempted to create directory. Please refresh and try again.</p>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['test_file']) && $_FILES['test_file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['test_file']['tmp_name'];
        $file_name = uniqid() . '_' . basename($_FILES['test_file']['name']);
        $destination = $upload_dir . $file_name;

        echo "<p>Attempting to move file from: " . htmlspecialchars($file_tmp_name) . " to: " . htmlspecialchars($destination) . "</p>";

        if (move_uploaded_file($file_tmp_name, $destination)) {
            echo "<p style=\"color: green;\">File uploaded successfully! Path: " . htmlspecialchars($destination) . "</p>";
        } else {
            echo "<p style=\"color: red;\">Failed to move uploaded file.</p>";
            echo "<p style=\"color: red;\">Error details: " . htmlspecialchars(error_get_last()['message']) . "</p>";
        }
    } elseif (isset($_FILES['test_file'])) {
        echo "<p style=\"color: red;\">File upload error: " . $_FILES['test_file']['error'] . "</p>";
        switch ($_FILES['test_file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                echo "<p style=\"color: red;\">The uploaded file exceeds the upload_max_filesize directive in php.ini.</p>";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                echo "<p style=\"color: red;\">The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.</p>";
                break;
            case UPLOAD_ERR_PARTIAL:
                echo "<p style=\"color: red;\">The uploaded file was only partially uploaded.</p>";
                break;
            case UPLOAD_ERR_NO_FILE:
                echo "<p style=\"color: red;\">No file was uploaded.</p>";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                echo "<p style=\"color: red;\">Missing a temporary folder.</p>";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                echo "<p style=\"color: red;\">Failed to write file to disk.</p>";
                break;
            case UPLOAD_ERR_EXTENSION:
                echo "<p style=\"color: red;\">A PHP extension stopped the file upload.</p>";
                break;
            default:
                echo "<p style=\"color: red;\">Unknown upload error.</p>";
                break;
        }
    }
} else {
    echo "<p>Please select a file to upload.</p>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Test</title>
</head>
<body>
    <h2>File Upload Test</h2>
    <form action="" method="POST" enctype="multipart/form-data">
        <input type="file" name="test_file" required>
        <button type="submit">Upload</button>
    </form>
</body>
</html>