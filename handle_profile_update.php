<?php
session_start();
require_once 'db_connect.php'; // Establishes $mysqli connection

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "You must be logged in to perform this action.";
    $_SESSION['message_type'] = "error";
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == "update_picture") {
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['profile_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if (!in_array($file['type'], $allowed_types)) {
                $_SESSION['message'] = "Invalid file type. Only JPG, PNG, GIF allowed.";
                $_SESSION['message_type'] = "error";
            } elseif ($file['size'] > $max_size) {
                $_SESSION['message'] = "File is too large. Maximum size is 2MB.";
                $_SESSION['message_type'] = "error";
            } else {
                // Ensure upload directory exists and is writable
                $upload_dir = 'uploads/profile_pics/';
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $_SESSION['message'] = "Failed to create upload directory. Check permissions.";
                        $_SESSION['message_type'] = "error";
                        header("Location: profile.php");
                        exit;
                    }
                }


                // Generate unique filename
                $filename = uniqid('user_' . $current_user_id . '_', true) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                $filepath = $upload_dir . $filename;

                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Get old image path to delete if not default
                    $stmt_old_img = $mysqli->prepare("SELECT profile_image_path FROM users WHERE user_id = ?");
                    $stmt_old_img->bind_param("i", $current_user_id);
                    $stmt_old_img->execute();
                    $result_old_img = $stmt_old_img->get_result();
                    $old_image_data = $result_old_img->fetch_assoc();
                    $stmt_old_img->close();

                    // Update database
                    $stmt_update = $mysqli->prepare("UPDATE users SET profile_image_path = ? WHERE user_id = ?");
                    $stmt_update->bind_param("si", $filename, $current_user_id); // Store only filename
                    if ($stmt_update->execute()) {
                        $_SESSION['message'] = "Profile picture updated successfully.";
                        $_SESSION['message_type'] = "success";
                        // Delete old picture if it's not the default and exists
                        if ($old_image_data && $old_image_data['profile_image_path'] !== 'default_profile.png' && $old_image_data['profile_image_path'] !== $filename) {
                            if (file_exists($upload_dir . $old_image_data['profile_image_path'])) {
                                unlink($upload_dir . $old_image_data['profile_image_path']);
                            }
                        }
                    } else {
                        $_SESSION['message'] = "Database update failed: " . $stmt_update->error;
                        $_SESSION['message_type'] = "error";
                        if(file_exists($filepath)) unlink($filepath); // Delete uploaded file if DB update fails
                    }
                    $stmt_update->close();
                } else {
                    $_SESSION['message'] = "Failed to upload file. Check directory permissions.";
                    $_SESSION['message_type'] = "error";
                }
            }
        } else {
            $_SESSION['message'] = "No file uploaded or an error occurred during upload. Error code: " . (isset($_FILES['profile_image']['error']) ? $_FILES['profile_image']['error'] : 'Unknown');
            $_SESSION['message_type'] = "error";
        }

    } elseif ($action == "update_password") {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
            $_SESSION['message'] = "All password fields are required.";
            $_SESSION['message_type'] = "error";
        } elseif (strlen($new_password) < 6) {
            $_SESSION['message'] = "New password must be at least 6 characters long.";
            $_SESSION['message_type'] = "error";
        } elseif ($new_password !== $confirm_new_password) {
            $_SESSION['message'] = "New passwords do not match.";
            $_SESSION['message_type'] = "error";
        } else {
            // Fetch current password hash
            $stmt_pass = $mysqli->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt_pass->bind_param("i", $current_user_id);
            $stmt_pass->execute();
            $result_pass = $stmt_pass->get_result();
            $user_data = $result_pass->fetch_assoc();
            $stmt_pass->close();

            if ($user_data && password_verify($current_password, $user_data['password_hash'])) {
                // Current password is correct, hash new password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update_pass = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt_update_pass->bind_param("si", $new_password_hash, $current_user_id);
                if ($stmt_update_pass->execute()) {
                    $_SESSION['message'] = "Password updated successfully.";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Failed to update password: " . $stmt_update_pass->error;
                    $_SESSION['message_type'] = "error";
                }
                $stmt_update_pass->close();
            } else {
                $_SESSION['message'] = "Incorrect current password.";
                $_SESSION['message_type'] = "error";
            }
        }
    } else {
        $_SESSION['message'] = "Invalid action.";
        $_SESSION['message_type'] = "error";
    }
} else {
    $_SESSION['message'] = "Invalid request method or action not specified.";
    $_SESSION['message_type'] = "error";
}

$mysqli->close();
header("Location: profile.php");
exit;
?>
