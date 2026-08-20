<?php
/**
 * 配置文件 - 数据库连接和全局常量
 */

// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// 会话配置
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
ini_set('session.gc_maxlifetime', 1800); // 30分钟

// 数据库配置
define('DB_PATH', __DIR__ . '/../database/cloud.db');

// 存储配置
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100MB 单文件最大
define('USER_QUOTA', 1024 * 1024 * 1024); // 1GB

// 允许的文件类型
define('ALLOWED_EXTENSIONS', [
    'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp',
    'pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx',
    'zip', 'rar', '7z', 'tar', 'gz',
    'mp3', 'wav', 'flac',
    'mp4', 'avi', 'mkv', 'mov',
    'ppt', 'pptx'
]);

// 禁止的文件扩展名
define('FORBIDDEN_EXTENSIONS', [
    'php', 'phtml', 'php3', 'php4', 'php5',
    'exe', 'bat', 'cmd', 'sh', 'bash',
    'js', 'vbs', 'ps1', 'py', 'pl'
]);

// 会话名称
define('SESSION_NAME', 'cloud_session');

// CSRF Token 名称
define('CSRF_TOKEN_NAME', 'csrf_token');

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// 数据库连接函数
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // 启用外键约束
            $db->exec('PRAGMA foreign_keys = ON');
        } catch (PDOException $e) {
            die('数据库连接失败: ' . $e->getMessage());
        }
    }
    return $db;
}

// 生成CSRF Token
function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

// 验证CSRF Token
function validateCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && 
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// 检查用户是否登录
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// 获取当前用户信息
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = getDB();
    $stmt = $db->prepare('SELECT id, username, email, used_space, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// 获取用户已用空间（实时统计）
function getUserUsedSpace($userId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT COALESCE(SUM(file_size), 0) as total FROM files WHERE user_id = ? AND is_folder = 0');
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return (int)($result['total'] ?? 0);
}

// 更新用户已用空间
function updateUserUsedSpace($userId) {
    $db = getDB();
    $used = getUserUsedSpace($userId);
    $stmt = $db->prepare('UPDATE users SET used_space = ? WHERE id = ?');
    return $stmt->execute([$used, $userId]);
}

// 获取文件大小格式化
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// 安全输出
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// 生成随机文件名
function generateStoredName($originalName) {
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $random = bin2hex(random_bytes(16));
    return $random . ($ext ? '.' . $ext : '');
}

// 检查文件名是否合法（防止路径遍历）
function isValidFileName($name) {
    // 不允许空名称、包含路径分隔符或特殊字符
    return !empty($name) && 
           strpos($name, '/') === false && 
           strpos($name, '\\') === false &&
           strpos($name, '..') === false &&
           preg_match('/^[a-zA-Z0-9_\-. \p{Han}]+$/u', $name);
}

// 检查文件扩展名是否允许
function isAllowedExtension($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // 检查是否在禁止列表中
    if (in_array($ext, FORBIDDEN_EXTENSIONS)) {
        return false;
    }
    
    // 如果允许列表不为空，检查是否在允许列表中
    if (!empty(ALLOWED_EXTENSIONS)) {
        return in_array($ext, ALLOWED_EXTENSIONS);
    }
    
    return true;
}

// 获取用户目录路径
function getUserDirectory($userId) {
    $dir = UPLOAD_DIR . $userId . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

// 获取文件物理路径
function getPhysicalPath($userId, $storedName) {
    return getUserDirectory($userId) . $storedName;
}

// 递归删除目录
function rrmdir($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

// 检查用户配额
function checkUserQuota($userId, $fileSize) {
    $used = getUserUsedSpace($userId);
    return ($used + $fileSize) <= USER_QUOTA;
}

// 递归获取文件夹大小
function getFolderSize($userId, $folderId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT COALESCE(SUM(file_size), 0) as total FROM files WHERE user_id = ? AND parent_id = ? AND is_folder = 0');
    $stmt->execute([$userId, $folderId]);
    $result = $stmt->fetch();
    
    // 获取子文件夹
    $stmt2 = $db->prepare('SELECT id FROM files WHERE user_id = ? AND parent_id = ? AND is_folder = 1');
    $stmt2->execute([$userId, $folderId]);
    $subFolders = $stmt2->fetchAll();
    
    $total = $result['total'] ?? 0;
    foreach ($subFolders as $folder) {
        $total += getFolderSize($userId, $folder['id']);
    }
    
    return $total;
}

// 获取文件列表
function getFileList($userId, $parentId = 0) {
    $db = getDB();
    $stmt = $db->prepare('
        SELECT id, name, stored_name, file_size, is_folder, created_at 
        FROM files 
        WHERE user_id = ? AND parent_id = ? 
        ORDER BY is_folder DESC, name ASC
    ');
    $stmt->execute([$userId, $parentId]);
    return $stmt->fetchAll();
}

// 检查文件所有者
function isFileOwner($fileId, $userId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT user_id FROM files WHERE id = ?');
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    return $file && $file['user_id'] == $userId;
}

// 获取文件信息
function getFileInfo($fileId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM files WHERE id = ?');
    $stmt->execute([$fileId]);
    return $stmt->fetch();
}

// 获取路径面包屑
function getBreadcrumbs($userId, $folderId) {
    $breadcrumbs = [];
    $currentId = $folderId;
    
    while ($currentId > 0) {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, name, parent_id FROM files WHERE id = ? AND user_id = ?');
        $stmt->execute([$currentId, $userId]);
        $folder = $stmt->fetch();
        
        if (!$folder) break;
        
        $breadcrumbs[] = $folder;
        $currentId = $folder['parent_id'];
    }
    
    return array_reverse($breadcrumbs);
}
