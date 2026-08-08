<?php include 'config.php';
isLogin();
renderPageStart('我的网盘', 'storage');
?>

<div class="storage-header card animate-in">
    <div class="storage-title-row">
        <span class="storage-title">存储空间</span>
        <span class="storage-numbers" id="storageNumbers">加载中...</span>
    </div>
    <div class="storage-bar-track">
        <div class="storage-bar-fill" id="storageBar" style="width:0%"></div>
    </div>
</div>

<div class="storage-toolbar card animate-in animate-delay-1">
    <button class="btn btn-primary" onclick="document.getElementById('fileInput').click()">📤 上传文件</button>
    <input type="file" id="fileInput" style="display:none" onchange="handleUpload(this.files[0])">
    <span class="storage-hint">单个文件最大 <span id="maxFileSizeHint">50MB</span></span>
</div>

<div id="fileList" class="storage-grid animate-in animate-delay-2">
    <div class="empty-state">
        <div class="empty-icon">📂</div>
        <div class="empty-text">暂无文件，上传一些吧</div>
    </div>
</div>

<!-- 文件预览弹窗 -->
<div class="file-viewer" id="fileViewer" style="display:none">
    <div class="file-viewer-header">
        <span class="file-viewer-title" id="fileViewerTitle"></span>
        <button class="file-viewer-close" onclick="closeFileViewer()">✕</button>
    </div>
    <div class="file-viewer-body" id="fileViewerBody"></div>
</div>

<script>
var fileListData = [];

var maxFileSizeBytes = 52428800; // 默认 50MB，由 loadMaxSize 更新

document.addEventListener('DOMContentLoaded', function() {
    loadStorage();
    loadFiles();
    loadMaxSize();
});

/** 加载全局最大文件大小 */
function loadMaxSize() {
    requestJson('api/user.php?act=get_max_file_size', function(data) {
        if (data.code == 1) {
            maxFileSizeBytes = data.size_bytes;
            var sizeMb = data.size_mb;
            document.getElementById('maxFileSizeHint').textContent = sizeMb >= 1024 ? (sizeMb/1024).toFixed(1) + 'GB' : sizeMb + 'MB';
        }
    });
}

function loadStorage() {
    requestJson('api/storage.php?act=stats', function(data) {
        if (data.code == 1) {
            var used = data.used;
            var total = data.storage;
            var isAdmin = data.is_admin;
            var percent = total > 0 ? Math.min(100, (used / total) * 100) : 0;
            
            if (isAdmin) {
                document.getElementById('storageNumbers').textContent = formatSize(used) + ' / 无限';
                document.getElementById('storageBar').style.width = '0%';
            } else {
                document.getElementById('storageNumbers').textContent = formatSize(used) + ' / ' + formatSize(total);
                document.getElementById('storageBar').style.width = percent + '%';
            }
            
            if (percent > 90) {
                document.getElementById('storageBar').className = 'storage-bar-fill danger';
            } else if (percent > 70) {
                document.getElementById('storageBar').className = 'storage-bar-fill warning';
            } else {
                document.getElementById('storageBar').className = 'storage-bar-fill';
            }
        }
    });
}

function loadFiles() {
    requestJson('api/storage.php?act=list', function(data) {
        if (data.code == 1) {
            fileListData = data.files;
            renderFiles(data.files);
        }
    });
}

