<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* ===== CHỈ ADMIN ===== */
if (
    !isset($_SESSION['user']) ||
    $_SESSION['user']['role'] !== 'admin'
) {
    header("Location: dashboard.php");
    exit;
}

$admin = $_SESSION['user'];

/* ===== LẤY PROJECT ID ===== */
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($project_id <= 0) {
    die("Dự án không hợp lệ");
}

/* ===== DUYỆT / TỪ CHỐI ===== */
if (isset($_GET['approve'])) {
    $todo_id = (int)$_GET['approve'];

    $conn->query("
        UPDATE todos
        SET approved = 1,
            approved_at = NOW()
        WHERE id = $todo_id
          AND project_id = $project_id
    ");

    // log
    $conn->query("
        INSERT INTO system_logs(content)
        VALUES (
            'Admin {$admin['username']} duyệt báo cáo todo ID {$todo_id} (project {$project_id})'
        )
    ");

    header("Location: project_detail.php?id=$project_id");
    exit;
}

if (isset($_GET['reject'])) {
    $todo_id = (int)$_GET['reject'];

    $conn->query("
        UPDATE todos
        SET approved = 0,
            status = 'Đang làm'
        WHERE id = $todo_id
          AND project_id = $project_id
    ");

    // log
    $conn->query("
        INSERT INTO system_logs(content)
        VALUES (
            'Admin {$admin['username']} từ chối báo cáo todo ID {$todo_id} (project {$project_id})'
        )
    ");

    header("Location: project_detail.php?id=$project_id");
    exit;
}

/* ===== THÔNG TIN PROJECT ===== */
$project = $conn->query("
    SELECT *
    FROM projects
    WHERE id = $project_id
")->fetch_assoc();

if (!$project) {
    die("Không tìm thấy dự án");
}

/* ===== DANH SÁCH TODO TRONG PROJECT ===== */
$todos = $conn->query("
    SELECT t.*, u.username AS assigned_name
    FROM todos t
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE t.project_id = $project_id
    ORDER BY t.created_at DESC
");
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Chi tiết dự án</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css">
</head>
<body>

<!-- HEADER -->
<div class="header d-flex align-items-center justify-content-between px-4 shadow-sm"
     style="height:70px;position:fixed;top:0;left:0;width:100%;background:#fff;z-index:1030;">
    <h4 class="mb-0">CHI TIẾT DỰ ÁN</h4>
    <a href="logout.php" class="btn btn-sm btn-outline-danger">Đăng xuất</a>
</div>

<!-- SIDEBAR -->
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<!-- CONTENT -->
<div class="content-wrapper">

    <!-- PROJECT INFO -->
    <div class="card mb-4">
        <div class="card-body">
            <h4 class="mb-2">
                <i class="fas fa-folder-open text-warning"></i>
                <?= htmlspecialchars($project['name']) ?>
            </h4>

            <p class="mb-1"><strong>Mục tiêu:</strong> <?= nl2br(htmlspecialchars($project['goal'])) ?></p>
            <p class="mb-1"><strong>Phạm vi:</strong> <?= nl2br(htmlspecialchars($project['scope'])) ?></p>

            <p class="mb-1">
                <strong>Thời gian:</strong>
                <?= $project['start_date'] ?> → <?= $project['end_date'] ?>
            </p>

            <p class="mb-1"><strong>Ngân sách:</strong> <?= $project['budget'] ?: 'Không có' ?></p>

            <div class="alert alert-info mt-3">
                <strong>🤖 AI Summary:</strong><br>
                <?= nl2br(htmlspecialchars($project['ai_summary'])) ?>
            </div>
        </div>
    </div>

    <!-- TODO LIST -->
    <h5 class="mb-3">📋 Công việc trong dự án</h5>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Công việc</th>
                        <th>Người thực hiện</th>
                        <th>Trạng thái</th>
                        <th>Báo cáo</th>
                        <th>Duyệt</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($todos->num_rows === 0): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            Chưa có công việc
                        </td>
                    </tr>
                <?php endif; ?>

                <?php while ($t = $todos->fetch_assoc()): ?>
                    <tr>
                        <td><?= $t['id'] ?></td>
                       <td><?= htmlspecialchars($t['title']) ?></td>
                        <td><?= htmlspecialchars($t['assigned_name'] ?? '-') ?></td>
                        <td>
                            <span class="badge bg-<?= $t['status']=='Hoàn thành'?'success':($t['status']=='Đang làm'?'warning':'secondary') ?>">
                                <?= $t['status'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if (isset($t['report_file']) && $t['report_file']): ?>
                                <a href="uploads/<?= $t['report_file'] ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-file"></i> Xem file
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Chưa nộp</span>
                            <?php endif; ?>
                        </td>
                       <td>
                            <?php if (isset($t['report_file']) && $t['report_file']): ?>
                                <a href="?id=<?= $project_id ?>&approve=<?= $t['id'] ?>" class="btn btn-sm btn-success"
                                onclick="return confirm('Duyệt báo cáo này?')">
                                    ✔ Duyệt
                                </a>
                                <a href="?id=<?= $project_id ?>&reject=<?= $t['id'] ?>" class="btn btn-sm btn-danger"
                                onclick="return confirm('Từ chối báo cáo này?')">
                                    ✖ Từ chối
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
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
