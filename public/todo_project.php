<?php
session_start();
require_once __DIR__ . '/../config/db.php';
include __DIR__ . '/layouts/header.php';
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$user = $_SESSION['user'];
$uid  = (int)$user['id'];

$project_id = (int)($_GET['id'] ?? 0);
if ($project_id <= 0) die("Dự án không hợp lệ");

$msg = $err = "";

/* ===== Upload báo cáo (mỗi todo/user chỉ 1 báo cáo, có thể ghi đè) ===== */
if (isset($_POST['send_report'])) {
    $todo_id = (int)($_POST['todo_id'] ?? 0);
    $note    = trim($_POST['report_note'] ?? '');

    // check todo có được giao cho user không
    $chk = $conn->query("
      SELECT 1
      FROM todos t
      JOIN todo_assignments ta ON ta.todo_id=t.id
      WHERE t.id=$todo_id AND t.project_id=$project_id AND ta.user_id=$uid
      LIMIT 1
    ");
    if ($chk->num_rows === 0) {
        $err = "Bạn không có quyền nộp báo cáo cho công việc này.";
    } elseif (empty($_FILES['report']['name'])) {
        $err = "Vui lòng chọn file báo cáo.";
    } else {
        $ext = strtolower(pathinfo($_FILES['report']['name'], PATHINFO_EXTENSION));
        $allow = ['pdf','doc','docx','zip'];
        if (!in_array($ext, $allow)) {
            $err = "Chỉ cho phép PDF, DOC, DOCX, ZIP.";
        } else {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $fileName = time().'_'.$uid.'_'.$todo_id.'.'.$ext;
            if (!move_uploaded_file($_FILES['report']['tmp_name'], $uploadDir.$fileName)) {
                $err = "Upload thất bại.";
            } else {
                // Upsert: nếu đã nộp thì cập nhật file/note, reset approved về 0
                $stmt = $conn->prepare("
                  INSERT INTO todo_submissions (todo_id, user_id, report_file, report_note, approved, approved_at)
                  VALUES (?, ?, ?, ?, 0, NULL)
                  ON DUPLICATE KEY UPDATE
                    report_file=VALUES(report_file),
                    report_note=VALUES(report_note),
                    approved=0,
                    approved_at=NULL
                ");
                $stmt->bind_param("iiss", $todo_id, $uid, $fileName, $note);
                $stmt->execute();

                // Cập nhật status todo sang Hoàn thành (ở mức user nộp xong)
                $conn->query("UPDATE todos SET status='Hoàn thành' WHERE id=$todo_id AND project_id=$project_id");

                $msg = "✅ Đã gửi báo cáo. Chờ Admin duyệt.";
            }
        }
    }
}

/* ===== Project info ===== */
$project = $conn->query("
  SELECT id, name, status
  FROM projects
  WHERE id=$project_id
")->fetch_assoc();
if (!$project) die("Không tìm thấy dự án");

/* ===== Lấy todo được giao cho user + trạng thái nộp/duyệt ===== */
$todos = $conn->query("
  SELECT t.*,
         s.report_file, s.approved, s.approved_at, s.report_note
  FROM todos t
  JOIN todo_assignments ta ON ta.todo_id=t.id AND ta.user_id=$uid
  LEFT JOIN todo_submissions s ON s.todo_id=t.id AND s.user_id=$uid
  WHERE t.project_id=$project_id
  ORDER BY t.created_at DESC
");
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Công việc dự án</title>
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
      <?= htmlspecialchars($project['name']) ?>
      <small class="text-muted ms-2">(<?= htmlspecialchars($project['status']) ?>)</small>
    </h4>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>

  <div class="card">
    <div class="card-body table-responsive">
      <table class="table table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:70px;">ID</th>
            <th>Công việc</th>
            <th style="width:140px;">Trạng thái</th>
            <th style="width:320px;">Báo cáo - File</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($todos->num_rows === 0): ?>
          <tr><td colspan="4" class="text-center text-muted">Không có công việc trong dự án này</td></tr>
        <?php endif; ?>

        <?php while ($t = $todos->fetch_assoc()): ?>
          <tr>
            <td><?= (int)$t['id'] ?></td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($t['title']) ?></div>
              <?php if (!empty($t['due_date'])): ?>
                <small class="text-muted">Hạn: <?= htmlspecialchars($t['due_date']) ?></small>
              <?php endif; ?>
            </td>
            <td>
              <?php
                $status = $t['status'];
                $badge = ($status==='Hoàn thành')?'success':(($status==='Đang làm')?'warning':'secondary');
              ?>
              <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($status) ?></span>
              <?php if ((int)$t['approved'] === 1): ?>
                <span class="badge bg-primary ms-1">Đã duyệt</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($t['report_file'])): ?>
                <a class="btn btn-sm btn-outline-success" target="_blank"
                   href="uploads/<?= htmlspecialchars($t['report_file']) ?>">
                  <i class="fas fa-file"></i> Xem file
                </a>
                <div class="small text-muted mt-1">
                  <?php if (!empty($t['approved_at'])): ?>
                    Duyệt lúc: <?= htmlspecialchars($t['approved_at']) ?>
                  <?php else: ?>
                    Đang chờ duyệt
                  <?php endif; ?>
                </div>
                <?php if (!empty($t['report_note'])): ?>
                  <div class="small mt-1"><b>Ghi chú:</b> <?= nl2br(htmlspecialchars($t['report_note'])) ?></div>
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
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>
