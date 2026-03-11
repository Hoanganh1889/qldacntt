<?php
session_start();
require_once __DIR__.'/../../config/db.php';
if (!isset($_SESSION['user'])) exit;

$uid = (int)$_SESSION['user']['id'];
$message = trim($_POST['message'] ?? '');

$roomId = isset($_POST['room_id']) ? (int)$_POST['room_id'] : null;
$toUid  = isset($_POST['uid']) ? (int)$_POST['uid'] : null;

/* ===== FILE ===== */
$filePath = null;
$fileName = null;

if (!empty($_FILES['file']['name'])) {
    $dir = __DIR__.'/../../uploads/chat/';
    if (!is_dir($dir)) mkdir($dir,0777,true);

    $fileName = $_FILES['file']['name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allow = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','zip','rar'];

    if (in_array($ext,$allow)) {
        $filePath = time().'_'.uniqid().'.'.$ext;
        move_uploaded_file($_FILES['file']['tmp_name'], $dir.$filePath);
    }
}

if ($message==='' && !$filePath) exit;

/* ===== INSERT ===== */
$stmt = $conn->prepare("
INSERT INTO chat_messages
(sender_id, receiver_id, room_id, message, file_path, file_name)
VALUES (?,?,?,?,?,?)
");

$stmt->bind_param(
  "iiisss",
  $uid,
  $toUid,
  $roomId,
  $message,
  $filePath,
  $fileName
);
$stmt->execute();

echo 'OK';
