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

/*
    Giả định:
    - todo_assignments.user_id là user được giao việc
    - todo_assignments.todo_id liên kết todos.id
    - todos.status có các giá trị: todo, in_progress, test, done
    - todo_submissions.user_id là user nộp báo cáo
*/

$sql = "
    SELECT 
        u.id,
        u.username,
        u.role,
        u.created_at,

        COUNT(DISTINCT ta.todo_id) AS total_assigned,

        COUNT(DISTINCT CASE WHEN t.status = 'done' THEN ta.todo_id END) AS total_done,
        COUNT(DISTINCT CASE WHEN t.status = 'in_progress' THEN ta.todo_id END) AS total_in_progress,
        COUNT(DISTINCT CASE WHEN t.status = 'test' THEN ta.todo_id END) AS total_test,
        COUNT(DISTINCT CASE WHEN t.status = 'todo' THEN ta.todo_id END) AS total_todo,

        COUNT(DISTINCT ts.id) AS total_submissions,

        MAX(ts.created_at) AS last_submission_at

    FROM users u
    LEFT JOIN todo_assignments ta ON ta.user_id = u.id
    LEFT JOIN todos t ON t.id = ta.todo_id
    LEFT JOIN todo_submissions ts ON ts.user_id = u.id

    GROUP BY u.id, u.username, u.role, u.created_at
    ORDER BY total_done DESC, total_assigned DESC, u.created_at DESC
";

$result = $conn->query($sql);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Quản lý hiệu suất User</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css">

    <style>
        .content-wrapper {
            padding: 90px 24px 24px;
        }
        .stat-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,.06);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .progress {
            height: 10px;
            border-radius: 999px;
        }
    </style>
</head>
<body>

<!-- HEADER -->
<div class="header d-flex align-items-center justify-content-between px-4 shadow-sm"
     style="height:70px;position:fixed;top:0;left:0;width:100%;background:#fff;z-index:1030;">
    <h4 class="mb-0">ADMIN PANEL</h4>
    <a href="logout.php" class="btn btn-sm btn-outline-danger">Đăng xuất</a>
</div>

<!-- SIDEBAR -->
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<!-- CONTENT -->
<div class="content-wrapper">

    <h3 class="mb-4">
        <i class="fas fa-chart-line me-2"></i> QUẢN LÝ HIỆU SUẤT USER
    </h3>

    <?php
    $totalUsers = 0;
    $sumAssigned = 0;
    $sumDone = 0;
    $sumSubmissions = 0;

    $rows = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
            $totalUsers++;
            $sumAssigned += (int)$row['total_assigned'];
            $sumDone += (int)$row['total_done'];
            $sumSubmissions += (int)$row['total_submissions'];
        }
    }

    $overallRate = $sumAssigned > 0 ? round(($sumDone / $sumAssigned) * 100, 1) : 0;
    ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Tổng người dùng</div>
                            <div class="fs-4 fw-bold"><?= (int)$totalUsers ?></div>
                        </div>
                        <div class="stat-icon bg-primary-subtle text-primary">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Tổng việc được giao</div>
                            <div class="fs-4 fw-bold"><?= (int)$sumAssigned ?></div>
                        </div>
                        <div class="stat-icon bg-warning-subtle text-warning">
                            <i class="fas fa-list-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Tổng việc hoàn thành</div>
                            <div class="fs-4 fw-bold"><?= (int)$sumDone ?></div>
                        </div>
                        <div class="stat-icon bg-success-subtle text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Tỷ lệ hoàn thành chung</div>
                            <div class="fs-4 fw-bold"><?= $overallRate ?>%</div>
                        </div>
                        <div class="stat-icon bg-info-subtle text-info">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- BẢNG CHI TIẾT -->
    <div class="card">
        <div class="card-header fw-bold">
            Bảng hiệu suất người dùng
        </div>
        <div class="card-body table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Việc được giao</th>
                        <th>Hoàn thành</th>
                        <th>Đang làm</th>
                        <th>Chờ kiểm tra</th>
                        <th>Chưa làm</th>
                        <th>Báo cáo đã nộp</th>
                        <th>Tỷ lệ</th>
                        <th>Lần nộp gần nhất</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $u): ?>
                        <?php
                            $assigned = (int)$u['total_assigned'];
                            $done = (int)$u['total_done'];
                            $inProgress = (int)$u['total_in_progress'];
                            $test = (int)$u['total_test'];
                            $todo = (int)$u['total_todo'];
                            $submissions = (int)$u['total_submissions'];
                            $rate = $assigned > 0 ? round(($done / $assigned) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?= (int)$u['id'] ?></td>
                            <td><?= h($u['username']) ?></td>
                            <td>
                                <span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'secondary' ?>">
                                    <?= h($u['role']) ?>
                                </span>
                            </td>
                            <td><?= $assigned ?></td>
                            <td class="text-success fw-bold"><?= $done ?></td>
                            <td class="text-primary"><?= $inProgress ?></td>
                            <td class="text-warning"><?= $test ?></td>
                            <td class="text-muted"><?= $todo ?></td>
                            <td><?= $submissions ?></td>
                            <td style="min-width:160px;">
                                <div class="fw-semibold mb-1"><?= $rate ?>%</div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" role="progressbar"
                                         style="width: <?= $rate ?>%;"
                                         aria-valuenow="<?= $rate ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </td>
                            <td><?= h($u['last_submission_at'] ?? 'Chưa có') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted">Không có dữ liệu</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>