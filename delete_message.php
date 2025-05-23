<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Authentication or invalid request.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$chat_id = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0; // For validation

if (empty($message_id) || empty($chat_id)) {
    echo json_encode(['error' => 'Message ID or Chat ID missing.']);
    exit;
}

// Verify the user is the sender of the message and part of the chat
$stmt_verify = $mysqli->prepare("
    SELECT m.sender_id 
    FROM messages m
    JOIN chat_participants cp ON m.chat_id = cp.chat_id
    WHERE m.message_id = ? AND m.chat_id = ? AND cp.user_id = ?
");
$stmt_verify->bind_param("iii", $message_id, $chat_id, $current_user_id);
$stmt_verify->execute();
$result_verify = $stmt_verify->get_result();
$message_data = $result_verify->fetch_assoc();
$stmt_verify->close();

if (!$message_data) {
    echo json_encode(['error' => 'Message not found or you are not part of this chat.']);
    exit;
}

if ($message_data['sender_id'] != $current_user_id) {
    echo json_encode(['error' => 'You can only delete your own messages.']);
    exit;
}

// Perform soft delete: update content_type and content
$deleted_content_type = 'deleted';
$deleted_content_text = 'Message deleted'; // Placeholder text

$stmt_delete = $mysqli->prepare("UPDATE messages SET content_type = ?, content = ? WHERE message_id = ? AND sender_id = ?");
$stmt_delete->bind_param("ssii", $deleted_content_type, $deleted_content_text, $message_id, $current_user_id);

if ($stmt_delete->execute()) {
    if ($stmt_delete->affected_rows > 0) {
        // Optionally, update last_message_at in chats table if this was the last message
        // For simplicity, not doing it here, as it might become complex to determine.
        // The chat list will just show "Message deleted" as the last message.
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Message already deleted or could not be updated.']);
    }
} else {
    error_log("Message delete error: " . $stmt_delete->error);
    echo json_encode(['error' => 'Failed to delete message due to a server error.']);
}
$stmt_delete->close();
$mysqli->close();
?>
