<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    return;
}
$user  = $_SESSION['user'];
$role  = $user['role'] ?? 'user';
$avatar = $user['avatar'] ?? 'default.png';
?>

<div class="header-bar d-flex align-items-center justify-content-between px-4">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none"
                onclick="document.getElementById('sidebar')?.classList.toggle('show')">
            <i class="fas fa-bars"></i>
        </button>
        <h5 class="mb-0 fw-semibold">
            <i class="fas fa-layer-group me-2"></i>
            <?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Quản lý công việc' ?>
        </h5>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary position-relative dropdown-toggle"
                    id="notifBtn" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <span id="notifBadge"
                      class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                      style="display:none;">0</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end p-2"
                style="width:360px;" aria-labelledby="notifBtn">
                <li class="px-2 pb-2 border-bottom">
                    <div class="fw-semibold">Thông báo</div>
                    <div class="small text-muted">Cập nhật tự động</div>
                </li>
                <li id="notifList" class="mt-2"></li>
            </ul>
        </div>
        <div class="d-flex align-items-center gap-2">
            <img src="uploads/avatars/<?= htmlspecialchars($avatar) ?>"
                 class="rounded-circle"
                 style="width:36px;height:36px;object-fit:cover"
                 alt="Avatar">

            <span class="text-muted small">
                <?= htmlspecialchars($user['username']) ?>
                <span class="badge bg-secondary ms-1"><?= strtoupper($role) ?></span>
            </span>
        </div>
        <a href="logout.php" class="btn btn-sm btn-outline-danger">
            <i class="fas fa-sign-out-alt"></i> Đăng xuất
        </a>
    </div>
</div>
