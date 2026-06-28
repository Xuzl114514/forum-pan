<?php
require_once 'config.php';
isLogin();
renderPageStart('浏览器功能检测', 'browser_test');

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

function parseBrowser($ua) {
    $result = ['browser' => 'Unknown', 'version' => 0, 'engine' => 'Unknown', 'os' => 'Unknown', 'mobile' => false];
    if (preg_match('/Windows NT 10\.0/', $ua)) $result['os'] = 'Windows 10/11';
    elseif (preg_match('/Windows NT 6\.3/', $ua)) $result['os'] = 'Windows 8.1';
    elseif (preg_match('/Windows NT 6\.1/', $ua)) $result['os'] = 'Windows 7';
    elseif (preg_match('/Windows NT 5\.1/', $ua)) $result['os'] = 'Windows XP';
    elseif (preg_match('/Mac OS X (\d+[._]\d+)/', $ua, $m)) $result['os'] = 'macOS ' . str_replace('_', '.', $m[1]);
    elseif (preg_match('/Android (\d+(\.\d+)?)/', $ua, $m)) $result['os'] = 'Android ' . $m[1];
    elseif (preg_match('/iPhone OS (\d+)/', $ua, $m)) $result['os'] = 'iOS ' . $m[1];
    elseif (preg_match('/Linux/', $ua)) $result['os'] = 'Linux';
    if (preg_match('/Edg\/(\d+)/', $ua, $m)) { $result['browser'] = 'Microsoft Edge'; $result['version'] = $m[1]; $result['engine'] = 'Blink'; }
    elseif (preg_match('/Chrome\/(\d+)/', $ua, $m)) { $result['browser'] = 'Chrome'; $result['version'] = $m[1]; $result['engine'] = 'Blink'; }
    elseif (preg_match('/Firefox\/(\d+)/', $ua, $m)) { $result['browser'] = 'Firefox'; $result['version'] = $m[1]; $result['engine'] = 'Gecko'; }
    elseif (preg_match('/Safari\/(\d+)/', $ua) && !preg_match('/Chrome\//', $ua)) { $result['browser'] = 'Safari'; $result['engine'] = 'WebKit'; if (preg_match('/Version\/(\d+)/', $ua, $mv)) $result['version'] = $mv[1]; }
    elseif (preg_match('/MSIE (\d+)/', $ua, $m)) { $result['browser'] = 'IE'; $result['version'] = $m[1]; $result['engine'] = 'Trident'; }
    elseif (preg_match('/Trident.*rv:(\d+)/', $ua, $m)) { $result['browser'] = 'IE'; $result['version'] = $m[1]; $result['engine'] = 'Trident'; }
    if (preg_match('/Mobile|Android|iPhone|iPad/', $ua)) $result['mobile'] = true;
    return $result;
}

$browser = parseBrowser($userAgent);
$isLowVersion = ($browser['browser'] === 'IE') || ($browser['browser'] === 'Chrome' && $browser['version'] < 55) || ($browser['browser'] === 'Firefox' && $browser['version'] < 50);
?>

