<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if(!isset($_SESSION['user_id'])){
    header("location: index.php");
    exit;
}

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/database.php';
$database = new Database();
$message = '';

$user_id = $_SESSION['user_id'];

// Fetch user data
$database->query('SELECT username, email, mobile, security_answer, profile_image FROM users WHERE id = :id');
$database->bind(':id', $user_id);
$user_data = $database->single();

if (!$user_data) {
    session_destroy();
    header("location: index.php");
    exit;
}



// Handle form submission for updating profile and 2FA
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_mobile = $_POST['mobile'] ?? '';
    $new_email = $_POST['email'] ?? '';
    $new_security_answer = $_POST['security_answer'] ?? '';
    $new_username = $_POST['username'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    $profile_image = $user_data->profile_image ?? '';
    $upload_dir = __DIR__ . '/../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
        $image_name = uniqid() . '_' . basename($_FILES['profile_image']['name']);
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $image_name)) {
            $profile_image = 'uploads/' . $image_name;
        }
    }

    $update_fields = [];
    $bind_params = [':id' => $user_id];

    if ($profile_image !== ($user_data->profile_image ?? '')) {
        $update_fields[] = "profile_image = :profile_image";
        $bind_params[':profile_image'] = $profile_image;
    }

    // Update Mobile No.
    if ($new_mobile !== $user_data->mobile) {
        $update_fields[] = "mobile = :mobile";
        $bind_params[':mobile'] = $new_mobile;
    }

    // Update Email
    if ($new_email !== $user_data->email) {
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format.";
        } else {
            // Check if new email already exists for another user
            $database->query('SELECT id FROM users WHERE email = :email AND id != :id');
            $database->bind(':email', $new_email);
            $database->bind(':id', $user_id);
            if ($database->single()) {
                $message = "Email already taken by another user.";
            } else {
                $update_fields[] = "email = :email";
                $bind_params[':email'] = $new_email;
            }
        }
    }

    // Update Security Question Answer
    if ($new_security_answer !== $user_data->security_answer) {
        $update_fields[] = "security_answer = :security_answer";
        $bind_params[':security_answer'] = $new_security_answer;
    }

    // Update Username
    if ($new_username !== $user_data->username) {
        // Check if new username already exists
        $database->query('SELECT id FROM users WHERE username = :username AND id != :id');
        $database->bind(':username', $new_username);
        $database->bind(':id', $user_id);
        if ($database->single()) {
            $message = "Username already taken.";
        } else {
            $update_fields[] = "username = :username";
            $bind_params[':username'] = $new_username;
        }
    }

    // Update Password
    if (!empty($new_password)) {
        // Verify current password
        $database->query('SELECT password FROM users WHERE id = :id');
        $database->bind(':id', $user_id);
        $user_pass_hash = $database->single()->password;

        if (!password_verify($current_password, $user_pass_hash)) {
            $message = "Current password is incorrect.";
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
            $update_fields[] = "password = :password";
            $bind_params[':password'] = $hashed_new_password;
        }
    }

    // Update PIN
    $new_pin = $_POST['new_pin'] ?? '';
    $confirm_new_pin = $_POST['confirm_new_pin'] ?? '';

    if (!empty($new_pin)) {
        if (!preg_match("/^[0-9]{4}$/", $new_pin)) {
            $message = "PIN must be a 4-digit number.";
        } elseif ($new_pin !== $confirm_new_pin) {
            $message = "New PINs do not match.";
        } else {
            $hashed_new_pin = password_hash($new_pin, PASSWORD_DEFAULT);
            $update_fields[] = "pin_hash = :pin_hash";
            $bind_params[':pin_hash'] = $hashed_new_pin;
        }
    }

    

    if (!empty($update_fields) && empty($message)) {
        $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = :id";
        $database->query($sql);
        foreach ($bind_params as $key => $value) {
            $database->bind($key, $value);
        }

        if ($database->execute()) {
            $message = "Profile updated successfully!";
            // Re-fetch user data to display updated info
            $database->query('SELECT username, email, mobile, security_answer, profile_image FROM users WHERE id = :id');
            $database->bind(':id', $user_id);
            $user_data = $database->single();
            $_SESSION['username'] = $user_data->username; // Update session username if changed
            $_SESSION['profile_image'] = $user_data->profile_image; // Update session profile image if changed
        } else {
            $message = "Error updating profile.";
        }
    } elseif (empty($message)) {
        $message = "No changes submitted.";
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
    <title>User Profile - CRM App</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
        .footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 10px 0;
            position: relative;
            bottom: 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <?php if (!empty($company_logo_header)): ?>
                <img src="../<?php echo $company_logo_header; ?>" alt="Company Logo" class="logo">
            <?php endif; ?>
            <span><?php echo $company_name_header; ?></span>
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
        <a href="dashboard.php">Dashboard</a>
        <a href="company_profile.php">Company Profile</a>
        <a href="product_management.php">Product Management</a>
        <a href="user_profile.php">User Profile</a>
        <a href="logout.php">Logout</a>
    </nav>

    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="content">
            <h1>User Profile</h1>
            <?php if (!empty($message)): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="profile_image">Profile Image</label>
                    <input type="file" class="form-control-file" id="profile_image" name="profile_image">
                    <?php if (!empty($user_data->profile_image)): ?>
                        <img src="../<?php echo htmlspecialchars($user_data->profile_image); ?>" alt="Current Profile Image" style="max-width: 150px; margin-top: 10px;">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user_data->username ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_data->email ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="mobile">Mobile No.</label>
                    <input type="text" class="form-control" id="mobile" name="mobile" value="<?php echo htmlspecialchars($user_data->mobile ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="security_answer">Security Question: What is your best place?</label>
                    <input type="text" class="form-control" id="security_answer" name="security_answer" value="<?php echo htmlspecialchars($user_data->security_answer ?? ''); ?>" required>
                </div>

                <hr>
                <h3>Change Password</h3>
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" class="form-control" id="current_password" name="current_password">
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password">
                </div>
                <div class="form-group">
                    <label for="confirm_new_password">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password">
                </div>

                <hr>
                <h3>Set/Change 4-Digit PIN</h3>
                <div class="form-group">
                    <label for="new_pin">New 4-Digit PIN</label>
                    <input type="password" class="form-control" id="new_pin" name="new_pin" maxlength="4" pattern="[0-9]{4}">
                </div>
                <div class="form-group">
                    <label for="confirm_new_pin">Confirm New 4-Digit PIN</label>
                    <input type="password" class="form-control" id="confirm_new_pin" name="confirm_new_pin" maxlength="4" pattern="[0-9]{4}">
                </div>

                

                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> CRM App. All rights reserved.</p>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>