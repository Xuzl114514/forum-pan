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
$uid = $_SESSION['uid'];

// 获取可选用户列表（用于创建群聊）
if($act == 'get_selectable_users'){
    $sql = "SELECT id, username, nickname, avatar FROM users WHERE id != $uid AND status = 1 ORDER BY id DESC";
    
    $res = tcp_query($conn, $sql);
    $users = [];
    while ($row = tcp_fetch_assoc($res)) {
        $users[] = $row;
    }
    
    echo json_encode(['code'=>1, 'users'=>$users]);
    exit;
}

// 创建群聊
	if($act == 'create'){
	    $name = trim($_POST['name']);
	    $member_ids = $_POST['member_ids'] ?? [];
	    
	    if (empty($name)) {
	        echo json_encode(['code'=>0, 'msg'=>'请输入群聊名称']);
	        exit;
	    }
	    
	    $nameEscaped = tcp_real_escape_string($conn, $name);
	    // 创建群聊
	    tcp_query($conn, "INSERT INTO group_chats(name, creator_id) VALUES('$nameEscaped', '$uid')");
    $group_id = tcp_insert_id($conn);
    
    // 添加创建者为成员
    tcp_query($conn, "INSERT INTO group_members(group_id, user_id) VALUES('$group_id', '$uid')");
    
    // 添加其他成员
    foreach ($member_ids as $member_id) {
        $member_id = intval($member_id);
        if ($member_id != $uid) {
            tcp_query($conn, "INSERT INTO group_members(group_id, user_id) VALUES('$group_id', '$member_id')");
        }
    }
    
    echo json_encode(['code'=>1, 'msg'=>'群聊创建成功', 'group_id'=>$group_id]);
    exit;
}

