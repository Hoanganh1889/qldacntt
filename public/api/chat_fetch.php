<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$room_id = (int)$_GET['room'] ?? null;
$chat_with = (int)$_GET['uid'] ?? null;

// Truy vấn để lấy tin nhắn từ phòng chat
if ($room_id) {
    $query = "SELECT m.id, m.user_id, m.message, m.file_path, m.file_name, m.created_at AS time, u.username, u.profile_picture, 1 AS me
              FROM messages m
              JOIN users u ON u.id = m.user_id
              WHERE m.room_id = ? ORDER BY m.created_at";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $room_id);
} else if ($chat_with) {
    // Truy vấn để lấy tin nhắn trong chat riêng
    $query = "SELECT m.id, m.user_id, m.message, m.file_path, m.file_name, m.created_at AS time, u.username, u.profile_picture, 
                     CASE WHEN m.user_id = ? THEN 1 ELSE 0 END AS me
              FROM messages m
              JOIN users u ON u.id = m.user_id
              WHERE (m.user_id = ? AND m.chat_with = ?) OR (m.user_id = ? AND m.chat_with = ?)
              ORDER BY m.created_at";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiii", $user_id, $user_id, $chat_with, $chat_with, $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'message' => $row['message'],
        'file_path' => $row['file_path'],
        'file_name' => $row['file_name'],
        'time' => date('H:i', strtotime($row['time'])),
        'username' => $row['username'],
        'profile_picture' => $row['profile_picture'],
        'me' => (int)$row['me']
    ];
}

$stmt->close();

// Trả về các tin nhắn dưới dạng JSON
echo json_encode($messages);