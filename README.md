# Private-cloud-storage
面向个人的开源（GPLv3.0）私有网盘（云存储），用PHP制作<br>
一个基于 PHP 7.4+ 和 SQLite3 的私有云盘系统，每个用户拥有独立的 1GB 存储空间。<br>

## 功能特点

- ✅ 用户注册、登录、注销<br>
- ✅ 多文件上传，带进度条<br>
- ✅ 文件/文件夹管理（创建、重命名、删除）<br>
- ✅ 1GB 存储配额管理<br>
- ✅ 文件类型白名单防护<br>
- ✅ CSRF 防护<br>
- ✅ 密码哈希存储 (password_hash)<br>
- ✅ 响应式设计，支持移动端<br>

## 技术栈

- PHP 7.1+<br>
- SQLite3<br>
- Bootstrap 5<br>
- HTML5 + CSS3 + JavaScript<br>

## 安装步骤

### 1. 环境要求

- PHP 7.1+ 已安装 PDO 扩展<br>
- SQLite3 扩展已启用<br>
- Web 服务器 (php -S或apache/nginx)<br>

### 2. 下载代码

```bash
git clone https://github.com/HVGLER/Private-cloud-storage.git<br>
cd Private-cloud-storage<br>
```
### 3. 启动网盘

用php内置的服务器启动：<br>
```bash
php -S localhost:1234 router.php
```
随后，打开浏览器，访问localhost:1234或127.0.0.1:1234开始玩转网盘吧<br>
我还没做管理后台敬请期待<br>
默认的管理员账户密码是：admin<br>
密码：admin123





所有代码由![Deepseek](./deepseek.svg)编写
