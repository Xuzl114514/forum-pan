<?php
include '../config.php';

$act = $_GET['act'] ?? '';
$uid = isset($_SESSION['uid']) ? intval($_SESSION['uid']) : 0;

header('Content-Type: application/json');

if ($uid === 0) {
    echo json_encode(['code' => 0, 'msg' => '请先登录']);
    exit;
}

$MAX_SIZE = 50 * 1024 * 1024; // 50MB per file

// ---------- list ----------
if ($act == 'list') {
    $res = mysqli_query($conn, "SELECT * FROM files WHERE user_id=$uid ORDER BY id DESC");
    $files = [];
    while ($row = mysqli_fetch_assoc($res)) {
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
        echo json_encode(['code' => 0, 'msg' => '文件大小超出限制（最大50MB）']);
        exit;
    }
    
    // 检查配额
    $userRes = mysqli_query($conn, "SELECT storage, used_storage FROM users WHERE id=$uid LIMIT 1");
    $userRow = mysqli_fetch_assoc($userRes);
    $quota = intval($userRow['storage']);
    $used = intval($userRow['used_storage']);
    
    if ($used + $size > $quota) {
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
    
    $relativePath = 'uploads/' . $storedName;
    $mimeType = $file['type'];
    
    mysqli_query($conn, "INSERT INTO files(user_id, file_name, file_path, file_size, file_type) VALUES($uid, '" . mysqli_real_escape_string($conn, $origName) . "', '" . mysqli_real_escape_string($conn, $relativePath) . "', $size, '" . mysqli_real_escape_string($conn, $mimeType) . "')");
    
    $newUsed = $used + $size;
    mysqli_query($conn, "UPDATE users SET used_storage=$newUsed WHERE id=$uid");
    
    $fileId = mysqli_insert_id($conn);
    echo json_encode([
        'code' => 1,
        'msg' => '上传成功',
        'file' => [
            'id' => $fileId,
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
    
    $res = mysqli_query($conn, "SELECT * FROM files WHERE id=$fid AND user_id=$uid LIMIT 1");
    if (mysqli_num_rows($res) == 0) {
        echo json_encode(['code' => 0, 'msg' => '文件不存在或无权删除']);
        exit;
    }
    
    $file = mysqli_fetch_assoc($res);
    $filePath = dirname(dirname(__DIR__)) . '/' . $file['file_path'];
    
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    $fileSize = intval($file['file_size']);
    mysqli_query($conn, "DELETE FROM files WHERE id=$fid");
    mysqli_query($conn, "UPDATE users SET used_storage = GREATEST(0, used_storage - $fileSize) WHERE id=$uid");
    
    echo json_encode(['code' => 1, 'msg' => '删除成功']);
    exit;
}

// ---------- stats ----------
if ($act == 'stats') {
    $userRes = mysqli_query($conn, "SELECT storage, used_storage FROM users WHERE id=$uid LIMIT 1");
    $userRow = mysqli_fetch_assoc($userRes);
    echo json_encode([
        'code' => 1,
        'storage' => intval($userRow['storage']),
        'used' => intval($userRow['used_storage'])
    ]);
    exit;
}

// ---------- share ----------
if ($act == 'share') {
    apiIsLogin();
    $fid = intval($_POST['file_id'] ?? 0);
    if ($fid <= 0) { echo json_encode(['code' => 0, 'msg' => '参数错误']); exit; }
    $res = mysqli_query($conn, "SELECT * FROM files WHERE id=$fid AND user_id=$uid LIMIT 1");
    if (mysqli_num_rows($res) == 0) { echo json_encode(['code' => 0, 'msg' => '文件不存在']); exit; }
    $shareCode = md5(uniqid() . $fid . $uid . time());
    mysqli_query($conn, "INSERT INTO file_shares(file_id, share_code, creator_id) VALUES($fid, '$shareCode', $uid)");
    $shareUrl = appUrl('share.php?code=' . $shareCode);
    echo json_encode(['code' => 1, 'msg' => '生成成功', 'share_code' => $shareCode, 'share_url' => $shareUrl]);
    exit;
}

// ---------- get_share ----------
if ($act == 'get_share') {
    $code = trim($_GET['code'] ?? '');
    if ($code === '') { echo json_encode(['code' => 0, 'msg' => '分享码无效']); exit; }
    $codeEsc = mysqli_real_escape_string($conn, $code);
    $res = mysqli_query($conn, "SELECT fs.*, f.file_name, f.file_size, f.file_type FROM file_shares fs JOIN files f ON fs.file_id=f.id WHERE fs.share_code='$codeEsc' LIMIT 1");
    if (!$res || mysqli_num_rows($res) == 0) { echo json_encode(['code' => 0, 'msg' => '分享不存在或已失效']); exit; }
    $row = mysqli_fetch_assoc($res);
    if ($row['expire_time'] && strtotime($row['expire_time']) < time()) {
        echo json_encode(['code' => 0, 'msg' => '分享已过期']);
        exit;
    }
    echo json_encode(['code' => 1, 'file' => $row]);
    exit;
}

echo json_encode(['code' => 0, 'msg' => '无效请求']);
