<?php
session_start();
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php';
require_once __DIR__ . '/../app/GoogleAuthenticator.php';

$database = new Database();
$ga = new PHPGangsta_GoogleAuthenticator();

$message = '';

if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['2fa_user_id'];
    $code = $_POST['code'];

    // --- Start Debug Logging ---
    $log_file = __DIR__ . '/../2fa_debug.log';
    $log_message = "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $log_message .= "User ID: " . $user_id . "\n";
    $log_message .= "Submitted Code: " . $code . "\n";
    // --- End Debug Logging ---

    $database->query('SELECT id, username, two_factor_secret, profile_image FROM users WHERE id = :id');
    $database->bind(':id', $user_id);
    $user = $database->single();

    // --- More Debug Logging ---
    if ($user) {
        $log_message .= "User Secret: " . $user->two_factor_secret . "\n";
    } else {
        $log_message .= "User not found in database.\n";
    }
    // --- End More Debug Logging ---

    if ($user && $ga->verifyCode($user->two_factor_secret, $code, 2)) { // 2 = 2*30sec clock tolerance
        // --- Success Logging ---
        $log_message .= "Verification: SUCCESS\n\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
        // --- End Success Logging ---

        // 2FA successful, complete the login process
        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['profile_image'] = $user->profile_image ?? '';
        unset($_SESSION['2fa_user_id']); // Clear the 2FA session variable

        // Record 2FA success activity
        $database->query('INSERT INTO user_activity_log (user_id, username, activity_type) VALUES (:user_id, :username, \'2fa_success\')');
        $database->bind(':user_id', $user->id);
        $database->bind(':username', $user->username);
        $database->execute();

        header('Location: dashboard.php');
        exit();
    } else {
        // --- Failure Logging ---
        $log_message .= "Verification: FAILED\n\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
        // --- End Failure Logging ---

        // Record 2FA failure activity
        $database->query('INSERT INTO user_activity_log (user_id, username, activity_type) VALUES (:user_id, :username, \'2fa_failure\')');
        $database->bind(':user_id', $user_id);
        $database->bind(':username', $_SESSION['username'] ?? 'unknown'); // Attempt to log username if available
        $database->execute();

        $message = "Invalid 2FA code. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA Verification - CRM App</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to bottom, #FF0000, #800080, #A7D129);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .verify-container {
            background-color: rgba(0, 0, 0, 0.7);
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .verify-container h2 {
            color: white;
            margin-bottom: 30px;
        }
        .form-group label {
            color: white;
            font-weight: bold;
        }
        .form-control {
            background-color: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
        }
        .form-control:focus {
            background-color: rgba(0, 0, 0, 0.6);
            border-color: #A7D129;
            box-shadow: none;
            color: white;
        }
        .btn-primary {
            background-color: #A7D129;
            border-color: #A7D129;
            color: black;
        }
        .btn-primary:hover {
            background-color: #8CBF20;
            border-color: #8CBF20;
        }
        .message {
            color: red;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <h2>Two-Factor Authentication</h2>
        <p style="color: white;">Please enter the code from your authenticator app.</p>
        <?php if (!empty($message)): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        <form action="" method="POST">
            <div class="form-group">
                <label for="code">Authentication Code</label>
                <input type="text" class="form-control" id="code" name="code" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Verify</button>
        </form>
    </div>
</body>
</html>
