CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    profile_image_path VARCHAR(255) DEFAULT 'default_profile.png',
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE chats (
    chat_id INT AUTO_INCREMENT PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP -- To help sort chats
);

CREATE TABLE chat_participants (
    participant_id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(chat_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY (chat_id, user_id) -- Ensures a user is only once in a chat
);

CREATE TABLE messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    sender_id INT NOT NULL,
    content_type ENUM('text', 'image', 'deleted') DEFAULT 'text', -- 'deleted' for soft deletes
    content TEXT NOT NULL, -- For text messages or image paths
    reply_to_message_id INT NULL, -- Self-referential for replies
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(chat_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reply_to_message_id) REFERENCES messages(message_id) ON DELETE SET NULL
);

CREATE TABLE message_reactions (
    reaction_id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction_emoji VARCHAR(10) NOT NULL, -- Store the emoji itself or a code
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY (message_id, user_id, reaction_emoji) -- Prevents duplicate reactions by same user on same message with same emoji
);

-- Indexes for performance
CREATE INDEX idx_messages_chat_timestamp ON messages (chat_id, timestamp);
CREATE INDEX idx_chat_participants_user ON chat_participants (user_id);
CREATE INDEX idx_users_last_active ON users (last_active);
CREATE INDEX idx_chats_last_message_at ON chats (last_message_at);
