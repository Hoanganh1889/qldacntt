<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
if (($user['role'] ?? '') !== 'admin') {
    header('Location: dashboard_user.php');
    exit;
}

if (!isset($conn) || !$conn) {
    http_response_code(500);
    exit('Database connection failed.');
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hasColumn(mysqli $conn, string $table, string $column): bool {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

function normalizeStatus(?string $status): string {
    $status = trim((string)$status);
    $map = [
        'todo' => 'todo', 'to do' => 'todo', 'open' => 'todo', 'mới' => 'todo', 'chưa thực hiện' => 'todo',
        'in_progress' => 'in_progress', 'in progress' => 'in_progress', 'doing' => 'in_progress', 'đang thực hiện' => 'in_progress', 'đang làm' => 'in_progress',
        'test' => 'test', 'testing' => 'test', 'in review' => 'test', 'review' => 'test', 'kiểm thử' => 'test', 'chờ duyệt' => 'test',
        'done' => 'done', 'closed' => 'done', 'resolved' => 'done', 'hoàn thành' => 'done', 'completed' => 'done',
    ];
    $key = mb_strtolower($status, 'UTF-8');
    return $map[$key] ?? 'todo';
}

function statusLabel(string $status): string {
    return match ($status) {
        'todo' => 'Todo',
        'in_progress' => 'In Progress',
        'test' => 'Test',
        'done' => 'Done',
        default => 'Todo',
    };
}

function priorityLabel(?string $priority): string {
    $priority = strtolower(trim((string)$priority));
    return match ($priority) {
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
        default => 'N/A',
    };
}

function priorityBadgeClass(?string $priority): string {
    $priority = strtolower(trim((string)$priority));
    return match ($priority) {
        'high' => 'danger',
        'medium' => 'warning',
        'low' => 'secondary',
        default => 'light text-dark border',
    };
}

$hasProjectId = hasColumn($conn, 'todos', 'project_id');
$hasPriority  = hasColumn($conn, 'todos', 'priority');
$hasStatus    = hasColumn($conn, 'todos', 'status');
$hasDueDate   = hasColumn($conn, 'todos', 'due_date');
$hasDesc      = hasColumn($conn, 'todos', 'description');
$hasUpdatedAt = hasColumn($conn, 'todos', 'updated_at');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$flash = null;
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'CSRF token không hợp lệ.'];
        header('Location: board.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        if (!$hasStatus) {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Bảng todos chưa có cột status.'];
            header('Location: board.php');
            exit;
        }

        $todoId = (int)($_POST['todo_id'] ?? 0);
        $status = normalizeStatus($_POST['status'] ?? 'todo');

        $stmt = $conn->prepare("UPDATE todos SET status = ?" . ($hasUpdatedAt ? ", updated_at = NOW()" : "") . " WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $status, $todoId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Đã cập nhật trạng thái công việc.'];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Không thể cập nhật trạng thái.'];
        }

        header('Location: board.php?' . http_build_query($_GET));
        exit;
    }

    if ($action === 'assign_user') {
        $todoId = (int)($_POST['todo_id'] ?? 0);
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);

        // Xóa phân công cũ
        $stmtDel = $conn->prepare("DELETE FROM todo_assignments WHERE todo_id = ?");
        if ($stmtDel) {
            $stmtDel->bind_param('i', $todoId);
            $stmtDel->execute();
            $stmtDel->close();
        }

        // Thêm phân công mới nếu có chọn user
        if ($assignedTo > 0) {
            $stmtIns = $conn->prepare("INSERT INTO todo_assignments(todo_id, user_id) VALUES(?, ?)");
            if ($stmtIns) {
                $stmtIns->bind_param('ii', $todoId, $assignedTo);
                $stmtIns->execute();
                $stmtIns->close();
            }
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Đã cập nhật phân công công việc.'];
        header('Location: board.php?' . http_build_query($_GET));
        exit;
    }
}

$projects = [];
$r = $conn->query("SELECT id, name FROM projects ORDER BY name ASC");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $projects[] = $row;
    }
}

$users = [];
$r = $conn->query("SELECT id, username FROM users WHERE role = 'user' ORDER BY username ASC");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $users[] = $row;
    }
}

