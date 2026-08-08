<?php include 'config.php';
isLogin();

$role = intval($_SESSION['role'] ?? 0);

renderPageStart('私聊', 'chat');
?>
<div class="chat-layout">
    <div class="chat-panels">
        <div class="chat-sidebar-panel">
            <div class="chat-sidebar-head">
                <div class="chat-sidebar-title">私聊</div>
            </div>
            <div class="chat-user-list" id="userList"></div>
        </div>
        <div class="chat-main-panel">
            <div class="chat-main-head">
                <div class="chat-main-title" id="chatTitle">请选择一个用户开始聊天</div>
            </div>
            <div class="chat-messages" id="chatMessages">
                <div class="empty-state" id="emptyState">
                    <div class="empty-icon">💬</div>
                    <div class="empty-text">选择左侧用户开始聊天</div>
                </div>
            </div>
            <div class="chat-input-area" id="chatInputArea" style="display:none">
                <form id="chatForm" class="chat-input-row">
                    <textarea id="messageInput" class="chat-textarea" placeholder="输入消息..." rows="1"></textarea>
                    <button type="submit" class="send-button">发送</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let currentUserId = null;
let lastMessageId = 0;
let pollInterval = null;
let unreadPollInterval = null;
const userRole = <?= $role ?>;

document.addEventListener('DOMContentLoaded', function() {
    loadUserList();
    unreadPollInterval = setInterval(checkUnreadCount, 3000);
});

function loadUserList() {
    requestJson('api/chat.php?act=get_users', function(data) {
        if (data.code == 1) {
            renderUserList(data.users);
        }
    });
}

function renderUserList(users) {
    var html = '';
    if (users.length === 0) {
        html = '<div class="empty-state"><div class="empty-text">暂无用户</div></div>';
    } else {
        for (var i = 0; i < users.length; i++) {
            var user = users[i];
            var name = user.nickname || user.username;
            var avatarHtml = App.avatarHtml(user.avatar, name);
            html += '<div class="chat-user-item" data-id="' + user.id + '" onclick="selectUser(' + user.id + ')">' +
                '<div class="chat-user-avatar">' + avatarHtml + '</div>' +
                '<div class="chat-user-info">' +
                '<div class="chat-user-name">' + escapeHtml(name) + '</div>' +
                '</div>' +
                (user.unread_count > 0 ? '<div class="chat-unread-badge">' + user.unread_count + '</div>' : '') +
                '</div>';
        }
    }
    document.getElementById('userList').innerHTML = html;
}

function selectUser(userId) {
    currentUserId = userId;
    lastMessageId = 0;
    loadChatHistory(userId);
    
    var items = document.querySelectorAll('.chat-user-item');
    for (var i = 0; i < items.length; i++) {
        items[i].classList.remove('active');
        if (parseInt(items[i].dataset.id) === userId) {
            items[i].classList.add('active');
        }
    }
    
    document.getElementById('chatInputArea').style.display = 'block';
    document.getElementById('emptyState').style.display = 'none';
    
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(pollNewMessages, 2000);
    
    loadUserList();
}

function loadChatHistory(userId) {
    requestJson('api/chat.php?act=get_history&other_uid=' + userId, function(data) {
        if (data.code == 1) {
            if (data.other_user) {
                var name = data.other_user.nickname || data.other_user.username;
                document.getElementById('chatTitle').textContent = '与 ' + name + ' 聊天';
            }
            renderMessages(data.messages);
        }
    });
}

function renderMessages(messages) {
    var container = document.getElementById('chatMessages');
    var html = '';
    if (messages.length === 0) {
        html = '<div class="empty-state"><div class="empty-text">暂无消息</div></div>';
    } else {
        for (var i = 0; i < messages.length; i++) {
            var msg = messages[i];
            var isSelf = parseInt(msg.sender_id) === parseInt('<?= $_SESSION['uid'] ?>');
            var time = new Date(msg.create_time).toLocaleString('zh-CN');
            
            var canRecall = false;
            if (userRole == 1) {
                canRecall = true;
            } else if (isSelf) {
                var msgTime = new Date(msg.create_time).getTime();
                var now = new Date().getTime();
                if ((now - msgTime) / 1000 <= 180) {
                    canRecall = true;
                }
            }
            
            var isRecalled = parseInt(msg.is_recalled) === 1;
            
            var contentHtml = '';
            if (isRecalled) {
                contentHtml = '<div class="msg-recall">消息已撤回</div>';
            } else {
                if (msg.content) {
                    contentHtml += '<div>' + escapeHtml(msg.content).replace(/\n/g, '<br>') + '</div>';
                }
                if (msg.file_path) {
                    contentHtml += renderAttachment(msg);
                }
            }
            
            html += '<div class="chat-msg-row ' + (isSelf ? 'self' : 'other') + '">' +
                '<div class="chat-msg-bubble">' +
                contentHtml +
                '<div class="msg-time">' + time + '</div>' +
                (!isRecalled && canRecall ? '<div class="msg-action" onclick="recallMessage(' + msg.id + ')">🗑️ 撤回</div>' : '') +
                '</div>' +
                '</div>';
            
            lastMessageId = Math.max(lastMessageId, msg.id);
        }
    }
    container.innerHTML = html;
    scrollToBottom();
}

