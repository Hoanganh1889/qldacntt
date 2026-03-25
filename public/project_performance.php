<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') !== 'admin')) {
    header("Location: dashboard.php");
    exit;
}

include __DIR__ . '/layouts/header.php';

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

function hasColumn(mysqli $conn, string $table, string $column): bool {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $r && $r->num_rows > 0;
}

$hasProjectCreatedAt = hasColumn($conn, 'projects', 'created_at');
$hasTodoDueDate      = hasColumn($conn, 'todos', 'due_date');

/* ===== KPI TỔNG ===== */
$totalProjects = 0;
$totalTodos = 0;
$doneTodos = 0;
$inProgressTodos = 0;
$testTodos = 0;
$todoTodos = 0;
$overdueTodos = 0;

$r = $conn->query("SELECT COUNT(*) AS c FROM projects");
if ($r) {
    $totalProjects = (int)($r->fetch_assoc()['c'] ?? 0);
}

$r = $conn->query("
    SELECT
        COUNT(*) AS total_todos,
        SUM(CASE WHEN status='done' THEN 1 ELSE 0 END) AS done_todos,
        SUM(CASE WHEN status='in_progress' THEN 1 ELSE 0 END) AS doing_todos,
        SUM(CASE WHEN status='test' THEN 1 ELSE 0 END) AS test_todos,
        SUM(CASE WHEN status='todo' THEN 1 ELSE 0 END) AS todo_todos
    FROM todos
");
if ($r) {
    $row = $r->fetch_assoc();
    $totalTodos      = (int)($row['total_todos'] ?? 0);
    $doneTodos       = (int)($row['done_todos'] ?? 0);
    $inProgressTodos = (int)($row['doing_todos'] ?? 0);
    $testTodos       = (int)($row['test_todos'] ?? 0);
    $todoTodos       = (int)($row['todo_todos'] ?? 0);
}

if ($hasTodoDueDate) {
    $r = $conn->query("
        SELECT COUNT(*) AS c
        FROM todos
        WHERE due_date IS NOT NULL
          AND due_date < CURDATE()
          AND status <> 'done'
    ");
    if ($r) {
        $overdueTodos = (int)($r->fetch_assoc()['c'] ?? 0);
    }
}

$overallCompletion = $totalTodos > 0 ? round(($doneTodos / $totalTodos) * 100, 1) : 0;

/* ===== DANH SÁCH DỰ ÁN ===== */
$projectSql = "
    SELECT
        p.id,
        p.name,
        p.status,
        " . ($hasProjectCreatedAt ? "p.created_at" : "NULL AS created_at") . ",
        COUNT(t.id) AS total_tasks,
        SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS done_tasks,
        SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) AS doing_tasks,
        SUM(CASE WHEN t.status = 'test' THEN 1 ELSE 0 END) AS test_tasks,
        SUM(CASE WHEN t.status = 'todo' THEN 1 ELSE 0 END) AS todo_tasks" .
        ($hasTodoDueDate ? ",
        SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date < CURDATE() AND t.status <> 'done' THEN 1 ELSE 0 END) AS overdue_tasks
        " : ",
        0 AS overdue_tasks
        ") . "
    FROM projects p
    LEFT JOIN todos t ON t.project_id = p.id
    GROUP BY p.id, p.name, p.status" . ($hasProjectCreatedAt ? ", p.created_at" : "") . "
    ORDER BY " . ($hasProjectCreatedAt ? "p.created_at DESC" : "p.id DESC");

$projects = $conn->query($projectSql);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Hiệu suất dự án</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css">

    <style>
        body {
            background: #f4f6f9;
        }

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

        .project-folder {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .folder-card {
            transition: .25s;
            border-radius: 16px;
        }

        .project-folder:hover .folder-card {
            transform: translateY(-6px);
            box-shadow: 0 18px 42px rgba(0,0,0,.16);
        }

        .folder-icon {
            font-size: 3rem;
            color: #f59e0b;
        }

        .progress {
            height: 10px;
            border-radius: 999px;
        }

        .mini-label {
            font-size: .82rem;
            color: #64748b;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<div class="content-wrapper">

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <h4 class="fw-bold mb-0">
            <i class="fas fa-chart-line text-primary me-2"></i> Hiệu suất dự án
        </h4>
        <div class="small text-muted">
            Click vào từng folder để xem tổng quan chi tiết của dự án
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card p-3 text-center kpi h-100">
                <small>Tổng dự án</small>
                <h2><?= $totalProjects ?></h2>
                <div class="small text-muted mt-1">Số project hiện có</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 text-center kpi h-100">
                <small>Tổng công việc</small>
                <h2><?= $totalTodos ?></h2>
                <div class="small text-muted mt-1">Toàn bộ task hệ thống</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 text-center kpi h-100">
                <small>Tỷ lệ hoàn thành</small>
                <h2 class="text-success"><?= $overallCompletion ?>%</h2>
                <div class="small text-muted mt-1">Done / Tổng task</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 text-center kpi h-100">
                <small>Task trễ hạn</small>
                <h2 class="text-danger"><?= $overdueTodos ?></h2>
                <div class="small text-muted mt-1">Chưa xong quá deadline</div>
            </div>
        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-md-3">
                    <div class="border rounded p-3">
                        <div class="small text-muted">Chưa làm</div>
                        <div class="fw-bold"><?= $todoTodos ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3">
                        <div class="small text-muted">Đang làm</div>
                        <div class="fw-bold text-warning"><?= $inProgressTodos ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3">
                        <div class="small text-muted">Kiểm thử</div>
                        <div class="fw-bold text-info"><?= $testTodos ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3">
                        <div class="small text-muted">Hoàn thành</div>
                        <div class="fw-bold text-success"><?= $doneTodos ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <?php if ($projects && $projects->num_rows > 0): ?>
            <?php while ($p = $projects->fetch_assoc()): ?>
                <?php
                    $totalTasks   = (int)($p['total_tasks'] ?? 0);
                    $doneTasks    = (int)($p['done_tasks'] ?? 0);
                    $doingTasks   = (int)($p['doing_tasks'] ?? 0);
                    $testTasks    = (int)($p['test_tasks'] ?? 0);
                    $todoTasksOne = (int)($p['todo_tasks'] ?? 0);
                    $overdueOne   = (int)($p['overdue_tasks'] ?? 0);
                    $rate         = $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100, 1) : 0;
                ?>
                <div class="col-md-3">
                    <a href="project_overview.php?id=<?= (int)$p['id'] ?>" class="project-folder">
                        <div class="card folder-card text-center p-4 h-100 shadow-sm">
                            <div class="folder-icon mb-2">
                                <i class="fas fa-folder"></i>
                            </div>

                            <h6 class="mt-1 mb-1 fw-bold"><?= h($p['name']) ?></h6>

                            <div class="small text-muted mb-2">
                                <?= h(statusLabel($p['status'] ?? 'todo')) ?>
                            </div>

                            <?php if (!empty($p['created_at'])): ?>
                                <div class="small text-muted mb-3">
                                    <?= h($p['created_at']) ?>
                                </div>
                            <?php else: ?>
                                <div class="small text-muted mb-3"> </div>
                            <?php endif; ?>

                            <div class="mini-label mb-1">Hoàn thành: <b><?= $rate ?>%</b></div>
                            <div class="progress mb-3">
                                <div class="progress-bar bg-success" style="width: <?= $rate ?>%"></div>
                            </div>

                            <div class="row g-2 text-center mb-2">
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <div class="mini-label">Task</div>
                                        <div class="fw-bold"><?= $totalTasks ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <div class="mini-label">Done</div>
                                        <div class="fw-bold text-success"><?= $doneTasks ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 text-center">
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <div class="mini-label">Doing</div>
                                        <div class="fw-bold text-warning"><?= $doingTasks ?></div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <div class="mini-label">Test</div>
                                        <div class="fw-bold text-info"><?= $testTasks ?></div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <div class="mini-label">Todo</div>
                                        <div class="fw-bold"><?= $todoTasksOne ?></div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($overdueOne > 0): ?>
                                <div class="mt-3 small text-danger fw-semibold">
                                    <i class="fas fa-triangle-exclamation me-1"></i>
                                    Trễ hạn: <?= $overdueOne ?>
                                </div>
                            <?php else: ?>
                                <div class="mt-3 small text-muted">
                                    Click để xem tổng quan
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-secondary mb-0">Chưa có dự án.</div>
            </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>