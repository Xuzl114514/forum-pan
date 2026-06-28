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
    $res = mysqli_query($conn, "SELECT username,nickname,role,status FROM users WHERE id=$uid LIMIT 1");
    $row = mysqli_fetch_assoc($res);
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

function apiIsAdmin() {
    if (intval($_SESSION['role']) !== 1) {
        echo json_encode(['code' => 0, 'msg' => '无权限']);
        exit;
    }
}

function filterSensitive($content, $conn) {
    $res = mysqli_query($conn, "SELECT word, level FROM sensitive_words");
    while ($row = mysqli_fetch_assoc($res)) {
        if (strpos($content, $row['word']) !== false) {
            if ($row['level'] == 2) {
                return ['blocked' => true, 'word' => $row['word']];
            }
            $content = str_replace($row['word'], '***', $content);
        }
    }
    return ['blocked' => false, 'content' => $content];
}

apiIsLogin();

// 发帖
if($act == 'add'){
    $uid = $_SESSION['uid'];
    $title = $_POST['title'];
    $content = $_POST['content'];
    mysqli_query($conn,"INSERT INTO posts(user_id,title,content) VALUES('$uid','$title','$content')");
    echo json_encode(['code'=>1, 'msg'=>'发布成功']);
    exit;
}

// 回复
if($act == 'comment'){
    $uid = $_SESSION['uid'];
    $post_id = intval($_GET['id']);
    $content = $_POST['content'];
    
    // 敏感词过滤
    $filterResult = filterSensitive($content, $conn);
    if ($filterResult['blocked']) {
        echo json_encode(['code' => 0, 'msg' => '内容包含敏感词「' . $filterResult['word'] . '」，无法发布']);
        exit;
    }
    $content = $filterResult['content'];
    
    mysqli_query($conn,"INSERT INTO comments(post_id,user_id,content) VALUES('$post_id','$uid','$content')");
    
    // 评论通知：通知帖子作者
    $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM posts WHERE id=$post_id"));
    $displayName = $_SESSION['nickname'] ?: $_SESSION['username'];
    if ($post && $post['user_id'] != $uid) {
        mysqli_query($conn, "INSERT INTO notifications(user_id, type, content, source_id, source_type) VALUES(".$post['user_id'].", 'comment', '".mysqli_real_escape_string($conn, $displayName)." 评论了你的帖子', $post_id, 'post')");
    }
    
    echo json_encode(['code'=>1, 'msg'=>'评论成功']);
    exit;
}

// 帖子点赞
if($act == 'like'){
    $uid = $_SESSION['uid'];
    $id = intval($_GET['id']);
    $type = $_GET['type'] ?? 'post';
    
    // 检查是否已点赞
    $check = mysqli_query($conn,"SELECT id FROM likes WHERE user_id=$uid AND type='$type' AND target_id=$id");
    if(mysqli_num_rows($check) > 0){
        echo json_encode(['code'=>0, 'msg'=>'您已经点过赞了']);
        exit;
    }
    
    // 获取目标所有者
    if ($type == 'post') {
        $targetRes = mysqli_query($conn, "SELECT user_id FROM posts WHERE id=$id");
    } else {
        $targetRes = mysqli_query($conn, "SELECT user_id FROM comments WHERE id=$id");
    }
    $target = mysqli_fetch_assoc($targetRes);
    
    // 记录点赞
    mysqli_query($conn,"INSERT INTO likes(user_id,type,target_id) VALUES('$uid','$type','$id')");
    
    // 更新点赞数
    if($type == 'post'){
        mysqli_query($conn,"UPDATE posts SET like_num=like_num+1 WHERE id=$id");
    } else {
        mysqli_query($conn,"UPDATE comments SET like_num=like_num+1 WHERE id=$id");
    }
    
    // 点赞通知
    $displayName = $_SESSION['nickname'] ?: $_SESSION['username'];
    if ($target && $target['user_id'] != $uid) {
        if ($type === 'post') {
            mysqli_query($conn, "INSERT INTO notifications(user_id, type, content, source_id, source_type) VALUES(".$target['user_id'].", 'like', '".mysqli_real_escape_string($conn, $displayName)." 点赞了你的帖子', $id, 'post')");
        } else {
            mysqli_query($conn, "INSERT INTO notifications(user_id, type, content, source_id, source_type) VALUES(".$target['user_id'].", 'like', '".mysqli_real_escape_string($conn, $displayName)." 点赞了你的评论', $id, 'comment')");
        }
    }
    
    echo json_encode(['code'=>1, 'msg'=>'点赞成功']);
    exit;
}