// 获取我的群聊列表
if($act == 'get_groups'){
    $sql = "SELECT g.id, g.name, g.creator_id, u.username AS creator_username, u.nickname AS creator_nickname,
            (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS member_count,
            (SELECT COUNT(*) FROM group_messages WHERE group_id = g.id AND sender_id != $uid AND id > (
                SELECT COALESCE((SELECT id FROM group_messages WHERE group_id = g.id ORDER BY id DESC LIMIT 1), 0) - 50
            )) AS unread_count
            FROM group_chats g
            LEFT JOIN users u ON g.creator_id = u.id
            WHERE g.id IN (SELECT group_id FROM group_members WHERE user_id = $uid)
            ORDER BY (SELECT MAX(id) FROM group_messages WHERE group_id = g.id) DESC";
    
    $res = tcp_query($conn, $sql);
    $groups = [];
    while ($row = tcp_fetch_assoc($res)) {
        $groups[] = $row;
    }
    
    echo json_encode(['code'=>1, 'groups'=>$groups]);
    exit;
}

// 获取群聊成员
if($act == 'get_members'){
    $group_id = intval($_GET['group_id']);
    
    $sql = "SELECT u.id, u.username, u.nickname, u.avatar
            FROM group_members gm
            LEFT JOIN users u ON gm.user_id = u.id
            WHERE gm.group_id = $group_id";
    
    $res = tcp_query($conn, $sql);
    $members = [];
    while ($row = tcp_fetch_assoc($res)) {
        $members[] = $row;
    }
    
    echo json_encode(['code'=>1, 'members'=>$members]);
    exit;
}

// 获取群聊历史消息
if($act == 'get_history'){
    $group_id = intval($_GET['group_id']);
    
    // 群聊消息自动标记为已读（实际上群聊没有已读状态，这里只是为了清除未读计数）
    // 我们通过在获取历史消息后不再显示未读红点来实现
    
    $sql = "SELECT gm.*, u.username AS sender_username, u.nickname AS sender_nickname, u.avatar AS sender_avatar,
            a.file_name, a.file_path, a.file_type, a.file_size
            FROM group_messages gm
            LEFT JOIN users u ON gm.sender_id = u.id
            LEFT JOIN attachments a ON gm.attachment_id = a.id
            WHERE gm.group_id = $group_id
            ORDER BY gm.id ASC";
    
    $res = tcp_query($conn, $sql);
    $messages = [];
    while ($row = tcp_fetch_assoc($res)) {
        $messages[] = $row;
    }
    
    // 标记这些消息为已读
    foreach ($messages as $msg) {
        if ($msg['sender_id'] != $uid) {
            tcp_query($conn, "INSERT IGNORE INTO message_read_status(user_id, message_id, message_type) VALUES($uid, " . intval($msg['id']) . ", 'group')");
        }
    }
    
    // 获取群聊信息
    $group_res = tcp_query($conn, "SELECT id, name FROM group_chats WHERE id = $group_id");
    $group = tcp_fetch_assoc($group_res);
    
    echo json_encode(['code'=>1, 'messages'=>$messages, 'group'=>$group]);
    exit;
}

// 获取群聊新消息
if($act == 'get_new_messages'){
    $group_id = intval($_GET['group_id']);
    $last_id = intval($_GET['last_id'] ?? 0);
    
    $sql = "SELECT gm.*, u.username AS sender_username, u.nickname AS sender_nickname, u.avatar AS sender_avatar,
            a.file_name, a.file_path, a.file_type, a.file_size
            FROM group_messages gm
            LEFT JOIN users u ON gm.sender_id = u.id
            LEFT JOIN attachments a ON gm.attachment_id = a.id
            WHERE gm.group_id = $group_id AND gm.id > $last_id
            ORDER BY gm.id ASC";
    
    $res = tcp_query($conn, $sql);
    $messages = [];
    while ($row = tcp_fetch_assoc($res)) {
        $messages[] = $row;
    }
    
    // 收到消息自动标记已读
    if (!empty($messages)) {
        foreach ($messages as $msg) {
            if ($msg['sender_id'] != $uid) {
                tcp_query($conn, "INSERT IGNORE INTO message_read_status(user_id, message_id, message_type) VALUES($uid, " . intval($msg['id']) . ", 'group')");
            }
        }
    }
    
    echo json_encode(['code'=>1, 'messages'=>$messages]);
    exit;
}

// 发送群聊消息
	if($act == 'send'){
	    $group_id = intval($_POST['group_id']);
	    $content = trim($_POST['content']);
	    $attachment_id = intval($_POST['attachment_id'] ?? 0);
	    
	    if (empty($content) && $attachment_id == 0) {
	        echo json_encode(['code'=>0, 'msg'=>'请输入消息内容或上传附件']);
	        exit;
	    }
	    
	    $contentEscaped = tcp_real_escape_string($conn, $content);
	    tcp_query($conn, "INSERT INTO group_messages(group_id, sender_id, content, attachment_id) 
	                        VALUES('$group_id', '$uid', '$contentEscaped', '$attachment_id')");
    $message_id = tcp_insert_id($conn);
    
    // 发送后自动标记发送人已读
    tcp_query($conn, "INSERT IGNORE INTO message_read_status(user_id, message_id, message_type) VALUES($uid, $message_id, 'group')");
    
    // 获取插入的消息
    $sql = "SELECT gm.*, u.username AS sender_username, u.nickname AS sender_nickname, u.avatar AS sender_avatar,
            a.file_name, a.file_path, a.file_type, a.file_size
            FROM group_messages gm
            LEFT JOIN users u ON gm.sender_id = u.id
            LEFT JOIN attachments a ON gm.attachment_id = a.id
            WHERE gm.id = $message_id";
    $res = tcp_query($conn, $sql);
    $message = tcp_fetch_assoc($res);
    
    echo json_encode(['code'=>1, 'msg'=>'发送成功', 'message'=>$message]);
    exit;
}

// 获取群聊未读消息数
if($act == 'get_unread_count'){
    $sql = "SELECT COUNT(*) AS cnt FROM group_messages gm
            WHERE gm.group_id IN (SELECT group_id FROM group_members WHERE user_id = $uid)
            AND gm.sender_id != $uid";
    
    $res = tcp_query($conn, $sql);
    $row = tcp_fetch_assoc($res);
    $count = intval($row['cnt']);
    
    echo json_encode(['code'=>1, 'count'=>$count]);
    exit;
}

// 撤回群聊消息
if($act == 'recall'){
    $message_id = intval($_GET['id']);
    $uid = intval($_SESSION['uid']);
    $role = intval($_SESSION['role']);
    
    // 检查消息是否存在
    $message = tcp_fetch_assoc(tcp_query($conn, "SELECT sender_id, is_recalled, create_time FROM group_messages WHERE id = $message_id"));
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
        // 既不是管理员也不是发送者
        echo json_encode(['code'=>0, 'msg'=>'没有权限撤回该消息']);
        exit;
    }
    
    // 执行撤回
    tcp_query($conn, "UPDATE group_messages SET is_recalled = 1, recall_time = NOW() WHERE id = $message_id");
    
    echo json_encode(['code'=>1, 'msg'=>'消息已撤回']);
    exit;
}

// 解散群聊
if($act == 'dismiss'){
    $group_id = intval($_GET['group_id']);
    $uid = $_SESSION['uid'];
    $role = intval($_SESSION['role']);
    
    // 获取群聊信息
    $group = tcp_fetch_assoc(tcp_query($conn, "SELECT creator_id FROM group_chats WHERE id = $group_id"));
    if (!$group) {
        echo json_encode(['code'=>0, 'msg'=>'群聊不存在']);
        exit;
    }
    
    // 检查权限：只有管理员或群主可以解散群
    if ($role !== 1 && intval($group['creator_id']) != $uid) {
        echo json_encode(['code'=>0, 'msg'=>'没有权限解散该群聊']);
        exit;
    }
    
    // 删除群聊成员
    tcp_query($conn, "DELETE FROM group_members WHERE group_id = $group_id");
    
    // 删除群聊消息
    tcp_query($conn, "DELETE FROM group_messages WHERE group_id = $group_id");
    
    // 删除群聊
    tcp_query($conn, "DELETE FROM group_chats WHERE id = $group_id");
    
    echo json_encode(['code'=>1, 'msg'=>'群聊已解散']);
    exit;
}
?>
