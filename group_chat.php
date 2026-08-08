<?php include 'config.php';
isLogin();
$role = intval($_SESSION['role'] ?? 0);
renderPageStart('群聊', 'group');
?>
<div class="group-layout">
    <div class="group-panels">
        <div class="group-sidebar-panel">
            <div class="group-sidebar-head">
                <div class="group-sidebar-title">群聊</div>
                <button class="group-create-btn" onclick="showCreateModal()" title="创建群聊">+</button>
            </div>
            <div class="group-list" id="groupList"></div>
        </div>
        <div class="group-main-panel">
            <div class="group-main-head">
                <div>
                    <div class="group-main-title" id="groupTitle">请选择一个群聊</div>
                    <div class="group-member-count" id="groupMembers"></div>
                </div>
                <button id="dismissGroupBtn" class="group-dismiss-btn" style="display:none" onclick="dismissGroup()">解散群聊</button>
            </div>
            <div class="group-messages" id="groupMessages">
                <div class="empty-state" id="emptyState">
                    <div class="empty-icon">💬</div>
                    <div class="empty-text">选择或创建一个群聊开始聊天</div>
                </div>
            </div>
            <div class="group-input-area" id="groupInputArea" style="display:none">
                <form id="groupForm" class="group-input-row">
                    <div style="-webkit-box-flex:1;-ms-flex:1;flex:1;">
                        <div id="attachmentPreview" style="display:none"></div>
                        <textarea id="messageInput" class="group-textarea" placeholder="输入消息..." rows="1"></textarea>
                    </div>
                    <div class="file-upload-btn">
                        📎
                        <input type="file" id="fileInput" onchange="handleFileSelect(event)">
                    </div>
                    <button type="submit" class="send-button">发送</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 创建群聊弹窗 -->
<div class="modal-mask" id="createModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title">创建群聊</div>
            <button class="modal-close" onclick="hideCreateModal()">&times;</button>
        </div>
        <div class="form-group">
            <label class="form-label">群聊名称</label>
            <input type="text" id="groupNameInput" class="form-control" placeholder="请输入群聊名称">
        </div>
        <div class="form-group">
            <label class="form-label">选择成员</label>
            <div class="user-pick-list" id="userSelectList"></div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline" onclick="hideCreateModal()">取消</button>
            <button class="btn btn-primary" onclick="createGroup()">创建</button>
        </div>
    </div>
</div>

<script>
let currentGroupId = null;
let lastMessageId = 0;
let pollInterval = null;
let selectedAttachment = null;
let selectedUsers = [];
let allUsers = [];
let groupCreators = {};
const userRole = <?= $role ?>;
const currentUserId = <?= intval($_SESSION['uid']) ?>;

document.addEventListener('DOMContentLoaded', function() {
    loadGroupList();
    setInterval(checkUnreadCount, 3000);
});

function loadGroupList() {
    requestJson('api/group_chat.php?act=get_groups', function(data) {
        if (data.code == 1) {
            for (var i = 0; i < data.groups.length; i++) {
                groupCreators[data.groups[i].id] = data.groups[i].creator_id;
            }
            renderGroupList(data.groups);
        }
    });
}

function renderGroupList(groups) {
    var html = '';
    if (groups.length === 0) {
        html = '<div class="empty-state"><div class="empty-text">暂无群聊</div></div>';
    } else {
        for (var i = 0; i < groups.length; i++) {
            var group = groups[i];
            var name = group.name;
            var avatar = name.charAt(0);
            html += '<div class="group-item" data-id="' + group.id + '" onclick="selectGroup(' + group.id + ')">' +
                '<div class="group-item-avatar">' + avatar + '</div>' +
                '<div class="group-item-info">' +
                '<div class="group-item-name">' + escapeHtml(name) + '</div>' +
                '<div class="group-item-meta">' + group.member_count + '人</div>' +
                '</div>' +
                (group.unread_count > 0 ? '<div class="group-unread-badge">' + group.unread_count + '</div>' : '') +
                '</div>';
        }
    }
    document.getElementById('groupList').innerHTML = html;
}

