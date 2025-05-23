<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not authenticated.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

if (strlen($query) < 1) { // Or a higher minimum length like 2 or 3
    echo json_encode(['users' => []]); // Return empty if query too short
    exit;
}

$search_term = "%" . $query . "%";
$users = [];

// Search by username or email, excluding the current user
$stmt = $mysqli->prepare("SELECT user_id, username, email FROM users WHERE (username LIKE ? OR email LIKE ?) AND user_id != ?");
$stmt->bind_param("ssi", $search_term, $search_term, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $users[] = [
        'user_id' => $row['user_id'],
        'username' => $row['username'],
        // 'email' => $row['email'] // Optionally include email
    ];
}

$stmt->close();
$mysqli->close();

echo json_encode(['users' => $users]);
?>
