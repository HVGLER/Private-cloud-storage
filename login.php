<?php
require_once __DIR__ . '/config/config.php';

// 如果已登录，跳转到主页
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF Token
    if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = '安全验证失败，请重试';
    } else {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($login) || empty($password)) {
            $error = '请输入用户名/邮箱和密码';
        } else {
            try {
                $db = getDB();
                
                // 查找用户（通过用户名或邮箱）
                $stmt = $db->prepare('SELECT id, username, email, password_hash FROM users WHERE username = ? OR email = ?');
                $stmt->execute([$login, $login]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password_hash'])) {
                    // 登录成功
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    
                    // 更新会话ID以防止会话固定攻击
                    session_regenerate_id(true);
                    
                    // 更新用户已用空间
                    updateUserUsedSpace($user['id']);
                    
                    header('Location: index.php');
                    exit;
                } else {
                    $error = '用户名或密码错误';
                }
            } catch (PDOException $e) {
                $error = '登录失败，请稍后重试';
                error_log('登录错误: ' . $e->getMessage());
            }
        }
    }
}

// 生成CSRF Token
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 云盘系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 440px;
            width: 100%;
            backdrop-filter: blur(10px);
        }
        .login-card .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-card .logo i {
            font-size: 48px;
            color: #667eea;
        }
        .login-card .logo h2 {
            color: #333;
            margin-top: 10px;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            width: 100%;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .remember-me {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <i class="bi bi-cloud-check"></i>
            <h2>云盘登录</h2>
            <p class="text-muted">安全存储，随时访问</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo h($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h($csrfToken); ?>">
            
            <div class="mb-3">
                <label for="login" class="form-label">用户名 / 邮箱</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="login" name="login" 
                           placeholder="请输入用户名或邮箱" required autofocus
                           value="<?php echo h($_POST['login'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">密码</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="请输入密码" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="remember-me">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">记住我</label>
                </div>
                <a href="#" class="text-decoration-none small text-muted">忘记密码？</a>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-right"></i> 登录
            </button>
        </form>
        
        <div class="register-link">
            还没有账号？ <a href="register.php">立即注册</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 密码可见切换
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            if (password.type === 'password') {
                password.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                password.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });
    </script>
</body>
</html>