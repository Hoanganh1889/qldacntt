<?php
// Tắt hiển thị lỗi trực tiếp để không làm hỏng định dạng JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
session_start();

// Kết nối database - Đường dẫn từ public/api/ đi ngược ra 2 cấp vào config/
require_once __DIR__ . '/../../config/db.php';

// 1. Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn chưa đăng nhập']);
    exit;
}

$user_id = (int)$_SESSION['user']['id'];
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$receiver_id = isset($_POST['uid']) ? (int)$_POST['uid'] : 0;

// 2. Kiểm tra dữ liệu đầu vào
if (empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'Nội dung tin nhắn không được để trống']);
    exit;
}

if ($room_id === 0 && $receiver_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Không xác định được người nhận hoặc phòng chat']);
    exit;
}

try {
    // Chuẩn bị câu lệnh SQL dựa trên cấu trúc bảng trong ảnh của bạn
    // Cột: user_id, room_id, receiver_id, message, created_at
    $sql = "INSERT INTO chat_messages (user_id, room_id, receiver_id, message, created_at) 
            VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    
    // Nếu là chat cá nhân thì room_id = NULL, nếu chat nhóm thì receiver_id = NULL
    $r_id = ($room_id > 0) ? $room_id : null;
    $u_dest_id = ($receiver_id > 0) ? $receiver_id : null;

    $stmt->bind_param("iiis", $user_id, $r_id, $u_dest_id, $message);

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Đã gửi tin nhắn',
            'data' => [
                'user_id' => $user_id,
                'message' => $message
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi thực thi SQL: ' . $conn->error]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}

$conn->close();