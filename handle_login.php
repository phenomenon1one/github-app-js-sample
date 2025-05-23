<?php
session_start();
require_once 'db_connect.php'; // Establishes $mysqli connection

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_identifier = trim($_POST['login_identifier']); // Can be username or email
    $password = $_POST['password'];

    if (empty($login_identifier) || empty($password)) {
        $error_message = "Username/Email and Password are required.";
    } else {
        // Prepare statement to fetch user by username or email
        $stmt = $mysqli->prepare("SELECT user_id, username, password_hash FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $login_identifier, $login_identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                // Password is correct, start session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];

                // Update last_active timestamp
                $update_stmt = $mysqli->prepare("UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE user_id = ?");
                $update_stmt->bind_param("i", $user['user_id']);
                $update_stmt->execute();
                $update_stmt->close();

                $stmt->close();
                $mysqli->close();
                header("Location: home.php"); // Redirect to a logged-in homepage
                exit;
            } else {
                $error_message = "Invalid password.";
            }
        } else {
            $error_message = "User not found or invalid credentials.";
        }
        $stmt->close();
    }
    $mysqli->close();
}

if ($error_message) {
    $_SESSION['error_message'] = $error_message;
    header("Location: login.php"); // Redirect back to login form with error
    exit;
}

// Fallback redirect if not POST or other issues
header("Location: login.php");
exit;
?>
