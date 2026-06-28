# Forum Pan — 轻量级论坛 + 即时通讯系统

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

基于原生 PHP + MySQL 开发的轻量级一体化 Web 应用，支持论坛发帖、私聊、群聊、网盘存储等功能，界面简洁现代，支持移动端访问。

---

## 功能

### 论坛模块
- **发布帖子** — 支持标题 + 内容，带附件上传
- **帖子列表** — 按置顶/时间排序，支持分页和每页条数选择
- **实时评论** — 3 秒自动刷新新评论，支持附件
- **点赞功能** — 帖子和评论均可点赞/取消，防重复
- **撤回功能** — 3 分钟内可撤回自己的内容，管理员无限制

### 即时通讯
- **私聊** — 与任意用户一对一聊天，未读红点提醒
- **群聊** — 创建多人群组，实时消息推送
- **消息撤回** — 3 分钟内可撤回，管理员无限制

### 网盘存储
- **文件上传** — 单文件最大 50MB，默认配额 1GB
- **文件预览** — 图片点击大图预览（暗色背景）
- **文件下载** — 支持所有文件类型
- **存储管理** — 实时显示已用/总配额，删除自动释放

### 用户管理
- **验证码注册** — 管理员生成 8 位验证码，用户凭码注册
- **限时开放注册** — 管理员可开启 5 分钟自由注册（无需验证码）
- **昵称系统** — 用户可设置昵称，优先显示昵称
- **修改密码** — 个人中心支持修改密码
- **多配色主题** — 支持琥珀金、粉钻、纯净白、纯黑、科技蓝 5 种配色

### 管理员功能
- 生成注册验证码
- 管理用户（禁用/删除）
- 管理帖子（置顶/精华/删除）
- 管理评论（删除）
- 敏感词管理（替换或拦截）
- 限时开放注册控制

---

## 技术栈

- **后端**: PHP 5.6+（推荐 7.3+）
- **数据库**: MySQL 5.7+（utf8mb4 字符集）
- **前端**: 原生 HTML / CSS / JavaScript
- **实时通讯**: AJAX 轮询
- **会话管理**: 支持 Cookie 和 URL 参数传递 Session ID

---

## 目录结构

```
forum_pan_release/
├── config.php            # 全局配置（数据库连接等）
├── init.sql              # 数据库初始化脚本
├── index.php             # 论坛首页（帖子列表）
├── login.php             # 登录/注册页面
├── post.php              # 帖子详情/发布
├── user.php              # 个人中心
├── admin.php             # 管理员后台
├── chat.php              # 私聊页面
├── group_chat.php        # 群聊页面
├── storage.php           # 网盘页面
├── share.php             # 分享页面
├── browser_test.php      # 浏览器检测
├── update.php            # 更新公告
├── go.php                # 外链跳转
├── install_recall.php    # 撤回通知安装
├── api/                  # API 接口
│   ├── user.php          # 用户相关
│   ├── forum.php         # 论坛相关
│   ├── chat.php          # 私聊相关
│   ├── group_chat.php    # 群聊相关
│   ├── like.php          # 点赞相关
│   ├── upload.php        # 上传接口
│   ├── announcement.php  # 公告接口
│   ├── storage.php       # 网盘接口
│   ├── get_file.php      # 文件获取
│   └── get_image.php     # 图片获取
├── static/
│   ├── css/style.css     # 全局样式
│   └── js/main.js        # 全局交互逻辑
├── error/                # HTTP 错误页面
├── uploads/              # 用户上传文件目录
└── README.md             # 本说明文件
```

---

## 快速部署

### 1. 环境要求

- PHP 5.6+（推荐 7.3+）
- MySQL 5.7+
- Apache / Nginx 或 任意 PHP Web 服务器

### 2. 配置数据库

编辑 `config.php`，修改数据库连接信息：

```php
$host = 'localhost';   // 数据库主机
$user = 'root';        // 数据库用户名
$pwd  = 'password';    // 数据库密码
$dbname = 'forum_pan'; // 数据库名
```

### 3. 初始化数据库

方法一：命令行导入

