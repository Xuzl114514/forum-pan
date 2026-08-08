<?php
include '../config.php';

$uid = isset($_SESSION['uid']) ? intval($_SESSION['uid']) : 0;
if ($uid === 0) {
    http_response_code(403);
    exit('Forbidden');
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Not Found');
}

$res = tcp_query($conn, "SELECT * FROM files WHERE id=$id AND user_id=$uid LIMIT 1");
if (!$res || tcp_num_rows($res) == 0) {
    if ($uid > 0 && isset($_SESSION['role']) && intval($_SESSION['role']) === 1) {
        $res = tcp_query($conn, "SELECT * FROM files WHERE id=$id LIMIT 1");
    }
    if (!$res || tcp_num_rows($res) == 0) {
        http_response_code(404);
        exit('File Not Found');
    }
}

$file = tcp_fetch_assoc($res);
$filePath = dirname(dirname(__DIR__)) . '/' . $file['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File Not Found on Disk');
}

$mimeType = $file['file_type'];
if (empty($mimeType)) {
    $mimeType = 'application/octet-stream';
}

$download = isset($_GET['download']) && $_GET['download'] == '1';
$fileSize = $file['file_size'];

header('Content-Type: ' . $mimeType);
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . rawurlencode($file['file_name']) . '"');
header('Cache-Control: private, max-age=0');
header('Accept-Ranges: bytes');

if (isset($_SERVER['HTTP_RANGE']) && !$download) {
    $range = $_SERVER['HTTP_RANGE'];
    if (preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
        $start = intval($matches[1]);
        $end = intval($matches[2]);
        if ($end == 0 || $end >= $fileSize) $end = $fileSize - 1;
        if ($start > $end || $start >= $fileSize) {
            header('HTTP/1.1 416 Range Not Satisfiable');
            header('Content-Range: bytes */' . $fileSize);
            exit;
        }
        $length = $end - $start + 1;
        header('HTTP/1.1 206 Partial Content');
        header('Content-Length: ' . $length);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
        $handle = fopen($filePath, 'rb');
        if ($handle) {
            fseek($handle, $start);
            $buffer = 8192;
            $remaining = $length;
            while ($remaining > 0 && !feof($handle)) {
                $read = min($buffer, $remaining);
                echo fread($handle, $read);
                $remaining -= $read;
                if (connection_status() != CONNECTION_NORMAL) {
                    break;
                }
            }
            fclose($handle);
        }
        exit;
    }
}

header('Content-Length: ' . $fileSize);
readfile($filePath);
