<?php include 'config.php'; isLogin(); isAdmin();
$theme = getTheme(); // 使用统一函数，适配无Cookie设备
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
    <title>管理员后台 - Forum Pan</title>
    <link rel="stylesheet" href="static/css/style.css?v=<?=time()?>">
</head>
<body class="theme-<?=h($theme)?>">
<?php renderSidebar('admin'); ?>
<div class="app-main">
    <div class="app-topbar">
        <button class="sidebar-toggle" type="button" onclick="toggleSidebar()">☰</button>
        <div class="app-topbar-title">管理员后台</div>
    </div>
    <div class="container">
        <div class="card animate-in">
            <h3 style="margin-bottom:20px;font-family:var(--font-display);font-size:20px;font-weight:700;">⚙️ 管理员后台</h3>
            
            <div style="margin-bottom:24px">
                <button onclick="createCode()" class="btn btn-success" style="width:auto;padding:0 20px">🔑 生成注册验证码</button>
                <p id="code_text" style="margin:16px 0;font-size:16px;color:var(--text-secondary)"></p>
            </div>
            
            <hr style="border:none;border-top:1px solid var(--border-subtle);margin:24px 0">

            <div class="open-reg-section">
                <div class="open-reg-header">
                    <div>
                        <div class="open-reg-title">⏰ 限时开放注册</div>
                        <div class="open-reg-desc" id="openRegStatus">正在加载状态...</div>
                    </div>
                    <button id="openRegBtn" class="btn btn-primary" onclick="toggleOpenReg()">开启（5分钟）</button>
                </div>
            </div>

            <hr style="border:none;border-top:1px solid var(--border-subtle);margin:24px 0">

            <h4 style="margin-bottom:16px;font-family:var(--font-display);font-size:16px;font-weight:600;color:var(--text-primary);">🔇 敏感词管理</h4>
            <div class="form-group" style="display:flex;gap:10px;margin-bottom:12px">
                <input type="text" id="swWord" class="form-control" placeholder="敏感词" style="flex:1">
                <select id="swLevel" class="form-control" style="width:120px">
                    <option value="1">替换***</option>
                    <option value="2">直接拦截</option>
                </select>
                <button onclick="addSensitiveWord()" class="btn btn-primary" style="padding:0 20px">添加</button>
            </div>
            <div id="swList" style="margin-bottom:24px"></div>

            <h4 style="margin-bottom:16px;font-family:var(--font-display);font-size:16px;font-weight:600;color:var(--text-primary);">🔔 系统通知</h4>
            <div id="sysNotifList" style="margin-bottom:24px"></div>

            <hr style="border:none;border-top:1px solid var(--border-subtle);margin:24px 0">

            <h4 style="margin-bottom:16px;font-family:var(--font-display);font-size:16px;font-weight:600;color:var(--text-primary);">📢 公告管理</h4>
            <div class="form-group" style="display:flex;gap:10px;margin-bottom:12px">
                <input type="text" id="annTitle" class="form-control" placeholder="公告标题" style="flex:1">
                <label style="display:flex;align-items:center;gap:4px;white-space:nowrap">
                    <input type="checkbox" id="annTop"> 置顶
                </label>
                <button onclick="addAnnouncement()" class="btn btn-primary" style="padding:0 20px">发布</button>
            </div>
            <div id="annList" style="margin-bottom:24px"></div>

            <hr style="border:none;border-top:1px solid var(--border-subtle);margin:24px 0">

            <h4 style="margin-bottom:16px;font-family:var(--font-display);font-size:16px;font-weight:600;color:var(--text-primary);">👥 用户管理</h4>
            <?php $ures = tcp_query($conn,"SELECT * FROM users WHERE id!=1 ORDER BY id DESC");
            if(tcp_num_rows($ures) == 0){
            ?>
            <div class="empty-state" style="padding:20px">
                <div class="empty-text">暂无普通用户</div>
            </div>
            <?php } while($u=tcp_fetch_array($ures)){
                $uName = !empty($u['nickname']) ? $u['nickname'] : $u['username'];
                $uAvatar = !empty($u['avatar']);
                $uChar = mb_substr($uName, 0, 1, 'utf-8');
            ?>
            <div style="padding:16px;border-bottom:1px solid var(--border-subtle);display:flex;align-items:center;gap:16px;">
                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--accent-primary),var(--accent-secondary));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;color:white;flex-shrink:0;overflow:hidden;">
                    <?php if ($uAvatar): ?>
                        <img src="<?=h($u['avatar'])?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <?=h($uChar)?>
                    <?php endif; ?>
                </div>
                <div style="flex:1">
                    <div style="font-weight:600;color:var(--text-primary);"><?=h($uName)?></div>
                    <small style="color:var(--text-muted);font-size:12px;">用户名：<?=$u['username']?> | <?=$u['status']==1?'<span style="color:#22c55e">正常</span>':'<span style="color:#ef4444">禁用</span>'?></small>
                </div>
                <button onclick="delUser(<?=(int)$u['id']?>, '<?=h($uName)?>')" class="btn btn-small btn-warning">删除</button>
            </div>
            <?php } ?>
        </div>
    </div>
