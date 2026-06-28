<?php
$url = isset($_GET['url']) ? trim($_GET['url']) : '';

$whitelist = array(
    'mefrp.com',
    'www.mefrp.com',
);

$target = '';
if (!empty($url)) {
    if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
        $url = 'https://' . $url;
    }
    $host = parse_url($url, PHP_URL_HOST);
    $allowed = false;
    foreach ($whitelist as $domain) {
        if ($host === $domain || substr($host, -strlen($domain) - 1) === '.' . $domain) {
            $allowed = true;
            break;
        }
    }
    if ($allowed) {
        $target = $url;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, target-densitydpi=device-dpi">
    <title>页面跳转中...</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0d0d0f;
            color: #f5f5f7;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .jump-box {
            background: #1a1a1f;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 40px;
            max-width: 420px;
            width: 100%;
            text-align: center;
        }
        .jump-icon { font-size: 48px; margin-bottom: 20px; }
        .jump-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .jump-desc {
            color: #a1a1a6;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .jump-url {
            background: #151518;
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: #e8a83c;
            word-break: break-all;
            margin-bottom: 24px;
        }
        .jump-btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #e8a83c, #d4963a);
            color: #0d0d0f;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 15px;
            transition: transform 0.15s;
        }
        .jump-btn:hover { transform: translateY(-2px); }
        .jump-tip {
            margin-top: 16px;
            font-size: 12px;
            color: #6e6e73;
        }
        .jump-error .jump-icon { color: #ef4444; }
    </style>
</head>
<body>
    <div class="jump-box <?php echo empty($target) ? 'jump-error' : ''; ?>">
        <?php if (!empty($target)): ?>
        <div class="jump-icon">🔗</div>
        <div class="jump-title">即将离开本站</div>
        <div class="jump-desc">您正在访问外部网站，请确认链接安全性</div>
        <div class="jump-url"><?php echo htmlspecialchars($target); ?></div>
        <a href="<?php echo htmlspecialchars($target); ?>" class="jump-btn" id="jumpBtn">立即跳转</a>
        <div class="jump-tip">页面将在 <span id="countdown">3</span> 秒后自动跳转</div>
        <script>
        var seconds = 3;
        var timer = setInterval(function() {
            seconds--;
            document.getElementById('countdown').textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                location.href = <?php echo json_encode($target); ?>;
            }
        }, 1000);
        document.getElementById('jumpBtn').addEventListener('click', function() {
            clearInterval(timer);
        });
        </script>
        <?php else: ?>
        <div class="jump-icon">⚠️</div>
        <div class="jump-title">链接无效</div>
        <div class="jump-desc">该链接不在允许的跳转列表中，无法跳转。</div>
        <a href="javascript:history.back()" class="jump-btn">返回上一页</a>
        <?php endif; ?>
    </div>
</body>
</html>
