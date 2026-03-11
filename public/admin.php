<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* CHỈ ADMIN */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$admin = $_SESSION['user'];
$msg = '';

/* ===== THÊM USER ===== */
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role     = $_POST['role'];

    if ($username && $password && in_array($role, ['admin','user'])) {
        $check = $conn->query("SELECT id FROM users WHERE username='$username'");
        if ($check->num_rows === 0) {
            $pass = md5($password);
            $conn->query("
                INSERT INTO users(username,password,role)
                VALUES('$username','$pass','$role')
            ");
            $msg = "✅ Đã thêm người dùng";
        } else {
            $msg = "❌ Tài khoản đã tồn tại";
        }
    }
}

/* ===== ĐỔI ROLE ===== */
if (isset($_GET['change_role'])) {
    $uid  = (int)$_GET['change_role'];
    $role = $_GET['role'];

    if (in_array($role, ['admin','user'])) {
        $conn->query("UPDATE users SET role='$role' WHERE id=$uid");

        // log
        $conn->query("
            INSERT INTO system_logs(content)
            VALUES ('Admin {$admin['username']} đổi role user ID $uid thành $role')
        ");
    }
    header("Location: admin.php");
    exit;
}

/* ===== XÓA USER ===== */
if (isset($_GET['delete'])) {
    $uid = (int)$_GET['delete'];

    if ($uid !== (int)$admin['id']) { // không cho tự xóa mình
        $conn->query("DELETE FROM users WHERE id=$uid");

        $conn->query("
            INSERT INTO system_logs(content)
            VALUES ('Admin {$admin['username']} xóa user ID $uid')
        ");
    }
    header("Location: admin.php");
    exit;
}

/* ===== LẤY DANH SÁCH USER ===== */
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Quản lý người dùng</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css">
</head>
<body>

<!-- HEADER -->
<div class="header d-flex align-items-center justify-content-between px-4 shadow-sm"
     style="height:70px;position:fixed;top:0;left:0;width:100%;background:#fff;z-index:1030;">
    <h4 class="mb-0">ADMIN PANEL</h4>
    <a href="logout.php" class="btn btn-sm btn-outline-danger">Đăng xuất</a>
</div>

<!-- SIDEBAR -->
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<!-- CONTENT -->
<div class="content-wrapper">

    <h3 class="mb-4">👑 QUẢN LÝ NGƯỜI DÙNG</h3>

    <?php if ($msg): ?>
        <div class="alert alert-info"><?= $msg ?></div>
    <?php endif; ?>

    <!-- THÊM USER -->
    <div class="card mb-4">
        <div class="card-header fw-bold">Thêm người dùng</div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-4">
                    <input name="username" class="form-control" placeholder="Username" required>
                </div>
                <div class="col-md-4">
                    <input name="password" type="password" class="form-control" placeholder="Password" required>
                </div>
                <div class="col-md-2">
                    <select name="role" class="form-select">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button name="add_user" class="btn btn-primary w-100">
                        <i class="fas fa-plus"></i> Thêm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- DANH SÁCH USER -->
    <div class="card">
        <div class="card-header fw-bold">Danh sách người dùng</div>
        <div class="card-body table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Ngày tạo</th>
                        <th width="220">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($u = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td>
                            <span class="badge bg-<?= $u['role']=='admin'?'danger':'secondary' ?>">
                                <?= $u['role'] ?>
                            </span>
                        </td>
                        <td><?= $u['created_at'] ?></td>
                        <td>
                            <?php if ($u['id'] != $admin['id']): ?>
                                <?php if ($u['role'] === 'user'): ?>
                                    <a href="?change_role=<?= $u['id'] ?>&role=admin"
                                       class="btn btn-sm btn-warning">
                                        Lên Admin
                                    </a>
                                <?php else: ?>
                                    <a href="?change_role=<?= $u['id'] ?>&role=user"
                                       class="btn btn-sm btn-secondary">
                                        Xuống User
                                    </a>
                                <?php endif; ?>

                                <a href="?delete=<?= $u['id'] ?>"
                                   onclick="return confirm('Xóa người dùng này?')"
                                   class="btn btn-sm btn-danger">
                                    Xóa
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Bạn</span>
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
