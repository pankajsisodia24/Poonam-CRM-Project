<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];

    if (empty($username)) {
        $message = "Please enter a username.";
    } else {
        $database = new Database();
        
        // Check if user exists
        $database->query('SELECT id FROM users WHERE username = :username');
        $database->bind(':username', $username);
        $user = $database->single();

        if ($user) {
            // User exists, disable 2FA
            $database->query('UPDATE users SET two_factor_secret = NULL WHERE username = :username');
            $database->bind(':username', $username);
            if ($database->execute()) {
                $message = "2-Factor Authentication has been successfully disabled for user: " . htmlspecialchars($username);
            } else {
                $message = "Error: Could not disable 2FA. Please check database permissions.";
            }
        } else {
            $message = "Error: User not found.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disable 2FA</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 500px;
            padding: 2rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center mb-4">Disable Two-Factor Authentication</h2>
        <p>Enter the username of the account for which you want to disable 2FA. This will allow the user to log in without an OTP code.</p>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <button type="submit" class="btn btn-danger btn-block">Disable 2FA</button>
        </form>
    </div>
</body>
</html>
