<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* ===== CHẶN SAI ROLE ===== */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'user') {
    header("Location: dashboard.php");
    exit;
}

$user = $_SESSION['user'];
$uid  = (int)$user['id'];
$current = basename($_SERVER['PHP_SELF']);
$pageTitle = 'Dự án của tôi';

/*
 Lấy các dự án mà USER có tham gia
 - 1 dự án = nhiều công việc
 - 1 công việc = nhiều user
 - Tiến độ cá nhân = số báo cáo được duyệt / số công việc được giao
*/
$sql = "
SELECT 
    p.id,
    p.name,
    p.status,
    COUNT(DISTINCT t.id) AS total_tasks,
    COUNT(DISTINCT CASE WHEN ts.approved = 1 THEN ts.id END) AS approved_tasks
FROM projects p
JOIN todos t ON t.project_id = p.id
JOIN todo_assignments ta ON ta.todo_id = t.id
LEFT JOIN todo_submissions ts 
    ON ts.todo_id = t.id AND ts.user_id = ta.user_id
WHERE ta.user_id = $uid
GROUP BY p.id
ORDER BY p.created_at DESC
";

$projects = $conn->query($sql);
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Dự án của tôi</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/sidebar.css">

<style>
.project-card {
    transition: .2s;
    cursor: pointer;
}
.project-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 25px rgba(0,0,0,.12);
}
.progress {
    height: 8px;
}
</style>
</head>
<body>

<?php include __DIR__ . '/layouts/header.php'; ?>
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<div class="content-wrapper">

<h4 class="mb-4">
    <i class="fas fa-folder-open text-warning me-2"></i>
    Dự án tôi tham gia
</h4>

<?php if ($projects->num_rows === 0): ?>
    <div class="alert alert-info">
        Bạn chưa được phân công vào dự án nào.
    </div>
<?php else: ?>

<div class="row g-4">
<?php while ($p = $projects->fetch_assoc()):
    $total = (int)$p['total_tasks'];
    $done  = (int)$p['approved_tasks'];
    $percent = $total > 0 ? round($done / $total * 100) : 0;
?>
    <div class="col-md-3">
        <a href="todo.php?project_id=<?= $p['id'] ?>"
           class="text-decoration-none text-dark">
            <div class="card project-card p-3 h-100">

                <div class="text-center">
                    <i class="fas fa-folder fa-4x text-warning"></i>
                </div>

                <h6 class="mt-3 text-center fw-semibold">
                    <?= htmlspecialchars($p['name']) ?>
                </h6>

                <div class="text-center mb-2">
                    <?php if ($p['status'] === 'Hoàn thành'): ?>
                        <span class="badge bg-success">Hoàn thành</span>
                    <?php elseif ($p['status'] === 'Đang làm'): ?>
                        <span class="badge bg-primary">Đang làm</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Mới</span>
                    <?php endif; ?>
                </div>

                <div class="small text-muted mb-1">
                    Tiến độ cá nhân: <?= $percent ?>%
                </div>

                <div class="progress">
                    <div class="progress-bar bg-success"
                         style="width: <?= $percent ?>%">
                    </div>
                </div>

                <div class="small text-muted mt-2 text-center">
                    <?= $done ?>/<?= $total ?> công việc đã duyệt
                </div>

            </div>
        </a>
    </div>
<?php endwhile; ?>
</div>

<?php endif; ?>

</div>
</body>
</html>
