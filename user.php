<?php include 'config.php'; isLogin();
if (isset($_GET['set_theme'])) {
    $t = $_GET['set_theme'];
    if (in_array($t, ['default', 'pink', 'white', 'black', 'blue'])) {
        setcookie('forum_theme', $t, time() + 86400 * 365, '/');
    }
    header('Location: user.php');
    exit;
}
$theme = $_COOKIE['forum_theme'] ?? 'default';
if (!in_array($theme, ['default', 'pink', 'white', 'black', 'blue'])) $theme = 'default';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, target-densitydpi=device-dpi">
    <title>个人中心 - Forum Pan</title>
    <link rel="stylesheet" href="static/css/style.css?v=<?=time()?>">
</head>
<body class="theme-<?=h($theme)?>">
<?php renderSidebar('user'); ?>
<div class="app-main">
    <div class="app-topbar">
        <button class="sidebar-toggle" type="button" onclick="toggleSidebar()">☰</button>
        <div class="app-topbar-title">个人中心</div>
    </div>
    <div class="container">
        <?php 
        $uid = $_SESSION['uid'];
        $res = mysqli_query($conn,"SELECT * FROM users WHERE id=$uid");
        $user = mysqli_fetch_array($res);
        $displayName = !empty($user['nickname']) ? $user['nickname'] : $user['username'];
        $avatarChar = mb_substr($displayName, 0, 1, 'utf-8');
        ?>
        
        <div class="card user-card animate-in">
            <div class="user-avatar-large" onclick="document.getElementById('avatarInput').click()" style="cursor:pointer;position:relative" title="点击更换头像">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?=h($user['avatar'])?>" style="width:100%;height:100%;border-radius:inherit;object-fit:cover" id="currentAvatar">
                <?php else: ?>
                    <?=h($avatarChar)?>
                <?php endif; ?>
                <div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,0.6);font-size:10px;padding:2px 0;border-radius:0 0 12px 12px;color:white;text-align:center">修改</div>
            </div>
            <input type="file" id="avatarInput" style="display:none" accept="image/*" onchange="uploadAvatar(this.files[0])">
            <div class="user-name" id="displayNickname"><?=htmlspecialchars($displayName)?></div>
            <span class="user-role" style="<?=$_SESSION['role']==1?'background:rgba(232,168,60,0.15);color:#e8a83c':''?>"><?=$_SESSION['role']==1?'管理员':'普通用户'?></span>
            
            <div style="margin-top:16px">
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px">选择配色</div>
                <div style="display:flex;justify-content:center;gap:10px">
                    <?php
                    $themes = [
                        'default' => ['琥珀金', 'linear-gradient(135deg,#e8a83c,#d4963a)'],
                        'pink' => ['粉钻', 'linear-gradient(135deg,#e040fb,#d500f9)'],
                        'white' => ['纯净白', 'linear-gradient(135deg,#ffffff,#e8e8f0)'],
                        'black' => ['纯黑', '#000'],
                        'blue' => ['科技蓝', 'linear-gradient(135deg,#3b82f6,#60a5fa)'],
                    ];
                    $curTheme = $_COOKIE['forum_theme'] ?? 'default';
                    foreach ($themes as $key => $info) {
                        $isActive = $key === $curTheme;
                        echo '<a href="user.php?set_theme=' . $key . '" class="theme-dot' . ($isActive ? ' active' : '') . '" title="' . $info[0] . '" style="display:inline-block;width:32px;height:32px;border-radius:50%;background:' . $info[1] . ';border:3px solid ' . ($isActive ? 'var(--accent-primary)' : 'transparent') . ';transition:border-color 0.2s"></a>';
                    }
                    ?>
                </div>
            </div>
            
            <div class="user-stats">
                <div class="stat-item">
                    <div class="stat-value" id="userStorageUsed">--</div>
                    <div class="stat-label">已用(MB)</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="userStorageTotal">--</div>
                    <div class="stat-label">配额(MB)</div>
                </div>
            </div>
        </div>

        <div class="card animate-in animate-delay-1">
            <h3 style="margin-bottom:16px;font-family:var(--font-display);font-size:18px;font-weight:700;">✏️ 修改昵称</h3>
            <div class="form-group">
                <input type="text" id="nickname" class="form-control" placeholder="请输入新昵称" value="<?=htmlspecialchars($user['nickname'])?>">
            </div>
            <button onclick="editNickname()" class="btn btn-primary" style="width:100%">保存昵称</button>
        </div>

        <div class="card animate-in animate-delay-2">
            <h3 style="margin-bottom:16px;font-family:var(--font-display);font-size:18px;font-weight:700;">🔐 修改密码</h3>
            <form onsubmit="return editPwd(this)">
                <div class="form-group">
                    <input type="password" name="old_pwd" placeholder="原密码" class="form-control" required>
                </div>
                <div class="form-group">
                    <input type="password" name="new_pwd" placeholder="新密码" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success" style="width:100%">修改密码</button>
            </form>
        </div>

        <button onclick="logout()" class="btn btn-outline" style="width:100%;margin-top:8px">退出登录</button>
    </div>
</div>
<script>window.APP_SESSION_NAME='<?=session_name()?>';window.APP_SESSION_ID='<?=session_id()?>';window.APP_SID_QUERY='<?=currentSidPair()?>';</script>
<script src="static/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadUserStorage();
});

function loadUserStorage() {
    requestJson('api/storage.php?act=stats', function(data) {
        if (data.code == 1) {
            var usedMB = (data.used / (1024 * 1024)).toFixed(1);
            var totalMB = (data.storage / (1024 * 1024)).toFixed(0);
            var usedEl = document.getElementById('userStorageUsed');
            var totalEl = document.getElementById('userStorageTotal');
            if (usedEl) usedEl.textContent = usedMB;
            if (totalEl) totalEl.textContent = totalMB;
        }
    });
}

function formSubmit(url, formData, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.withCredentials = true;
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                callback(data);
            } catch(e) { Toast.error('解析错误'); }
        } else { Toast.error('请求失败'); }
    };
    xhr.onerror = function() { Toast.error('网络错误'); };
    if (formData instanceof FormData) {
        xhr.send(formData);
    } else {
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send(urlEncodeFormData(formData));
    }
}

function uploadAvatar(file) {
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) { Toast.error('头像最大2MB'); return; }
    var formData = new FormData();
    formData.append('avatar', file);
    if (App.sidValue && !formData.get(App.sidName)) {
        formData.append(App.sidName, App.sidValue);
    }
    var xhr = new XMLHttpRequest();
    var url = App.withSid('api/user.php?act=upload_avatar');
    xhr.open('POST', url, true);
    xhr.withCredentials = true;
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.code == 1) {
                    Toast.success('头像上传成功');
                    var avatarEl = document.querySelector('.user-avatar-large');
                    var img = document.getElementById('currentAvatar');
                    if (img) {
                        img.src = data.avatar + '?t=' + new Date().getTime();
                    } else {
                        location.reload();
                    }
                } else {
                    Toast.error(data.msg);
                }
            } catch(e) { Toast.error('上传失败'); }
        }
    };
    xhr.onerror = function() { Toast.error('网络错误'); };
    xhr.send(formData);
    document.getElementById('avatarInput').value = '';
}
</script>
</body>
</html>
