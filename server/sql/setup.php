<?php
// 数据库初始化脚本 — TCP 总端模式
// 通过 TCP 连接总端执行初始化

require_once __DIR__ . '/../../tcp_db.php';
$conn = tcp_connect('127.0.0.1', 9527);
if ($conn->connect_error) die('无法连接到总端: ' . $conn->connect_error);

// 执行转码表结构更新
$sql = file_get_contents('sql/transcode.sql');
if ($sql) {
    $queries = explode(';', $sql);
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            if (tcp_query($conn, $query)) {
                echo "执行成功: " . substr($query, 0, 50) . "...\n";
            } else {
                echo "执行失败: " . tcp_error($conn) . "\n";
            }
        }
    }
} else {
    echo "无法读取SQL文件\n";
}

echo "数据库表结构更新完成\n";
$conn->close();
?>