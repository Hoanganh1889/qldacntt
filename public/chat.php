<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user']['id'];
$roomId = (int)($_GET['room'] ?? 0);
$chatWith = (int)($_GET['uid'] ?? 0);

// Lấy danh sách phòng của người dùng
$rooms = $conn->query("
    SELECT r.*
    FROM chat_rooms r
    JOIN chat_room_members m ON m.room_id = r.id
    WHERE m.user_id = $uid
    ORDER BY r.name
");

// Lấy danh sách người dùng (bao gồm admin và user)
$users = $conn->query("
    SELECT id, username, role, avatar
    FROM users
    WHERE id <> $uid
    ORDER BY username
");

?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hệ thống Chat Nội bộ | Premium UI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --chat-primary: #6366f1;
            --chat-bg: #f3f4f6;
            --sidebar-width: 320px;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--chat-bg);
            color: #1f2937;
        }

        .chat-container {
            height: calc(100vh - 100px);
            margin: 20px;
            display: flex;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* Sidebar Styles */
        .chat-sidebar {
            width: var(--sidebar-width);
            background: #fff;
            border-right: 1px solid #f3f4f6;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }

        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid #f3f4f6;
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }

        .section-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
            font-weight: 700;
            margin: 20px 12px 10px;
        }

        .chat-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-radius: 12px;
            text-decoration: none;
            color: #4b5563;
            transition: all 0.2s ease;
            margin-bottom: 4px;
            gap: 12px;
        }

        .chat-item:hover {
            background: #f9fafb;
            color: var(--chat-primary);
        }

        .chat-item.active {
            background: #eef2ff;
            color: var(--chat-primary);
            font-weight: 600;
        }

        .item-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .active .item-icon {
            background: var(--chat-primary);
            color: #fff;
        }

        /* Main Chat Styles */
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        .chat-header {
            padding: 16px 24px;
            background: #fff;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-box {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            background: #fafafa;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* Message Bubbles */
        .msg-row { display: flex; width: 100%; animation: fadeIn 0.3s ease; }
        .msg-row.me { justify-content: flex-end; }

        .bubble {
            max-width: 65%;
            padding: 12px 18px;
            border-radius: 16px;
            font-size: 0.95rem;
            line-height: 1.5;
            position: relative;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .bubble.me {
            background: var(--chat-primary);
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .bubble.other {
            background: #fff;
            border: 1px solid #f3f4f6;
            border-bottom-left-radius: 4px;
        }

        .msg-user {
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: #6366f1;
        }

        .meta {
            font-size: 0.7rem;
            margin-top: 6px;
            opacity: 0.7;
            display: block;
            text-align: right;
        }

        /* Input Area */
        .chat-footer {
            padding: 20px 24px;
            background: #fff;
            border-top: 1px solid #f3f4f6;
        }

        .input-wrapper {
            background: #f3f4f6;
            border-radius: 16px;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: ring 0.2s;
        }

        .input-wrapper:focus-within {
            ring: 2px solid var(--chat-primary);
            background: #fff;
            box-shadow: 0 0 0 2px #eef2ff;
        }

        .input-wrapper input[type="text"] {
            flex: 1;
            border: none;
            background: transparent;
            padding: 8px 0;
            outline: none;
            font-size: 0.95rem;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            background: transparent;
            color: #6b7280;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-btn:hover {
            background: #eef2ff;
            color: var(--chat-primary);
        }

        .btn-send {
            background: var(--chat-primary);
            color: #fff;
        }

        .btn-send:hover {
            background: #4f46e5;
            color: #fff;
            transform: scale(1.05);
        }

        .file-preview-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 8px 12px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body>

<?php include __DIR__.'/layouts/header.php'; ?>
<?php include __DIR__.'/layouts/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="chat-container">
        
        <!-- Sidebar -->
        <div class="chat-sidebar">
            <div class="sidebar-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Tin nhắn</h5>
                    <button class="action-btn" onclick="openRoomModal()">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
            </div>
            
            <div class="sidebar-content">
                <div class="section-title">Phòng thảo luận</div>
                <?php while($r = $rooms->fetch_assoc()): ?>
                    <a href="?room=<?=$r['id']?>" class="chat-item <?=$roomId == $r['id'] ? 'active' : ''?>">
                        <div class="item-icon"><i class="fa-solid fa-hashtag"></i></div>
                        <span><?=htmlspecialchars($r['name'])?></span>
                    </a>
                <?php endwhile; ?>

                <div class="section-title">Thành viên</div>
                <?php while($u = $users->fetch_assoc()): ?>
                    <a href="?uid=<?=$u['id']?>" class="chat-item <?=$chatWith == $u['id'] ? 'active' : ''?>">
                        <div class="item-icon"><i class="fa-solid fa-user"></i></div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <span><?=htmlspecialchars($u['username'])?></span>
                                <?php if($u['role'] == 'admin'): ?>
                                    <span class="badge bg-soft-danger text-danger" style="font-size: 8px; background: #fee2e2;">Admin</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="chat-main">
            <div class="chat-header">
                <div class="header-info">
                    <div class="item-icon" style="background: #eef2ff; color: var(--chat-primary)">
                        <i class="fa-solid <?= $roomId ? 'fa-users' : 'fa-comment' ?>"></i>
                    </div>
                    <div>
                        <div class="fw-bold">
                            <?= $roomId ? 'Kênh thảo luận' : ($chatWith ? 'Trò chuyện cá nhân' : 'Hệ thống Chat') ?>
                        </div>
                        <small class="text-muted">Đang trực tuyến</small>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="action-btn"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                </div>
            </div>

            <div id="chatBox" class="chat-box">
                <!-- Nội dung chat -->
            </div>

            <?php if($roomId || $chatWith): ?>
            <div class="chat-footer">
                <form id="chatForm">
                    <div id="fileInfo" class="file-preview-card d-none">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-file-invoice text-primary"></i>
                            <span id="fileNameDisplay" class="small fw-medium"></span>
                        </div>
                        <i class="fa-solid fa-times-circle text-muted cursor-pointer" onclick="cancelFile()"></i>
                    </div>
                    
                    <div class="input-wrapper">
                        <input type="file" id="fileInput" name="file" hidden onchange="handleFileSelect(this)">
                        <button type="button" class="action-btn" onclick="document.getElementById('fileInput').click()">
                            <i class="fa-solid fa-paperclip"></i>
                        </button>
                        
                        <input type="text" id="msgInput" name="message" placeholder="Nhập tin nhắn của bạn..." autocomplete="off">
                        
                        <button type="submit" class="action-btn btn-send">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="d-flex flex-column align-items-center justify-content-center h-100 text-center px-4">
                <div class="mb-4" style="font-size: 4rem; opacity: 0.1;"><i class="fa-solid fa-comments"></i></div>
                <h5 class="fw-bold">Bắt đầu cuộc trò chuyện</h5>
                <p class="text-muted small">Chọn một phòng hoặc thành viên ở thanh bên trái<br>để bắt đầu thảo luận công việc.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="roomModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 px-4 pt-4">
                <h5 class="modal-title fw-bold">Tạo kênh mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <label class="small fw-bold text-muted mb-2">TÊN KÊNH THẢO LUẬN</label>
                <input type="text" id="newRoomName" class="form-control form-control-lg border-0 bg-light" placeholder="Ví dụ: Backend-Dev" style="border-radius: 12px;">
            </div>
            <div class="modal-footer border-0 px-4 pb-4">
                <button type="button" class="btn btn-light btn-lg flex-grow-1" data-bs-dismiss="modal" style="border-radius: 12px;">Hủy</button>
                <button type="button" class="btn btn-primary btn-lg flex-grow-1" onclick="submitCreateRoom()" style="border-radius: 12px; background: var(--chat-primary);">Tạo ngay</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const currentRoom = <?=(int)$roomId?>;
const currentUid = <?=(int)$chatWith?>;
const chatBox = document.getElementById('chatBox');
const chatForm = document.getElementById('chatForm');

// Helper function để debug JSON lỗi
async function safeFetchJson(url, options = {}) {
    try {
        const response = await fetch(url, options);
        const text = await response.text(); // Lấy text thô trước
        try {
            return JSON.parse(text); // Cố gắng parse
        } catch (e) {
            console.error("Server trả về dữ liệu không phải JSON hợp lệ:");
            console.error(text); // In ra nội dung lỗi HTML để debug
            throw new Error("Invalid JSON response from server");
        }
    } catch (error) {
        throw error;
    }
}

async function submitCreateRoom() {
    const name = document.getElementById('newRoomName').value.trim();
    if(!name) return;

    const fd = new FormData();
    fd.append('name', name);

    try {
        const result = await safeFetchJson('api/chat_create_room.php', { method: 'POST', body: fd });
        if(result.status === 'success') location.reload();
        else alert(result.message);
    } catch (error) {
        console.error("Lỗi tạo phòng:", error);
    }
}

if(chatForm) {
    chatForm.onsubmit = async (e) => {
        e.preventDefault();
        const msg = document.getElementById('msgInput').value.trim();
        const fileInput = document.getElementById('fileInput');
        
        if(!msg && !fileInput.files.length) return;

        const fd = new FormData(chatForm);
        if(currentRoom > 0) fd.append('room_id', currentRoom);
        else fd.append('uid', currentUid);

        try {
            const result = await safeFetchJson('api/chat_send.php', { method: 'POST', body: fd });
            if(result.status === 'success') {
                document.getElementById('msgInput').value = '';
                cancelFile();
                loadMessages();
            }
        } catch (error) {
            console.error("Lỗi gửi tin nhắn:", error);
        }
    };
}

async function loadMessages() {
    if(!currentRoom && !currentUid) return;
    let url = currentRoom > 0 ? `api/chat_room_fetch.php?room=${currentRoom}` : `api/chat_fetch.php?uid=${currentUid}`;

    try {
        const data = await safeFetchJson(url);
        const isBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 100;
        
        chatBox.innerHTML = '';
        if (Array.isArray(data)) {
            data.forEach(m => {
                const row = document.createElement('div');
                row.className = `msg-row ${m.me ? 'me' : 'other'}`;
                
                let contentHtml = '';
                if(m.file_path) {
                    if(/\.(jpg|jpeg|png|gif)$/i.test(m.file_path)) {
                        contentHtml += `<img src="uploads/chat/${m.file_path}" style="max-width:280px; border-radius:12px; margin-bottom:8px; display:block;">`;
                    } else {
                        contentHtml += `<div class="p-2 bg-light rounded mb-2 small"><i class="fa-solid fa-file-arrow-down me-2"></i><a href="uploads/chat/${m.file_path}" target="_blank" class="text-primary text-decoration-none">${m.file_name}</a></div>`;
                    }
                }
                if(m.message) contentHtml += `<div>${m.message}</div>`;

                row.innerHTML = `
                    <div class="bubble ${m.me ? 'me' : 'other'}">
                        ${!m.me && currentRoom ? `<div class="msg-user">${m.username}</div>` : ''}
                        ${contentHtml}
                        <span class="meta">${m.time}</span>
                    </div>
                `;
                chatBox.appendChild(row);
            });
        }

        if(isBottom) chatBox.scrollTop = chatBox.scrollHeight;
    } catch (e) {
        console.warn("Lỗi khi tải tin nhắn:", e);
    }
}

function handleFileSelect(input) {
    if(input.files.length > 0) {
        document.getElementById('fileNameDisplay').innerText = input.files[0].name;
        document.getElementById('fileInfo').classList.remove('d-none');
    }
}

function cancelFile() {
    document.getElementById('fileInput').value = '';
    document.getElementById('fileInfo').classList.add('d-none');
}

function openRoomModal() {
    new bootstrap.Modal(document.getElementById('roomModal')).show();
}

if(currentRoom || currentUid) {
    loadMessages();
    setInterval(loadMessages, 3000);
}
</script>
</body>
</html>