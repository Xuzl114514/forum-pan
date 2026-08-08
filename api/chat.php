<?php include '../config.php';
$act = $_GET['act'] ?? '';

header('Content-Type: application/json');

function apiIsLogin() {
    global $conn;
    if (!isset($_SESSION['uid'])) {
        echo json_encode(['code' => 0, 'msg' => '请先登录']);
        exit;
    }
    $uid = intval($_SESSION['uid']);
    $res = tcp_query($conn, "SELECT username,nickname,role,status FROM users WHERE id=$uid LIMIT 1");
    $row = tcp_fetch_assoc($res);
    if (!$row || intval($row['status']) !== 1) {
        $_SESSION = [];
        session_destroy();
        echo json_encode(['code' => 0, 'msg' => '账号已被禁用']);
        exit;
    }
    $_SESSION['username'] = $row['username'];
    $_SESSION['nickname'] = $row['nickname'];
    $_SESSION['role'] = $row['role'];
}

apiIsLogin();

// 获取用户列表（除了自己）
if($act == 'get_users'){
    $uid = $_SESSION['uid'];
    
    // 获取聊天过的用户，排在前面
    $sql = "SELECT DISTINCT u.id, u.username, u.nickname, u.avatar,
            (SELECT COUNT(*) FROM private_messages pm 
             WHERE ((pm.sender_id = u.id AND pm.receiver_id = $uid) OR (pm.sender_id = $uid AND pm.receiver_id = u.id))
             AND pm.is_read = 0 AND pm.sender_id != $uid) AS unread_count
            FROM users u 
            WHERE u.id != $uid 
            ORDER BY unread_count DESC, u.id DESC";
    
    $res = tcp_query($conn, $sql);
    $users = [];
    while ($row = tcp_fetch_assoc($res)) {
        $users[] = $row;
    }
    
    echo json_encode(['code'=>1, 'users'=>$users]);
    exit;
}

