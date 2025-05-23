// At the top of chat_script.js
let reactionPicker = null; // Will be created dynamically
const ALLOWED_REACTIONS = ['👍', '❤️', '😂', '😢', '😮', '🤔']; // Must match backend

document.addEventListener('DOMContentLoaded', function() {
    const messagesArea = document.getElementById('chat-messages-area');
    const messageInput = document.getElementById('message-input');
    const sendMessageButton = document.getElementById('send-message-button');
    const imageInput = document.getElementById('image-input');
    const imageAttachButton = document.getElementById('image-attach-button');

    const replyContextArea = document.getElementById('reply-context-area');
    const replyContextUser = document.getElementById('reply-context-user');
    const replyContextSnippet = document.getElementById('reply-context-snippet');
    const cancelReplyButton = document.getElementById('cancel-reply-button');
    
    let currentReplyToMessageId = null; 
    let originalMessagesCache = {}; 

    let lastLoadedMessageId = 0; 

    function displayMessage(msg) {
        const cacheContent = msg.original_content_for_cache !== undefined ? msg.original_content_for_cache : msg.content;
        originalMessagesCache[msg.message_id] = {
            content: cacheContent, 
            contentType: msg.content_type, 
            senderUsername: msg.sender_username
        };

        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message-item');
        messageDiv.setAttribute('data-message-id', msg.message_id);

        let replyInfoHtml = '';
        if (msg.reply_to_info) {
            replyInfoHtml = `
                <div class="message-is-reply">
                    <div class="reply-sender-name">Replying to ${msg.reply_to_info.original_sender}</div>
                    <div class="reply-snippet-content">${msg.reply_to_info.original_content_snippet}</div>
                </div>`;
        }

        let contentHtml = '';
        let messageControlsHtml = '';

        if (msg.content_type === 'deleted') {
            messageDiv.classList.add('deleted-message');
            contentHtml = `<div class="message-content">${msg.content}</div>`;
        } else {
            messageDiv.classList.add(msg.sender_id === currentUserId ? 'sent' : 'received');
            if (msg.content_type === 'text') {
                contentHtml = `<div class="message-content">${msg.content.replace(/\n/g, '<br>')}</div>`;
            } else if (msg.content_type === 'image') {
                contentHtml = `<div class="message-content"><img src="uploads/chat_images/${msg.content}" alt="Chat Image" class="chat-image"></div>`;
            } else {
                contentHtml = `<div class="message-content"><em>Unsupported message type: ${msg.content_type}</em></div>`;
            }
            
            // Updated messageControlsHtml to include react button
            let reactButtonHtml = `<span class="react-btn" data-message-id="${msg.message_id}" title="React">😀</span>`;
            let replyButtonHtml = `<span class="reply-btn" data-message-id="${msg.message_id}" data-sender-username="${msg.sender_username}" title="Reply">↩️</span>`;
            let deleteButtonHtml = '';
            if (msg.sender_id === currentUserId) {
                deleteButtonHtml = `<span class="delete-btn" data-message-id="${msg.message_id}" title="Delete Message">🗑️</span>`;
            }
            messageControlsHtml = `${reactButtonHtml} ${replyButtonHtml} ${deleteButtonHtml}`;
        }
        
        messageDiv.innerHTML = `
            ${replyInfoHtml}
            ${contentHtml}
            <div class="message-timestamp">${msg.timestamp} ${messageControlsHtml}</div>
        `;
        // Reactions display will be added by updateMessageReactionsDisplay, called after this.
        messagesArea.appendChild(messageDiv);
        updateMessageReactionsDisplay(messageDiv, msg.reactions || []); // Display initial/updated reactions

        if (msg.content_type !== 'deleted') {
            const reactBtn = messageDiv.querySelector('.react-btn');
            if (reactBtn) {
                reactBtn.addEventListener('click', function(e) {
                    e.stopPropagation(); 
                    createReactionPicker(this.getAttribute('data-message-id'), this);
                });
            }

            const replyBtn = messageDiv.querySelector('.reply-btn');
            if (replyBtn) {
                replyBtn.addEventListener('click', function() {
                    initiateReply(this.getAttribute('data-message-id'));
                });
            }
            if (msg.sender_id === currentUserId) {
                const deleteBtn = messageDiv.querySelector('.delete-btn');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function() {
                        deleteMessage(this.getAttribute('data-message-id'));
                    });
                }
            }
        }

        if (msg.message_id > lastLoadedMessageId) {
            lastLoadedMessageId = msg.message_id;
        }
    }

    function loadMessages() {
        if (messagesArea.querySelector('p')) {
             messagesArea.innerHTML = ''; 
        }
        fetch(`get_chat_messages.php?chat_id=${chatId}&last_message_id=${lastLoadedMessageId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error loading messages:', data.error);
                    return;
                }
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(displayMessage);
                    // If it's the initial full load or many messages loaded, scroll to bottom
                    if (lastLoadedMessageId === 0 || data.messages.length > 1) { // Heuristic
                         scrollToBottom();
                    }
                }
            })
            .catch(error => console.error('Fetch error:', error));
    }

    function initiateReply(messageId) {
        const originalMessage = originalMessagesCache[messageId];
        if (!originalMessage) {
            console.error("Original message not found in cache for ID:", messageId);
            return;
        }
        currentReplyToMessageId = messageId;
        replyContextUser.textContent = originalMessage.senderUsername;
        let snippet = originalMessage.content; 
        if (originalMessage.contentType === 'image') {
            snippet = '[Image]';
        } else if (originalMessage.contentType === 'deleted' || snippet === 'Message deleted') {
            snippet = '[Original message was deleted]';
        }
        replyContextSnippet.textContent = snippet.substring(0, 100) + (snippet.length > 100 ? '...' : '');
        replyContextArea.style.display = 'block';
        messageInput.focus();
    }

    function cancelReply() {
        currentReplyToMessageId = null;
        replyContextArea.style.display = 'none';
    }

    if (cancelReplyButton) {
        cancelReplyButton.addEventListener('click', cancelReply);
    }

    function sendMessage(isImageAttempt = false) {
        const messageText = messageInput.value.trim();
        const imageFile = imageInput.files && imageInput.files[0];
        if (messageText === '' && !imageFile) {
            if (isImageAttempt && !imageFile) alert("Please select an image file first.");
            return;
        }
        const formData = new FormData();
        formData.append('chat_id', chatId);
        formData.append('message', messageText); 
        if (imageFile) formData.append('image_file', imageFile);
        if (currentReplyToMessageId) {
            formData.append('reply_to_message_id', currentReplyToMessageId);
        }
        fetch('send_chat_message.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageInput.value = ''; 
                if (imageInput) imageInput.value = null; 
                messageInput.style.height = 'auto';
                // Don't call loadMessages() immediately, rely on polling or specific update
                // loadMessages(); 
                cancelReply(); 
                scrollToBottom(); // Scroll after sending a new message
            } else {
                console.error('Error sending message:', data.error);
                alert('Error sending message: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Send message fetch error:', error);
            alert('Failed to send message. Please check your connection.');
        });
    }

    function scrollToBottom() {
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }
    
    messageInput.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    sendMessageButton.addEventListener('click', function() { sendMessage(false); });
    messageInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault(); 
            sendMessage(false);
        }
    });

    if (imageAttachButton && imageInput) {
        imageAttachButton.addEventListener('click', function() {
            imageInput.click(); 
        });
        imageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                sendMessage(true); 
                // this.value = null; // Resetting here might be too soon if send fails
            }
        });
    }

    loadMessages();
    setInterval(loadMessages, 3000); 
    setTimeout(scrollToBottom, 500); 
});

function deleteMessage(messageId) { 
    if (!confirm("Are you sure you want to delete this message?")) return;
    const formData = new FormData();
    formData.append('message_id', messageId);
    formData.append('chat_id', chatId); 
    fetch('delete_message.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const messageElement = document.querySelector(`.message-item[data-message-id="${messageId}"]`);
            if (messageElement) {
                messageElement.classList.add('deleted-message');
                messageElement.classList.remove('sent', 'received');
                const contentElement = messageElement.querySelector('.message-content');
                if(contentElement) contentElement.innerHTML = 'Message deleted';
                
                const controlsContainer = messageElement.querySelector('.message-timestamp');
                if(controlsContainer){
                    const timestampText = controlsContainer.textContent.split(" ")[0] + " " + controlsContainer.textContent.split(" ")[1]; 
                    controlsContainer.innerHTML = timestampText; // Remove buttons
                }
                // Remove reaction display as well
                const reactionDisp = messageElement.querySelector('.message-reactions-display');
                if(reactionDisp) reactionDisp.remove();

                if(originalMessagesCache[messageId]){
                    originalMessagesCache[messageId].content = 'Message deleted';
                    originalMessagesCache[messageId].contentType = 'deleted';
                }
            }
        } else {
            alert('Error deleting message: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Delete message fetch error:', error);
        alert('Failed to delete message. Please check your connection.');
    });
}

// Reaction Functions
function createReactionPicker(messageId, reactButtonElement) {
    if (reactionPicker) reactionPicker.remove(); 

    reactionPicker = document.createElement('div');
    reactionPicker.classList.add('reaction-picker');
    
    ALLOWED_REACTIONS.forEach(emoji => {
        const emojiOption = document.createElement('span');
        emojiOption.classList.add('emoji-option');
        emojiOption.textContent = emoji;
        emojiOption.addEventListener('click', function(e) {
            e.stopPropagation(); 
            handleReaction(messageId, emoji);
            if (reactionPicker) reactionPicker.style.display = 'none';
        });
        reactionPicker.appendChild(emojiOption);
    });

    document.body.appendChild(reactionPicker); 

    const btnRect = reactButtonElement.getBoundingClientRect();
    reactionPicker.style.display = 'block';
    let topPos = window.scrollY + btnRect.top - reactionPicker.offsetHeight - 5;
    if (topPos < window.scrollY) { // If picker goes off-screen at the top
        topPos = window.scrollY + btnRect.bottom + 5; // Position below the button
    }
    reactionPicker.style.top = topPos + 'px';
    reactionPicker.style.left = (window.scrollX + btnRect.left + (btnRect.width / 2) - (reactionPicker.offsetWidth / 2)) + 'px';

    setTimeout(() => { 
        document.addEventListener('click', hideReactionPickerOnClickOutside, { once: true });
    }, 0);
}

function hideReactionPickerOnClickOutside(event) {
    if (reactionPicker && !reactionPicker.contains(event.target) && reactionPicker.style.display === 'block') {
        // Check if the click was on a react-btn itself, if so, the picker was likely re-opened by its own handler
        if (event.target.classList.contains('react-btn')) {
             // Re-add listener because the one that fired was {once: true}
             setTimeout(() => {document.addEventListener('click', hideReactionPickerOnClickOutside, { once: true });},0);
             return;
        }
        reactionPicker.style.display = 'none';
    }
}

function handleReaction(messageId, emoji) {
    const formData = new FormData();
    formData.append('message_id', messageId);
    formData.append('emoji', emoji);

    fetch('handle_reaction.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const messageElement = messagesArea.querySelector(`.message-item[data-message-id="${messageId}"]`);
            if (messageElement) {
                updateMessageReactionsDisplay(messageElement, data.reactions);
            }
             // Update cache for this message's reactions
            if (originalMessagesCache[messageId]) {
                originalMessagesCache[messageId].reactions = data.reactions;
            }
        } else {
            alert('Error reacting to message: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Reaction fetch error:', error);
        alert('Failed to react. Please check your connection.');
    });
}

function updateMessageReactionsDisplay(messageElement, reactionsData) {
    let reactionsDisplayDiv = messageElement.querySelector('.message-reactions-display');
    if (!reactionsDisplayDiv) {
        reactionsDisplayDiv = document.createElement('div');
        reactionsDisplayDiv.classList.add('message-reactions-display');
        const timestampDiv = messageElement.querySelector('.message-timestamp');
        if (timestampDiv) {
             messageElement.insertBefore(reactionsDisplayDiv, timestampDiv);
        } else {
             messageElement.appendChild(reactionsDisplayDiv); 
        }
    }
    reactionsDisplayDiv.innerHTML = ''; 

    if (reactionsData && reactionsData.length > 0) {
        reactionsData.forEach(reaction => {
            const reactionBubble = document.createElement('span');
            reactionBubble.classList.add('reaction-bubble');
            if (reaction.user_has_reacted) {
                reactionBubble.classList.add('user-reacted');
            }
            reactionBubble.textContent = reaction.emoji;
            const countSpan = document.createElement('span');
            countSpan.classList.add('reaction-count');
            countSpan.textContent = reaction.count > 1 ? reaction.count : ''; 
            reactionBubble.appendChild(countSpan);

            reactionBubble.addEventListener('click', function(e) {
                e.stopPropagation();
                handleReaction(messageElement.getAttribute('data-message-id'), reaction.emoji);
            });
            reactionsDisplayDiv.appendChild(reactionBubble);
        });
    }
}
```
