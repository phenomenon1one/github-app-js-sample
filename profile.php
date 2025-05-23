<?php
session_start();
require_once 'db_connect.php';

// If not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Fetch user details
$stmt_user = $mysqli->prepare("SELECT username, email, profile_image_path FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $current_user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();
$stmt_user->close();

if (!$user) {
    // Should not happen if session is valid, but as a safeguard
    session_destroy();
    header("Location: login.php?error=User data not found.");
    exit;
}

$profile_image = !empty($user['profile_image_path']) ? htmlspecialchars($user['profile_image_path']) : 'default_profile.png';
// Ensure the path is web-accessible, typically relative to the script or a base URL.
// For simplicity, assuming 'uploads/profile_pics/' is the directory where images are stored.
// And 'default_profile.png' is in the same directory or a known accessible path.
if ($profile_image !== 'default_profile.png' && !file_exists('uploads/profile_pics/' . $profile_image) && !file_exists($profile_image)) {
    // If the specific image file doesn't exist in the expected upload folder or as a direct path (less ideal)
    // and it's not the default, fallback to default to avoid broken images.
    // This check might need adjustment based on how profile_image_path is stored (full path vs. filename)
    // For this example, let's assume profile_image_path stores just the filename, and they are in 'uploads/profile_pics/'
    if (file_exists('uploads/profile_pics/' . $profile_image)) {
         $profile_image_url = 'uploads/profile_pics/' . $profile_image;
    } else {
         $profile_image_url = 'uploads/profile_pics/default_profile.png'; // Fallback if specific image not found
         if (!file_exists($profile_image_url)) $profile_image_url = 'default_profile.png'; // Absolute fallback
    }
} elseif ($profile_image === 'default_profile.png') {
    if (file_exists('uploads/profile_pics/default_profile.png')) {
        $profile_image_url = 'uploads/profile_pics/default_profile.png';
    } else {
        $profile_image_url = 'default_profile.png'; // Assuming it's in the root or accessible path
    }
} else {
     $profile_image_url = 'uploads/profile_pics/' . $profile_image;
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Chat App</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-container { display: flex; flex-direction: column; align-items: center; }
        .profile-pic { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 20px; border: 3px solid #ddd; }
        .profile-info p { font-size: 1.1em; margin: 5px 0; }
        .form-section { margin-top: 20px; padding: 20px; border: 1px solid #eee; border-radius: 5px; width:100%; max-width:500px; }
        .form-section h3 { margin-top: 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="file"], input[type="password"] { margin-bottom: 10px; width: calc(100% - 22px); padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        input[type="submit"] { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background-color: #0056b3; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
    <div class="container">
        <nav>
            <h1>My Profile</h1>
            <div>
                <a href="home.php">Home</a>
                <a href="logout.php">Logout</a>
            </div>
        </nav>

        <div class="content-area profile-container">
            <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile Picture" class="profile-pic">
            
            <div class="profile-info">
                <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            </div>

            <?php
            if (isset($_SESSION['message'])) {
                $msg_type = isset($_SESSION['message_type']) && $_SESSION['message_type'] == 'error' ? 'error' : 'success';
                echo '<div class="message ' . $msg_type . '">' . htmlspecialchars($_SESSION['message']) . '</div>';
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            }
            ?>

            <div class="form-section">
                <h3>Change Profile Picture</h3>
                <form action="handle_profile_update.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_picture">
                    <label for="profile_image">New Profile Picture:</label>
                    <input type="file" name="profile_image" id="profile_image" accept="image/jpeg, image/png, image/gif" required>
                    <input type="submit" value="Upload Picture">
                </form>
            </div>

            <div class="form-section">
                <h3>Change Password</h3>
                <form action="handle_profile_update.php" method="POST">
                    <input type="hidden" name="action" value="update_password">
                    <label for="current_password">Current Password:</label>
                    <input type="password" name="current_password" id="current_password" required>
                    <label for="new_password">New Password:</label>
                    <input type="password" name="new_password" id="new_password" minlength="6" required>
                    <label for="confirm_new_password">Confirm New Password:</label>
                    <input type="password" name="confirm_new_password" id="confirm_new_password" required>
                    <input type="submit" value="Change Password">
                </form>
            </div>
        </div>
    </div>
</body>
</html>
```
