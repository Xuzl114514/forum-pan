<?php include 'config.php';
isLogin();

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 0;

if ($perPage <= 0) {
    $perPage = getPerPage(); // 使用统一函数，适配无Cookie设备
}
if ($perPage < 5) $perPage = 5;
if ($perPage > 100) $perPage = 100;

// 持久化偏好（同时写Cookie和Session）
if (!isset($_COOKIE['forum_per_page']) || intval($_COOKIE['forum_per_page']) != $perPage) {
    setPerPage($perPage); // 使用统一函数，适配无Cookie设备
}

$totalRes = tcp_query($conn, "SELECT COUNT(*) as cnt FROM posts");
$totalRow = tcp_fetch_assoc($totalRes);
$total = intval($totalRow['cnt']);
$totalPages = max(1, ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;
$res = tcp_query($conn, "SELECT p.*, u.username, u.nickname, u.avatar FROM posts p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.is_top DESC, p.id DESC LIMIT $offset, $perPage");

renderPageStart('论坛首页', 'index');
?>
<div class="card animate-in search-bar" style="margin-bottom:16px;padding:14px 20px">
    <div style="display:flex;gap:12px;align-items:center">
        <div style="position:relative;flex:1">
            <input type="text" id="searchInput" class="form-control" placeholder="搜索帖子标题或内容..." style="padding-left:40px;height:44px" onkeydown="if(event.key==='Enter')doSearch()">
            <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:16px">🔍</span>
        </div>
        <button onclick="doSearch()" class="btn btn-outline" style="height:44px;padding:0 20px">搜索</button>
    </div>
    <div id="searchResults" style="display:none;margin-top:16px"></div>
</div>

<div id="announcementSection" class="card animate-in" style="margin-bottom:16px;display:none">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border-subtle);display:flex;align-items:center;gap:8px">
        <span style="font-size:18px">📢</span>
        <span style="font-weight:600;color:var(--text-primary)">公告</span>
        <span id="annUnreadBadge" style="display:none;margin-left:4px;padding:2px 8px;background:#ef4444;color:white;border-radius:12px;font-size:11px;font-weight:600">0</span>
        <button onclick="markAllAnnRead()" class="btn btn-small btn-outline" style="margin-left:auto;padding:0 12px">全部已读</button>
    </div>
    <div id="announcementList" style="max-height:240px;overflow-y:auto"></div>
</div>

<div id="notificationBtn" class="notif-btn" onclick="toggleNotifications()" title="通知">
    <span style="font-size:20px">🔔</span>
    <span id="notifBadge" class="notif-badge" style="display:none">0</span>
</div>

<div class="card animate-in" style="margin-bottom:24px">
    <a href="<?=h(appUrl('post.php?add=1'))?>" class="btn btn-primary" style="width:100%">发布新帖子</a>
</div>

<div class="card animate-in animate-delay-1" style="margin-bottom:20px;padding:16px 20px">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <span style="color:var(--text-secondary);font-size:13px;font-weight:600">每页显示：</span>
        <?php
        $perPageOptions = array(5, 10, 20, 50, 100);
        foreach ($perPageOptions as $opt) {
            $active = $perPage == $opt;
            echo '<a href="' . h(appUrl('index.php?page=1&per_page=' . $opt)) . '" class="per-page-btn' . ($active ? ' active' : '') . '">' . $opt . '条</a>';
        }
        ?>
        <span style="margin-left:auto;color:var(--text-muted);font-size:13px">共 <?= $total ?> 条</span>
    </div>
</div>

