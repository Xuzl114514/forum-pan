var App = {
    get sidName() { return window.APP_SESSION_NAME || 'PHPSESSID'; },
    get sidValue() { return window.APP_SESSION_ID || ''; },
    get sidQuery() { return window.APP_SID_QUERY || ''; },
    /** 获取当前主题（从服务端注入的变量） */
    get theme() { return window.APP_THEME || 'default'; },
    /** 获取每页条数（从服务端注入的变量） */
    get perPage() { return window.APP_PER_PAGE || 10; },
    withSid: function(url) {
        if (!this.sidQuery) return url;
        return url + (url.indexOf('?') > -1 ? '&' : '?') + this.sidQuery;
    },
    /** 设置主题（无Cookie兼容：通过API持久化 + 立即生效） */
    setTheme: function(name) {
        document.body.className = document.body.className.replace(/theme-\S+/g, '') + ' theme-' + name;
        var formData = new FormData();
        formData.append('theme', name);
        fetch('config.php?api=theme', { method: 'POST', body: formData, credentials: 'include' })
            .catch(function() { /* 静默失败，UI已更新 */ });
    },
    avatarHtml: function(avatarUrl, displayName) {
        if (avatarUrl) {
            return '<img src="' + avatarUrl + '" style="width:100%;height:100%;border-radius:inherit;object-fit:cover">';
        }
        var name = displayName || '?';
        return name.charAt(0);
    }
};

var Toast = {
    show: function(msg, type) {
        type = type || 'info';
        var toast = document.createElement('div');
        toast.className = 'custom-toast ' + type;
        toast.innerHTML = '<span>' + msg + '</span>';
        document.body.appendChild(toast);
        setTimeout(function() { toast.classList.add('show'); }, 10);
        setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 300);
        }, 2500);
    },
    success: function(msg) { this.show(msg, 'success'); },
    error: function(msg) { this.show(msg, 'error'); },
    warning: function(msg) { this.show(msg, 'warning'); },
    info: function(msg) { this.show(msg, 'info'); }
};

function toggleSidebar(force) {
    var sidebar = document.getElementById('sidebar');
    var mask = document.querySelector('.sidebar-mask');
    if (!sidebar) return;
    var open = typeof force === 'boolean' ? force : !sidebar.classList.contains('open');
    if (open) {
        sidebar.classList.add('open');
        if (mask) mask.classList.add('show');
        document.body.classList.add('sidebar-open');
    } else {
        sidebar.classList.remove('open');
        if (mask) mask.classList.remove('show');
        document.body.classList.remove('sidebar-open');
    }
}

function formSubmit(url, formData, callback) {
    var realUrl = App.withSid(url);
    if (App.sidValue && !formData.get(App.sidName)) {
        formData.append(App.sidName, App.sidValue);
    }
    if (window.fetch) {
        fetch(realUrl, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        }).then(function(res) {
            return res.json();
        }).then(function(data) {
            callback(data);
        }).catch(function() {
            Toast.error('网络错误');
        });
    } else {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', realUrl, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                callback(JSON.parse(xhr.responseText));
            } else {
                Toast.error('请求失败');
            }
        };
        xhr.onerror = function() {
            Toast.error('网络错误');
        };
        xhr.send(formData);
    }
}

function requestJson(url, callback) {
    var realUrl = App.withSid(url);
    if (window.fetch) {
        fetch(realUrl, { credentials: 'include' })
            .then(function(res) { return res.json(); })
            .then(callback)
            .catch(function() { Toast.error('网络错误'); });
    } else {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', realUrl, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                callback(JSON.parse(xhr.responseText));
            } else {
                Toast.error('请求失败');
            }
        };
        xhr.onerror = function() { Toast.error('网络错误'); };
        xhr.send();
    }
}

function like(id, type) {
    requestJson('api/forum.php?act=like&id=' + id + '&type=' + type, function(data) {
        if (data.code == 1) {
            Toast.success(data.msg);
            setTimeout(function() { location.reload(); }, 500);
        } else {
            Toast.warning(data.msg);
        }
    });
}

