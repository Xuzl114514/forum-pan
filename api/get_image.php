<?php
// 初始化 Session
ini_set('session.use_only_cookies', '0');
ini_set('session.use_trans_sid', '1');
session_start();

include '../config.php';

// 获取图片 ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid ID';
    exit;
}

// 查询附件信息
$res = mysqli_query($conn, "SELECT file_data, file_type FROM attachments WHERE id = $id AND file_data IS NOT NULL");
$attachment = mysqli_fetch_assoc($res);

if (!$attachment || !$attachment['file_data']) {
    http_response_code(404);
    echo 'Image not found or no data';
    exit;
}

// 输出图片
header('Content-Type: ' . $attachment['file_type']);
header('Content-Length: ' . strlen($attachment['file_data']));
header('Cache-Control: public, max-age=31536000');
echo $attachment['file_data'];
?>
