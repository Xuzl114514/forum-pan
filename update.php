<?php
require_once 'config.php';
isLogin();
renderPageStart('Forum Pan v2.0 更新公告', 'update');
?>

<style>
.update-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.update-hero {
    text-align: center;
    padding: 40px 20px;
    background: linear-gradient(135deg, var(--bg-card), var(--bg-tertiary));
    border-radius: 16px;
    border: 1px solid var(--border-default);
    margin-bottom: 24px;
}

.update-version-badge {
    display: inline-block;
    padding: 6px 16px;
    background: var(--accent-glow);
    color: var(--accent-primary);
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 12px;
}

.update-title {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 8px 0;
}

.update-subtitle {
    font-size: 14px;
    color: var(--text-secondary);
    margin: 0;
}

.update-section {
    background: var(--bg-card);
    border: 1px solid var(--border-default);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
}

.update-section-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.update-section-icon {
    font-size: 22px;
}

.update-feature-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.update-feature-item {
    padding: 10px 0;
    border-bottom: 1px solid var(--border-subtle);
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.update-feature-item:last-child {
    border-bottom: none;
}

.update-feature-icon {
    font-size: 18px;
    flex-shrink: 0;
    width: 24px;
    text-align: center;
}

.update-feature-content {
    flex: 1;
}

.update-feature-title {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.update-feature-desc {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.6;
}

.update-feature-tag {
    display: inline-block;
    padding: 2px 8px;
    background: rgba(76, 175, 80, 0.15);
    color: #4caf50;
    border-radius: 4px;
    font-size: 11px;
    margin-left: 8px;
    font-weight: 500;
}

.update-feature-tag.fix {
    background: rgba(255, 152, 0, 0.15);
    color: #ff9800;
}

.update-feature-tag.new {
    background: rgba(33, 150, 243, 0.15);
    color: #2196f3;
}

.update-stats {
    display: flex;
    justify-content: space-around;
    padding: 20px 0;
    text-align: center;
}

.update-stat-item {
    text-align: center;
}

.update-stat-num {
    font-size: 24px;
    font-weight: 700;
    color: var(--accent-primary);
}

.update-stat-label {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 4px;
}

.update-footer {
    text-align: center;
    padding: 20px;
    color: var(--text-muted);
    font-size: 12px;
}

.update-highlight {
    background: linear-gradient(135deg, rgba(232, 168, 60, 0.1), rgba(232, 168, 60, 0.05));
    border: 1px solid rgba(232, 168, 60, 0.2);
}
}
</style>

<div class="update-container">

    <!-- 头部 -->
    <div class="update-hero">
        <span class="update-version-badge">v2.0 正式版</span>
        <h1 class="update-title">Forum Pan 全新升级</h1>
        <p class="update-subtitle">更多功能 · 更好体验 · 更强兼容</p>
        <div class="update-stats">
            <div class="update-stat-item">
                <div class="update-stat-num">10+</div>
                <div class="update-stat-label">新增功能</div>
            </div>
            <div class="update-stat-item">
                <div class="update-stat-num">20+</div>
                <div class="update-stat-label">问题修复</div>
            </div>
            <div class="update-stat-item">
                <div class="update-stat-num">5</div>
                <div class="update-stat-label">主题皮肤</div>
            </div>
        </div>
    </div>

    <!-- 头像系统 -->
    <div class="update-section">
        <div class="update-section-title">
            <span class="update-section-icon">👤</span>
            头像系统全面升级
        </div>
        <ul class="update-feature-list">
            <li class="update-feature-item">
                <span class="update-feature-icon">✅</span>
                <div class="update-feature-content">
                    <div class="update-feature-title">全站头像统一显示 <span class="update-feature-tag new">NEW</span></div>
                    <div class="update-feature-desc">头像现在上传的头像会在所有页面统一显示，包括侧边栏、帖子列表、帖子详情、评论、私聊、群聊等所有位置</div>
                </div>
            </li>
            <li class="update-feature-item">
                <span class="update-feature-icon">✅</span>
                <div class="update-feature-content">
                    <div class="update-feature-title">上传路径修复 <span class="update-feature-tag fix">FIX</span></div>
                    <div class="update-feature-desc">修复了头像上传后文件不保存的问题，现在上传头像立即生效</div>
                </div>
            </li>
            <li class="update-feature-item">
                <span class="update-feature-icon">✅</span>
                <div class="update-feature-content">
                    <div class="update-feature-title">管理后台头像 <span class="update-feature-tag new">NEW</span></div>
                    <div class="update-feature-desc">管理员可以在用户列表中看到所有用户的头像</div>
                </div>
            </li>
        </ul>
    </div>

    <!-- 网盘增强 -->
    <div class="update-section">
        <div class="update-section-title">
            <span class="update-section-icon">📁</span>
            网盘功能大增强
        </div>
        <ul class="update-feature-list">
            <li class="update-feature-item">
                <span class="update-feature-icon">🖼️</span>
                <div class="update-feature-content">
                    <div class="update-feature-title">图片预览 <span class="update-feature-tag new">NEW</span></div>
                    <div class="update-feature-desc">支持 JPG、PNG、GIF、BMP、WebP、SVG 等图片在线预览</div>
                </div>
            </li>
            <li class="update-feature-item">
                <span class="update-feature-icon">🎬</span>
                <div class="update-feature-content">
                    <div class="update-feature-title">视频播放 <span class="update-feature-tag new">NEW</span></div>
                    <div class="update-feature-desc">支持 MP4、WebM、OGG、MOV 等视频格式在线播放</div>
                </div>
            </li>
            <li class="update-feature-item">
                <span class="update-feature-icon">🎵</span>
                <div class="update-feature-content">
                    <div class="update-feature-title">音频播放 <span class="update-feature-tag new">NEW</span></div>
                    <div class="update-feature-desc">支持 MP3、WAV、OGG、FLAC、M4A 等音频格式在线播放</div>
                </div>
            </li>
            <li class="update-feature-item">
                <span class="update-feature-icon">📕</span>
                <div class="update-feature-content">
                    <div class="update-feature-title">PDF 预览 <span class="update-feature-tag new">NEW</span></div>
                    <div class="update-feature-desc">PDF 文件可直接在线预览，无需下载</div>
                </div>
            </li>
            <li class="update-feature-item">
                <span class="update-feature-icon">📝</span>
                <div class="update-feature-content">
                    <div class="update-feature-title">文本/代码查看 <span class="update-feature-tag new">NEW</span></div>
                    <div class="update-feature-desc">支持 TXT、MD、JSON、HTML、CSS、JS、PHP、Python、Java 等 30+ 种格式的代码查看</div>
                </div>
            </li>
        </ul>
    </div>

    <!-- 主题系统 -->
    <div class="update-section">
        <div class="update-section-title">
            <span class="update-section-icon">🎨</span>
            主题与界面优化
        </div>
        <ul class="update-feature-list">
            <li class="update-feature-item">
                <span class="update-feature-icon">✅</span>
                <div class="update-feature-content">
                    <div class="update-feature-title">主题切换全页面生效 <span class="update-feature-tag fix">FIX</span></div>
                    <div class="update-feature-desc">修复了个人中心、管理后台、帖子详情等页面主题不生效的问题</div>
                </div>
            </li>
            <li class="update-feature-item">
                <span class="update-feature-icon">✅</span>
                <div class="update-feature-content">
                    <div class="update-feature-title">确认弹窗修复 <span class="update-feature-tag fix">FIX</span></div>
                    <div class="update-feature-desc">修复了确认弹窗只显示半透明遮罩、内容看不见的问题</div>
                </div>
            </li>
            <li class="update-feature-item">
                <span class="update-feature-icon">✅</span>
                <div class="update-feature-content">
                    <div class="update-feature-title">低版本安卓兼容 <span class="update-feature-tag new">NEW</span></div>
                    <div class="update-feature-desc">优化了安卓7及更低版本系统的兼容性，提升旧设备也能正常使用</div>
                </div>
            </li>
        </ul>
    </div>

    <!-- 新增工具 -->
    <div class="update-section update-highlight">
        <div class="update-section-title">
            <span class="update-section-icon">🛠️</span>
            新增工具页面
        </div>
        <ul class="update-feature-list">
            <li class="update-feature-item">
                <span class="update-feature-icon">🔍</span>
                <div class="update-feature-content">
                    <div class="update-feature-title">浏览器功能检测 <span class="update-feature-tag new">NEW</span></div>
                    <div class="update-feature-desc">一键检测您的浏览器支持哪些功能，包括CSS、JavaScript、HTML5、媒体编解码、网络、设备、存储、安全等8大类70+项检测</div>
                </div>
            </li>
        </ul>
    </div>

    <!-- 权限优化 -->
    <div class="update-section">
        <div class="update-section-title">
            <span class="update-section-icon">🔐</span>
            权限与安全
        </div>
        <ul class="update-feature-list">
            <li class="update-feature-item">
                <span class="update-feature-icon">✅</span>
                <div class="update-feature-content">
                    <div class="update-feature-title">远程桌面权限控制 <span class="update-feature-tag fix">FIX</span></div>
                    <div class="update-feature-desc">远程桌面功能现在仅管理员可见，普通用户不会看到该入口</div>
                </div>
            </li>
        </ul>
    </div>

    <!-- 页脚 -->
    <div class="update-footer">
        Forum Pan v2.0 · 感谢您的使用与支持<br>
        如有问题或建议，欢迎在论坛反馈
    </div>

</div>

<?php renderPageEnd(); ?>