$filters = [
    'keyword'     => trim($_GET['keyword'] ?? ''),
    'project_id'  => (int)($_GET['project_id'] ?? 0),
    'assigned_to' => (int)($_GET['assigned_to'] ?? 0),
    'priority'    => trim($_GET['priority'] ?? ''),
    'overdue'     => (int)($_GET['overdue'] ?? 0),
];

$selectFields = ["t.id", "t.title"];
$selectFields[] = $hasDesc ? "t.description" : "'' AS description";
$selectFields[] = $hasStatus ? "t.status" : "'todo' AS status";
$selectFields[] = $hasPriority ? "t.priority" : "'' AS priority";
$selectFields[] = $hasDueDate ? "t.due_date" : "NULL AS due_date";
$selectFields[] = $hasProjectId ? "t.project_id" : "NULL AS project_id";
$selectFields[] = "p.name AS project_name";
$selectFields[] = "GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', ') AS assigned_names";
$selectFields[] = "MIN(ta.user_id) AS first_assigned_user_id";

$sql = "SELECT " . implode(", ", $selectFields) . "
        FROM todos t
        LEFT JOIN projects p ON " . ($hasProjectId ? "p.id = t.project_id" : "1 = 0") . "
        LEFT JOIN todo_assignments ta ON ta.todo_id = t.id
        LEFT JOIN users u ON u.id = ta.user_id";

$where = [];
$types = '';
$params = [];

if ($filters['keyword'] !== '') {
    $where[] = "(t.title LIKE ? " . ($hasDesc ? " OR t.description LIKE ?" : "") . ")";
    $kw = '%' . $filters['keyword'] . '%';
    $types .= 's';
    $params[] = $kw;
    if ($hasDesc) {
        $types .= 's';
        $params[] = $kw;
    }
}

if ($hasProjectId && $filters['project_id'] > 0) {
    $where[] = "t.project_id = ?";
    $types .= 'i';
    $params[] = $filters['project_id'];
}

if ($filters['assigned_to'] > 0) {
    $where[] = "ta.user_id = ?";
    $types .= 'i';
    $params[] = $filters['assigned_to'];
}

if ($hasPriority && in_array($filters['priority'], ['low', 'medium', 'high'], true)) {
    $where[] = "t.priority = ?";
    $types .= 's';
    $params[] = $filters['priority'];
}

if ($hasDueDate && $hasStatus && $filters['overdue'] === 1) {
    $where[] = "t.due_date IS NOT NULL AND t.due_date < CURDATE() AND LOWER(t.status) NOT IN ('done', 'hoàn thành', 'completed')";
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= " GROUP BY t.id";
$sql .= $hasDueDate ? " ORDER BY (t.due_date IS NULL), t.due_date ASC, t.id DESC" : " ORDER BY t.id DESC";

$tasks = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['normalized_status'] = normalizeStatus($row['status'] ?? '');
        $tasks[] = $row;
    }
    $stmt->close();
}

$columns = ['todo' => [], 'in_progress' => [], 'test' => [], 'done' => []];
foreach ($tasks as $task) {
    $status = $task['normalized_status'];
    if (!isset($columns[$status])) {
        $status = 'todo';
    }
    $columns[$status][] = $task;
}

