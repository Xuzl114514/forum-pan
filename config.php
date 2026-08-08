<?php
ini_set('session.use_only_cookies', '0');
ini_set('session.use_trans_sid', '1');
ini_set('session.use_strict_mode', '0');
ini_set('session.cookie_httponly', '0');
ini_set('url_rewriter.tags', 'a=href,area=href,frame=src,form=');

if (isset($_GET[session_name()])) {
    session_id($_GET[session_name()]);
} elseif (isset($_POST[session_name()])) {
    session_id($_POST[session_name()]);
}

session_start();
header("Content-Type:text/html;charset=utf-8");

// ============================================
// 数据库配置 — TCP 总端模式
// 客户端通过 TCP 与总端通信，不直连 MySQL
// ============================================
define('TCP_HOST', '127.0.0.1');  // 总端 IP（局域网部署时改为总端所在 IP）
define('TCP_PORT', 9527);         // 总端端口

require_once __DIR__ . '/tcp_db.php';
$conn = tcp_connect(TCP_HOST, TCP_PORT);
if ($conn->connect_error) die('无法连接到总端数据库服务！请确认总端已启动: ' . $conn->connect_error);
tcp_set_charset($conn, 'utf8mb4');

// 运行时迁移：升级密码列以支持 bcrypt 哈希
@tcp_query($conn, "ALTER TABLE `users` MODIFY `password` varchar(255) NOT NULL COMMENT '密码（bcrypt哈希）'");

$upload_path = 'uploads/';
$max_size = 1024 * 1024 * 1024;

if (!is_dir($upload_path)) mkdir($upload_path, 0777, true);

function currentSidPair() {
    return session_name() . '=' . session_id();
}

function appUrl($path) {
    $sid = currentSidPair();
    if (strpos($path, '?') !== false) {
        return $path . '&' . $sid;
    }
    return $path . '?' . $sid;
}

function jsonUrl($path) {
    return str_replace('../', '', appUrl($path));
}

function redirectTo($path) {
    header('Location:' . appUrl($path));
    exit;
}

function isLogin() {
    global $conn;
    if (!isset($_SESSION['uid'])) {
        redirectTo('login.php');
    }
    $uid = intval($_SESSION['uid']);
    $res = tcp_query($conn, "SELECT username,nickname,role,status FROM users WHERE id=$uid LIMIT 1");
    $row = tcp_fetch_assoc($res);
    if (!$row || intval($row['status']) !== 1) {
        $_SESSION = [];
        session_destroy();
        redirectTo('login.php');
    }
    $_SESSION['username'] = $row['username'];
    $_SESSION['nickname'] = $row['nickname'];
    $_SESSION['role'] = $row['role'];
}

function isAdmin() {
    if (!isset($_SESSION['role']) || intval($_SESSION['role']) !== 1) {
        redirectTo('index.php');
    }
}

