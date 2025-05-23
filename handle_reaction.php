<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Authentication or invalid request.', 'success' => false]);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$emoji = isset($_POST['emoji']) ? trim($_POST['emoji']) : '';
// Basic emoji validation (example: allow only specific ones or check length)
$allowed_emojis = ['👍', '❤️', '😂', '😢', '😮', '🤔']; // Example set
if (empty($message_id) || empty($emoji) || !in_array($emoji, $allowed_emojis)) {
    echo json_encode(['error' => 'Message ID or emoji missing or invalid emoji.', 'success' => false]);
    exit;
}

// Check if user has already reacted with this emoji on this message
$stmt_check = $mysqli->prepare("SELECT reaction_id FROM message_reactions WHERE message_id = ? AND user_id = ? AND reaction_emoji = ?");
$stmt_check->bind_param("iis", $message_id, $current_user_id, $emoji);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$existing_reaction = $result_check->fetch_assoc();
$stmt_check->close();

$mysqli->begin_transaction();
try {
    if ($existing_reaction) {
        // User already reacted with this emoji, so remove reaction
        $stmt_delete = $mysqli->prepare("DELETE FROM message_reactions WHERE reaction_id = ?");
        $stmt_delete->bind_param("i", $existing_reaction['reaction_id']);
        $stmt_delete->execute();
        $stmt_delete->close();
        $action_taken = 'removed';
    } else {
        // New reaction, add it
        // Optional: Limit number of distinct emojis per user per message if desired (not implemented here)
        $stmt_insert = $mysqli->prepare("INSERT INTO message_reactions (message_id, user_id, reaction_emoji) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("iis", $message_id, $current_user_id, $emoji);
        $stmt_insert->execute();
        $stmt_insert->close();
        $action_taken = 'added';
    }
    $mysqli->commit();

    // Fetch updated reactions for this message to send back
    $updated_reactions = [];
    $stmt_updated_reacts = $mysqli->prepare("
        SELECT reaction_emoji, COUNT(user_id) as reaction_count,
               GROUP_CONCAT(user_id) as reacted_user_ids
        FROM message_reactions 
        WHERE message_id = ? 
        GROUP BY reaction_emoji
    ");
    $stmt_updated_reacts->bind_param("i", $message_id);
    $stmt_updated_reacts->execute();
    $result_updated_reacts = $stmt_updated_reacts->get_result();
    while ($react_row = $result_updated_reacts->fetch_assoc()) {
        $reacted_users_array = explode(',', $react_row['reacted_user_ids']);
        $updated_reactions[] = [
            'emoji' => $react_row['reaction_emoji'],
            'count' => (int)$react_row['reaction_count'],
            'user_has_reacted' => in_array($current_user_id, $reacted_users_array)
        ];
    }
    $stmt_updated_reacts->close();

    echo json_encode(['success' => true, 'action' => $action_taken, 'reactions' => $updated_reactions]);

} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Reaction handling failed: " . $e->getMessage());
    echo json_encode(['error' => 'Could not process reaction. ' . $e->getMessage(), 'success' => false]);
}
$mysqli->close();
?>