$summary = [];
foreach ($columns as $key => $items) {
    $summary[$key] = count($items);
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Board công việc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
        :root{
            --bg:#f5f7fb;
            --panel:#ffffff;
            --border:#e5e7eb;
            --muted:#6b7280;
            --todo:#64748b;
            --doing:#2563eb;
            --test:#f59e0b;
            --done:#16a34a;
        }
        body{ background:var(--bg); }
        .board-page{ padding:24px; }
        .panel{
            background:var(--panel);
            border:1px solid var(--border);
            border-radius:18px;
            box-shadow:0 10px 24px rgba(15,23,42,.05);
        }
        .chip{
            display:inline-flex; align-items:center; gap:6px;
            padding:8px 12px; border-radius:999px; font-weight:700; font-size:.82rem;
            background:#f8fafc; border:1px solid var(--border);
        }
        .chip.todo{ color:var(--todo); }
        .chip.in_progress{ color:var(--doing); }
        .chip.test{ color:var(--test); }
        .chip.done{ color:var(--done); }
        .filter-grid .form-label{ font-size:.88rem; font-weight:600; margin-bottom:6px; }
        .kanban-row{
            display:grid; grid-template-columns:repeat(4, minmax(280px, 1fr));
            gap:18px; align-items:start; overflow-x:auto; padding-bottom:6px;
        }
        .kanban-col{
            min-width:280px; background:#f8fafc; border:1px solid var(--border);
            border-radius:18px; padding:14px; min-height:540px;
        }
        .kanban-head{ display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
        .kanban-name{ font-weight:800; font-size:1rem; }
        .kanban-count{
            min-width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center;
            border-radius:999px; color:#fff; font-weight:700; font-size:.82rem;
        }
        .count-todo{ background:var(--todo); }
        .count-in_progress{ background:var(--doing); }
        .count-test{ background:var(--test); }
        .count-done{ background:var(--done); }
        .task-card{
            background:#fff; border:1px solid var(--border); border-radius:16px; padding:14px;
            box-shadow:0 4px 14px rgba(15,23,42,.04); margin-bottom:12px;
        }
        .task-title{ font-weight:800; line-height:1.35; margin-bottom:8px; font-size:.98rem; }
        .task-desc{ color:var(--muted); font-size:.86rem; line-height:1.45; margin-bottom:10px; }
        .task-tags{ display:flex; flex-wrap:wrap; gap:8px; margin-bottom:10px; }
        .task-meta{ display:grid; gap:6px; margin-bottom:12px; font-size:.85rem; color:#475569; }
        .task-meta i{ width:16px; color:#64748b; }
        .form-inline{ display:flex; gap:8px; align-items:center; }
        .form-stack{ display:grid; gap:8px; }
        .empty-col{
            border:2px dashed #cbd5e1; border-radius:14px; color:#94a3b8; text-align:center;
            padding:26px 12px; background:#fff;
        }
        .small-note{ color:var(--muted); font-size:.8rem; }
        @media (max-width: 991.98px){
            .board-page{ padding:16px; }
            .kanban-row{ grid-template-columns:repeat(4, 280px); }
        }
    </style>
</head>
<body>
<?php
$headerFile = __DIR__ . '/layouts/header.php';
$sidebarFile = __DIR__ . '/layouts/sidebar.php';
if (file_exists($headerFile)) include $headerFile;
if (file_exists($sidebarFile)) include $sidebarFile;
?>
<div class="content-wrapper">
    <div class="board-page">
        <div class="panel p-3 p-md-4 mb-4">
            <div class="d-flex flex-wrap gap-2">
                <span class="chip todo">Todo: <?= (int)$summary['todo'] ?></span>
                <span class="chip in_progress">In Progress: <?= (int)$summary['in_progress'] ?></span>
                <span class="chip test">Test: <?= (int)$summary['test'] ?></span>
                <span class="chip done">Done: <?= (int)$summary['done'] ?></span>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show">
                <?= h($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$hasStatus): ?>
            <div class="alert alert-warning">Bảng <b>todos</b> chưa có cột <code>status</code>. Hệ thống sẽ tạm đưa mọi task về cột Todo.</div>
        <?php endif; ?>

        <div class="panel p-3 p-md-4 mb-4 filter-grid">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">Từ khóa</label>
                    <input type="text" name="keyword" class="form-control" value="<?= h($filters['keyword']) ?>" placeholder="Tên task hoặc mô tả">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Dự án</label>
                    <select name="project_id" class="form-select">
                        <option value="0">Tất cả</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int)$project['id'] ?>" <?= $filters['project_id'] === (int)$project['id'] ? 'selected' : '' ?>><?= h($project['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Người phụ trách</label>
                    <select name="assigned_to" class="form-select">
                        <option value="0">Tất cả</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= $filters['assigned_to'] === (int)$u['id'] ? 'selected' : '' ?>><?= h($u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Ưu tiên</label>
                    <select name="priority" class="form-select" <?= $hasPriority ? '' : 'disabled' ?>>
                        <option value="">Tất cả</option>
                        <option value="low" <?= $filters['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= $filters['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= $filters['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                    </select>
                    <?php if (!$hasPriority): ?><div class="small-note mt-1">Chưa có cột priority</div><?php endif; ?>
                </div>
                <div class="col-lg-1 col-md-6">
                    <label class="form-label">Trễ hạn</label>
                    <select name="overdue" class="form-select" <?= $hasDueDate ? '' : 'disabled' ?>>
                        <option value="0" <?= $filters['overdue'] === 0 ? 'selected' : '' ?>>Không</option>
                        <option value="1" <?= $filters['overdue'] === 1 ? 'selected' : '' ?>>Có</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <div class="d-grid gap-2 d-md-flex">
                        <button class="btn btn-primary flex-fill"><i class="fas fa-filter me-1"></i>Lọc</button>
                        <a href="board.php" class="btn btn-outline-secondary flex-fill">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="kanban-row">
            <?php foreach ($columns as $statusKey => $items): ?>
                <div class="kanban-col">
                    <div class="kanban-head">
                        <div class="kanban-name"><?= h(statusLabel($statusKey)) ?></div>
                        <div class="kanban-count count-<?= h($statusKey) ?>"><?= count($items) ?></div>
                    </div>
                    <?php if (!$items): ?>
                        <div class="empty-col">Không có công việc</div>
                    <?php else: ?>
                        <?php foreach ($items as $task): ?>
                            <?php
                                $dueDate = $task['due_date'] ?? null;
                                $isOverdue = $dueDate && strtotime($dueDate) < strtotime(date('Y-m-d')) && $task['normalized_status'] !== 'done';
                            ?>
                            <div class="task-card">
                                <div class="task-title"><?= h($task['title']) ?></div>

                                <?php if (!empty($task['description'])): ?>
                                    <div class="task-desc"><?= nl2br(h(mb_strimwidth($task['description'], 0, 120, '...'))) ?></div>
                                <?php endif; ?>

                                <div class="task-tags">
                                    <span class="badge text-bg-<?= h(priorityBadgeClass($task['priority'] ?? '')) ?>">
                                        Priority: <?= h(priorityLabel($task['priority'] ?? null)) ?>
                                    </span>
                                    <?php if ($isOverdue): ?>
                                        <span class="badge text-bg-danger">Overdue</span>
                                    <?php endif; ?>
                                </div>

                                <div class="task-meta">
                                    <div><i class="fas fa-folder"></i> <?= !empty($task['project_name']) ? h($task['project_name']) : 'Chưa gắn dự án' ?></div>
                                    <div><i class="fas fa-user"></i> <?= !empty($task['assigned_names']) ? h($task['assigned_names']) : 'Chưa phân công' ?></div>
                                    <div><i class="fas fa-calendar-day"></i> <?= $dueDate ? h($dueDate) : 'Không có deadline' ?></div>
                                </div>

                                <div class="form-stack">
                                    <form method="post" class="form-inline">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="todo_id" value="<?= (int)$task['id'] ?>">
                                        <select name="status" class="form-select form-select-sm" <?= $hasStatus ? '' : 'disabled' ?>>
                                            <option value="todo" <?= $task['normalized_status'] === 'todo' ? 'selected' : '' ?>>Todo</option>
                                            <option value="in_progress" <?= $task['normalized_status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="test" <?= $task['normalized_status'] === 'test' ? 'selected' : '' ?>>Test</option>
                                            <option value="done" <?= $task['normalized_status'] === 'done' ? 'selected' : '' ?>>Done</option>
                                        </select>
                                        <button class="btn btn-sm btn-primary" <?= $hasStatus ? '' : 'disabled' ?>>Đổi</button>
                                    </form>

                                    <form method="post" class="form-inline">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="action" value="assign_user">
                                        <input type="hidden" name="todo_id" value="<?= (int)$task['id'] ?>">
                                        <select name="assigned_to" class="form-select form-select-sm">
                                            <option value="0">Chưa phân công</option>
                                            <?php foreach ($users as $u): ?>
                                                <option value="<?= (int)$u['id'] ?>" <?= (int)($task['first_assigned_user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                                                    <?= h($u['username']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-outline-secondary">Giao</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>