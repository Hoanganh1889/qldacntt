<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') !== 'admin')) {
    header("Location: dashboard.php");
    exit;
}

include __DIR__ . '/layouts/header.php';

$project_id = (int)($_GET['id'] ?? 0);
$msg = $err = "";

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

/* ===== XÓA DỰ ÁN ===== */
if (isset($_GET['delete_project'])) {
    $deleteProjectId = (int)($_GET['delete_project'] ?? 0);

    if ($deleteProjectId > 0) {
        $conn->begin_transaction();

        try {
            /* Lấy tất cả todo thuộc dự án */
            $todoIds = [];
            $stmtTodoIds = $conn->prepare("SELECT id FROM todos WHERE project_id = ?");
            if ($stmtTodoIds) {
                $stmtTodoIds->bind_param("i", $deleteProjectId);
                $stmtTodoIds->execute();
                $resultTodoIds = $stmtTodoIds->get_result();

                while ($row = $resultTodoIds->fetch_assoc()) {
                    $todoIds[] = (int)$row['id'];
                }

                $stmtTodoIds->close();
            }

            if (!empty($todoIds)) {
                $idList = implode(',', $todoIds);

                /* Xóa phân công */
                $conn->query("DELETE FROM todo_assignments WHERE todo_id IN ($idList)");

                /* Xóa báo cáo */
                $conn->query("DELETE FROM todo_submissions WHERE todo_id IN ($idList)");

                /* Xóa todos */
                $conn->query("DELETE FROM todos WHERE id IN ($idList)");
            }

            /* Xóa project */
            $stmtDeleteProject = $conn->prepare("DELETE FROM projects WHERE id = ?");
            if ($stmtDeleteProject) {
                $stmtDeleteProject->bind_param("i", $deleteProjectId);
                $stmtDeleteProject->execute();
                $stmtDeleteProject->close();
            }

            $conn->commit();
            header("Location: projects.php?deleted=1");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $err = "❌ Không thể xóa dự án vì còn dữ liệu liên quan.";
        }
    }
}

/* ===== Danh sách user role=user ===== */
$users = $conn->query("SELECT id, username FROM users WHERE role='user' ORDER BY username");

/* ===== Giao việc (multi-user) ===== */
if (isset($_POST['assign_multi'])) {
    $todo_id = (int)($_POST['todo_id'] ?? 0);
    $project_id = (int)($_POST['project_id'] ?? 0);
    $assignees = $_POST['assignees'] ?? [];

    if ($todo_id > 0 && $project_id > 0) {
        $stmtDelete = $conn->prepare("DELETE FROM todo_assignments WHERE todo_id = ?");
        if ($stmtDelete) {
            $stmtDelete->bind_param("i", $todo_id);
            $stmtDelete->execute();
            $stmtDelete->close();
        }

        if (is_array($assignees) && count($assignees) > 0) {
            $stmtInsert = $conn->prepare("INSERT INTO todo_assignments(todo_id, user_id) VALUES(?, ?)");
            if ($stmtInsert) {
                foreach ($assignees as $u) {
                    $uid = (int)$u;
                    if ($uid > 0) {
                        $stmtInsert->bind_param("ii", $todo_id, $uid);
                        $stmtInsert->execute();
                    }
                }
                $stmtInsert->close();
            }

            $stmtTodo = $conn->prepare("UPDATE todos SET status = 'todo' WHERE id = ?");
            if ($stmtTodo) {
                $stmtTodo->bind_param("i", $todo_id);
                $stmtTodo->execute();
                $stmtTodo->close();
            }

            $stmtProject = $conn->prepare("UPDATE projects SET status = 'in_progress' WHERE id = ?");
            if ($stmtProject) {
                $stmtProject->bind_param("i", $project_id);
                $stmtProject->execute();
                $stmtProject->close();
            }

            $msg = "✅ Đã giao việc.";
        } else {
            $msg = "✅ Đã bỏ giao (không có user).";
        }
    }

    header("Location: projects.php?id=$project_id&msg=1");
    exit;
}

