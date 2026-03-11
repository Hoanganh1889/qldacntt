<?php
session_start();
require_once __DIR__ . '/../config/db.php';
include __DIR__ . '/layouts/header.php';
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$user = $_SESSION['user'];
$uid  = (int)$user['id'];

$projects = $conn->query("
  SELECT DISTINCT p.id, p.name, p.status, p.created_at
  FROM projects p
  JOIN todos t ON t.project_id = p.id
  JOIN todo_assignments ta ON ta.todo_id = t.id
  WHERE ta.user_id = $uid
  ORDER BY p.created_at DESC
");
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Công việc</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/sidebar.css">
</head>
<body>
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<div class="content-wrapper">
  <h3 class="mb-3">📁 Công Việc Theo Dự Án</h3>

  <div class="row g-3">
    <?php if ($projects->num_rows === 0): ?>
      <p class="text-muted">Chưa có công việc được giao</p>
    <?php endif; ?>

    <?php while ($p = $projects->fetch_assoc()): ?>
      <div class="col-md-3">
        <a href="todo_project.php?id=<?= (int)$p['id'] ?>" class="text-decoration-none">
          <div class="card text-center p-4 shadow-sm h-100">
            <i class="fas fa-folder fa-3x text-warning"></i>
            <h6 class="mt-2"><?= htmlspecialchars($p['name']) ?></h6>
            <small class="text-muted"><?= htmlspecialchars($p['status']) ?></small>
          </div>
        </a>
      </div>
    <?php endwhile; ?>
  </div>
</div>
</body>
</html>