function renderFiles(files) {
    var container = document.getElementById('fileList');
    
    if (files.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="empty-icon">📂</div><div class="empty-text">暂无文件，上传一些吧</div></div>';
        return;
    }
    
    var html = '';
    for (var i = 0; i < files.length; i++) {
        var f = files[i];
        var previewType = getPreviewType(f.file_name, f.file_type);
        var icon = getFileIcon(f.file_name, f.file_type);
        var previewUrl = 'api/get_file.php?id=' + f.id;
        var downloadUrl = 'api/get_file.php?id=' + f.id + '&download=1';
        
        html += '<div class="file-card animate-in">';
        
        if (previewType === 'image') {
            html += '<div class="file-thumb" onclick="previewFile(' + f.id + ', \'' + escapeHtml(f.file_name) + '\', \'' + previewType + '\')">' +
                '<img src="' + previewUrl + '" alt="' + escapeHtml(f.file_name) + '" loading="lazy">' +
                '<div class="file-overlay"><span>🔍 预览</span></div></div>';
        } else if (previewType === 'video') {
            html += '<div class="file-thumb file-icon-only" onclick="previewFile(' + f.id + ', \'' + escapeHtml(f.file_name) + '\', \'' + previewType + '\')">' +
                '<div class="file-big-icon">🎬</div>' +
                '<div class="file-overlay"><span>▶️ 播放</span></div></div>';
        } else if (previewType === 'audio') {
            html += '<div class="file-thumb file-icon-only" onclick="previewFile(' + f.id + ', \'' + escapeHtml(f.file_name) + '\', \'' + previewType + '\')">' +
                '<div class="file-big-icon">🎵</div>' +
                '<div class="file-overlay"><span>▶️ 播放</span></div></div>';
        } else if (previewType === 'pdf') {
            html += '<div class="file-thumb file-icon-only" onclick="previewFile(' + f.id + ', \'' + escapeHtml(f.file_name) + '\', \'' + previewType + '\')">' +
                '<div class="file-big-icon">📕</div>' +
                '<div class="file-overlay"><span>📖 查看</span></div></div>';
        } else if (previewType === 'text') {
            html += '<div class="file-thumb file-icon-only" onclick="previewFile(' + f.id + ', \'' + escapeHtml(f.file_name) + '\', \'' + previewType + '\')">' +
                '<div class="file-big-icon">📝</div>' +
                '<div class="file-overlay"><span>📖 查看</span></div></div>';
        } else {
            html += '<div class="file-thumb file-icon-only">' +
                '<div class="file-big-icon">' + icon + '</div>' +
                '<div class="file-overlay" onclick="downloadFile(' + f.id + ')"><span>⬇️ 下载</span></div></div>';
        }
        
        html += '<div class="file-info">' +
            '<div class="file-name" title="' + escapeHtml(f.file_name) + '">' + escapeHtml(f.file_name) + '</div>' +
            '<div class="file-meta">' +
            '<span>' + formatSize(f.file_size) + '</span>' +
            '<span>' + formatTime(f.create_time) + '</span>' +
            '</div>' +
            '<div class="file-actions">' +
            '<button class="file-action-btn" onclick="downloadFile(' + f.id + ')" title="下载">⬇️ 下载</button>';
        
        if (previewType) {
            html += '<button class="file-action-btn" onclick="previewFile(' + f.id + ', \'' + escapeHtml(f.file_name) + '\', \'' + previewType + '\')" title="预览">🔍 预览</button>';
        }
        
        html += '<button class="file-action-btn danger" onclick="deleteFile(' + f.id + ', \'' + escapeHtml(f.file_name) + '\')" title="删除">🗑️ 删除</button>' +
            '</div></div></div>';
    }
    
    container.innerHTML = html;
}

function getPreviewType(name, type) {
    var ext = getExt(name).toLowerCase();
    
    // 图片
    if (type && type.indexOf('image/') === 0) return 'image';
    var imgExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico'];
    if (imgExts.indexOf(ext) > -1) return 'image';
    
    // 视频
    if (type && type.indexOf('video/') === 0) return 'video';
    var videoExts = ['mp4', 'webm', 'ogg', 'mov', 'm4v'];
    if (videoExts.indexOf(ext) > -1) return 'video';
    
    // 音频
    if (type && type.indexOf('audio/') === 0) return 'audio';
    var audioExts = ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac'];
    if (audioExts.indexOf(ext) > -1) return 'audio';
    
    // PDF
    if (ext === 'pdf') return 'pdf';
    if (type === 'application/pdf') return 'pdf';
    
    // 文本/代码
    var textExts = ['txt', 'log', 'md', 'json', 'xml', 'csv',
        'html', 'htm', 'css', 'js', 'ts', 'jsx', 'tsx', 'vue',
        'php', 'py', 'java', 'c', 'cpp', 'h', 'cs', 'go', 'rs',
        'rb', 'sh', 'bat', 'sql', 'yml', 'yaml', 'ini', 'conf'];
    if (textExts.indexOf(ext) > -1) return 'text';
    if (type && type.indexOf('text/') === 0) return 'text';
    if (type === 'application/json') return 'text';
    
    return null;
}

function previewFile(fileId, fileName, previewType) {
    var viewer = document.getElementById('fileViewer');
    var title = document.getElementById('fileViewerTitle');
    var body = document.getElementById('fileViewerBody');
    var url = 'api/get_file.php?id=' + fileId;
    
    title.textContent = fileName;
    
    if (previewType === 'image') {
        body.innerHTML = '<img src="' + url + '" alt="' + escapeHtml(fileName) + '" class="viewer-img">';
    } else if (previewType === 'video') {
        body.innerHTML = '<video src="' + url + '" controls class="viewer-video" autoplay></video>';
    } else if (previewType === 'audio') {
        body.innerHTML = '<div class="viewer-audio-wrap"><div class="viewer-audio-icon">🎵</div><div class="viewer-audio-name">' + escapeHtml(fileName) + '</div><audio src="' + url + '" controls class="viewer-audio" autoplay></audio></div>';
    } else if (previewType === 'pdf') {
        body.innerHTML = '<iframe src="' + url + '" class="viewer-pdf"></iframe>';
    } else if (previewType === 'text') {
        body.innerHTML = '<div class="viewer-text-loading">加载中...</div>';
        loadTextFile(url, body);
    }
    
    viewer.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function loadTextFile(url, container) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            var text = xhr.responseText;
            var escaped = escapeHtml(text);
            container.innerHTML = '<pre class="viewer-text">' + escaped + '</pre>';
        } else {
            container.innerHTML = '<div class="viewer-text-error">加载失败: ' + xhr.status + '</div>';
        }
    };
    xhr.onerror = function() {
        container.innerHTML = '<div class="viewer-text-error">加载失败</div>';
    };
    xhr.send();
}

