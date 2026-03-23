<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ai.php';
require_once __DIR__ . '/../config/wbs_parser.php';

/* ===== CHỈ ADMIN ===== */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$user = $_SESSION['user'];
$pageTitle = 'AI Insights – Phân tích dự án (WBS)';
$current   = basename($_SERVER['PHP_SELF']);

$result = '';
$error  = '';

/* ================= PHÂN TÍCH AI ================= */
if (isset($_POST['analyze'])) {
    $description = trim($_POST['description']);

    if (strlen($description) < 30) {
        $error = '❌ Mô tả dự án quá ngắn (ít nhất 30 ký tự)';
    } else {
        try {
            $prompt = <<<PROMPT
Bạn là chuyên gia quản lý dự án CNTT.

Dự án:
{$description}

Hãy phân tích theo WBS (Work Breakdown Structure):
- Chia giai đoạn → hạng mục → công việc
- Đánh số dạng 1.1, 1.2, 2.1...
- Mỗi công việc một dòng
PROMPT;

            $result = call_openrouter($prompt);
        } catch (Exception $e) {
            $error = '⚠️ Lỗi AI: ' . $e->getMessage();
        }
    }
}

/* ================= TẠO PROJECT + TODOS ================= */
if (isset($_POST['create_project'])) {

    // Lấy thông tin từ form
    $wbsText     = $_POST['wbs_text'];
    $projectDesc = $_POST['project_desc'];
    $projectName = 'Dự án AI - ' . date('d/m/Y');

    // Đảm bảo không có giá trị NULL hoặc trống cho các trường liên quan đến số
    $goal = isset($_POST['goal']) ? $_POST['goal'] : '';
    $scope = isset($_POST['scope']) ? $_POST['scope'] : '';
    $start_date = isset($_POST['start_date']) && $_POST['start_date'] !== '' ? $_POST['start_date'] : NULL;  // Sử dụng NULL nếu không có giá trị
    $end_date = isset($_POST['end_date']) && $_POST['end_date'] !== '' ? $_POST['end_date'] : NULL;  // Sử dụng NULL nếu không có giá trị
    $budget_str = isset($_POST['budget']) && $_POST['budget'] !== '' ? $_POST['budget'] : NULL;  // Sử dụng NULL nếu không có giá trị hợp lệ
    $risk_level_str = isset($_POST['risk_level']) ? $_POST['risk_level'] : '';
    $summary_str = isset($_POST['summary']) ? $_POST['summary'] : '';
    $ai_raw_str = isset($wbsText) ? $wbsText : '';

    /* ---- 1. TẠO PROJECT ---- */
    $stmt = $conn->prepare("
        INSERT INTO projects
        (name, description, user_id, goal, scope, start_date, end_date, budget, risk_level, ai_summary, ai_raw, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?, 'todo')
    ");

    if (!$stmt) {
        throw new Exception("Không thể prepare câu lệnh tạo project.");
    }

    $stmt->bind_param(
        "ssissssssss",
        $projectName,
        $projectDesc,
        $user['id'],
        $goal,
        $scope,
        $start_date,
        $end_date,
        $budget_str,  // Sử dụng NULL nếu không có giá trị cho budget
        $risk_level_str,
        $summary_str,
        $ai_raw_str
    );
    $stmt->execute();
    $stmt->close();

    $project_id = $conn->insert_id;

    /* ---- 2. TẠO TODO ---- */
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
            throw new Exception("Không thể prepare câu lệnh tạo todo.");
        }

        $stmt2->bind_param(
            "siiss",
            $title,
            $user['id'],
            $project_id,
            $due_date,
            $priority
        );
        $stmt2->execute();
        $stmt2->close();
    }

    /* ---- 3. LOG ---- */
    $logContent = "User {$user['username']} phân tích AI dự án: {$projectName} (project_id={$project_id})";
    $stmtLog = $conn->prepare("INSERT INTO system_logs(content) VALUES (?)");
    if ($stmtLog) {
        $stmtLog->bind_param("s", $logContent);
        $stmtLog->execute();
        $stmtLog->close();
    }

    $conn->commit();
    $msg = "✅ Đã phân tích AI và tạo dự án thành công.";
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>AI Insights</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
        pre {
            white-space: pre-wrap;
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/layouts/header.php'; ?>
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<div class="content-wrapper">

    <h4 class="mb-4">
        <i class="fas fa-lightbulb me-2"></i> Phân tích dự án bằng AI (WBS)
    </h4>

    <form method="post" class="card p-4 mb-4">
        <label class="form-label fw-semibold">Mô tả dự án</label>
        <textarea name="description" rows="5" class="form-control"
                  placeholder="Ví dụ: Xây dựng hệ thống quản lý công việc cho doanh nghiệp..."
                  required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

        <button name="analyze" class="btn btn-primary mt-3">
            <i class="fas fa-robot"></i> Phân tích WBS
        </button>
    </form>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($result): ?>
        <div class="card p-4">
            <h6 class="mb-3"><i class="fas fa-sitemap me-2"></i>Kết quả WBS</h6>

            <pre><?= htmlspecialchars($result) ?></pre>

            <form method="post" class="mt-3">
                <input type="hidden" name="wbs_text" value="<?= htmlspecialchars($result) ?>">
                <input type="hidden" name="project_desc"
                       value="<?= htmlspecialchars($_POST['description']) ?>">

                <button name="create_project" class="btn btn-success">
                    <i class="fas fa-folder-plus"></i> Tạo dự án & công việc
                </button>
            </form>
        </div>
    <?php endif; ?>

</div>
</body>
</html>