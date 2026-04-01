<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QLDACNTT - Đăng nhập Hệ thống</title>
    <!-- Thư viện CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-blue: #0033FF;
            --dark-blue: #0026bd;
            --soft-blue: #e6ebff;
            --bg-dark: #050a1f;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #fff;
            color: #1e293b;
            margin: 0;
        }

        /* Navbar tối giản */
        .navbar {
            padding: 20px 0;
            background: white;
            border-bottom: 1px solid #eee;
        }

        .navbar-brand {
            font-weight: 800;
            color: var(--primary-blue) !important;
            font-size: 1.5rem;
        }

        /* Hero Section tập trung vào Action */
        .hero-section {
            position: relative;
            min-height: calc(100vh - 80px);
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, var(--bg-dark) 0%, #001a80 100%);
            color: white;
            overflow: hidden;
        }

        /* Hiệu ứng trang trí Blue Glow */
        .hero-section::before {
            content: '';
            position: absolute;
            top: -10%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: var(--primary-blue);
            filter: blur(150px);
            opacity: 0.3;
            border-radius: 50%;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-section h1 {
            font-size: clamp(2.5rem, 6vw, 4rem);
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero-section p {
            font-size: 1.2rem;
            color: #cbd5e1;
            max-width: 500px;
            margin-bottom: 2.5rem;
            border-left: 4px solid var(--primary-blue);
            padding-left: 20px;
        }

        /* Button Đăng nhập chính */
        .btn-login-main {
            background-color: var(--primary-blue);
            color: white;
            padding: 16px 45px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1.1rem;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 10px 30px rgba(0, 51, 255, 0.4);
        }

        .btn-login-main:hover {
            background-color: var(--dark-blue);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0, 51, 255, 0.5);
        }

        /* Features rút gọn */
        .quick-features {
            padding: 60px 0;
            background: white;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .feature-icon {
            width: 45px;
            height: 45px;
            background: var(--soft-blue);
            color: var(--primary-blue);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .feature-text {
            font-weight: 600;
            margin: 0;
        }

        /* Footer */
        footer {
            background: #f8fafc;
            padding: 40px 0;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">HANK</a>
            <div class="ms-auto">
                <a href="public/login.php" class="btn btn-outline-primary border-2 fw-bold px-4" style="color: var(--primary-blue); border-color: var(--primary-blue);">Đăng nhập</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 hero-content">
                    <h1>Hệ thống Quản lý <br><span style="color: var(--primary-blue);">Dự án & Hội nhóm</span></h1>
                    <p>Giải pháp tập trung giúp tối ưu hóa tiến độ công việc và quản lý thành viên hiệu quả cho các dự án lĩnh vực CNTT.</p>
                    
                    <a href="public/login.php" class="btn-login-main">
                        Truy cập hệ thống <i class="fas fa-sign-in-alt ms-3"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Stats/Features -->
    <section class="quick-features">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-project-diagram"></i></div>
                        <p class="feature-text">Quản lý dự án tập trung</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-users"></i></div>
                        <p class="feature-text">Kết nối đội ngũ linh hoạt</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fas fa-file-signature"></i></div>
                        <p class="feature-text">Phê duyệt báo cáo nhanh</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="text-center">
        <div class="container">
            <p class="mb-0 fw-bold">&copy; QLDACNTT. Hệ thống quản trị nội bộ.</p>
            <small>...</small>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>