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

function hasColumn(mysqli $conn, string $table, string $column): bool {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $r && $r->num_rows > 0;
}

function hasTable(mysqli $conn, string $table): bool {
    $table = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '$table'");
    return $r && $r->num_rows > 0;
}

/* ===== BASIC KPI ===== */
$totalUsers    = $q("SELECT COUNT(*) c FROM users WHERE role='user'");
$totalProjects = $q("SELECT COUNT(*) c FROM projects");
$totalTodos    = $q("SELECT COUNT(*) c FROM todos");

$approvedReports = $q("SELECT COUNT(*) c FROM todo_submissions WHERE approved=1");
$pendingReports  = $q("SELECT COUNT(*) c FROM todo_submissions WHERE approved=0");

$doneTodos = $q("SELECT COUNT(*) c FROM todos WHERE status='done'");
$inProgressTodos = $q("SELECT COUNT(*) c FROM todos WHERE status='in_progress'");
$testTodos = $q("SELECT COUNT(*) c FROM todos WHERE status='test'");
$todoTodos = $q("SELECT COUNT(*) c FROM todos WHERE status='todo'");

$overallCompletion = $totalTodos > 0 ? round(($doneTodos / $totalTodos) * 100, 1) : 0;

$overdueTodos = $q("
  SELECT COUNT(*) c
  FROM todos
  WHERE due_date IS NOT NULL
    AND due_date < CURDATE()
    AND status <> 'done'
");

$dueSoonTodos = $q("
  SELECT COUNT(*) c
  FROM todos
  WHERE due_date IS NOT NULL
    AND due_date >= CURDATE()
    AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    AND status <> 'done'
");

/* ===== ONLINE / OFFLINE ===== */
$hasLastActive = hasColumn($conn, 'users', 'last_active');

$onlineUsers = 0;
$offlineUsers = 0;

if ($hasLastActive) {
    $onlineUsers = $q("
        SELECT COUNT(*) c
        FROM users
        WHERE role='user'
          AND last_active >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $offlineUsers = max(0, $totalUsers - $onlineUsers);
}

/* ===== CHAT STATS ===== */
$hasChatMessages = hasTable($conn, 'chat_messages');

$totalMessages = 0;
$todayMessages = 0;
$topChatUsers = false;

if ($hasChatMessages) {
    $totalMessages = $q("SELECT COUNT(*) c FROM chat_messages");
    $todayMessages = $q("SELECT COUNT(*) c FROM chat_messages WHERE DATE(created_at)=CURDATE()");

    $topChatUsers = $conn->query("
        SELECT 
            u.username,
            COUNT(cm.id) AS total_messages,
            MAX(cm.created_at) AS last_message_at
        FROM users u
        LEFT JOIN chat_messages cm ON cm.user_id = u.id
        WHERE u.role='user'
        GROUP BY u.id, u.username
        ORDER BY total_messages DESC, u.username ASC
        LIMIT 7
    ");
}

/* ===== CHART: TODO STATUS ===== */
$chartLabels = [];
$chartValues = [];
$chartQ = $conn->query("
    SELECT 
        CASE 
            WHEN status='todo' THEN 'Chưa làm'
            WHEN status='in_progress' THEN 'Đang làm'
            WHEN status='test' THEN 'Chờ kiểm tra'
            WHEN status='done' THEN 'Hoàn thành'
            ELSE status
        END AS status_name,
        COUNT(*) total
    FROM todos
    GROUP BY status
");
while ($chartQ && $row = $chartQ->fetch_assoc()) {
    $chartLabels[] = $row['status_name'];
    $chartValues[] = (int)$row['total'];
}

/* ===== TREND 7 NGÀY ===== */
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
        $map[$r['d']] = [
            'approved' => (int)$r['approved'],
            'pending'  => (int)$r['pending']
        ];
    }
}
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $trendLabels[]   = $d;
    $trendApproved[] = $map[$d]['approved'] ?? 0;
    $trendPending[]  = $map[$d]['pending'] ?? 0;
}

/* ===== USER PERFORMANCE ===== */
$userPerf = $conn->query("
    SELECT 
        u.id,
        u.username,
        COUNT(DISTINCT ta.todo_id) AS total_tasks,
        COUNT(DISTINCT CASE WHEN t.status='done' THEN ta.todo_id END) AS done_tasks,
        COUNT(DISTINCT CASE WHEN t.status='in_progress' THEN ta.todo_id END) AS doing_tasks,
        COUNT(DISTINCT CASE WHEN t.status='test' THEN ta.todo_id END) AS test_tasks,
        COUNT(DISTINCT ts.id) AS submitted,
        COUNT(DISTINCT CASE WHEN ts.approved=1 THEN ts.id END) AS approved
    FROM users u
    LEFT JOIN todo_assignments ta ON ta.user_id = u.id
    LEFT JOIN todos t ON t.id = ta.todo_id
    LEFT JOIN todo_submissions ts ON ts.todo_id = ta.todo_id AND ts.user_id = u.id
    WHERE u.role='user'
    GROUP BY u.id, u.username
    ORDER BY done_tasks DESC, approved DESC, total_tasks DESC
    LIMIT 10
");

/* ===== DONUT: USER ACTIVITY ===== */
$userActivityLabels = ['Online', 'Offline'];
$userActivityValues = [$onlineUsers, $offlineUsers];

/* ===== DONUT: REPORT QUALITY ===== */
$reportQualityLabels = ['Đã duyệt', 'Chờ duyệt'];
$reportQualityValues = [$approvedReports, $pendingReports];

/* ===== RECENT PROJECTS ===== */
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
.metric-circle {
  width: 130px;
  height: 130px;
  margin: 0 auto;
  position: relative;
}
.metric-circle canvas {
  width: 130px !important;
  height: 130px !important;
}
.metric-center {
  position: absolute;
  inset: 0;
  display:flex;
  align-items:center;
  justify-content:center;
  flex-direction:column;
  font-weight:700;
}
.metric-center .big {
  font-size: 1rem;
  line-height: 1;
}
.metric-center .small {
  font-size: .8rem;
  color: var(--muted);
}
.table-sm td, .table-sm th {
  vertical-align: middle;
}
.progress {
  height: 10px;
  border-radius: 999px;
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

    <!-- KPI -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <a class="card p-3 text-center kpi d-block" href="admin.php">
                <small>Người dùng</small>
                <h2 id="kpiUsers"><?= $totalUsers ?></h2>
                <div class="small text-muted mt-1">Tổng user trong hệ thống</div>
            </a>
        </div>

        <div class="col-md-3">
            <a class="card p-3 text-center kpi d-block" href="projects.php">
                <small>Dự án</small>
                <h2 id="kpiProjects"><?= $totalProjects ?></h2>
                <div class="small text-muted mt-1">Tổng dự án hiện có</div>
            </a>
        </div>

        <div class="col-md-3">
            <a class="card p-3 text-center kpi d-block" href="admin_todos.php">
                <small>Công việc</small>
                <h2 id="kpiTodos"><?= $totalTodos ?></h2>
                <div class="small text-muted mt-1">Toàn bộ task</div>
            </a>
        </div>

        <div class="col-md-3">
            <a class="card p-3 text-center kpi d-block" href="admin_todos.php?tab=approved">
                <small>Báo cáo đã duyệt</small>
                <h2 id="kpiApproved"><?= $approvedReports ?></h2>
                <div class="small text-muted mt-1">Chất lượng thực thi</div>
            </a>
        </div>
    </div>

    <!-- Extra KPI -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card p-3 text-center">
                <small>Tỷ lệ hoàn thành</small>
                <h2><?= $overallCompletion ?>%</h2>
                <div class="small text-muted mt-1">Done / Tổng task</div>
            </div>
        </div>

        <div class="col-md-3">
            <a class="card p-3 text-center kpi d-block" href="board.php">
                <small>Đang làm</small>
                <h2><?= $inProgressTodos ?></h2>
                <div class="small text-muted mt-1">Task in progress</div>
            </a>
        </div>

        <div class="col-md-3">
            <a class="card p-3 text-center kpi d-block" href="board.php">
                <small>Chờ kiểm tra</small>
                <h2><?= $testTodos ?></h2>
                <div class="small text-muted mt-1">Task test</div>
            </a>
        </div>

        <div class="col-md-3">
            <div class="card p-3 text-center">
                <small>Tin nhắn hôm nay</small>
                <h2><?= $todayMessages ?></h2>
                <div class="small text-muted mt-1">Nếu có module chat</div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-semibold"><i class="fas fa-triangle-exclamation text-danger me-2"></i>Công việc trễ hạn</div>
                        <div class="small text-muted">DATE < hôm nay & chưa hoàn thành</div>
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
                        <div class="small text-muted">Ưu tiên nhắc user nộp báo cáo</div>
                    </div>
                    <div class="text-end">
                        <div class="fs-2 fw-bold text-warning" id="kpiDueSoon"><?= $dueSoonTodos ?></div>
                        <a class="btn btn-sm btn-outline-warning" href="admin_todos.php?filter=dueSoon">Xem</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="fw-semibold">📊 Trạng thái công việc</div>
                    <div class="small text-muted">Toàn hệ thống</div>
                </div>
                <canvas id="todoChart" height="220"></canvas>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="fw-semibold">📈 Xu hướng báo cáo 7 ngày</div>
                    <div class="small text-muted">Approved vs Pending</div>
                </div>
                <canvas id="trendChart" height="220"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card p-3 text-center">
                <div class="fw-semibold mb-3">🟢 Online / Offline</div>
                <?php if ($hasLastActive): ?>
                    <div class="metric-circle">
                        <canvas id="userActivityChart"></canvas>
                        <div class="metric-center">
                            <div class="big"><?= $onlineUsers ?></div>
                        </div>
                    </div>
                    <div class="small text-muted mt-3">
                        Offline: <b><?= $offlineUsers ?></b>
                    </div>
                <?php else: ?>
                    <div class="text-muted py-5">
                        Chưa có cột <b>users.last_active</b><br>nên chưa tính được online/offline
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3 text-center">
                <div class="fw-semibold mb-3">📝 Chất lượng báo cáo</div>
                <div class="metric-circle">
                    <canvas id="reportQualityChart"></canvas>
                    <div class="metric-center">
                        <div class="big"><?= $approvedReports ?></div>
                    </div>
                </div>
                <div class="small text-muted mt-3">
                    Pending: <b><?= $pendingReports ?></b>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3 text-center">
                <div class="fw-semibold mb-3">💬 Tin nhắn hệ thống</div>
                <?php if ($hasChatMessages): ?>
                    <div class="metric-circle">
                        <canvas id="messageChart"></canvas>
                        <div class="metric-center">
                            <div class="big"><?= $todayMessages ?></div>
                        </div>
                    </div>
                    <div class="small text-muted mt-3">
                        Tổng tin nhắn: <b><?= $totalMessages ?></b>
                    </div>
                <?php else: ?>
                    <div class="text-muted py-5">
                        Chưa có bảng <b>chat_messages</b>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- User performance -->
    <div class="card p-3 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-semibold">👤 Hiệu suất user (Top 10)</div>
            <a class="btn btn-sm btn-outline-secondary" href="admin.php">Quản lý user</a>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Task</th>
                        <th>Done</th>
                        <th>Doing</th>
                        <th>Test</th>
                        <th>Nộp</th>
                        <th>Đã duyệt</th>
                        <th>Tỷ lệ hoàn thành</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$userPerf || $userPerf->num_rows === 0): ?>
                    <tr><td colspan="8" class="text-center text-muted">Chưa có dữ liệu</td></tr>
                <?php else: ?>
                    <?php while($u = $userPerf->fetch_assoc()): ?>
                        <?php
                            $rate = ((int)$u['total_tasks'] > 0)
                                ? round(((int)$u['done_tasks'] / (int)$u['total_tasks']) * 100, 1)
                                : 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= (int)$u['total_tasks'] ?></td>
                            <td><span class="badge bg-success"><?= (int)$u['done_tasks'] ?></span></td>
                            <td><span class="badge bg-primary"><?= (int)$u['doing_tasks'] ?></span></td>
                            <td><span class="badge bg-warning text-dark"><?= (int)$u['test_tasks'] ?></span></td>
                            <td><?= (int)$u['submitted'] ?></td>
                            <td><?= (int)$u['approved'] ?></td>
                            <td style="min-width:170px;">
                                <div class="fw-semibold mb-1"><?= $rate ?>%</div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" style="width: <?= $rate ?>%"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Chat user ranking -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="fw-semibold">💬 Top user nhắn tin</div>
                    <div class="small text-muted">Theo tổng số tin nhắn</div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Tin nhắn</th>
                                <th>Lần cuối</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$hasChatMessages || !$topChatUsers || $topChatUsers->num_rows === 0): ?>
                            <tr><td colspan="3" class="text-center text-muted">Chưa có dữ liệu chat</td></tr>
                        <?php else: ?>
                            <?php while($c = $topChatUsers->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['username']) ?></td>
                                    <td><span class="badge bg-info"><?= (int)$c['total_messages'] ?></span></td>
                                    <td><?= htmlspecialchars($c['last_message_at'] ?? 'Chưa có') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="fw-semibold">🤖 Dự án gần đây</div>
                    <a class="btn btn-sm btn-outline-secondary" href="projects.php">Xem tất cả</a>
                </div>

                <ul class="list-group list-group-flush">
                <?php if (!$recentProjects || $recentProjects->num_rows === 0): ?>
                    <li class="list-group-item text-muted">Chưa có dự án</li>
                <?php else: ?>
                    <?php while($p = $recentProjects->fetch_assoc()): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="small text-muted">
                                    <?= htmlspecialchars($p['created_at']) ?> • <?= htmlspecialchars($p['status']) ?>
                                </div>
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
    </div>

</div>

<script>
/* ===== Chart 1: Todo status ===== */
const statusCtx = document.getElementById('todoChart');
const statusChart = new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            data: <?= json_encode($chartValues) ?>,
            backgroundColor: ['#94a3b8','#3b82f6','#f59e0b','#22c55e'],
            borderWidth: 0
        }]
    },
    options: {
        cutout: '68%',
        plugins: { legend: { position: 'bottom' } }
    }
});

