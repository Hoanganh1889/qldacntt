<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$uid = (int)$_SESSION['user']['id'];

$unread = (int)($conn->query("
    SELECT COUNT(*) c FROM notifications
    WHERE user_id=$uid AND is_read=0
")->fetch_assoc()['c'] ?? 0);

$list = [];
$res = $conn->query("
    SELECT id, title, content, link, is_read, created_at
    FROM notifications
    WHERE user_id=$uid
    ORDER BY created_at DESC
    LIMIT 10
");

while ($row = $res->fetch_assoc()) {
    $list[] = $row;
}

echo json_encode([
    "unread" => $unread,
    "items"  => $list
], JSON_UNESCAPED_UNICODE);
