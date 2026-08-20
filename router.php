<?php
// router.php - PHP 内置服务器路由文件

// 定义上传目录路径（相对于当前目录）
$uploadDir = __DIR__ . '/uploads/';

// 获取请求的 URI
$uri = $_SERVER['REQUEST_URI'];

// 检查是否请求了 uploads 目录
if (strpos($uri, '/uploads/') === 0) {
    // 检查是否是文件请求
    $filePath = __DIR__ . $uri;
    
    // 如果文件存在，返回 403 禁止访问
    if (file_exists($filePath)) {
        header('HTTP/1.0 403 Forbidden');
        die('<h1>403 Forbidden</h1><p>禁止访问 uploads 目录</p>');
    }
}

// 如果请求的是 PHP 文件，执行它
if (preg_match('/\.php$/', $uri)) {
    // 将请求转发到对应的 PHP 文件
    return false;
}

// 其他请求（CSS、JS、图片等）正常处理
return false;