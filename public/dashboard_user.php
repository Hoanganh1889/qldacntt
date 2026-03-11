<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'user') {
    header("Location: dashboard.php");
    exit;
}

$user = $_SESSION['user'];
$uid  = (int)$user['id'];

$pageTitle = 'Dashboard cá nhân';
$current   = basename($_SERVER['PHP_SELF']);

// helper count
$q = function(string $sql) use ($conn) {
    $r = $conn->query($sql);
    if (!$r) return 0;
    $row = $r->fetch_assoc();
    return (int)($row['c'] ?? 0);
};

/* ====== KPI cá nhân ====== */
$tasksAssigned  = $q("SELECT COUNT(*) c FROM todo_assignments WHERE user_id=$uid");
$tasksSubmitted = $q("SELECT COUNT(*) c FROM todo_submissions WHERE user_id=$uid");
$tasksApproved  = $q("SELECT COUNT(*) c FROM todo_submissions WHERE user_id=$uid AND approved=1");
$tasksPending   = $q("SELECT COUNT(*) c FROM todo_submissions WHERE user_id=$uid AND approved=0");

$projectsJoined = $q("
    SELECT COUNT(DISTINCT t.project_id) c
    FROM todos t
    JOIN todo_assignments ta ON ta.todo_id=t.id
    WHERE ta.user_id=$uid
");

/* ====== Biểu đồ: trạng thái công việc được giao (dựa trên todos.status) ====== */
$st = $conn->query("
    SELECT t.status, COUNT(*) total
    FROM todos t
    JOIN todo_assignments ta ON ta.todo_id=t.id
    WHERE ta.user_id=$uid
    GROUP BY t.status
");
$statusLabels = [];
$statusValues = [];
if ($st) {
    while ($row = $st->fetch_assoc()) {
        $statusLabels[] = $row['status'];
        $statusValues[] = (int)$row['total'];
    }
}

/* ====== Dự án của tôi (hiển thị thư mục) + tiến độ cá nhân ======
   Tiến độ cá nhân: approved submissions / số task được giao trong dự án
*/
$projects = $conn->query("
    SELECT 
        p.id, p.name, p.status,
        COUNT(DISTINCT t.id) AS total_tasks,
        COUNT(DISTINCT CASE WHEN s.approved = 1 THEN s.id END) AS approved_tasks
    FROM projects p
    JOIN todos t ON t.project_id = p.id
    JOIN todo_assignments ta ON ta.todo_id = t.id AND ta.user_id = $uid
    LEFT JOIN todo_submissions s ON s.todo_id = t.id AND s.user_id = $uid
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 6
");

/* ====== Công việc gần đây của tôi (6 task) ====== */
$recentTodos = $conn->query("
    SELECT 
        t.id, t.title, t.status, t.due_date, t.project_id,
        p.name AS project_name,
        s.report_file, s.approved, s.created_at AS submitted_at
    FROM todos t
    JOIN todo_assignments ta ON ta.todo_id=t.id AND ta.user_id=$uid
    JOIN projects p ON p.id=t.project_id
    LEFT JOIN todo_submissions s ON s.todo_id=t.id AND s.user_id=$uid
    ORDER BY t.created_at DESC
    LIMIT 6
");
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard cá nhân</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <link rel="stylesheet" href="assets/css/sidebar.css">

  <style>
    .kpi-card { transition: .2s; }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,.12); }
    .folder-card { transition:.2s; cursor:pointer; }
    .folder-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,.12); }
    .progress { height: 8px; }
  </style>
</head>
<body>

