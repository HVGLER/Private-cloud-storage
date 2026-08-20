<?php
require_once __DIR__ . '/config/config.php';

// 检查登录
if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    die('请先登录');
}

$fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($fileId <= 0) {
    header('HTTP/1.1 400 Bad Request');
    die('无效的文件ID');
}

$userId = $_SESSION['user_id'];

// 获取文件信息
$fileInfo = getFileInfo($fileId);

if (!$fileInfo) {
    header('HTTP/1.1 404 Not Found');
    die('文件不存在');
}

// 验证文件所有者
if ($fileInfo['user_id'] != $userId) {
    header('HTTP/1.1 403 Forbidden');
    die('<>h1>403 Forbidden</h1><p>您无权下载他人文件</p>');
}

// 如果是文件夹，拒绝下载
if ($fileInfo['is_folder'] == 1) {
    header('HTTP/1.1 400 Bad Request');
    die('无法下载文件夹');
}

// 构建物理文件路径
$physicalPath = getPhysicalPath($userId, $fileInfo['stored_name']);

if (!file_exists($physicalPath)) {
    header('HTTP/1.1 404 Not Found');
    die('物理文件不存在');
}

// 下载文件
$originalName = $fileInfo['name'];
$fileSize = filesize($physicalPath);

// 设置下载头
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . addslashes($originalName) . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Transfer-Encoding: binary');

// 清除输出缓冲
if (ob_get_level()) {
    ob_end_clean();
}

// 输出文件流
readfile($physicalPath);
exit;