/* ===== Danh sách dự án dạng thư mục ===== */
if ($project_id === 0) {
    $projects = $conn->query("SELECT id, name, status FROM projects ORDER BY created_at DESC");
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
    <title>Admin - Giao việc</title>
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

<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<div class="content-wrapper">

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">✅ Đã xóa dự án thành công.</div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="alert alert-danger"><?= h($err) ?></div>
<?php endif; ?>

<?php if ($project_id === 0): ?>

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0">📁 DỰ ÁN ĐÃ PHÂN TÍCH</h3>
    </div>

    <div class="row g-3">
        <?php if ($projects && $projects->num_rows > 0): ?>
            <?php while ($p = $projects->fetch_assoc()): ?>
                <div class="col-md-3">
                    <div class="card text-center p-4 shadow-sm h-100">
                        <a href="projects.php?id=<?= (int)$p['id'] ?>" class="text-decoration-none text-dark">
                            <i class="fas fa-folder fa-3x text-warning"></i>
                            <h6 class="mt-2"><?= h($p['name']) ?></h6>
                            <small class="text-muted"><?= h(statusLabel($p['status'])) ?></small>
                        </a>

                        <div class="mt-3">
                            <a href="projects.php?delete_project=<?= (int)$p['id'] ?>"
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Bạn có chắc muốn xóa dự án này? Tất cả công việc và phân công liên quan sẽ bị xóa.')">
                                <i class="fas fa-trash"></i> Xóa dự án
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-secondary mb-0">Chưa có dự án.</div>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center">
            <a href="projects.php" class="btn btn-sm btn-outline-secondary me-3">
                <i class="fas fa-arrow-left"></i> Quay lại
            </a>
            <h4 class="mb-0">
                <i class="fas fa-folder-open text-warning me-2"></i>
                <?= h($project['name']) ?>
                <small class="text-muted ms-2">(<?= h(statusLabel($project['status'])) ?>)</small>
            </h4>
        </div>

        <a href="projects.php?delete_project=<?= (int)$project['id'] ?>"
           class="btn btn-sm btn-outline-danger"
           onclick="return confirm('Bạn có chắc muốn xóa dự án này? Tất cả công việc và phân công liên quan sẽ bị xóa.')">
            <i class="fas fa-trash"></i> Xóa dự án
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">✅ Đã cập nhật phân công.</div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body" style="max-height: 220px; overflow-y: auto;">
            <b>🤖 AI Summary:</b><br>
            <?= nl2br(h($project['ai_summary'] ?? '')) ?>
        </div>
    </div>

    <div class="d-flex align-items-center mb-3">
        <a href="chat.php?project_id=<?= (int)$project['id'] ?>" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-comments"></i> Chat dự án
        </a>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Công việc</th>
                        <th style="width:140px;">Trạng thái</th>
                        <th style="width:260px;">Đang giao cho</th>
                        <th style="width:320px;">Giao nhiều user</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$todos || $todos->num_rows === 0): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">Chưa có công việc</td>
                    </tr>
                <?php else: ?>
                    <?php while ($t = $todos->fetch_assoc()): ?>
                        <?php
                            $todo_id = (int)$t['id'];

                            $stmtAssigned = $conn->prepare("
                                SELECT u.id, u.username
                                FROM todo_assignments ta
                                JOIN users u ON u.id = ta.user_id
                                WHERE ta.todo_id = ?
                                ORDER BY u.username
                            ");

                            $assignedRows = [];
                            $assigned_ids = [];

                            if ($stmtAssigned) {
                                $stmtAssigned->bind_param("i", $todo_id);
                                $stmtAssigned->execute();
                                $assignedResult = $stmtAssigned->get_result();

                                while ($a = $assignedResult->fetch_assoc()) {
                                    $assignedRows[] = $a;
                                    $assigned_ids[] = (int)$a['id'];
                                }

                                $stmtAssigned->close();
                            }

                            if ($users) {
                                $users->data_seek(0);
                            }
                        ?>
                        <tr>
                            <td>#<?= $todo_id ?></td>
                            <td><?= h($t['title']) ?></td>
                            <td>
                                <span class="badge bg-<?= statusBadge($t['status']) ?>">
                                    <?= h(statusLabel($t['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (count($assigned_ids) === 0): ?>
                                    <span class="text-muted">Chưa giao</span>
                                <?php else: ?>
                                    <?php foreach ($assignedRows as $a2): ?>
                                        <div>• <?= h($a2['username']) ?></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="todo_id" value="<?= $todo_id ?>">
                                    <input type="hidden" name="project_id" value="<?= $project_id ?>">

                                    <select name="assignees[]" class="form-select form-select-sm" multiple size="5">
                                        <?php if ($users): ?>
                                            <?php while ($u = $users->fetch_assoc()): ?>
                                                <option value="<?= (int)$u['id'] ?>"
                                                    <?= in_array((int)$u['id'], $assigned_ids, true) ? 'selected' : '' ?>>
                                                    <?= h($u['username']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>

                                    <button name="assign_multi" class="btn btn-sm btn-primary mt-2 w-100">
                                        <i class="fas fa-user-check"></i> Lưu phân công
                                    </button>
                                    <small class="text-muted d-block mt-1">Giữ Ctrl để chọn nhiều user</small>
                                </form>
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