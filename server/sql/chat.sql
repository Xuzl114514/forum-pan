-- 私聊消息表
DROP TABLE IF EXISTS `private_messages`;
CREATE TABLE `private_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL COMMENT '发送者ID',
  `receiver_id` int(11) NOT NULL COMMENT '接收者ID',
  `content` text NOT NULL COMMENT '消息内容',
  `is_read` tinyint(1) DEFAULT 0 COMMENT '0未读 1已读',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
