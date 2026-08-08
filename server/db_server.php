#!/usr/bin/env php
<?php
/**
 * Forum Pan — 总端 TCP 数据库服务 (PHP 版)
 * =========================================
 * 功能：
 *   - 集中管理 MySQL 数据库
 *   - 通过 TCP 端口接收客户端 SQL 请求
 *   - 支持文件二进制传输（存储/读取/删除）
 *   - 返回 JSON 格式的查询结果
 *   - 支持多客户端并发连接
 * 
 * 协议：JSON 行协议 + 二进制文件传输
 * 
 * 数据库请求：
 *   {"action":"query", "sql":"SELECT ..."}
 *   {"action":"escape", "value":"需要转义的字符串"}
 * 
 * 文件上传（客户端 → 服务端）：
 *   {"action":"store_file","file_name":"...","file_size":N,"mime_type":"..."}\n
 *   <N bytes 原始二进制数据>
 *   → {"code":1,"file_name":"...","stored_path":"server_uploads/xxx"}
 * 
 * 文件下载（服务端 → 客户端）：
 *   {"action":"get_file","file_name":"..."}\n
 *   → {"code":1,"file_name":"...","file_size":N,"mime_type":"..."}\n
 *   → <N bytes 原始二进制数据>
 * 
 * 文件删除：
 *   {"action":"delete_server_file","file_name":"..."}\n
 *   → {"code":1,"msg":"deleted"}
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

/** 服务端文件存储目录 */
$server_uploads_dir = __DIR__ . '/server_uploads/';

// 确保上传目录存在
if (!is_dir($server_uploads_dir)) {
    mkdir($server_uploads_dir, 0755, true);
}

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
// 文件存储操作
// ============================================================

/**
 * 从服务端磁盘读取文件
 * @param string $storedName 存储的文件名
 * @return array|null 文件信息或 null
 */
function get_server_file($storedName) {
    global $server_uploads_dir;
    
    $filePath = $server_uploads_dir . $storedName;
    if (!file_exists($filePath)) {
        return null;
    }
    
    $fileData = file_get_contents($filePath);
    $fileSize = filesize($filePath);
    
    return [
        "file_data" => $fileData,
        "file_size" => $fileSize,
    ];
}

/**
 * 删除服务端文件
 * @param string $storedName 存储的文件名
 * @return bool 是否删除成功
 */