<?php
if ($total == 0) {
?>
<div class="card empty-state">
    <div class="empty-icon">💬</div>
    <div class="empty-text">还没有帖子，去发第一篇吧。</div>
</div>
<?php } else {
while ($row = tcp_fetch_assoc($res)) {
    $displayName = !empty($row['nickname']) ? $row['nickname'] : $row['username'];
    $avatarChar = mb_substr($displayName, 0, 1, 'utf-8');
    $hasAvatar = !empty($row['avatar']);
?>
<div class="post-card animate-in">
    <div class="post-header">
        <div class="post-avatar">
            <?php if ($hasAvatar): ?>
                <img src="<?=h($row['avatar'])?>" style="width:100%;height:100%;border-radius:inherit;object-fit:cover">
            <?php else: ?>
                <?=h($avatarChar)?>
            <?php endif; ?>
        </div>
        <div class="post-user">
            <div class="post-author"><?=h($displayName)?></div>
            <div class="post-time"><?=h($row['create_time'])?></div>
        </div>
        <?php if (intval($row['is_top']) === 1) { ?><span class="post-top">置顶</span><?php } ?>
    </div>
    <a href="<?=h(appUrl('post.php?id=' . intval($row['id'])))?>" style="display:block;">
        <div class="post-title"><?=h($row['title'])?></div>
        <div class="post-content"><?=h(mb_substr($row['content'], 0, 88, 'utf-8'))?>...</div>
    </a>
    <div class="post-actions">
        <button class="post-action" type="button" onclick="like(<?=intval($row['id'])?>, 'post')">👍 点赞 (<?=intval($row['like_num'])?>)</button>
        <a class="post-action" href="<?=h(appUrl('post.php?id=' . intval($row['id'])))?>">💬 查看评论</a>
        <?php if (intval($_SESSION['role']) === 1 || intval($row['user_id']) === intval($_SESSION['uid'])): ?>
            <button class="post-action" type="button" onclick="deletePost(<?=intval($row['id'])?>)" style="color:#ef4444">🗑️ 删除</button>
        <?php endif; ?>
    </div>
</div>
<?php } ?>

<?php if ($totalPages > 1) { ?>
<div class="pagination card animate-in">
    <div class="pagination-inner">
        <?php
        $pageUrl = function($p) use ($perPage) {
            return appUrl('index.php?page=' . $p . '&per_page=' . $perPage);
        };
        
        if ($page > 1) {
            echo '<a href="' . h($pageUrl(1)) . '" class="page-btn">首页</a>';
            echo '<a href="' . h($pageUrl($page - 1)) . '" class="page-btn">上一页</a>';
        }
        
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        if ($startPage > 1) {
            echo '<span class="page-dots">...</span>';
        }
        
        for ($p = $startPage; $p <= $endPage; $p++) {
            if ($p == $page) {
                echo '<span class="page-btn active">' . $p . '</span>';
            } else {
                echo '<a href="' . h($pageUrl($p)) . '" class="page-btn">' . $p . '</a>';
            }
        }
        
        if ($endPage < $totalPages) {
            echo '<span class="page-dots">...</span>';
        }
        
        if ($page < $totalPages) {
            echo '<a href="' . h($pageUrl($page + 1)) . '" class="page-btn">下一页</a>';
            echo '<a href="' . h($pageUrl($totalPages)) . '" class="page-btn">末页</a>';
        }
        ?>
    </div>
    <div class="pagination-info">第 <?= $page ?> / <?= $totalPages ?> 页</div>
</div>
<?php } ?>

<?php } renderPageEnd(); ?>
<script>
function requestJson(url, callback) {
    if (url.indexOf('?') === -1) {
        url += '?';
    }
    // 无Cookie兼容：优先从Session获取ID（而非Cookie）
    if (url.indexOf('PHPSESSID') === -1) {
        url += (url.indexOf('?') === url.length - 1 ? '' : '&') + 'PHPSESSID=' + '<?=session_id()?>';
    }
    
    fetch(url, {
        credentials: 'include'
    })
    .then(res => res.json())
    .then(data => callback(data))
    .catch(err => Toast.error('网络错误'));
}

function like(id, type) {
    requestJson('api/like.php?id=' + id + '&type=' + type, function(data) {
        if (data.code == 1) {
            location.reload();
        } else {
            Toast.error(data.msg);
        }
    });
}

