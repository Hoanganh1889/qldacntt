<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/services/ai_service.php';

/* ===== AUTH ===== */
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$uid  = (int)$user['id'];
$role = $user['role'] ?? 'user';

$msg = '';
$err = '';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function projectStatusLabel(string $status): string {
    return match ($status) {
        'todo' => 'Chưa bắt đầu',
        'in_progress' => 'Đang làm',
        'test' => 'Đang kiểm tra',
        'done' => 'Hoàn thành',
        default => $status,
    };
}

function mapPriorityToDb(?string $priority): string {
    $priority = trim((string)$priority);

    return match ($priority) {
        'Cao', 'high', 'High' => 'high',
        'Thấp', 'low', 'Low' => 'low',
        default => 'medium',
    };
}

/* ===== PHÂN TÍCH AI & TẠO PROJECT + TODO ===== */
if (isset($_POST['analyze'])) {
    $name       = trim($_POST['name'] ?? '');
    $goal       = trim($_POST['goal'] ?? '');
    $scope      = trim($_POST['scope'] ?? '');
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date   = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $budget     = ($_POST['budget'] !== '') ? $_POST['budget'] : null;

    if ($name === '') {
        $err = "Vui lòng nhập tên dự án.";
    } else {
        try {
            $payload = [
                "name"       => $name,
                "goal"       => $goal,
                "scope"      => $scope,
                "start_date" => $start_date,
                "end_date"   => $end_date,
                "budget"     => $budget
            ];

            $ai = ai_analyze_project($payload);

            $desc            = '';
            $ai_summary      = $ai['summary'] ?? null;
            $risk_level      = $ai['risk_level'] ?? null;
            $ai_raw          = json_encode($ai, JSON_UNESCAPED_UNICODE);

            $budget_str     = $budget !== null ? (string)$budget : null;
            $risk_level_str = $risk_level !== null ? (string)$risk_level : null;
            $summary_str    = $ai_summary !== null ? (string)$ai_summary : null;
            $ai_raw_str     = (string)$ai_raw;

            $conn->begin_transaction();

            /* 1) LƯU PROJECT */
            $stmt = $conn->prepare("
                INSERT INTO projects
                (name, description, user_id, goal, scope, start_date, end_date, budget, risk_level, ai_summary, ai_raw, status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?, 'todo')
            ");

            if (!$stmt) {
                throw new Exception("Không prepare được câu lệnh tạo project.");
            }

            $stmt->bind_param(
                "ssissssssss",
                $name,
                $desc,
                $uid,
                $goal,
                $scope,
                $start_date,
                $end_date,
                $budget_str,
                $risk_level_str,
                $summary_str,
                $ai_raw_str
            );
            $stmt->execute();
            $stmt->close();

            $project_id = $conn->insert_id;

            /* 2) TẠO TODO */
            $tasks = $ai['tasks'] ?? [];
            $today = new DateTime();

            foreach ($tasks as $t) {
                $title = trim($t['title'] ?? '');
                if ($title === '') {
                    continue;
                }

                $priorityRaw = $t['priority'] ?? 'medium';
                $priority = mapPriorityToDb($priorityRaw);

                $due_days = (int)($t['due_days'] ?? 7);
                if ($due_days <= 0) {
                    $due_days = 7;
                }

                $due_date = (clone $today)->modify("+{$due_days} days")->format('Y-m-d');

                $stmt2 = $conn->prepare("
                    INSERT INTO todos
                    (title, status, user_id, project_id, due_date, priority)
                    VALUES (?, 'todo', ?, ?, ?, ?)
                ");

                if (!$stmt2) {
                    throw new Exception("Không prepare được câu lệnh tạo todo.");
                }

                $stmt2->bind_param(
                    "siiss",
                    $title,
                    $uid,
                    $project_id,
                    $due_date,
                    $priority
                );
                $stmt2->execute();
                $stmt2->close();
            }

            /* 3) LOG */
            $logContent = "User {$user['username']} phân tích AI dự án: {$name} (project_id={$project_id})";
            $stmtLog = $conn->prepare("INSERT INTO system_logs(content) VALUES (?)");
            if ($stmtLog) {
                $stmtLog->bind_param("s", $logContent);
                $stmtLog->execute();
                $stmtLog->close();
            }

            $conn->commit();
            $msg = "✅ Đã phân tích AI và tạo dự án thành công.";
        } catch (Throwable $e) {
            $conn->rollback();
            $err = "Lỗi phân tích/lưu dữ liệu: " . $e->getMessage();
        }
    }
}

/* ===== LẤY DANH SÁCH PROJECT ===== */
if ($role === 'admin') {
    $projects = $conn->query("
        SELECT p.*, u.username
        FROM projects p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
    ");
} else {
    $stmtProjects = $conn->prepare("
        SELECT *
        FROM projects
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $projects = false;

    if ($stmtProjects) {
        $stmtProjects->bind_param("i", $uid);
        $stmtProjects->execute();
        $projects = $stmtProjects->get_result();
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Dự án (AI)</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css">
</head>
<body>

<div class="header d-flex align-items-center justify-content-between px-4 shadow-sm"
     style="height:70px;position:fixed;top:0;left:0;width:100%;background:#fff;z-index:1030;">
    <h4 class="mb-0">📁 DỰ ÁN (AI)</h4>
    <a href="logout.php" class="btn btn-sm btn-outline-danger">Đăng xuất</a>
</div>

<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<div class="content-wrapper">

    <h3 class="mb-3">🤖 PHÂN TÍCH & QUẢN LÝ DỰ ÁN</h3>

    <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Tên dự án</label>
                    <input name="name" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ngày bắt đầu</label>
                    <input type="date" name="start_date" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ngày kết thúc</label>
                    <input type="date" name="end_date" class="form-control">
                </div>

                <div class="col-md-12">
                    <label class="form-label">Mục tiêu</label>
                    <textarea name="goal" class="form-control" rows="2"></textarea>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Phạm vi (Scope)</label>
                    <textarea name="scope" class="form-control" rows="3"></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Ngân sách (tuỳ chọn)</label>
                    <input type="number" step="0.01" name="budget" class="form-control">
                </div>

                <div class="col-md-8 d-flex align-items-end justify-content-end">
                    <button name="analyze" class="btn btn-primary">
                        <i class="fas fa-wand-magic-sparkles me-1"></i>
                        Phân tích AI & Tạo dự án
                    </button>
                </div>
            </form>
        </div>
    </div>

    <h5 class="mb-3">📁 Danh sách dự án</h5>
    <div class="row g-3">
        <?php if (!$projects || $projects->num_rows === 0): ?>
            <p class="text-muted">Chưa có dự án</p>
        <?php else: ?>
            <?php while ($p = $projects->fetch_assoc()): ?>
                <div class="col-md-3">
                    <a href="project_detail.php?id=<?= (int)$p['id'] ?>" class="text-decoration-none">
                        <div class="card text-center p-4 shadow-sm h-100">
                            <i class="fas fa-folder fa-3x text-warning"></i>
                            <h6 class="mt-2"><?= h($p['name']) ?></h6>
                            <small class="text-muted">
                                <?= h(projectStatusLabel($p['status'])) ?>
                                <?php if ($role === 'admin' && isset($p['username'])): ?>
                                    <br>👤 <?= h($p['username']) ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

</div>

</body>
</html>