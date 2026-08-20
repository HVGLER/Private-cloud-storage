<?php
require_once __DIR__ . '/config/config.php';

// 检查登录状态
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$userId = $user['id'];
$username = $user['username'];

// 获取当前目录ID
$currentFolder = isset($_GET['folder']) ? (int)$_GET['folder'] : 0;

// 验证文件夹是否存在且属于当前用户
if ($currentFolder > 0) {
    $fileInfo = getFileInfo($currentFolder);
    if (!$fileInfo || $fileInfo['user_id'] != $userId || $fileInfo['is_folder'] != 1) {
        $currentFolder = 0;
    }
}

// 更新用户已用空间
updateUserUsedSpace($userId);
$usedSpace = getUserUsedSpace($userId);
$quotaPercent = min(100, round(($usedSpace / USER_QUOTA) * 100, 2));

// 获取文件列表
$files = getFileList($userId, $currentFolder);

// 获取面包屑
$breadcrumbs = getBreadcrumbs($userId, $currentFolder);

// 生成CSRF Token
$csrfToken = generateCSRFToken();

// 处理操作消息
$message = '';
$messageType = '';
if (isset($_GET['msg'])) {
    $msgMap = [
        'upload_success' => ['文件上传成功', 'success'],
        'delete_success' => ['删除成功', 'success'],
        'rename_success' => ['重命名成功', 'success'],
        'mkdir_success' => ['文件夹创建成功', 'success'],
        'upload_failed' => ['上传失败，请重试', 'danger'],
        'quota_exceeded' => ['存储空间已满或配额不足', 'warning'],
        'file_not_found' => ['文件不存在', 'danger'],
        'permission_denied' => ['权限不足', 'danger'],
        'invalid_name' => ['文件名包含非法字符', 'danger'],
        'type_not_allowed' => ['文件类型不被允许', 'danger']
    ];
    if (isset($msgMap[$_GET['msg']])) {
        $message = $msgMap[$_GET['msg']][0];
        $messageType = $msgMap[$_GET['msg']][1];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的云盘 - <?php echo h($username); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f0f2f5;
            min-height: 100vh;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .navbar .nav-link, .navbar .navbar-brand {
            color: #fff !important;
        }
        .navbar .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }
        .main-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .storage-card {
            background: #fff;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .storage-card .progress {
            height: 8px;
            border-radius: 10px;
        }
        .storage-card .progress-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.5s ease;
        }
        .file-area {
            background: #fff;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            min-height: 500px;
        }
        .file-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s;
            cursor: default;
        }
        .file-item:hover {
            background: #f8f9fa;
        }
        .file-item .icon {
            font-size: 24px;
            width: 40px;
            text-align: center;
            color: #667eea;
        }
        .file-item .icon.folder {
            color: #ffc107;
        }
        .file-item .info {
            flex: 1;
            margin-left: 15px;
        }
        .file-item .info .name {
            font-weight: 500;
            color: #333;
        }
        .file-item .info .name a {
            color: #333;
            text-decoration: none;
        }
        .file-item .info .name a:hover {
            color: #667eea;
        }
        .file-item .info .meta {
            font-size: 12px;
            color: #999;
        }
        .file-item .actions {
            display: flex;
            gap: 8px;
        }
        .file-item .actions .btn {
            padding: 2px 8px;
            font-size: 12px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 20px;
        }
        .breadcrumb-item a {
            color: #667eea;
            text-decoration: none;
        }
        .breadcrumb-item a:hover {
            text-decoration: underline;
        }
        .upload-progress {
            display: none;
            margin-top: 15px;
        }
        .upload-progress .progress {
            height: 20px;
        }
        .upload-progress .progress-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .modal-content {
            border-radius: 15px;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border-radius: 15px 15px 0 0;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .drop-zone {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        .drop-zone i {
            font-size: 48px;
            color: #ccc;
        }
        .drop-zone p {
            margin: 10px 0 0;
            color: #999;
        }
        .file-item .icon .bi-file-earmark {
            color: #6c757d;
        }
        .file-item .icon .bi-file-image {
            color: #28a745;
        }
        .file-item .icon .bi-file-pdf {
            color: #dc3545;
        }
        .file-item .icon .bi-file-word {
            color: #007bff;
        }
        .file-item .icon .bi-file-excel {
            color: #28a745;
        }
        .file-item .icon .bi-file-zip {
            color: #ffc107;
        }
        .file-item .icon .bi-file-music {
            color: #fd7e14;
        }
        .file-item .icon .bi-file-play {
            color: #dc3545;
        }
        .file-item .icon .bi-file-text {
            color: #6c757d;
        }
        @media (max-width: 768px) {
            .file-item {
                flex-wrap: wrap;
            }
            .file-item .actions {
                margin-top: 10px;
                width: 100%;
                justify-content: flex-end;
            }
            .toolbar .btn {
                font-size: 12px;
                padding: 5px 10px;
            }
            .storage-card .col-md-6.text-md-end {
                text-align: left !important;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-cloud-upload"></i> 我的云盘
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="bi bi-person-circle"></i> <?php echo h($username); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> 退出
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo h($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- 存储空间 -->
        <div class="storage-card">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div>
                        <strong>存储空间</strong>
                        <span class="text-muted ms-2">
                            <?php echo formatFileSize($usedSpace); ?> / <?php echo formatFileSize(USER_QUOTA); ?>
                        </span>
                    </div>
                    <div class="progress mt-2">
                        <div class="progress-bar" style="width: <?php echo $quotaPercent; ?>%">
                            <?php echo $quotaPercent; ?>%
                        </div>
                    </div>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <span class="badge bg-<?php echo $quotaPercent > 90 ? 'danger' : ($quotaPercent > 70 ? 'warning' : 'success'); ?>">
                        <?php echo $quotaPercent > 90 ? '空间紧张' : ($quotaPercent > 70 ? '空间充足' : '空间充裕'); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- 工具栏 -->
        <div class="toolbar">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="bi bi-cloud-upload"></i> 上传文件
            </button>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#mkdirModal">
                <i class="bi bi-folder-plus"></i> 新建文件夹
            </button>
            <?php if ($currentFolder > 0): ?>
                <a href="index.php?folder=<?php echo $breadcrumbs[count($breadcrumbs)-1]['parent_id'] ?? 0; ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> 返回上级
                </a>
            <?php endif; ?>
            <span class="ms-auto text-muted small align-self-center">
                共 <?php echo count($files); ?> 个项目
            </span>
        </div>

        <!-- 面包屑 -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="index.php?folder=0"><i class="bi bi-house"></i> 根目录</a>
                </li>
                <?php foreach ($breadcrumbs as $crumb): ?>
                    <li class="breadcrumb-item">
                        <a href="index.php?folder=<?php echo $crumb['id']; ?>">
                            <?php echo h($crumb['name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <!-- 文件列表 -->
        <div class="file-area">
            <?php if (empty($files)): ?>
                <div class="empty-state">
                    <i class="bi bi-folder2-open"></i>
                    <h5>当前目录为空</h5>
                    <p class="text-muted">点击"上传文件"或"新建文件夹"开始管理您的文件</p>
                </div>
            <?php else: ?>
                <?php foreach ($files as $file): ?>
                    <div class="file-item" data-id="<?php echo $file['id']; ?>">
                        <div class="icon <?php echo $file['is_folder'] ? 'folder' : ''; ?>">
                            <?php if ($file['is_folder']): ?>
                                <i class="bi bi-folder-fill"></i>
                            <?php else: ?>
                                <?php
                                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                $iconMap = [
                                    'jpg' => 'bi-file-image', 'jpeg' => 'bi-file-image',
                                    'png' => 'bi-file-image', 'gif' => 'bi-file-image',
                                    'bmp' => 'bi-file-image', 'webp' => 'bi-file-image',
                                    'pdf' => 'bi-file-pdf',
                                    'txt' => 'bi-file-text',
                                    'doc' => 'bi-file-word', 'docx' => 'bi-file-word',
                                    'xls' => 'bi-file-excel', 'xlsx' => 'bi-file-excel',
                                    'zip' => 'bi-file-zip', 'rar' => 'bi-file-zip',
                                    '7z' => 'bi-file-zip', 'tar' => 'bi-file-zip', 'gz' => 'bi-file-zip',
                                    'mp3' => 'bi-file-music', 'wav' => 'bi-file-music', 'flac' => 'bi-file-music',
                                    'mp4' => 'bi-file-play', 'avi' => 'bi-file-play', 
                                    'mkv' => 'bi-file-play', 'mov' => 'bi-file-play',
                                    'ppt' => 'bi-file-slides', 'pptx' => 'bi-file-slides'
                                ];
                                $icon = 'bi ' . ($iconMap[$ext] ?? 'bi-file-earmark');
                                ?>
                                <i class="<?php echo $icon; ?>"></i>
                            <?php endif; ?>
                        </div>
                        <div class="info">
                            <div class="name">
                                <?php if ($file['is_folder']): ?>
                                    <a href="index.php?folder=<?php echo $file['id']; ?>">
                                        <?php echo h($file['name']); ?>
                                    </a>
                                <?php else: ?>
                                    <a href="download.php?id=<?php echo $file['id']; ?>">
                                        <?php echo h($file['name']); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="meta">
                                <?php if (!$file['is_folder']): ?>
                                    <?php echo formatFileSize($file['file_size']); ?>
                                    <span class="mx-1">·</span>
                                <?php endif; ?>
                                <?php echo date('Y-m-d H:i', strtotime($file['created_at'])); ?>
                            </div>
                        </div>
                        <div class="actions">
                            <button class="btn btn-sm btn-outline-primary rename-btn" 
                                    data-id="<?php echo $file['id']; ?>"
                                    data-name="<?php echo h($file['name']); ?>"
                                    data-type="<?php echo $file['is_folder'] ? 'folder' : 'file'; ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-btn"
                                    data-id="<?php echo $file['id']; ?>"
                                    data-name="<?php echo h($file['name']); ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 上传文件模态框 -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cloud-upload"></i> 上传文件</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="drop-zone" id="dropZone">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <p>点击选择文件或拖拽文件到此处</p>
                        <small class="text-muted">支持多文件上传，单个文件最大 100MB</small>
                    </div>
                    <input type="file" id="fileInput" multiple style="display:none;">
                    <div class="upload-progress" id="uploadProgress">
                        <div class="d-flex justify-content-between mb-1">
                            <span id="progressText">上传中...</span>
                            <span id="progressPercent">0%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                        </div>
                        <div id="uploadStatus" class="mt-2"></div>
                    </div>
                    <input type="hidden" id="csrfToken" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" id="parentId" value="<?php echo $currentFolder; ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- 新建文件夹模态框 -->
    <div class="modal fade" id="mkdirModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-folder-plus"></i> 新建文件夹</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="mkdirForm" action="mkdir.php" method="POST">
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h($csrfToken); ?>">
                        <input type="hidden" name="parent_id" value="<?php echo $currentFolder; ?>">
                        <div class="mb-3">
                            <label for="folderName" class="form-label">文件夹名称</label>
                            <input type="text" class="form-control" id="folderName" name="name" 
                                   placeholder="请输入文件夹名称" required>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-folder-plus"></i> 创建
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 重命名模态框 -->
    <div class="modal fade" id="renameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> 重命名</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="renameForm" action="rename.php" method="POST">
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h($csrfToken); ?>">
                        <input type="hidden" name="id" id="renameId">
                        <div class="mb-3">
                            <label for="renameName" class="form-label">新名称</label>
                            <input type="text" class="form-control" id="renameName" name="name" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> 确定
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 删除确认模态框 -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> 确认删除</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>确定要删除 <strong id="deleteName"></strong> 吗？</p>
                    <p class="text-danger small">此操作不可恢复，删除后将无法找回。</p>
                    <form id="deleteForm" action="delete.php" method="POST">
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo h($csrfToken); ?>">
                        <input type="hidden" name="id" id="deleteId">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> 确认删除
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 定义CSRF Token名称常量
        const CSRF_TOKEN_NAME = '<?php echo CSRF_TOKEN_NAME; ?>';
        
        // 文件上传 - 拖拽和点击
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const uploadProgress = document.getElementById('uploadProgress');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const progressPercent = document.getElementById('progressPercent');
        const uploadStatus = document.getElementById('uploadStatus');
        const csrfToken = document.getElementById('csrfToken').value;
        const parentId = document.getElementById('parentId').value;
        dropZone.addEventListener('click', () => fileInput.click());
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                handleFiles(e.dataTransfer.files);
            }
        });
        
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length) {
                handleFiles(fileInput.files);
            }
        });

        function handleFiles(files) {
            uploadProgress.style.display = 'block';
            uploadStatus.innerHTML = '';
            const totalFiles = files.length;
            let completed = 0;
            let hasError = false;

            // 显示开始上传信息
            progressText.textContent = '开始上传 ' + totalFiles + ' 个文件...';
            
            // 发送每个文件
            Array.from(files).forEach((file, index) => {
                const formData = new FormData();
                formData.append('file', file);
                formData.append(CSRF_TOKEN_NAME, csrfToken);
                formData.append('parent_id', parentId);

                const xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        // 计算总体进度
                        let totalProgress = 0;
                        // 这里简化处理，只显示当前文件进度
                        const percent = Math.round((e.loaded / e.total) * 100);
                        // 整体进度 = (已完成文件数 + 当前文件进度) / 总文件数
                        const overall = Math.round(((completed / totalFiles) + (percent / totalFiles / 100)) * 100);
                        progressBar.style.width = overall + '%';
                        progressPercent.textContent = overall + '%';
                    }
                });
                
                xhr.addEventListener('load', () => {
                    completed++;
                    if (xhr.status === 200) {
                        try {
                            const result = JSON.parse(xhr.responseText);
                            if (result.success) {
                                uploadStatus.innerHTML += `<div class="text-success">✓ ${file.name} 上传成功</div>`;
                            } else {
                                hasError = true;
                                uploadStatus.innerHTML += `<div class="text-danger">✗ ${file.name}: ${result.message || '上传失败'}</div>`;
                            }
                        } catch (e) {
                            hasError = true;
                            uploadStatus.innerHTML += `<div class="text-danger">✗ ${file.name}: 解析响应失败</div>`;
                            console.error('解析错误:', e, xhr.responseText);
                        }
                    } else {
                        hasError = true;
                        uploadStatus.innerHTML += `<div class="text-danger">✗ ${file.name}: 上传失败 (HTTP ${xhr.status})</div>`;
                        console.error('HTTP错误:', xhr.status, xhr.responseText);
                    }
                    
                    if (completed === totalFiles) {
                        progressText.textContent = hasError ? '上传完成，部分文件失败' : '所有文件上传完成！';
                        setTimeout(() => {
                            location.reload();
                        }, hasError ? 3000 : 1500);
                    }
                });
                
                xhr.addEventListener('error', () => {
                    completed++;
                    hasError = true;
                    uploadStatus.innerHTML += `<div class="text-danger">✗ ${file.name}: 网络错误</div>`;
                    if (completed === totalFiles) {
                        progressText.textContent = '上传完成，部分文件失败';
                    }
                });
                
                xhr.open('POST', 'upload.php');
                xhr.send(formData);
            });
        }

        // 重命名按钮
        document.querySelectorAll('.rename-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('renameId').value = this.dataset.id;
                document.getElementById('renameName').value = this.dataset.name;
                new bootstrap.Modal(document.getElementById('renameModal')).show();
            });
        });

        // 删除按钮
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('deleteId').value = this.dataset.id;
                document.getElementById('deleteName').textContent = this.dataset.name;
                new bootstrap.Modal(document.getElementById('deleteModal')).show();
            });
        });

        // 自动隐藏alert
        document.querySelectorAll('.alert').forEach(el => {
            setTimeout(() => {
                const alert = bootstrap.Alert.getInstance(el);
                if (alert) alert.close();
            }, 5000);
        });
    </script>
</body>
</html>