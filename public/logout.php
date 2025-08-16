<?php
session_start();

require_once __DIR__ . '/../app/database.php';
$database = new Database();

// Record logout activity before destroying the session
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];

    $database->query('INSERT INTO user_activity_log (user_id, username, activity_type) VALUES (:user_id, :username, \'logout\')');
    $database->bind(':user_id', $user_id);
    $database->bind(':username', $username);
    $database->execute();
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session.
session_destroy();

// Redirect to login page
header("location: index.php");
exit;
?>