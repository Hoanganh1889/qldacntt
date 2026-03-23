<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$myId = (int)$_SESSION['user']['id'];
$roomId = (int)($_GET['room_id'] ?? 0);
$chatWith = (int)($_GET['uid'] ?? 0);

if ($roomId > 0) {
    $sql = "SELECT m.*, u.username FROM chat_messages m 
            JOIN users u ON m.user_id = u.id 
            WHERE m.room_id = $roomId ORDER BY m.created_at ASC";
} else {
    $sql = "SELECT m.*, u.username FROM chat_messages m 
            JOIN users u ON m.user_id = u.id 
            WHERE (m.user_id = $myId AND m.receiver_id = $chatWith) 
            OR (m.user_id = $chatWith AND m.receiver_id = $myId) 
            ORDER BY m.created_at ASC";
}

$result = $conn->query($sql);
$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
echo json_encode($messages);
?>