// 取消点赞
if($act == 'unlike'){
    $uid = $_SESSION['uid'];
    $id = intval($_GET['id']);
    $type = $_GET['type'] ?? 'post';
    
    mysqli_query($conn,"DELETE FROM likes WHERE user_id=$uid AND type='$type' AND target_id=$id");
    
    if($type == 'post'){
        mysqli_query($conn,"UPDATE posts SET like_num=GREATEST(like_num-1,0) WHERE id=$id");
    } else {
        mysqli_query($conn,"UPDATE comments SET like_num=GREATEST(like_num-1,0) WHERE id=$id");
    }
    
    echo json_encode(['code'=>1, 'msg'=>'取消点赞']);
    exit;
}

// 获取点赞状态
if($act == 'check_like'){
    $uid = $_SESSION['uid'];
    $id = intval($_GET['id']);
    $type = $_GET['type'] ?? 'post';
    
    $check = mysqli_query($conn,"SELECT id FROM likes WHERE user_id=$uid AND type='$type' AND target_id=$id");
    $liked = mysqli_num_rows($check) > 0 ? 1 : 0;
    
    echo json_encode(['code'=>1, 'liked'=>$liked]);
    exit;
}

// 获取帖子最新评论
if($act == 'get_new_comments'){
    $post_id = intval($_GET['post_id']);
    $last_id = intval($_GET['last_id'] ?? 0);
    
    $sql = "SELECT c.*, u.username, u.nickname 
            FROM comments c 
            LEFT JOIN users u ON c.user_id = u.id 
            WHERE c.post_id = $post_id AND c.id > $last_id AND c.is_recalled = 0
            ORDER BY c.id ASC";
    $res = mysqli_query($conn, $sql);
    $comments = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $comments[] = $row;
    }
    
    echo json_encode(['code'=>1, 'comments'=>$comments]);
    exit;
}

// 撤回帖子
if($act == 'recall_post'){
    $post_id = intval($_GET['id']);
    $uid = intval($_SESSION['uid']);
    $role = intval($_SESSION['role']);
    
    // 检查帖子是否存在
    $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, is_recalled FROM posts WHERE id = $post_id"));
    if (!$post) {
        echo json_encode(['code'=>0, 'msg'=>'帖子不存在']);
        exit;
    }
    
    // 检查是否已撤回
    if (intval($post['is_recalled']) === 1) {
        echo json_encode(['code'=>0, 'msg'=>'帖子已被撤回']);
        exit;
    }
    
    // 检查权限：管理员可以撤回任何帖子，普通用户只能撤回自己的
    $user_id = intval($post['user_id']);
    if ($role !== 1 && $user_id !== $uid) {
        echo json_encode(['code'=>0, 'msg'=>'没有权限撤回该帖子']);
        exit;
    }
    
    // 执行撤回
    mysqli_query($conn, "UPDATE posts SET is_recalled = 1, recall_time = NOW() WHERE id = $post_id");
    
    echo json_encode(['code'=>1, 'msg'=>'帖子已撤回']);
    exit;
}

// 撤回评论
if($act == 'recall_comment'){
    $comment_id = intval($_GET['id']);
    $uid = intval($_SESSION['uid']);
    $role = intval($_SESSION['role']);
    
    // 检查评论是否存在
    $comment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, is_recalled, create_time FROM comments WHERE id = $comment_id"));
    if (!$comment) {
        echo json_encode(['code'=>0, 'msg'=>'评论不存在']);
        exit;
    }
    
    // 检查是否已撤回
    if (intval($comment['is_recalled']) === 1) {
        echo json_encode(['code'=>0, 'msg'=>'评论已被撤回']);
        exit;
    }
    
    // 检查权限
    $user_id = intval($comment['user_id']);
    if ($role !== 1 && $user_id !== $uid) {
        echo json_encode(['code'=>0, 'msg'=>'没有权限撤回该评论']);
        exit;
    }
    
    // 普通用户只能撤回 3 分钟内的评论
    if ($role !== 1) {
        $now = time();
        $create_time = strtotime($comment['create_time']);
        if ($now - $create_time > 180) { // 3 分钟 = 180 秒
            echo json_encode(['code'=>0, 'msg'=>'只能撤回 3 分钟内的评论']);
            exit;
        }
    }
    
    // 执行撤回
    mysqli_query($conn, "UPDATE comments SET is_recalled = 1, recall_time = NOW() WHERE id = $comment_id");
    
    echo json_encode(['code'=>1, 'msg'=>'评论已撤回']);
    exit;
}

