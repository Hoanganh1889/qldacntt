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
$role = $user['role'];

$msg = '';
$err = '';

/* ===== PHÂN TÍCH AI & TẠO PROJECT + TODO ===== */
if (isset($_POST['analyze'])) {
    $name       = trim($_POST['name'] ?? '');
    $goal       = trim($_POST['goal'] ?? '');
    $scope      = trim($_POST['scope'] ?? '');
    $start_date = $_POST['start_date'] ?: null;
    $end_date   = $_POST['end_date'] ?: null;
    $budget     = ($_POST['budget'] !== '') ? $_POST['budget'] : null;

    if ($name === '') {
        $err = "Vui lòng nhập tên dự án.";
    } else {
        try {
            /* 1) GỌI AI */
            $payload = [
                "name"       => $name,
                "goal"       => $goal,
                "scope"      => $scope,
                "start_date" => $start_date,
                "end_date"   => $end_date,
                "budget"     => $budget
            ];
            $ai = ai_analyze_project($payload);

            /* 2) BIẾN TRUNG GIAN (FIX bind_param) */
            $desc            = '';
            $ai_summary      = $ai['summary'] ?? null;
            $risk_level      = $ai['risk_level'] ?? null;
            $ai_raw          = json_encode($ai, JSON_UNESCAPED_UNICODE);

            $budget_str     = $budget !== null ? (string)$budget : null;
            $risk_level_str = $risk_level !== null ? (string)$risk_level : null;
            $summary_str    = $ai_summary !== null ? (string)$ai_summary : null;
            $ai_raw_str     = (string)$ai_raw;

            /* 3) LƯU PROJECT */
            $stmt = $conn->prepare("
            INSERT INTO projects
            (name, description, user_id, goal, scope, start_date, end_date, budget, risk_level, ai_summary, ai_raw, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?, 'Mới')
        ");
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
            $project_id = $conn->insert_id;

            /* 4) TẠO TODO (CHƯA PHÂN CÔNG) */
            $tasks = $ai['tasks'] ?? [];
            $today = new DateTime();

            foreach ($tasks as $t) {
                $title = trim($t['title'] ?? '');
                if ($title === '') continue;

                $priority = $t['priority'] ?? 'Trung bình';
                if (!in_array($priority, ['Thấp','Trung bình','Cao'])) {
                    $priority = 'Trung bình';
                }

                $due_days = (int)($t['due_days'] ?? 7);
                $due_date = (clone $today)->modify("+{$due_days} days")->format('Y-m-d');

                $stmt2 = $conn->prepare("
                    INSERT INTO todos
                    (title, status, user_id, project_id, due_date, priority)
                    VALUES (?, 'Chưa làm', ?, ?, ?, ?)
                ");
                $stmt2->bind_param(
                    "siiss",
                    $title,
                    $uid,
                    $project_id,
                    $due_date,
                    $priority
                );
                $stmt2->execute();
            }

            /* 5) LOG */
            $conn->query("
                INSERT INTO system_logs(content)
                VALUES (
                    'User {$user['username']} phân tích AI dự án: {$name} (project_id={$project_id})'
                )
            ");

            $msg = "✅ Đã phân tích AI và tạo dự án thành công.";
        } catch (Throwable $e) {
            $err = "Lỗi phân tích/lưu dữ liệu: " . $e->getMessage();
        }
    }
}

/* ===== LẤY DANH SÁCH PROJECT ===== */
if ($role === 'admin') {
    // Admin thấy tất cả dự án
    $projects = $conn->query("
        SELECT p.*, u.username
        FROM projects p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
    ");
} else {
    // User chỉ thấy dự án của mình
    $projects = $conn->query("
        SELECT *
        FROM projects
        WHERE user_id = $uid
        ORDER BY created_at DESC
    ");
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

<!-- HEADER -->
<div class="header d-flex align-items-center justify-content-between px-4 shadow-sm"
     style="height:70px;position:fixed;top:0;left:0;width:100%;background:#fff;z-index:1030;">
    <h4 class="mb-0">📁 DỰ ÁN (AI)</h4>
    <a href="logout.php" class="btn btn-sm btn-outline-danger">Đăng xuất</a>
</div>

<!-- SIDEBAR -->
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<!-- CONTENT -->
<div class="content-wrapper">

    <h3 class="mb-3">🤖 PHÂN TÍCH & QUẢN LÝ DỰ ÁN</h3>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>

    <!-- FORM TẠO DỰ ÁN -->
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

    <!-- PROJECT LIST -->
    <h5 class="mb-3">📁 Danh sách dự án</h5>
    <div class="row g-3">
        <?php if ($projects->num_rows === 0): ?>
            <p class="text-muted">Chưa có dự án</p>
        <?php endif; ?>

        <?php while ($p = $projects->fetch_assoc()): ?>
            <div class="col-md-3">
                <a href="project_detail.php?id=<?= $p['id'] ?>" class="text-decoration-none">
                    <div class="card text-center p-4 shadow-sm h-100">
                        <i class="fas fa-folder fa-3x text-warning"></i>
                        <h6 class="mt-2"><?= htmlspecialchars($p['name']) ?></h6>
                        <small class="text-muted">
                            <?= $p['status'] ?>
                            <?php if ($role === 'admin'): ?>
                                <br>👤 <?= htmlspecialchars($p['username']) ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </a>
            </div>
        <?php endwhile; ?>
    </div>

</div>

</body>
</html>
