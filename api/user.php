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

function apiIsAdmin() {
    if (!isset($_SESSION['role']) || intval($_SESSION['role']) !== 1) {
        echo json_encode(['code' => 0, 'msg' => '无权限操作']);
        exit;
    }
}

if ($act == 'login') {
	    $user = trim($_POST['username'] ?? '');
	    $pwd = trim($_POST['password'] ?? '');
	    if ($user === '' || $pwd === '') {
	        echo json_encode(['code' => 0, 'msg' => '请输入账号和密码']);
	        exit;
	    }
	    $user = tcp_real_escape_string($conn, $user);
	    $res = tcp_query($conn, "SELECT * FROM users WHERE username='$user' AND status=1 LIMIT 1");
	    $row = tcp_fetch_assoc($res);
	    if ($row) {
	        $passwordValid = false;
	        $storedHash = $row['password'];
	        // 优先使用 password_verify（bcrypt/argon2）
	        if (password_verify($pwd, $storedHash)) {
	            $passwordValid = true;
	        }
	        // 兼容旧版 MD5 密码（自动升级）
	        elseif (strlen($storedHash) === 32 && strtoupper(md5($pwd)) === strtoupper($storedHash)) {
	            $passwordValid = true;
	            // 自动升级为 bcrypt
	            $newHash = tcp_real_escape_string($conn, password_hash($pwd, PASSWORD_DEFAULT));
	            tcp_query($conn, "UPDATE users SET password='$newHash' WHERE id=" . intval($row['id']));
	        }
	        if ($passwordValid) {
	            session_regenerate_id();
	            $_SESSION['uid'] = $row['id'];
	            $_SESSION['username'] = $row['username'];
	            $_SESSION['nickname'] = $row['nickname'];
	            $_SESSION['role'] = $row['role'];
	            echo json_encode(['code' => 1, 'msg' => '登录成功', 'url' => appUrl('index.php')]);
	        } else {
	            echo json_encode(['code' => 0, 'msg' => '账号或密码错误']);
	        }
	    } else {
	        echo json_encode(['code' => 0, 'msg' => '账号或密码错误']);
	    }
	    exit;
	}

