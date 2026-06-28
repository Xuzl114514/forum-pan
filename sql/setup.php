<?php
// 数据库初始化脚本 - 请根据实际情况修改
$host = 'localhost';
$user = 'root';
$pwd = 'password';
$dbname = 'forum_pan';

$conn = new mysqli($host, $user, $pwd, $dbname);
if ($conn->connect_error) {
    die('数据库连接失败: ' . $conn->connect_error);
}
mysqli_set_charset($conn, 'utf8mb4');

// 执行转码表结构更新
$sql = file_get_contents('sql/transcode.sql');
if ($sql) {
    $queries = explode(';', $sql);
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            if (mysqli_query($conn, $query)) {
                echo "执行成功: " . substr($query, 0, 50) . "...\n";
            } else {
                echo "执行失败: " . mysqli_error($conn) . "\n";
            }
        }
    }
} else {
    echo "无法读取SQL文件\n";
}

echo "数据库表结构更新完成\n";
$conn->close();
?>