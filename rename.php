<?php
require_once __DIR__ . '/config/config.php';

// 检查登录
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// 验证CSRF Token
if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    die('安全验证失败，请返回重试');
}

$fileId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$newName = trim($_POST['name'] ?? '');

if ($fileId <= 0 || empty($newName)) {
    header('Location: index.php?msg=invalid_name');
    exit;
}

// 验证文件名
if (!isValidFileName($newName)) {
    header('Location: index.php?msg=invalid_name');
    exit;
}

$userId = $_SESSION['user_id'];

// 获取文件信息
$fileInfo = getFileInfo($fileId);

if (!$fileInfo) {
    header('Location: index.php?msg=file_not_found');
    exit;
}

// 验证所有者
if ($fileInfo['user_id'] != $userId) {
    header('Location: index.php?msg=permission_denied');
    exit;
}

try {
    $db = getDB();
    
    // 检查是否存在同名文件（同一目录下）
    $stmt = $db->prepare('SELECT id FROM files WHERE user_id = ? AND parent_id = ? AND name = ? AND id != ?');
    $stmt->execute([$userId, $fileInfo['parent_id'], $newName, $fileId]);
    if ($stmt->fetch()) {
        header('Location: index.php?folder=' . $fileInfo['parent_id'] . '&msg=invalid_name');
        exit;
    }
    
    // 更新文件名
    $stmt = $db->prepare('UPDATE files SET name = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$newName, $fileId, $userId]);
    
    $parentId = $fileInfo['parent_id'];
    header('Location: index.php?folder=' . $parentId . '&msg=rename_success');
    
} catch (Exception $e) {
    error_log('重命名错误: ' . $e->getMessage());
    header('Location: index.php?msg=upload_failed');
}