function deletePost(postId) {
    showConfirm('确认删除', '确定要删除这个帖子吗？此操作不可恢复！', function(confirmed) {
        if (!confirmed) return;
        
        requestJson('api/forum.php?act=delete_post&id=' + postId, function(data) {
            if (data.code == 1) {
                Toast.success('帖子已删除');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                Toast.error(data.msg);
            }
        });
    });
}

function doSearch() {
    var kw = document.getElementById('searchInput').value.trim();
    if (!kw) { Toast.warning('请输入搜索关键词'); return; }
    requestJson('api/forum.php?act=search&keyword=' + encodeURIComponent(kw), function(data) {
        if (data.code == 1) {
            var container = document.getElementById('searchResults');
            if (data.posts.length === 0) {
                container.innerHTML = '<div class="empty-state" style="padding:20px"><div class="empty-text">未找到相关帖子</div></div>';
            } else {
                var html = '<div style="margin-bottom:12px;font-size:13px;color:var(--text-secondary)">找到 ' + data.posts.length + ' 条结果</div>';
                for (var i = 0; i < data.posts.length; i++) {
                    var p = data.posts[i];
                    var name = p.nickname || p.username;
                    var avatarHtml = '';
                    if (p.avatar) {
                        avatarHtml = '<img src="' + p.avatar + '" style="width:100%;height:100%;border-radius:inherit;object-fit:cover">';
                    } else {
                        avatarHtml = name.charAt(0);
                    }
                    html += '<div class="post-card" style="margin-bottom:12px"><div class="post-header"><div class="post-avatar">' + avatarHtml + '</div><div class="post-user"><div class="post-author">' + escapeHtml(name) + '</div><div class="post-time">' + p.create_time + '</div></div>' + (p.is_top == 1 ? '<span class="post-top">置顶</span>' : '') + (p.is_essence == 1 ? '<span class="post-top" style="background:rgba(168,85,247,0.15);color:#a855f7">精华</span>' : '') + '</div><a href="post.php?id=' + p.id + '" style="display:block"><div class="post-title">' + escapeHtml(p.title) + '</div><div class="post-content">' + escapeHtml(p.content.substring(0, 80)) + '...</div></a></div>';
                }
                container.innerHTML = html;
            }
            container.style.display = 'block';
        } else {
            Toast.error(data.msg);
        }
    });
}

function toggleNotifications() {
    var panel = document.getElementById('notifPanel');
    if (!panel) {
        createNotifPanel();
        return;
    }
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        loadNotifications();
    } else {
        panel.style.display = 'none';
    }
}

function createNotifPanel() {
    var panel = document.createElement('div');
    panel.id = 'notifPanel';
    panel.className = 'notif-panel';
    panel.innerHTML = '<div class="notif-panel-head"><span>通知</span><button onclick="markAllRead()" style="background:none;border:none;color:var(--accent-primary);cursor:pointer;font-size:12px">全部已读</button></div><div class="notif-list" id="notifList"><div class="empty-state" style="padding:20px"><div class="empty-text">加载中...</div></div></div>';
    document.body.appendChild(panel);
    panel.style.display = 'block';
    loadNotifications();
}

function loadNotifications() {
    requestJson('api/forum.php?act=get_notifications', function(data) {
        if (data.code == 1) {
            var list = document.getElementById('notifList');
            if (data.notifications.length === 0) {
                list.innerHTML = '<div class="empty-state" style="padding:20px"><div class="empty-text">暂无通知</div></div>';
            } else {
                var html = '';
                for (var i = 0; i < data.notifications.length; i++) {
                    var n = data.notifications[i];
                    html += '<div class="notif-item' + (n.is_read == 0 ? ' unread' : '') + '" onclick="clickNotif(' + n.id + ',' + n.source_id + ',\'' + n.source_type + '\')"><div class="notif-icon">' + (n.type === 'comment' ? '💬' : n.type === 'like' ? '👍' : '🔔') + '</div><div class="notif-content"><div class="notif-text">' + escapeHtml(n.content) + '</div><div class="notif-time">' + formatNotifTime(n.create_time) + '</div></div>' + (n.is_read == 0 ? '<div class="notif-dot"></div>' : '') + '</div>';
                }
                list.innerHTML = html;
            }
            updateNotifBadge(data.unread);
        }
    });
}

