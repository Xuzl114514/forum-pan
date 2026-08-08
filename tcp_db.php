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
        if ($resp === null) { $this->error = '响应解析失败'; return false; }
        if ($resp['code'] === 0) { $this->error = $resp['error']; return false; }
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
function tcp_fetch_assoc($result) { return $result->fetch_assoc(); }
function tcp_fetch_row($result) { return $result->fetch_row(); }
function tcp_fetch_array($result) { return $result->fetch_array(); }
function tcp_num_rows($result) { return $result->num_rows; }
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