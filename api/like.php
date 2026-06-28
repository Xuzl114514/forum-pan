<?php
/**
 * 点赞 API
 * 
 * 功能：切换点赞状态（点赞/取消点赞）
 * 
 * 请求参数：
 * - act=toggle：切换点赞状态
 * - type：点赞类型（post/comment）
 * - id：目标ID
 * 
 * 返回值：
 * - code: 1=成功，0=失败
 * - msg: 提示信息
 * - action: like=点赞，unlike=取消点赞
 * - like_num: 当前点赞数
 */

include '../config.php';

header('Content-Type: application/json');

// 登录验证
if (!isset($_SESSION['uid'])) {
    echo json_encode(['code' => 0, 'msg' => '请先登录']);
    exit;
}

$uid = intval($_SESSION['uid']);
$act = $_GET['act'] ?? 'toggle';
$id = intval($_GET['id'] ?? 0);
$type = $_GET['type'] ?? '';

// 参数验证
if ($id <= 0 || !in_array($type, ['post', 'comment'])) {
    echo json_encode(['code' => 0, 'msg' => '无效参数']);
    exit;
}

// 检查目标是否存在
if ($type === 'post') {
    $target = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, like_num FROM posts WHERE id = $id"));
} else {
    $target = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, like_num FROM comments WHERE id = $id"));
}

if (!$target) {
    echo json_encode(['code' => 0, 'msg' => '目标不存在']);
    exit;
}

// ========== 切换点赞状态 ==========
if ($act === 'toggle') {
    // 检查是否已点赞（使用统一的 likes 表）
    $check = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT id FROM likes WHERE user_id = $uid AND type = '$type' AND target_id = $id"
    ));

    if ($check) {
        // 取消点赞
        mysqli_query($conn, "DELETE FROM likes WHERE id = {$check['id']}");
        // 更新点赞数，使用 GREATEST 防止负数
        mysqli_query($conn, "UPDATE {$type}s SET like_num = GREATEST(0, like_num - 1) WHERE id = $id");
        $newNum = max(0, $target['like_num'] - 1);
        echo json_encode(['code' => 1, 'msg' => '已取消点赞', 'action' => 'unlike', 'like_num' => $newNum]);
    } else {
        // 点赞
        mysqli_query($conn, "INSERT INTO likes(user_id, type, target_id) VALUES($uid, '$type', $id)");
        mysqli_query($conn, "UPDATE {$type}s SET like_num = like_num + 1 WHERE id = $id");
        $newNum = $target['like_num'] + 1;
        echo json_encode(['code' => 1, 'msg' => '点赞成功', 'action' => 'like', 'like_num' => $newNum]);
    }
    exit;
}

// ========== 检查点赞状态 ==========
if ($act === 'check') {
    $check = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id FROM likes WHERE user_id = $uid AND type = '$type' AND target_id = $id"
    ));
    echo json_encode(['code' => 1, 'liked' => $check ? 1 : 0]);
    exit;
}

echo json_encode(['code' => 0, 'msg' => '无效操作']);
?>