```bash
mysql -u root -p
CREATE DATABASE forum_pan DEFAULT CHARSET utf8mb4;
USE forum_pan;
SOURCE init.sql;
```

方法二：phpMyAdmin 导入
1. 创建数据库 `forum_pan`，字符集选择 `utf8mb4`
2. 点击"导入"，选择 `init.sql` 文件

### 4. 设置目录权限

确保 `uploads/` 目录可写：

```bash
chmod -R 777 uploads/
```

### 5. 访问系统

浏览器打开项目地址：

```
http://localhost/forum_pan_release/
```

---

## 默认管理员账号

| 用户名 | 密码 | 角色 |
|--------|------|------|
| admin | admin123 | 管理员 |

> **首次登录后建议立即修改密码。**

---

## 使用流程

1. **管理员登录** → 进入后台 → 生成注册验证码
2. **新用户** → 使用验证码注册账号 → 登录系统
3. **论坛** → 浏览帖子 / 发布新帖 / 评论 / 点赞
4. **私聊** → 选择用户 → 实时聊天
5. **群聊** → 创建群组 → 邀请成员 → 多人聊天
6. **网盘** → 上传 / 下载 / 管理文件
7. **个人中心** → 修改昵称 / 修改密码 / 退出登录

---

## 管理员操作

1. 使用 `admin / admin123` 登录
2. 点击侧边栏"管理后台"
3. 功能包括：
   - **生成注册验证码** — 生成 8 位验证码供新用户注册
   - **限时开放注册** — 开启 5 分钟免验证码注册
   - **敏感词管理** — 添加敏感词，选择替换为 `***` 或直接拦截
   - **用户管理** — 查看所有用户，支持禁用/删除
   - **帖子管理** — 置顶/取消置顶、设为精华、删除帖子
   - **评论管理** — 删除违规评论

---

## API 接口

### 用户模块 (`api/user.php`)

| 接口 | 说明 |
|------|------|
| `?act=login` | 用户登录（POST） |
| `?act=register` | 用户注册（POST） |
| `?act=logout` | 退出登录 |
| `?act=edit_pwd` | 修改密码 |
| `?act=edit_nickname` | 修改昵称 |
| `?act=create_code` | 生成注册码（管理员） |
| `?act=del&id={n}` | 删除用户（管理员） |
| `?act=get_open_status` | 获取开放注册状态 |

### 论坛模块 (`api/forum.php`)

| 接口 | 说明 |
|------|------|
| `?act=add` | 发布帖子（POST） |
| `?act=comment` | 发表评论（POST） |
| `?act=get_comments&id={n}` | 获取评论列表 |
| `?act=new_comments&id={n}&last_id={n}` | 获取新评论 |
| `?act=del_post&id={n}` | 删除帖子（管理员） |
| `?act=del_comment&id={n}` | 删除评论（管理员） |
| `?act=toggle_top&id={n}` | 置顶/取消（管理员） |

### 点赞模块 (`api/like.php`)

| 接口 | 说明 |
|------|------|
| `?act=toggle&type=post&id={n}` | 帖子点赞/取消 |
| `?act=toggle&type=comment&id={n}` | 评论点赞/取消 |

### 私聊模块 (`api/chat.php`)

| 接口 | 说明 |
|------|------|
| `?act=get_users` | 获取可聊天用户列表 |
| `?act=get_history&other_uid={n}` | 获取聊天记录 |
| `?act=send` | 发送消息（POST） |
| `?act=recall&id={n}` | 撤回消息 |
| `?act=get_unread_count` | 获取未读消息数 |

### 网盘模块 (`api/storage.php`)

| 接口 | 说明 |
|------|------|
| `?act=stats` | 获取存储统计 |
| `?act=list` | 获取文件列表 |
| `?act=upload` | 上传文件（POST，字段 `file`） |
| `?act=delete&id={n}` | 删除文件 |

---

## 注意事项

- 用户密码使用 MD5 存储，建议生产环境升级为 bcrypt
- 本项目未使用参数绑定，生产环境需加固防 SQL 注入
- 文件上传建议增加类型和大小校验
- 删除文件操作不可恢复，请谨慎操作

---

## 版本

v1.0（基于 Forum Pan v3.1.0 整理发布）