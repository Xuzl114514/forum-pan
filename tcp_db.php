<?php
/**
 * Forum Pan — 客户端 TCP 数据库层
 * ================================
 * 替代直接 MySQL 连接，通过 TCP 与总端通信
 * 提供与 mysqli 兼容的接口
 * 
 * 用法：
 *   require_once 'tcp_db.php';
 *   $conn = tcp_connect('总端IP', 9527);
 *   tcp_query($conn, "SELECT * FROM users");
 *   $row = tcp_fetch_assoc($result);
 */

// ============================================================
// TCP 连接类
// ============================================================
class TcpConnection {
    public $socket = null;
    public $host = '';
    public $port = 0;
    public $insert_id = 0;
    public $affected_rows = 0;
    public $error = '';
    public $connect_error = '';
    public $errno = 0;
    public $connect_errno = 0;
    private $_buffer = '';

    /** 连接到总端 TCP 数据库服务 */
    public function __construct($host = '127.0.0.1', $port = 9527) {
        $this->host = $host;
        $this->port = $port;
        $this->socket = @stream_socket_client("tcp://$host:$port", $this->connect_errno, $this->connect_error, 3);
        if (!$this->socket) {
            $this->connect_error = "无法连接到总端 $host:$port — {$this->connect_error}";
        }
    }

    /** 检查连接是否成功 */
    public function is_connected() {
        return $this->socket !== null && $this->socket !== false;
    }

    /** 设置字符集（兼容 mysqli，TCP 端已设置 utf8mb4） */
    public function set_charset($charset) { return true; }

