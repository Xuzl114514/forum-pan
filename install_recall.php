<?php
require_once __DIR__ . '/config.php';
// 注意：此文件需要 config.php 中已建立 $conn 连接

$sql = file_get_contents('sql/recall.sql');
if (mysqli_multi_query($conn, $sql)) {
    echo "撤回功能数据库表更新成功\n";
} else {
    echo "更新失败：" . mysqli_error($conn) . "\n";
}

mysqli_close($conn);
?>
