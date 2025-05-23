<?php
session_start();
require_once 'db_connect.php';

// If not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'];

// Fetch existing chats for the current user
$chats = [];
// This query needs to join with users to get the other participant's name
// and with messages to get the last message and its timestamp.
$stmt_chats = $mysqli->prepare("
    SELECT 
        c.chat_id,
        GROUP_CONCAT(DISTINCT u.username SEPARATOR ', ') AS participant_names,
        u_other.user_id AS other_user_id,
        u_other.username AS other_username,
        (SELECT m.content FROM messages m WHERE m.chat_id = c.chat_id ORDER BY m.timestamp DESC LIMIT 1) AS last_message_content,
        (SELECT m.content_type FROM messages m WHERE m.chat_id = c.chat_id ORDER BY m.timestamp DESC LIMIT 1) AS last_message_type,
        c.last_message_at
    FROM chats c
    JOIN chat_participants cp ON c.chat_id = cp.chat_id
    JOIN users u ON u.user_id = cp.user_id
    JOIN chat_participants cp_other ON c.chat_id = cp_other.chat_id -- Join again to find the other user
    JOIN users u_other ON u_other.user_id = cp_other.user_id AND u_other.user_id != ? -- The other participant
    WHERE cp.user_id = ?
    GROUP BY c.chat_id, u_other.user_id, u_other.username -- Group by chat and the specific other user
    ORDER BY c.last_message_at DESC
");
$stmt_chats->bind_param("ii", $current_user_id, $current_user_id);
$stmt_chats->execute();
$result_chats = $stmt_chats->get_result();
while ($row = $result_chats->fetch_assoc()) {
    // Determine the display name for the chat (typically the other user's name in a 1-on-1 chat)
    $chat_name = $row['other_username'];
    
    $last_msg_display = $row['last_message_content'];
    if ($row['last_message_type'] === 'image') {
        $last_msg_display = '[Image]';
    } else if (empty($last_msg_display)) {
        $last_msg_display = 'No messages yet.';
    }

    $chats[] = [
        'chat_id' => $row['chat_id'],
        'chat_name' => $chat_name, // In a group chat, this would be more complex
        'other_user_id' => $row['other_user_id'],
        'last_message' => $last_msg_display,
        'timestamp' => $row['last_message_at'] ? date("M d, H:i", strtotime($row['last_message_at'])) : 'Never'
    ];
}
$stmt_chats->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Chat App</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <nav>
            <h1>Welcome, <?php echo htmlspecialchars($current_username); ?>!</h1>
            <div>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </nav>

        <div class="content-area">
            <div class="user-search-container">
                <h3>Search for Users to Chat With</h3>
                <input type="search" id="user-search-input" placeholder="Enter username or email">
                <button id="user-search-button">Search</button>
                <div id="search-results">
                    <!-- Search results will be populated here by JavaScript -->
                </div>
            </div>

            <hr>

            <div class="chat-list">
                <h2>Your Chats</h2>
                <?php if (empty($chats)): ?>
                    <p>No chats yet. Search for a user to start a conversation!</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($chats as $chat): ?>
                            <li>
                                <a href="chat_view.php?chat_id=<?php echo $chat['chat_id']; ?>&with_user_id=<?php echo $chat['other_user_id']; ?>">
                                    <div class="chat-info">
                                        <span class="chat-name"><?php echo htmlspecialchars($chat['chat_name']); ?></span>
                                        <span class="chat-timestamp"><?php echo $chat['timestamp']; ?></span>
                                    </div>
                                    <div class="chat-last-message">
                                        <?php echo htmlspecialchars(substr($chat['last_message'], 0, 50)) . (strlen($chat['last_message']) > 50 ? '...' : ''); ?>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchButton = document.getElementById('user-search-button');
        const searchInput = document.getElementById('user-search-input');
        const searchResultsContainer = document.getElementById('search-results');

        searchButton.addEventListener('click', performSearch);
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        function performSearch() {
            const query = searchInput.value.trim();
            if (query.length < 2) {
                searchResultsContainer.innerHTML = '<p>Please enter at least 2 characters to search.</p>';
                return;
            }

            searchResultsContainer.innerHTML = '<p>Searching...</p>';

            fetch('search_users.php?query=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    searchResultsContainer.innerHTML = ''; // Clear previous results
                    if (data.error) {
                        searchResultsContainer.innerHTML = `<p style="color:red;">Error: ${data.error}</p>`;
                    } else if (data.users && data.users.length > 0) {
                        data.users.forEach(user => {
                            const userDiv = document.createElement('div');
                            userDiv.innerHTML = `<strong>${user.username}</strong> (ID: ${user.user_id})`;
                            userDiv.style.cursor = 'pointer';
                            userDiv.addEventListener('click', function() {
                                createOrOpenChat(user.user_id, user.username);
                            });
                            searchResultsContainer.appendChild(userDiv);
                        });
                    } else {
                        searchResultsContainer.innerHTML = '<p>No users found.</p>';
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                    searchResultsContainer.innerHTML = '<p style="color:red;">Search failed. Please try again.</p>';
                });
        }

        function createOrOpenChat(userId, username) {
            if (!confirm(`Start a chat with ${username}?`)) {
                return;
            }
            
            fetch('create_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'user_id=' + encodeURIComponent(userId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error creating chat: ' + data.error);
                } else if (data.chat_id) {
                    window.location.href = 'chat_view.php?chat_id=' + data.chat_id + '&with_user_id=' + userId;
                } else {
                    alert('Could not create or open chat. Unknown response.');
                }
            })
            .catch(error => {
                console.error('Create chat error:', error);
                alert('Failed to create or open chat.');
            });
        }
    });
    </script>
</body>
</html>
