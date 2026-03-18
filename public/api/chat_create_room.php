<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$room_name = $_POST['name'] ?? '';

// Kiểm tra tên phòng
if (empty($room_name)) {
    echo json_encode(['status' => 'error', 'message' => 'Room name is required']);
    exit;
}

// Chèn phòng chat mới vào cơ sở dữ liệu
$query = "INSERT INTO chat_rooms (name) VALUES (?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $room_name);

if ($stmt->execute()) {
    // Lấy ID của phòng chat mới tạo
    $room_id = $stmt->insert_id;

    // Thêm người dùng vào phòng chat
    $query_members = "INSERT INTO chat_room_members (room_id, user_id) VALUES (?, ?)";
    $stmt_members = $conn->prepare($query_members);
    $stmt_members->bind_param("ii", $room_id, $user_id);
    $stmt_members->execute();

    $stmt_members->close();
    $stmt->close();

    // Trả về thông báo thành công
    echo json_encode(['status' => 'success', 'message' => 'Room created successfully', 'room_id' => $room_id]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create room']);
}