function closeFileViewer() {
    var viewer = document.getElementById('fileViewer');
    var body = document.getElementById('fileViewerBody');
    viewer.style.display = 'none';
    body.innerHTML = '';
    document.body.style.overflow = '';
}

function getExt(name) {
    var parts = name.split('.');
    return parts.length > 1 ? parts[parts.length - 1] : '';
}

function getFileIcon(name, type) {
    var ext = getExt(name).toLowerCase();
    var iconMap = {
        'pdf': '📕', 'doc': '📘', 'docx': '📘',
        'xls': '📗', 'xlsx': '📗',
        'ppt': '📙', 'pptx': '📙',
        'zip': '🗂️', 'rar': '🗂️', '7z': '🗂️', 'tar': '🗂️', 'gz': '🗂️',
        'mp3': '🎵', 'wav': '🎵', 'ogg': '🎵', 'flac': '🎵',
        'mp4': '🎬', 'avi': '🎬', 'mkv': '🎬', 'mov': '🎬', 'wmv': '🎬',
        'txt': '📝', 'log': '📝', 'md': '📝',
        'html': '🌐', 'css': '🎨', 'js': '⚡',
        'php': '🐘', 'py': '🐍', 'java': '☕',
        'exe': '⚙️', 'dll': '⚙️',
        'ttf': '🔤', 'otf': '🔤', 'woff': '🔤', 'woff2': '🔤',
    };
    if (iconMap[ext]) return iconMap[ext];
    return '📄';
}

function previewImage(fileId, fileName) {
    var viewer = document.getElementById('imgViewer');
    var img = document.getElementById('imgViewerContent');
    img.src = 'api/get_file.php?id=' + fileId;
    img.alt = fileName;
    viewer.style.display = 'flex';
}

function closeImgViewer() {
    document.getElementById('imgViewer').style.display = 'none';
}

function downloadFile(fileId) {
    var link = document.createElement('a');
    link.href = 'api/get_file.php?id=' + fileId + '&download=1';
    link.download = '';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function deleteFile(fileId, fileName) {
    showConfirm('确认删除', '确定要删除文件「' + fileName + '」吗？此操作不可恢复！', function(confirmed) {
        if (!confirmed) return;
        
        requestJson('api/storage.php?act=delete&id=' + fileId, function(data) {
            if (data.code == 1) {
                Toast.success('文件已删除');
                loadStorage();
                loadFiles();
            } else {
                Toast.error(data.msg);
            }
        });
    });
}

function handleUpload(file) {
    if (!file) return;
    
    if (file.size > maxFileSizeBytes) {
        Toast.error('文件大小超出限制（最大' + (maxFileSizeBytes >= 1073741824 ? (maxFileSizeBytes/1073741824).toFixed(1) + 'GB' : (maxFileSizeBytes/1048576).toFixed(0) + 'MB') + '）');
        return;
    }
    
    var formData = new FormData();
    formData.append('file', file);
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/storage.php?act=upload', true);
    xhr.withCredentials = true;
    
    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            var pct = Math.round((e.loaded / e.total) * 100);
            Toast.info('上传中... ' + pct + '%');
        }
    };
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.code == 1) {
                    Toast.success('上传成功');
                    loadStorage();
                    loadFiles();
                } else {
                    Toast.error(data.msg);
                }
            } catch(e) {
                Toast.error('上传失败');
            }
        } else {
            Toast.error('上传失败');
        }
    };
    
    xhr.onerror = function() { Toast.error('网络错误'); };
    xhr.send(formData);
    
    document.getElementById('fileInput').value = '';
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
}

function formatTime(timeStr) {
    var d = new Date(timeStr);
    var y = d.getFullYear();
    var m = ('0' + (d.getMonth() + 1)).slice(-2);
    var day = ('0' + d.getDate()).slice(-2);
    return y + '-' + m + '-' + day;
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php renderPageEnd(); ?>
