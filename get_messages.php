<?php
$file = 'messages.txt';

// Check if the message file exists
if (file_exists($file)) {
    // Read the entire file into a string
    $messages = file_get_contents($file);
    
    // It's good practice to set the content type header
    header('Content-Type: text/plain; charset=utf-8');
    
    // Output the messages
    // We'll send raw text; the client-side will handle formatting if needed (e.g., splitting into lines)
    echo $messages;
} else {
    // If the file doesn't exist, it means no messages yet, or an error.
    // Send an empty response or a specific message.
    header('Content-Type: text/plain; charset=utf-8');
    echo ""; // No messages yet
}
?>
