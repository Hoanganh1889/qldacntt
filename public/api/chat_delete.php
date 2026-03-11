<?php
session_start();
require_once __DIR__.'/../../config/db.php';
if (!isset($_SESSION['user'])) exit;

$uid = (int)$_SESSION['user']['id'];
$id  = (int)($_POST['id'] ?? 0);

if ($id<=0) exit;

/* lấy file để xóa vật lý */
$res = $conn->query("
SELECT file_path FROM chat_messages
WHERE id=$id AND sender_id=$uid
");
if ($res->num_rows){
  $row=$res->fetch_assoc();
  if ($row['file_path']){
    $path=__DIR__.'/../../uploads/chat/'.$row['file_path'];
    if (file_exists($path)) unlink($path);
  }
}

/* xóa DB */
$conn->query("
DELETE FROM chat_messages
WHERE id=$id AND sender_id=$uid
");

echo 'OK';