function clickNotif(id, sourceId, sourceType) {
    if (sourceType === 'post' && sourceId > 0) {
        location.href = 'post.php?id=' + sourceId;
    }
    requestJson('api/forum.php?act=mark_read', function() {});
}

function markAllRead() {
    requestJson('api/forum.php?act=mark_read', function(data) {
        if (data.code == 1) {
            loadNotifications();
        }
    });
}

function updateNotifBadge(count) {
    var badge = document.getElementById('notifBadge');
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'block';
    } else {
        badge.style.display = 'none';
    }
}

function formatNotifTime(timeStr) {
    var d = new Date(timeStr);
    var now = new Date();
    var diff = Math.floor((now - d) / 1000);
    if (diff < 60) return '刚刚';
    if (diff < 3600) return Math.floor(diff / 60) + '分钟前';
    if (diff < 86400) return Math.floor(diff / 3600) + '小时前';
    return Math.floor(diff / 86400) + '天前';
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 每30秒检查未读通知数
setInterval(function() {
    requestJson('api/forum.php?act=get_unread_count', function(data) {
        if (data.code == 1) updateNotifBadge(data.count);
    });
}, 30000);

// 初始加载未读数
requestJson('api/forum.php?act=get_unread_count', function(data) {
    if (data.code == 1) updateNotifBadge(data.count);
});

function loadAnnouncements() {
    requestJson('api/announcement.php?action=list', function(data) {
        if (data.success && data.data.length > 0) {
            var section = document.getElementById('announcementSection');
            section.style.display = 'block';
            var html = '';
            var unreadCount = 0;
            for (var i = 0; i < data.data.length; i++) {
                var a = data.data[i];
                if (a.is_read == 0) unreadCount++;
                html += '<div style="padding:12px 20px;border-bottom:1px solid var(--border-subtle);' + (a.is_read == 0 ? 'background:var(--bg-hover);' : '') + '">' +
                    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">' +
                    (a.is_top == 1 ? '<span style="color:#ef4444;font-size:12px;font-weight:600">📌 置顶</span>' : '') +
                    '<span style="font-weight:600;color:var(--text-primary);' + (a.is_read == 0 ? '' : 'font-weight:400;color:var(--text-secondary)') + '">' + escapeHtml(a.title) + '</span>' +
                    (a.is_read == 0 ? '<span style="color:#ef4444;font-size:11px;font-weight:600">●</span>' : '') +
                    '<span style="margin-left:auto;color:var(--text-muted);font-size:11px">' + a.created_at + '</span>' +
                    '</div>' +
                    '<div style="color:var(--text-secondary);font-size:13px;white-space:pre-wrap;line-height:1.5">' + escapeHtml(a.content) + '</div>' +
                    (a.is_read == 0 ? '<div style="margin-top:8px;text-align:right"><button onclick="markAnnRead(' + a.id + ')" class="btn btn-small btn-outline" style="padding:0 12px">标为已读</button></div>' : '') +
                    '</div>';
            }
            document.getElementById('announcementList').innerHTML = html;
            var badge = document.getElementById('annUnreadBadge');
            if (unreadCount > 0) {
                badge.style.display = 'inline-block';
                badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            } else {
                badge.style.display = 'none';
            }
        }
    });
}

function markAnnRead(id) {
    var formData = new FormData();
    formData.append('id', id);
    formSubmit('api/announcement.php?action=mark_read', formData, function(data) {
        if (data.success) {
            loadAnnouncements();
        }
    });
}

function markAllAnnRead() {
    showConfirm('确认已读', '确定要将所有公告标记为已读吗？', function(confirmed) {
        if (!confirmed) return;
        requestJson('api/announcement.php?action=mark_all_read', function(data) {
            if (data.success) {
                Toast.success('已全部标记为已读');
                loadAnnouncements();
            }
        });
    });
}

loadAnnouncements();
</script>
