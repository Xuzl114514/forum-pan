<?php
include '../config.php';

$act = $_GET['act'] ?? '';
$uid = isset($_SESSION['uid']) ? intval($_SESSION['uid']) : 0;

header('Content-Type: application/json');

if ($uid === 0) {
    echo json_encode(['code' => 0, 'msg' => '请先登录']);
    exit;
}

$MAX_SIZE = getMaxFileSize(); // 从全局设置读取最大文件大小
$isAdmin = isset($_SESSION['role']) && intval($_SESSION['role']) === 1;

// ---------- list ----------
if ($act == 'list') {
    $res = tcp_query($conn, "SELECT * FROM files WHERE user_id=$uid ORDER BY id DESC");
    $files = [];
    while ($row = tcp_fetch_assoc($res)) {
        $files[] = $row;
    }
    echo json_encode(['code' => 1, 'files' => $files]);
    exit;
}

// ---------- upload ----------
if ($act == 'upload') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['code' => 0, 'msg' => '文件上传失败']);
        exit;
    }
    
    $file = $_FILES['file'];
    $size = $file['size'];
    
    if ($size <= 0 || $size > $MAX_SIZE) {
        $maxMb = round($MAX_SIZE / 1024 / 1024, 1);
        echo json_encode(['code' => 0, 'msg' => '文件大小超出限制（最大' . $maxMb . 'MB）']);
        exit;
    }
    
    // 检查配额（管理员不限）
	    $userRes = tcp_query($conn, "SELECT storage, used_storage, role FROM users WHERE id=$uid LIMIT 1");
	    $userRow = tcp_fetch_assoc($userRes);
	    $quota = intval($userRow['storage']);
	    $used = intval($userRow['used_storage']);
	    $userRole = intval($userRow['role']);
	    
	    if ($userRole !== 1 && $used + $size > $quota) {
        echo json_encode(['code' => 0, 'msg' => '存储空间不足，请清理文件或联系管理员扩容']);
        exit;
    }
    
    $origName = $file['name'];
    $ext = pathinfo($origName, PATHINFO_EXTENSION);
    $storedName = uniqid() . '_' . time() . '.' . $ext;
    $uploadDir = dirname(dirname(__DIR__)) . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $destPath = $uploadDir . $storedName;
    
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['code' => 0, 'msg' => '文件保存失败']);
        exit;
    }
    
    $mimeType = $file['type'];
    
    // 同步上传到服务端（通过 TCP 二进制传输）
    $serverResult = tcp_store_file($conn, $destPath, $origName, $mimeType, $uid);
    if ($serverResult && ($serverResult['code'] ?? 0) === 1) {
        // 服务端存储成功，使用服务端路径
        $relativePath = $serverResult['stored_path'];
        $serverFileId = $serverResult['file_id'] ?? 0;
        // 删除本地临时文件（服务端已存储）
        @unlink($destPath);
    } else {
        // 服务端存储失败，使用本地路径作为兜底
        $relativePath = 'uploads/' . $storedName;
        $serverFileId = 0;
    }
    
    // 如果服务端已经创建了数据库记录（file_id > 0），服务端已处理存储更新
    if ($serverFileId > 0) {
        // 服务端已完成：文件存储 + DB插入 + used_storage更新，客户端无需额外操作
    } else {
        // 本地兜底：插入数据库记录
        tcp_query($conn, "INSERT INTO files(user_id, file_name, file_path, file_size, file_type) VALUES($uid, '" . tcp_real_escape_string($conn, $origName) . "', '" . tcp_real_escape_string($conn, $relativePath) . "', $size, '" . tcp_real_escape_string($conn, $mimeType) . "')");
        $newUsed = $used + $size;
        tcp_query($conn, "UPDATE users SET used_storage=$newUsed WHERE id=$uid");
        $serverFileId = tcp_insert_id($conn);
    }
    
    echo json_encode([
        'code' => 1,
        'msg' => '上传成功',
        'file' => [
            'id' => $serverFileId,
            'file_name' => $origName,
            'file_path' => $relativePath,
            'file_size' => $size,
            'file_type' => $mimeType,
            'create_time' => date('Y-m-d H:i:s')
        ]
    ]);
    exit;
}

