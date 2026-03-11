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

    $wbsText     = $_POST['wbs_text'];
    $projectDesc = $_POST['project_desc'];
    $projectName = 'Dự án AI - ' . date('d/m/Y');

    /* ---- 1. TẠO PROJECT ---- */
    $aiSummary = mb_substr($wbsText, 0, 300);

    $stmt = $conn->prepare("
        INSERT INTO projects
        (name, description, user_id, ai_summary, ai_raw, status)
        VALUES (?, ?, ?, ?, ?, 'Mới')
    ");
    $stmt->bind_param(
        "ssiss",
        $projectName,
        $projectDesc,
        $user['id'],
        $aiSummary,
        $wbsText
    );
    $stmt->execute();
    $projectId = $stmt->insert_id;

    /* ---- 2. PARSE WBS ---- */
    $tasks = parse_wbs_to_tasks($wbsText);

    /* ---- 3. TẠO TODOS ---- */
    $stmtTodo = $conn->prepare("
        INSERT INTO todos
        (title, project_id, user_id, status, priority)
        VALUES (?, ?, ?, 'Chưa làm', 'Trung bình')
    ");

    foreach ($tasks as $title) {
        $stmtTodo->bind_param(
            "sii",
            $title,
            $projectId,
            $user['id']
        );
        $stmtTodo->execute();
    }

    header("Location: projects.php?created=1");
    exit;
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
pre{
    white-space:pre-wrap;
    background:#f8f9fa;
    padding:16px;
    border-radius:8px;
    font-size:0.95rem;
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
