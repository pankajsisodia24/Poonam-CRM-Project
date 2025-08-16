<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php';

$database = new Database();

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile']);
    $security_answer = trim($_POST['security_answer']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Input validation
    if (empty($username) || empty($email) || empty($mobile) || empty($security_answer) || empty($password) || empty($confirm_password)) {
        $message = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long.";
    } elseif (!preg_match("/[A-Z]/", $password)) {
        $message = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match("/[0-9]/", $password)) {
        $message = "Password must contain at least one number.";
    } elseif (!preg_match("/[^a-zA-Z0-9]/", $password)) {
        $message = "Password must contain at least one special character.";
    } else {
        // Check if username or email already exists
        $database->query('SELECT * FROM users WHERE username = :username OR email = :email');
        $database->bind(':username', $username);
        $database->bind(':email', $email);
        $database->execute();

        if ($database->rowCount() > 0) {
            $message = "Username or Email already exists.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user into database
            $database->query('INSERT INTO users (username, email, mobile, security_answer, password) VALUES (:username, :email, :mobile, :security_answer, :password)');
            $database->bind(':username', $username);
            $database->bind(':email', $email);
            $database->bind(':mobile', $mobile);
            $database->bind(':security_answer', $security_answer);
            $database->bind(':password', $hashed_password);

            if ($database->execute()) {
                // Log in the user immediately after registration
                $_SESSION['user_id'] = $database->lastInsertId(); // Assuming lastInsertId() gets the new user's ID
                $_SESSION['username'] = $username;
                $message = "Registration successful! Please set your 4-digit PIN.";
                // Redirect to user_profile.php to set PIN
                header('Location: user_profile.php');
                exit();
            } else {
                $message = "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CRM App</title>
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
        .register-container {
            background-color: rgba(0, 0, 0, 0.7); /* Transparent black background */
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        .register-container h2 {
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
        .password-requirements {
            color: white;
            text-align: left;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .password-requirements li {
            list-style-type: none;
            margin-left: -20px;
        }
        .message {
            color: red;
            font-weight: bold;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Register</h2>
        <?php if (!empty($message)): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        <form action="" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Choose a username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="mobile">Mobile No.</label>
                <input type="text" class="form-control" id="mobile" name="mobile" placeholder="Enter your mobile number" value="<?php echo htmlspecialchars($mobile ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="security_question">Security Question: What is your best place?</label>
                <input type="text" class="form-control" id="security_question" name="security_answer" placeholder="Your answer" value="<?php echo htmlspecialchars($security_answer ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                <ul class="password-requirements">
                    <li>Min 8 characters</li>
                    <li>At least 1 uppercase letter</li>
                    <li>At least 1 number</li>
                    <li>At least 1 special character</li>
                </ul>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
            </div>
            <button type="submit" class="btn btn-primary">Register</button>
        </form>
        <div class="links">
            <a href="index.php">Back to Login</a>
        </div>
    </div>
</body>
</html>