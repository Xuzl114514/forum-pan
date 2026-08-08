<?php
/**
 * 通用文件上传 API
 * 
 * 功能：
 * - 图片文件：二进制数据存储到数据库（attachments.file_data）
 * - 非图片文件：存储到文件系统（uploads/attachments/）
 * - 统一返回附件ID和信息
 * 
 * 请求方式：
 * - POST: 上传文件（字段名: file）
 * - GET?id={n}: 获取附件信息
 */

include '../config.php';

header('Content-Type: application/json');

// 登录验证
if (!isset($_SESSION['uid'])) {
    echo json_encode(['code' => 0, 'msg' => '请先登录']);
    exit;
}

$uid = intval($_SESSION['uid']);

// ========== 处理文件上传 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];

    // 检查上传错误码
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE   => '文件超过 php.ini 中 upload_max_filesize 限制',
            UPLOAD_ERR_FORM_SIZE  => '文件超过表单 MAX_FILE_SIZE 限制',
            UPLOAD_ERR_PARTIAL    => '文件只有部分被上传',
            UPLOAD_ERR_NO_FILE    => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            UPLOAD_ERR_EXTENSION  => '上传被扩展程序中断'
        ];
        $msg = $error_messages[$file['error']] ?? '未知错误';
        echo json_encode(['code' => 0, 'msg' => '上传失败：' . $msg]);
        exit;
    }

    // 验证文件大小（最大 100MB）
    $maxSize = 100 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        echo json_encode(['code' => 0, 'msg' => '文件大小不能超过 100MB']);
        exit;
    }

    // 获取文件 MIME 类型
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $fileType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // 转义文件名防止 SQL 注入
    $safeName = tcp_real_escape_string($conn, $file['name']);

    // 判断是否为图片
    $isImage = strpos($fileType, 'image/') === 0;

    if ($isImage) {
        // ========== 图片：存储到数据库 ==========
        $fileData = file_get_contents($file['tmp_name']);
        if ($fileData === false) {
            echo json_encode(['code' => 0, 'msg' => '读取文件内容失败']);
            exit;
        }

        // 生成唯一路径标识
        $dbPath = 'db://image/' . uniqid();

        // 转义二进制数据
        $escapedData = tcp_real_escape_string($conn, $fileData);

        $sql = "INSERT INTO attachments(user_id, file_name, file_path, file_type, file_size, file_data) 
                VALUES($uid, '$safeName', '$dbPath', '$fileType', {$file['size']}, '$escapedData')";

        if (tcp_query($conn, $sql)) {
            $attachmentId = tcp_insert_id($conn);
            // 更新路径为使用ID引用
            $realPath = 'db://image/' . $attachmentId;
            tcp_query($conn, "UPDATE attachments SET file_path='$realPath' WHERE id=$attachmentId");

            echo json_encode([
                'code' => 1,
                'msg' => '上传成功',
                'attachment' => [
                    'id' => $attachmentId,
                    'file_name' => $file['name'],
                    'file_path' => $realPath,
                    'file_type' => $fileType,
                    'file_size' => $file['size']
                ]
            ]);
        } else {
            echo json_encode(['code' => 0, 'msg' => '数据库保存失败：' . tcp_error($conn)]);
        }
    } else {
        // ========== 非图片：存储到文件系统 ==========
        $uploadDir = '../uploads/attachments/' . date('Ym') . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // 生成唯一文件名，防止文件名冲突
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeExt = tcp_real_escape_string($conn, $extension);
        $fileName = uniqid() . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // 相对路径存入数据库
            $dbFilePath = 'uploads/attachments/' . date('Ym') . '/' . $fileName;
            tcp_query($conn, "INSERT INTO attachments(user_id, file_name, file_path, file_type, file_size) 
                                VALUES($uid, '$safeName', '$dbFilePath', '$fileType', {$file['size']})");
            $attachmentId = tcp_insert_id($conn);

            echo json_encode([
                'code' => 1,
                'msg' => '上传成功',
                'attachment' => [
                    'id' => $attachmentId,
                    'file_name' => $file['name'],
                    'file_path' => $dbFilePath,
                    'file_type' => $fileType,
                    'file_size' => $file['size']
                ]
            ]);
        } else {
            echo json_encode(['code' => 0, 'msg' => '文件移动失败，请检查目录权限']);
        }
    }
    exit;
}

// ========== 获取附件信息 ==========
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $res = tcp_query($conn, "SELECT * FROM attachments WHERE id = $id");
    $attachment = tcp_fetch_assoc($res);

    if ($attachment) {
        echo json_encode(['code' => 1, 'attachment' => $attachment]);
    } else {
        echo json_encode(['code' => 0, 'msg' => '附件不存在']);
    }
    exit;
}

echo json_encode(['code' => 0, 'msg' => '无效请求']);
?>