</div>
<script>window.APP_SESSION_NAME='<?=session_name()?>';window.APP_SESSION_ID='<?=session_id()?>';window.APP_SID_QUERY='<?=currentSidPair()?>';</script>
<script src="static/js/main.js"></script>
<script>
var countdownTimer = null;

function loadOpenStatus() {
    requestJson('api/user.php?act=get_open_status', function(data) {
        if (data.code == 1) {
            updateOpenStatusUI(data.is_open, data.remaining);
        }
    });
}

function updateOpenStatusUI(isOpen, remaining) {
    var btn = document.getElementById('openRegBtn');
    var status = document.getElementById('openRegStatus');
    
    if (countdownTimer) {
        clearInterval(countdownTimer);
        countdownTimer = null;
    }
    
    if (isOpen) {
        btn.textContent = '关闭限时注册';
        btn.className = 'btn btn-warning';
        startCountdown(remaining);
    } else {
        btn.textContent = '开启（5分钟）';
        btn.className = 'btn btn-primary';
        status.textContent = '当前关闭中，用户注册需要验证码';
    }
}

function startCountdown(seconds) {
    function update() {
        var min = Math.floor(seconds / 60);
        var sec = seconds % 60;
        var status = document.getElementById('openRegStatus');
        if (status) {
            status.innerHTML = '<span class="open-reg-active">✅ 限时开放中 · 剩余 ' + min + '分' + (sec < 10 ? '0' + sec : sec) + '秒</span>';
        }
        seconds--;
        if (seconds < 0) {
            clearInterval(countdownTimer);
            countdownTimer = null;
            loadOpenStatus();
        }
    }
    
    update();
    countdownTimer = setInterval(update, 1000);
}

function toggleOpenReg() {
    var btn = document.getElementById('openRegBtn');
    var isOpening = btn.textContent.indexOf('关闭') > -1;
    
    if (isOpening) {
        showConfirm('确认关闭', '确定要关闭限时注册吗？', function(confirmed) {
            if (!confirmed) return;
            doToggle();
        });
    } else {
        doToggle();
    }
    
    function doToggle() {
        requestJson('api/user.php?act=toggle_open_reg', function(data) {
            if (data.code == 1) {
                Toast.success(data.msg);
                setTimeout(function() {
                    loadOpenStatus();
                }, 300);
            } else {
                Toast.error(data.msg || '操作失败');
            }
        });
    }
}

loadOpenStatus();

function delUser(userId, userName) {
    showConfirm('确认删除', '确定要删除用户「' + userName + '」吗？此操作不可恢复！', function(confirmed) {
        if (!confirmed) return;
        
        requestJson('api/user.php?act=del&id=' + userId, function(data) {
            if (data.code == 1) {
                Toast.success('用户已删除');
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                Toast.error(data.msg);
            }
        });
    });
}

loadSensitiveWords();
loadSysNotifications();

function loadSensitiveWords() {
    requestJson('api/user.php?act=get_sensitive_words', function(data) {
        if (data.code == 1) {
            var html = '';
            if (data.words.length === 0) {
                html = '<div class="empty-state" style="padding:12px"><div class="empty-text">暂无敏感词</div></div>';
            } else {
                html = '<div style="display:flex;flex-wrap:wrap;gap:8px">';
                for (var i = 0; i < data.words.length; i++) {
                    var w = data.words[i];
                    html += '<span style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:var(--bg-tertiary);border-radius:20px;font-size:13px">' + escapeHtml(w.word) + ' <span style="font-size:11px;color:var(--text-muted)">' + (w.level == 1 ? '替换' : '拦截') + '</span> <span onclick="delSw(' + w.id + ')" style="cursor:pointer;color:#ef4444;font-weight:700">×</span></span>';
                }
                html += '</div>';
            }
            document.getElementById('swList').innerHTML = html;
        }
    });
}

function addSensitiveWord() {
    var word = document.getElementById('swWord').value.trim();
    var level = document.getElementById('swLevel').value;
    if (!word) { Toast.warning('请输入敏感词'); return; }
    var formData = new FormData();
    formData.append('word', word);
    formData.append('level', level);
    formSubmit('api/user.php?act=add_sensitive_word', formData, function(data) {
        if (data.code == 1) {
            Toast.success('添加成功');
            document.getElementById('swWord').value = '';
            loadSensitiveWords();
        } else { Toast.error(data.msg); }
    });
}

function delSw(id) {
    showConfirm('确认删除', '确定要删除这个敏感词吗？', function(confirmed) {
        if (!confirmed) return;
        requestJson('api/user.php?act=del_sensitive_word&id=' + id, function(data) {
            if (data.code == 1) { Toast.success('已删除'); loadSensitiveWords(); }
            else { Toast.error(data.msg); }
        });
    });
}

