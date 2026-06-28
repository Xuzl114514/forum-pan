<?php include 'config.php'; isLogin();
$theme = $_COOKIE['forum_theme'] ?? 'default';
if (!in_array($theme, ['default', 'pink', 'white', 'black', 'blue'])) $theme = 'default';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, target-densitydpi=device-dpi">
    <title>帖子 - Forum Pan</title>
    <link rel="stylesheet" href="static/css/style.css?v=<?=time()?>">
</head>
<body class="theme-<?=h($theme)?>">
<?php renderSidebar(''); ?>
<div class="app-main">
    <div class="app-topbar">
        <button class="sidebar-toggle" type="button" onclick="toggleSidebar()">☰</button>
        <div class="app-topbar-title">帖子详情</div>
    </div>
    <div class="container">
        <?php if(isset($_GET['add'])){ ?>
        <div class="card animate-in">
            <h3 style="margin-bottom:20px;font-family:var(--font-display);font-size:20px;font-weight:700;">✏️ 发布帖子</h3>
            <form onsubmit="return submitPost(this)">
                <div class="form-group">
                    <label class="form-label">标题</label>
                    <input type="text" name="title" placeholder="请输入标题" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">内容</label>
                    <textarea name="content" rows="6" placeholder="请输入内容" class="form-control" required></textarea>
                </div>
                <button type="submit" class="btn btn-success" style="width:100%">发布帖子</button>
            </form>
        </div>
        <?php }else{ 
        $id = intval($_GET['id']);
        $post = mysqli_fetch_array(mysqli_query($conn,"SELECT p.*, u.username, u.nickname, u.avatar FROM posts p LEFT JOIN users u ON p.user_id = u.id WHERE p.id=$id"));
        if(!$post){
            echo '<div class="card empty-state"><div class="empty-icon">❌</div><div class="empty-text">帖子不存在</div></div>';
            exit;
        }
        $displayName = !empty($post['nickname']) ? $post['nickname'] : $post['username'];
        $avatarChar = mb_substr($displayName, 0, 1, 'utf-8');
        $postHasAvatar = !empty($post['avatar']);
        ?>
        <a href="index.php" class="btn btn-outline" style="width:auto;padding:0 20px;margin-bottom:16px;display:inline-flex;">← 返回首页</a>

        <div class="card animate-in" style="margin-top:0">
            <div class="post-header">
                <div class="post-avatar">
                    <?php if ($postHasAvatar): ?>
                        <img src="<?=htmlspecialchars($post['avatar'])?>" style="width:100%;height:100%;border-radius:inherit;object-fit:cover">
                    <?php else: ?>
                        <?=$avatarChar?>
                    <?php endif; ?>
                </div>
                <div class="post-user">
                    <div class="post-author"><?=htmlspecialchars($displayName)?></div>
                    <div class="post-time"><?=$post['create_time']?></div>
                </div>
            </div>
            <h2 class="post-title" style="font-size:22px"><?=htmlspecialchars($post['title'])?></h2>
            <div class="post-content" style="font-size:16px;line-height:1.8"><?=nl2br(htmlspecialchars($post['content']))?></div>
            <div class="post-actions">
                <div class="post-action" onclick="like(<?=$post['id']?>, 'post', this)">
                    👍 点赞 (<?=$post['like_num']?>)
                </div>
                <?php if (intval($_SESSION['role']) === 1): ?>
                <button class="post-action" type="button" onclick="toggleTop(<?=$post['id']?>, this)" style="<?=intval($post['is_top'])===1?'color:#22c55e':''?>">📌 <?=intval($post['is_top'])===1?'取消置顶':'置顶'?></button>
                <button class="post-action" type="button" onclick="toggleEssence(<?=$post['id']?>, this)" style="<?=intval($post['is_essence'])===1?'color:#a855f7':''?>">⭐ <?=intval($post['is_essence'])===1?'取消精华':'精华'?></button>
                <?php endif; ?>
                <?php if(intval($_SESSION['role']) === 1 || intval($post['user_id']) === intval($_SESSION['uid'])): ?>
                <div class="post-action" onclick="recallPost(<?=$post['id']?>)" style="color:#ef4444">
                    🗑️ 撤回
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card animate-in animate-delay-1">
            <h4 style="margin-bottom:16px;font-family:var(--font-display);font-size:16px;font-weight:700;">💬 评论</h4>
            <div id="commentList">
            <?php 
            $lastCommentId = 0;
            $cres = mysqli_query($conn,"SELECT c.*, u.username, u.nickname, u.avatar FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.post_id=$id ORDER BY c.id ASC");
            if(mysqli_num_rows($cres) == 0){
            ?>
            <div class="empty-state" style="padding:20px" id="emptyComment">
                <div class="empty-text">暂无评论，快来抢沙发吧！</div>
            </div>
            <?php } while($c = mysqli_fetch_array($cres)){ 
                $commentAuthor = !empty($c['nickname']) ? $c['nickname'] : $c['username'];
                $commentAvatarChar = mb_substr($commentAuthor, 0, 1, 'utf-8');
                $commentHasAvatar = !empty($c['avatar']);
                $lastCommentId = $c['id'];
            ?>
            <div class="comment-item" data-id="<?=$c['id']?>">
                <div class="comment-header">
                    <div class="comment-avatar">
                        <?php if ($commentHasAvatar): ?>
                            <img src="<?=htmlspecialchars($c['avatar'])?>" style="width:100%;height:100%;border-radius:inherit;object-fit:cover">
                        <?php else: ?>
                            <?=$commentAvatarChar?>
                        <?php endif; ?>
                    </div>
                    <div class="comment-author"><?=htmlspecialchars($commentAuthor)?></div>
                    <div class="comment-time"><?=$c['create_time']?></div>
                </div>
                <div class="comment-content"><?=nl2br(htmlspecialchars($c['content']))?></div>
                <div class="comment-actions">
                    <span class="comment-action" onclick="like(<?=$c['id']?>, 'comment', this)">👍 赞 (<?=$c['like_num']?>)</span>
                    <?php if(intval($_SESSION['role']) === 1 || intval($c['user_id']) === intval($_SESSION['uid'])): ?>
                    <span class="comment-action" onclick="recallComment(<?=$c['id']?>)" style="color:#ef4444">🗑️ 撤回</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php } ?>
            </div>
        </div>

        <div class="card animate-in animate-delay-2">
            <h4 style="margin-bottom:16px;font-family:var(--font-display);font-size:16px;font-weight:700;">✍️ 发表回复</h4>
            <form onsubmit="return submitComment(this, <?=$id?>)">
                <div class="form-group">
                    <textarea name="content" rows="3" placeholder="请输入回复内容" class="form-control" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">发表评论</button>
            </form>
        </div>
        <?php } ?>
    </div>
