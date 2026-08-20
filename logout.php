<?php
require_once __DIR__ . '/config/config.php';

// 销毁会话
$_SESSION = array();

// 删除会话Cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// 跳转到登录页
header('Location: login.php');
exit;