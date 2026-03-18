<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* CHỈ ADMIN */
if (!isset($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') !== 'admin')) {
    header("Location: dashboard.php");
    exit;
}

$admin = $_SESSION['user'];
$msg = '';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ===== THÊM USER ===== */
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = $_POST['role'] ?? 'user';

    if ($username && $password && in_array($role, ['admin', 'user'], true)) {
        $stmtCheck = $conn->prepare("SELECT id FROM users WHERE username = ?");
        if ($stmtCheck) {
            $stmtCheck->bind_param("s", $username);
            $stmtCheck->execute();
            $check = $stmtCheck->get_result();

            if ($check->num_rows === 0) {
                $pass = password_hash($password, PASSWORD_DEFAULT);

                $stmtInsert = $conn->prepare("
                    INSERT INTO users(username, password, role)
                    VALUES(?, ?, ?)
                ");
                if ($stmtInsert) {
                    $stmtInsert->bind_param("sss", $username, $pass, $role);
                    $stmtInsert->execute();
                    $stmtInsert->close();
                    $msg = "✅ Đã thêm người dùng";
                } else {
                    $msg = "❌ Không thể thêm người dùng";
                }
            } else {
                $msg = "❌ Tài khoản đã tồn tại";
            }

            $stmtCheck->close();
        }
    } else {
        $msg = "❌ Vui lòng nhập đầy đủ thông tin hợp lệ";
    }
}

/* ===== ĐỔI ROLE ===== */
if (isset($_GET['change_role'])) {
    $uid  = (int)($_GET['change_role'] ?? 0);
    $role = $_GET['role'] ?? '';

    if ($uid > 0 && in_array($role, ['admin', 'user'], true)) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $role, $uid);
            $stmt->execute();
            $stmt->close();
        }

        $logContent = "Admin {$admin['username']} đổi role user ID $uid thành $role";
        $stmtLog = $conn->prepare("INSERT INTO system_logs(content) VALUES (?)");
        if ($stmtLog) {
            $stmtLog->bind_param("s", $logContent);
            $stmtLog->execute();
            $stmtLog->close();
        }
    }

    header("Location: admin.php");
    exit;
}

/* ===== XÓA USER ===== */
if (isset($_GET['delete'])) {
    $uid = (int)($_GET['delete'] ?? 0);

    if ($uid > 0 && $uid !== (int)$admin['id']) {
        $conn->begin_transaction();

        try {
            /* Xóa liên kết phân công công việc */
            $stmt1 = $conn->prepare("DELETE FROM todo_assignments WHERE user_id = ?");
            if ($stmt1) {
                $stmt1->bind_param("i", $uid);
                $stmt1->execute();
                $stmt1->close();
            }

            /* Xóa báo cáo user đã nộp */
            $stmt2 = $conn->prepare("DELETE FROM todo_submissions WHERE user_id = ?");
            if ($stmt2) {
                $stmt2->bind_param("i", $uid);
                $stmt2->execute();
                $stmt2->close();
            }

            /* Nếu bảng todos có user_id thì bỏ gắn user khỏi task */
            $checkTodoUser = $conn->query("SHOW COLUMNS FROM todos LIKE 'user_id'");
            if ($checkTodoUser && $checkTodoUser->num_rows > 0) {
                $stmt3 = $conn->prepare("UPDATE todos SET user_id = NULL WHERE user_id = ?");
                if ($stmt3) {
                    $stmt3->bind_param("i", $uid);
                    $stmt3->execute();
                    $stmt3->close();
                }
            }

            /* Xóa user */
            $stmt4 = $conn->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt4) {
                $stmt4->bind_param("i", $uid);
                $stmt4->execute();
                $stmt4->close();
            }

            /* Log */
            $logContent = "Admin {$admin['username']} xóa user ID $uid";
            $stmtLog = $conn->prepare("INSERT INTO system_logs(content) VALUES (?)");
            if ($stmtLog) {
                $stmtLog->bind_param("s", $logContent);
                $stmtLog->execute();
                $stmtLog->close();
            }

            $conn->commit();
            $msg = "✅ Đã xóa người dùng";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "❌ Không thể xóa người dùng vì còn dữ liệu liên quan";
        }
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
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css">

    <style>
        .content-wrapper {
            padding: 90px 24px 24px;
        }
    </style>
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
        <div class="alert alert-info"><?= h($msg) ?></div>
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
                <?php if ($users && $users->num_rows > 0): ?>
                    <?php while ($u = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$u['id'] ?></td>
                            <td><?= h($u['username']) ?></td>
                            <td>
                                <span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'secondary' ?>">
                                    <?= h($u['role']) ?>
                                </span>
                            </td>
                            <td><?= h($u['created_at']) ?></td>
                            <td>
                                <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
                                    <?php if ($u['role'] === 'user'): ?>
                                        <a href="?change_role=<?= (int)$u['id'] ?>&role=admin"
                                           class="btn btn-sm btn-warning">
                                            Lên Admin
                                        </a>
                                    <?php else: ?>
                                        <a href="?change_role=<?= (int)$u['id'] ?>&role=user"
                                           class="btn btn-sm btn-secondary">
                                            Xuống User
                                        </a>
                                    <?php endif; ?>

                                    <a href="?delete=<?= (int)$u['id'] ?>"
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
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">Không có dữ liệu</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>