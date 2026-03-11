<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user']['id'];
$roomId   = (int)($_GET['room'] ?? 0);
$chatWith = (int)($_GET['uid'] ?? 0);

/* ROOMS */
$rooms = $conn->query("
    SELECT r.*
    FROM chat_rooms r
    JOIN chat_room_members m ON m.room_id = r.id
    WHERE m.user_id = $uid
    ORDER BY r.name
");

/* USERS */
$users = $conn->query("
    SELECT id, username
    FROM users
    WHERE id <> $uid
    ORDER BY username
");
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Chat</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/sidebar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
.chat-layout{height:calc(100vh - 140px); background:#eef1f5;}
.chat-sidebar{background:#fff; border-right:1px solid #e5e7eb;}
.chat-main{display:flex; flex-direction:column; background:#f0f2f5;}
.chat-header{background:#fff; border-bottom:1px solid #e5e7eb; padding:10px 12px; font-weight:600;}

.chat-box{
  flex:1;
  overflow-y:auto;
  padding:18px;
  display:flex;
  flex-direction:column;
  gap:8px;
}

.msg-row{display:flex;}
.msg-row.me{justify-content:flex-end;}
.msg-row.other{justify-content:flex-start;}

.bubble{
  max-width:68%;
  display:inline-block;
  padding:8px 12px;
  border-radius:16px;
  font-size:14px;
  line-height:1.4;
  word-break:break-word;
}
.bubble.me{
  background:#0084ff;
  color:#fff;
  border-bottom-right-radius:6px;
}
.bubble.other{
  background:#fff;
  border-bottom-left-radius:6px;
  box-shadow:0 1px 3px rgba(0,0,0,.08);
}

.bubble img{
  max-width:260px;
  border-radius:12px;
  margin-bottom:6px;
  display:block;
}
.bubble a{color:inherit; text-decoration:none; font-weight:600;}
.bubble a:hover{text-decoration:underline;}

.meta{
  font-size:11px;
  opacity:.7;
  margin-top:4px;
  display:flex;
  justify-content:flex-end;
  gap:6px;
  align-items:center;
}

/* delete */
.delete-btn{
  border:none;
  background:#fff;
  color:#ef4444;
  width:26px;
  height:26px;
  border-radius:50%;
  display:none;
  cursor:pointer;
  margin-left:6px;
}
.msg-row.me:hover .delete-btn{display:inline-flex; justify-content:center; align-items:center;}

/* input */
.chat-input{background:#fff; border-top:1px solid #e5e7eb; padding:10px 12px;}
.input-wrap{
  display:flex;
  gap:8px;
  align-items:center;
  background:#f3f4f6;
  border-radius:999px;
  padding:6px;
}
.input-wrap input{
  border:none;
  outline:none;
  background:transparent;
  flex:1;
}
.btn-round{width:40px;height:40px;border-radius:50%;}

/* file preview */
.file-preview{
  margin-top:6px;
  background:#f3f4f6;
  border:1px solid #e5e7eb;
  border-radius:10px;
  padding:6px 10px;
  font-size:13px;
  display:flex;
  align-items:center;
  gap:8px;
}
.file-preview button{
  border:none;
  background:none;
  cursor:pointer;
  color:#ef4444;
}

/* sidebar */
.chat-item{padding:8px 10px;border-radius:10px;cursor:pointer;}
.chat-item:hover{background:#f1f5f9;}
.chat-item.active{background:#e0e7ff;font-weight:700;}
</style>
</head>
<body>

<?php include __DIR__.'/layouts/header.php'; ?>
<?php include __DIR__.'/layouts/sidebar.php'; ?>

<div class="content-wrapper">
<div class="row g-0 chat-layout">

<!-- SIDEBAR -->
<div class="col-md-3 chat-sidebar p-3">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <span class="fw-semibold">📁 Phòng</span>
    <button class="btn btn-sm btn-outline-primary"
      onclick="showCreateRoom()">+ Phòng</button>
  </div>

  <?php while($r=$rooms->fetch_assoc()): ?>
    <a href="?room=<?=$r['id']?>" class="text-decoration-none text-dark">
      <div class="chat-item <?=$roomId==$r['id']?'active':''?>">
        # <?=htmlspecialchars($r['name'])?>
      </div>
    </a>
  <?php endwhile; ?>

  <hr>

  <div class="fw-semibold mb-2">👤 Chat riêng</div>
  <?php while($u=$users->fetch_assoc()): ?>
    <a href="?uid=<?=$u['id']?>" class="text-decoration-none text-dark">
      <div class="chat-item <?=$chatWith==$u['id']?'active':''?>">
        <?=htmlspecialchars($u['username'])?>
      </div>
    </a>
  <?php endwhile; ?>
</div>

<!-- MAIN -->
<div class="col-md-9 chat-main">
  <div class="chat-header">
    <?= $roomId ? 'Phòng chat' : ($chatWith ? 'Chat riêng' : 'Chat') ?>
  </div>

  <div id="chatBox" class="chat-box"></div>

  <?php if($roomId || $chatWith): ?>
  <form id="chatForm" class="chat-input" enctype="multipart/form-data">
    <div class="input-wrap">
      <input type="file" id="fileInput" name="file" hidden>

      <button type="button" class="btn btn-light btn-round"
        onclick="fileInput.click()">
        <i class="fa-solid fa-paperclip"></i>
      </button>

      <input id="msgInput" name="message" placeholder="Nhập tin nhắn...">

      <button class="btn btn-primary btn-round">
        <i class="fa-solid fa-paper-plane"></i>
      </button>
    </div>

    <div id="filePreview" class="file-preview d-none">
      <i class="fa-solid fa-file"></i>
      <span id="fileName"></span>
      <button type="button" onclick="clearFile()">✖</button>
    </div>
  </form>
  <?php endif; ?>
</div>

</div>
</div>

<!-- MODAL CREATE ROOM -->
<div class="modal fade" id="roomModal">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title">Tạo phòng chung</h6></div>
      <div class="modal-body">
        <input id="roomName" class="form-control" placeholder="Tên phòng...">
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button class="btn btn-primary" onclick="createRoom()">Tạo</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
const roomId   = <?= (int)$roomId ?>;
const chatWith = <?= (int)$chatWith ?>;
const chatBox  = document.getElementById('chatBox');
const form     = document.getElementById('chatForm');
const input    = document.getElementById('msgInput');
const fileInput= document.getElementById('fileInput');

/* FILE PREVIEW */
fileInput.addEventListener('change', ()=>{
  if(fileInput.files.length){
    document.getElementById('fileName').innerText = fileInput.files[0].name;
    document.getElementById('filePreview').classList.remove('d-none');
  }
});
function clearFile(){
  fileInput.value='';
  document.getElementById('filePreview').classList.add('d-none');
}

/* API */
function fetchUrl(){
  if(roomId>0) return 'api/chat_room_fetch.php?room='+roomId;
  if(chatWith>0) return 'api/chat_fetch.php?uid='+chatWith;
}

/* LOAD */
async function load(){
  const res = await fetch(fetchUrl(),{cache:'no-store'});
  const data = await res.json();
  chatBox.innerHTML='';

  data.forEach(m=>{
    const row=document.createElement('div');
    row.className='msg-row '+(m.me?'me':'other');

    const bubble=document.createElement('div');
    bubble.className='bubble '+(m.me?'me':'other');

    let html='';
    if(m.file_path){
      if(/\.(jpg|jpeg|png|gif|webp)$/i.test(m.file_path))
        html+=`<img src="uploads/chat/${m.file_path}">`;
      else
        html+=`📎 <a href="uploads/chat/${m.file_path}" target="_blank">${m.file_name}</a>`;
    }
    if(m.message) html+=`<div>${m.message}</div>`;
    html+=`<div class="meta">${m.time}</div>`;
    bubble.innerHTML=html;

    row.appendChild(bubble);

    if(m.me){
      const del=document.createElement('button');
      del.className='delete-btn';
      del.innerHTML='<i class="fa-solid fa-trash"></i>';
      del.onclick=()=>deleteMsg(m.id);
      row.appendChild(del);
    }

    chatBox.appendChild(row);
  });

  chatBox.scrollTop=chatBox.scrollHeight;
}

/* SEND */
if(form){
form.onsubmit=async e=>{
  e.preventDefault();
  if(!input.value.trim() && !fileInput.files.length) return;

  const fd=new FormData(form);
  if(roomId>0) fd.append('room_id',roomId);
  else fd.append('uid',chatWith);

  await fetch('api/chat_send.php',{method:'POST',body:fd});
  input.value='';
  clearFile();
  load();
};
}

/* DELETE */
async function deleteMsg(id){
  if(!confirm('Xóa tin nhắn?')) return;
  const fd=new FormData();
  fd.append('id',id);
  await fetch('api/chat_delete.php',{method:'POST',body:fd});
  load();
}

/* CREATE ROOM */
function showCreateRoom(){
  new bootstrap.Modal(document.getElementById('roomModal')).show();
}
async function createRoom(){
  const name=document.getElementById('roomName').value.trim();
  if(!name) return;
  const fd=new FormData();
  fd.append('name',name);
  await fetch('api/chat_create_room.php',{method:'POST',body:fd});
  location.reload();
}

load();
setInterval(load,2000);
</script>

</body>
</html>