// 删除帖子
if($act == 'delete_post'){
    $post_id = intval($_GET['id']);
    $uid = intval($_SESSION['uid']);
    $role = intval($_SESSION['role']);
    
    // 检查帖子是否存在
    $post = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id FROM posts WHERE id = $post_id"));
    if (!$post) {
        echo json_encode(['code'=>0, 'msg'=>'帖子不存在']);
        exit;
    }
    
    // 检查权限：管理员可以删除任何帖子，普通用户只能删除自己的
    if ($role !== 1 && intval($post['user_id']) !== $uid) {
        echo json_encode(['code'=>0, 'msg'=>'没有权限删除该帖子']);
        exit;
    }
    
    // 删除帖子关联的评论
    mysqli_query($conn, "DELETE FROM comments WHERE post_id = $post_id");
    
    // 删除帖子
    mysqli_query($conn, "DELETE FROM posts WHERE id = $post_id");
    
    echo json_encode(['code'=>1, 'msg'=>'帖子已删除']);
    exit;
}

// ---------- search ----------
if ($act == 'search') {
    $keyword = trim($_GET['keyword'] ?? '');
    if ($keyword === '') {
        echo json_encode(['code' => 0, 'msg' => '请输入关键词']);
        exit;
    }
    $kw = mysqli_real_escape_string($conn, $keyword);
    $res = mysqli_query($conn, "SELECT p.*, u.username, u.nickname, u.avatar FROM posts p LEFT JOIN users u ON p.user_id = u.id WHERE p.title LIKE '%$kw%' OR p.content LIKE '%$kw%' ORDER BY p.is_top DESC, p.id DESC LIMIT 50");
    $posts = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $posts[] = $row;
    }
    echo json_encode(['code' => 1, 'posts' => $posts, 'keyword' => $keyword]);
    exit;
}

// ---------- toggle_top ----------
if ($act == 'toggle_top') {
    apiIsLogin();
    apiIsAdmin();
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['code' => 0, 'msg' => '参数错误']); exit; }
    $res = mysqli_query($conn, "SELECT is_top FROM posts WHERE id=$id LIMIT 1");
    if (!$res || mysqli_num_rows($res) == 0) { echo json_encode(['code' => 0, 'msg' => '帖子不存在']); exit; }
    $row = mysqli_fetch_assoc($res);
    $newVal = $row['is_top'] == 1 ? 0 : 1;
    mysqli_query($conn, "UPDATE posts SET is_top=$newVal WHERE id=$id");
    echo json_encode(['code' => 1, 'msg' => $newVal == 1 ? '已置顶' : '已取消置顶', 'is_top' => $newVal]);
    exit;
}

// ---------- toggle_essence ----------
if ($act == 'toggle_essence') {
    apiIsLogin();
    apiIsAdmin();
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['code' => 0, 'msg' => '参数错误']); exit; }
    $res = mysqli_query($conn, "SELECT is_essence FROM posts WHERE id=$id LIMIT 1");
    if (!$res || mysqli_num_rows($res) == 0) { echo json_encode(['code' => 0, 'msg' => '帖子不存在']); exit; }
    $row = mysqli_fetch_assoc($res);
    $newVal = (intval($row['is_essence'] ?? 0) == 1) ? 0 : 1;
    mysqli_query($conn, "UPDATE posts SET is_essence=$newVal WHERE id=$id");
    echo json_encode(['code' => 1, 'msg' => $newVal == 1 ? '已设为精华' : '已取消精华', 'is_essence' => $newVal]);
    exit;
}

// ---------- notifications ----------
if ($act == 'get_notifications') {
    apiIsLogin();
    $uid = intval($_SESSION['uid']);
    $res = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id=$uid ORDER BY id DESC LIMIT 50");
    $notifs = [];
    while ($row = mysqli_fetch_assoc($res)) { $notifs[] = $row; }
    $unreadRes = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notifications WHERE user_id=$uid AND is_read=0");
    $unread = mysqli_fetch_assoc($unreadRes)['cnt'];
    echo json_encode(['code' => 1, 'notifications' => $notifs, 'unread' => intval($unread)]);
    exit;
}

if ($act == 'mark_read') {
    apiIsLogin();
    $uid = intval($_SESSION['uid']);
    mysqli_query($conn, "UPDATE notifications SET is_read=1 WHERE user_id=$uid AND is_read=0");
    echo json_encode(['code' => 1, 'msg' => '已标记已读']);
    exit;
}

if ($act == 'get_unread_count') {
    apiIsLogin();
    $uid = intval($_SESSION['uid']);
    $res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notifications WHERE user_id=$uid AND is_read=0");
    echo json_encode(['code' => 1, 'count' => intval(mysqli_fetch_assoc($res)['cnt'])]);
    exit;
}
?>