// ---------- delete ----------
if ($act == 'delete') {
    $fid = intval($_GET['id'] ?? 0);
    if ($fid <= 0) {
        echo json_encode(['code' => 0, 'msg' => '参数错误']);
        exit;
    }
    
    $res = tcp_query($conn, "SELECT * FROM files WHERE id=$fid AND user_id=$uid LIMIT 1");
    if (tcp_num_rows($res) == 0) {
        echo json_encode(['code' => 0, 'msg' => '文件不存在或无权删除']);
        exit;
    }
    
    $file = tcp_fetch_assoc($res);
    $filePath = $file['file_path'];
    $fileSize = intval($file['file_size']);
    
    // 判断是否为服务端存储的文件（路径以 server_uploads/ 开头）
    if (strpos($filePath, 'server_uploads/') === 0) {
        // 从服务端删除
        $storedName = basename($filePath);
        tcp_delete_server_file($conn, $storedName);
    } else {
        // 从本地删除
        $localPath = dirname(dirname(__DIR__)) . '/' . $filePath;
        if (file_exists($localPath)) {
            unlink($localPath);
        }
    }
    
    tcp_query($conn, "DELETE FROM files WHERE id=$fid");
    tcp_query($conn, "UPDATE users SET used_storage = GREATEST(0, used_storage - $fileSize) WHERE id=$uid");
    
    echo json_encode(['code' => 1, 'msg' => '删除成功']);
    exit;
}

// ---------- stats ----------
	if ($act == 'stats') {
	    $userRes = tcp_query($conn, "SELECT storage, used_storage, role FROM users WHERE id=$uid LIMIT 1");
	    $userRow = tcp_fetch_assoc($userRes);
	    echo json_encode([
	        'code' => 1,
	        'storage' => intval($userRow['storage']),
	        'used' => intval($userRow['used_storage']),
	        'is_admin' => (intval($userRow['role']) === 1)
	    ]);
	    exit;
	}

// ---------- share ----------
if ($act == 'share') {
    apiIsLogin();
    $fid = intval($_POST['file_id'] ?? 0);
    if ($fid <= 0) { echo json_encode(['code' => 0, 'msg' => '参数错误']); exit; }
    $res = tcp_query($conn, "SELECT * FROM files WHERE id=$fid AND user_id=$uid LIMIT 1");
    if (tcp_num_rows($res) == 0) { echo json_encode(['code' => 0, 'msg' => '文件不存在']); exit; }
    $shareCode = md5(uniqid() . $fid . $uid . time());
    tcp_query($conn, "INSERT INTO file_shares(file_id, share_code, creator_id) VALUES($fid, '$shareCode', $uid)");
    $shareUrl = appUrl('share.php?code=' . $shareCode);
    echo json_encode(['code' => 1, 'msg' => '生成成功', 'share_code' => $shareCode, 'share_url' => $shareUrl]);
    exit;
}

// ---------- get_share ----------
if ($act == 'get_share') {
    $code = trim($_GET['code'] ?? '');
    if ($code === '') { echo json_encode(['code' => 0, 'msg' => '分享码无效']); exit; }
    $codeEsc = tcp_real_escape_string($conn, $code);
    $res = tcp_query($conn, "SELECT fs.*, f.file_name, f.file_size, f.file_type FROM file_shares fs JOIN files f ON fs.file_id=f.id WHERE fs.share_code='$codeEsc' LIMIT 1");
    if (!$res || tcp_num_rows($res) == 0) { echo json_encode(['code' => 0, 'msg' => '分享不存在或已失效']); exit; }
    $row = tcp_fetch_assoc($res);
    if ($row['expire_time'] && strtotime($row['expire_time']) < time()) {
        echo json_encode(['code' => 0, 'msg' => '分享已过期']);
        exit;
    }
    echo json_encode(['code' => 1, 'file' => $row]);
    exit;
}

echo json_encode(['code' => 0, 'msg' => '无效请求']);
