<?php
session_start();
require_once __DIR__ . '/../config/db.php';
include __DIR__ . '/layouts/header.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$uid  = (int)$user['id'];

$project_id = (int)($_GET['id'] ?? 0);
if ($project_id <= 0) die("Dự án không hợp lệ");

$msg = $err = "";

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

function statusBadge(string $status): string {
    return match ($status) {
        'todo' => 'secondary',
        'in_progress' => 'warning',
        'test' => 'info',
        'done' => 'success',
        default => 'secondary',
    };
}

/* ===== USER CẬP NHẬT TRẠNG THÁI ===== */
if (isset($_POST['update_status'])) {
    $todo_id = (int)($_POST['todo_id'] ?? 0);
    $new_status = trim($_POST['new_status'] ?? '');

    $allowed = ['todo', 'in_progress', 'test'];

    if (!in_array($new_status, $allowed, true)) {
        $err = "Trạng thái không hợp lệ.";
    } else {
        $stmtChk = $conn->prepare("
            SELECT 1
            FROM todos t
            JOIN todo_assignments ta ON ta.todo_id = t.id
            WHERE t.id = ? AND t.project_id = ? AND ta.user_id = ?
            LIMIT 1
        ");

        if ($stmtChk) {
            $stmtChk->bind_param("iii", $todo_id, $project_id, $uid);
            $stmtChk->execute();
            $chk = $stmtChk->get_result();

            if ($chk->num_rows === 0) {
                $err = "Bạn không có quyền cập nhật công việc này.";
            } else {
                $stmtUpdate = $conn->prepare("
                    UPDATE todos
                    SET status = ?
                    WHERE id = ? AND project_id = ?
                ");
                if ($stmtUpdate) {
                    $stmtUpdate->bind_param("sii", $new_status, $todo_id, $project_id);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                    $msg = "✅ Đã cập nhật trạng thái công việc.";
                }
            }

            $stmtChk->close();
        }
    }
}

/* ===== Upload báo cáo (mỗi todo/user chỉ 1 báo cáo, có thể ghi đè) ===== */
if (isset($_POST['send_report'])) {
    $todo_id = (int)($_POST['todo_id'] ?? 0);
    $note    = trim($_POST['report_note'] ?? '');

    $stmtChk = $conn->prepare("
        SELECT 1
        FROM todos t
        JOIN todo_assignments ta ON ta.todo_id = t.id
        WHERE t.id = ? AND t.project_id = ? AND ta.user_id = ?
        LIMIT 1
    ");

    if ($stmtChk) {
        $stmtChk->bind_param("iii", $todo_id, $project_id, $uid);
        $stmtChk->execute();
        $chk = $stmtChk->get_result();

        if ($chk->num_rows === 0) {
            $err = "Bạn không có quyền nộp báo cáo cho công việc này.";
        } elseif (empty($_FILES['report']['name'])) {
            $err = "Vui lòng chọn file báo cáo.";
        } else {
            $ext = strtolower(pathinfo($_FILES['report']['name'], PATHINFO_EXTENSION));
            $allow = ['pdf', 'doc', 'docx', 'zip'];

            if (!in_array($ext, $allow, true)) {
                $err = "Chỉ cho phép PDF, DOC, DOCX, ZIP.";
            } else {
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName = time() . '_' . $uid . '_' . $todo_id . '.' . $ext;

                if (!move_uploaded_file($_FILES['report']['tmp_name'], $uploadDir . $fileName)) {
                    $err = "Upload thất bại.";
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO todo_submissions (todo_id, user_id, report_file, report_note, approved, approved_at)
                        VALUES (?, ?, ?, ?, 0, NULL)
                        ON DUPLICATE KEY UPDATE
                            report_file = VALUES(report_file),
                            report_note = VALUES(report_note),
                            approved = 0,
                            approved_at = NULL
                    ");

                    if ($stmt) {
                        $stmt->bind_param("iiss", $todo_id, $uid, $fileName, $note);
                        $stmt->execute();
                        $stmt->close();
                    }

                    /* Khi user nộp báo cáo -> chuyển sang chờ kiểm tra */
                    $stmtUpdate = $conn->prepare("
                        UPDATE todos
                        SET status = 'test'
                        WHERE id = ? AND project_id = ?
                    ");
                    if ($stmtUpdate) {
                        $stmtUpdate->bind_param("ii", $todo_id, $project_id);
                        $stmtUpdate->execute();
                        $stmtUpdate->close();
                    }

                    $msg = "✅ Đã gửi báo cáo. Chờ Admin duyệt.";
                }
            }
        }

        $stmtChk->close();
    }
}

/* ===== Project info ===== */
$stmtProject = $conn->prepare("
    SELECT id, name, status
    FROM projects
    WHERE id = ?
");
$project = null;

if ($stmtProject) {
    $stmtProject->bind_param("i", $project_id);
    $stmtProject->execute();
    $project = $stmtProject->get_result()->fetch_assoc();
    $stmtProject->close();
}

if (!$project) die("Không tìm thấy dự án");

/* ===== Lấy todo được giao cho user + trạng thái nộp/duyệt ===== */
$stmtTodos = $conn->prepare("
    SELECT t.*,
           s.report_file, s.approved, s.approved_at, s.report_note
    FROM todos t
    JOIN todo_assignments ta ON ta.todo_id = t.id AND ta.user_id = ?
    LEFT JOIN todo_submissions s ON s.todo_id = t.id AND s.user_id = ?
    WHERE t.project_id = ?
    ORDER BY t.created_at DESC
");
$todos = false;

if ($stmtTodos) {
    $stmtTodos->bind_param("iii", $uid, $uid, $project_id);
    $stmtTodos->execute();
    $todos = $stmtTodos->get_result();
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Công việc dự án</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css">
</head>
<body>
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="d-flex align-items-center mb-3">
        <a href="todo.php" class="btn btn-sm btn-outline-secondary me-3">
            <i class="fas fa-arrow-left"></i> Quay lại
        </a>
        <h4 class="mb-0">
            <i class="fas fa-folder-open text-warning me-2"></i>
            <?= h($project['name']) ?>
            <small class="text-muted ms-2">(<?= h(statusLabel($project['status'])) ?>)</small>
        </h4>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= h($msg) ?></div>
    <?php endif; ?>

    <?php if ($err): ?>
        <div class="alert alert-danger"><?= h($err) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Công việc</th>
                        <th style="width:220px;">Trạng thái</th>
                        <th style="width:340px;">Báo cáo - File</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$todos || $todos->num_rows === 0): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">Không có công việc trong dự án này</td>
                    </tr>
                <?php else: ?>
                    <?php while ($t = $todos->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$t['id'] ?></td>
                            <td>
                                <div class="fw-semibold"><?= h($t['title']) ?></div>
                                <?php if (!empty($t['due_date'])): ?>
                                    <small class="text-muted">Hạn: <?= h($t['due_date']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="mb-2">
                                    <span class="badge bg-<?= statusBadge($t['status']) ?>">
                                        <?= h(statusLabel($t['status'])) ?>
                                    </span>
                                    <?php if ((int)$t['approved'] === 1): ?>
                                        <span class="badge bg-primary ms-1">Đã duyệt</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ((int)$t['approved'] !== 1 && $t['status'] !== 'done'): ?>
                                    <form method="post" class="d-flex gap-2">
                                        <input type="hidden" name="todo_id" value="<?= (int)$t['id'] ?>">
                                        <select name="new_status" class="form-select form-select-sm">
                                            <option value="todo" <?= $t['status'] === 'todo' ? 'selected' : '' ?>>Chưa làm</option>
                                            <option value="in_progress" <?= $t['status'] === 'in_progress' ? 'selected' : '' ?>>Đang làm</option>
                                            <option value="test" <?= $t['status'] === 'test' ? 'selected' : '' ?>>Chờ kiểm tra</option>
                                        </select>
                                        <button name="update_status" class="btn btn-sm btn-outline-primary">
                                            Cập nhật
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($t['report_file'])): ?>
                                    <a class="btn btn-sm btn-outline-success" target="_blank"
                                       href="uploads/<?= h($t['report_file']) ?>">
                                        <i class="fas fa-file"></i> Xem file
                                    </a>

                                    <div class="small text-muted mt-1">
                                        <?php if (!empty($t['approved_at'])): ?>
                                            Duyệt lúc: <?= h($t['approved_at']) ?>
                                        <?php else: ?>
                                            Đang chờ duyệt
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($t['report_note'])): ?>
                                        <div class="small mt-1">
                                            <b>Ghi chú:</b> <?= nl2br(h($t['report_note'])) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ((int)$t['approved'] !== 1): ?>
                                        <form method="post" enctype="multipart/form-data" class="mt-2">
                                            <input type="hidden" name="todo_id" value="<?= (int)$t['id'] ?>">
                                            <div class="mb-2">
                                                <input type="file" name="report" class="form-control form-control-sm" required>
                                            </div>
                                            <div class="mb-2">
                                                <textarea name="report_note" class="form-control form-control-sm"
                                                          placeholder="Cập nhật ghi chú (tuỳ chọn)" rows="2"></textarea>
                                            </div>
                                            <button name="send_report" class="btn btn-sm btn-primary">
                                                <i class="fas fa-upload"></i> Gửi lại
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <form method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-start">
                                        <input type="hidden" name="todo_id" value="<?= (int)$t['id'] ?>">
                                        <div class="w-100">
                                            <input type="file" name="report" class="form-control form-control-sm" required>
                                            <textarea name="report_note" class="form-control form-control-sm mt-1"
                                                      placeholder="Ghi chú (tuỳ chọn)" rows="2"></textarea>
                                        </div>
                                        <button name="send_report" class="btn btn-sm btn-primary">
                                            <i class="fas fa-upload"></i> Gửi
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
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