<?php
session_start();
require_once 'db_connect.php'; // Establishes $mysqli connection

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validations
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        // Check if username or email already exists
        $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error_message = "Username or email already taken.";
        } else {
            // Hash the password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $insert_stmt = $mysqli->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("sss", $username, $email, $password_hash);

            if ($insert_stmt->execute()) {
                $success_message = "Registration successful! You can now login.";
                $_SESSION['success_message'] = $success_message;
                $insert_stmt->close();
                $stmt->close();
                $mysqli->close();
                header("Location: login.php"); // Redirect to login page on success
                exit;
            } else {
                $error_message = "Registration failed. Please try again. Error: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $stmt->close();
    }
    $mysqli->close();
}

if ($error_message) {
    $_SESSION['error_message'] = $error_message;
    header("Location: register.php"); // Redirect back to registration form with error
    exit;
}
// Should not reach here if successful, as it redirects.
// If method is not POST, or other issues, redirect to register page.
if (!isset($_SESSION['success_message'])) { // Avoid redirecting if success message was set and redirect to login happened
    header("Location: register.php");
    exit;
}

?>