<?php include __DIR__ . '/layouts/header.php'; ?>
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<div class="content-wrapper">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0"><i class="fas fa-user-check me-2"></i>Dashboard của tôi</h4>
    <a href="todo.php" class="btn btn-sm btn-outline-primary">
      <i class="fas fa-clipboard-list me-1"></i> Đi tới Công việc
    </a>
  </div>

  <!-- KPI -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <a class="text-decoration-none" href="todo.php">
        <div class="card p-3 text-center kpi-card">
          <small class="text-muted">Công việc được giao</small>
          <h2 class="mb-0"><?= $tasksAssigned ?></h2>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <div class="card p-3 text-center kpi-card">
        <small class="text-muted">Đã nộp báo cáo</small>
        <h2 class="mb-0"><?= $tasksSubmitted ?></h2>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card p-3 text-center kpi-card">
        <small class="text-muted">Đã được duyệt</small>
        <h2 class="mb-0"><?= $tasksApproved ?></h2>
      </div>
    </div>
    <div class="col-md-3">
      <a class="text-decoration-none" href="project_user.php">
        <div class="card p-3 text-center kpi-card">
          <small class="text-muted">Dự án tham gia</small>
          <h2 class="mb-0"><?= $projectsJoined ?></h2>
        </div>
      </a>
    </div>
  </div>

  <!-- Charts + quick stats -->
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-semibold">Trạng thái công việc</div>
          <div class="small text-muted">Theo các task được giao</div>
        </div>
        <canvas id="statusChart" height="160"></canvas>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card p-3">
        <div class="fw-semibold mb-2">Báo cáo</div>
        <div class="row text-center">
          <div class="col-6">
            <div class="border rounded p-3">
              <div class="small text-muted">Chờ duyệt</div>
              <div class="fs-2 fw-bold"><?= $tasksPending ?></div>
            </div>
          </div>
          <div class="col-6">
            <div class="border rounded p-3">
              <div class="small text-muted">Đã duyệt</div>
              <div class="fs-2 fw-bold"><?= $tasksApproved ?></div>
            </div>
          </div>
        </div>
        <div class="small text-muted mt-2">
          * Nếu bị từ chối, user nộp lại thì hệ thống sẽ reset “chờ duyệt”.
        </div>
      </div>
    </div>
  </div>

  <!-- Projects folders -->
  <div class="d-flex align-items-center justify-content-between mb-2">
    <div class="fw-semibold"><i class="fas fa-folder-open text-warning me-2"></i>Dự án của tôi</div>
    <a class="small" href="project_user.php">Xem tất cả</a>
  </div>

  <div class="row g-3 mb-4">
    <?php if (!$projects || $projects->num_rows === 0): ?>
      <div class="col-12">
        <div class="alert alert-info mb-0">Bạn chưa được phân công vào dự án nào.</div>
      </div>
    <?php else: ?>
      <?php while ($p = $projects->fetch_assoc()):
        $total = (int)$p['total_tasks'];
        $done  = (int)$p['approved_tasks'];
        $percent = $total > 0 ? (int)round($done / $total * 100) : 0;
      ?>
        <div class="col-md-4 col-lg-3">
          <a class="text-decoration-none text-dark" href="todo.php?project_id=<?= (int)$p['id'] ?>">
            <div class="card p-3 folder-card h-100">
              <div class="text-center">
                <i class="fas fa-folder fa-3x text-warning"></i>
              </div>
              <div class="mt-2 text-center fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
              <div class="text-center mb-2">
                <?php if ($p['status'] === 'Hoàn thành'): ?>
                  <span class="badge bg-success">Hoàn thành</span>
                <?php elseif ($p['status'] === 'Đang làm'): ?>
                  <span class="badge bg-primary">Đang làm</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Mới</span>
                <?php endif; ?>
              </div>

              <div class="small text-muted">Tiến độ cá nhân: <?= $percent ?>%</div>
              <div class="progress">
                <div class="progress-bar bg-success" style="width:<?= $percent ?>%"></div>
              </div>
              <div class="small text-muted mt-2 text-center"><?= $done ?>/<?= $total ?> đã duyệt</div>
            </div>
          </a>
        </div>
      <?php endwhile; ?>
    <?php endif; ?>
  </div>

  <!-- Recent tasks -->
  <div class="fw-semibold mb-2"><i class="fas fa-clock me-2"></i>Công việc gần đây</div>
  <div class="card">
    <div class="card-body table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:80px;">#</th>
            <th>Công việc</th>
            <th style="width:180px;">Dự án</th>
            <th style="width:140px;">Trạng thái</th>
            <th style="width:160px;">Báo cáo</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$recentTodos || $recentTodos->num_rows === 0): ?>
            <tr><td colspan="5" class="text-center text-muted">Chưa có công việc</td></tr>
          <?php else: ?>
            <?php while ($t = $recentTodos->fetch_assoc()): ?>
              <tr>
                <td>#<?= (int)$t['id'] ?></td>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($t['title']) ?></div>
                  <?php if (!empty($t['due_date'])): ?>
                    <div class="small text-muted">Hạn: <?= htmlspecialchars($t['due_date']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($t['project_name']) ?></td>
                <td>
                  <?php
                    $status = $t['status'] ?? 'Chưa làm';
                    $badge = ($status==='Hoàn thành')?'success':(($status==='Đang làm')?'warning':'secondary');
                  ?>
                  <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($status) ?></span>
                  <?php if (!empty($t['report_file'])): ?>
                    <?php if ((int)$t['approved'] === 1): ?>
                      <span class="badge bg-primary ms-1">Đã duyệt</span>
                    <?php else: ?>
                      <span class="badge bg-secondary ms-1">Chờ duyệt</span>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td>
                  <a class="btn btn-sm btn-outline-primary"
                     href="todo.php?project_id=<?= (int)$t['project_id'] ?>">
                    <i class="fas fa-arrow-right"></i> Mở
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const labels = <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE) ?>;
const values = <?= json_encode($statusValues, JSON_UNESCAPED_UNICODE) ?>;

