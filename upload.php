<?php
require_once __DIR__ . '/config/config.php';

// 只接受POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// 检查登录
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

// 验证CSRF Token
if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    echo json_encode(['success' => false, 'message' => '安全验证失败']);
    exit;
}

$userId = $_SESSION['user_id'];
$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}

// 获取父目录ID
$parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;

// 验证父目录
if ($parentId > 0) {
    $parentInfo = getFileInfo($parentId);
    if (!$parentInfo || $parentInfo['user_id'] != $userId || $parentInfo['is_folder'] != 1) {
        echo json_encode(['success' => false, 'message' => '目标目录不存在或无权访问']);
        exit;
    }
}

// 检查文件上传
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => '文件超过服务器限制',
        UPLOAD_ERR_FORM_SIZE => '文件超过表单限制',
        UPLOAD_ERR_PARTIAL => '文件只上传了部分',
        UPLOAD_ERR_NO_FILE => '没有文件被上传',
        UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
        UPLOAD_ERR_CANT_WRITE => '文件写入失败',
        UPLOAD_ERR_EXTENSION => '文件上传被扩展阻止'
    ];
    $errorMsg = $_FILES['file']['error'] !== UPLOAD_ERR_OK ? 
                ($errorMessages[$_FILES['file']['error']] ?? '上传失败') : '上传失败';
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

$file = $_FILES['file'];
$originalName = $file['name'];
$fileSize = $file['size'];
$tmpPath = $file['tmp_name'];

// 验证文件名
if (!isValidFileName($originalName)) {
    echo json_encode(['success' => false, 'message' => '文件名包含非法字符']);
    exit;
}

// 验证文件类型
if (!isAllowedExtension($originalName)) {
    echo json_encode(['success' => false, 'message' => '文件类型不被允许']);
    exit;
}

// 检查文件大小
if ($fileSize > MAX_FILE_SIZE) {
    echo json_encode(['success' => false, 'message' => '文件超过最大限制 (100MB)']);
    exit;
}

// 检查配额
if (!checkUserQuota($userId, $fileSize)) {
    echo json_encode(['success' => false, 'message' => '存储空间不足，剩余空间 ' . formatFileSize(USER_QUOTA - getUserUsedSpace($userId))]);
    exit;
}

// 检查同名文件
$db = getDB();
$stmt = $db->prepare('SELECT id FROM files WHERE user_id = ? AND parent_id = ? AND name = ? AND is_folder = 0');
$stmt->execute([$userId, $parentId, $originalName]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => '文件名已存在，请重命名后再上传']);
    exit;
}

try {
    // 生成随机存储文件名
    $storedName = generateStoredName($originalName);
    
    // 用户目录
    $userDir = getUserDirectory($userId);
    $destPath = $userDir . $storedName;
    
    // 移动文件
    if (!move_uploaded_file($tmpPath, $destPath)) {
        echo json_encode(['success' => false, 'message' => '文件保存失败']);
        exit;
    }
    
    // 记录到数据库
    $db = getDB();
    $stmt = $db->prepare('
        INSERT INTO files (user_id, parent_id, name, stored_name, file_size, is_folder) 
        VALUES (?, ?, ?, ?, ?, 0)
    ');
    $stmt->execute([$userId, $parentId, $originalName, $storedName, $fileSize]);
    
    // 更新用户已用空间
    updateUserUsedSpace($userId);
    
    echo json_encode(['success' => true, 'message' => '上传成功']);
    
} catch (Exception $e) {
    error_log('上传错误: ' . $e->getMessage());
    // 清理可能已创建的文件
    if (isset($destPath) && file_exists($destPath)) {
        @unlink($destPath);
    }
    echo json_encode(['success' => false, 'message' => '上传失败，请重试']);
}