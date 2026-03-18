<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header("Location: dashboard_user.php");
    exit;
}

$user = $_SESSION['user'];
$pageTitle = 'Dashboard hệ thống';
$current   = basename($_SERVER['PHP_SELF']);

$q = function(string $sql) use ($conn) {
    $r = $conn->query($sql);
    if (!$r) return 0;
    return (int)($r->fetch_assoc()['c'] ?? 0);
};

$totalUsers    = $q("SELECT COUNT(*) c FROM users WHERE role='user'");
$totalProjects = $q("SELECT COUNT(*) c FROM projects");
$totalTodos    = $q("SELECT COUNT(*) c FROM todos");

$approvedReports = $q("SELECT COUNT(*) c FROM todo_submissions WHERE approved=1");
$pendingReports  = $q("SELECT COUNT(*) c FROM todo_submissions WHERE approved=0");

$overdueTodos = $q("
  SELECT COUNT(*) c
  FROM todos
  WHERE due_date IS NOT NULL
    AND due_date < CURDATE()
    AND status <> 'Hoàn thành'
");

$dueSoonTodos = $q("
  SELECT COUNT(*) c
  FROM todos
  WHERE due_date IS NOT NULL
    AND due_date >= CURDATE()
    AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    AND status <> 'Hoàn thành'
");

/* Chart trạng thái */
$chartLabels = [];
$chartValues = [];
$chartQ = $conn->query("SELECT status, COUNT(*) total FROM todos GROUP BY status");
while ($chartQ && $row = $chartQ->fetch_assoc()) {
    $chartLabels[] = $row['status'];
    $chartValues[] = (int)$row['total'];
}

/* Trend 7 ngày */
$trendLabels = [];
$trendApproved = [];
$trendPending  = [];

$trend = $conn->query("
  SELECT DATE(created_at) d,
         SUM(CASE WHEN approved=1 THEN 1 ELSE 0 END) approved,
         SUM(CASE WHEN approved=0 THEN 1 ELSE 0 END) pending
  FROM todo_submissions
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  GROUP BY DATE(created_at)
  ORDER BY d ASC
");

$map = [];
if ($trend) {
    while ($r = $trend->fetch_assoc()) {
        $map[$r['d']] = ['approved' => (int)$r['approved'], 'pending' => (int)$r['pending']];
    }
}
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $trendLabels[]   = $d;
    $trendApproved[] = $map[$d]['approved'] ?? 0;
    $trendPending[]  = $map[$d]['pending'] ?? 0;
}

/* Top user performance */
$userPerf = $conn->query("
    SELECT 
        u.username,
        COUNT(DISTINCT t.id) AS total_tasks,
        COUNT(DISTINCT s.id) AS submitted,
        COUNT(DISTINCT CASE WHEN s.approved=1 THEN s.id END) AS approved
    FROM users u
    LEFT JOIN todos t ON t.assigned_to = u.id
    LEFT JOIN todo_submissions s ON s.todo_id = t.id AND s.user_id = u.id
    WHERE u.role='user'
    GROUP BY u.id
    ORDER BY approved DESC
    LIMIT 7
");

/* Recent projects */
$recentProjects = $conn->query("
    SELECT id, name, status, created_at
    FROM projects
    ORDER BY created_at DESC
    LIMIT 8
");
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Admin</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<link rel="stylesheet" href="assets/css/sidebar.css">

<style>
:root{
  --bg:#f4f6f9;
  --card:#ffffff;
  --muted:#64748b;
}
body{ background:var(--bg); }

.card{
  border:none;
  border-radius:16px;
  background:var(--card);
  box-shadow:0 8px 26px rgba(0,0,0,.08);
}
.kpi{
  transition:.25s;
  text-decoration:none;
  color:inherit;
}
.kpi:hover{
  transform:translateY(-6px);
  box-shadow:0 18px 42px rgba(0,0,0,.16);
}
.kpi small{ color:var(--muted); }
.kpi h2{ font-weight:800; margin:0; }
.badge-soft{
  background:#eef2ff;
  color:#3730a3;
  border:1px solid #e0e7ff;
}
</style>
</head>
<body>

<?php include __DIR__ . '/layouts/header.php'; ?>
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<div class="content-wrapper">

      <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="fw-bold mb-0">
          <i class="fas fa-gauge-high text-primary me-2"></i> Dashboard hệ thống
        </h4>
        <div class="small text-muted">
          Xin chào <b><?= htmlspecialchars($user['username']) ?></b> (ADMIN) •
          <span class="badge badge-soft" id="liveBadge">LIVE</span>
        </div>
      </div>

      <!-- KPI (click drill-down) -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <a class="card p-3 text-center kpi d-block" href="admin.php" title="Quản lý user">
            <small>Người dùng</small>
            <h2 id="kpiUsers"><?= $totalUsers ?></h2>
            <div class="small text-muted mt-1">Click để xem danh sách</div>
          </a>
        </div>

        <div class="col-md-3">
          <a class="card p-3 text-center kpi d-block" href="projects.php" title="Dự án">
            <small>Dự án</small>
            <h2 id="kpiProjects"><?= $totalProjects ?></h2>
            <div class="small text-muted mt-1">Click để mở dự án</div>
          </a>
        </div>

        <div class="col-md-3">
          <a class="card p-3 text-center kpi d-block" href="admin_todos.php" title="Toàn bộ công việc">
            <small>Công việc</small>
            <h2 id="kpiTodos"><?= $totalTodos ?></h2>
            <div class="small text-muted mt-1">Click để giám sát</div>
          </a>
        </div>

        <div class="col-md-3">
          <a class="card p-3 text-center kpi d-block" href="admin_todos.php?tab=approved" title="Báo cáo đã duyệt">
            <small>Báo cáo đã duyệt</small>
            <h2 id="kpiApproved"><?= $approvedReports ?></h2>
            <div class="small text-muted mt-1">Chất lượng thực thi</div>
          </a>
        </div>
      </div>

<!-- Alert cards -->
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <div class="card p-3">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="fw-semibold"><i class="fas fa-triangle-exclamation text-danger me-2"></i>Công việc trễ hạn</div>
                <div class="small text-muted">due_date < hôm nay & chưa hoàn thành</div>
              </div>
              <div class="text-end">
                <div class="fs-2 fw-bold text-danger" id="kpiOverdue"><?= $overdueTodos ?></div>
                <a class="btn btn-sm btn-outline-danger" href="admin_todos.php?filter=overdue">Xem</a>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card p-3">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="fw-semibold"><i class="fas fa-clock text-warning me-2"></i>Sắp đến hạn (3 ngày)</div>
                <div class="small text-muted">ưu tiên nhắc user nộp báo cáo</div>
              </div>
              <div class="text-end">
                <div class="fs-2 fw-bold text-warning" id="kpiDueSoon"><?= $dueSoonTodos ?></div>
                <a class="btn btn-sm btn-outline-warning" href="admin_todos.php?filter=dueSoon">Xem</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts -->
      <div class="row g-4 mb-4">
        <div class="col-md-6">
          <div class="card p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="fw-semibold">📊 Trạng thái công việc</div>
              <div class="small text-muted">toàn hệ thống</div>
            </div>
            <canvas id="todoChart" height="210"></canvas>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="fw-semibold">📈 Xu hướng báo cáo 7 ngày</div>
              <div class="small text-muted">approved vs pending</div>
            </div>
            <canvas id="trendChart" height="210"></canvas>
            <div class="mt-2">
              <a class="btn btn-sm btn-outline-primary" href="admin_todos.php">Mở danh sách báo cáo</a>
            </div>
          </div>
        </div>
      </div>

      <!-- User performance -->
      <div class="card p-3 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="fw-semibold">👤 Hiệu suất user (Top)</div>
          <a class="btn btn-sm btn-outline-secondary" href="admin.php">Quản lý user</a>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>User</th>
                <th>Task</th>
                <th>Nộp</th>
                <th>Đã duyệt</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$userPerf || $userPerf->num_rows === 0): ?>
              <tr><td colspan="4" class="text-center text-muted">Chưa có dữ liệu</td></tr>
            <?php else: ?>
              <?php while($u=$userPerf->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= (int)$u['total_tasks'] ?></td>
                <td><?= (int)$u['submitted'] ?></td>
                <td><span class="badge bg-success"><?= (int)$u['approved'] ?></span></td>
              </tr>
              <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent projects -->
      <div class="card p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="fw-semibold">🤖 Dự án gần đây</div>
          <a class="btn btn-sm btn-outline-secondary" href="projects.php">Xem tất cả</a>
        </div>

        <ul class="list-group list-group-flush">
        <?php if (!$recentProjects || $recentProjects->num_rows === 0): ?>
          <li class="list-group-item text-muted">Chưa có dự án</li>
        <?php else: ?>
          <?php while($p=$recentProjects->fetch_assoc()): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
                <div class="small text-muted"><?= htmlspecialchars($p['created_at']) ?> • <?= htmlspecialchars($p['status']) ?></div>
              </div>
              <a href="projects.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-folder-open me-1"></i> Mở
              </a>
            </li>
          <?php endwhile; ?>
        <?php endif; ?>
        </ul>
      </div>

      </div>

<script>
/* ===== Chart 1: todo status ===== */
      const statusCtx = document.getElementById('todoChart');
      const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
          labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
          datasets: [{
            data: <?= json_encode($chartValues) ?>,
            backgroundColor: ['#94a3b8','#f59e0b','#22c55e'],
            borderWidth: 0
          }]
        },
        options: {
          cutout: '70%',
          plugins: { legend: { position: 'bottom' } }
        }
      });

      /* ===== Chart 2: trend 7 days ===== */
      const trendCtx = document.getElementById('trendChart');
      const trendChart = new Chart(trendCtx, {
        type: 'bar',
        data: {
          labels: <?= json_encode($trendLabels, JSON_UNESCAPED_UNICODE) ?>,
          datasets: [
            { label: 'Đã duyệt', data: <?= json_encode($trendApproved) ?> },
            { label: 'Chờ duyệt', data: <?= json_encode($trendPending) ?> }
          ]
        },
        options: {
          responsive: true,
          plugins: { legend: { position: 'bottom' } },
          scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
        }
      });

      /* ===== Realtime polling ===== */
      async function refreshDashboard(){
        try{
          const res = await fetch('api/admin_dashboard_stats.php', { cache: 'no-store' });
          if(!res.ok) return;
          const json = await res.json();
          if(!json.ok) return;

          const s = json.stats;

          // KPI
          document.getElementById('kpiUsers').textContent    = s.totalUsers;
          document.getElementById('kpiProjects').textContent = s.totalProjects;
          document.getElementById('kpiTodos').textContent    = s.totalTodos;
          document.getElementById('kpiApproved').textContent = s.approvedReports;

          // alerts
          document.getElementById('kpiOverdue').textContent  = s.overdueTodos;
          document.getElementById('kpiDueSoon').textContent  = s.dueSoonTodos;

          // status chart
          statusChart.data.labels = s.todoStatus.labels;
          statusChart.data.datasets[0].data = s.todoStatus.values;
          statusChart.update();

          // trend chart
          trendChart.data.labels = s.reportTrend7.labels;
          trendChart.data.datasets[0].data = s.reportTrend7.approved;
          trendChart.data.datasets[1].data = s.reportTrend7.pending;
          trendChart.update();

          // live badge pulse
          const lb = document.getElementById('liveBadge');
          lb.classList.add('bg-success','text-white');
          setTimeout(()=>lb.classList.remove('bg-success','text-white'), 250);

        }catch(e){}
      }
      refreshDashboard();
      setInterval(refreshDashboard, 5000);
</script>

</body>
</html>