function delete_server_file($storedName) {
    global $server_uploads_dir;
    $filePath = $server_uploads_dir . $storedName;
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return true; // 文件不存在也算删除成功
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

    stream_set_blocking($socket, false);

    echo "============================================================\n";
    echo "  Forum Pan — 总端 TCP 数据库服务\n";
    echo "============================================================\n";
    echo "[总端] 数据库服务已启动 → $host:$port\n";
    echo "[总端] MySQL: {$GLOBALS['db_host']}/{$GLOBALS['db_name']}\n";
    echo "[总端] 文件存储: {$GLOBALS['server_uploads_dir']}\n";
    echo "[总端] 按 Ctrl+C 停止\n";

    $clients = [];
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
                'mode' => 'json',           // 'json' | 'receiving_file'
                'file_expected' => 0,        // 期望接收的字节数
                'file_received' => 0,        // 已接收的字节数
                'file_handle' => null,       // 文件写入句柄
                'file_meta' => null,         // 文件元数据
            ];
            echo "[总端] 客户端 #{$client_id} 已连接 ({$peer})\n";
        }

        // 处理已有客户端
        foreach ($clients as $key => &$client) {
            $sock = $client['resource'];
            
            if ($client['mode'] === 'receiving_file') {
                // 二进制模式：流式接收文件数据，直接写入磁盘
                $remaining = $client['file_expected'] - $client['file_received'];
                $chunkSize = min(65536, $remaining); // 一次最多读 64KB
                $chunk = @fread($sock, $chunkSize);
                
                if ($chunk === false || ($chunk === '' && !feof($sock))) {
                    continue; // 暂无数据
                }
                
                if ($chunk === '' && feof($sock)) {
                    echo "[总端] 客户端 #{$client['id']} 在文件传输中断开\n";
                    if ($client['file_handle']) { fclose($client['file_handle']); }
                    @fclose($sock);
                    unset($clients[$key]);
                    continue;
                }
                
                // 流式写入磁盘
                if ($client['file_handle']) {
                    fwrite($client['file_handle'], $chunk);
                }
                $client['file_received'] += strlen($chunk);
                
                // 文件接收完成
                if ($client['file_received'] >= $client['file_expected']) {
                    if ($client['file_handle']) {
                        fclose($client['file_handle']);
                        $client['file_handle'] = null;
                    }
                    
                    $meta = $client['file_meta'];
                    $storedName = $meta['_stored_name'];
                    $storedPath = 'server_uploads/' . $storedName;
                    $fileSize = $client['file_received'];
                    
                    echo "[总端] 文件已存储: {$meta['file_name']} → {$storedName} (" . round($fileSize / 1024, 1) . "KB)\n";
                    
                    $result = [
                        "code" => 1,
                        "stored_name" => $storedName,
                        "stored_path" => $storedPath,
                        "file_size" => $fileSize,
                        "mime_type" => $meta['mime_type'],
                    ];
                    
                    // 在数据库中创建文件记录
                    $dbResult = execute_query($db, 
                        "INSERT INTO files(user_id, file_name, file_path, file_size, file_type) VALUES(" .
                        intval($meta['user_id']) . ", '" .
                        escape_value($db, $meta['file_name']) . "', '" .
                        escape_value($db, $storedPath) . "', " .
                        $fileSize . ", '" .
                        escape_value($db, $meta['mime_type']) . "')"
                    );
                    
                    if ($dbResult['code'] === 1) {
                        $result['file_id'] = $dbResult['data']['insert_id'];
                        // 更新用户已用存储
                        execute_query($db, 
                            "UPDATE users SET used_storage = GREATEST(0, used_storage + " . 
                            $fileSize . ") WHERE id=" . intval($meta['user_id'])
                        );
                    }
                    
                    send_response($sock, $result);
                    
                    // 重置为 JSON 模式
                    $client['mode'] = 'json';
                    $client['file_expected'] = 0;
                    $client['file_received'] = 0;
                    $client['file_meta'] = null;
                }
                continue;
            }
            
            // JSON 模式
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

                $response = process_message($msg, $sock, $client);
                
                // 如果 process_message 返回了特殊标记，说明需要切换到二进制模式
                if (isset($response['_switch_to_binary']) && $response['_switch_to_binary'] === true) {
                    // 不发送 JSON 响应，等待二进制数据
                    unset($response['_switch_to_binary']);
                    $client['mode'] = 'receiving_file';
                    $client['file_expected'] = $response['_file_size'];
                    $client['file_received'] = 0;
                    $client['file_meta'] = $response['_file_meta'];
                    // 将 buffer 中剩余的数据作为文件数据写入磁盘
                    if (strlen($buf) > 0) {
                        if ($client['file_handle']) {
                            fwrite($client['file_handle'], $buf);
                        }
                        $client['file_received'] = strlen($buf);
                        $buf = '';
                    }
                    break; // 跳出 while 循环，让二进制模式处理
                }
                
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
 * @param array $msg 解析后的 JSON 消息
 * @param resource $sock 客户端 socket
 * @param array $client 客户端状态引用
 * @return array 响应
 */
function process_message($msg, $sock, &$client) {
    global $db;

    $action = $msg['action'] ?? '';

    if ($action === 'query') {
        $sql = $msg['sql'] ?? '';
        if ($sql === '') return ["code" => 0, "error" => "SQL 不能为空"];

        if (!@$db->ping()) {
            $db = db_connect();
        }

        return execute_query($db, $sql);

    } elseif ($action === 'escape') {
        $value = $msg['value'] ?? '';
        return ["code" => 1, "data" => ["escaped" => escape_value($db, $value)]];

    } elseif ($action === 'store_file') {
        // 文件上传：客户端先发 JSON 头，再发二进制数据
        $fileSize = intval($msg['file_size'] ?? 0);
        if ($fileSize <= 0) {
            return ["code" => 0, "error" => "文件大小无效"];
        }
        if ($fileSize > 1024 * 1024 * 1024) { // 1GB 上限
            return ["code" => 0, "error" => "文件过大（最大1GB）"];
        }
        
        // 生成存储文件名并打开文件句柄（流式写入）
        $fileName = $msg['file_name'] ?? 'unknown';
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $storedName = uniqid() . '_' . time() . '.' . $ext;
        $storedPath = $GLOBALS['server_uploads_dir'] . $storedName;
        $handle = fopen($storedPath, 'wb');
        if (!$handle) {
            return ["code" => 0, "error" => "服务端无法创建文件"];
        }
        $client['file_handle'] = $handle;
        
        // 返回特殊标记，通知事件循环切换到二进制模式
        return [
            '_switch_to_binary' => true,
            '_file_size' => $fileSize,
            '_file_meta' => [
                'file_name' => $fileName,
                'mime_type' => $msg['mime_type'] ?? 'application/octet-stream',
                'user_id' => intval($msg['user_id'] ?? 0),
                '_stored_name' => $storedName,
            ],
        ];

    } elseif ($action === 'get_file') {
        // 文件下载：返回 JSON 头 + 二进制数据
        $storedName = $msg['file_name'] ?? '';
        if ($storedName === '') {
            return ["code" => 0, "error" => "文件名不能为空"];
        }
        
        // 安全检查：防止路径遍历
        $storedName = basename($storedName);
        
        $fileInfo = get_server_file($storedName);
        if ($fileInfo === null) {
            return ["code" => 0, "error" => "文件不存在"];
        }
        
        // 先发送 JSON 头
        $header = json_encode([
            "code" => 1,
            "file_name" => $storedName,
            "file_size" => $fileInfo['file_size'],
            "mime_type" => $msg['mime_type'] ?? 'application/octet-stream',
        ], JSON_UNESCAPED_UNICODE) . "\n";
        @fwrite($sock, $header);
        
        // 再发送二进制数据
        @fwrite($sock, $fileInfo['file_data']);
        
        echo "[总端] 文件已发送: {$storedName} (" . round($fileInfo['file_size'] / 1024, 1) . "KB)\n";
        
        // 返回空响应（已通过直接写入 socket 完成）
        return ["_sent" => true];

    } elseif ($action === 'delete_server_file') {
        // 删除服务端文件
        $storedName = $msg['file_name'] ?? '';
        if ($storedName === '') {
            return ["code" => 0, "error" => "文件名不能为空"];
        }
        
        $storedName = basename($storedName);
        $success = delete_server_file($storedName);
        
        if ($success) {
            echo "[总端] 文件已删除: {$storedName}\n";
            return ["code" => 1, "msg" => "文件已删除"];
        } else {
            return ["code" => 0, "error" => "删除失败"];
        }

    } else {
        return ["code" => 0, "error" => "未知操作: $action，支持 query / escape / store_file / get_file / delete_server_file"];
    }
}

/**
 * 发送 JSON 响应
 */
function send_response($sock, $data) {
    // 跳过空响应（用于 get_file 等直接写 socket 的操作）
    if (isset($data['_sent'])) return;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
    @fwrite($sock, $json);
}

// ============================================================
// 启动
// ============================================================
start_server($bind_host, $bind_port);