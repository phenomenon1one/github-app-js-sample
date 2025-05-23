<?php
session_start(); // Start the session

// Simple way to get a username, replace with a real login system later
if (!isset($_SESSION['username'])) {
    // For now, if no username is set, prompt the user or assign a guest name
    // This part would ideally be handled by a login page
    if (isset($_POST['username']) && !empty($_POST['username'])) {
        $_SESSION['username'] = $_POST['username'];
    } else {
        // If submitted via a form that includes username, use it.
        // Otherwise, default to 'Guest'. This is a simplification.
        $_SESSION['username'] = 'Guest'; 
    }
}

if (isset($_POST['usermsg']) && !empty($_POST['usermsg'])) {
    $username = $_SESSION['username'];
    $message = $_POST['usermsg'];
    $timestamp = date('Y-m-d H:i:s'); // Get current timestamp

    // Format the message: Timestamp [Username]: Message
    $formatted_message = $timestamp . " [" . $username . "]: " . $message . "\n";

    // Append the message to a file
    $file = 'messages.txt';
    if (file_put_contents($file, $formatted_message, FILE_APPEND | LOCK_EX)) {
        echo "Message sent.";
    } else {
        // It's good practice to provide error feedback
        header("HTTP/1.1 500 Internal Server Error");
        echo "Error sending message.";
    }
} else {
    // It's good practice to handle cases where data is missing
    header("HTTP/1.1 400 Bad Request");
    echo "No message content provided.";
}
?>