function submitPost(form) {
    var formData = new FormData(form);
    formSubmit('api/forum.php?act=add', formData, function(data) {
        if (data.code == 1) {
            Toast.success(data.msg);
            setTimeout(function() { location.href = App.withSid('index.php'); }, 700);
        } else {
            Toast.error(data.msg);
        }
    });
    return false;
}

function submitComment(form, postId) {
    var formData = new FormData(form);
    formSubmit('api/forum.php?act=comment&id=' + postId, formData, function(data) {
        if (data.code == 1) {
            Toast.success(data.msg);
            setTimeout(function() { location.reload(); }, 700);
        } else {
            Toast.error(data.msg);
        }
    });
    return false;
}

function loginSubmit(form) {
    var formData = new FormData(form);
    formSubmit('api/user.php?act=login', formData, function(data) {
        if (data.code == 1) {
            Toast.success(data.msg);
            setTimeout(function() { location.href = data.url; }, 700);
        } else {
            Toast.error(data.msg);
        }
    });
    return false;
}

function registerSubmit(form) {
    var formData = new FormData(form);
    formSubmit('api/user.php?act=register', formData, function(data) {
        if (data.code == 1) {
            Toast.success(data.msg);
            setTimeout(function() { location.href = data.url; }, 700);
        } else {
            Toast.error(data.msg);
        }
    });
    return false;
}

function editPwd(form) {
    var formData = new FormData(form);
    formSubmit('api/user.php?act=edit_pwd', formData, function(data) {
        if (data.code == 1) {
            Toast.success(data.msg);
            form.reset();
        } else {
            Toast.error(data.msg);
        }
    });
    return false;
}

function editNickname() {
    var nickname = document.getElementById('nickname').value;
    if (!nickname) {
        Toast.warning('请输入昵称');
        return;
    }
    var formData = new FormData();
    formData.append('nickname', nickname);
    formSubmit('api/user.php?act=edit_nickname', formData, function(data) {
        if (data.code == 1) {
            Toast.success(data.msg);
            var el = document.getElementById('displayNickname');
            if (el) el.textContent = nickname;
        } else {
            Toast.error(data.msg);
        }
    });
}

function createCode() {
    var url = 'api/user.php?act=create_code';
    if (App.sidQuery) {
        url += (url.indexOf('?') > -1 ? '&' : '?') + App.sidQuery;
    }
    fetch(url, { credentials: 'include' })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.code == 1) {
                document.getElementById('code_text').innerHTML = '<span class="code-highlight">注册码：' + data.verify_code + '</span>';
                Toast.success('注册码已生成');
            } else {
                Toast.error(data.msg || '生成失败');
            }
        })
        .catch(function() { Toast.error('请求失败'); });
}

function delUser(id) {
    requestJson('api/user.php?act=del&id=' + id, function(data) {
        if (data.code == 1) {
            Toast.success(data.msg);
            setTimeout(function() { location.reload(); }, 700);
        } else {
            Toast.error(data.msg);
        }
    });
}

function logout() {
    requestJson('api/user.php?act=logout', function(data) {
        if (data.code == 1) {
            Toast.success(data.msg);
            setTimeout(function() { location.href = data.url; }, 500);
        } else {
            Toast.error(data.msg || '退出失败');
        }
    });
}

var _confirmCallback = null;

