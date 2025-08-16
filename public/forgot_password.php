<?php
session_start();
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php';

$database = new Database();

$message = '';
$step = 1; // 1: Email input, 2: Security question, 3: New password
$user_email = '';
$user_id = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['step'])) {
        $step = (int)$_POST['step'];
    }

    if ($step == 1) {
        $user_email = trim($_POST['email'] ?? '');
        if (empty($user_email)) {
            $message = "Please enter your email address.";
        } elseif (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
        } else {
            $database->query('SELECT id, email FROM users WHERE email = :email');
            $database->bind(':email', $user_email);
            $user = $database->single();

            if ($user) {
                $user_id = $user->id;
                $_SESSION['forgot_password_user_id'] = $user_id;
                $_SESSION['forgot_password_email'] = $user_email;
                $step = 2; // Move to security question step
            } else {
                $message = "Email not found.";
            }
        }
    } elseif ($step == 2) {
        $user_id = $_SESSION['forgot_password_user_id'] ?? null;
        $user_email = $_SESSION['forgot_password_email'] ?? '';
        $security_answer = trim($_POST['security_answer'] ?? '');

        if (!$user_id || empty($user_email)) {
            $message = "Session expired or invalid request. Please start over.";
            $step = 1;
        } elseif (empty($security_answer)) {
            $message = "Please answer the security question.";
        } else {
            $database->query('SELECT security_answer FROM users WHERE id = :id');
            $database->bind(':id', $user_id);
            $user = $database->single();

            if ($user && strtolower($user->security_answer) === strtolower($security_answer)) {
                $step = 3; // Move to new password step
            } else {
                $message = "Incorrect security answer.";
            }
        }
    } elseif ($step == 3) {
        $user_id = $_SESSION['forgot_password_user_id'] ?? null;
        $user_email = $_SESSION['forgot_password_email'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_new_password = $_POST['confirm_new_password'] ?? '';

        if (!$user_id || empty($user_email)) {
            $message = "Session expired or invalid request. Please start over.";
            $step = 1;
        } elseif (empty($new_password) || empty($confirm_new_password)) {
            $message = "Please enter and confirm your new password.";
        } elseif ($new_password !== $confirm_new_password) {
            $message = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $message = "New password must be at least 8 characters long.";
        } elseif (!preg_match("/[A-Z]/", $new_password)) {
            $message = "New password must contain at least one uppercase letter.";
        } elseif (!preg_match("/[0-9]/", $new_password)) {
            $message = "New password must contain at least one number.";
        } elseif (!preg_match("/[^a-zA-Z0-9]/", $new_password)) {
            $message = "New password must contain at least one special character.";
        } else {
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            $database->query('UPDATE users SET password = :password WHERE id = :id');
            $database->bind(':password', $hashed_new_password);
            $database->bind(':id', $user_id);

            if ($database->execute()) {
                $message = "Your password has been reset successfully! You can now log in.";
                session_unset();
                session_destroy();
                header('Location: index.php');
                exit();
            } else {
                $message = "Error resetting password. Please try again.";
            }
        }
    }
}

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
    <title>Forgot Password - CRM App</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to right, orange 50%, black 50%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .forgot-password-container {
            background-color: rgba(0, 0, 0, 0.7); /* Transparent black background */
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .forgot-password-container h2 {
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
    <div class="forgot-password-container">
        <h2>Forgot Password</h2>
        <?php if (!empty($message)): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <form action="" method="POST">
                <input type="hidden" name="step" value="1">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($user_email); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Next</button>
            </form>
        <?php elseif ($step == 2): ?>
            <form action="" method="POST">
                <input type="hidden" name="step" value="2">
                <div class="form-group">
                    <label for="security_answer">Security Question: What is your best place?</label>
                    <input type="text" class="form-control" id="security_answer" name="security_answer" placeholder="Your answer" required>
                </div>
                <button type="submit" class="btn btn-primary">Next</button>
            </form>
        <?php elseif ($step == 3): ?>
            <form action="" method="POST">
                <input type="hidden" name="step" value="3">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Enter new password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_new_password">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" placeholder="Confirm new password" required>
                </div>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </form>
        <?php endif; ?>

        <div class="links">
            <a href="index.php">Back to Login</a>
        </div>
    </div>
</body>
</html>