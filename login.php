<?php include 'config.php';
$theme = $_COOKIE['forum_theme'] ?? 'default';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, target-densitydpi=device-dpi">
    <title>登录 - Forum Pan</title>
    <link rel="stylesheet" href="static/css/style.css?v=<?=time()?>">
</head>
<body class="theme-<?=h($theme)?>">
    <div class="login-box">
        <div class="login-card animate-in">
            <h2 class="login-title">Forum <span>Pan</span></h2>
            <?php if(!isset($_GET['reg'])){ ?>
            <form onsubmit="return loginSubmit(this)">
                <div class="form-group">
                    <input type="text" name="username" placeholder="用户名" class="form-control" required autocomplete="username">
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="密码" class="form-control" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">登录</button>
                <a href="?reg=1" class="btn btn-outline" style="width:100%;margin-top:10px">注册账号</a>
            </form>
            <?php }else{ 
                $isOpen = isOpenRegistration();
            ?>
            <div class="open-reg-banner" id="openRegBanner" style="<?php echo $isOpen ? 'display:block;' : 'display:none;'; ?>">
                限时开放注册中，无需验证码！
            </div>
            <form onsubmit="return registerSubmit(this)">
                <div class="form-group">
                    <input type="text" name="username" placeholder="用户名" class="form-control" required autocomplete="username">
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="密码" class="form-control" required autocomplete="new-password">
                </div>
                <div class="form-group" id="codeGroup" style="<?php echo $isOpen ? 'display:none;' : 'display:block;'; ?>">
                    <input type="text" name="code" placeholder="8位注册验证码" class="form-control" maxlength="8" autocomplete="off">
                </div>
                <button type="submit" class="btn btn-success" style="width:100%;margin-top:8px">注册</button>
                <a href="login.php" class="btn btn-outline" style="width:100%;margin-top:10px">返回登录</a>
            </form>
            <?php } ?>
            
            <div style="margin-top:20px;text-align:center">
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:10px">选择配色</div>
                <div style="display:flex;justify-content:center;gap:10px;flex-wrap:wrap">
                    <button type="button" onclick="setTheme('default')" class="theme-swatch" data-theme="default" title="琥珀金" style="width:28px;height:28px;border-radius:50%;border:2px solid transparent;background:linear-gradient(135deg,#e8a83c,#d4963a);cursor:pointer"></button>
                    <button type="button" onclick="setTheme('pink')" class="theme-swatch" data-theme="pink" title="粉钻" style="width:28px;height:28px;border-radius:50%;border:2px solid transparent;background:linear-gradient(135deg,#e040fb,#d500f9);cursor:pointer"></button>
                    <button type="button" onclick="setTheme('white')" class="theme-swatch" data-theme="white" title="纯净白" style="width:28px;height:28px;border-radius:50%;border:2px solid rgba(0,0,0,0.15);background:linear-gradient(135deg,#ffffff,#e8e8f0);cursor:pointer"></button>
                    <button type="button" onclick="setTheme('black')" class="theme-swatch" data-theme="black" title="纯黑" style="width:28px;height:28px;border-radius:50%;border:2px solid rgba(255,255,255,0.1);background:#000;cursor:pointer"></button>
                    <button type="button" onclick="setTheme('blue')" class="theme-swatch" data-theme="blue" title="科技蓝" style="width:28px;height:28px;border-radius:50%;border:2px solid transparent;background:linear-gradient(135deg,#3b82f6,#60a5fa);cursor:pointer"></button>
                </div>
            </div>
        </div>
    </div>
<script>
function setTheme(name) {
    document.cookie = 'forum_theme=' + name + ';path=/;max-age=' + (86400 * 365);
    document.body.className = 'theme-' + name;
    document.querySelectorAll('.theme-swatch').forEach(function(b) {
        b.style.borderColor = b.dataset.theme === name ? 'var(--accent-primary)' : 'transparent';
    });
}
document.body.onload = function() {
    var t = document.cookie.match(/forum_theme=([^;]+)/);
    var cur = t ? t[1] : 'default';
    document.querySelectorAll('.theme-swatch').forEach(function(b) {
        b.style.borderColor = b.dataset.theme === cur ? 'var(--accent-primary)' : 'transparent';
    });
};
</script>
<script src="static/js/main.js"></script>
<?php if(isset($_GET['reg'])){ ?>
<script>
function checkOpenStatus() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'api/user.php?act=get_open_status', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.code == 1) {
                    var banner = document.getElementById('openRegBanner');
                    var codeGroup = document.getElementById('codeGroup');
                    var codeInput = codeGroup.querySelector('input');
                    if (data.is_open) {
                        banner.style.display = 'block';
                        codeGroup.style.display = 'none';
                        codeInput.removeAttribute('required');
                    } else {
                        banner.style.display = 'none';
                        codeGroup.style.display = 'block';
                        codeInput.setAttribute('required', 'required');
                    }
                }
            } catch(e) {}
        }
    };
    xhr.send();
}
checkOpenStatus();
setInterval(checkOpenStatus, 30000);
</script>
<?php } ?>
</body>
</html>