function loadSysNotifications() {
    requestJson('api/forum.php?act=get_notifications', function(data) {
        if (data.code == 1) {
            var html = '';
            if (data.notifications.length === 0) {
                html = '<div class="empty-state" style="padding:12px"><div class="empty-text">暂无通知记录</div></div>';
            } else {
                for (var i = 0; i < Math.min(data.notifications.length, 10); i++) {
                    var n = data.notifications[i];
                    html += '<div style="padding:10px 0;border-bottom:1px solid var(--border-subtle);font-size:13px;color:var(--text-secondary)"><span>' + (n.is_read == 0 ? '●' : '○') + '</span> ' + escapeHtml(n.content) + ' <span style="color:var(--text-muted);font-size:12px;margin-left:8px">' + n.create_time + '</span></div>';
                }
            }
            document.getElementById('sysNotifList').innerHTML = html;
        }
    });
}

loadAnnouncements();

function loadAnnouncements() {
    requestJson('api/announcement.php?action=get_all', function(data) {
        if (data.success) {
            var html = '';
            if (data.data.length === 0) {
                html = '<div class="empty-state" style="padding:12px"><div class="empty-text">暂无公告</div></div>';
            } else {
                html = '<div style="display:flex;flex-direction:column;gap:10px">';
                for (var i = 0; i < data.data.length; i++) {
                    var a = data.data[i];
                    html += '<div style="padding:12px;background:var(--bg-tertiary);border-radius:8px;position:relative">' +
                        '<div style="font-weight:600;color:var(--text-primary);margin-bottom:4px">' +
                        (a.is_top == 1 ? '📌 ' : '') + escapeHtml(a.title) + '</div>' +
                        '<div style="color:var(--text-secondary);font-size:13px;margin-bottom:6px;white-space:pre-wrap">' + escapeHtml(a.content) + '</div>' +
                        '<div style="color:var(--text-muted);font-size:11px;margin-bottom:8px">' + a.created_at + ' | ' + (a.status == 1 ? '<span style="color:#22c55e">显示</span>' : '<span style="color:#ef4444">隐藏</span>') +
                        ' | 已读：<span style="color:#22c55e">' + a.read_count + '</span> / 未读：<span style="color:#ef4444">' + a.unread_count + '</span>（共 ' + a.total_users + ' 人）</div>' +
                        '<div style="display:flex;gap:8px">' +
                        '<button onclick="toggleAnnTop(' + a.id + ', ' + a.is_top + ')" class="btn btn-small ' + (a.is_top == 1 ? 'btn-warning' : 'btn-primary') + '" style="padding:0 10px">' + (a.is_top == 1 ? '取消置顶' : '置顶') + '</button>' +
                        '<button onclick="toggleAnnStatus(' + a.id + ', ' + a.status + ')" class="btn btn-small ' + (a.status == 1 ? 'btn-warning' : 'btn-success') + '" style="padding:0 10px">' + (a.status == 1 ? '隐藏' : '显示') + '</button>' +
                        '<button onclick="delAnnouncement(' + a.id + ')" class="btn btn-small btn-danger" style="padding:0 10px">删除</button>' +
                        '</div></div>';
                }
                html += '</div>';
            }
            document.getElementById('annList').innerHTML = html;
        }
    });
}

function addAnnouncement() {
    var title = document.getElementById('annTitle').value.trim();
    if (!title) { Toast.warning('请输入公告标题'); return; }
    showPrompt('发布公告', '请输入公告内容：', '', function(content) {
        if (content === null) return;
        content = content.trim();
        if (!content) { Toast.warning('请输入公告内容'); return; }
        var isTop = document.getElementById('annTop').checked ? 1 : 0;
        var formData = new FormData();
        formData.append('title', title);
        formData.append('content', content);
        formData.append('is_top', isTop);
        formSubmit('api/announcement.php?action=create', formData, function(data) {
            if (data.success) {
                Toast.success('公告已发布');
                document.getElementById('annTitle').value = '';
                document.getElementById('annTop').checked = false;
                loadAnnouncements();
            } else { Toast.error(data.error); }
        });
    });
}

function toggleAnnTop(id, currentTop) {
    var formData = new FormData();
    formData.append('id', id);
    formData.append('title', '');
    formData.append('content', '');
    formData.append('is_top', currentTop == 1 ? 0 : 1);
    formData.append('status', 1);
    formSubmit('api/announcement.php?action=update', formData, function(data) {
        if (data.success) { Toast.success('已更新'); loadAnnouncements(); }
        else { Toast.error(data.error); }
    });
}

function toggleAnnStatus(id, currentStatus) {
    var formData = new FormData();
    formData.append('id', id);
    formData.append('title', '');
    formData.append('content', '');
    formData.append('is_top', 0);
    formData.append('status', currentStatus == 1 ? 0 : 1);
    formSubmit('api/announcement.php?action=update', formData, function(data) {
        if (data.success) { Toast.success('已更新'); loadAnnouncements(); }
        else { Toast.error(data.error); }
    });
}

function delAnnouncement(id) {
    showConfirm('确认删除', '确定要删除这条公告吗？', function(confirmed) {
        if (!confirmed) return;
        var formData = new FormData();
        formData.append('id', id);
        formSubmit('api/announcement.php?action=delete', formData, function(data) {
            if (data.success) { Toast.success('已删除'); loadAnnouncements(); }
            else { Toast.error(data.error); }
        });
    });
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
</body>
</html>
