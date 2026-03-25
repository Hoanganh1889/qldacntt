<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') !== 'admin')) {
    header("Location: dashboard.php");
    exit;
}

include __DIR__ . '/layouts/header.php';

$project_id = (int)($_GET['id'] ?? 0);

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hasColumn(mysqli $conn, string $table, string $column): bool {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $r && $r->num_rows > 0;
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
        'in_progress' => 'primary',
        'test' => 'warning',
        'done' => 'success',
        default => 'secondary',
    };
}

if ($project_id <= 0) {
    die('Thiếu mã dự án.');
}

$hasProjectCreatedAt = hasColumn($conn, 'projects', 'created_at');
$hasProjectStartDate = hasColumn($conn, 'projects', 'start_date');
$hasProjectEndDate   = hasColumn($conn, 'projects', 'end_date');

$hasTodoDueDate    = hasColumn($conn, 'todos', 'due_date');
$hasTodoCreatedAt  = hasColumn($conn, 'todos', 'created_at');
$hasTodoDesc       = hasColumn($conn, 'todos', 'description');
$hasTodoPriority   = hasColumn($conn, 'todos', 'priority');

/* ===== LẤY THÔNG TIN DỰ ÁN ===== */
$projectSql = "
    SELECT 
        p.id,
        p.name,
        p.status,
        " . ($hasProjectCreatedAt ? "p.created_at" : "NULL AS created_at") . ",
        " . ($hasProjectStartDate ? "p.start_date" : "NULL AS start_date") . ",
        " . ($hasProjectEndDate ? "p.end_date" : "NULL AS end_date") . ",
        p.ai_summary
    FROM projects p
    WHERE p.id = ?
";

$stmtProject = $conn->prepare($projectSql);
$project = null;

if ($stmtProject) {
    $stmtProject->bind_param("i", $project_id);
    $stmtProject->execute();
    $project = $stmtProject->get_result()->fetch_assoc();
    $stmtProject->close();
}

if (!$project) {
    die('Không tìm thấy dự án.');
}

/* ===== KPI DỰ ÁN ===== */
$kpiSql = "
    SELECT
        COUNT(*) AS total_tasks,
        SUM(CASE WHEN t.status = 'todo' THEN 1 ELSE 0 END) AS todo_tasks,
        SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) AS doing_tasks,
        SUM(CASE WHEN t.status = 'test' THEN 1 ELSE 0 END) AS test_tasks,
        SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS done_tasks,
        SUM(CASE
            WHEN " . ($hasTodoDueDate ? "t.due_date IS NOT NULL AND t.due_date < CURDATE() AND t.status <> 'done'" : "0") . "
            THEN 1 ELSE 0
        END) AS overdue_tasks,
        SUM(CASE
            WHEN " . ($hasTodoDueDate ? "t.due_date IS NOT NULL AND t.due_date >= CURDATE() AND t.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND t.status <> 'done'" : "0") . "
            THEN 1 ELSE 0
        END) AS due_soon_tasks,
        COUNT(DISTINCT ta.user_id) AS member_count
    FROM todos t
    LEFT JOIN todo_assignments ta ON ta.todo_id = t.id
    WHERE t.project_id = ?
";

$stmtKpi = $conn->prepare($kpiSql);

$kpi = [
    'total_tasks' => 0,
    'todo_tasks' => 0,
    'doing_tasks' => 0,
    'test_tasks' => 0,
    'done_tasks' => 0,
    'overdue_tasks' => 0,
    'due_soon_tasks' => 0,
    'member_count' => 0,
];

if ($stmtKpi) {
    $stmtKpi->bind_param("i", $project_id);
    $stmtKpi->execute();
    $result = $stmtKpi->get_result()->fetch_assoc();
    if ($result) {
        $kpi = $result;
    }
    $stmtKpi->close();
}

$totalTasks = (int)($kpi['total_tasks'] ?? 0);
$doneTasks = (int)($kpi['done_tasks'] ?? 0);
$completionRate = $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100, 1) : 0;

/* ===== TIẾN ĐỘ KỲ VỌNG NẾU CÓ start_date / end_date ===== */
$expectedPercent = null;
if (!empty($project['start_date']) && !empty($project['end_date'])) {
    $today = strtotime(date('Y-m-d'));
    $start = strtotime($project['start_date']);
    $end   = strtotime($project['end_date']);

    if ($end > $start) {
        if ($today <= $start) {
            $expectedPercent = 0;
        } elseif ($today >= $end) {
            $expectedPercent = 100;
        } else {
            $expectedPercent = round((($today - $start) / ($end - $start)) * 100, 1);
        }
    }
}

