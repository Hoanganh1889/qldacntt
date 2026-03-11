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
$id  = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read=1
        WHERE id=? AND user_id=?
    ");
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();
}

echo json_encode(["ok" => true], JSON_UNESCAPED_UNICODE);