</div>
<script>window.APP_SESSION_NAME='<?=session_name()?>';window.APP_SESSION_ID='<?=session_id()?>';window.APP_SID_QUERY='<?=currentSidPair()?>';</script>
<script src="static/js/main.js"></script>
<script>
<?php if(!isset($_GET['add'])){ ?>
let commentPollInterval = null;
let lastCommentId = <?= $lastCommentId ?>;
const postId = <?= $id ?>;
const canRecallComment = <?= $role == 1 ? 'true' : 'false' ?>;

document.addEventListener('DOMContentLoaded', function() {
    commentPollInterval = setInterval(pollNewComments, 3000);
});

function pollNewComments() {
    requestJson('api/forum.php?act=get_new_comments&post_id=' + postId + '&last_id=' + lastCommentId, function(data) {
        if (data.code == 1 && data.comments.length > 0) {
            appendComments(data.comments);
        }
    });
}

function appendComments(comments) {
    const list = document.getElementById('commentList');
    const empty = document.getElementById('emptyComment');
    
    if (empty) {
        empty.style.display = 'none';
    }
    
    comments.forEach(comment => {
        const author = comment.nickname || comment.username;
        let avatarHtml = '';
        if (comment.avatar) {
            avatarHtml = '<img src="' + comment.avatar + '" style="width:100%;height:100%;border-radius:inherit;object-fit:cover">';
        } else {
            avatarHtml = author.charAt(0);
        }
        
        const el = document.createElement('div');
        el.className = 'comment-item';
        el.dataset.id = comment.id;
        
        el.innerHTML = 
            '<div class="comment-header">' +
                '<div class="comment-avatar">' + avatarHtml + '</div>' +
                '<div class="comment-author">' + escapeHtml(author) + '</div>' +
                '<div class="comment-time">' + comment.create_time + '</div>' +
            '</div>' +
            '<div class="comment-content">' + escapeHtml(comment.content).replace(/\n/g, '<br>') + '</div>' +
            '<div class="comment-actions">' +
                '<span class="comment-action" onclick="like(' + comment.id + ', \'comment\', this)">👍 赞 (' + comment.like_num + ')</span>' +
                (canRecallComment ? '<span class="comment-action" onclick="recallComment(' + comment.id + ')" style="color:#ef4444">🗑️ 撤回</span>' : '') +
            '</div>';
        
        list.appendChild(el);
        
        lastCommentId = Math.max(lastCommentId, comment.id);
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function recallPost(postId) {
    showConfirm('确认撤回', '确定要撤回这个帖子吗？', function(confirmed) {
        if (!confirmed) return;
        
        requestJson('api/forum.php?act=recall_post&id=' + postId, function(data) {
            if (data.code == 1) {
                Toast.success('帖子已撤回');
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                Toast.error(data.msg);
            }
        });
    });
}

function recallComment(commentId) {
    showConfirm('确认撤回', '确定要撤回这条评论吗？', function(confirmed) {
        if (!confirmed) return;
        
        requestJson('api/forum.php?act=recall_comment&id=' + commentId, function(data) {
            if (data.code == 1) {
                Toast.success('评论已撤回');
                const commentEl = document.querySelector('.comment-item[data-id="' + commentId + '"]');
                if (commentEl) {
                    commentEl.remove();
                }
            } else {
                Toast.error(data.msg);
            }
        });
    });
}

function toggleTop(id, btn) {
    requestJson('api/forum.php?act=toggle_top&id=' + id, function(data) {
        if (data.code == 1) {
            Toast.success(data.msg);
            if (data.is_top == 1) {
                btn.innerHTML = '📌 取消置顶';
                btn.style.color = '#22c55e';
            } else {
                btn.innerHTML = '📌 置顶';
                btn.style.color = '';
            }
        } else { Toast.error(data.msg); }
    });
}

function toggleEssence(id, btn) {
    requestJson('api/forum.php?act=toggle_essence&id=' + id, function(data) {
        if (data.code == 1) {
            Toast.success(data.msg);
            if (data.is_essence == 1) {
                btn.innerHTML = '⭐ 取消精华';
                btn.style.color = '#a855f7';
            } else {
                btn.innerHTML = '⭐ 精华';
                btn.style.color = '';
            }
        } else { Toast.error(data.msg); }
    });
}
<?php } ?>
</script>
</body>
</html>
