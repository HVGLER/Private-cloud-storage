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

$folderName = trim($_POST['name'] ?? '');
$parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;

if (empty($folderName)) {
    header('Location: index.php?folder=' . $parentId . '&msg=invalid_name');
    exit;
}

// 验证文件夹名
if (!isValidFileName($folderName)) {
    header('Location: index.php?folder=' . $parentId . '&msg=invalid_name');
    exit;
}

$userId = $_SESSION['user_id'];

// 验证父目录
if ($parentId > 0) {
    $parentInfo = getFileInfo($parentId);
    if (!$parentInfo || $parentInfo['user_id'] != $userId || $parentInfo['is_folder'] != 1) {
        header('Location: index.php?msg=permission_denied');
        exit;
    }
}

try {
    $db = getDB();
    
    // 检查同名文件夹
    $stmt = $db->prepare('SELECT id FROM files WHERE user_id = ? AND parent_id = ? AND name = ? AND is_folder = 1');
    $stmt->execute([$userId, $parentId, $folderName]);
    if ($stmt->fetch()) {
        header('Location: index.php?folder=' . $parentId . '&msg=invalid_name');
        exit;
    }
    
    // 创建文件夹记录
    $stmt = $db->prepare('
        INSERT INTO files (user_id, parent_id, name, stored_name, file_size, is_folder) 
        VALUES (?, ?, ?, NULL, 0, 1)
    ');
    $stmt->execute([$userId, $parentId, $folderName]);
    
    header('Location: index.php?folder=' . $parentId . '&msg=mkdir_success');
    
} catch (Exception $e) {
    error_log('创建文件夹错误: ' . $e->getMessage());
    header('Location: index.php?folder=' . $parentId . '&msg=upload_failed');
}