if ($act == 'register') {
    $user = trim($_POST['username'] ?? '');
    $pwd = trim($_POST['password'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $isOpen = isOpenRegistration();
    if ($user === '' || $pwd === '') {
        echo json_encode(['code' => 0, 'msg' => '请完整填写信息']);
        exit;
    }
    if (!$isOpen && $code === '') {
        echo json_encode(['code' => 0, 'msg' => '请输入注册验证码']);
        exit;
    }
    $userEscaped = tcp_real_escape_string($conn, $user);
    $check = tcp_query($conn, "SELECT id FROM users WHERE username='$userEscaped' LIMIT 1");
    if (tcp_num_rows($check) > 0) {
        echo json_encode(['code' => 0, 'msg' => '用户名已存在']);
        exit;
    }
    if (!$isOpen) {
        $codeEscaped = tcp_real_escape_string($conn, $code);
        $cres = tcp_query($conn, "SELECT * FROM verify_codes WHERE code='$codeEscaped' AND is_used=0 LIMIT 1");
        if (tcp_num_rows($cres) == 0) {
            echo json_encode(['code' => 0, 'msg' => '验证码无效']);
            exit;
        }
        tcp_query($conn, "UPDATE verify_codes SET is_used=1 WHERE code='$codeEscaped'");
    }
    $pwdHash = tcp_real_escape_string($conn, password_hash($pwd, PASSWORD_DEFAULT));
	    tcp_query($conn, "INSERT INTO users(username,password,nickname) VALUES('$userEscaped','$pwdHash','$userEscaped')");
    echo json_encode(['code' => 1, 'msg' => '注册成功', 'url' => appUrl('login.php')]);
    exit;
}

if ($act == 'get_open_status') {
    $isOpen = isOpenRegistration();
    $remaining = getOpenRegistrationRemaining();
    echo json_encode(['code' => 1, 'is_open' => $isOpen, 'remaining' => $remaining]);
    exit;
}

if ($act == 'toggle_open_reg') {
    apiIsLogin();
    apiIsAdmin();
    $isOpen = isOpenRegistration();
    if ($isOpen) {
        setSetting('open_reg_end', 0);
        echo json_encode(['code' => 1, 'msg' => '已关闭限时注册', 'is_open' => false, 'remaining' => 0]);
    } else {
        $endTime = time() + 300;
        setSetting('open_reg_end', $endTime);
        echo json_encode(['code' => 1, 'msg' => '已开启限时注册，有效期5分钟', 'is_open' => true, 'remaining' => 300]);
    }
    exit;
}

if ($act == 'create_code') {
    apiIsLogin();
    apiIsAdmin();
    $code = substr(str_shuffle('0123456789ABCDEFGHIJKLMN'), 0, 8);
    tcp_query($conn, "INSERT INTO verify_codes(code) VALUES('$code')");
    echo json_encode(['code' => 1, 'verify_code' => $code]);
    exit;
}

if ($act == 'edit_pwd') {
	    apiIsLogin();
	    $uid = intval($_SESSION['uid']);
	    $old = trim($_POST['old_pwd'] ?? '');
	    $newRaw = trim($_POST['new_pwd'] ?? '');
	    if ($newRaw === '') {
	        echo json_encode(['code' => 0, 'msg' => '新密码不能为空']);
	        exit;
	    }
	    $res = tcp_query($conn, "SELECT password FROM users WHERE id=$uid LIMIT 1");
	    $row = tcp_fetch_assoc($res);
	    if (!$row) {
	        echo json_encode(['code' => 0, 'msg' => '用户不存在']);
	        exit;
	    }
	    $storedHash = $row['password'];
	    $oldValid = false;
	    // 优先使用 password_verify
	    if (password_verify($old, $storedHash)) {
	        $oldValid = true;
	    }
	    // 兼容旧版 MD5
	    elseif (strlen($storedHash) === 32 && strtoupper(md5($old)) === strtoupper($storedHash)) {
	        $oldValid = true;
	    }
	    if ($oldValid) {
	        $newHash = tcp_real_escape_string($conn, password_hash($newRaw, PASSWORD_DEFAULT));
	        tcp_query($conn, "UPDATE users SET password='$newHash' WHERE id=$uid");
	        echo json_encode(['code' => 1, 'msg' => '密码修改成功']);
	    } else {
	        echo json_encode(['code' => 0, 'msg' => '原密码错误']);
	    }
	    exit;
	}

if ($act == 'edit_nickname') {
    apiIsLogin();
    $uid = intval($_SESSION['uid']);
    $nickname = trim($_POST['nickname'] ?? '');
    if ($nickname === '') {
        echo json_encode(['code' => 0, 'msg' => '昵称不能为空']);
        exit;
    }
    $nicknameEscaped = tcp_real_escape_string($conn, $nickname);
    tcp_query($conn, "UPDATE users SET nickname='$nicknameEscaped' WHERE id=$uid");
    $_SESSION['nickname'] = $nickname;
    echo json_encode(['code' => 1, 'msg' => '昵称修改成功']);
    exit;
}

if ($act == 'del') {
    apiIsLogin();
    apiIsAdmin();
    $id = intval($_GET['id'] ?? 0);
    tcp_query($conn, "DELETE FROM users WHERE id=$id");
    echo json_encode(['code' => 1, 'msg' => '删除成功']);
    exit;
}

if ($act == 'logout') {
    $_SESSION = [];
    // 仅在 Cookie 可用时清除 Cookie（无 Cookie 设备跳过）
    if (ini_get('session.use_cookies') && !empty($_COOKIE[session_name()])) {
        $params = session_get_cookie_params();
        @setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    echo json_encode(['code' => 1, 'msg' => '已退出登录', 'url' => appUrl('login.php')]);
    exit;
}

if ($act == 'get_user') {
    apiIsLogin();
    $uid = intval($_SESSION['uid']);
    $res = tcp_query($conn, "SELECT id,username,nickname,avatar,role,storage,used_storage,create_time FROM users WHERE id=$uid LIMIT 1");
    $user = tcp_fetch_assoc($res);
    echo json_encode(['code' => 1, 'user' => $user]);
    exit;
}

// ---------- upload_avatar ----------
if ($act == 'upload_avatar') {
    apiIsLogin();
    $uid = intval($_SESSION['uid']);
    
    if (!isset($_FILES['avatar'])) {
        echo json_encode(['code' => 0, 'msg' => '请选择要上传的图片']);
        exit;
    }
    
    $file = $_FILES['avatar'];
    $errorCode = intval($file['error']);
    if ($errorCode !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE => '图片大小超过服务器限制',
            UPLOAD_ERR_FORM_SIZE => '图片大小超过表单限制',
            UPLOAD_ERR_PARTIAL => '图片上传不完整',
            UPLOAD_ERR_NO_FILE => '未选择图片',
            UPLOAD_ERR_NO_TMP_DIR => '服务器临时目录不存在',
            UPLOAD_ERR_CANT_WRITE => '服务器无法写入文件',
            UPLOAD_ERR_EXTENSION => '服务器扩展阻止了上传',
        ];
        $errMsg = isset($errMap[$errorCode]) ? $errMap[$errorCode] : '上传失败（错误码:' . $errorCode . '）';
        echo json_encode(['code' => 0, 'msg' => $errMsg]);
        exit;
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array(strtolower($ext), $allowed)) {
        echo json_encode(['code' => 0, 'msg' => '仅支持 JPG/PNG/GIF/WebP 格式']);
        exit;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['code' => 0, 'msg' => '头像最大 2MB']);
        exit;
    }
    
    $storedName = 'avatar_' . $uid . '_' . time() . '.' . strtolower($ext);
    $uploadDir = __DIR__ . '/../uploads/avatars/';
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            echo json_encode(['code' => 0, 'msg' => '创建上传目录失败']);
            exit;
        }
    }
    
    if (!is_writable($uploadDir)) {
        echo json_encode(['code' => 0, 'msg' => '上传目录不可写']);
        exit;
    }
    
    $dest = $uploadDir . $storedName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['code' => 0, 'msg' => '保存文件失败']);
        exit;
    }
    
    $relativePath = 'uploads/avatars/' . $storedName;
    tcp_query($conn, "UPDATE users SET avatar='" . tcp_real_escape_string($conn, $relativePath) . "' WHERE id=$uid");
    echo json_encode(['code' => 1, 'msg' => '上传成功', 'avatar' => $relativePath]);
    exit;
}