/* ===== Chart 2: Trend ===== */
const trendCtx = document.getElementById('trendChart');
const trendChart = new Chart(trendCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($trendLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [
            { label: 'Đã duyệt', data: <?= json_encode($trendApproved) ?>, backgroundColor: '#22c55e' },
            { label: 'Chờ duyệt', data: <?= json_encode($trendPending) ?>, backgroundColor: '#f59e0b' }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
    }
});

/* ===== Online / Offline ===== */
<?php if ($hasLastActive): ?>
new Chart(document.getElementById('userActivityChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($userActivityLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            data: <?= json_encode($userActivityValues) ?>,
            backgroundColor: ['#22c55e', '#cbd5e1'],
            borderWidth: 0
        }]
    },
    options: {
        cutout: '75%',
        plugins: { legend: { position: 'bottom' } }
    }
});
<?php endif; ?>

/* ===== Report quality ===== */
new Chart(document.getElementById('reportQualityChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($reportQualityLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            data: <?= json_encode($reportQualityValues) ?>,
            backgroundColor: ['#22c55e', '#f59e0b'],
            borderWidth: 0
        }]
    },
    options: {
        cutout: '75%',
        plugins: { legend: { position: 'bottom' } }
    }
});

/* ===== Message chart ===== */
<?php if ($hasChatMessages): ?>
new Chart(document.getElementById('messageChart'), {
    type: 'doughnut',
    data: {
        labels: ['Hôm nay', 'Trước đó'],
        datasets: [{
            data: [<?= (int)$todayMessages ?>, <?= max(0, (int)$totalMessages - (int)$todayMessages) ?>],
            backgroundColor: ['#3b82f6', '#cbd5e1'],
            borderWidth: 0
        }]
    },
    options: {
        cutout: '75%',
        plugins: { legend: { position: 'bottom' } }
    }
});
<?php endif; ?>

/* ===== Realtime polling ===== */
async function refreshDashboard(){
    try{
        const res = await fetch('api/admin_dashboard_stats.php', { cache: 'no-store' });
        if(!res.ok) return;
        const json = await res.json();
        if(!json.ok) return;

        const s = json.stats;

        document.getElementById('kpiUsers').textContent    = s.totalUsers;
        document.getElementById('kpiProjects').textContent = s.totalProjects;
        document.getElementById('kpiTodos').textContent    = s.totalTodos;
        document.getElementById('kpiApproved').textContent = s.approvedReports;
        document.getElementById('kpiOverdue').textContent  = s.overdueTodos;
        document.getElementById('kpiDueSoon').textContent  = s.dueSoonTodos;

        statusChart.data.labels = s.todoStatus.labels;
        statusChart.data.datasets[0].data = s.todoStatus.values;
        statusChart.update();

        trendChart.data.labels = s.reportTrend7.labels;
        trendChart.data.datasets[0].data = s.reportTrend7.approved;
        trendChart.data.datasets[1].data = s.reportTrend7.pending;
        trendChart.update();

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