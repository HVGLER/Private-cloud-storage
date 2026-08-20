<?php
require_once __DIR__ . '/config/config.php';

// 如果已登录，跳转到主页
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF Token
    if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = '安全验证失败，请重试';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        // 验证输入
        if (empty($username) || empty($email) || empty($password)) {
            $error = '所有字段均为必填项';
        } elseif (strlen($username) < 3 || strlen($username) > 20) {
            $error = '用户名长度为3-20个字符';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '请输入有效的邮箱地址';
        } elseif (strlen($password) < 6) {
            $error = '密码长度至少6位';
        } elseif ($password !== $confirm) {
            $error = '两次输入的密码不一致';
        } elseif (!preg_match('/^[a-zA-Z0-9_\p{Han}]+$/u', $username)) {
            $error = '用户名只能包含字母、数字、下划线和中文';
        } else {
            try {
                $db = getDB();
                
                // 检查用户名和邮箱是否已存在
                $stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    $error = '用户名或邮箱已被注册';
                } else {
                    // 密码哈希
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // 插入用户
                    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
                    $stmt->execute([$username, $email, $passwordHash]);
                    
                    $userId = $db->lastInsertId();
                    
                    // 创建用户目录
                    getUserDirectory($userId);
                    
                    $success = '注册成功！即将跳转到登录页面...';
                    
                    // 3秒后跳转
                    header('Refresh: 2; URL=login.php');
                }
            } catch (PDOException $e) {
                $error = '注册失败，请稍后重试';
                error_log('注册错误: ' . $e->getMessage());
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
    <title>注册 - 云盘系统</title>
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
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 480px;
            width: 100%;
            backdrop-filter: blur(10px);
        }
        .register-card .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-card .logo i {
            font-size: 48px;
            color: #667eea;
        }
        .register-card .logo h2 {
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
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="logo">
            <i class="bi bi-cloud-upload"></i>
            <h2>创建账户</h2>
            <p class="text-muted">注册即获得 1GB 免费存储空间</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo h($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo h($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h($csrfToken); ?>">
            
            <div class="mb-3">
                <label for="username" class="form-label">用户名</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="请输入用户名" required autofocus
                           value="<?php echo h($_POST['username'] ?? ''); ?>">
                </div>
                <div class="form-text">3-20个字符，支持字母、数字、下划线和中文</div>
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label">邮箱</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="请输入邮箱地址" required
                           value="<?php echo h($_POST['email'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">密码</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="请输入密码（至少6位）" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="confirm_password" class="form-label">确认密码</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                           placeholder="请再次输入密码" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-person-plus"></i> 注册
            </button>
        </form>
        
        <div class="login-link">
            已有账号？ <a href="login.php">立即登录</a>
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
        
        // 密码强度指示
        document.getElementById('password').addEventListener('input', function() {
            const strength = this.value.length;
            const indicator = document.querySelector('.password-strength');
            if (!indicator) {
                const div = document.createElement('div');
                div.className = 'password-strength mt-1';
                this.parentNode.parentNode.appendChild(div);
            }
        });
    </script>
</body>
</html>