function selectGroup(groupId) {
    currentGroupId = groupId;
    lastMessageId = 0;
    loadGroupHistory(groupId);
    
    var items = document.querySelectorAll('.group-item');
    for (var i = 0; i < items.length; i++) {
        items[i].classList.remove('active');
        if (parseInt(items[i].dataset.id) === groupId) {
            items[i].classList.add('active');
        }
    }
    
    document.getElementById('groupInputArea').style.display = 'block';
    document.getElementById('emptyState').style.display = 'none';
    
    var isCreator = groupCreators[groupId] == currentUserId;
    var dismissBtn = document.getElementById('dismissGroupBtn');
    if (dismissBtn) {
        dismissBtn.style.display = (userRole == 1 || isCreator) ? 'inline-block' : 'none';
    }
    
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(pollNewMessages, 2000);
}

function loadGroupHistory(groupId) {
    requestJson('api/group_chat.php?act=get_history&group_id=' + groupId, function(data) {
        if (data.code == 1) {
            if (data.group) {
                document.getElementById('groupTitle').textContent = data.group.name;
            }
            loadGroupMembers(groupId);
            renderMessages(data.messages);
            loadGroupList();
        }
    });
}

function loadGroupMembers(groupId) {
    requestJson('api/group_chat.php?act=get_members&group_id=' + groupId, function(data) {
        if (data.code == 1) {
            var count = data.members.length;
            document.getElementById('groupMembers').textContent = count + '人';
        }
    });
}

