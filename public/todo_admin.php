<?php
session_start();
require_once __DIR__ . '/../config/db.php';
include __DIR__ . '/layouts/header.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  header("Location: dashboard.php"); exit;
}

$admin = $_SESSION['user'];
$project_id = (int)($_GET['id'] ?? 0);

/* ===== Hàm kiểm tra dự án hoàn thành: 
   Mỗi todo được coi là hoàn thành khi TẤT CẢ user được giao đã có submission approved=1 */
function refresh_project_status(mysqli $conn, int $project_id): void {
  // số todo trong dự án
  $r1 = $conn->query("SELECT COUNT(*) c FROM todos WHERE project_id=$project_id")->fetch_assoc();
  $total_todos = (int)($r1['c'] ?? 0);
  if ($total_todos === 0) {
    $conn->query("UPDATE projects SET status='Mới' WHERE id=$project_id");
    return;
  }

  // đếm todo đạt điều kiện: số assignees == số submissions approved
  $sql = "
    SELECT COUNT(*) c
    FROM todos t
    WHERE t.project_id = $project_id
      AND (
        SELECT COUNT(*) FROM todo_assignments ta WHERE ta.todo_id = t.id
      ) > 0
      AND (
        SELECT COUNT(*) 
        FROM todo_submissions s
        WHERE s.todo_id = t.id AND s.approved = 1
      ) = (
        SELECT COUNT(*) FROM todo_assignments ta2 WHERE ta2.todo_id = t.id
      )
  ";
  $r2 = $conn->query($sql)->fetch_assoc();
  $done_todos = (int)($r2['c'] ?? 0);

  if ($done_todos === $total_todos) {
    $conn->query("UPDATE projects SET status='Hoàn thành' WHERE id=$project_id");
  } else {
    $conn->query("UPDATE projects SET status='Đang làm' WHERE id=$project_id");
  }
}

