<?php
session_start();
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php';

$database = new Database();

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'] ?? ''; // Password is now optional
    $pin = $_POST['pin'] ?? ''; // Get the PIN

    if (empty($username)) {
        $message = "Please enter your username.";
    } elseif (empty($password) && empty($pin)) {
        $message = "Please enter either your password or your 4-digit PIN.";
    } else {
        $database->query('SELECT id, username, password, pin_hash, profile_image FROM users WHERE username = :username');
        $database->bind(':username', $username);
        $row = $database->single();

        if ($row) {
            $authenticated = false;
            // Try password first if provided
            if (!empty($password) && password_verify($password, $row->password)) {
                $authenticated = true;
            } elseif (!empty($pin) && !empty($row->pin_hash) && password_verify($pin, $row->pin_hash)) {
                // If password not provided or incorrect, try PIN
                $authenticated = true;
            }

            if ($authenticated) {
                $_SESSION['user_id'] = $row->id;
                $_SESSION['username'] = $row->username;
                $_SESSION['profile_image'] = $row->profile_image ?? ''; // Set profile image in session

                // Record login activity
                $database->query('INSERT INTO user_activity_log (user_id, username, activity_type) VALUES (:user_id, :username, \'login\')');
                $database->bind(':user_id', $row->id);
                $database->bind(':username', $row->username);
                $database->execute();

                // 2FA removed as per user request
                // $_SESSION['2fa_user_id'] = $row->id; // Store user ID for 2FA verification
                // header('Location: 2fa_verify.php');
                // exit();
                header('Location: dashboard.php');
                exit();
            } else {
                $message = "Invalid username or password/PIN.";
                // Debugging: Log PIN verification details for failed attempts
                $log_file = sys_get_temp_dir() . '/login_debug.log';
                $log_message = "Timestamp: " . date('Y-m-d H:i:s') . "\n";
                $log_message .= "Entered PIN: " . ($pin ?? 'N/A') . "\n";
                $log_message .= "Stored PIN Hash: " . ($row->pin_hash ?? 'N/A') . "\n";
                $log_message .= "Password Verify Result: " . (password_verify($pin, $row->pin_hash) ? 'True' : 'False') . "\n\n";
                file_put_contents($log_file, $log_message, FILE_APPEND);
            }
        } else {
            $message = "Invalid username or password/PIN.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CRM App</title>
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
        .login-container {
            background-color: rgba(0, 0, 0, 0.7); /* Transparent black background */
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .login-container h2 {
            color: white;
            margin-bottom: 30px;
            font-weight: bold;
        }
        .form-group label {
            color: white;
            font-weight: bold;
            display: block;
            text-align: left;
            margin-bottom: 5px;
        }
        .form-control {
            background-color: rgba(0, 0, 0, 0.5); /* Transparent black input background */
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            font-weight: bold;
            padding: 10px 15px;
            border-radius: 5px;
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7); /* White placeholder with transparency */
            font-weight: bold;
        }
        .form-control:focus {
            background-color: rgba(0, 0, 0, 0.6);
            border-color: orange;
            box-shadow: none;
            color: white;
        }
        .btn-primary {
            background-color: orange;
            border-color: orange;
            font-weight: bold;
            padding: 10px 20px;
            border-radius: 5px;
            width: 100%;
            margin-top: 20px;
        }
        .btn-primary:hover {
            background-color: darkorange;
            border-color: darkorange;
        }
        .links {
            margin-top: 20px;
        }
        .links a {
            color: white;
            font-weight: bold;
            text-decoration: none;
            margin: 0 10px;
        }
        .links a:hover {
            text-decoration: underline;
        }
        .message {
            color: red;
            font-weight: bold;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <?php if (!empty($message)): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        <form action="" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password">
            </div>
            <div class="form-group">
                <label for="pin">4-Digit PIN (Optional)</label>
                <input type="password" class="form-control" id="pin" name="pin" placeholder="Enter your 4-digit PIN" maxlength="4" pattern="[0-9]{4}">
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
        <div class="links">
            <a href="index.php?page=register">Register</a>
            <a href="index.php?page=forgot_password">Forgot Password?</a>
        </div>
    </div>
</body>
</html>