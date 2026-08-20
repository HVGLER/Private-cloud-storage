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

if ($fileId <= 0) {
    header('Location: index.php?msg=file_not_found');
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
    
    if ($fileInfo['is_folder'] == 1) {
        // 删除文件夹及其所有内容
        // 1. 获取文件夹下所有文件
        $stmt = $db->prepare('SELECT id, stored_name, is_folder FROM files WHERE user_id = ? AND parent_id = ?');
        $stmt->execute([$userId, $fileId]);
        $children = $stmt->fetchAll();
        
        // 2. 递归删除子文件
        foreach ($children as $child) {
            if ($child['is_folder'] == 1) {
                // 递归删除子文件夹
                $stmt2 = $db->prepare('SELECT stored_name FROM files WHERE user_id = ? AND parent_id = ? AND is_folder = 0');
                $stmt2->execute([$userId, $child['id']]);
                $files = $stmt2->fetchAll();
                foreach ($files as $f) {
                    $path = getPhysicalPath($userId, $f['stored_name']);
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }
                // 删除子文件夹记录
                $stmt3 = $db->prepare('DELETE FROM files WHERE user_id = ? AND parent_id = ?');
                $stmt3->execute([$userId, $child['id']]);
                $stmt4 = $db->prepare('DELETE FROM files WHERE id = ? AND user_id = ?');
                $stmt4->execute([$child['id'], $userId]);
            } else {
                // 删除物理文件
                $path = getPhysicalPath($userId, $child['stored_name']);
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        }
        
        // 删除当前文件夹记录
        $stmt = $db->prepare('DELETE FROM files WHERE id = ? AND user_id = ?');
        $stmt->execute([$fileId, $userId]);
        
        // 删除文件夹物理目录（如果存在）
        $folderPath = getUserDirectory($userId) . 'folder_' . $fileId;
        if (is_dir($folderPath)) {
            rrmdir($folderPath);
        }
        
    } else {
        // 删除单个文件
        $path = getPhysicalPath($userId, $fileInfo['stored_name']);
        if (file_exists($path)) {
            @unlink($path);
        }
        
        $stmt = $db->prepare('DELETE FROM files WHERE id = ? AND user_id = ?');
        $stmt->execute([$fileId, $userId]);
    }
    
    // 更新用户已用空间
    updateUserUsedSpace($userId);
    
    // 获取父目录ID
    $parentId = $fileInfo['parent_id'];
    header('Location: index.php?folder=' . $parentId . '&msg=delete_success');
    
} catch (Exception $e) {
    error_log('删除错误: ' . $e->getMessage());
    header('Location: index.php?msg=upload_failed');
}