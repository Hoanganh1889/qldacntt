<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$message = $_POST['message'] ?? '';
$file = $_FILES['file'] ?? null;
$room_id = $_POST['room_id'] ?? null;
$chat_with = $_POST['uid'] ?? null;

// Kiểm tra nếu tin nhắn rỗng và không có file
if (!$message && !$file) {
    echo json_encode(['status' => 'error', 'message' => 'Message or file is required']);
    exit;
}

// Xử lý upload file nếu có
$file_path = null;
$file_name = null;

if ($file && $file['error'] == UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../uploads/chat/';
    $file_name = basename($file['name']);
    $file_path = 'chat/' . $file_name;

    // Di chuyển file từ tạm thời sang thư mục uploads
    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $file_name)) {
        echo json_encode(['status' => 'error', 'message' => 'File upload failed']);
        exit;
    }
}

// Chèn tin nhắn vào cơ sở dữ liệu
if ($room_id) {
    // Tin nhắn trong phòng chat
    $query = "INSERT INTO messages (user_id, room_id, message, file_path, file_name) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iisss", $user_id, $room_id, $message, $file_path, $file_name);
} else {
    // Tin nhắn trong chat riêng
    $query = "INSERT INTO messages (user_id, chat_with, message, file_path, file_name) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iisss", $user_id, $chat_with, $message, $file_path, $file_name);
}

$stmt->execute();
$stmt->close();

// Phản hồi thành công
echo json_encode(['status' => 'success', 'message' => 'Message sent']);