function getSetting($key) {
    global $conn;
    static $tableChecked = false;
    if (!$tableChecked) {
        $tableChecked = true;
        @tcp_query($conn, "CREATE TABLE IF NOT EXISTS `settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(50) NOT NULL,
            `setting_value` text,
            `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    $key = tcp_real_escape_string($conn, $key);
    $res = @tcp_query($conn, "SELECT setting_value FROM settings WHERE setting_key='$key' LIMIT 1");
    if ($res && tcp_num_rows($res) > 0) {
        $row = tcp_fetch_assoc($res);
        return $row['setting_value'];
    }
    return null;
}

function setSetting($key, $value) {
    global $conn;
    $key = tcp_real_escape_string($conn, $key);
    $value = tcp_real_escape_string($conn, $value);
    $existing = getSetting($key);
    if ($existing === null) {
        @tcp_query($conn, "INSERT INTO settings(setting_key, setting_value) VALUES('$key', '$value')");
    } else {
        @tcp_query($conn, "UPDATE settings SET setting_value='$value' WHERE setting_key='$key'");
    }
}

function isOpenRegistration() {
    $endTime = intval(getSetting('open_reg_end'));
    return $endTime > time();
}

function getOpenRegistrationRemaining() {
    $endTime = intval(getSetting('open_reg_end'));
    $remaining = $endTime - time();
    return max(0, $remaining);
}

function getDisplayName($uid) {
    global $conn;
    $uid = intval($uid);
    $res = tcp_query($conn, "SELECT username, nickname FROM users WHERE id=$uid LIMIT 1");
    $row = tcp_fetch_assoc($res);
    if (!$row) return '未知用户';
    return !empty($row['nickname']) ? $row['nickname'] : $row['username'];
}

function h($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

/**
 * 获取用户主题偏好（优先 Cookie，兜底 Session）
 * 适配无 Cookie 能力的设备
 */
function getTheme() {
    $theme = $_COOKIE['forum_theme'] ?? ($_SESSION['forum_theme'] ?? 'default');
    if (!in_array($theme, ['default', 'pink', 'white', 'black', 'blue'])) $theme = 'default';
    return $theme;
}

/**
 * 设置用户主题偏好（同时写入 Cookie 和 Session）
 * Cookie 可能失败时 Session 作为兜底
 */
function setTheme($theme) {
    if (in_array($theme, ['default', 'pink', 'white', 'black', 'blue'])) {
        // 尝试设置 Cookie（无 Cookie 设备会静默失败）
        @setcookie('forum_theme', $theme, time() + 86400 * 365, '/');
        // Session 兜底存储
        $_SESSION['forum_theme'] = $theme;
    }
}

/**
 * 获取用户每页条数偏好（优先 Cookie，兜底 Session）
 * 适配无 Cookie 能力的设备
 */
function getPerPage() {
    $perPage = isset($_COOKIE['forum_per_page']) ? intval($_COOKIE['forum_per_page']) : 0;
    if ($perPage <= 0) {
        $perPage = isset($_SESSION['forum_per_page']) ? intval($_SESSION['forum_per_page']) : 10;
    }
    if ($perPage < 5) $perPage = 5;
    if ($perPage > 100) $perPage = 100;
    return $perPage;
}

/**
 * 设置用户每页条数偏好（同时写入 Cookie 和 Session）
 * Cookie 可能失败时 Session 作为兜底
 */
function setPerPage($perPage) {
    if ($perPage >= 5 && $perPage <= 100) {
        // 尝试设置 Cookie（无 Cookie 设备会静默失败）
        @setcookie('forum_per_page', $perPage, time() + 86400 * 30, '/');
        // Session 兜底存储
        $_SESSION['forum_per_page'] = $perPage;
    }
}

function renderPageStart($title, $current = '') {
    $title = h($title);
    $cssVersion = time(); // 添加时间戳防止缓存
    $theme = getTheme(); // 使用统一函数，适配无Cookie设备
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no"><title>' . $title . '</title><link rel="stylesheet" href="static/css/style.css?v=' . $cssVersion . '"></head><body class="theme-' . h($theme) . '">';
    renderSidebar($current);
    echo '<div class="app-main"><div class="app-topbar"><button class="sidebar-toggle" type="button" onclick="toggleSidebar()">☰</button><div class="app-topbar-title">' . $title . '</div></div><div class="container">';
}

function renderSidebar($current = '') {
    global $conn;
    $displayName = !empty($_SESSION['nickname']) ? $_SESSION['nickname'] : ($_SESSION['username'] ?? '用户');
    $avatarChar = mb_substr($displayName, 0, 1, 'utf-8');
    $userAvatar = '';
    if (isset($_SESSION['uid'])) {
        $uid = intval($_SESSION['uid']);
        $res = tcp_query($conn, "SELECT avatar FROM users WHERE id=$uid LIMIT 1");
        if ($res && ($row = tcp_fetch_assoc($res))) {
            $userAvatar = $row['avatar'];
        }
    }
    $avatarHtml = '';
    if (!empty($userAvatar)) {
        $avatarHtml = '<img src="' . h($userAvatar) . '" style="width:100%;height:100%;border-radius:inherit;object-fit:cover">';
    } else {
        $avatarHtml = h($avatarChar);
    }
    echo '<div class="sidebar-mask" onclick="toggleSidebar(false)"></div>';
    echo '<aside class="sidebar" id="sidebar">';
    echo '<div class="sidebar-brand">Forum <span>Pan</span></div>';
    echo '<div class="sidebar-user"><div class="sidebar-avatar">' . $avatarHtml . '</div><div><div class="sidebar-name">' . h($displayName) . '</div><div class="sidebar-role">' . (intval($_SESSION['role']) === 1 ? '管理员' : '普通用户') . '</div></div></div>';
    echo '<nav class="sidebar-nav">';
    echo '<a class="sidebar-link' . ($current === 'index' ? ' active' : '') . '" href="' . h(appUrl('index.php')) . '"><span class="icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg></span>论坛首页</a>';
    echo '<a class="sidebar-link' . ($current === 'post' ? ' active' : '') . '" href="' . h(appUrl('post.php?add=1')) . '"><span class="icon"><svg viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg></span>发布帖子</a>';
    echo '<a class="sidebar-link' . ($current === 'chat' ? ' active' : '') . '" href="' . h(appUrl('chat.php')) . '"><span class="icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg></span>私聊</a>';
    echo '<a class="sidebar-link' . ($current === 'group' ? ' active' : '') . '" href="' . h(appUrl('group_chat.php')) . '"><span class="icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>群聊</a>';
    echo '<div class="sidebar-section-title">账户</div>';
    echo '<a class="sidebar-link' . ($current === 'user' ? ' active' : '') . '" href="' . h(appUrl('user.php')) . '"><span class="icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>个人中心</a>';
    echo '<a class="sidebar-link' . ($current === 'storage' ? ' active' : '') . '" href="' . h(appUrl('storage.php')) . '"><span class="icon"><svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg></span>我的网盘</a>';
    echo '<a class="sidebar-link' . ($current === 'browser_test' ? ' active' : '') . '" href="' . h(appUrl('browser_test.php')) . '"><span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path><line x1="2" y1="12" x2="22" y2="12"></line></svg></span>浏览器检测</a>';
    echo '<a class="sidebar-link' . ($current === 'update' ? ' active' : '') . '" href="' . h(appUrl('update.php')) . '"><span class="icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg></span>更新公告</a>';
    if (intval($_SESSION['role']) === 1) {
        echo '<div class="sidebar-section-title">管理</div>';
        echo '<a class="sidebar-link' . ($current === 'admin' ? ' active' : '') . '" href="' . h(appUrl('admin.php')) . '"><span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg></span>管理后台</a>';
        echo '<a class="sidebar-link' . ($current === 'remote' ? ' active' : '') . '" href="' . h(appUrl('remote_desktop.php')) . '"><span class="icon"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg></span>远程桌面</a>';
    }
    echo '</nav>';
    echo '<button class="sidebar-logout" type="button" onclick="logout()"><span class="icon"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg></span>退出登录</button>';
    echo '</aside>';
}

function renderPageEnd() {
    echo '</div>';
    
    echo '<footer class="app-footer">
            <div class="footer-inner">
                <span class="footer-text">此网站由 <a href="go.php?url=mefrp.com" class="footer-link" target="_blank">mefrp</a> 进行穿透外网服务</span>
            </div>
          </footer>';
    
    echo '</div>';
    
    echo '<script>window.APP_SESSION_NAME=' . json_encode(session_name()) . ';window.APP_SESSION_ID=' . json_encode(session_id()) . ';window.APP_SID_QUERY=' . json_encode(currentSidPair()) . ';window.APP_THEME=' . json_encode(getTheme()) . ';window.APP_PER_PAGE=' . json_encode(getPerPage()) . ';</script><script src="static/js/main.js"></script></body></html>';
}

// ---------- theme ----------
if (isset($_GET['api']) && $_GET['api'] === 'theme') {
    header('Content-Type: application/json');
    $theme = $_POST['theme'] ?? $_GET['theme'] ?? '';
    if (in_array($theme, ['default', 'pink', 'white', 'black', 'blue'])) {
        setTheme($theme); // 使用统一函数，同时写Cookie和Session
        echo json_encode(['code' => 1, 'theme' => $theme]);
    } else {
        echo json_encode(['code' => 0, 'msg' => '无效主题']);
    }
    exit;
}
?>