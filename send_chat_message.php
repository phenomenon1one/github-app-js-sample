<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Authentication or invalid request.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$chat_id = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0;
$message_content = isset($_POST['message']) ? trim($_POST['message']) : '';
$image_file_info = isset($_FILES['image_file']) ? $_FILES['image_file'] : null;
$reply_to_message_id = isset($_POST['reply_to_message_id']) ? (int)$_POST['reply_to_message_id'] : null; // Added

if (empty($chat_id) || (empty($message_content) && ($image_file_info === null || $image_file_info['error'] != UPLOAD_ERR_OK) )) {
    echo json_encode(['error' => 'Chat ID missing, or no message content or image provided.']);
    exit;
}

// Validate user is part of the chat
$stmt_validate = $mysqli->prepare("SELECT COUNT(*) FROM chat_participants WHERE chat_id = ? AND user_id = ?");
$stmt_validate->bind_param("ii", $chat_id, $current_user_id);
$stmt_validate->execute();
$stmt_validate->bind_result($count);
$stmt_validate->fetch();
$stmt_validate->close();

if ($count == 0) {
    echo json_encode(['error' => 'Access denied to send message to this chat.']);
    exit;
}


$content_type = 'text';
$db_content = $message_content;

// Image processing
if ($image_file_info && $image_file_info['error'] == UPLOAD_ERR_OK) {
    $file = $image_file_info;
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; 

    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['error' => 'Invalid image type. Only JPG, PNG, GIF allowed.']);
        exit;
    }
    if ($file['size'] > $max_size) {
        echo json_encode(['error' => 'Image file is too large. Maximum size is 5MB.']);
        exit;
    }

    $upload_dir = 'uploads/chat_images/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Failed to create chat image upload directory: " . $upload_dir);
            echo json_encode(['error' => 'Server error: Could not create image directory.']);
            exit;
        }
    }

    $filename = uniqid('chatimg_' . $chat_id . '_', true) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $content_type = 'image';
        $db_content = $filename; 
    } else {
        error_log("Failed to move uploaded chat image: " . $filepath);
        echo json_encode(['error' => 'Failed to save uploaded image.']);
        exit;
    }
} elseif ($image_file_info && $image_file_info['error'] != UPLOAD_ERR_OK && $image_file_info['error'] != UPLOAD_ERR_NO_FILE) {
    echo json_encode(['error' => 'Image upload failed. Error code: ' . $image_file_info['error']]);
    exit;
}

if (empty($db_content) && $content_type === 'text') { // Check if text content is empty AND it's supposed to be a text message
    if (!($image_file_info && $image_file_info['error'] == UPLOAD_ERR_OK)) { // if there isn't a successfully processed image
         echo json_encode(['error' => 'No message content to send.']);
         exit;
    }
}


// Insert message
$stmt_insert = $mysqli->prepare("INSERT INTO messages (chat_id, sender_id, content_type, content, reply_to_message_id) VALUES (?, ?, ?, ?, ?)");

// For reply_to_message_id, ensure it's null if 0 or not set
if ($reply_to_message_id === 0) {
    $reply_to_message_id = null;
}
// Corrected bind_param: iissi (chat_id INT, sender_id INT, content_type STR, content STR, reply_to_message_id INT)
$stmt_insert->bind_param("iissi", $chat_id, $current_user_id, $content_type, $db_content, $reply_to_message_id);


if ($stmt_insert->execute()) {
    $new_message_id = $mysqli->insert_id;
    
    $stmt_update_chat = $mysqli->prepare("UPDATE chats SET last_message_at = CURRENT_TIMESTAMP WHERE chat_id = ?");
    $stmt_update_chat->bind_param("i", $chat_id);
    $stmt_update_chat->execute();
    $stmt_update_chat->close();
    echo json_encode(['success' => true, 'message_id' => $new_message_id, 'timestamp' => date("M d, H:i")]);
} else {
    error_log("Message insert error: " . $stmt_insert->error . " (Content-Type: " . $content_type . ", ReplyTo: " . $reply_to_message_id . ")");
    echo json_encode(['error' => 'Failed to send message. DB Error. (' . $stmt_insert->errno . ')']);
}
$stmt_insert->close();
$mysqli->close();
?>