    /** 发送 JSON 请求 */
    private function _send($data) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
        return @fwrite($this->socket, $json);
    }

    /** 接收 JSON 响应 */
    private function _recv() {
        $line = '';
        while (($pos = strpos($this->_buffer, "\n")) === false) {
            $data = @fread($this->socket, 4096);
            if ($data === false || $data === '') {
                if (feof($this->socket)) { $this->error = '连接已断开'; return null; }
                continue;
            }
            $this->_buffer .= $data;
        }
        $line = substr($this->_buffer, 0, $pos);
        $this->_buffer = substr($this->_buffer, $pos + 1);
        return json_decode(trim($line), true);
    }

    /** 执行 SQL 查询 */
    public function query($sql) {
        if (!$this->is_connected()) { $this->error = '未连接到总端'; return false; }
        $this->_send(['action' => 'query', 'sql' => $sql]);
        $resp = $this->_recv();
        if ($resp === null) { $this->error = '响应解析失败: ' . $this->error; return false; }
        if ($resp['code'] === 0) { $this->error = 'SQL错误: ' . ($resp['error'] ?? '未知'); return false; }
        $data = $resp['data'];
        $this->insert_id = $data['insert_id'];
        $this->affected_rows = $data['affected_rows'];
        return new TcpResult($data['rows'], $data['columns'], $data['num_rows']);
    }

    /** 转义字符串 */
	    public function real_escape_string($value) {
	        if (!$this->is_connected()) {
	            $this->error = '未连接到总端，无法转义字符串';
	            return false;
	        }
	        $this->_send(['action' => 'escape', 'value' => $value]);
	        $resp = $this->_recv();
	        if ($resp && $resp['code'] === 1) return $resp['data']['escaped'];
	        $this->error = '总端转义失败: ' . ($resp['error'] ?? '未知错误');
	        return false;
	    }

    /** 关闭连接 */
    public function close() {
        if ($this->socket) { @fclose($this->socket); $this->socket = null; }
    }

    // ============================================================
    // 文件传输方法（二进制协议）
    // ============================================================

    /** 发送原始数据（JSON + 二进制），不经过 _send */
    public function send_raw($data) {
        return @fwrite($this->socket, $data);
    }

    /** 接收精确指定字节数的数据（阻塞直到读完） */
    public function recv_raw($length) {
        $data = '';
        $remaining = $length;
        // 先消耗 buffer 中已有的数据
        if (strlen($this->_buffer) > 0) {
            $fromBuffer = substr($this->_buffer, 0, $remaining);
            $data .= $fromBuffer;
            $remaining -= strlen($fromBuffer);
            $this->_buffer = substr($this->_buffer, strlen($fromBuffer));
        }
        // 从 socket 读取剩余数据
        while ($remaining > 0) {
            $chunk = @fread($this->socket, min(65536, $remaining));
            if ($chunk === false || $chunk === '') {
                if (feof($this->socket)) {
                    $this->error = '连接在文件传输中断开';
                    return false;
                }
                usleep(10000); // 10ms 等待
                continue;
            }
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }
        return $data;
    }

    /** 接收一行 JSON 数据（直到换行符） */
    public function recv_line() {
        $line = '';
        while (($pos = strpos($this->_buffer, "\n")) === false) {
            $chunk = @fread($this->socket, 4096);
            if ($chunk === false || $chunk === '') {
                if (feof($this->socket)) {
                    $this->error = '连接已断开';
                    return null;
                }
                usleep(10000);
                continue;
            }
            $this->_buffer .= $chunk;
        }
        $line = substr($this->_buffer, 0, $pos);
        $this->_buffer = substr($this->_buffer, $pos + 1);
        return json_decode(trim($line), true);
    }

    /**
     * 存储文件到服务端
     * @param string $localFilePath 本地文件路径
     * @param string $fileName 原始文件名
     * @param string $mimeType MIME类型
     * @param int $userId 用户ID
     * @return array|null 服务端响应 ['code'=>1, 'stored_path'=>..., 'file_id'=>...] 或 null
     */
    public function store_server_file($localFilePath, $fileName, $mimeType, $userId) {
        if (!file_exists($localFilePath)) {
            $this->error = '本地文件不存在: ' . $localFilePath;
            return null;
        }
        
        $fileSize = filesize($localFilePath);
        if ($fileSize <= 0) {
            $this->error = '文件大小为0';
            return null;
        }
        
        // 发送 JSON 头
        $header = json_encode([
            'action' => 'store_file',
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'user_id' => $userId,
        ], JSON_UNESCAPED_UNICODE) . "\n";
        
        if (!$this->send_raw($header)) {
            $this->error = '发送文件头失败';
            return null;
        }
        
        // 发送二进制文件数据（分块发送，避免内存问题）
        $handle = fopen($localFilePath, 'rb');
        if (!$handle) {
            $this->error = '无法打开本地文件';
            // 发送空数据以完成协议
            $this->send_raw('');
            return null;
        }
        
        $totalSent = 0;
        while (!feof($handle)) {
            $chunk = fread($handle, 65536); // 64KB 块
            if ($chunk === false) break;
            $sent = $this->send_raw($chunk);
            if ($sent === false || $sent === 0) {
                fclose($handle);
                $this->error = '发送文件数据失败';
                return null;
            }
            $totalSent += $sent;
        }
        fclose($handle);
        
        // 读取服务端响应
        $response = $this->recv_line();
        return $response;
    }

    /**
     * 从服务端获取文件
     * @param string $storedName 服务端存储的文件名
     * @return array|null ['file_data'=>string, 'file_name'=>string, 'file_size'=>int, 'mime_type'=>string] 或 null
     */
    public function get_server_file($storedName) {
        // 发送请求
        $request = json_encode([
            'action' => 'get_file',
            'file_name' => $storedName,
        ], JSON_UNESCAPED_UNICODE) . "\n";
        
        if (!$this->send_raw($request)) {
            $this->error = '发送文件请求失败';
            return null;
        }
        
        // 读取 JSON 响应头
        $header = $this->recv_line();
        if ($header === null) {
            $this->error = '读取文件响应头失败';
            return null;
        }
        
        if (($header['code'] ?? 0) !== 1) {
            $this->error = $header['error'] ?? '文件不存在';
            return null;
        }
        
        // 读取二进制文件数据
        $fileSize = intval($header['file_size'] ?? 0);
        $fileData = $this->recv_raw($fileSize);
        
        if ($fileData === false) {
            return null;
        }
        
        return [
            'file_data' => $fileData,
            'file_name' => $header['file_name'] ?? $storedName,
            'file_size' => $fileSize,
            'mime_type' => $header['mime_type'] ?? 'application/octet-stream',
        ];
    }

    /**
     * 删除服务端文件
     * @param string $storedName 服务端存储的文件名
     * @return array|null 响应
     */
    public function delete_server_file($storedName) {
        $request = json_encode([
            'action' => 'delete_server_file',
            'file_name' => $storedName,
        ], JSON_UNESCAPED_UNICODE) . "\n";
        
        if (!$this->send_raw($request)) {
            $this->error = '发送删除请求失败';
            return null;
        }
        
        return $this->recv_line();
    }
}

