<?php
// Database configuration
// IMPORTANT: Replace with your actual InfinityFree database credentials!
define('DB_SERVER', 'sqlXXX.infinityfree.com'); // e.g., sql101.infinityfree.com
define('DB_USERNAME', 'if0_XXXXXXXX');       // Your InfinityFree username, e.g., if0_12345678
define('DB_PASSWORD', 'YOUR_PASSWORD');       // The password you set for the database in cPanel
define('DB_NAME', 'if0_XXXXXXXX_chat');     // Your database name, e.g., if0_12345678_mydb

// Attempt to connect to MySQL database
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($mysqli === false){
    // Don't output detailed errors in production for security reasons
    // Log error to a file or use a more robust error handling mechanism
    error_log("ERROR: Could not connect to database. " . $mysqli->connect_error);
    die("ERROR: Could not connect. Please try again later."); // User-friendly message
}

// Set charset to utf8mb4 for emoji support and broader character sets
if (!$mysqli->set_charset("utf8mb4")) {
    error_log("Error loading character set utf8mb4: %s\n", $mysqli->error);
    // Continue without it if it fails, but some characters might not work
}

// Optional: You can include a function here for easy querying
// function query($sql, $params = []) {
//     global $mysqli;
//     $stmt = $mysqli->prepare($sql);
//     if ($params) {
//         $types = str_repeat('s', count($params)); // Adjust types if not all strings
//         $stmt->bind_param($types, ...$params);
//     }
//     $stmt->execute();
//     return $stmt->get_result();
// }

// Example usage (you can remove this later):
// if ($mysqli) {
//     echo "Connected successfully to " . DB_NAME . ".";
// }
?>
