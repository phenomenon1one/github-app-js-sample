<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not authenticated.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['user_id'])) {
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$other_user_id = (int)$_POST['user_id'];

if ($current_user_id == $other_user_id) {
    echo json_encode(['error' => 'Cannot start a chat with yourself.']);
    exit;
}

// Check if a chat already exists between these two users
// This query is a bit complex: find a chat_id that has both users as participants.
$stmt_check = $mysqli->prepare("
    SELECT cp1.chat_id 
    FROM chat_participants cp1
    JOIN chat_participants cp2 ON cp1.chat_id = cp2.chat_id
    WHERE cp1.user_id = ? AND cp2.user_id = ?
    LIMIT 1
");
$stmt_check->bind_param("ii", $current_user_id, $other_user_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($existing_chat = $result_check->fetch_assoc()) {
    // Chat already exists, return its ID
    echo json_encode(['chat_id' => $existing_chat['chat_id'], 'message' => 'Chat already exists.']);
    $stmt_check->close();
    $mysqli->close();
    exit;
}
$stmt_check->close();

// Start a transaction
$mysqli->begin_transaction();

try {
    // Create a new chat
    $stmt_create_chat = $mysqli->prepare("INSERT INTO chats (created_at, last_message_at) VALUES (CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
    if (!$stmt_create_chat->execute()) {
        throw new Exception("Failed to create chat: " . $stmt_create_chat->error);
    }
    $chat_id = $mysqli->insert_id;
    $stmt_create_chat->close();

    // Add current user as participant
    $stmt_add_current = $mysqli->prepare("INSERT INTO chat_participants (chat_id, user_id) VALUES (?, ?)");
    $stmt_add_current->bind_param("ii", $chat_id, $current_user_id);
    if (!$stmt_add_current->execute()) {
        throw new Exception("Failed to add current user to chat: " . $stmt_add_current->error);
    }
    $stmt_add_current->close();

    // Add other user as participant
    $stmt_add_other = $mysqli->prepare("INSERT INTO chat_participants (chat_id, user_id) VALUES (?, ?)");
    $stmt_add_other->bind_param("ii", $chat_id, $other_user_id);
    if (!$stmt_add_other->execute()) {
        throw new Exception("Failed to add other user to chat: " . $stmt_add_other->error);
    }
    $stmt_add_other->close();

    // Commit transaction
    $mysqli->commit();
    echo json_encode(['chat_id' => $chat_id, 'message' => 'Chat created successfully.']);

} catch (Exception $e) {
    $mysqli->rollback(); // Corrected from mysqli_rollback()
    error_log("Chat creation failed: " . $e->getMessage());
    echo json_encode(['error' => 'Could not create chat. ' . $e->getMessage()]);
}

$mysqli->close();
?>