/* ===== Duyệt / Từ chối 1 submission (todo_id + user_id) ===== */
if (isset($_GET['approve']) && isset($_GET['todo']) && isset($_GET['user'])) {
  $todo_id = (int)$_GET['todo'];
  $user_id = (int)$_GET['user'];

  $stmt = $conn->prepare("
    UPDATE todo_submissions
    SET approved=1, approved_at=NOW()
    WHERE todo_id=? AND user_id=?
  ");
  $stmt->bind_param("ii", $todo_id, $user_id);
  $stmt->execute();

  $conn->query("INSERT INTO system_logs(content) VALUES ('Admin {$admin['username']} duyệt submission todo={$todo_id} user={$user_id}')");

  refresh_project_status($conn, $project_id);

  header("Location: todo_admin.php?id=$project_id");
  exit;
}

if (isset($_GET['reject']) && isset($_GET['todo']) && isset($_GET['user'])) {
  $todo_id = (int)$_GET['todo'];
  $user_id = (int)$_GET['user'];

  $stmt = $conn->prepare("
    UPDATE todo_submissions
    SET approved=0, approved_at=NULL
    WHERE todo_id=? AND user_id=?
  ");
  $stmt->bind_param("ii", $todo_id, $user_id);
  $stmt->execute();

  $conn->query("INSERT INTO system_logs(content) VALUES ('Admin {$admin['username']} từ chối submission todo={$todo_id} user={$user_id}')");

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
  $project = $conn->query("SELECT * FROM projects WHERE id=$project_id")->fetch_assoc();
  if (!$project) die("Không tìm thấy dự án");

  // Lấy toàn bộ todo + danh sách người được giao + submission
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
  <title>Admin - Duyệt công việc</title>
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
    <?php while ($p = $projects->fetch_assoc()): ?>
      <div class="col-md-3">
        <a href="todo_admin.php?id=<?= (int)$p['id'] ?>" class="text-decoration-none">
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
    <a href="todo_admin.php" class="btn btn-sm btn-outline-secondary me-3">
      <i class="fas fa-arrow-left"></i> Quay lại
    </a>
    <h4 class="mb-0">
      <i class="fas fa-folder-open text-warning me-2"></i>
      <?= htmlspecialchars($project['name']) ?>
      <small class="text-muted ms-2">(<?= htmlspecialchars($project['status']) ?>)</small>
    </h4>
  </div>

<div class="card mb-3">
  <div class="card-body"
       style="max-height: 220px; overflow-y: auto;">
    <b>🤖 AI Summary:</b><br>
    <?= nl2br(htmlspecialchars($project['ai_summary'] ?? '')) ?>
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
        <?php while ($t = $todos->fetch_assoc()): ?>
          <?php
            $todo_id = (int)$t['id'];

            // assignees
            $assignees = $conn->query("
              SELECT u.id, u.username
              FROM todo_assignments ta
              JOIN users u ON u.id = ta.user_id
              WHERE ta.todo_id = $todo_id
              ORDER BY u.username
            ");

            // submissions for this todo
            $subs = $conn->query("
              SELECT s.*, u.username
              FROM todo_submissions s
              JOIN users u ON u.id = s.user_id
              WHERE s.todo_id = $todo_id
              ORDER BY s.created_at DESC
            ");
          ?>
          <tr>
            <td>#<?= $todo_id ?></td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($t['title']) ?></div>
              <small class="text-muted">Status: <?= htmlspecialchars($t['status']) ?></small>
            </td>
            <td>
              <?php if ($assignees->num_rows === 0): ?>
                <span class="text-muted">Chưa giao</span>
              <?php else: ?>
                <?php while ($a = $assignees->fetch_assoc()): ?>
                  <div>• <?= htmlspecialchars($a['username']) ?></div>
                <?php endwhile; ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($subs->num_rows === 0): ?>
                <span class="text-muted">Chưa có báo cáo</span>
              <?php else: ?>
                <?php while ($s = $subs->fetch_assoc()): ?>
                  <div class="border rounded p-2 mb-2">
                    <div class="d-flex justify-content-between">
                      <div class="fw-semibold"><?= htmlspecialchars($s['username']) ?></div>
                      <div>
                        <?php if ((int)$s['approved'] === 1): ?>
                          <span class="badge bg-success">Approved</span>
                        <?php else: ?>
                          <span class="badge bg-secondary">Pending</span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="mt-1">
                      <a target="_blank" href="uploads/<?= htmlspecialchars($s['report_file']) ?>">
                        📄 <?= htmlspecialchars($s['report_file']) ?>
                      </a>
                    </div>
                    <?php if (!empty($s['report_note'])): ?>
                      <div class="small mt-1"><b>Note:</b> <?= nl2br(htmlspecialchars($s['report_note'])) ?></div>
                    <?php endif; ?>
                    <div class="small text-muted mt-1">Nộp: <?= htmlspecialchars($s['created_at']) ?></div>
                  </div>
                <?php endwhile; ?>
              <?php endif; ?>
            </td>
            <td>
              <?php
                // duyệt từng user theo submission (nếu user chưa nộp thì không duyệt được)
                $subs2 = $conn->query("
                  SELECT s.todo_id, s.user_id, s.approved, u.username
                  FROM todo_submissions s
                  JOIN users u ON u.id=s.user_id
                  WHERE s.todo_id=$todo_id
                  ORDER BY s.created_at DESC
                ");
              ?>
              <?php if ($subs2->num_rows === 0): ?>
                —
              <?php else: ?>
                <?php while ($x = $subs2->fetch_assoc()): ?>
                  <div class="d-flex gap-1 mb-1">
                    <span class="small" style="min-width:90px;"><?= htmlspecialchars($x['username']) ?></span>
                    <?php if ((int)$x['approved'] === 0): ?>
                      <a class="btn btn-sm btn-success"
                         href="?id=<?= $project_id ?>&approve=1&todo=<?= (int)$x['todo_id'] ?>&user=<?= (int)$x['user_id'] ?>">
                        Duyệt
                      </a>
                      <a class="btn btn-sm btn-danger"
                         href="?id=<?= $project_id ?>&reject=1&todo=<?= (int)$x['todo_id'] ?>&user=<?= (int)$x['user_id'] ?>">
                        Từ chối
                      </a>
                    <?php else: ?>
                      <span class="small text-success fw-semibold">Đã duyệt</span>
                    <?php endif; ?>
                  </div>
                <?php endwhile; ?>
              <?php endif; ?>
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