function renderMessages(messages) {
    var container = document.getElementById('groupMessages');
    var html = '';
    
    if (messages.length === 0) {
        html = '<div class="empty-state"><div class="empty-text">暂无消息</div></div>';
    } else {
        for (var i = 0; i < messages.length; i++) {
            var msg = messages[i];
            var isSelf = parseInt(msg.sender_id) === currentUserId;
            var time = new Date(msg.create_time).toLocaleString('zh-CN');
            var senderName = msg.sender_nickname || msg.sender_username;
            var avatarHtml = App.avatarHtml(msg.sender_avatar, senderName);
            
            var canRecall = false;
            if (userRole == 1) {
                canRecall = true;
            } else if (isSelf) {
                var msgTime = new Date(msg.create_time).getTime();
                var now = new Date().getTime();
                var diffSeconds = Math.floor((now - msgTime) / 1000);
                if (diffSeconds <= 180) {
                    canRecall = true;
                }
            }
            
            var isRecalled = parseInt(msg.is_recalled) === 1;
            
            html += '<div class="group-msg-row ' + (isSelf ? 'self' : 'other') + '">' +
                '<div class="group-msg-avatar">' + avatarHtml + '</div>' +
                '<div class="group-msg-body">' +
                (!isSelf ? '<div class="group-msg-sender">' + escapeHtml(senderName) + '</div>' : '') +
                '<div class="group-msg-bubble">';
            
            if (isRecalled) {
                html += '<div class="msg-recall">消息已撤回</div>';
            } else {
                if (msg.content) {
                    html += '<div>' + escapeHtml(msg.content).replace(/\n/g, '<br>') + '</div>';
                }
                if (msg.file_path) {
                    html += renderAttachment(msg);
                }
            }
            
            html += '</div>' +
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
            '<img src="' + filePath + '" class="msg-attachment-img" onclick="viewImage(\'' + filePath + '\')" alt="' + escapeHtml(msg.file_name) + '" loading="lazy">' +
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

function appendMessages(messages) {
    var container = document.getElementById('groupMessages');
    var empty = container.querySelector('.empty-state');
    
    if (empty) {
        empty.remove();
    }
    
    for (var i = 0; i < messages.length; i++) {
        var msg = messages[i];
        var isSelf = parseInt(msg.sender_id) === parseInt('<?= $_SESSION['uid'] ?>');
        var time = new Date(msg.create_time).toLocaleString('zh-CN');
        var senderName = msg.sender_nickname || msg.sender_username;
        var avatarHtml = App.avatarHtml(msg.sender_avatar, senderName);
        
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
        
        var el = document.createElement('div');
        el.className = 'group-msg-row ' + (isSelf ? 'self' : 'other');
        
        var html = '<div class="group-msg-avatar">' + avatarHtml + '</div>' +
            '<div class="group-msg-body">' +
            (!isSelf ? '<div class="group-msg-sender">' + escapeHtml(senderName) + '</div>' : '') +
            '<div class="group-msg-bubble">';
        
        if (isRecalled) {
            html += '<div class="msg-recall">消息已撤回</div>';
        } else {
            if (msg.content) {
                html += '<div>' + escapeHtml(msg.content).replace(/\n/g, '<br>') + '</div>';
            }
            if (msg.file_path) {
                html += renderAttachment(msg);
            }
        }
        
        html += '</div>' +
            '<div class="msg-time">' + time + '</div>' +
            (!isRecalled && canRecall ? '<div class="msg-action" onclick="recallMessage(' + msg.id + ')">🗑️ 撤回</div>' : '') +
            '</div>';
        
        el.innerHTML = html;
        container.appendChild(el);
        
        lastMessageId = Math.max(lastMessageId, msg.id);
    }
    
    scrollToBottom();
}

function pollNewMessages() {
    if (!currentGroupId) return;
    requestJson('api/group_chat.php?act=get_new_messages&group_id=' + currentGroupId + '&last_id=' + lastMessageId, function(data) {
        if (data.code == 1 && data.messages.length > 0) {
            appendMessages(data.messages);
        }
    });
}

function checkUnreadCount() {
    requestJson('api/group_chat.php?act=get_unread_count', function(data) {
        if (data.code == 1) {
            var currentUnread = document.querySelectorAll('.group-unread-badge');
            var currentTotal = 0;
            for (var i = 0; i < currentUnread.length; i++) {
                currentTotal += parseInt(currentUnread[i].textContent);
            }
            
            if (data.count !== currentTotal) {
                loadGroupList();
            }
        }
    });
}

function scrollToBottom() {
    var container = document.getElementById('groupMessages');
    container.scrollTop = container.scrollHeight;
}

// 文件上传
function handleFileSelect(event) {
    var file = event.target.files[0];
    if (!file) return;
    
    var formData = new FormData();
    formData.append('file', file);
    
    fetch('api/upload.php', {
        method: 'POST',
        body: formData,
        credentials: 'include'
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.code == 1) {
            selectedAttachment = data.attachment;
            showAttachmentPreview(selectedAttachment);
        } else {
            Toast.error(data.msg);
        }
    })
    .catch(function() { Toast.error('上传失败'); });
    
    event.target.value = '';
}

function showAttachmentPreview(attachment) {
    var preview = document.getElementById('attachmentPreview');
    
    var html = '<div class="attach-preview">' +
        '<span>' + escapeHtml(attachment.file_name) + ' (' + formatSize(attachment.file_size) + ')</span>' +
        '<button type="button" class="remove-attach" onclick="removeAttachment()">×</button>' +
        '</div>';
    
    preview.innerHTML = html;
    preview.style.display = 'block';
}

function removeAttachment() {
    selectedAttachment = null;
    document.getElementById('attachmentPreview').style.display = 'none';
}

// 发送消息
document.getElementById('groupForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var input = document.getElementById('messageInput');
    var content = input.value.trim();
    
    if (!content && !selectedAttachment) {
        Toast.warning('请输入消息内容或选择附件');
        return;
    }
    
    var formData = new FormData();
    formData.append('group_id', currentGroupId);
    formData.append('content', content);
    if (selectedAttachment) {
        formData.append('attachment_id', selectedAttachment.id);
    }
    
    formSubmit('api/group_chat.php?act=send', formData, function(data) {
        if (data.code == 1) {
            input.value = '';
            removeAttachment();
            appendMessages([data.message]);
        } else {
            Toast.error(data.msg);
        }
    });
});

// 撤回消息
function recallMessage(messageId) {
    showConfirm('确认撤回', '确定要撤回这条消息吗？', function(confirmed) {
        if (!confirmed) return;
        
        requestJson('api/group_chat.php?act=recall&id=' + messageId, function(data) {
            if (data.code == 1) {
                Toast.success('消息已撤回');
                var messageEls = document.querySelectorAll('.group-msg-row');
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

// 创建群聊相关函数
function showCreateModal() {
    document.getElementById('createModal').classList.add('show');
    loadUserSelectList();
}

function hideCreateModal() {
    document.getElementById('createModal').classList.remove('show');
    document.getElementById('groupNameInput').value = '';
    selectedUsers = [];
}

// 解散群聊
function dismissGroup() {
    if (!currentGroupId) return;
    
    showConfirm('确认解散', '确定要解散这个群聊吗？此操作不可恢复！', function(confirmed) {
        if (!confirmed) return;
        
        requestJson('api/group_chat.php?act=dismiss&group_id=' + currentGroupId, function(data) {
            if (data.code == 1) {
                Toast.success('群聊已解散');
                loadGroupList();
                currentGroupId = null;
                document.getElementById('groupTitle').textContent = '请选择一个群聊';
                document.getElementById('groupMembers').innerHTML = '';
                document.getElementById('groupMessages').innerHTML = '<div class="empty-state" id="emptyState"><div class="empty-icon">💬</div><div class="empty-text">选择或创建一个群聊开始聊天</div></div>';
                document.getElementById('groupInputArea').style.display = 'none';
                document.getElementById('dismissGroupBtn').style.display = 'none';
            } else {
                Toast.error(data.msg);
            }
        });
    });
}

function loadUserSelectList() {
    requestJson('api/group_chat.php?act=get_selectable_users', function(data) {
        if (data.code == 1 && data.users) {
            allUsers = data.users;
            renderUserSelectList(allUsers);
        } else {
            allUsers = [];
            renderUserSelectList([]);
        }
    });
}

function renderUserSelectList(users) {
    var html = '';
    if (!users || users.length === 0) {
        html = '<div class="empty-state"><div class="empty-text">暂无用户</div></div>';
    } else {
        for (var i = 0; i < users.length; i++) {
            var user = users[i];
            var name = user.nickname || user.username;
            var avatarHtml = App.avatarHtml(user.avatar, name);
            var userId = parseInt(user.id);
            var isSelected = false;
            for (var j = 0; j < selectedUsers.length; j++) {
                if (selectedUsers[j] === userId) {
                    isSelected = true;
                    break;
                }
            }
            
            html += '<div class="user-pick-item ' + (isSelected ? 'selected' : '') + '" onclick="toggleUser(' + userId + ')">' +
                '<div class="user-pick-avatar">' + avatarHtml + '</div>' +
                '<div class="user-pick-name">' + escapeHtml(name) + '</div>' +
                '<div class="user-pick-check">' + (isSelected ? '✓' : '') + '</div>' +
                '</div>';
        }
    }
    document.getElementById('userSelectList').innerHTML = html;
}

function toggleUser(userId) {
    var found = false;
    var newSelected = [];
    for (var i = 0; i < selectedUsers.length; i++) {
        if (selectedUsers[i] === userId) {
            found = true;
        } else {
            newSelected.push(selectedUsers[i]);
        }
    }
    if (!found) {
        newSelected.push(userId);
    }
    selectedUsers = newSelected;
    renderUserSelectList(allUsers);
}

function createGroup() {
    var name = document.getElementById('groupNameInput').value.trim();
    
    if (!name) {
        Toast.warning('请输入群聊名称');
        return;
    }
    
    if (selectedUsers.length === 0) {
        Toast.warning('请至少选择一位成员');
        return;
    }
    
    var formData = new FormData();
    formData.append('name', name);
    for (var i = 0; i < selectedUsers.length; i++) {
        formData.append('member_ids[]', selectedUsers[i]);
    }
    
    formSubmit('api/group_chat.php?act=create', formData, function(data) {
        if (data.code == 1) {
            Toast.success('群聊创建成功');
            hideCreateModal();
            loadGroupList();
            selectGroup(data.group_id);
        } else {
            Toast.error(data.msg);
        }
    });
}

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
</script>

<?php
renderPageEnd();
?>
