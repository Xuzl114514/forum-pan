-- 给帖子表添加撤回字段
ALTER TABLE `posts` ADD COLUMN `is_recalled` tinyint(1) DEFAULT 0 COMMENT '是否撤回' AFTER `content`;
ALTER TABLE `posts` ADD COLUMN `recall_time` datetime DEFAULT NULL COMMENT '撤回时间' AFTER `is_recalled`;

-- 给私聊消息表添加撤回字段
ALTER TABLE `private_messages` ADD COLUMN `is_recalled` tinyint(1) DEFAULT 0 COMMENT '是否撤回' AFTER `attachment_id`;
ALTER TABLE `private_messages` ADD COLUMN `recall_time` datetime DEFAULT NULL COMMENT '撤回时间' AFTER `is_recalled`;

-- 给群聊消息表添加撤回字段
ALTER TABLE `group_messages` ADD COLUMN `is_recalled` tinyint(1) DEFAULT 0 COMMENT '是否撤回' AFTER `attachment_id`;
ALTER TABLE `group_messages` ADD COLUMN `recall_time` datetime DEFAULT NULL COMMENT '撤回时间' AFTER `is_recalled`;

-- 给评论表添加撤回字段
ALTER TABLE `comments` ADD COLUMN `is_recalled` tinyint(1) DEFAULT 0 COMMENT '是否撤回' AFTER `attachment_id`;
ALTER TABLE `comments` ADD COLUMN `recall_time` datetime DEFAULT NULL COMMENT '撤回时间' AFTER `is_recalled`;
