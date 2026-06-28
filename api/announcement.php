<?php
header('Content-Type: application/json');
include '../config.php';

if (!isset($_SESSION['uid'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$uid = intval($_SESSION['uid']);
$role = intval($_SESSION['role'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 获取公告列表（带已读状态）
if ($action === 'list') {
    $announcements = [];
    $res = mysqli_query($conn, "SELECT a.*, IF(ar.id IS NOT NULL, 1, 0) as is_read
        FROM announcements a
        LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id AND ar.user_id = $uid
        WHERE a.status=1
        ORDER BY a.is_top DESC, a.id DESC
        LIMIT 10");
    while ($row = mysqli_fetch_assoc($res)) {
        $announcements[] = [
            'id' => intval($row['id']),
            'title' => $row['title'],
            'content' => $row['content'],
            'is_top' => intval($row['is_top']),
            'is_read' => intval($row['is_read']),
            'created_at' => $row['created_at']
        ];
    }
    echo json_encode(['success' => true, 'data' => $announcements]);
    exit;
}

// 标记公告已读
if ($action === 'mark_read') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid ID']);
        exit;
    }
    mysqli_query($conn, "INSERT IGNORE INTO announcement_reads (announcement_id, user_id, read_at) VALUES ($id, $uid, NOW())");
    echo json_encode(['success' => true, 'message' => 'Marked as read']);
    exit;
}

// 标记所有公告已读
if ($action === 'mark_all_read') {
    mysqli_query($conn, "INSERT IGNORE INTO announcement_reads (announcement_id, user_id, read_at)
        SELECT id, $uid, NOW() FROM announcements WHERE status=1");
    echo json_encode(['success' => true, 'message' => 'All marked as read']);
    exit;
}

// 获取未读公告数
if ($action === 'unread_count') {
    $res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM announcements a
        LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id AND ar.user_id = $uid
        WHERE a.status=1 AND ar.id IS NULL");
    $row = mysqli_fetch_assoc($res);
    echo json_encode(['success' => true, 'count' => intval($row['cnt'])]);
    exit;
}

// 以下操作需要管理员权限
if ($role !== 1) {
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

// 创建公告
if ($action === 'create') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $isTop = isset($_POST['is_top']) ? 1 : 0;
    
    if (empty($title) || empty($content)) {
        echo json_encode(['error' => 'Title and content are required']);
        exit;
    }
    
    $title = mysqli_real_escape_string($conn, $title);
    $content = mysqli_real_escape_string($conn, $content);
    
    mysqli_query($conn, "INSERT INTO announcements (title, content, is_top, status, created_at) VALUES ('$title', '$content', $isTop, 1, NOW())");
    echo json_encode(['success' => true, 'message' => 'Announcement created']);
    exit;
}

// 更新公告
if ($action === 'update') {
    $id = intval($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $isTop = isset($_POST['is_top']) ? 1 : 0;
    $status = isset($_POST['status']) ? intval($_POST['status']) : 1;
    
    if ($id <= 0 || empty($title) || empty($content)) {
        echo json_encode(['error' => 'Invalid parameters']);
        exit;
    }
    
    $title = mysqli_real_escape_string($conn, $title);
    $content = mysqli_real_escape_string($conn, $content);
    
    mysqli_query($conn, "UPDATE announcements SET title='$title', content='$content', is_top=$isTop, status=$status WHERE id=$id");
    echo json_encode(['success' => true, 'message' => 'Announcement updated']);
    exit;
}

// 删除公告
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid ID']);
        exit;
    }
    mysqli_query($conn, "DELETE FROM announcements WHERE id=$id");
    echo json_encode(['success' => true, 'message' => 'Announcement deleted']);
    exit;
}

// 获取所有公告（管理用，带已读统计）
if ($action === 'get_all') {
    $announcements = [];
    $res = mysqli_query($conn, "SELECT a.*, 
        (SELECT COUNT(*) FROM announcement_reads ar WHERE ar.announcement_id = a.id) as read_count,
        (SELECT COUNT(*) FROM users u WHERE u.status=1) as total_users
        FROM announcements a ORDER BY a.id DESC");
    while ($row = mysqli_fetch_assoc($res)) {
        $row['read_count'] = intval($row['read_count']);
        $row['total_users'] = intval($row['total_users']);
        $row['unread_count'] = $row['total_users'] - $row['read_count'];
        $announcements[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $announcements]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
