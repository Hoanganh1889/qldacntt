<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$roomId = isset($_GET['room']) ? (int)$_GET['room'] : 0;
$chatWith = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;

if ($roomId > 0) {
    $stmt = $conn->prepare("SELECT m.*, u.username FROM chat_messages m JOIN users u ON m.user_id = u.id WHERE m.room_id = ? ORDER BY m.created_at ASC");
    $stmt->bind_param("i", $roomId);
} else {
    $stmt = $conn->prepare("SELECT m.*, u.username FROM chat_messages m JOIN users u ON m.user_id = u.id WHERE (m.room_id = 0 AND (m.user_id = ? OR m.user_id = ?)) ORDER BY m.created_at ASC");
    $stmt->bind_param("ii", $_SESSION['user']['id'], $chatWith);
}

$stmt->execute();
$result = $stmt->get_result();
$messages = [];

while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode($messages);

$stmt->close();
?>