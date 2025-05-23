<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['chat_id'])) {
    echo json_encode(['error' => 'Authentication or chat ID missing.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$chat_id = (int)$_GET['chat_id'];
$last_message_id = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;

// Validate user is part of the chat
$stmt_validate = $mysqli->prepare("SELECT COUNT(*) FROM chat_participants WHERE chat_id = ? AND user_id = ?");
$stmt_validate->bind_param("ii", $chat_id, $current_user_id);
$stmt_validate->execute();
$stmt_validate->bind_result($count);
$stmt_validate->fetch();
$stmt_validate->close();

if ($count == 0) {
    echo json_encode(['error' => 'Access denied to this chat.']);
    exit;
}

$messages = [];
$sql = "
    SELECT 
        m.message_id, m.sender_id, m.content_type, m.content, m.timestamp, 
        m.reply_to_message_id,
        u.username, u.profile_image_path,
        replied_msg.content AS replied_content, 
        replied_msg.content_type AS replied_content_type,
        replied_user.username AS replied_sender_username
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    LEFT JOIN messages replied_msg ON m.reply_to_message_id = replied_msg.message_id
    LEFT JOIN users replied_user ON replied_msg.sender_id = replied_user.user_id
    WHERE m.chat_id = ? AND m.message_id > ? 
    ORDER BY m.timestamp ASC
";

$stmt_messages = $mysqli->prepare($sql);
$stmt_messages->bind_param("ii", $chat_id, $last_message_id);
$stmt_messages->execute();
$result_messages = $stmt_messages->get_result();

while ($row = $result_messages->fetch_assoc()) {
    $profile_pic_filename = (!empty($row['profile_image_path'])) ? htmlspecialchars($row['profile_image_path']) : 'default_profile.png';
    $profile_pic = 'uploads/profile_pics/' . $profile_pic_filename;
    if (!file_exists($profile_pic)) { 
        $profile_pic = 'uploads/profile_pics/default_profile.png'; 
        if (!file_exists($profile_pic)) {
            $profile_pic = 'default_profile.png'; 
        }
    }

    $message_content_display = $row['content']; 
    if ($row['content_type'] === 'deleted') {
        $message_content_display = 'Message deleted';
    } elseif ($row['content_type'] === 'text') {
        $message_content_display = htmlspecialchars($row['content']);
    } else { // image
        $message_content_display = htmlspecialchars($row['content']); 
    }
    
    $replied_message_snippet = null;
    if ($row['reply_to_message_id'] && $row['replied_sender_username']) { 
        $snippet_text = $row['replied_content'];
        if ($row['replied_content_type'] === 'image') {
            $snippet_text = '[Image]';
        } elseif ($row['replied_content_type'] === 'deleted') {
            $snippet_text = 'Original message was deleted';
        } else if ($row['replied_content_type'] === 'text' && $row['replied_content'] === 'Message deleted'){ 
             $snippet_text = 'Original message was deleted';
        }
        $replied_message_snippet = [
            'original_sender' => htmlspecialchars($row['replied_sender_username']),
            'original_content_snippet' => htmlspecialchars(substr($snippet_text, 0, 50)) . (strlen($snippet_text) > 50 ? '...' : '')
        ];
    }  else if ($row['reply_to_message_id'] && !$row['replied_sender_username']) {
         $replied_message_snippet = [
            'original_sender' => 'Unknown User',
            'original_content_snippet' => 'Original message not available'
        ];
    }

    // Fetch reactions for this message_id
    $reactions = [];
    $stmt_reactions = $mysqli->prepare("
        SELECT reaction_emoji, COUNT(user_id) as reaction_count,
               GROUP_CONCAT(user_id) as reacted_user_ids
        FROM message_reactions 
        WHERE message_id = ? 
        GROUP BY reaction_emoji
    ");
    $stmt_reactions->bind_param("i", $row['message_id']);
    $stmt_reactions->execute();
    $result_react = $stmt_reactions->get_result();
    while ($react_row = $result_react->fetch_assoc()) {
        $reacted_users_array = explode(',', $react_row['reacted_user_ids']);
        $reactions[] = [
            'emoji' => $react_row['reaction_emoji'],
            'count' => (int)$react_row['reaction_count'],
            'user_has_reacted' => in_array($current_user_id, $reacted_users_array)
        ];
    }
    $stmt_reactions->close();

    $messages[] = [
        'message_id' => $row['message_id'],
        'sender_id' => $row['sender_id'],
        'sender_username' => htmlspecialchars($row['username']),
        'sender_profile_pic' => $profile_pic,
        'content_type' => $row['content_type'], 
        'content' => $message_content_display, 
        'original_content_for_cache' => $row['content'], 
        'timestamp' => date("M d, H:i", strtotime($row['timestamp'])),
        'is_sender' => ($row['sender_id'] == $current_user_id),
        'reply_to_info' => $replied_message_snippet,
        'reactions' => $reactions // Added
    ];
}
$stmt_messages->close();
$mysqli->close();

echo json_encode(['messages' => $messages]);
?>
