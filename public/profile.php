<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* ===== BẮT BUỘC ĐĂNG NHẬP ===== */
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$uid  = (int)$user['id'];

$pageTitle = 'Hồ sơ cá nhân';
$current   = basename($_SERVER['PHP_SELF']);

$success = '';
$error   = '';

/* ================== UPLOAD AVATAR ================== */
if (isset($_POST['upload_avatar']) && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];

    if ($file['error'] === 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allow = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allow)) {
            $error = 'Chỉ cho phép ảnh JPG, PNG, WEBP';
        } else {
            $newName = 'avatar_'.$uid.'_'.time().'.'.$ext;
            $uploadDir = __DIR__ . '/uploads/avatars/';
            $uploadPath = $uploadDir . $newName;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $stmt = $conn->prepare("UPDATE users SET avatar=? WHERE id=?");
                $stmt->bind_param("si", $newName, $uid);
                $stmt->execute();

                $_SESSION['user']['avatar'] = $newName;
                $user['avatar'] = $newName;

                // Nếu bạn có hàm ghi log
                // write_log($conn, $uid, 'UPDATE_AVATAR', 'Người dùng cập nhật avatar');

                $success = '✔ Cập nhật avatar thành công';
            } else {
                $error = 'Không thể upload ảnh';
            }
        }
    }
}

/* ================== CẬP NHẬT EMAIL ================== */
if (isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ';
    } else {
        $stmt = $conn->prepare("UPDATE users SET email=? WHERE id=?");
        $stmt->bind_param("si", $email, $uid);
        $stmt->execute();

        $_SESSION['user']['email'] = $email;
        $user['email'] = $email;

        // write_log($conn, $uid, 'UPDATE_PROFILE', 'Cập nhật email');

        $success = '✔ Cập nhật thông tin thành công';
    }
}

/* ================== ĐỔI MẬT KHẨU ================== */
if (isset($_POST['change_password'])) {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $re  = $_POST['re_password'] ?? '';

    if ($new !== $re) {
        $error = 'Mật khẩu mới không khớp';
    } elseif (strlen($new) < 6) {
        $error = 'Mật khẩu phải từ 6 ký tự';
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $hash = $stmt->get_result()->fetch_assoc()['password'] ?? '';

        if (!password_verify($old, $hash)) {
            $error = 'Mật khẩu hiện tại không đúng';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param("si", $newHash, $uid);
            $stmt->execute();

            // write_log($conn, $uid, 'CHANGE_PASSWORD', 'Đổi mật khẩu');

            $success = '✔ Đổi mật khẩu thành công';
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Hồ sơ cá nhân</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/sidebar.css">

<style>
.profile-card {
    max-width: 560px;
}
</style>
</head>
<body>

<?php include __DIR__ . '/layouts/header.php'; ?>
<?php include __DIR__ . '/layouts/sidebar.php'; ?>

<div class="content-wrapper">

<h4 class="mb-4">
    <i class="fas fa-user-circle me-2"></i> Hồ sơ cá nhân
</h4>

<?php if ($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<!-- AVATAR -->
<div class="card p-4 mb-4 profile-card">
    <h6 class="mb-3">Ảnh đại diện</h6>

    <div class="d-flex align-items-center gap-3">
        <img src="uploads/avatars/<?= htmlspecialchars($user['avatar'] ?? 'default.png') ?>"
             class="rounded-circle"
             style="width:90px;height:90px;object-fit:cover"
             alt="Avatar">

        <form method="post" enctype="multipart/form-data">
            <input type="file" name="avatar" class="form-control mb-2" accept="image/*" required>
            <button name="upload_avatar" class="btn btn-secondary btn-sm">
                <i class="fas fa-image"></i> Cập nhật avatar
            </button>
        </form>
    </div>
</div>

<!-- THÔNG TIN -->
<div class="card p-4 profile-card mb-4">
    <h6 class="mb-3">Thông tin tài khoản</h6>

    <form method="post">
        <div class="mb-3">
            <label class="form-label">Tên đăng nhập</label>
            <input class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
        </div>

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input name="email" class="form-control"
                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Vai trò</label>
            <input class="form-control"
                   value="<?= strtoupper($user['role']) ?>" disabled>
        </div>

        <button name="update_profile" class="btn btn-primary">
            <i class="fas fa-save"></i> Lưu thông tin
        </button>
    </form>
</div>

<!-- ĐỔI MẬT KHẨU -->
<div class="card p-4 profile-card">
    <h6 class="mb-3">Đổi mật khẩu</h6>

    <form method="post">
        <div class="mb-3">
            <label class="form-label">Mật khẩu hiện tại</label>
            <input type="password" name="old_password" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Mật khẩu mới</label>
            <input type="password" name="new_password" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Nhập lại mật khẩu mới</label>
            <input type="password" name="re_password" class="form-control" required>
        </div>

        <button name="change_password" class="btn btn-warning">
            <i class="fas fa-key"></i> Đổi mật khẩu
        </button>
    </form>
</div>

</div>
</body>
</html>