function appendMessages(messages) {
    var container = document.getElementById('chatMessages');
    
    for (var i = 0; i < messages.length; i++) {
        var msg = messages[i];
        var isSelf = parseInt(msg.sender_id) === parseInt('<?= $_SESSION['uid'] ?>');
        var time = new Date(msg.create_time).toLocaleString('zh-CN');
        
        var canRecall = false;
        if (userRole == 1) {
            canRecall = true;
        } else if (isSelf) {
            var msgTime = new Date(msg.create_time).getTime();
            var now = new Date().getTime();
            if ((now - msgTime) / 1000 <= 180) {
                canRecall = true;
            }
        }
        
        var isRecalled = parseInt(msg.is_recalled) === 1;
        
        var contentHtml = '';
        if (isRecalled) {
            contentHtml = '<div class="msg-recall">消息已撤回</div>';
        } else {
            if (msg.content) {
                contentHtml += '<div>' + escapeHtml(msg.content).replace(/\n/g, '<br>') + '</div>';
            }
            if (msg.file_path) {
                contentHtml += renderAttachment(msg);
            }
        }
        
        var el = document.createElement('div');
        el.className = 'chat-msg-row ' + (isSelf ? 'self' : 'other');
        el.innerHTML = '<div class="chat-msg-bubble">' +
            contentHtml +
            '<div class="msg-time">' + time + '</div>' +
            (!isRecalled && canRecall ? '<div class="msg-action" onclick="recallMessage(' + msg.id + ')">🗑️ 撤回</div>' : '') +
            '</div>';
        
        container.appendChild(el);
        lastMessageId = Math.max(lastMessageId, msg.id);
    }
    
    scrollToBottom();
}

function renderAttachment(msg) {
    var isImage = msg.file_type && msg.file_type.indexOf('image/') === 0;
    
    var filePath = msg.file_path;
    if (filePath && filePath.indexOf('db://image/') === 0) {
        var imageId = filePath.split('/').pop();
        filePath = 'api/get_image.php?id=' + imageId;
    } else if (filePath && filePath.indexOf('/') !== 0 && filePath.indexOf('http') !== 0) {
        filePath = '/' + filePath;
    }
    
    if (isImage) {
        return '<div class="msg-attachment">' +
            '<img src="' + filePath + '" class="msg-attachment-img" onclick="viewImage(\'' + filePath + '\')" alt="' + escapeHtml(msg.file_name) + '">' +
            '</div>';
    } else {
        return '<div class="msg-attachment">' +
            '<a href="' + filePath + '" class="msg-attachment-file" download="' + escapeHtml(msg.file_name) + '">' +
            '<span class="msg-attachment-icon">📎</span>' +
            '<div class="msg-attachment-info">' +
            '<div class="msg-attachment-name">' + escapeHtml(msg.file_name) + '</div>' +
            '<div class="msg-attachment-size">' + formatSize(msg.file_size) + '</div>' +
            '</div>' +
            '</a>' +
            '</div>';
    }
}

function viewImage(src) {
    if (src.indexOf('/') !== 0 && src.indexOf('http') !== 0 && src.indexOf('api/') !== 0) {
        src = '/' + src;
    }
    
    var modal = document.createElement('div');
    modal.className = 'img-viewer';
    modal.onclick = function() { this.remove(); };
    
    var img = document.createElement('img');
    img.src = src;
    img.onclick = function(e) { e.stopPropagation(); };
    
    modal.appendChild(img);
    document.body.appendChild(modal);
}

function pollNewMessages() {
    if (!currentUserId) return;
    requestJson('api/chat.php?act=get_new_messages&other_uid=' + currentUserId + '&last_id=' + lastMessageId, function(data) {
        if (data.code == 1 && data.messages.length > 0) {
            appendMessages(data.messages);
        }
    });
}

function checkUnreadCount() {
    requestJson('api/chat.php?act=get_unread_count', function(data) {
        if (data.code == 1) {
            var currentUnread = document.querySelectorAll('.chat-unread-badge');
            var currentCount = 0;
            for (var i = 0; i < currentUnread.length; i++) {
                currentCount += parseInt(currentUnread[i].textContent);
            }
            if (data.count !== currentCount) {
                loadUserList();
            }
        }
    });
}

function scrollToBottom() {
    var container = document.getElementById('chatMessages');
    container.scrollTop = container.scrollHeight;
}

function recallMessage(messageId) {
    showConfirm('确认撤回', '确定要撤回这条消息吗？', function(confirmed) {
        if (!confirmed) return;
        
        requestJson('api/chat.php?act=recall&id=' + messageId, function(data) {
            if (data.code == 1) {
                Toast.success('消息已撤回');
                var messageEls = document.querySelectorAll('.chat-msg-row');
                for (var i = 0; i < messageEls.length; i++) {
                    if (messageEls[i].innerHTML.indexOf('recallMessage(' + messageId + ')') > -1) {
                        messageEls[i].remove();
                    }
                }
            } else {
                Toast.error(data.msg);
            }
        });
    });
}

document.getElementById('chatForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var input = document.getElementById('messageInput');
    var content = input.value.trim();
    
    if (!content) return;
    
    var formData = new FormData();
    formData.append('receiver_id', currentUserId);
    formData.append('content', content);
    
    formSubmit('api/chat.php?act=send', formData, function(data) {
        if (data.code == 1) {
            input.value = '';
            appendMessages([data.message]);
        } else {
            Toast.error(data.msg);
        }
    });
});

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
    if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
}
</script>

<?php
renderPageEnd();
?>
