<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)($_SESSION['user']['id'] ?? 0);
$roomId = (int)($_GET['room'] ?? 0);
$chatWith = (int)($_GET['uid'] ?? 0);

// Danh sách phòng của user
$rooms = $conn->query("
    SELECT r.*
    FROM chat_rooms r
    JOIN chat_room_members m ON m.chat_room_id = r.id
    WHERE m.user_id = $uid
    ORDER BY COALESCE(r.room_name, r.name) ASC
");

// Danh sách user
$users = $conn->query("
    SELECT id, username, role, avatar
    FROM users
    WHERE id <> $uid
    ORDER BY username ASC
");
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hệ thống Chat Nội bộ</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --chat-primary: #6366f1;
            --chat-bg: #f3f4f6;
            --chat-border: #e5e7eb;
            --chat-soft: #eef2ff;
            --chat-text: #111827;
            --chat-muted: #6b7280;
            --chat-sidebar-width: 300px;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        .chat-container {
            height: calc(100vh - 140px);
            min-height: 620px;
            display: flex;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 6px 24px rgba(0,0,0,.06);
            overflow: hidden;
            margin-top: 12px;
            border: 1px solid var(--chat-border);
        }

        .chat-sidebar-inner {
            width: var(--chat-sidebar-width);
            background: #fff;
            border-right: 1px solid var(--chat-border);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }

        .sidebar-header-inner {
            padding: 18px 20px;
            border-bottom: 1px solid var(--chat-border);
        }

        .sidebar-content-inner {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        .section-title {
            font-size: .72rem;
            text-transform: uppercase;
            color: #9ca3af;
            font-weight: 700;
            margin: 14px 10px 8px;
            letter-spacing: .5px;
        }

        .chat-item {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            border-radius: 12px;
            text-decoration: none;
            color: #374151;
            transition: .2s;
            margin-bottom: 4px;
            gap: 12px;
        }

        .chat-item:hover {
            background: #f9fafb;
            color: var(--chat-primary);
        }

        .chat-item.active {
            background: var(--chat-soft);
            color: var(--chat-primary);
            font-weight: 600;
        }

        .item-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .chat-item.active .item-icon {
            background: var(--chat-primary);
            color: #fff;
        }

        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            background: #fff;
        }

        .chat-header-top {
            padding: 16px 20px;
            border-bottom: 1px solid var(--chat-border);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-avatar-head {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--chat-soft);
            color: var(--chat-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .chat-box {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #fafafa;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .msg-row {
            display: flex;
            width: 100%;
            animation: fadeIn .18s ease;
        }

        .msg-row.me {
            justify-content: flex-end;
        }

        .msg-row.other {
            justify-content: flex-start;
        }

        .bubble-wrap {
            max-width: 72%;
            display: flex;
            flex-direction: column;
        }

        .msg-row.me .bubble-wrap {
            align-items: flex-end;
        }

        .msg-row.other .bubble-wrap {
            align-items: flex-start;
        }

        .msg-user {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--chat-primary);
            padding: 0 6px;
        }

        .bubble {
            padding: 12px 14px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.45;
            box-shadow: 0 1px 2px rgba(0,0,0,.05);
            word-break: break-word;
            white-space: pre-wrap;
        }

        .bubble.me {
            background: var(--chat-primary);
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .bubble.other {
            background: #fff;
            color: var(--chat-text);
            border: 1px solid var(--chat-border);
            border-bottom-left-radius: 4px;
        }

        .meta {
            font-size: 11px;
            color: var(--chat-muted);
            margin-top: 4px;
            padding: 0 6px;
        }

        .chat-footer {
            padding: 14px 18px;
            border-top: 1px solid var(--chat-border);
            background: #fff;
        }

        .input-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f3f4f6;
            border-radius: 14px;
            padding: 8px 10px;
            border: 1px solid transparent;
            transition: .2s;
        }

        .input-wrapper:focus-within {
            background: #fff;
            border-color: #c7d2fe;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .08);
        }

        .input-wrapper input {
            flex: 1;
            border: none;
            background: transparent;
            outline: none;
            padding: 8px 4px;
            font-size: 14px;
        }

        .btn-send-chat {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: none;
            background: var(--chat-primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: .2s;
            cursor: pointer;
            flex-shrink: 0;
        }

        .btn-send-chat:hover {
            background: #4f46e5;
            transform: scale(1.03);
        }

        .btn-send-chat:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none;
        }

        .chat-empty,
        .chat-welcome-state {
            margin: auto;
            text-align: center;
            color: #9ca3af;
            max-width: 420px;
            padding: 20px;
        }

        .chat-empty i,
        .chat-welcome-state i {
            font-size: 54px;
            opacity: .15;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .sidebar-content-inner::-webkit-scrollbar,
        .chat-box::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-content-inner::-webkit-scrollbar-thumb,
        .chat-box::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 999px;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">

<div class="wrapper">
    <?php include __DIR__ . '/layouts/header.php'; ?>
    <?php include __DIR__ . '/layouts/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid">

                <div class="chat-container">
                    <div class="chat-sidebar-inner">
                        <div class="sidebar-header-inner">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-dark">Hội thoại</h6>
                                <button class="btn btn-sm btn-light text-primary" onclick="openRoomModal()">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <div class="sidebar-content-inner">
                            <div class="section-title">Nhóm thảo luận</div>
                            <?php if ($rooms && $rooms->num_rows > 0): ?>
                                <?php while ($r = $rooms->fetch_assoc()): ?>
                                    <?php $roomName = $r['room_name'] ?? $r['name'] ?? 'Phòng chat'; ?>
                                    <a href="?room=<?= (int)$r['id'] ?>" class="chat-item <?= $roomId == $r['id'] ? 'active' : '' ?>">
                                        <div class="item-icon"><i class="fa-solid fa-hashtag"></i></div>
                                        <span class="text-truncate"><?= htmlspecialchars($roomName) ?></span>
                                    </a>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="px-2 text-muted small">Chưa có phòng nào</div>
                            <?php endif; ?>

                            <div class="section-title">Thành viên</div>
                            <?php if ($users && $users->num_rows > 0): ?>
                                <?php while ($u = $users->fetch_assoc()): ?>
                                    <a href="?uid=<?= (int)$u['id'] ?>" class="chat-item <?= $chatWith == $u['id'] ? 'active' : '' ?>">
                                        <div class="item-icon"><i class="fa-solid fa-circle-user"></i></div>
                                        <div class="flex-grow-1 text-truncate">
                                            <span><?= htmlspecialchars($u['username']) ?></span>
                                        </div>
                                        <?php if (($u['role'] ?? '') === 'admin'): ?>
                                            <i class="fa-solid fa-shield-halved text-danger small" title="Admin"></i>
                                        <?php endif; ?>
                                    </a>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="chat-main">
                        <div class="chat-header-top">
                            <div class="d-flex align-items-center gap-3">
                                <div class="chat-avatar-head">
                                    <i class="fa-solid <?= $roomId ? 'fa-users' : ($chatWith ? 'fa-comment-dots' : 'fa-comments') ?>"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">
                                        <?= $roomId ? 'Kênh thảo luận' : ($chatWith ? 'Chat trực tiếp' : 'Hệ thống Chat') ?>
                                    </div>
                                    <small class="text-success">
                                        <i class="fa-solid fa-circle me-1" style="font-size:7px;"></i>Đang hoạt động
                                    </small>
                                </div>
                            </div>
                        </div>

                        <?php if ($roomId > 0 || $chatWith > 0): ?>
                            <div id="chatBox" class="chat-box">
                                <div class="chat-empty">
                                    <i class="fa-regular fa-comments"></i>
                                    <div class="mt-2 fw-semibold">Chưa có tin nhắn</div>
                                    <div class="small text-muted">Hãy bắt đầu cuộc trò chuyện.</div>
                                </div>
                            </div>

                            <div class="chat-footer">
                                <form id="chatForm" class="w-100">
                                    <div class="input-wrapper">
                                        <input
                                            type="text"
                                            id="msgInput"
                                            name="message"
                                            placeholder="Nhập tin nhắn của bạn..."
                                            autocomplete="off"
                                        >
                                        <button type="submit" id="btnSend" class="btn-send-chat">
                                            <i class="fa-solid fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="chat-welcome-state">
                                <i class="fa-regular fa-comment-dots mb-3"></i>
                                <h6 class="fw-bold text-dark">Hãy chọn một cuộc hội thoại</h6>
                                <p class="small text-muted mb-0">
                                    Chọn một người đồng nghiệp hoặc một nhóm thảo luận ở thanh bên trái để bắt đầu nhắn tin.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
const currentRoom = <?= (int)$roomId ?>;
const currentUid = <?= (int)$chatWith ?>;
const myId = <?= (int)$uid ?>;
const chatBox = document.getElementById('chatBox');
const chatForm = document.getElementById('chatForm');

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function (m) {
        return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[m];
    });
}

async function loadMessages() {
    if (!currentRoom && !currentUid) return;

    const url = currentRoom > 0
        ? `api/chat_fetch.php?room=${currentRoom}`
        : `api/chat_fetch.php?uid=${currentUid}`;

    try {
        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const data = await response.json();

        if (!Array.isArray(data) || data.length === 0) {
            chatBox.innerHTML = `
                <div class="chat-empty">
                    <i class="fa-regular fa-comments"></i>
                    <div class="mt-2 fw-semibold">Chưa có tin nhắn</div>
                    <div class="small text-muted">Hãy bắt đầu cuộc trò chuyện.</div>
                </div>
            `;
            return;
        }

        const nearBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 120;
        let html = '';

        data.forEach(m => {
            const isMe = parseInt(m.user_id) === myId;
            const msg = m.message ? escapeHtml(m.message) : '';
            const username = m.username ? escapeHtml(m.username) : 'User';
            const time = m.created_at
                ? new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                : '';

            html += `
                <div class="msg-row ${isMe ? 'me' : 'other'}">
                    <div class="bubble-wrap">
                        ${(!isMe && currentRoom > 0) ? `<div class="msg-user">${username}</div>` : ''}
                        <div class="bubble ${isMe ? 'me' : 'other'}">${msg}</div>
                        <div class="meta">${time}</div>
                    </div>
                </div>
            `;
        });

        chatBox.innerHTML = html;

        if (nearBottom) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    } catch (e) {
        console.error('Lỗi tải tin nhắn:', e);
        chatBox.innerHTML = `
            <div class="chat-empty">
                <i class="fa-regular fa-face-frown"></i>
                <div class="mt-2 fw-semibold">Không tải được tin nhắn</div>
                <div class="small text-muted">Vui lòng thử lại sau.</div>
            </div>
        `;
    }
}

if (chatForm) {
    chatForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const input = document.getElementById('msgInput');
        const btn = document.getElementById('btnSend');
        const msg = input.value.trim();

        if (!msg) return;

        btn.disabled = true;

        const fd = new FormData();
        fd.append('message', msg);

        if (currentRoom > 0) {
            fd.append('room_id', currentRoom);
        } else if (currentUid > 0) {
            fd.append('uid', currentUid);
        }

        try {
            const response = await fetch('api/chat_send.php', {
                method: 'POST',
                body: fd,
                headers: { 'Accept': 'application/json' }
            });

            const result = await response.json();

            if (result.status === 'success') {
                input.value = '';
                await loadMessages();
                chatBox.scrollTop = chatBox.scrollHeight;
                input.focus();
            } else {
                alert(result.message || 'Không thể gửi tin nhắn');
            }
        } catch (e) {
            console.error('Lỗi gửi tin nhắn:', e);
            alert('Lỗi kết nối hệ thống');
        } finally {
            btn.disabled = false;
        }
    });
}

if (currentRoom > 0 || currentUid > 0) {
    loadMessages();
    setInterval(loadMessages, 2000);
}

async function openRoomModal() {
    const name = prompt('Nhập tên phòng:');
    if (!name) return;

    const fd = new FormData();
    fd.append('name', name.trim());

    try {
        const response = await fetch('api/chat_create_room.php', {
            method: 'POST',
            body: fd,
            headers: { 'Accept': 'application/json' }
        });

        const result = await response.json();

        if (result.status === 'success') {
            alert('Tạo phòng thành công');
            location.reload();
        } else {
            alert(result.message || 'Không thể tạo phòng');
        }
    } catch (e) {
        console.error('Lỗi tạo phòng:', e);
        alert('Lỗi kết nối hệ thống');
    }
}
</script>
</body>
</html>