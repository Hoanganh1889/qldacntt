<?php
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION['user'];
$current = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar" id="sidebar">
    <h3><i class="fas fa-rocket me-2"></i> TRANG CHỦ</h3>

    <?php
        if (!isset($current)) $current = basename($_SERVER['PHP_SELF']);
        $isAdmin = ($user['role'] ?? '') === 'admin';
        $active = function(string $file) use ($current) {
            return $current === $file ? 'active' : '';
        };
    ?>

    <nav>
        <!-- He thong -->
        <div class="sidebar-label">Hệ thống</div>

        <?php if ($isAdmin): ?>
            <a class="<?= $active('dashboard.php') ?>" href="dashboard.php">
                <i class="fas fa-chart-line fa-fw me-3"></i> Dashboard
            </a>
            <a class="<?= $active('board.php') ?>" href="board.php">
                <i class="fas fa-columns fa-fw me-3"></i> Board
            </a>
            <a class="<?= $active('admin.php') ?>" href="admin.php">
                <i class="fas fa-users-cog fa-fw me-3"></i> Quản lý User
            </a>
            
            <a class="<?= $active('user_performance.php') ?>" href="user_performance.php">
                <i class="fas fa-check fa-fw me-3"></i> Hiệu suất công việc
            </a>
            <a class="<?= $active('admin_todos.php') ?>" href="admin_todos.php">
                <i class="fas fa-list-check fa-fw me-3"></i> Toàn bộ công việc
            </a>

            <a class="<?= $active('system_logs.php') ?>" href="system_logs.php">
                <i class="fas fa-file-alt fa-fw me-3"></i> Nhật ký hệ thống
            </a>
        <?php else: ?>
            <a class="<?= $active('dashboard_user.php') ?>" href="dashboard_user.php">
                <i class="fas fa-chart-line fa-fw me-3"></i> Dashboard
            </a>
        <?php endif; ?>

        <!-- cong viec  -->
        <div class="sidebar-label mt-3">Quản lý công việc</div>

        <?php if ($isAdmin): ?>
            <a class="<?= $active('todo_admin.php') ?>" href="todo_admin.php">
                <i class="fas fa-clipboard-list fa-fw me-3"></i> Công việc
            </a>

            <a class="<?= $active('projects.php') ?>" href="projects.php">
                <i class="fas fa-folder-open fa-fw me-3"></i> Dự án
            </a>

            <a class="<?= $active('project_performance.php') ?>" href="project_performance.php">
                <i class="fas fa-folder-closed fa-fw me-3"></i> Hiệu suất công việc
            </a>
        <?php else: ?>
            <a class="<?= $active('todo.php') ?>" href="todo.php">
                <i class="fas fa-clipboard-list fa-fw me-3"></i> Công việc
            </a>

            <a class="<?= $active('project_user.php') ?>" href="project_user.php">
                <i class="fas fa-folder-open fa-fw me-3"></i> Dự án
            </a>
        <?php endif; ?>

        <!-- ai -->
        <div class="sidebar-label mt-3">Trí tuệ nhân tạo</div>

        <?php if ($isAdmin): ?>
            <a class="<?= $active('project_ai.php') ?>" href="project_ai.php">
                <i class="fas fa-brain fa-fw me-3"></i> Phân tích dự án (AI)
            </a>

            <a class="<?= $active('ai_insights.php') ?>" href="ai_insights.php">
                <i class="fas fa-lightbulb fa-fw me-3"></i> Phân tích WBS
            </a>
        <?php else: ?>
            <a class="<?= $active('ai_user.php') ?>" href="ai_user.php">
                <i class="fas fa-brain fa-fw me-3"></i> Dự án (AI)
            </a>
        <?php endif; ?>

        <!-- Tai khoan -->
        <div class="sidebar-label mt-3">Tài khoản</div>
        <a class="<?= $active('profile.php') ?>" href="profile.php">
            <i class="fas fa-user-circle fa-fw me-3"></i> Hồ sơ cá nhân
        </a>


        <!-- Lien lac -->
        <div class="sidebar-label mt-3">Liên lạc</div>
        <a class="<?= $active('chat.php') ?>" href="chat.php">
            <i class="fas fa-comments fa-fw me-3"></i> Chat
        </a>
    </nav>
</aside>