// ============================================================
// 查询结果类
// ============================================================
class TcpResult {
    private $_rows = [];
    private $_columns = [];
    private $_position = 0;
    public $num_rows = 0;

    public function __construct($rows, $columns, $num_rows) {
        $this->_rows = $rows;
        $this->_columns = $columns;
        $this->num_rows = $num_rows;
    }

    /** 获取关联数组行 */
    public function fetch_assoc() {
        if ($this->_position >= count($this->_rows)) return null;
        $row = $this->_rows[$this->_position++];
        $assoc = [];
        foreach ($this->_columns as $i => $col) $assoc[$col] = $row[$i] ?? null;
        return $assoc;
    }

    /** 获取数字索引行 */
    public function fetch_row() {
        if ($this->_position >= count($this->_rows)) return null;
        return $this->_rows[$this->_position++];
    }

    /** 获取数组行（同时有数字和关联索引） */
    public function fetch_array() {
        if ($this->_position >= count($this->_rows)) return null;
        $row = $this->_rows[$this->_position++];
        $result = [];
        foreach ($this->_columns as $i => $col) {
            $result[$i] = $row[$i] ?? null;
            $result[$col] = $row[$i] ?? null;
        }
        return $result;
    }

    /** 释放结果 */
    public function free() { $this->_rows = []; $this->_position = 0; }

    /** 获取字段信息 */
    public function fetch_fields() {
        $fields = [];
        foreach ($this->_columns as $col) $fields[] = (object)['name' => $col];
        return $fields;
    }
}

// ============================================================
// 兼容函数（替代 mysqli_* 函数，在所有 PHP 文件中替换即可）
// ============================================================
function tcp_connect($host = '127.0.0.1', $port = 9527) { return new TcpConnection($host, $port); }
function tcp_query($conn, $sql) { return $conn->query($sql); }
function tcp_fetch_assoc($result) { return ($result && $result !== false) ? $result->fetch_assoc() : null; }
function tcp_fetch_row($result) { return ($result && $result !== false) ? $result->fetch_row() : null; }
function tcp_fetch_array($result) { return ($result && $result !== false) ? $result->fetch_array() : null; }
function tcp_num_rows($result) { return ($result && $result !== false) ? $result->num_rows : 0; }
function tcp_real_escape_string($conn, $value) { return $conn->real_escape_string($value); }
function tcp_insert_id($conn) { return $conn->insert_id; }
function tcp_error($conn) { return $conn->error; }
function tcp_set_charset($conn, $charset) { return $conn->set_charset($charset); }
function tcp_affected_rows($conn) { return $conn->affected_rows; }
function tcp_multi_query($conn, $sql) {
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) { $conn->query($stmt); }
    return true;
}
function tcp_free_result($result) { $result->free(); }
function tcp_close($conn) { $conn->close(); }

/**
 * 存储文件到服务端（通过 TCP 二进制传输）
 * @param TcpConnection $conn TCP 连接
 * @param string $localFilePath 本地文件路径
 * @param string $fileName 原始文件名
 * @param string $mimeType MIME类型
 * @param int $userId 用户ID
 * @return array|null 服务端响应
 */
function tcp_store_file($conn, $localFilePath, $fileName, $mimeType, $userId) {
    return $conn->store_server_file($localFilePath, $fileName, $mimeType, $userId);
}

/**
 * 从服务端获取文件（通过 TCP 二进制传输）
 * @param TcpConnection $conn TCP 连接
 * @param string $storedName 服务端存储的文件名
 * @return array|null 文件数据
 */
function tcp_get_file($conn, $storedName) {
    return $conn->get_server_file($storedName);
}

/**
 * 删除服务端文件
 * @param TcpConnection $conn TCP 连接
 * @param string $storedName 服务端存储的文件名
 * @return array|null 响应
 */
function tcp_delete_server_file($conn, $storedName) {
    return $conn->delete_server_file($storedName);
}