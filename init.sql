-- =====================================================
-- Forum Pan 数据库初始化脚本
-- 版本: 2.0.0
-- 更新时间: 2026-06-23
-- 说明: 包含所有数据表结构和默认数据
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------
-- 用户表：存储用户账号信息
-- ---------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username` varchar(20) NOT NULL COMMENT '用户名（登录名）',
  `nickname` varchar(20) DEFAULT '' COMMENT '昵称（显示名）',
  `avatar` varchar(500) DEFAULT '' COMMENT '头像URL',
  `password` varchar(32) NOT NULL COMMENT '密码（MD5加密）',
  `role` tinyint(1) DEFAULT 0 COMMENT '角色：1=管理员，0=普通用户',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1=正常，0=禁用',
  `storage` bigint(20) DEFAULT 1073741824 COMMENT '存储配额（字节），默认1GB',
  `used_storage` bigint(20) DEFAULT 0 COMMENT '已用存储（字节）',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 默认管理员账号（密码：admin123）
INSERT INTO `users` VALUES (1,'admin','管理员','',md5('admin123'),1,1,1073741824,0,NOW());

-- ---------------------------------------------------
-- 注册验证码表：管理用户注册验证码
-- ---------------------------------------------------
DROP TABLE IF EXISTS `verify_codes`;
CREATE TABLE `verify_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '验证码ID',
  `code` char(8) NOT NULL COMMENT '8位验证码',
  `is_used` tinyint(1) DEFAULT 0 COMMENT '是否已使用：0=未使用，1=已使用',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '生成时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='注册验证码表';