// ---------- sensitive_words ----------
if ($act == 'get_sensitive_words') {
    apiIsLogin();
    apiIsAdmin();
    $res = tcp_query($conn, "SELECT * FROM sensitive_words ORDER BY id DESC");
    $words = [];
    while ($row = tcp_fetch_assoc($res)) { $words[] = $row; }
    echo json_encode(['code' => 1, 'words' => $words]);
    exit;
}

if ($act == 'add_sensitive_word') {
    apiIsLogin();
    apiIsAdmin();
    $word = trim($_POST['word'] ?? '');
    $level = intval($_POST['level'] ?? 1);
    if ($word === '') { echo json_encode(['code' => 0, 'msg' => '请输入敏感词']); exit; }
    $wordEsc = tcp_real_escape_string($conn, $word);
    tcp_query($conn, "INSERT INTO sensitive_words(word, level) VALUES('$wordEsc', $level) ON DUPLICATE KEY UPDATE level=$level");
    echo json_encode(['code' => 1, 'msg' => '添加成功']);
    exit;
}

if ($act == 'del_sensitive_word') {
    apiIsLogin();
    apiIsAdmin();
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['code' => 0, 'msg' => '参数错误']); exit; }
    tcp_query($conn, "DELETE FROM sensitive_words WHERE id=$id");
    echo json_encode(['code' => 1, 'msg' => '删除成功']);
    exit;
}

echo json_encode(['code' => 0, 'msg' => '无效请求']);