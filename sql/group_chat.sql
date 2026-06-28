-- 群聊表
DROP TABLE IF EXISTS `group_chats`;
CREATE TABLE `group_chats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '群聊名称',
  `creator_id` int(11) NOT NULL COMMENT '创建者ID',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 群聊成员表
DROP TABLE IF EXISTS `group_members`;
CREATE TABLE `group_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL COMMENT '群聊ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `join_time` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_user` (`group_id`, `user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 群聊消息表
DROP TABLE IF EXISTS `group_messages`;
CREATE TABLE `group_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL COMMENT '群聊ID',
  `sender_id` int(11) NOT NULL COMMENT '发送者ID',
  `content` text COMMENT '消息内容',
  `attachment_id` int(11) DEFAULT NULL COMMENT '附件ID',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `sender_id` (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 附件表
DROP TABLE IF EXISTS `attachments`;
CREATE TABLE `attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '上传用户ID',
  `file_name` varchar(255) NOT NULL COMMENT '文件名',
  `file_path` varchar(500) NOT NULL COMMENT '文件路径',
  `file_type` varchar(50) DEFAULT NULL COMMENT '文件类型',
  `file_size` int(11) DEFAULT 0 COMMENT '文件大小 (字节)',
  `upload_time` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 给私聊消息表添加附件字段
ALTER TABLE `private_messages` ADD COLUMN `attachment_id` int(11) DEFAULT NULL COMMENT '附件ID' AFTER `content`;

-- 给评论表添加附件字段
ALTER TABLE `comments` ADD COLUMN `attachment_id` int(11) DEFAULT NULL COMMENT '附件ID' AFTER `content`;
