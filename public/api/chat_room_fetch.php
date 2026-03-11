<?php
session_start();
require_once __DIR__.'/../../config/db.php';
if (!isset($_SESSION['user'])) exit;

$uid = (int)$_SESSION['user']['id'];
$roomId = (int)($_GET['room'] ?? 0);

if ($roomId<=0) {
  echo json_encode([]);
  exit;
}

/* kiểm tra thành viên */
$check = $conn->query("
SELECT 1 FROM chat_room_members
WHERE room_id=$roomId AND user_id=$uid
");
if ($check->num_rows==0) {
  http_response_code(403);
  exit;
}

$res = $conn->query("
SELECT *
FROM chat_messages
WHERE room_id=$roomId
ORDER BY created_at ASC
");

$data=[];
while($r=$res->fetch_assoc()){
  $data[]=[
    'id'=>$r['id'],
    'message'=>$r['message'],
    'file_path'=>$r['file_path'],
    'file_name'=>$r['file_name'],
    'me'=>$r['sender_id']==$uid,
    'time'=>date('H:i',strtotime($r['created_at']))
  ];
}

header('Content-Type:application/json');
echo json_encode($data);