function showConfirm(title, message, callback) {
    var mask = document.getElementById('confirmMask');
    var modal = document.getElementById('confirmModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'confirmModal';
        modal.className = 'confirm-modal';
        modal.innerHTML =
            '<div class="confirm-modal-header">' +
                '<div class="confirm-modal-icon" id="confirmIconEl">!</div>' +
                '<div class="confirm-modal-title" id="confirmTitleEl">提示</div>' +
            '</div>' +
            '<div class="confirm-modal-body" id="confirmBodyEl">内容</div>' +
            '<div class="confirm-modal-footer">' +
                '<button class="confirm-modal-btn cancel" id="confirmCancelBtn">取消</button>' +
                '<button class="confirm-modal-btn confirm" id="confirmOkBtn">确定</button>' +
            '</div>';
        mask = document.createElement('div');
        mask.id = 'confirmMask';
        mask.className = 'confirm-modal-mask';
        mask.appendChild(modal);
        document.body.appendChild(mask);
        
        document.getElementById('confirmCancelBtn').onclick = function() {
            hideConfirm(false);
        };
        document.getElementById('confirmOkBtn').onclick = function() {
            hideConfirm(true);
        };
        
        mask.onclick = function(e) {
            if (e.target === mask) {
                hideConfirm(false);
            }
        };
    }
    
    _confirmCallback = callback;
    document.getElementById('confirmTitleEl').textContent = title || '提示';
    document.getElementById('confirmBodyEl').textContent = message || '';
    mask.classList.add('show');
    modal.classList.add('show');
}

function hideConfirm(result) {
    var mask = document.getElementById('confirmMask');
    var modal = document.getElementById('confirmModal');
    if (mask) mask.classList.remove('show');
    if (modal) modal.classList.remove('show');
    if (_confirmCallback) {
        var cb = _confirmCallback;
        _confirmCallback = null;
        cb(result);
    }
}

var _promptCallback = null;

function showPrompt(title, message, defaultValue, callback) {
    if (typeof defaultValue === 'function') {
        callback = defaultValue;
        defaultValue = '';
    }
    var mask = document.getElementById('promptMask');
    var modal = document.getElementById('promptModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'promptModal';
        modal.className = 'confirm-modal';
        modal.innerHTML =
            '<div class="confirm-modal-header">' +
                '<div class="confirm-modal-icon" id="promptIconEl">?</div>' +
                '<div class="confirm-modal-title" id="promptTitleEl">请输入</div>' +
            '</div>' +
            '<div class="confirm-modal-body" id="promptBodyEl">' +
                '<div id="promptMessageEl" style="margin-bottom:12px;color:var(--text-secondary)"></div>' +
                '<textarea id="promptInputEl" class="form-control" rows="4" style="width:100%;resize:vertical"></textarea>' +
            '</div>' +
            '<div class="confirm-modal-footer">' +
                '<button class="confirm-modal-btn cancel" id="promptCancelBtn">取消</button>' +
                '<button class="confirm-modal-btn confirm" id="promptOkBtn">确定</button>' +
            '</div>';
        mask = document.createElement('div');
        mask.id = 'promptMask';
        mask.className = 'confirm-modal-mask';
        mask.appendChild(modal);
        document.body.appendChild(mask);
        
        document.getElementById('promptCancelBtn').onclick = function() {
            hidePrompt(null);
        };
        document.getElementById('promptOkBtn').onclick = function() {
            var val = document.getElementById('promptInputEl').value;
            hidePrompt(val);
        };
        
        mask.onclick = function(e) {
            if (e.target === mask) {
                hidePrompt(null);
            }
        };
    }
    
    _promptCallback = callback;
    document.getElementById('promptTitleEl').textContent = title || '请输入';
    document.getElementById('promptMessageEl').textContent = message || '';
    document.getElementById('promptInputEl').value = defaultValue || '';
    mask.classList.add('show');
    modal.classList.add('show');
    setTimeout(function() {
        document.getElementById('promptInputEl').focus();
    }, 100);
}

function hidePrompt(value) {
    var mask = document.getElementById('promptMask');
    var modal = document.getElementById('promptModal');
    if (mask) mask.classList.remove('show');
    if (modal) modal.classList.remove('show');
    if (_promptCallback) {
        var cb = _promptCallback;
        _promptCallback = null;
        cb(value);
    }
}

document.addEventListener('click', function(e) {
    if (e.target.tagName === 'A') {
        var href = e.target.getAttribute('href');
        if (href && href.indexOf('javascript:') !== 0 && href.indexOf('#') !== 0 && href.indexOf('http') !== 0 && href.indexOf(App.sidName + '=') === -1) {
            e.target.setAttribute('href', App.withSid(href));
        }
    }
});
