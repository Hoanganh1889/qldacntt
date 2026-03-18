<?php
session_start();
require_once __DIR__ . '/../config/db.php';
include __DIR__ . '/layouts/header.php';

if (!isset($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') !== 'admin')) {
    header("Location: dashboard.php");
    exit;
}

$admin = $_SESSION['user'];
$project_id = (int)($_GET['id'] ?? 0);

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function statusLabel(string $status): string {
    return match ($status) {
        'todo' => 'Chưa làm',
        'in_progress' => 'Đang làm',
        'test' => 'Chờ kiểm tra',
        'done' => 'Hoàn thành',
        default => $status,
    };
}

function refresh_todo_status(mysqli $conn, int $todo_id): void {
    $stmt = $conn->prepare("
        SELECT
            (SELECT COUNT(*) FROM todo_assignments WHERE todo_id = ?) AS total_assigned,
            (SELECT COUNT(*) 
             FROM todo_submissions s
             JOIN todo_assignments ta ON ta.todo_id = s.todo_id AND ta.user_id = s.user_id
             WHERE s.todo_id = ? AND s.approved = 1) AS total_approved
    ");
    if (!$stmt) return;

    $stmt->bind_param("ii", $todo_id, $todo_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $totalAssigned = (int)($row['total_assigned'] ?? 0);
    $totalApproved = (int)($row['total_approved'] ?? 0);

    if ($totalAssigned === 0) {
        $newStatus = 'todo';
    } elseif ($totalApproved === $totalAssigned) {
        $newStatus = 'done';
    } elseif ($totalApproved > 0) {
        $newStatus = 'test';
    } else {
        // có phân công nhưng chưa ai được duyệt
        $newStatus = 'in_progress';
    }

    $stmtUpdate = $conn->prepare("UPDATE todos SET status = ? WHERE id = ?");
    if ($stmtUpdate) {
        $stmtUpdate->bind_param("si", $newStatus, $todo_id);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }
}

/* ===== Hàm cập nhật trạng thái dự án ===== */
function refresh_project_status(mysqli $conn, int $project_id): void {
    $stmt1 = $conn->prepare("SELECT COUNT(*) c FROM todos WHERE project_id = ?");
    if (!$stmt1) return;

    $stmt1->bind_param("i", $project_id);
    $stmt1->execute();
    $r1 = $stmt1->get_result()->fetch_assoc();
    $stmt1->close();

    $total_todos = (int)($r1['c'] ?? 0);

    if ($total_todos === 0) {
        $stmt = $conn->prepare("UPDATE projects SET status = 'todo' WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }

    $stmt2 = $conn->prepare("
        SELECT
            SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS done_count,
            SUM(CASE WHEN status = 'test' THEN 1 ELSE 0 END) AS test_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS progress_count
        FROM todos
        WHERE project_id = ?
    ");
    if (!$stmt2) return;

    $stmt2->bind_param("i", $project_id);
    $stmt2->execute();
    $r2 = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    $doneCount = (int)($r2['done_count'] ?? 0);
    $testCount = (int)($r2['test_count'] ?? 0);
    $progressCount = (int)($r2['progress_count'] ?? 0);

    if ($doneCount === $total_todos) {
        $projectStatus = 'done';
    } elseif ($testCount > 0) {
        $projectStatus = 'test';
    } elseif ($progressCount > 0) {
        $projectStatus = 'in_progress';
    } else {
        $projectStatus = 'todo';
    }

    $stmtUpdate = $conn->prepare("UPDATE projects SET status = ? WHERE id = ?");
    if ($stmtUpdate) {
        $stmtUpdate->bind_param("si", $projectStatus, $project_id);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }
}

/* ===== Duyệt / Từ chối 1 submission (todo_id + user_id) ===== */
if (isset($_GET['approve'], $_GET['todo'], $_GET['user'])) {
    $todo_id = (int)$_GET['todo'];
    $user_id = (int)$_GET['user'];

    $stmt = $conn->prepare("
        UPDATE todo_submissions
        SET approved = 1, approved_at = NOW()
        WHERE todo_id = ? AND user_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("ii", $todo_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    $logContent = "Admin {$admin['username']} duyệt submission todo={$todo_id} user={$user_id}";
    $stmtLog = $conn->prepare("INSERT INTO system_logs(content) VALUES (?)");
    if ($stmtLog) {
        $stmtLog->bind_param("s", $logContent);
        $stmtLog->execute();
        $stmtLog->close();
    }

    refresh_todo_status($conn, $todo_id);
    refresh_project_status($conn, $project_id);

    header("Location: todo_admin.php?id=$project_id");
    exit;
}

if (isset($_GET['reject'], $_GET['todo'], $_GET['user'])) {
    $todo_id = (int)$_GET['todo'];
    $user_id = (int)$_GET['user'];

    $stmt = $conn->prepare("
        UPDATE todo_submissions
        SET approved = 0, approved_at = NULL
        WHERE todo_id = ? AND user_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("ii", $todo_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    $logContent = "Admin {$admin['username']} từ chối submission todo={$todo_id} user={$user_id}";
    $stmtLog = $conn->prepare("INSERT INTO system_logs(content) VALUES (?)");
    if ($stmtLog) {
        $stmtLog->bind_param("s", $logContent);
        $stmtLog->execute();
        $stmtLog->close();
    }

    refresh_todo_status($conn, $todo_id);
    refresh_project_status($conn, $project_id);

    header("Location: todo_admin.php?id=$project_id");
    exit;
}

/* ===== Nếu chưa chọn dự án: hiển thị thư mục ===== */
if ($project_id === 0) {
    $projects = $conn->query("
        SELECT p.id, p.name, p.status
        FROM projects p
        ORDER BY p.created_at DESC
    ");
} else {
    $stmtProject = $conn->prepare("SELECT * FROM projects WHERE id = ?");
    $project = null;

    if ($stmtProject) {
        $stmtProject->bind_param("i", $project_id);
        $stmtProject->execute();
        $project = $stmtProject->get_result()->fetch_assoc();
        $stmtProject->close();
    }

    if (!$project) {
        die("Không tìm thấy dự án");
    }

    $stmtTodos = $conn->prepare("
        SELECT t.*
        FROM todos t
        WHERE t.project_id = ?
        ORDER BY t.created_at DESC
    ");
    $todos = false;

    if ($stmtTodos) {
        $stmtTodos->bind_param("i", $project_id);
        $stmtTodos->execute();
        $todos = $stmtTodos->get_result();
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Admin - Duyệt công việc</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css">
</head>
<body>
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<div class="content-wrapper">

<?php if ($project_id === 0): ?>

    <h3 class="mb-3">📁 DUYỆT BÁO CÁO THEO DỰ ÁN</h3>
    <div class="row g-3">
        <?php if ($projects && $projects->num_rows > 0): ?>
            <?php while ($p = $projects->fetch_assoc()): ?>
                <div class="col-md-3">
                    <a href="todo_admin.php?id=<?= (int)$p['id'] ?>" class="text-decoration-none">
                        <div class="card text-center p-4 shadow-sm h-100">
                            <i class="fas fa-folder fa-3x text-warning"></i>
                            <h6 class="mt-2"><?= h($p['name']) ?></h6>
                            <small class="text-muted"><?= h(statusLabel($p['status'])) ?></small>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-secondary">Chưa có dự án</div>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>

    <div class="d-flex align-items-center mb-3">
        <a href="todo_admin.php" class="btn btn-sm btn-outline-secondary me-3">
            <i class="fas fa-arrow-left"></i> Quay lại
        </a>
        <h4 class="mb-0">
            <i class="fas fa-folder-open text-warning me-2"></i>
            <?= h($project['name']) ?>
            <small class="text-muted ms-2">(<?= h(statusLabel($project['status'])) ?>)</small>
        </h4>
    </div>

    <div class="card mb-3">
        <div class="card-body" style="max-height: 220px; overflow-y: auto;">
            <b>🤖 AI Summary:</b><br>
            <?= nl2br(h($project['ai_summary'] ?? '')) ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:70px;">Todo</th>
                        <th>Công việc</th>
                        <th style="width:220px;">User được giao</th>
                        <th style="width:320px;">Báo cáo - File</th>
                        <th style="width:160px;">Duyệt</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$todos || $todos->num_rows === 0): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">Không có công việc</td>
                    </tr>
                <?php else: ?>
                    <?php while ($t = $todos->fetch_assoc()): ?>
                        <?php
                            $todo_id = (int)$t['id'];

                            // Chỉ lấy assignee hiện tại
                            $stmtAssignees = $conn->prepare("
                                SELECT u.id, u.username
                                FROM todo_assignments ta
                                JOIN users u ON u.id = ta.user_id
                                WHERE ta.todo_id = ?
                                ORDER BY u.username
                            ");
                            $assigneeRows = [];
                            if ($stmtAssignees) {
                                $stmtAssignees->bind_param("i", $todo_id);
                                $stmtAssignees->execute();
                                $assigneeResult = $stmtAssignees->get_result();
                                while ($a = $assigneeResult->fetch_assoc()) {
                                    $assigneeRows[] = $a;
                                }
                                $stmtAssignees->close();
                            }

                            // Chỉ lấy submission của user còn đang được giao
                            $stmtSubs = $conn->prepare("
                                SELECT s.*, u.username
                                FROM todo_submissions s
                                JOIN users u ON u.id = s.user_id
                                JOIN todo_assignments ta ON ta.todo_id = s.todo_id AND ta.user_id = s.user_id
                                WHERE s.todo_id = ?
                                ORDER BY s.created_at DESC
                            ");
                            $subsRows = [];
                            if ($stmtSubs) {
                                $stmtSubs->bind_param("i", $todo_id);
                                $stmtSubs->execute();
                                $subsResult = $stmtSubs->get_result();
                                while ($s = $subsResult->fetch_assoc()) {
                                    $subsRows[] = $s;
                                }
                                $stmtSubs->close();
                            }
                        ?>
                        <tr>
                            <td>#<?= $todo_id ?></td>
                            <td>
                                <div class="fw-semibold"><?= h($t['title']) ?></div>
                                <small class="text-muted">Status: <?= h(statusLabel($t['status'])) ?></small>
                            </td>
                            <td>
                                <?php if (count($assigneeRows) === 0): ?>
                                    <span class="text-muted">Chưa giao</span>
                                <?php else: ?>
                                    <?php foreach ($assigneeRows as $a): ?>
                                        <div>• <?= h($a['username']) ?></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (count($subsRows) === 0): ?>
                                    <span class="text-muted">Chưa có báo cáo</span>
                                <?php else: ?>
                                    <?php foreach ($subsRows as $s): ?>
                                        <div class="border rounded p-2 mb-2">
                                            <div class="d-flex justify-content-between">
                                                <div class="fw-semibold"><?= h($s['username']) ?></div>
                                                <div>
                                                    <?php if ((int)$s['approved'] === 1): ?>
                                                        <span class="badge bg-success">Approved</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Pending</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mt-1">
                                                <a target="_blank" href="uploads/<?= h($s['report_file']) ?>">
                                                    📄 <?= h($s['report_file']) ?>
                                                </a>
                                            </div>
                                            <?php if (!empty($s['report_note'])): ?>
                                                <div class="small mt-1"><b>Note:</b> <?= nl2br(h($s['report_note'])) ?></div>
                                            <?php endif; ?>
                                            <div class="small text-muted mt-1">Nộp: <?= h($s['created_at']) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (count($subsRows) === 0): ?>
                                    —
                                <?php else: ?>
                                    <?php foreach ($subsRows as $x): ?>
                                        <div class="d-flex gap-1 mb-1">
                                            <span class="small" style="min-width:90px;"><?= h($x['username']) ?></span>
                                            <?php if ((int)$x['approved'] === 0): ?>
                                                <a class="btn btn-sm btn-success"
                                                   href="?id=<?= (int)$project_id ?>&approve=1&todo=<?= (int)$x['todo_id'] ?>&user=<?= (int)$x['user_id'] ?>">
                                                    Duyệt
                                                </a>
                                                <a class="btn btn-sm btn-danger"
                                                   href="?id=<?= (int)$project_id ?>&reject=1&todo=<?= (int)$x['todo_id'] ?>&user=<?= (int)$x['user_id'] ?>">
                                                    Từ chối
                                                </a>
                                            <?php else: ?>
                                                <span class="small text-success fw-semibold">Đã duyệt</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

</div>
</body>
</html>