/* ===== THÀNH VIÊN THAM GIA ===== */
$stmtMembers = $conn->prepare("
    SELECT DISTINCT u.id, u.username
    FROM todo_assignments ta
    JOIN users u ON u.id = ta.user_id
    JOIN todos t ON t.id = ta.todo_id
    WHERE t.project_id = ?
    ORDER BY u.username ASC
");
$members = false;

if ($stmtMembers) {
    $stmtMembers->bind_param("i", $project_id);
    $stmtMembers->execute();
    $members = $stmtMembers->get_result();
}

/* ===== TASK SẮP ĐẾN HẠN ===== */
$dueSoonTasks = false;
if ($hasTodoDueDate) {
    $stmtDueSoon = $conn->prepare("
        SELECT id, title, status, due_date
        FROM todos
        WHERE project_id = ?
          AND due_date IS NOT NULL
          AND due_date >= CURDATE()
          AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
          AND status <> 'done'
        ORDER BY due_date ASC, id DESC
        LIMIT 10
    ");

    if ($stmtDueSoon) {
        $stmtDueSoon->bind_param("i", $project_id);
        $stmtDueSoon->execute();
        $dueSoonTasks = $stmtDueSoon->get_result();
    }
}

/* ===== TOÀN BỘ TASK CỦA DỰ ÁN ===== */
$taskSql = "
    SELECT
        t.id,
        t.title,
        " . ($hasTodoDesc ? "t.description" : "'' AS description") . ",
        t.status,
        " . ($hasTodoDueDate ? "t.due_date" : "NULL AS due_date") . ",
        " . ($hasTodoCreatedAt ? "t.created_at" : "NULL AS created_at") . ",
        " . ($hasTodoPriority ? "t.priority" : "'' AS priority") . ",
        GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', ') AS assigned_users
    FROM todos t
    LEFT JOIN todo_assignments ta ON ta.todo_id = t.id
    LEFT JOIN users u ON u.id = ta.user_id
    WHERE t.project_id = ?
    GROUP BY t.id
    ORDER BY " . ($hasTodoCreatedAt ? "t.created_at DESC" : "t.id DESC");

$stmtTodos = $conn->prepare($taskSql);
$todos = false;

if ($stmtTodos) {
    $stmtTodos->bind_param("i", $project_id);
    $stmtTodos->execute();
    $todos = $stmtTodos->get_result();
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Tổng quan dự án</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css">

    <style>
        body { background:#f4f6f9; }

        .content-wrapper {
            padding: 90px 24px 24px;
        }

        .card {
            border: none;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 26px rgba(0,0,0,.08);
        }

        .kpi {
            transition: .25s;
            text-decoration: none;
            color: inherit;
        }

        .kpi:hover {
            transform: translateY(-6px);
            box-shadow: 0 18px 42px rgba(0,0,0,.16);
        }

        .kpi small {
            color: #64748b;
        }

        .kpi h2 {
            font-weight: 800;
            margin: 0;
        }

        .progress {
            height: 12px;
            border-radius: 999px;
        }

        .member-badge {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            margin: 4px 6px 0 0;
            font-size: .9rem;
            font-weight: 600;
        }

        .mini-label {
            font-size: .84rem;
            color: #64748b;
        }

        .section-title {
            font-weight: 700;
            margin-bottom: 14px;
        }

        .summary-box {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 12px;
            height: 100%;
            background: #fff;
        }

        .task-desc {
            color: #64748b;
            font-size: .9rem;
            margin-top: 4px;
        }

        .scroll-box {
            max-height: 260px;
            overflow-y: auto;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<div class="content-wrapper">

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                <a href="project_performance.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Quay lại hiệu suất dự án
                </a>

                <a href="chat.php?project_id=<?= (int)$project['id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-comments"></i> Chat dự án
                </a>

                <a href="projects.php?id=<?= (int)$project['id'] ?>" class="btn btn-sm btn-outline-dark">
                    <i class="fas fa-user-check"></i> Phân công
                </a>

                <a href="board.php?project_id=<?= (int)$project['id'] ?>" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-table-columns"></i> Kanban dự án
                </a>
            </div>

            <h3 class="mb-1">
                <i class="fas fa-folder-open text-warning me-2"></i>
                <?= h($project['name']) ?>
            </h3>

            <div class="text-muted d-flex flex-wrap gap-3">
                <span>
                    Trạng thái:
                    <span class="badge bg-<?= h(statusBadge($project['status'] ?? 'todo')) ?>">
                        <?= h(statusLabel($project['status'] ?? 'todo')) ?>
                    </span>
                </span>

                <?php if (!empty($project['created_at'])): ?>
                    <span>Ngày tạo: <b><?= h($project['created_at']) ?></b></span>
                <?php endif; ?>

                <?php if (!empty($project['start_date'])): ?>
                    <span>Bắt đầu: <b><?= h($project['start_date']) ?></b></span>
                <?php endif; ?>

                <?php if (!empty($project['end_date'])): ?>
                    <span>Kết thúc: <b><?= h($project['end_date']) ?></b></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card p-3 text-center kpi">
                <small>Tổng task</small>
                <h2><?= (int)$kpi['total_tasks'] ?></h2>
                <div class="small text-muted mt-1">Toàn bộ công việc dự án</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 text-center kpi">
                <small>Hoàn thành</small>
                <h2 class="text-success"><?= (int)$kpi['done_tasks'] ?></h2>
                <div class="small text-muted mt-1"><?= $completionRate ?>% hoàn thiện</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 text-center kpi">
                <small>Trễ hạn</small>
                <h2 class="text-danger"><?= (int)$kpi['overdue_tasks'] ?></h2>
                <div class="small text-muted mt-1">Task chưa xong quá deadline</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 text-center kpi">
                <small>Thành viên</small>
                <h2 class="text-primary"><?= (int)$kpi['member_count'] ?></h2>
                <div class="small text-muted mt-1">User đang tham gia dự án</div>
            </div>
        </div>
    </div>

    <div class="card p-3 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div class="section-title mb-0">Tiến độ dự án</div>
            <div class="fw-bold text-success"><?= $completionRate ?>%</div>
        </div>

        <div class="progress mb-3">
            <div class="progress-bar bg-success" style="width: <?= $completionRate ?>%"></div>
        </div>

        <div class="row g-3 text-center">
            <div class="col-md-3">
                <div class="summary-box">
                    <div class="mini-label">Chưa làm</div>
                    <div class="fw-bold"><?= (int)$kpi['todo_tasks'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-box">
                    <div class="mini-label">Đang làm</div>
                    <div class="fw-bold text-primary"><?= (int)$kpi['doing_tasks'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-box">
                    <div class="mini-label">Kiểm thử</div>
                    <div class="fw-bold text-warning"><?= (int)$kpi['test_tasks'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-box">
                    <div class="mini-label">Hoàn thành</div>
                    <div class="fw-bold text-success"><?= (int)$kpi['done_tasks'] ?></div>
                </div>
            </div>
        </div>

        <?php if ($expectedPercent !== null): ?>
            <?php $delta = round($completionRate - $expectedPercent, 1); ?>
            <div class="mt-3 small text-muted">
                Tiến độ kỳ vọng: <b><?= h($expectedPercent) ?>%</b>
                <?php if ($delta >= 0): ?>
                    <span class="text-success">• Nhanh hơn <?= h($delta) ?>%</span>
                <?php else: ?>
                    <span class="text-danger">• Chậm hơn <?= h(abs($delta)) ?>%</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card p-3 h-100">
                <div class="section-title">AI Summary</div>
                <div class="text-muted scroll-box" style="white-space: pre-line;">
                    <?= h($project['ai_summary'] ?? 'Chưa có tóm tắt AI.') ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card p-3 h-100">
                <div class="section-title">Thành viên tham gia</div>

                <?php if (!$members || $members->num_rows === 0): ?>
                    <div class="text-muted">Chưa có thành viên được phân công.</div>
                <?php else: ?>
                    <?php while ($m = $members->fetch_assoc()): ?>
                        <span class="member-badge"><?= h($m['username']) ?></span>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card p-3 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div class="section-title mb-0">Task sắp đến hạn</div>
            <span class="badge bg-warning text-dark"><?= (int)$kpi['due_soon_tasks'] ?> task</span>
        </div>

        <?php if (!$hasTodoDueDate): ?>
            <div class="text-muted">Bảng todos chưa có cột due_date.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Công việc</th>
                            <th>Trạng thái</th>
                            <th>Deadline</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$dueSoonTasks || $dueSoonTasks->num_rows === 0): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">Không có task sắp đến hạn</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($d = $dueSoonTasks->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= (int)$d['id'] ?></td>
                                <td><?= h($d['title']) ?></td>
                                <td>
                                    <span class="badge bg-<?= h(statusBadge($d['status'])) ?>">
                                        <?= h(statusLabel($d['status'])) ?>
                                    </span>
                                </td>
                                <td><?= h($d['due_date']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card p-3">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div class="section-title mb-0">Toàn bộ task của dự án</div>
            <span class="badge bg-dark"><?= (int)$kpi['total_tasks'] ?> task</span>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Công việc</th>
                        <th style="width:140px;">Trạng thái</th>
                        <?php if ($hasTodoPriority): ?>
                            <th style="width:120px;">Ưu tiên</th>
                        <?php endif; ?>
                        <?php if ($hasTodoDueDate): ?>
                            <th style="width:140px;">Deadline</th>
                        <?php endif; ?>
                        <th>Người phụ trách</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$todos || $todos->num_rows === 0): ?>
                    <tr>
                        <td colspan="<?= $hasTodoPriority && $hasTodoDueDate ? 6 : (($hasTodoPriority || $hasTodoDueDate) ? 5 : 4) ?>" class="text-center text-muted">
                            Chưa có công việc
                        </td>
                    </tr>
                <?php else: ?>
                    <?php while ($t = $todos->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= (int)$t['id'] ?></td>
                            <td>
                                <div class="fw-semibold"><?= h($t['title']) ?></div>
                                <?php if (!empty($t['description'])): ?>
                                    <div class="task-desc"><?= nl2br(h($t['description'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= h(statusBadge($t['status'])) ?>">
                                    <?= h(statusLabel($t['status'])) ?>
                                </span>
                            </td>

                            <?php if ($hasTodoPriority): ?>
                                <td><?= h($t['priority'] ?: 'N/A') ?></td>
                            <?php endif; ?>

                            <?php if ($hasTodoDueDate): ?>
                                <td><?= h($t['due_date'] ?: '') ?></td>
                            <?php endif; ?>

                            <td><?= h($t['assigned_users'] ?: 'Chưa giao') ?></td>
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