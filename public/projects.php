<?php
session_start();
require_once __DIR__ . '/../config/db.php';
include __DIR__ . '/layouts/header.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  header("Location: dashboard.php"); exit;
}

$project_id = (int)($_GET['id'] ?? 0);
$msg = $err = "";

/* ===== Danh sách user role=user ===== */
$users = $conn->query("SELECT id, username FROM users WHERE role='user' ORDER BY username");

/* ===== Giao việc (multi-user) ===== */
if (isset($_POST['assign_multi'])) {
  $todo_id = (int)$_POST['todo_id'];
  $project_id = (int)$_POST['project_id'];
  $assignees = $_POST['assignees'] ?? [];

  // xóa cũ → set mới (đơn giản, ổn định)
  $conn->query("DELETE FROM todo_assignments WHERE todo_id=$todo_id");

  if (is_array($assignees) && count($assignees) > 0) {
    $stmt = $conn->prepare("INSERT INTO todo_assignments(todo_id, user_id) VALUES(?,?)");
    foreach ($assignees as $u) {
      $uid = (int)$u;
      $stmt->bind_param("ii", $todo_id, $uid);
      $stmt->execute();
    }
    $conn->query("UPDATE todos SET status='Đang làm' WHERE id=$todo_id");
    $conn->query("UPDATE projects SET status='Đang làm' WHERE id=$project_id");
    $msg = "✅ Đã giao việc.";
  } else {
    $msg = "✅ Đã bỏ giao (không có user).";
  }

  header("Location: projects.php?id=$project_id&msg=1");
  exit;
}

/* ===== Danh sách dự án dạng thư mục ===== */
if ($project_id === 0) {
  $projects = $conn->query("SELECT id, name, status FROM projects ORDER BY created_at DESC");
} else {
  $project = $conn->query("SELECT * FROM projects WHERE id=$project_id")->fetch_assoc();
  if (!$project) die("Không tìm thấy dự án");

  $todos = $conn->query("
    SELECT t.*
    FROM todos t
    WHERE t.project_id=$project_id
    ORDER BY t.created_at DESC
  ");
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Admin - Giao việc</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/sidebar.css">
</head>
<body>
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<div class="content-wrapper">

<?php if ($project_id === 0): ?>

  <h3 class="mb-3">📁 DỰ ÁN ĐÃ PHÂN TÍCH</h3>
  <div class="row g-3">
    <?php while ($p = $projects->fetch_assoc()): ?>
      <div class="col-md-3">
        <a href="projects.php?id=<?= (int)$p['id'] ?>" class="text-decoration-none">
          <div class="card text-center p-4 shadow-sm h-100">
            <i class="fas fa-folder fa-3x text-warning"></i>
            <h6 class="mt-2"><?= htmlspecialchars($p['name']) ?></h6>
            <small class="text-muted"><?= htmlspecialchars($p['status']) ?></small>
          </div>
        </a>
      </div>
    <?php endwhile; ?>
  </div>

<?php else: ?>

  <div class="d-flex align-items-center mb-3">
    <a href="projects.php" class="btn btn-sm btn-outline-secondary me-3">
      <i class="fas fa-arrow-left"></i> Quay lại
    </a>
    <h4 class="mb-0">
      <i class="fas fa-folder-open text-warning me-2"></i>
      <?= htmlspecialchars($project['name']) ?>
      <small class="text-muted ms-2">(<?= htmlspecialchars($project['status']) ?>)</small>
    </h4>
  </div>

  <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">✅ Đã cập nhật phân công.</div>
  <?php endif; ?>

<div class="card mb-3">
  <div class="card-body"
       style="max-height: 220px; overflow-y: auto;">
    <b>🤖 AI Summary:</b><br>
    <?= nl2br(htmlspecialchars($project['ai_summary'] ?? '')) ?>
  </div>
</div>

   <div class="d-flex align-items-center mb-3">
        <a href="chat.php?project_id=<?= $project['id'] ?>"
            class="btn btn-sm btn-outline-primary">
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
            <th style="width:260px;">Đang giao cho</th>
            <th style="width:320px;">Giao nhiều user</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($t = $todos->fetch_assoc()): ?>
          <?php
            $todo_id = (int)$t['id'];
            $assigned = $conn->query("
              SELECT u.id, u.username
              FROM todo_assignments ta
              JOIN users u ON u.id=ta.user_id
              WHERE ta.todo_id=$todo_id
              ORDER BY u.username
            ");
            $assigned_ids = [];
            while ($a = $assigned->fetch_assoc()) $assigned_ids[] = (int)$a['id'];

            // reset pointer user list
            $users->data_seek(0);
          ?>
          <tr>
            <td>#<?= $todo_id ?></td>
            <td><?= htmlspecialchars($t['title']) ?></td>
            <td>
              <?php if (count($assigned_ids) === 0): ?>
                <span class="text-muted">Chưa giao</span>
              <?php else: ?>
                <?php
                  $assigned->data_seek(0);
                  while ($a2 = $assigned->fetch_assoc()):
                ?>
                  <div>• <?= htmlspecialchars($a2['username']) ?></div>
                <?php endwhile; ?>
              <?php endif; ?>
            </td>
            <td>
              <form method="post">
                <input type="hidden" name="todo_id" value="<?= $todo_id ?>">
                <input type="hidden" name="project_id" value="<?= $project_id ?>">

                <select name="assignees[]" class="form-select form-select-sm" multiple size="5">
                  <?php while ($u = $users->fetch_assoc()): ?>
                    <option value="<?= (int)$u['id'] ?>"
                      <?= in_array((int)$u['id'], $assigned_ids, true) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($u['username']) ?>
                    </option>
                  <?php endwhile; ?>
                </select>

                <button name="assign_multi" class="btn btn-sm btn-primary mt-2 w-100">
                  <i class="fas fa-user-check"></i> Lưu phân công
                </button>
                <small class="text-muted d-block mt-1">Giữ Ctrl để chọn nhiều user</small>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

</div>
</body>
</html>