<style>
.test-container { max-width: 1000px; margin: 0 auto; padding: 20px; }
.test-card { background: var(--bg-card); border: 1px solid var(--border-default); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
.test-title { font-size: 18px; font-weight: 600; color: var(--text-primary); margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; gap: 8px; }
.test-row { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border-subtle); }
.test-row:last-child { border-bottom: none; }
.test-label { width: 200px; color: var(--text-secondary); font-size: 14px; }
.test-value { flex: 1; color: var(--text-primary); font-size: 14px; font-weight: 500; }
.test-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.version-badge { background: var(--accent-glow); color: var(--accent-primary); }
.warning-box { background: rgba(255, 152, 0, 0.1); border: 1px solid rgba(255, 152, 0, 0.3); border-radius: 8px; padding: 16px; margin-bottom: 20px; }
.warning-box h4 { color: #ff9800; margin: 0 0 8px 0; font-size: 14px; }
.warning-box p { color: var(--text-secondary); margin: 0; font-size: 13px; line-height: 1.6; }
.success-box { background: rgba(76, 175, 80, 0.1); border: 1px solid rgba(76, 175, 80, 0.3); border-radius: 8px; padding: 16px; margin-bottom: 20px; }
.success-box h4 { color: #4caf50; margin: 0 0 8px 0; font-size: 14px; }
.success-box p { color: var(--text-secondary); margin: 0; font-size: 13px; line-height: 1.6; }
.feature-grid { display: flex; flex-wrap: wrap; margin: -8px; }
.feature-item { width: 180px; padding: 8px; }
.feature-name { color: var(--text-secondary); font-size: 12px; margin-bottom: 6px; }
.progress-bar { height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden; }
.progress-fill { height: 100%; border-radius: 3px; transition: width 0.3s; }
.score-excellent { background: #4caf50; }
.score-poor { background: #f44336; }
.summary-row { display: flex; justify-content: space-around; padding: 20px 0; }
.summary-item { text-align: center; }
.summary-num { font-size: 28px; font-weight: 700; color: var(--accent-primary); }
.summary-label { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
</style>

<div class="test-container">
    <h1 style="text-align:center;margin-bottom:30px;font-size:24px;">浏览器功能检测报告</h1>

    <?php if ($isLowVersion): ?>
    <div class="warning-box"><h4>检测到低版本浏览器</h4><p>您的浏览器版本较低，可能会影响部分功能体验。建议升级到最新版本的浏览器以获得最佳体验。</p></div>
    <?php else: ?>
    <div class="success-box"><h4>浏览器版本正常</h4><p>您的浏览器版本较新，支持大部分现代Web功能。</p></div>
    <?php endif; ?>

    <!-- 浏览器信息 -->
    <div class="test-card">
        <div class="test-title">浏览器信息</div>
        <div class="test-row"><span class="test-label">浏览器</span><span class="test-value"><?= htmlspecialchars($browser['browser']) ?></span></div>
        <div class="test-row"><span class="test-label">版本</span><span class="test-value"><span class="test-badge version-badge"><?= htmlspecialchars($browser['version']) ?></span></span></div>
        <div class="test-row"><span class="test-label">渲染引擎</span><span class="test-value"><?= htmlspecialchars($browser['engine']) ?></span></div>
        <div class="test-row"><span class="test-label">操作系统</span><span class="test-value"><?= htmlspecialchars($browser['os']) ?></span></div>
        <div class="test-row"><span class="test-label">设备类型</span><span class="test-value"><?= $browser['mobile'] ? '移动设备' : '桌面设备' ?></span></div>
        <div class="test-row"><span class="test-label">屏幕分辨率</span><span class="test-value" id="screen-info">检测中...</span></div>
        <div class="test-row"><span class="test-label">窗口尺寸</span><span class="test-value" id="window-info">检测中...</span></div>
        <div class="test-row"><span class="test-label">DPR</span><span class="test-value" id="dpr-info">检测中...</span></div>
        <div class="test-row"><span class="test-label">触控点</span><span class="test-value" id="touch-info">检测中...</span></div>
    </div>

    <!-- 功能汇总 -->
    <div class="test-card">
        <div class="test-title">功能支持汇总</div>
        <div class="summary-row">
            <div class="summary-item"><div class="summary-num" id="total-count">0</div><div class="summary-label">检测项目</div></div>
            <div class="summary-item"><div class="summary-num" id="pass-count" style="color:#4caf50;">0</div><div class="summary-label">支持</div></div>
            <div class="summary-item"><div class="summary-num" id="fail-count" style="color:#f44336;">0</div><div class="summary-label">不支持</div></div>
            <div class="summary-item"><div class="summary-num" id="pass-rate">0%</div><div class="summary-label">支持率</div></div>
        </div>
    </div>

    <!-- CSS功能 -->
    <div class="test-card">
        <div class="test-title">CSS 功能检测</div>
        <div id="css-tests" class="feature-grid">
            <div class="feature-item"><div class="feature-name">CSS 变量</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="css-vars" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Flexbox</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="flexbox" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">CSS Grid</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="css-grid" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">backdrop-filter</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="backdrop-filter" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">transform 3D</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="transform3d" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">transition</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="transition" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">animation</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="animation" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">calc()</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="calc" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">object-fit</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="object-fit" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">rgba/hsla</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="rgba" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">var()</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="css-vars-fn" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">clamp()</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="clamp" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">filter</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="filter" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">clip-path</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="clip-path" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">columns</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="columns" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">text-overflow</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="text-overflow" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">appearance</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="appearance" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">writing-mode</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="writing-mode" style="width:0%"></div></div></div>
        </div>
    </div>

    <!-- JavaScript功能 -->
    <div class="test-card">
        <div class="test-title">JavaScript 功能检测</div>
        <div id="js-tests" class="feature-grid">
            <div class="feature-item"><div class="feature-name">ES6+</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="es6" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">ES Module</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="esm" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Fetch API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="fetch" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">LocalStorage</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="localstorage" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">SessionStorage</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="sessionstorage" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">WebSocket</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="websocket" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">File API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="file-api" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">FormData</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="formdata" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">XMLHttpRequest</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="xhr" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Intersection Observer</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="intersection-observer" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Mutation Observer</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="mutation-observer" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Resize Observer</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="resize-observer" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Performance API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="performance" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Web Worker</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="webworker" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">SharedWorker</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="sharedworker" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Service Worker</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="serviceworker" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Beacon API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="beacon" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Crypto API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="crypto" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">URL API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="url" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Proxy</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="proxy" style="width:0%"></div></div></div>
        </div>
    </div>

    <!-- HTML5功能 -->
    <div class="test-card">
        <div class="test-title">HTML5 功能检测</div>
        <div id="html5-tests" class="feature-grid">
            <div class="feature-item"><div class="feature-name">Canvas 2D</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="canvas" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Canvas WebGL</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="webgl" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Video 元素</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="video" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Audio 元素</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="audio" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">SVG 支持</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="svg" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">History API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="history" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Drag and Drop</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="dragdrop" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">ContentEditable</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="contenteditable" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">IndexedDB</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="indexeddb" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">WebSQL</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="websql" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Notifications</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="notifications" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">WebRTC</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="webrtc" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">MediaDevices</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="mediadevices" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Geolocation</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="geolocation" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Pointer Events</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="pointer-events" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Gamepad API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="gamepad" style="width:0%"></div></div></div>
        </div>
    </div>

    <!-- 媒体编解码 -->
    <div class="test-card">
        <div class="test-title">媒体编解码支持</div>
        <div id="media-tests" class="feature-grid">
            <div class="feature-item"><div class="feature-name">MP4 (H.264)</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="codec-h264" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">WebM (VP8)</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="codec-vp8" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">WebM (VP9)</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="codec-vp9" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Opus 音频</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="codec-opus" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">AAC 音频</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="codec-aac" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">MP3 音频</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="codec-mp3" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">OGG 音频</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="codec-ogg" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">WAV 音频</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="codec-wav" style="width:0%"></div></div></div>
        </div>
    </div>

    <!-- 网络与连接 -->
    <div class="test-card">
        <div class="test-title">网络与连接功能</div>
        <div id="network-tests" class="feature-grid">
            <div class="feature-item"><div class="feature-name">Online Status</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="online" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Network Info</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="networkinfo" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">SSE (EventSource)</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="eventsource" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Fetch Stream</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="fetch-stream" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">WebSocket</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="websocket" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">RTCDataChannel</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="rtcdatachannel" style="width:0%"></div></div></div>
        </div>
    </div>

    <!-- 设备功能 -->
    <div class="test-card">
        <div class="test-title">设备与硬件功能</div>
        <div id="device-tests" class="feature-grid">
            <div class="feature-item"><div class="feature-name">触摸屏支持</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="touch" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Fullscreen API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="fullscreen" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Wake Lock</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="wagelock" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Vibration</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="vibration" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Device Motion</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="devicemotion" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Device Orientation</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="deviceorientation" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Battery API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="battery" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Clipboard API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="clipboard" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Share API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="share" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Bluetooth API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="bluetooth" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">USB API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="usb" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">NFC API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="nfc" style="width:0%"></div></div></div>
        </div>
    </div>

    <!-- 存储功能 -->
    <div class="test-card">
        <div class="test-title">存储与缓存功能</div>
        <div id="storage-tests" class="feature-grid">
            <div class="feature-item"><div class="feature-name">LocalStorage</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="localstorage" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">SessionStorage</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="sessionstorage" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">IndexedDB</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="indexeddb" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">FileSystem API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="filesystem" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Cache API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="cacheapi" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">StorageManager</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="storagemanager" style="width:0%"></div></div></div>
        </div>
    </div>

    <!-- 安全功能 -->
    <div class="test-card">
        <div class="test-title">安全与隐私功能</div>
        <div id="security-tests" class="feature-grid">
            <div class="feature-item"><div class="feature-name">HTTPS</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="https" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Secure Context</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="securecontext" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Permissions API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="permissions" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">Credential API</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="credential" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">SubtleCrypto</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="subtlecrypto" style="width:0%"></div></div></div>
            <div class="feature-item"><div class="feature-name">CSP</div><div class="progress-bar"><div class="progress-fill score-excellent" data-test="csp" style="width:0%"></div></div></div>
        </div>
    </div>

    <!-- 兼容性问题汇总 -->
    <div class="test-card">
        <div class="test-title">兼容性问题汇总</div>
        <div id="issues-list" style="color:var(--text-secondary);font-size:14px;">检测中...</div>
    </div>
</div>

<script>
(function() {
    var results = {};

    // 设备信息
    document.getElementById('screen-info').textContent = screen.width + ' x ' + screen.height;
    document.getElementById('window-info').textContent = window.innerWidth + ' x ' + window.innerHeight;
    document.getElementById('dpr-info').textContent = window.devicePixelRatio || 'N/A';
    document.getElementById('touch-info').textContent = navigator.maxTouchPoints > 0 ? navigator.maxTouchPoints + ' 点' : '不支持';

    function testColor(rgba) {
        var el = document.createElement('div');
        el.style.cssText = 'color:' + rgba;
        document.body.appendChild(el);
        var computed = window.getComputedStyle(el).color;
        document.body.removeChild(el);
        return computed !== '' && computed !== 'transparent';
    }

    // CSS功能
    var cssTests = {
        'css-vars': function() { return window.CSS && window.CSS.supports && window.CSS.supports('--test', '0'); },
        'flexbox': function() { var el = document.createElement('div'); el.style.cssText = 'display:flex'; return el.style.display === 'flex'; },
        'css-grid': function() { var el = document.createElement('div'); el.style.cssText = 'display:grid'; return el.style.display === 'grid'; },
        'backdrop-filter': function() { var el = document.createElement('div'); el.style.cssText = 'backdrop-filter:blur(10px)'; return el.style.backdropFilter !== undefined; },
        'transform3d': function() { var el = document.createElement('div'); el.style.cssText = 'transform:translateZ(0)'; return el.style.transform !== undefined; },
        'transition': function() { var el = document.createElement('div'); el.style.cssText = 'transition:all 0s'; return el.style.transition !== undefined; },
        'animation': function() { var el = document.createElement('div'); el.style.cssText = 'animation:test 0s'; return el.style.animation !== undefined; },
        'calc': function() { var el = document.createElement('div'); el.style.cssText = 'width:calc(100% - 10px)'; return el.style.width.indexOf('calc') !== -1; },
        'object-fit': function() { var el = document.createElement('div'); el.style.cssText = 'object-fit:cover'; return el.style.objectFit === 'cover'; },
        'rgba': function() { return testColor('rgba(0,0,0,0.5)'); },
        'css-vars-fn': function() { return window.CSS && window.CSS.supports && window.CSS.supports('color', 'var(--test)'); },
        'clamp': function() { var el = document.createElement('div'); el.style.cssText = 'width:clamp(10px, 50%, 100px)'; return el.style.width.indexOf('clamp') !== -1; },
        'filter': function() { var el = document.createElement('div'); el.style.cssText = 'filter:blur(1px)'; return el.style.filter !== undefined; },
        'clip-path': function() { var el = document.createElement('div'); el.style.cssText = 'clip-path:circle(50%)'; return el.style.clipPath !== undefined; },
        'columns': function() { var el = document.createElement('div'); el.style.cssText = 'column-count:2'; return el.style.columnCount !== undefined; },
        'text-overflow': function() { var el = document.createElement('div'); el.style.cssText = 'text-overflow:ellipsis'; return el.style.textOverflow === 'ellipsis'; },
        'appearance': function() { var el = document.createElement('div'); el.style.cssText = 'appearance:none'; return el.style.webkitAppearance !== undefined || el.style.mozAppearance !== undefined || el.style.appearance !== undefined; },
        'writing-mode': function() { var el = document.createElement('div'); el.style.cssText = 'writing-mode:vertical-rl'; return el.style.writingMode !== undefined; }
    };

    // JavaScript功能
    var jsTests = {
        'es6': function() { try { new Function('let x = 1'); return typeof Promise !== 'undefined' && typeof Map !== 'undefined'; } catch(e) { return false; } },
        'esm': function() { return typeof document !== 'undefined' && 'noModule' in document.createElement('script'); },
        'fetch': function() { return typeof fetch === 'function'; },
        'localstorage': function() { try { localStorage.setItem('t', 1); localStorage.removeItem('t'); return true; } catch(e) { return false; } },
        'sessionstorage': function() { try { sessionStorage.setItem('t', 1); sessionStorage.removeItem('t'); return true; } catch(e) { return false; } },
        'websocket': function() { return typeof WebSocket !== 'undefined'; },
        'file-api': function() { return typeof FileReader !== 'undefined'; },
        'formdata': function() { return typeof FormData !== 'undefined'; },
        'xhr': function() { return typeof XMLHttpRequest !== 'undefined'; },
        'intersection-observer': function() { return 'IntersectionObserver' in window; },
        'mutation-observer': function() { return 'MutationObserver' in window; },
        'resize-observer': function() { return 'ResizeObserver' in window; },
        'performance': function() { return 'performance' in window && performance.now !== undefined; },
        'webworker': function() { return typeof Worker !== 'undefined'; },
        'sharedworker': function() { return typeof SharedWorker !== 'undefined'; },
        'serviceworker': function() { return 'serviceWorker' in navigator; },
        'beacon': function() { return typeof navigator.sendBeacon === 'function'; },
        'crypto': function() { return typeof crypto !== 'undefined' || typeof window.crypto !== 'undefined'; },
        'url': function() { try { new URL('http://test.com'); return true; } catch(e) { return false; } },
        'proxy': function() { return typeof Proxy !== 'undefined'; }
    };

    // HTML5功能
    var html5Tests = {
        'canvas': function() { var el = document.createElement('canvas'); return !!(el.getContext && el.getContext('2d')); },
        'webgl': function() { var el = document.createElement('canvas'); return !!(el.getContext('webgl') || el.getContext('experimental-webgl')); },
        'video': function() { var el = document.createElement('video'); return !!el.canPlayType; },
        'audio': function() { var el = document.createElement('audio'); return !!el.canPlayType; },
        'svg': function() { var el = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); return !!el.createSVGMatrix; },
        'history': function() { return !!(window.history && window.history.pushState); },
        'dragdrop': function() { return 'draggable' in document.createElement('span'); },
        'contenteditable': function() { return 'contentEditable' in document.createElement('div'); },
        'indexeddb': function() { return 'indexedDB' in window; },
        'websql': function() { return typeof openDatabase !== 'undefined'; },
        'notifications': function() { return 'Notification' in window; },
        'webrtc': function() { return !!(window.RTCPeerConnection || window.mozRTCPeerConnection || window.webkitRTCPeerConnection); },
        'mediadevices': function() { return navigator.mediaDevices && !!navigator.mediaDevices.getUserMedia; },
        'geolocation': function() { return 'geolocation' in navigator; },
        'pointer-events': function() { return window.PointerEvent !== undefined; },
        'gamepad': function() { return 'getGamepads' in navigator; }
    };

    // 媒体编解码
    var mediaTests = {
        'codec-h264': function() { var v = document.createElement('video'); return !!(v.canPlayType && v.canPlayType('video/mp4; codecs="avc1.42E01E"').replace(/no/, '')); },
        'codec-vp8': function() { var v = document.createElement('video'); return !!(v.canPlayType && v.canPlayType('video/webm; codecs="vp8"').replace(/no/, '')); },
        'codec-vp9': function() { var v = document.createElement('video'); return !!(v.canPlayType && v.canPlayType('video/webm; codecs="vp9"').replace(/no/, '')); },
        'codec-opus': function() { var a = document.createElement('audio'); return !!(a.canPlayType && a.canPlayType('audio/opus').replace(/no/, '')); },
        'codec-aac': function() { var a = document.createElement('audio'); return !!(a.canPlayType && a.canPlayType('audio/aac').replace(/no/, '')); },
        'codec-mp3': function() { var a = document.createElement('audio'); return !!(a.canPlayType && a.canPlayType('audio/mpeg').replace(/no/, '')); },
        'codec-ogg': function() { var a = document.createElement('audio'); return !!(a.canPlayType && a.canPlayType('audio/ogg').replace(/no/, '')); },
        'codec-wav': function() { var a = document.createElement('audio'); return !!(a.canPlayType && a.canPlayType('audio/wav').replace(/no/, '')); }
    };

    // 网络功能
    var networkTests = {
        'online': function() { return navigator.onLine !== undefined; },
        'networkinfo': function() { return navigator.connection !== undefined || navigator.mozConnection !== undefined || navigator.webkitConnection !== undefined; },
        'eventsource': function() { return typeof EventSource !== 'undefined'; },
        'fetch-stream': function() { return typeof ReadableStream !== 'undefined'; },
        'websocket': function() { return typeof WebSocket !== 'undefined'; },
        'rtcdatachannel': function() { return !!(window.RTCPeerConnection || window.mozRTCPeerConnection || window.webkitRTCPeerConnection); }
    };

    // 设备功能
    var deviceTests = {
        'touch': function() { return ('ontouchstart' in window) || (navigator.maxTouchPoints > 0); },
        'fullscreen': function() { return document.fullscreenEnabled || document.webkitFullscreenEnabled || document.mozFullScreenEnabled; },
        'wagelock': function() { return 'wakeLock' in navigator; },
        'vibration': function() { return 'vibrate' in navigator; },
        'devicemotion': function() { return 'DeviceMotionEvent' in window; },
        'deviceorientation': function() { return 'DeviceOrientationEvent' in window; },
        'battery': function() { return 'getBattery' in navigator; },
        'clipboard': function() { return !!(navigator.clipboard && navigator.clipboard.writeText); },
        'share': function() { return navigator.share !== undefined; },
        'bluetooth': function() { return 'bluetooth' in navigator; },
        'usb': function() { return 'usb' in navigator; },
        'nfc': function() { return 'nfc' in navigator; }
    };

    // 存储功能
    var storageTests = {
        'localstorage': function() { try { localStorage.setItem('t', 1); localStorage.removeItem('t'); return true; } catch(e) { return false; } },
        'sessionstorage': function() { try { sessionStorage.setItem('t', 1); sessionStorage.removeItem('t'); return true; } catch(e) { return false; } },
        'indexeddb': function() { return 'indexedDB' in window; },
        'filesystem': function() { return 'webkitRequestFileSystem' in window || 'requestFileSystem' in window; },
        'cacheapi': function() { return 'caches' in window; },
        'storagemanager': function() { return navigator.storage && navigator.storage.estimate !== undefined; }
    };

    // 安全功能
    var securityTests = {
        'https': function() { return location.protocol === 'https:'; },
        'securecontext': function() { return window.isSecureContext !== undefined ? window.isSecureContext : location.protocol === 'https:'; },
        'permissions': function() { return 'permissions' in navigator; },
        'credential': function() { return window.CredentialManager !== undefined || 'credentials' in navigator; },
        'subtlecrypto': function() { return window.crypto && window.crypto.subtle !== undefined; },
        'csp': function() { return typeof CSP !== 'undefined' || document.security !== undefined; }
    };

    var allTests = [cssTests, jsTests, html5Tests, mediaTests, networkTests, deviceTests, storageTests, securityTests];

    for (var i = 0; i < allTests.length; i++) {
        var tests = allTests[i];
        for (var key in tests) {
            if (tests.hasOwnProperty(key)) {
                var passed = false;
                try { passed = tests[key](); } catch(e) { passed = false; }
                results[key] = passed;
                var bar = document.querySelector('[data-test="' + key + '"]');
                if (bar) {
                    bar.style.width = passed ? '100%' : '20%';
                }
            }
        }
    }

    // 统计
    var total = 0, passed = 0, failed = 0;
    for (var key in results) {
        if (results.hasOwnProperty(key)) {
            total++;
            if (results[key]) passed++; else failed++;
        }
    }
    var rate = total > 0 ? Math.round((passed / total) * 100) : 0;
    document.getElementById('total-count').textContent = total;
    document.getElementById('pass-count').textContent = passed;
    document.getElementById('fail-count').textContent = failed;
    document.getElementById('pass-rate').textContent = rate + '%';

    // 问题汇总
    var criticalIssues = [];
    var warnings = [];

    if (!results['es6']) criticalIssues.push('JavaScript ES6+ 不支持');
    if (!results['fetch']) warnings.push('Fetch API 不支持');
    if (!results['flexbox']) criticalIssues.push('Flexbox 不支持');
    if (!results['css-vars']) warnings.push('CSS 变量不支持');
    if (!results['localstorage']) warnings.push('LocalStorage 不支持');
    if (!results['canvas']) warnings.push('Canvas 2D 不支持');
    if (!results['video']) warnings.push('Video 元素不支持');
    if (!results['audio']) warnings.push('Audio 元素不支持');
    if (!results['backdrop-filter']) warnings.push('backdrop-filter 不支持');
    if (!results['css-grid']) warnings.push('CSS Grid 不支持');
    if (!results['indexeddb']) warnings.push('IndexedDB 不支持');
    if (!results['webgl']) warnings.push('WebGL 不支持');
    if (!results['svg']) warnings.push('SVG 不支持');
    if (!results['webrtc']) warnings.push('WebRTC 不支持');
    if (!results['serviceworker']) warnings.push('Service Worker 不支持');
    if (!results['https']) criticalIssues.push('非 HTTPS 连接，安全功能受限');

    var html = '';
    if (criticalIssues.length > 0) {
        html += '<div style="margin-bottom:12px;"><strong style="color:#f44336;">严重问题:</strong></div>';
        for (var j = 0; j < criticalIssues.length; j++) {
            html += '<div style="padding:8px 0;font-size:13px;">- ' + criticalIssues[j] + '</div>';
        }
    }
    if (warnings.length > 0) {
        html += '<div style="margin:12px 0;"><strong style="color:#ff9800;">警告:</strong></div>';
        for (var j = 0; j < warnings.length; j++) {
            html += '<div style="padding:8px 0;font-size:13px;">- ' + warnings[j] + '</div>';
        }
    }
    if (criticalIssues.length === 0 && warnings.length === 0) {
        html = '<div style="color:#4caf50;">未检测到兼容性问题，您的浏览器完全支持所有功能</div>';
    }
    document.getElementById('issues-list').innerHTML = html;
})();
</script>

<?php renderPageEnd(); ?>
