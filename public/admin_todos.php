<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* CHỈ ADMIN */
if (!isset($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') !== 'admin')) {
    header("Location: dashboard.php");
    exit;
}

$admin = $_SESSION['user'];

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function statusLabel(string $status): string {
    return match ($status) {
        'todo' => 'Chưa làm',
        'in_progress' => 'Đang làm',
        'test' => 'Kiểm thử',
        'done' => 'Hoàn thành',
        default => $status,
    };
}

function statusBadge(string $status): string {
    return match ($status) {
        'todo' => 'secondary',
        'in_progress' => 'warning',
        'test' => 'info',
        'done' => 'success',
        default => 'secondary',
    };
}

/* ===== ĐỔI TRẠNG THÁI ===== */
if (isset($_GET['status'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $status = trim($_GET['status']);

    $allowedStatuses = ['todo', 'in_progress', 'test', 'done'];

    if ($id > 0 && in_array($status, $allowedStatuses, true)) {
        $stmt = $conn->prepare("UPDATE todos SET status = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $status, $id);
            $stmt->execute();
            $stmt->close();
        }

        $statusText = statusLabel($status);
        $logContent = "Admin {$admin['username']} đổi trạng thái todo ID $id thành $statusText";
        $stmtLog = $conn->prepare("INSERT INTO system_logs(content) VALUES (?)");
        if ($stmtLog) {
            $stmtLog->bind_param("s", $logContent);
            $stmtLog->execute();
            $stmtLog->close();
        }
    }

    header("Location: admin_todos.php");
    exit;
}

/* ===== XÓA CÔNG VIỆC ===== */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM todos WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }

        $logContent = "Admin {$admin['username']} xóa todo ID $id";
        $stmtLog = $conn->prepare("INSERT INTO system_logs(content) VALUES (?)");
        if ($stmtLog) {
            $stmtLog->bind_param("s", $logContent);
            $stmtLog->execute();
            $stmtLog->close();
        }
    }

    header("Location: admin_todos.php");
    exit;
}

/* ===== LỌC ===== */
$where = [];
$params = [];
$types = '';

if (!empty($_GET['user_id'])) {
    $uid = (int)$_GET['user_id'];
    if ($uid > 0) {
        $where[] = "todos.user_id = ?";
        $params[] = $uid;
        $types .= 'i';
    }
}

if (!empty($_GET['status_filter'])) {
    $st = trim($_GET['status_filter']);
    $allowedStatuses = ['todo', 'in_progress', 'test', 'done'];

    if (in_array($st, $allowedStatuses, true)) {
        $where[] = "todos.status = ?";
        $params[] = $st;
        $types .= 's';
    }
}

$whereSql = $where ? implode(' AND ', $where) : '1';

/* ===== LẤY DỮ LIỆU ===== */
$sql = "
    SELECT todos.*, users.username, projects.name AS project_name
    FROM todos
    JOIN users ON todos.user_id = users.id
    LEFT JOIN projects ON todos.project_id = projects.id
    WHERE $whereSql
    ORDER BY todos.created_at DESC
";

$stmt = $conn->prepare($sql);
$todos = false;

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $todos = $stmt->get_result();
}

$users = $conn->query("SELECT id, username FROM users ORDER BY username");
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Toàn bộ công việc</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css">

    <style>
        .content-wrapper {
            padding: 90px 24px 24px;
        }
    </style>
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
    <form class="row g-3 mb-4" method="get">
        <div class="col-md-4">
            <select name="user_id" class="form-select">
                <option value="">-- Tất cả người dùng --</option>
                <?php if ($users): ?>
                    <?php while ($u = $users->fetch_assoc()): ?>
                        <option value="<?= (int)$u['id'] ?>"
                            <?= (isset($_GET['user_id']) && (int)$_GET['user_id'] === (int)$u['id']) ? 'selected' : '' ?>>
                            <?= h($u['username']) ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="col-md-4">
            <select name="status_filter" class="form-select">
                <option value="">-- Tất cả trạng thái --</option>
                <option value="todo" <?= (($_GET['status_filter'] ?? '') === 'todo') ? 'selected' : '' ?>>Chưa làm</option>
                <option value="in_progress" <?= (($_GET['status_filter'] ?? '') === 'in_progress') ? 'selected' : '' ?>>Đang làm</option>
                <option value="test" <?= (($_GET['status_filter'] ?? '') === 'test') ? 'selected' : '' ?>>Kiểm thử</option>
                <option value="done" <?= (($_GET['status_filter'] ?? '') === 'done') ? 'selected' : '' ?>>Hoàn thành</option>
            </select>
        </div>

        <div class="col-md-2">
            <button class="btn btn-primary w-100">
                <i class="fas fa-filter"></i> Lọc
            </button>
        </div>

        <div class="col-md-2">
            <a href="admin_todos.php" class="btn btn-outline-secondary w-100">
                Reset
            </a>
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
                        <th width="260">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$todos || $todos->num_rows == 0): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">Không có dữ liệu</td>
                    </tr>
                <?php else: ?>
                    <?php while ($t = $todos->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$t['id'] ?></td>
                            <td><?= h($t['title']) ?></td>
                            <td><?= h($t['username']) ?></td>
                            <td><?= h($t['project_name'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-<?= statusBadge($t['status']) ?>">
                                    <?= h(statusLabel($t['status'])) ?>
                                </span>
                            </td>
                            <td><?= h($t['created_at']) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm flex-wrap" role="group">
                                    <a href="?id=<?= (int)$t['id'] ?>&status=todo" class="btn btn-secondary">Todo</a>
                                    <a href="?id=<?= (int)$t['id'] ?>&status=in_progress" class="btn btn-warning">Doing</a>
                                    <a href="?id=<?= (int)$t['id'] ?>&status=test" class="btn btn-info text-white">Test</a>
                                    <a href="?id=<?= (int)$t['id'] ?>&status=done" class="btn btn-success">Done</a>
                                    <a href="?delete=<?= (int)$t['id'] ?>"
                                       onclick="return confirm('Xóa công việc này?')"
                                       class="btn btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>