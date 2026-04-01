<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password']; 

    if ($username === '' || $password === '') {
        $error = "Vui lòng nhập đầy đủ thông tin";
    } else {
        if ($stmt = $conn->prepare("SELECT * FROM users WHERE username=? LIMIT 1")) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user'] = $user;
                    header("Location: dashboard_user.php");
                    exit;
                } else {
                    $error = "Sai tài khoản hoặc mật khẩu";
                }
            } else {
                $error = "Sai tài khoản hoặc mật khẩu";
            }
        } else {
            $error = "Lỗi kết nối cơ sở dữ liệu";
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Đăng nhập vào hệ thống</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, sans-serif;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            padding: 35px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 40px rgba(0,0,0,.25);
        }
        .login-icon {
            font-size: 3rem;
            color: #2563eb;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px;
        }
        .btn-login {
            background: #2563eb;
            color: #fff;
            font-weight: 600;
            border-radius: 10px;
            padding: 12px;
        }
        .btn-login:hover {
            background: #1e40af;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <div class="login-icon mb-2">
            <i class="fas fa-user-shield"></i>
        </div>
        <h3 class="fw-bold">Đăng nhập</h3>
        <p class="text-muted mb-0">Hệ thống quản lý dự án & công việc</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
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

        <button class="btn btn-login w-100 mt-2">
            <i class="fas fa-sign-in-alt me-1"></i> Đăng nhập
        </button>

        <div class="text-center mt-3">
            <span class="text-muted">Chưa có tài khoản?</span><br>
            <a href="register.php" class="fw-semibold text-decoration-none">
                👉 Đăng ký ngay
            </a>
        </div>
    </form>

    <div class="text-center mt-4 text-muted" style="font-size: 0.9rem;">
         <?= date('Y') ?> HANK
    </div>
</div>

</body>
</html>