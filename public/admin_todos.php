<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* CHỈ ADMIN */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$admin = $_SESSION['user'];

/* ===== ĐỔI TRẠNG THÁI ===== */
if (isset($_GET['status'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $status = $_GET['status'];

    if (in_array($status, ['Chưa làm','Đang làm','Hoàn thành'])) {
        $conn->query("UPDATE todos SET status='$status' WHERE id=$id");

        $conn->query("
            INSERT INTO system_logs(content)
            VALUES ('Admin {$admin['username']} đổi trạng thái todo ID $id thành $status')
        ");
    }
    header("Location: admin_todos.php");
    exit;
}

/* ===== XÓA CÔNG VIỆC ===== */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM todos WHERE id=$id");

    $conn->query("
        INSERT INTO system_logs(content)
        VALUES ('Admin {$admin['username']} xóa todo ID $id')
    ");

    header("Location: admin_todos.php");
    exit;
}

/* ===== LỌC ===== */
$where = "1";
if (!empty($_GET['user_id'])) {
    $uid = (int)$_GET['user_id'];
    $where .= " AND todos.user_id=$uid";
}
if (!empty($_GET['status_filter'])) {
    $st = $_GET['status_filter'];
    $where .= " AND todos.status='$st'";
}

/* ===== LẤY DỮ LIỆU ===== */
$todos = $conn->query("
    SELECT todos.*, users.username, projects.name AS project_name
    FROM todos
    JOIN users ON todos.user_id = users.id
    LEFT JOIN projects ON todos.project_id = projects.id
    WHERE $where
    ORDER BY todos.created_at DESC
");

$users = $conn->query("SELECT id, username FROM users ORDER BY username");
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Toàn bộ công việc</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css">
</head>
<body>

<!-- HEADER -->
<div class="header d-flex align-items-center justify-content-between px-4 shadow-sm"
     style="height:70px;position:fixed;top:0;left:0;width:100%;background:#fff;z-index:1030;">
    <h4 class="mb-0">ADMIN – TOÀN BỘ CÔNG VIỆC</h4>
    <a href="logout.php" class="btn btn-sm btn-outline-danger">Đăng xuất</a>
</div>

<!-- SIDEBAR -->
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<!-- CONTENT -->
<div class="content-wrapper">

    <h3 class="mb-4">📋 DANH SÁCH TOÀN BỘ CÔNG VIỆC</h3>

    <!-- FILTER -->
    <form class="row g-3 mb-4">
        <div class="col-md-4">
            <select name="user_id" class="form-select">
                <option value="">-- Tất cả người dùng --</option>
                <?php while ($u = $users->fetch_assoc()): ?>
                    <option value="<?= $u['id'] ?>"
                        <?= (isset($_GET['user_id']) && $_GET['user_id']==$u['id'])?'selected':'' ?>>
                        <?= htmlspecialchars($u['username']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-4">
            <select name="status_filter" class="form-select">
                <option value="">-- Tất cả trạng thái --</option>
                <?php foreach (['Chưa làm','Đang làm','Hoàn thành'] as $s): ?>
                    <option value="<?= $s ?>"
                        <?= (isset($_GET['status_filter']) && $_GET['status_filter']==$s)?'selected':'' ?>>
                        <?= $s ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <button class="btn btn-primary w-100">
                <i class="fas fa-filter"></i> Lọc
            </button>
        </div>
    </form>

    <!-- TABLE -->
    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Công việc</th>
                        <th>Người tạo</th>
                        <th>Dự án</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th width="220">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($todos->num_rows == 0): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">Không có dữ liệu</td>
                    </tr>
                <?php endif; ?>

                <?php while ($t = $todos->fetch_assoc()): ?>
                    <tr>
                        <td><?= $t['id'] ?></td>
                        <td><?= htmlspecialchars($t['title']) ?></td>
                        <td><?= htmlspecialchars($t['username']) ?></td>
                        <td><?= $t['project_name'] ?? '-' ?></td>
                        <td>
                            <span class="badge bg-<?= 
                                $t['status']=='Hoàn thành'?'success':
                                ($t['status']=='Đang làm'?'warning':'secondary') ?>">
                                <?= $t['status'] ?>
                            </span>
                        </td>
                        <td><?= $t['created_at'] ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?id=<?= $t['id'] ?>&status=Chưa làm" class="btn btn-secondary">Chưa</a>
                                <a href="?id=<?= $t['id'] ?>&status=Đang làm" class="btn btn-warning">Đang</a>
                                <a href="?id=<?= $t['id'] ?>&status=Hoàn thành" class="btn btn-success">Xong</a>
                                <a href="?delete=<?= $t['id'] ?>"
                                   onclick="return confirm('Xóa công việc này?')"
                                   class="btn btn-danger">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>