const ctx = document.getElementById('statusChart');
if (ctx) {
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{ data: values }]
    }
  });
}
</script>

<!-- ✅ Nếu bạn đã dùng JS notifications chung ở các trang thì có thể bỏ đoạn này.
     Nếu CHƯA, bạn giữ để dashboard_user cũng có chuông realtime. -->
<script>
async function fetchNotifs() {
  try {
    const res = await fetch('api/notifications_fetch.php', { cache: 'no-store' });
    if (!res.ok) return;
    const data = await res.json();

    const badge = document.getElementById('notifBadge');
    const list  = document.getElementById('notifList');
    if (!badge || !list) return;

    if (data.unread > 0) {
      badge.style.display = 'inline-block';
      badge.textContent = data.unread;
    } else {
      badge.style.display = 'none';
    }

    list.innerHTML = '';
    if (!data.items || data.items.length === 0) {
      list.innerHTML = '<li class="px-2 text-muted">Chưa có thông báo</li>';
      return;
    }

    data.items.forEach(item => {
      const li = document.createElement('li');
      li.className = 'px-2 py-2 border-bottom';
      li.style.cursor = item.link ? 'pointer' : 'default';

      li.innerHTML = `
        <div class="fw-semibold">${item.title}</div>
        <div class="small text-muted">${item.content || ''}</div>
        <div class="small text-muted">${item.created_at}</div>
      `;

      if (item.link) li.onclick = () => window.location.href = item.link;

      if (parseInt(item.is_read) === 0) {
        const btn = document.createElement('button');
        btn.className = 'btn btn-sm btn-outline-primary mt-1';
        btn.textContent = 'Đã xem';
        btn.onclick = async (e) => {
          e.stopPropagation();
          const fd = new FormData();
          fd.append('id', item.id);
          await fetch('api/notifications_mark_read.php', { method:'POST', body: fd });
          fetchNotifs();
        };
        li.appendChild(btn);
      }

      list.appendChild(li);
    });
  } catch(e) {}
}
fetchNotifs();
setInterval(fetchNotifs, 4000);
</script>

</body>
</html>
