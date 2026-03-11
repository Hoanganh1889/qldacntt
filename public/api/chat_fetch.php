<?php
session_start();
require_once __DIR__.'/../../config/db.php';
if (!isset($_SESSION['user'])) exit;

$uid = (int)$_SESSION['user']['id'];
$chatWith = (int)($_GET['uid'] ?? 0);

if ($chatWith<=0) {
  echo json_encode([]);
  exit;
}

$res = $conn->query("
SELECT *
FROM chat_messages
WHERE room_id IS NULL
AND (
  (sender_id=$uid AND receiver_id=$chatWith)
  OR
  (sender_id=$chatWith AND receiver_id=$uid)
)
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