-- ---------------------------------------------------
-- 帖子表：存储论坛帖子
-- ---------------------------------------------------
DROP TABLE IF EXISTS `posts`;
CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '帖子ID',
  `user_id` int(11) NOT NULL COMMENT '发帖用户ID',
  `title` varchar(100) NOT NULL COMMENT '帖子标题',
  `content` text NOT NULL COMMENT '帖子内容',
  `file_ids` varchar(255) DEFAULT '' COMMENT '关联附件ID列表',
  `is_top` tinyint(1) DEFAULT 0 COMMENT '是否置顶：0=否，1=是',
  `is_essence` tinyint(1) DEFAULT 0 COMMENT '是否精华：0=否，1=是',
  `is_recalled` tinyint(1) DEFAULT 0 COMMENT '是否撤回：0=否，1=是',
  `recall_time` datetime DEFAULT NULL COMMENT '撤回时间',
  `like_num` int(11) DEFAULT 0 COMMENT '点赞数',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '发帖时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_top` (`is_top`),
  KEY `is_essence` (`is_essence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='帖子表';

-- ---------------------------------------------------
-- 评论表：存储帖子评论
-- ---------------------------------------------------
DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '评论ID',
  `post_id` int(11) NOT NULL COMMENT '所属帖子ID',
  `user_id` int(11) NOT NULL COMMENT '评论用户ID',
  `content` text NOT NULL COMMENT '评论内容',
  `attachment_ids` varchar(255) DEFAULT '' COMMENT '附件ID列表',
  `is_recalled` tinyint(1) DEFAULT 0 COMMENT '是否撤回：0=否，1=是',
  `recall_time` datetime DEFAULT NULL COMMENT '撤回时间',
  `like_num` int(11) DEFAULT 0 COMMENT '点赞数',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '评论时间',
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='评论表';

-- ---------------------------------------------------
-- 点赞记录表：防止重复点赞
-- ---------------------------------------------------
DROP TABLE IF EXISTS `likes`;
CREATE TABLE `likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `user_id` int(11) NOT NULL COMMENT '点赞用户ID',
  `type` varchar(20) NOT NULL COMMENT '点赞类型：post=帖子，comment=评论',
  `target_id` int(11) NOT NULL COMMENT '目标ID',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '点赞时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_like` (`user_id`, `type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='点赞记录表';

-- ---------------------------------------------------
-- 附件表：统一管理所有上传的文件
-- ---------------------------------------------------
DROP TABLE IF EXISTS `attachments`;
CREATE TABLE `attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '附件ID',
  `user_id` int(11) NOT NULL COMMENT '上传用户ID',
  `file_name` varchar(255) NOT NULL COMMENT '原始文件名',
  `file_path` varchar(255) NOT NULL COMMENT '存储路径（文件系统或db://image/id）',
  `file_type` varchar(100) NOT NULL COMMENT 'MIME类型',
  `file_size` bigint(20) NOT NULL COMMENT '文件大小（字节）',
  `file_data` longblob COMMENT '文件二进制数据（仅图片存储在此）',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='附件表';

-- ---------------------------------------------------
-- 私聊消息表：存储一对一聊天消息
-- ---------------------------------------------------
DROP TABLE IF EXISTS `private_messages`;
CREATE TABLE `private_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '消息ID',
  `sender_id` int(11) NOT NULL COMMENT '发送者ID',
  `receiver_id` int(11) NOT NULL COMMENT '接收者ID',
  `content` text NOT NULL COMMENT '消息内容',
  `attachment_ids` varchar(255) DEFAULT '' COMMENT '附件ID列表',
  `is_read` tinyint(1) DEFAULT 0 COMMENT '是否已读：0=未读，1=已读',
  `is_recalled` tinyint(1) DEFAULT 0 COMMENT '是否撤回：0=否，1=是',
  `recall_time` datetime DEFAULT NULL COMMENT '撤回时间',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '发送时间',
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='私聊消息表';

-- ---------------------------------------------------
-- 群聊表：存储群组信息
-- ---------------------------------------------------
DROP TABLE IF EXISTS `group_chats`;
CREATE TABLE `group_chats` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '群组ID',
  `name` varchar(50) NOT NULL COMMENT '群组名称',
  `owner_id` int(11) NOT NULL COMMENT '群主ID',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `owner_id` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='群聊表';

-- ---------------------------------------------------
-- 群成员表：存储群组成员关系
-- ---------------------------------------------------
DROP TABLE IF EXISTS `group_members`;
CREATE TABLE `group_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `group_id` int(11) NOT NULL COMMENT '群组ID',
  `user_id` int(11) NOT NULL COMMENT '成员ID',
  `join_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '加入时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_member` (`group_id`, `user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='群成员表';

-- ---------------------------------------------------
-- 群消息表：存储群聊消息
-- ---------------------------------------------------
DROP TABLE IF EXISTS `group_messages`;
CREATE TABLE `group_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '消息ID',
  `group_id` int(11) NOT NULL COMMENT '群组ID',
  `sender_id` int(11) NOT NULL COMMENT '发送者ID',
  `content` text NOT NULL COMMENT '消息内容',
  `attachment_ids` varchar(255) DEFAULT '' COMMENT '附件ID列表',
  `is_recalled` tinyint(1) DEFAULT 0 COMMENT '是否撤回：0=否，1=是',
  `recall_time` datetime DEFAULT NULL COMMENT '撤回时间',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '发送时间',
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `sender_id` (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='群消息表';

-- ---------------------------------------------------
-- 系统设置表：存储全局配置
-- ---------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `setting_key` varchar(50) NOT NULL COMMENT '配置键',
  `setting_value` text COMMENT '配置值',
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统设置表';

-- ---------------------------------------------------
-- 文件表：存储用户上传文件
-- ---------------------------------------------------
DROP TABLE IF EXISTS `files`;
CREATE TABLE `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '文件ID',
  `user_id` int(11) NOT NULL COMMENT '上传用户ID',
  `file_name` varchar(255) NOT NULL COMMENT '原始文件名',
  `file_path` varchar(500) NOT NULL COMMENT '存储路径',
  `file_size` bigint(20) NOT NULL DEFAULT 0 COMMENT '文件大小（字节）',
  `file_type` varchar(100) DEFAULT '' COMMENT '文件MIME类型',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文件表';

-- 通知表
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '接收者用户ID',
  `type` varchar(30) NOT NULL COMMENT '类型：comment/like/system/message',
  `content` varchar(500) NOT NULL COMMENT '通知内容',
  `source_id` int(11) DEFAULT 0 COMMENT '来源ID（帖子/评论等）',
  `source_type` varchar(30) DEFAULT '' COMMENT '来源类型',
  `is_read` tinyint(1) DEFAULT 0 COMMENT '是否已读',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='通知表';

-- 敏感词表
DROP TABLE IF EXISTS `sensitive_words`;
CREATE TABLE `sensitive_words` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `word` varchar(100) NOT NULL COMMENT '敏感词',
  `level` tinyint(1) DEFAULT 1 COMMENT '级别：1=替换，2=拦截',
  PRIMARY KEY (`id`),
  UNIQUE KEY `word` (`word`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='敏感词表';

-- 消息已读状态表
DROP TABLE IF EXISTS `message_read_status`;
CREATE TABLE `message_read_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `message_id` int(11) NOT NULL COMMENT '消息ID',
  `message_type` varchar(20) NOT NULL COMMENT '类型：private/group',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_msg` (`user_id`, `message_id`, `message_type`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='消息已读状态表';

-- 文件分享表
DROP TABLE IF EXISTS `file_shares`;
CREATE TABLE `file_shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL COMMENT '文件ID',
  `share_code` varchar(32) NOT NULL COMMENT '分享码',
  `creator_id` int(11) NOT NULL COMMENT '创建人ID',
  `expire_time` datetime DEFAULT NULL COMMENT '过期时间（可空表示永不过期）',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `share_code` (`share_code`),
  KEY `file_id` (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文件分享表';

-- 公告表
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT '公告标题',
  `content` text NOT NULL COMMENT '公告内容',
  `is_top` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否置顶',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0-禁用，1-启用',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `is_top` (`is_top`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告表';

-- 公告已读表
DROP TABLE IF EXISTS `announcement_reads`;
CREATE TABLE `announcement_reads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `announcement_id` int(11) NOT NULL COMMENT '公告ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `read_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `announcement_user` (`announcement_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告已读表';

SET FOREIGN_KEY_CHECKS = 1;