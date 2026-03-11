<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $repass   = trim($_POST['repassword']);

    if ($username === '' || $password === '' || $repass === '') {
        $error = "Vui lòng nhập đầy đủ thông tin";
    } elseif ($password !== $repass) {
        $error = "Mật khẩu nhập lại không khớp";
    } else {
        // Kiểm tra trùng username
        $check = $conn->query("SELECT id FROM users WHERE username='$username'");
        if ($check->num_rows > 0) {
            $error = "Tài khoản đã tồn tại";
        } else {
            // Sử dụng password_hash() để mã hóa mật khẩu
            $pass = password_hash($password, PASSWORD_DEFAULT);
            
            // Thực hiện câu lệnh SQL để lưu người dùng vào cơ sở dữ liệu
            $conn->query("INSERT INTO users(username, password, role)
                          VALUES('$username', '$pass', 'user')");
            $success = "Đăng ký thành công! Bạn có thể đăng nhập.";
        }
    }
}
?>

<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Đăng ký tài khoản</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #16a34a, #166534);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-card {
            background: #fff;
            border-radius: 16px;
            padding: 35px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 40px rgba(0,0,0,.25);
        }
        .register-icon {
            font-size: 3rem;
            color: #16a34a;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px;
        }
        .btn-register {
            background: #16a34a;
            color: #fff;
            font-weight: 600;
            border-radius: 10px;
            padding: 12px;
        }
        .btn-register:hover {
            background: #166534;
        }
    </style>
</head>
<body>

<div class="register-card">
    <div class="text-center mb-4">
        <div class="register-icon mb-2">
            <i class="fas fa-user-plus"></i>
        </div>
        <h3 class="fw-bold">Đăng ký</h3>
        <p class="text-muted mb-0">Tạo tài khoản người dùng</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= $success ?><br>
            <a href="login.php">👉 Quay lại đăng nhập</a>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label class="form-label">Tài khoản</label>
            <input type="text" name="username" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Mật khẩu</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Nhập lại mật khẩu</label>
            <input type="password" name="repassword" class="form-control" required>
        </div>

        <button class="btn btn-register w-100">
            <i class="fas fa-user-plus me-1"></i> Đăng ký
        </button>
    </form>

    <div class="text-center mt-4">
        <a href="login.php" class="text-decoration-none">
            ← Quay lại đăng nhập
        </a>
    </div>
</div>

</body>
</html> 