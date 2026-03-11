<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit;
}

$uid  = (int)$_SESSION['user']['id'];
$name = trim($_POST['name'] ?? '');

if ($name === '') {
    http_response_code(400);
    exit;
}

/* 1️⃣ TẠO PHÒNG */
$stmt = $conn->prepare("
    INSERT INTO chat_rooms (name, type, created_at)
    VALUES (?, 'global', NOW())
");
$stmt->bind_param("s", $name);
$stmt->execute();

$roomId = $stmt->insert_id;

/* 2️⃣ THÊM NGƯỜI TẠO VÀO PHÒNG */
$stmt2 = $conn->prepare("
    INSERT INTO chat_room_members (room_id, user_id)
    VALUES (?, ?)
");
$stmt2->bind_param("ii", $roomId, $uid);
$stmt2->execute();

echo 'OK';