// 获取与某用户的聊天历史
if($act == 'get_history'){
    $uid = $_SESSION['uid'];
    $other_uid = intval($_GET['other_uid']);
    
    // 标记消息为已读
    tcp_query($conn, "UPDATE private_messages SET is_read = 1 
                        WHERE sender_id = $other_uid AND receiver_id = $uid AND is_read = 0");
    
    // 获取聊天记录
    $sql = "SELECT pm.*, 
            u1.username AS sender_username, u1.nickname AS sender_nickname, u1.avatar AS sender_avatar,
            u2.username AS receiver_username, u2.nickname AS receiver_nickname, u2.avatar AS receiver_avatar,
            a.file_name, a.file_path, a.file_type, a.file_size
            FROM private_messages pm
            LEFT JOIN users u1 ON pm.sender_id = u1.id
            LEFT JOIN users u2 ON pm.receiver_id = u2.id
            LEFT JOIN attachments a ON pm.attachment_id = a.id
            WHERE (pm.sender_id = $uid AND pm.receiver_id = $other_uid) 
               OR (pm.sender_id = $other_uid AND pm.receiver_id = $uid)
            ORDER BY pm.id ASC";
    
    $res = tcp_query($conn, $sql);
    $messages = [];
    while ($row = tcp_fetch_assoc($res)) {
        $messages[] = $row;
    }
    
    // 标记这些消息为已读
    foreach ($messages as $msg) {
        if ($msg['sender_id'] != $uid) {
            tcp_query($conn, "INSERT IGNORE INTO message_read_status(user_id, message_id, message_type) VALUES($uid, " . intval($msg['id']) . ", 'private')");
        }
    }
    
    // 获取对方用户信息
    $other_user_res = tcp_query($conn, "SELECT id, username, nickname, avatar FROM users WHERE id = $other_uid");
    $other_user = tcp_fetch_assoc($other_user_res);
    
    echo json_encode(['code'=>1, 'messages'=>$messages, 'other_user'=>$other_user]);
    exit;
}

// 获取新消息
if($act == 'get_new_messages'){
    $uid = $_SESSION['uid'];
    $other_uid = intval($_GET['other_uid']);
    $last_id = intval($_GET['last_id'] ?? 0);
    
    // 标记为已读
    tcp_query($conn, "UPDATE private_messages SET is_read = 1 
                        WHERE sender_id = $other_uid AND receiver_id = $uid AND is_read = 0");
    
    // 获取新消息
    $sql = "SELECT pm.*, 
            u1.username AS sender_username, u1.nickname AS sender_nickname, u1.avatar AS sender_avatar,
            u2.username AS receiver_username, u2.nickname AS receiver_nickname, u2.avatar AS receiver_avatar,
            a.file_name, a.file_path, a.file_type, a.file_size
            FROM private_messages pm
            LEFT JOIN users u1 ON pm.sender_id = u1.id
            LEFT JOIN users u2 ON pm.receiver_id = u2.id
            LEFT JOIN attachments a ON pm.attachment_id = a.id
            WHERE ((pm.sender_id = $uid AND pm.receiver_id = $other_uid) 
                OR (pm.sender_id = $other_uid AND pm.receiver_id = $uid))
            AND pm.id > $last_id
            ORDER BY pm.id ASC";
    
    $res = tcp_query($conn, $sql);
    $messages = [];
    while ($row = tcp_fetch_assoc($res)) {
        $messages[] = $row;
    }
    
    // 收到消息自动标记已读
    if (!empty($messages)) {
        foreach ($messages as $msg) {
            if ($msg['sender_id'] != $uid) {
                tcp_query($conn, "INSERT IGNORE INTO message_read_status(user_id, message_id, message_type) VALUES($uid, " . intval($msg['id']) . ", 'private')");
            }
        }
    }
    
    echo json_encode(['code'=>1, 'messages'=>$messages]);
    exit;
}

// 发送消息
		if($act == 'send'){
		    $uid = $_SESSION['uid'];
		    $receiver_id = intval($_POST['receiver_id']);
		    $content = trim($_POST['content']);
		    
		    if (empty($content)) {
		        echo json_encode(['code'=>0, 'msg'=>'请输入消息内容']);
		        exit;
		    }
		    
		    // 敏感词过滤
		    $filterResult = filterSensitive($content, $conn);
		    if ($filterResult['blocked']) {
		        echo json_encode(['code' => 0, 'msg' => '消息包含敏感词「' . $filterResult['word'] . '」，无法发送']);
		        exit;
		    }
		    $content = $filterResult['content'];
		    
		    $contentEscaped = tcp_real_escape_string($conn, $content);
	    tcp_query($conn, "INSERT INTO private_messages(sender_id, receiver_id, content) 
	                        VALUES('$uid', '$receiver_id', '$contentEscaped')");
    $message_id = tcp_insert_id($conn);
    
    // 发送后自动标记发送人已读
    tcp_query($conn, "INSERT IGNORE INTO message_read_status(user_id, message_id, message_type) VALUES($uid, $message_id, 'private')");
    
    // 获取插入的消息
    $sql = "SELECT pm.*, 
            u1.username AS sender_username, u1.nickname AS sender_nickname,
            u2.username AS receiver_username, u2.nickname AS receiver_nickname
            FROM private_messages pm
            LEFT JOIN users u1 ON pm.sender_id = u1.id
            LEFT JOIN users u2 ON pm.receiver_id = u2.id
            WHERE pm.id = $message_id";
    $res = tcp_query($conn, $sql);
    $message = tcp_fetch_assoc($res);
    
    echo json_encode(['code'=>1, 'msg'=>'发送成功', 'message'=>$message]);
    exit;
}

// 获取未读消息总数
if($act == 'get_unread_count'){
    $uid = $_SESSION['uid'];
    
    $res = tcp_query($conn, "SELECT COUNT(*) AS cnt FROM private_messages WHERE receiver_id = $uid AND is_read = 0");
    $row = tcp_fetch_assoc($res);
    $count = intval($row['cnt']);
    
    echo json_encode(['code'=>1, 'count'=>$count]);
    exit;
}

// 撤回私聊消息
if($act == 'recall'){
    $message_id = intval($_GET['id']);
    $uid = intval($_SESSION['uid']);
    $role = intval($_SESSION['role']);
    
    // 检查消息是否存在
    $message = tcp_fetch_assoc(tcp_query($conn, "SELECT sender_id, is_recalled, create_time FROM private_messages WHERE id = $message_id"));
    if (!$message) {
        echo json_encode(['code'=>0, 'msg'=>'消息不存在']);
        exit;
    }
    
    // 检查是否已撤回
    if (intval($message['is_recalled']) === 1) {
        echo json_encode(['code'=>0, 'msg'=>'消息已被撤回']);
        exit;
    }
    
    // 检查权限
    $sender_id = intval($message['sender_id']);
    $is_sender = ($sender_id === $uid);
    
    // 管理员可以撤回任何消息
    if ($role === 1) {
        // 管理员无限制
    } else if ($is_sender) {
        // 普通用户只能撤回自己 3 分钟内的消息
        $now = time();
        $create_time = strtotime($message['create_time']);
        if ($now - $create_time > 180) {
            echo json_encode(['code'=>0, 'msg'=>'只能撤回 3 分钟内的消息']);
            exit;
        }
    } else {
        echo json_encode(['code'=>0, 'msg'=>'没有权限撤回该消息']);
        exit;
    }
    
    // 执行撤回
    tcp_query($conn, "UPDATE private_messages SET is_recalled = 1, recall_time = NOW() WHERE id = $message_id");
    
    echo json_encode(['code'=>1, 'msg'=>'消息已撤回']);
    exit;
}
?>
