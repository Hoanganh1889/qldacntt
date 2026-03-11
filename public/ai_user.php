<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'user') {
    header("Location: dashboard.php");
    exit;
}

$user = $_SESSION['user'];
$uid  = (int)$user['id'];

$pageTitle = 'Trí tuệ nhân tạo';
$current   = basename($_SERVER['PHP_SELF']);

/* ================= DỮ LIỆU AI GIẢ LẬP (RULE-BASED) ================= */

// Tổng công việc được giao
$totalTasks = (int)($conn->query("
    SELECT COUNT(*) c FROM todo_assignments WHERE user_id=$uid
")->fetch_assoc()['c'] ?? 0);

// Công việc đã được duyệt
$approvedTasks = (int)($conn->query("
    SELECT COUNT(*) c FROM todo_submissions
    WHERE user_id=$uid AND approved=1
")->fetch_assoc()['c'] ?? 0);

// Công việc chờ duyệt
$pendingTasks = (int)($conn->query("
    SELECT COUNT(*) c FROM todo_submissions
    WHERE user_id=$uid AND approved=0
")->fetch_assoc()['c'] ?? 0);

// Công việc sắp tới hạn (3 ngày)
$deadlineTasks = $conn->query("
    SELECT t.title, t.due_date, p.name AS project_name
    FROM todos t
    JOIN todo_assignments ta ON ta.todo_id=t.id AND ta.user_id=$uid
    JOIN projects p ON p.id=t.project_id
    WHERE t.due_date IS NOT NULL
      AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
      AND t.status != 'Hoàn thành'
    ORDER BY t.due_date ASC
");

// Đánh giá hiệu suất
$efficiency = 0;
if ($totalTasks > 0) {
    $efficiency = round(($approvedTasks / $totalTasks) * 100);
}

if ($efficiency >= 80) {
    $aiComment = "🌟 Hiệu suất rất tốt! Bạn đang làm việc rất hiệu quả.";
} elseif ($efficiency >= 50) {
    $aiComment = "👍 Hiệu suất ổn định. Hãy cố gắng hoàn thành thêm các công việc còn lại.";
} else {
    $aiComment = "⚠️ Hiệu suất thấp. Bạn nên ưu tiên hoàn thành các công việc quan trọng.";
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>AI User</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/sidebar.css">

<style>
.ai-card {
    transition: .2s;
}
.ai-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,.12);
}
</style>
</head>
<body>

<?php include __DIR__ . '/layouts/header.php'; ?>
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<div class="content-wrapper">

<h4 class="mb-4">
    <i class="fas fa-brain text-primary me-2"></i>
    Trợ lý trí tuệ nhân tạo
</h4>

<!-- ĐÁNH GIÁ AI -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card ai-card p-3 text-center">
            <small>Tổng công việc</small>
            <h2><?= $totalTasks ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card ai-card p-3 text-center">
            <small>Đã được duyệt</small>
            <h2><?= $approvedTasks ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card ai-card p-3 text-center">
            <small>Hiệu suất</small>
            <h2><?= $efficiency ?>%</h2>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <strong>🤖 AI nhận xét:</strong> <?= $aiComment ?>
</div>

<!-- CẢNH BÁO -->
<div class="card p-4 mb-4">
    <h6 class="mb-3">
        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
        Công việc sắp đến hạn
    </h6>

    <?php if ($deadlineTasks->num_rows === 0): ?>
        <div class="text-muted">🎉 Không có công việc sắp trễ hạn.</div>
    <?php else: ?>
        <ul class="list-group">
            <?php while ($d = $deadlineTasks->fetch_assoc()): ?>
                <li class="list-group-item">
                    <strong><?= htmlspecialchars($d['title']) ?></strong>
                    <div class="small text-muted">
                        Dự án: <?= htmlspecialchars($d['project_name']) ?> |
                        Hạn: <?= htmlspecialchars($d['due_date']) ?>
                    </div>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php endif; ?>
</div>

<!-- GỢI Ý -->
<div class="card p-4">
    <h6 class="mb-3">
        <i class="fas fa-lightbulb text-success me-2"></i>
        Gợi ý từ AI
    </h6>

    <ul>
        <?php if ($pendingTasks > 0): ?>
            <li>Bạn có <strong><?= $pendingTasks ?></strong> công việc đang chờ duyệt. Hãy theo dõi phản hồi từ Admin.</li>
        <?php endif; ?>

        <?php if ($efficiency < 50): ?>
            <li>Nên ưu tiên hoàn thành các công việc có hạn gần nhất.</li>
        <?php else: ?>
            <li>Tiếp tục duy trì tiến độ làm việc hiện tại.</li>
        <?php endif; ?>

        <li>Sử dụng trang <a href="todo.php">Công việc</a> để theo dõi chi tiết từng nhiệm vụ.</li>
    </ul>
</div>

</div>
</body>
</html>
