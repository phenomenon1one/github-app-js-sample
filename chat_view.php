<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if (!isset($_GET['chat_id']) || !isset($_GET['with_user_id'])) {
    header("Location: home.php?error=Chat ID or other user ID not specified.");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$chat_id = (int)$_GET['chat_id'];
$other_user_id = (int)$_GET['with_user_id']; // The user we are chatting with

// Validate that the current user is part of this chat
$stmt_validate = $mysqli->prepare("SELECT COUNT(*) FROM chat_participants WHERE chat_id = ? AND user_id = ?");
$stmt_validate->bind_param("ii", $chat_id, $current_user_id);
$stmt_validate->execute();
$stmt_validate->bind_result($count);
$stmt_validate->fetch();
$stmt_validate->close();

if ($count == 0) {
    header("Location: home.php?error=Access denied to this chat.");
    exit;
}

// Fetch details of the other user (for chat header)
$stmt_other_user = $mysqli->prepare("SELECT username, profile_image_path FROM users WHERE user_id = ?");
$stmt_other_user->bind_param("i", $other_user_id);
$stmt_other_user->execute();
$result_other_user = $stmt_other_user->get_result();
$other_user = $result_other_user->fetch_assoc();
$stmt_other_user->close();

if (!$other_user) {
    header("Location: home.php?error=Other user not found.");
    exit;
}

$other_username = htmlspecialchars($other_user['username']);
$other_user_profile_pic = 'uploads/profile_pics/' . (!empty($other_user['profile_image_path']) && file_exists('uploads/profile_pics/' . $other_user['profile_image_path']) ? htmlspecialchars($other_user['profile_image_path']) : 'default_profile.png');
if (!file_exists($other_user_profile_pic)) $other_user_profile_pic = 'uploads/profile_pics/default_profile.png'; // Fallback if specific image not found after construction
if (!file_exists($other_user_profile_pic)) $other_user_profile_pic = 'default_profile.png'; // Absolute fallback


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?php echo $other_username; ?> - Chat App</title>
    <link rel="stylesheet" href="style.css">
    <!-- jQuery is already included in the original index.php, assuming it's available or added if not -->
    <!-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script> -->
</head>
<body>
    <div class="container"> <!-- Main page container -->
        <nav> <!-- Navigation bar from home.php for consistency -->
            <h1>Chat with <?php echo $other_username; ?></h1>
            <div>
                <a href="home.php">Home</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </nav>

        <div class="chat-container">
            <div class="chat-header">
                <img src="<?php echo $other_user_profile_pic; ?>" alt="<?php echo $other_username; ?>" class="profile-pic-small">
                <span><?php echo $other_username; ?></span>
                <!-- Active status can be added here later -->
            </div>
            <div class="chat-messages" id="chat-messages-area">
                <!-- Messages will be loaded here by JavaScript -->
                <p>Loading messages...</p>
            </div>
            <!-- Add this above the .chat-input-area in chat_view.php -->
            <div class="reply-context-area" id="reply-context-area">
                Replying to: <strong id="reply-context-user">User</strong>
                <div id="reply-context-snippet" class="reply-original-snippet">Original message snippet...</div>
                <span class="cancel-reply-btn" id="cancel-reply-button" title="Cancel Reply">&times;</span>
            </div>
            <div class="chat-input-area">
                <textarea id="message-input" placeholder="Type your message..." rows="1"></textarea>
                <input type="file" id="image-input" accept="image/jpeg, image/png, image/gif" style="display: none;">
                <button id="image-attach-button" title="Attach Image" style="margin-left: 5px; margin-right: 5px; padding: 10px 15px; background-color: #6c757d; color:white; border:none; border-radius:20px; cursor:pointer;">📷</button>
                <button id="send-message-button">Send</button>
            </div>
        </div>
    </div>

    <script src="chat_script.js"></script>
    <script>
        // Pass PHP variables to JavaScript
        const currentUserId = <?php echo $current_user_id; ?>;
        const chatId = <?php echo $chat_id; ?>;
        const otherUserId = <?php echo $other_user_id; ?>; // For potential future use in JS
    </script>
</body>
</html>
```
