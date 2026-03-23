<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập']);
    exit;
}

$userId = (int)$_SESSION['user']['id'];
$name = trim($_POST['name'] ?? '');

if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Tên phòng trống']);
    exit;
}

try {
    $conn->begin_transaction();

    // ✅ dùng room_name (đúng DB bạn)
    $stmt = $conn->prepare("INSERT INTO chat_rooms (room_name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();

    $roomId = $stmt->insert_id;
    $stmt->close();

    // ✅ dùng chat_room_id (đúng DB bạn)
    $stmt2 = $conn->prepare("
        INSERT INTO chat_room_members (chat_room_id, user_id)
        VALUES (?, ?)
    ");
    $stmt2->bind_param("ii", $roomId, $userId);
    $stmt2->execute();
    $stmt2->close();

    $conn->commit();

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}