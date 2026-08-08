## v2.0.0 — C/S 架构重构版

### 架构变更

- **C/S 架构**：总端（TCP 数据库服务） + 多客户端节点（PHP Web 应用）
- 总端集中管理 MySQL，客户端通过 JSON 行协议（TCP 端口 9527）通信
- 客户端之间通过总端共享数据，实时互通
- 终端设备仅需内网 HTTP 访问客户端，**无需外网能力**

### 跨平台支持

| 组件 | Linux | Windows |
|------|-------|---------|
| 服务端（总端） | ✅ `server/start_server.sh` | ✅ `server/启动总端.bat` |
| 客户端 | ✅ PHP 内置服务器 | ✅ phpstudy 集成环境 |

**Linux 服务端 ↔ Windows 客户端 TCP 协议完全互通**

### 安全修复

- **SQL 注入**：所有用户输入统一使用 `tcp_real_escape_string()` 转义
- **SQL 注入**：`type` 参数增加白名单验证（仅允许 `post`/`comment`）
- **存储型 XSS**：私聊/群聊消息渲染前增加 `escapeHtml()` HTML 实体编码
- **弱密码**：MD5 替换为 `password_hash()`/`password_verify()`，旧密码自动升级
- **tcp_db 降级防护**：TCP 连接失败时返回 `false`，不再降级到不安全的 `addslashes()`

### 无 Cookie 适配

- Session ID 通过 URL 参数传递（`PHPSESSID`）
- 主题和分页设置使用 Cookie → Session 双存储架构
- 适配不支持 Cookie 的嵌入式设备浏览器

### 目录结构

```
server/      ← 总端（部署在外网服务器）
  ├── db_server.php        TCP 数据库服务
  ├── start_server.sh      Linux 启动脚本
  ├── 启动总端.bat         Windows 启动脚本
  ├── init.sql             数据库初始化
  └── sql/                 增量迁移脚本

[根目录]     ← 客户端节点（部署在内网 phpstudy）
  ├── config.php           配置总端 IP
  ├── tcp_db.php           TCP 数据库层
  ├── 启动客户端节点.bat   Windows 启动脚本
  ├── api/                 API 接口
  ├── static/              静态资源
  └── ...
```

### 快速开始

**总端（Linux）**：
```bash
mysql -u root -p < server/init.sql
chmod +x server/start_server.sh
./server/start_server.sh
```

**客户端（Windows + phpstudy）**：
1. 修改 `config.php` 中 `TCP_HOST` 为总端 IP
2. phpstudy 创建网站，根目录指向项目文件夹
3. 浏览器访问 `http://localhost:8080`

### 默认账号

| 用户 | 密码 |
|------|------|
| admin | admin123 |

> 首次登录后密码自动升级为 bcrypt