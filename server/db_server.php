#!/usr/bin/env php
<?php
/**
 * Forum Pan — 总端 TCP 数据库服务 (PHP 版)
 * =========================================
 * 功能：
 *   - 集中管理 MySQL 数据库
 *   - 通过 TCP 端口接收客户端 SQL 请求
 *   - 返回 JSON 格式的查询结果
 *   - 支持多客户端并发连接
 * 
 * 协议：JSON 行协议（每条消息以换行符分隔）
 * 
 * 请求格式：
 *   {"action":"query", "sql":"SELECT ..."}
 *   {"action":"escape", "value":"需要转义的字符串"}
 * 
 * 响应格式：
 *   {"code":1, "data":{"rows":[[...]],"columns":["..."],"num_rows":N,"affected_rows":N,"insert_id":N}}
 *   {"code":0, "error":"错误信息"}
 * 
 * 启动：php server/db_server.php [host] [port] [db_host] [db_user] [db_pwd] [db_name]
 */

// ============================================================
// 配置
// ============================================================
$bind_host = $argv[1] ?? '0.0.0.0';
$bind_port = intval($argv[2] ?? 9527);
$db_host   = $argv[3] ?? 'localhost';
$db_user   = $argv[4] ?? 'root';
$db_pwd    = $argv[5] ?? 'password';
$db_name   = $argv[6] ?? 'forum_pan';

// ============================================================
// 数据库连接
// ============================================================
function db_connect() {
    global $db_host, $db_user, $db_pwd, $db_name;
    $conn = @new mysqli($db_host, $db_user, $db_pwd, $db_name);
    if ($conn->connect_error) {
        echo "[总端] 数据库连接失败: {$conn->connect_error}\n";
        echo "[总端] 请确认 MySQL 已启动，且配置正确\n";
        exit(1);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

$db = db_connect();

/**
 * 执行 SQL 查询并返回结果
 */
function execute_query($conn, $sql) {
    $result = $conn->query($sql);
    if ($result === false) {
        return ["code" => 0, "error" => $conn->error];
    }

    $sql_upper = strtoupper(trim($sql));
    if (strpos($sql_upper, 'SELECT') === 0 || strpos($sql_upper, 'SHOW') === 0 || strpos($sql_upper, 'DESCRIBE') === 0) {
        // 查询语句
        $rows = [];
        $columns = [];
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_row()) {
                $rows[] = $row;
            }
            $fields = $result->fetch_fields();
            foreach ($fields as $f) {
                $columns[] = $f->name;
            }
            $result->free();
        }
        return ["code" => 1, "data" => [
            "rows" => $rows,
            "columns" => $columns,
            "num_rows" => count($rows),
            "affected_rows" => 0,
            "insert_id" => 0,
        ]];
    } else {
        // 修改语句
        return ["code" => 1, "data" => [
            "rows" => [],
            "columns" => [],
            "num_rows" => 0,
            "affected_rows" => $conn->affected_rows,
            "insert_id" => $conn->insert_id,
        ]];
    }
}

/**
 * 转义字符串
 */
function escape_value($conn, $value) {
    return $conn->real_escape_string($value);
}

// ============================================================
// TCP 服务端
// ============================================================
function start_server($host, $port) {
    global $db;

    $socket = @stream_socket_server("tcp://$host:$port", $errno, $errstr);
    if (!$socket) {
        echo "[总端] 无法绑定 $host:$port — $errstr ($errno)\n";
        exit(1);
    }

    // 设置非阻塞
    stream_set_blocking($socket, false);

    echo "============================================================\n";
    echo "  Forum Pan — 总端 TCP 数据库服务\n";
    echo "============================================================\n";
    echo "[总端] 数据库服务已启动 → $host:$port\n";
    echo "[总端] MySQL: {$GLOBALS['db_host']}/{$GLOBALS['db_name']}\n";
    echo "[总端] 按 Ctrl+C 停止\n";

    $clients = [];  // [resource => ['buffer' => '']]
    $client_id = 0;

    while (true) {
        // 接受新连接
        $new = @stream_socket_accept($socket, 0, $peer);
        if ($new) {
            stream_set_blocking($new, false);
            $client_id++;
            $clients[(int)$new] = [
                'id' => $client_id,
                'buffer' => '',
                'resource' => $new,
            ];
            echo "[总端] 客户端 #{$client_id} 已连接 ({$peer})\n";
        }

        // 处理已有客户端
        foreach ($clients as $key => &$client) {
            $sock = $client['resource'];
            $data = @fread($sock, 4096);
            if ($data === false || $data === '') {
                if (feof($sock)) {
                    echo "[总端] 客户端 #{$client['id']} 已断开\n";
                    @fclose($sock);
                    unset($clients[$key]);
                }
                continue;
            }

            $client['buffer'] .= $data;
            $buf = $client['buffer'];

            while (($pos = strpos($buf, "\n")) !== false) {
                $line = trim(substr($buf, 0, $pos));
                $buf = substr($buf, $pos + 1);

                if ($line === '') continue;

                $msg = json_decode($line, true);
                if ($msg === null) {
                    send_response($sock, ["code" => 0, "error" => "消息格式错误"]);
                    continue;
                }

                $response = process_message($msg);
                send_response($sock, $response);
            }
            $client['buffer'] = $buf;
        }
        unset($client);

        usleep(10000); // 10ms 避免 CPU 空转
    }

    @fclose($socket);
}

/**
 * 处理客户端请求
 */
function process_message($msg) {
    global $db;

    $action = $msg['action'] ?? '';

    if ($action === 'query') {
        $sql = $msg['sql'] ?? '';
        if ($sql === '') return ["code" => 0, "error" => "SQL 不能为空"];

        // 检查连接是否存活
        if (!@$db->ping()) {
            $db = db_connect();
        }

        return execute_query($db, $sql);

    } elseif ($action === 'escape') {
        $value = $msg['value'] ?? '';
        return ["code" => 1, "data" => ["escaped" => escape_value($db, $value)]];

    } else {
        return ["code" => 0, "error" => "未知操作: $action，支持 query / escape"];
    }
}

/**
 * 发送 JSON 响应
 */
function send_response($sock, $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
    @fwrite($sock, $json);
}

// ============================================================
// 启动
// ============================================================
start_server($bind_host, $bind_port);