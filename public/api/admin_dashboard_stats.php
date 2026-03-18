<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$q = function(string $sql) use ($conn) {
    $r = $conn->query($sql);
    if (!$r) return 0;
    return (int)($r->fetch_assoc()['c'] ?? 0);
};

$stats = [];
$stats['totalUsers']    = $q("SELECT COUNT(*) c FROM users WHERE role='user'");
$stats['totalProjects'] = $q("SELECT COUNT(*) c FROM projects");
$stats['totalTodos']    = $q("SELECT COUNT(*) c FROM todos");

$stats['approvedReports'] = $q("SELECT COUNT(*) c FROM todo_submissions WHERE approved=1");
$stats['pendingReports']  = $q("SELECT COUNT(*) c FROM todo_submissions WHERE approved=0");


$stats['overdueTodos'] = $q("
  SELECT COUNT(*) c
  FROM todos
  WHERE due_date IS NOT NULL
    AND due_date < CURDATE()
    AND status <> 'Hoàn thành'
");


$stats['dueSoonTodos'] = $q("
  SELECT COUNT(*) c
  FROM todos
  WHERE due_date IS NOT NULL
    AND due_date >= CURDATE()
    AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    AND status <> 'Hoàn thành'
");


$labels = [];
$values = [];
$chartQ = $conn->query("SELECT status, COUNT(*) total FROM todos GROUP BY status");
if ($chartQ) {
    while ($row = $chartQ->fetch_assoc()) {
        $labels[] = $row['status'];
        $values[] = (int)$row['total'];
    }
}
$stats['todoStatus'] = ['labels' => $labels, 'values' => $values];


$days = [];
$appr = [];
$pend = [];

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
    $days[] = $d;
    $appr[] = $map[$d]['approved'] ?? 0;
    $pend[] = $map[$d]['pending'] ?? 0;
}

$stats['reportTrend7'] = [
    'labels' => $days,
    'approved' => $appr,
    'pending' => $pend
];

echo json_encode(['ok' => true, 'stats' => $stats], JSON_UNESCAPED_UNICODE);
