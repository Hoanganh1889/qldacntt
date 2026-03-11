<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* ===== CHỈ ADMIN ===== */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$user = $_SESSION['user'];
$pageTitle = 'Nhật ký hệ thống';
$current   = basename($_SERVER['PHP_SELF']);

/*
 system_logs (theo DB của bạn):
 id | user_id | content | action | detail | created_at
*/
$logs = $conn->query("
    SELECT 
        l.id,
        u.username,
        u.role,
        l.action,
        l.content,
        l.detail,
        l.created_at
    FROM system_logs l
    LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.created_at DESC
    LIMIT 200
");
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Nhật ký hệ thống</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/sidebar.css">

<style>
.log-admin { background:#fff3cd; }
.log-user  { background:#e7f1ff; }
.log-sys   { background:#f8f9fa; }
</style>
</head>
<body>

<?php include __DIR__ . '/layouts/header.php'; ?>
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<div class="content-wrapper">

<h4 class="mb-4">
    <i class="fas fa-file-alt me-2"></i> Nhật ký hệ thống
</h4>

<div class="card">
<div class="card-body table-responsive">

<table class="table table-hover align-middle mb-0">
<thead class="table-light">
<tr>
    <th style="width:60px;">#</th>
    <th>Người dùng</th>
    <th style="width:90px;">Role</th>
    <th style="width:150px;">Hành động</th>
    <th>Nội dung</th>
    <th>Chi tiết</th>
    <th style="width:170px;">Thời gian</th>
</tr>
</thead>
<tbody>

<?php if (!$logs || $logs->num_rows === 0): ?>
<tr>
    <td colspan="7" class="text-center text-muted">Chưa có nhật ký</td>
</tr>
<?php else: ?>
<?php while ($l = $logs->fetch_assoc()):
    $rowClass = 'log-sys';
    if ($l['role'] === 'admin') $rowClass = 'log-admin';
    elseif ($l['role'] === 'user') $rowClass = 'log-user';
?>
<tr class="<?= $rowClass ?>">
    <td>#<?= (int)$l['id'] ?></td>
    <td><strong><?= htmlspecialchars($l['username'] ?? 'Hệ thống') ?></strong></td>
    <td>
        <?php if ($l['role'] === 'admin'): ?>
            <span class="badge bg-danger">ADMIN</span>
        <?php elseif ($l['role'] === 'user'): ?>
            <span class="badge bg-primary">USER</span>
        <?php else: ?>
            <span class="badge bg-secondary">SYSTEM</span>
        <?php endif; ?>
    </td>
    
    <td><?= htmlspecialchars($l['action'] ?? '') ?></td>
    <td><?= htmlspecialchars($l['content']) ?></td>
    <td><?= htmlspecialchars($l['detail'] ?? '') ?></td>
    <td><?= htmlspecialchars($l['created_at']) ?></td>
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
