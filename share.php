<?php include 'config.php';
$code = trim($_GET['code'] ?? '');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, target-densitydpi=device-dpi">
    <title>文件分享 - Forum Pan</title>
    <link rel="stylesheet" href="static/css/style.css?v=<?=time()?>">
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:var(--bg-primary); }
        .share-box { background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:20px; padding:40px; max-width:480px; width:90%; text-align:center; }
        .share-icon { font-size:64px; margin-bottom:20px; }
        .share-filename { font-family:var(--font-display); font-size:20px; font-weight:700; color:var(--text-primary); margin-bottom:8px; word-break:break-all; }
        .share-size { color:var(--text-muted); font-size:13px; margin-bottom:24px; }
        .share-btn { display:inline-block; padding:14px 32px; background:linear-gradient(135deg, var(--accent-primary), var(--accent-secondary)); color:var(--bg-primary); border-radius:12px; font-weight:700; font-size:15px; text-decoration:none; }
        .share-btn:hover { transform:translateY(-2px); box-shadow:0 6px 20px var(--accent-glow); }
        .share-expired { color:#ef4444; font-size:15px; margin-top:16px; }
    </style>
</head>
<body>
<?php
$codeEsc = mysqli_real_escape_string($conn, $code);
$res = mysqli_query($conn, "SELECT fs.*, f.file_name, f.file_size, f.file_type FROM file_shares fs JOIN files f ON fs.file_id=f.id WHERE fs.share_code='$codeEsc' LIMIT 1");
if (!$res || mysqli_num_rows($res) == 0) {
    echo '<div class="share-box"><div class="share-icon">❌</div><div class="share-expired">分享不存在或已失效</div></div>';
    exit;
}
$share = mysqli_fetch_assoc($res);
if ($share['expire_time'] && strtotime($share['expire_time']) < time()) {
    echo '<div class="share-box"><div class="share-icon">⏰</div><div class="share-expired">此分享已过期</div></div>';
    exit;
}
$isImage = $share['file_type'] && strpos($share['file_type'], 'image/') === 0;
?>
<div class="share-box">
    <div class="share-icon"><?=$isImage ? '🖼️' : '📄'?></div>
    <div class="share-filename"><?=htmlspecialchars($share['file_name'])?></div>
    <div class="share-size"><?=number_format($share['file_size'] / 1024, 1)?> KB</div>
    <a href="api/get_file.php?id=<?=(int)$share['file_id']?>&download=1" class="share-btn">⬇️ 下载文件</a>
</div>
</body>
</html>